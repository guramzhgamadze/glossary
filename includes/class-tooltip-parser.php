<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Scans post content and wraps glossary term occurrences with tooltip spans.
 * Uses DOM parsing to avoid breaking HTML attributes, tags, or existing links.
 */
class WPGT_Tooltip_Parser {

    private static bool $initialized = false;

    public static function init() {
        if ( self::$initialized ) return;
        self::$initialized = true;
        add_filter( 'the_content', [ __CLASS__, 'parse_content' ], 12 );
    }

    /**
     * Main filter callback.
     */
    public static function parse_content( string $content, $widget = null ): string {
        if ( empty( $content ) ) return $content;

        $settings = WPGT_Settings::get_all();

        // Only run on singular pages of configured post types.
        // Note: do NOT check in_the_loop() here — Elementor widget filters fire outside the loop.
        if ( ! is_singular() ) return $content;
        if ( ! in_array( get_post_type(), (array) $settings['parse_post_types'], true ) ) {
            return $content;
        }

        $terms = WPGT_Post_Type::get_all_terms_for_parsing();
        if ( empty( $terms ) ) return $content;

        return self::inject_tooltips( $content, $terms, $settings );
    }

    /**
     * Walk text nodes in the HTML and wrap trigger words.
     */
    private static function inject_tooltips( string $html, array $terms, array $settings ): string {
        if ( ! class_exists( 'DOMDocument' ) ) {
            return self::inject_tooltips_regex( $html, $terms, $settings );
        }

        $doc = new DOMDocument();
        $use_libxml_errors = libxml_use_internal_errors( true );

        // Wrap in UTF-8 meta so DOMDocument handles encoding properly
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="wpgt-wrapper">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors( $use_libxml_errors );

        $wrapper = $doc->getElementById( 'wpgt-wrapper' );
        if ( ! $wrapper ) return $html;

        $already_seen  = []; // track first-occurrence per title
        $current_post  = get_the_ID();

        self::walk_node( $wrapper, $doc, $terms, $settings, $already_seen, $current_post );

        // Extract inner HTML of wrapper
        $inner = '';
        foreach ( $wrapper->childNodes as $child ) {
            $inner .= $doc->saveHTML( $child );
        }
        return $inner;
    }

    /**
     * Recursively walk DOM nodes; skip scripts, styles, links, and heading tags when configured.
     */
    private static function walk_node(
        DOMNode $node,
        DOMDocument $doc,
        array $terms,
        array $settings,
        array &$already_seen,
        int $current_post
    ) {
        $skip_tags = [ 'script', 'style', 'code', 'pre', 'textarea', 'button', 'select' ];
        if ( ! empty( $settings['exclude_links'] ) ) {
            $skip_tags[] = 'a';
        }
        if ( ! empty( $settings['exclude_headings'] ) ) {
            foreach ( [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] as $h ) {
                $skip_tags[] = $h;
            }
        }
        // Also skip our own tooltip spans to avoid double-wrapping
        $skip_tags[] = 'wpgt-span'; // placeholder

        if ( $node->nodeType === XML_ELEMENT_NODE ) {
            $tag = strtolower( $node->nodeName );
            if ( in_array( $tag, $skip_tags, true ) ) return;

            // Skip existing wpgt spans
            if ( $tag === 'span' && $node instanceof DOMElement && $node->getAttribute( 'data-wpgt' ) ) {
                return;
            }
        }

        // Process child nodes (collect first to avoid mutation issues)
        $children = [];
        foreach ( $node->childNodes as $child ) {
            $children[] = $child;
        }

        foreach ( $children as $child ) {
            if ( $child->nodeType === XML_TEXT_NODE ) {
                $replaced = self::replace_in_text_node( $child, $doc, $terms, $settings, $already_seen, $current_post );
                if ( $replaced ) {
                    // Replace text node with fragment
                    $node->replaceChild( $replaced, $child );
                }
            } elseif ( $child->nodeType === XML_ELEMENT_NODE ) {
                self::walk_node( $child, $doc, $terms, $settings, $already_seen, $current_post );
            }
        }
    }

