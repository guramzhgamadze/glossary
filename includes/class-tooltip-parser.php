<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Scans post content and wraps glossary term occurrences with tooltip spans.
 *
 * MATCHING ALGORITHM
 * ==================
 * We do NOT use a large alternation regex (300 terms × 130 forms = ~39k alternations
 * causes PHP's PCRE engine to time out on compilation alone).
 *
 * Instead we use a two-step O(N) hash-map approach:
 *
 * 1. TOKENIZE the text node into Georgian/Latin word tokens using a tiny regex.
 * 2. NORMALIZE each token (strip soft hyphens, lowercase).
 * 3. LOOK UP the normalized token in $index['map']  →  O(1) hash map lookup.
 * 4. BUILD the output string with <span> wrappers around matched tokens.
 *
 * The map is built once per page request in get_all_terms_for_parsing() and
 * cached in the WP object cache. Total cost per page: O(total text characters).
 *
 * ELEMENTOR SCOPE GUARD
 * =====================
 * We use a whitelist approach: tooltips are injected ONLY while an Elementor
 * "Post Content" widget is rendering. This prevents injection into headers,
 * footers, loop-grid cards, related posts, post navigation widgets, etc.
 *
 * Official Elementor hooks used:
 *   elementor/frontend/widget/before_render  (action, passes \Elementor\Widget_Base)
 *   elementor/frontend/widget/after_render   (action, passes \Elementor\Widget_Base)
 * Source: https://developers.elementor.com/docs/hooks/render-frontend-elements/
 *
 * The $in_post_content_widget flag is set true ONLY for widgets whose get_name()
 * returns one of POST_CONTENT_WIDGETS. The_content filter bails immediately if
 * the flag is false — guaranteeing zero injection outside that widget.
 */
class WPGT_Tooltip_Parser {

    private static bool $initialized            = false;
    private static bool $in_post_content_widget = false;  // true ONLY while a Post Content widget renders
    private static bool $elementor_is_rendering = false;  // true once Elementor begins rendering ANY widget on the page

    /** Elementor widget names that represent the main post body content. */
    private const POST_CONTENT_WIDGETS = [ 'theme-post-content', 'post-content' ];

    /** Regex that finds a run of Georgian/Latin/digit chars, allowing soft hyphens inside. */
    private const WORD_RE = '/[\x{10D0}-\x{10FF}\x{10A0}-\x{10CF}A-Za-z0-9\x{00AD}][\x{10D0}-\x{10FF}\x{10A0}-\x{10CF}A-Za-z0-9\x{00AD}\-]*/u';

    public static function init(): void {
        if ( self::$initialized ) return;
        self::$initialized = true;
        add_filter( 'the_content', [ __CLASS__, 'parse_content' ], 12 );

        // Elementor scope guard — uses official Elementor hooks.
        // Source: https://developers.elementor.com/docs/hooks/render-frontend-elements/
        //
        // elementor/frontend/before_render fires once before Elementor starts rendering
        // the page — we use it to know the page IS an Elementor page so we can enforce
        // the whitelist without calling any potentially missing Elementor methods.
        //
        // elementor/frontend/widget/before_render & after_render fire per-widget and
        // let us flag exactly which widget is currently rendering.
        add_action( 'elementor/frontend/before_render',        [ __CLASS__, 'mark_elementor_rendering'    ] );
        add_action( 'elementor/frontend/widget/before_render', [ __CLASS__, 'enter_post_content_widget'   ] );
        add_action( 'elementor/frontend/widget/after_render',  [ __CLASS__, 'leave_post_content_widget'   ] );
    }

    /** Fired once by Elementor before it renders the page — marks this as an Elementor page. */
    public static function mark_elementor_rendering(): void {
        self::$elementor_is_rendering = true;
    }

    public static function enter_post_content_widget( $widget ): void {
        if ( $widget && method_exists( $widget, 'get_name' )
             && in_array( $widget->get_name(), self::POST_CONTENT_WIDGETS, true ) ) {
            self::$in_post_content_widget = true;
        }
    }

    public static function leave_post_content_widget( $widget ): void {
        if ( $widget && method_exists( $widget, 'get_name' )
             && in_array( $widget->get_name(), self::POST_CONTENT_WIDGETS, true ) ) {
            self::$in_post_content_widget = false;
        }
    }

    public static function parse_content( string $content, $widget = null ): string {
        if ( empty( $content ) ) return $content;

        // Scope guard: on Elementor-built pages, only inject inside the Post Content widget.
        // On classic/block-theme pages ($elementor_is_rendering stays false) → no restriction.
        // This prevents injection into headers, footers, loop grids, related posts, etc.
        if ( self::$elementor_is_rendering && ! self::$in_post_content_widget ) {
            return $content;
        }

        $settings = WPGT_Settings::get_all();
        if ( ! is_singular() ) return $content;
        if ( ! in_array( get_post_type(), (array) $settings['parse_post_types'], true ) ) return $content;

        $current_id = (int) get_the_ID();
        if ( $current_id && get_post_meta( $current_id, '_wpgt_skip_tooltips', true ) ) return $content;

        $index = WPGT_Post_Type::get_all_terms_for_parsing();
        if ( empty( $index['map'] ) || empty( $index['terms'] ) ) return $content;

        // ── Category Rules filtering ─────────────────────────────────────
        // If any rules are configured, filter the term index to only include
        // terms whose groups overlap with the active groups for this post.
        // Terms with NO group assigned are always excluded (strict mode).
        // If NO rules are configured at all → backward compat: show all grouped terms.
        $cat_rules = WPGT_Settings::get_cat_rules();

        if ( ! empty( $cat_rules ) ) {
            // Build union of active group slugs from this post's WP categories
            $post_cat_ids  = wp_get_post_categories( $current_id, [ 'fields' => 'ids' ] );
            $active_groups = [];
            foreach ( (array) $post_cat_ids as $cat_id ) {
                $key = (string) $cat_id;
                if ( isset( $cat_rules[ $key ] ) ) {
                    foreach ( $cat_rules[ $key ] as $slug ) {
                        $active_groups[ $slug ] = true;
                    }
                }
            }
            // Filter: keep only terms with at least one group in active_groups.
            // Terms with no groups ([] ) are always excluded in strict mode.
            $allowed = [];
            foreach ( $index['terms'] as $tid => $tdata ) {
                if ( empty( $tdata['groups'] ) ) continue;           // no group → excluded
                foreach ( $tdata['groups'] as $g ) {
                    if ( isset( $active_groups[ $g ] ) ) {
                        $allowed[ $tid ] = true;
                        break;
                    }
                }
            }
            // Rebuild filtered index
            $filtered_terms = array_intersect_key( $index['terms'], $allowed );
            $filtered_map   = array_filter( $index['map'], fn( $id ) => isset( $allowed[ $id ] ) );
            $index = [ 'terms' => $filtered_terms, 'map' => $filtered_map ];

            if ( empty( $index['map'] ) ) return $content;
        }
        // No rules configured: fall through with full index (backward compat)

        return self::inject_tooltips( $content, $index, $settings, $current_id );
    }

    // ── DOM walker ───────────────────────────────────────────────────────────

    private static function inject_tooltips( string $html, array $index, array $settings, int $current_post ): string {
        if ( ! class_exists( 'DOMDocument' ) ) {
            return self::inject_tooltips_simple( $html, $index, $settings, $current_post );
        }

        $doc  = new DOMDocument();
        $prev = libxml_use_internal_errors( true );
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="wpgt-wrapper">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        $wrapper = $doc->getElementById( 'wpgt-wrapper' );
        if ( ! $wrapper ) return $html;

        $already_seen = [];
        self::walk_node( $wrapper, $doc, $index, $settings, $already_seen, $current_post );

        $inner = '';
        foreach ( $wrapper->childNodes as $child ) {
            $inner .= $doc->saveHTML( $child );
        }
        return $inner;
    }

    private static function walk_node(
        DOMNode $node, DOMDocument $doc, array $index,
        array $settings, array &$already_seen, int $current_post
    ): void {
        $skip = [ 'script','style','code','pre','textarea','button','select' ];
        if ( ! empty( $settings['exclude_links'] ) )    $skip[] = 'a';
        if ( ! empty( $settings['exclude_headings'] ) ) {
            foreach ( [ 'h1','h2','h3','h4','h5','h6' ] as $h ) $skip[] = $h;
        }

        if ( $node->nodeType === XML_ELEMENT_NODE ) {
            if ( in_array( strtolower( $node->nodeName ), $skip, true ) ) return;
            if ( $node instanceof DOMElement && $node->getAttribute( 'data-wpgt' ) ) return;
        }

        $children = iterator_to_array( $node->childNodes );
        foreach ( $children as $child ) {
            if ( $child->nodeType === XML_TEXT_NODE ) {
                $frag = self::process_text_node( $child, $doc, $index, $settings, $already_seen, $current_post );
                if ( $frag ) $node->replaceChild( $frag, $child );
            } elseif ( $child->nodeType === XML_ELEMENT_NODE ) {
                self::walk_node( $child, $doc, $index, $settings, $already_seen, $current_post );
            }
        }
    }

    /**
     * Core matching logic — hash-map, no large regex.
     *
     * Finds all Georgian/Latin word tokens in the text, normalizes each,
     * looks it up in $map, collects matches, then builds the output.
     */
    private static function process_text_node(
        DOMText $text_node, DOMDocument $doc, array $index,
        array $settings, array &$already_seen, int $current_post
    ): ?DOMDocumentFragment {
        $text = $text_node->nodeValue;
        if ( trim( $text ) === '' ) return null;

        $map        = $index['map'];        // normalized_form => term_id
        $terms_data = $index['terms'];      // term_id => term_info
        $first_only = ! empty( $settings['first_occurrence'] );

        // Find every word-like token with its byte offset
        if ( ! preg_match_all( self::WORD_RE, $text, $tok_matches, PREG_OFFSET_CAPTURE ) ) {
            return null;
        }

        $keep = [];
        foreach ( $tok_matches[0] as $tok ) {
            $raw    = $tok[0];
            $offset = $tok[1];
            $length = strlen( $raw );

            // Normalize: strip soft hyphens, lowercase
            $norm = mb_strtolower(
                str_replace( [ '&shy;', '&#173;', "\xc2\xad" ], '', $raw ),
                'UTF-8'
            );

            $term_id = $map[ $norm ] ?? null;
            if ( $term_id === null )          continue;
            if ( $term_id === $current_post ) continue;

            $term_key = (string) $term_id;
            if ( $first_only && isset( $already_seen[ $term_key ] ) ) continue;

            $keep[] = [
                'offset'  => $offset,
                'length'  => $length,
                'matched' => $raw,
                'term_id' => $term_id,
                'key'     => $term_key,
            ];
        }

        if ( empty( $keep ) ) return null;

        // Maximal munch: sort by offset, drop overlaps (prefer longer at same pos)
        usort( $keep, fn( $a, $b ) =>
            $a['offset'] !== $b['offset']
                ? $a['offset'] - $b['offset']
                : $b['length'] - $a['length']
        );
        $non_overlap = [];
        $last_end    = 0;
        foreach ( $keep as $m ) {
            if ( $m['offset'] >= $last_end ) {
                $non_overlap[] = $m;
                $last_end      = $m['offset'] + $m['length'];
            }
        }
        if ( empty( $non_overlap ) ) return null;

        // Build HTML
        $html   = '';
        $cursor = 0;
        foreach ( $non_overlap as $m ) {
            $html .= esc_html( substr( $text, $cursor, $m['offset'] - $cursor ) );
            $term  = $terms_data[ $m['term_id'] ] ?? null;
            if ( ! $term ) {
                $html  .= esc_html( $m['matched'] );
                $cursor = $m['offset'] + $m['length'];
                continue;
            }
            $id    = (int) $term['id'];
            $html .= sprintf(
                '<span class="wpgt-tooltip-trigger" data-wpgt="%d" data-tooltip="%s" data-title="%s" data-url="%s" tabindex="0" aria-describedby="wpgt-tip-%d">%s</span>',
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
        $html .= esc_html( substr( $text, $cursor ) );

        // Build DocumentFragment
        $frag = $doc->createDocumentFragment();
        $tmp  = new DOMDocument();
        libxml_use_internal_errors( true );
        $tmp->loadHTML(
            '<?xml encoding="UTF-8"><body>' . $html . '</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        $body = $tmp->getElementsByTagName( 'body' )->item( 0 );
        if ( ! $body ) return null;
        foreach ( $body->childNodes as $child ) {
            $frag->appendChild( $doc->importNode( $child, true ) );
        }
        return $frag;
    }

    // ── Fallback (no DOMDocument) ────────────────────────────────────────────

    /**
     * Simple token-replace fallback when DOMDocument is unavailable.
     * Replaces word tokens inside text nodes only (strips tags, replaces, re-wraps).
     * Not as safe as the DOM version but never crashes.
     */
    private static function inject_tooltips_simple( string $html, array $index, array $settings, int $current_post ): string {
        $map        = $index['map'];
        $terms_data = $index['terms'];
        $first_only = ! empty( $settings['first_occurrence'] );
        $already_seen = [];

        // Split on tags, process only text segments
        $parts  = preg_split( '/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
        $inside = false; // inside a skip tag?
        $depth  = 0;
        $output = '';

        foreach ( $parts as $part ) {
            if ( str_starts_with( $part, '<' ) ) {
                $output .= $part;
                continue;
            }
            $output .= self::replace_tokens_in_text( $part, $map, $terms_data, $current_post, $first_only, $already_seen );
        }
        return $output;
    }

    private static function replace_tokens_in_text(
        string $text, array $map, array $terms_data,
        int $current_post, bool $first_only, array &$already_seen
    ): string {
        if ( trim( $text ) === '' ) return $text;

        if ( ! preg_match_all( self::WORD_RE, $text, $tok_matches, PREG_OFFSET_CAPTURE ) ) return $text;

        $keep = [];
        foreach ( $tok_matches[0] as $tok ) {
            $raw  = $tok[0];
            $norm = mb_strtolower( str_replace( [ '&shy;', '&#173;', "\xc2\xad" ], '', $raw ), 'UTF-8' );
            $term_id = $map[ $norm ] ?? null;
            if ( $term_id === null || $term_id === $current_post ) continue;
            $key = (string) $term_id;
            if ( $first_only && isset( $already_seen[ $key ] ) ) continue;
            $keep[] = [ 'offset' => $tok[1], 'length' => strlen( $raw ), 'matched' => $raw, 'term_id' => $term_id, 'key' => $key ];
        }
        if ( empty( $keep ) ) return $text;

        usort( $keep, fn( $a, $b ) => $a['offset'] !== $b['offset'] ? $a['offset'] - $b['offset'] : $b['length'] - $a['length'] );
        $non_overlap = []; $last_end = 0;
        foreach ( $keep as $m ) {
            if ( $m['offset'] >= $last_end ) { $non_overlap[] = $m; $last_end = $m['offset'] + $m['length']; }
        }

        $out = ''; $cursor = 0;
        foreach ( $non_overlap as $m ) {
            $out  .= esc_html( substr( $text, $cursor, $m['offset'] - $cursor ) );
            $term  = $terms_data[ $m['term_id'] ] ?? null;
            if ( ! $term ) { $out .= esc_html( $m['matched'] ); $cursor = $m['offset'] + $m['length']; continue; }
            $out  .= sprintf(
                '<span class="wpgt-tooltip-trigger" data-wpgt="%d" data-tooltip="%s" data-title="%s" data-url="%s" tabindex="0">%s</span>',
                (int)$term['id'], esc_attr($term['tooltip']), esc_attr($term['title']), esc_url($term['url']), esc_html($m['matched'])
            );
            $cursor = $m['offset'] + $m['length'];
            $already_seen[ $m['key'] ] = true;
        }
        $out .= esc_html( substr( $text, $cursor ) );
        return $out;
    }
}

add_action( 'wp', [ 'WPGT_Tooltip_Parser', 'init' ] );
// NOTE: elementor/widget/render_content is intentionally NOT hooked here.
// It fires for EVERY widget on the page (headers, footers, loop grids, etc.)
// which would inject tooltips everywhere. Instead we use the whitelist approach:
// the_content filter + before/after_render flags to limit injection to the
// Elementor Post Content widget only.
// Reference: https://developers.elementor.com/docs/hooks/render-frontend-elements/
