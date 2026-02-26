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

        $case_flag = ! empty( $settings['case_sensitive'] ) ? '' : 'i';
        $modified  = false;
        $result    = $text;

        foreach ( $terms as $term ) {
            // Don't link a term to its own page
            if ( (int) $term['id'] === $current_post ) continue;

            foreach ( $term['triggers'] as $trigger ) {
                if ( empty( $trigger ) ) continue;

                $key = strtolower( $trigger );
                if ( ! empty( $settings['first_occurrence'] ) && isset( $already_seen[ $key ] ) ) {
                    continue;
                }

                // Allow soft hyphens (U+00AD) between syllables — common in Georgian/CJK hyphenated content.
                // Also add Unicode flag (u) for correct multibyte character handling.
                $quoted   = preg_quote( $trigger, '/' );
                $chars    = preg_split( '//u', $quoted, -1, PREG_SPLIT_NO_EMPTY );
                $flexible = implode( '\\x{00AD}*', $chars );  // allow zero or more soft hyphens between chars
                $pattern  = '/(?<![a-zA-Z0-9\-_])(' . $flexible . ')(?![a-zA-Z0-9\-_])/' . $case_flag . 'u';

                $replacement_count = 0;
                $limit             = ! empty( $settings['first_occurrence'] ) ? 1 : -1;

                $new_result = preg_replace_callback(
                    $pattern,
                    function( $matches ) use ( $term, &$replacement_count ) {
                        $replacement_count++;
                        $tooltip = esc_attr( $term['tooltip'] );
                        $url     = esc_url( $term['url'] );
                        $title   = esc_attr( $term['title'] );
                        $id      = (int) $term['id'];
                        return sprintf(
                            '<span class="wpgt-tooltip-trigger" data-wpgt="%d" data-tooltip="%s" data-title="%s" data-url="%s" tabindex="0" role="term" aria-describedby="wpgt-tip-%d">%s</span>',
                            $id, $tooltip, $title, $url, $id, esc_html( $matches[1] )
                        );
                    },
                    $result,
                    $limit
                );

                if ( $new_result !== $result ) {
                    $result   = $new_result;
                    $modified = true;
                    if ( $replacement_count > 0 ) {
                        $already_seen[ $key ] = true;
                    }
                }
            }
        }

        if ( ! $modified ) return null;

        // Build a fragment from the HTML string
        $fragment = $doc->createDocumentFragment();
        $tmp = new DOMDocument();
        libxml_use_internal_errors( true );
        $tmp->loadHTML(
            '<?xml encoding="UTF-8"><body>' . $result . '</body>',
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

                // Allow soft hyphens (U+00AD) between syllables — common in Georgian/CJK hyphenated content.
                // Also add Unicode flag (u) for correct multibyte character handling.
                $quoted   = preg_quote( $trigger, '/' );
                $chars    = preg_split( '//u', $quoted, -1, PREG_SPLIT_NO_EMPTY );
                $flexible = implode( '\\x{00AD}*', $chars );  // allow zero or more soft hyphens between chars
                $pattern  = '/(?<![a-zA-Z0-9\-_])(' . $flexible . ')(?![a-zA-Z0-9\-_])/' . $case_flag . 'u';
                $limit   = ! empty( $settings['first_occurrence'] ) ? 1 : -1;

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
}

// Boot parser
add_action( 'wp', [ 'WPGT_Tooltip_Parser', 'init' ] );

// Elementor: filter widget HTML output.
// render_content passes ($widget_content, $widget_instance) — declare 2 accepted args.
// elementor/frontend/the_content is an ACTION (not filter), so we use add_action.
add_filter( 'elementor/widget/render_content', [ 'WPGT_Tooltip_Parser', 'parse_content' ], 12, 2 );
add_action( 'elementor/frontend/the_content',  [ 'WPGT_Tooltip_Parser', 'parse_content' ], 12 );