    /**
     * Replace trigger words in a text node with tooltip spans.
     * Returns a DocumentFragment if replacements were made, null otherwise.
     *
     * IMPORTANT: all regex matching is done against the ORIGINAL plain-text nodeValue.
     * We collect every match (offset + length + term) first, resolve overlaps, then
     * do a single left-to-right pass to build the final HTML string.
     * This prevents the regex from accidentally matching text inside already-injected
     * <span> attribute values on subsequent iterations (which caused raw HTML to leak
     * into the rendered page as visible text).
     */
    private static function replace_in_text_node(
        DOMText $text_node,
        DOMDocument $doc,
        array $terms,
        array $settings,
        array &$already_seen,
        int $current_post
    ): ?DOMDocumentFragment {
        $text = $text_node->nodeValue;
        if ( trim( $text ) === '' ) return null;

        $case_flag  = ! empty( $settings['case_sensitive'] ) ? '' : 'i';
        $first_only = ! empty( $settings['first_occurrence'] );

        // --- Pass 1: collect all matches from the ORIGINAL plain text ---
        // Matching always runs against $text (never against accumulated HTML) so that
        // we never accidentally match text inside injected <span> attribute values.
        $matches_found = [];
        $seen_in_node  = []; // first_occurrence tracking within this text node

        foreach ( $terms as $term ) {
            if ( (int) $term['id'] === $current_post ) continue;

            foreach ( $term['triggers'] as $trigger ) {
                if ( empty( $trigger ) ) continue;

                $key = strtolower( $trigger );
                if ( $first_only && ( isset( $already_seen[ $key ] ) || isset( $seen_in_node[ $key ] ) ) ) {
                    continue;
                }

                // Build a stem-aware pattern (Georgian) or literal pattern (other scripts).
                // self::build_pattern() returns null if the pattern would be unsafe.
                $pattern = self::build_pattern( $trigger, $case_flag );
                if ( $pattern === null ) continue;

                if ( preg_match_all( $pattern, $text, $all_matches, PREG_OFFSET_CAPTURE ) === false ) continue;
                if ( empty( $all_matches[1] ) ) continue;

                // For first_occurrence only take the first hit in this node.
                $hits = $first_only ? [ $all_matches[1][0] ] : $all_matches[1];

                foreach ( $hits as $hit ) {
                    $matches_found[] = [
                        'offset'  => $hit[1],           // byte offset in $text
                        'length'  => strlen( $hit[0] ), // byte length
                        'matched' => $hit[0],
                        'term'    => $term,
                        'key'     => $key,
                    ];
                }

                $seen_in_node[ $key ] = true;
            }
        }

        if ( empty( $matches_found ) ) return null;

        // --- Pass 2: sort by offset, drop overlapping matches ---
        usort( $matches_found, fn( $a, $b ) => $a['offset'] <=> $b['offset'] );

        $non_overlapping = [];
        $last_end        = 0;
        foreach ( $matches_found as $m ) {
            if ( $m['offset'] >= $last_end ) {
                $non_overlapping[] = $m;
                $last_end = $m['offset'] + $m['length'];
            }
        }

        if ( empty( $non_overlapping ) ) return null;

        // --- Pass 3: single left-to-right pass to build HTML ---
        $html   = '';
        $cursor = 0;
        foreach ( $non_overlapping as $m ) {
            // Plain text before this match — escape for HTML output.
            $html .= esc_html( substr( $text, $cursor, $m['offset'] - $cursor ) );

            $term  = $m['term'];
            $id    = (int) $term['id'];
            $html .= sprintf(
                '<span class="wpgt-tooltip-trigger" data-wpgt="%d" data-tooltip="%s" data-title="%s" data-url="%s" tabindex="0" role="term" aria-describedby="wpgt-tip-%d">%s</span>',
                $id,
                esc_attr( $term['tooltip'] ),
                esc_attr( $term['title'] ),
                esc_url( $term['url'] ),
                $id,
                esc_html( $m['matched'] )
            );

            $cursor = $m['offset'] + $m['length'];
            $already_seen[ $m['key'] ] = true;
        }
        // Remaining plain text after the last match.
        $html .= esc_html( substr( $text, $cursor ) );

        // Build a DOMDocumentFragment from the HTML string.
        $fragment = $doc->createDocumentFragment();
        $tmp = new DOMDocument();
        libxml_use_internal_errors( true );
        $tmp->loadHTML(
            '<?xml encoding="UTF-8"><body>' . $html . '</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $body = $tmp->getElementsByTagName( 'body' )->item( 0 );
        if ( ! $body ) return null;

        foreach ( $body->childNodes as $child ) {
            $fragment->appendChild( $doc->importNode( $child, true ) );
        }

        return $fragment;
    }

    /**
     * Fallback regex-based replacement (no DOM).
     */
    private static function inject_tooltips_regex( string $html, array $terms, array $settings ): string {
        $case_flag    = ! empty( $settings['case_sensitive'] ) ? '' : 'i';
        $already_seen = [];

        foreach ( $terms as $term ) {
            foreach ( $term['triggers'] as $trigger ) {
                if ( empty( $trigger ) ) continue;
                $key = strtolower( $trigger );
                if ( ! empty( $settings['first_occurrence'] ) && isset( $already_seen[ $key ] ) ) continue;

                $pattern = self::build_pattern( $trigger, $case_flag );
                if ( $pattern === null ) continue;
                $limit = ! empty( $settings['first_occurrence'] ) ? 1 : -1;

                $new_html = preg_replace_callback(
                    $pattern,
                    function( $m ) use ( $term ) {
                        return sprintf(
                            '<span class="wpgt-tooltip-trigger" data-wpgt="%d" data-tooltip="%s" data-title="%s" data-url="%s" tabindex="0">%s</span>',
                            $term['id'],
                            esc_attr( $term['tooltip'] ),
                            esc_attr( $term['title'] ),
                            esc_url( $term['url'] ),
                            esc_html( $m[1] )
                        );
                    },
                    $html,
                    $limit
                );

                if ( $new_html !== $html ) {
                    $html = $new_html;
                    $already_seen[ $key ] = true;
                }
            }
        }

        return $html;
    }

    /**
     * Build the regex pattern for a trigger word.
     *
     * Georgian triggers: stem the trigger, then build a pattern that matches
     * the stem followed by any Georgian suffix. This makes the glossary
     * declension-aware — "სტრესი", "სტრესს", "სტრესისგან" all match a term
     * stored as "სტრესი".
     *
     * Non-Georgian triggers: literal soft-hyphen-tolerant pattern.
     *
     * Returns null if the pattern would be unsafe (stem too short, bad regex).
     */
    private static function build_pattern( string $trigger, string $case_flag ): ?string {
        // Strip soft hyphens for script detection only.
        $trigger_clean = str_replace( "\xc2\xad", '', $trigger );

        if ( WPGT_Georgian_Stemmer::is_georgian( $trigger_clean ) ) {
            return self::build_georgian_pattern( $trigger_clean, $case_flag );
        }

        // Non-Georgian: literal match with soft-hyphen tolerance between chars.
        $raw_chars = preg_split( '//u', $trigger, -1, PREG_SPLIT_NO_EMPTY );
        $flexible  = implode( '\x{00AD}*', array_map( fn( $c ) => preg_quote( $c, '/' ), $raw_chars ) );
        return '/(?<![\pL\pN\-_])(' . $flexible . ')(?![\pL\pN\-_])/' . $case_flag . 'u';
    }

    /**
     * Build a declension-aware regex for a Georgian trigger.
     *
     * 1. Stem the trigger (strip case endings, postpositions, plural marker, -ი).
     * 2. Build stem portion with soft-hyphen tolerance between each character.
     * 3. Append a "any Georgian letters/soft-hyphens" tail to absorb any suffix.
     * 4. Wrap in Unicode word boundaries so we never match mid-word.
     *
     * Example: trigger "სტრესი" → stem "სტრეს"
     *   pattern core: სტ\x{00AD}*რ\x{00AD}*ე\x{00AD}*ს[\x{10D0}-\x{10FF}\x{00AD}]*
     *   matches: სტრესი, სტრესს, სტრესმა, სტრესის, სტრესისგან, სტრესებში …
     */
    private static function build_georgian_pattern( string $trigger, string $case_flag ): ?string {
        $stem = WPGT_Georgian_Stemmer::stem( $trigger );

        if ( mb_strlen( $stem, 'UTF-8' ) < WPGT_Georgian_Stemmer::MIN_STEM_LEN ) {
            return null; // too short — over-matching risk too high
        }

        // Stem chars with soft-hyphen tolerance between them.
        $chars    = preg_split( '//u', $stem, -1, PREG_SPLIT_NO_EMPTY );
        $flexible = implode( '\x{00AD}*', array_map( fn( $c ) => preg_quote( $c, '/' ), $chars ) );

        // Suffix tail: zero or more Georgian Unicode letters + soft hyphens.
        // U+10D0–U+10FF = Mkhedruli (modern Georgian script).
        $suffix_tail = '[\x{10D0}-\x{10FF}\x{00AD}]*';

        return '/(?<![\pL\pN\-_])(' . $flexible . $suffix_tail . ')(?![\pL\pN\-_])/' . $case_flag . 'u';
    }
}

// Boot parser
add_action( 'wp', [ 'WPGT_Tooltip_Parser', 'init' ] );

// Elementor: filter widget HTML output.
// render_content passes ($widget_content, $widget_instance) — declare 2 accepted args.
// elementor/frontend/the_content is a FILTER (returns content), so we use add_filter.
add_filter( 'elementor/widget/render_content',   [ 'WPGT_Tooltip_Parser', 'parse_content' ], 12, 2 );
add_filter( 'elementor/frontend/the_content',    [ 'WPGT_Tooltip_Parser', 'parse_content' ], 12 );
