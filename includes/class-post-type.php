<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPGT_Post_Type {

    const POST_TYPE  = 'wpgt_term';
    const LETTER_TAX = 'wpgt_letter';
    const GROUP_TAX  = 'wpgt_group';

    /**
     * Georgian letter → ASCII slug.
     * Slug format: "letter-a", "letter-b" … avoids:
     *   1. WordPress percent-encoding bug with non-Latin chars
     *   2. Too-short slug issues (minimum 8 chars)
     *   3. Conflicts with CPT post slugs (which are Georgian words)
     */
    private static array $letter_slug_map = [
        'ა' => 'letter-a',  'ბ' => 'letter-b',  'გ' => 'letter-g',
        'დ' => 'letter-d',  'ე' => 'letter-e',  'ვ' => 'letter-v',
        'ზ' => 'letter-z',  'თ' => 'letter-th', 'ი' => 'letter-i',
        'კ' => 'letter-k',  'ლ' => 'letter-l',  'მ' => 'letter-m',
        'ნ' => 'letter-n',  'ო' => 'letter-o',  'პ' => 'letter-p',
        'ჟ' => 'letter-zh', 'რ' => 'letter-r',  'ს' => 'letter-s',
        'ტ' => 'letter-t',  'უ' => 'letter-u',  'ფ' => 'letter-ph',
        'ქ' => 'letter-q',  'ღ' => 'letter-gh', 'ყ' => 'letter-y',
        'შ' => 'letter-sh', 'ჩ' => 'letter-ch', 'ც' => 'letter-ts',
        'ძ' => 'letter-dz', 'წ' => 'letter-w',  'ჭ' => 'letter-tc',
        'ხ' => 'letter-x',  'ჯ' => 'letter-j',  'ჰ' => 'letter-h',
    ];

    public static function register() {

        // ── Custom Post Type ──────────────────────────────────────────────
        register_post_type( self::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Glossary Terms',          'wp-glossary-tooltip' ),
                'singular_name' => __( 'Glossary Term',           'wp-glossary-tooltip' ),
                'add_new'       => __( 'Add New Term',             'wp-glossary-tooltip' ),
                'add_new_item'  => __( 'Add New Glossary Term',    'wp-glossary-tooltip' ),
                'edit_item'     => __( 'Edit Glossary Term',       'wp-glossary-tooltip' ),
                'view_item'     => __( 'View Glossary Term',       'wp-glossary-tooltip' ),
                'search_items'  => __( 'Search Glossary Terms',    'wp-glossary-tooltip' ),
                'not_found'     => __( 'No glossary terms found.', 'wp-glossary-tooltip' ),
                'menu_name'     => __( 'Glossary',                 'wp-glossary-tooltip' ),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'glossary' ],
            'capability_type'    => 'post',
            'has_archive'        => 'glossary',
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-book-alt',
            'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ],
        ] );

        // ── Letter Taxonomy ───────────────────────────────────────────────
        // Archive URLs: /glossary-letter/letter-a/  /glossary-letter/letter-b/ …
        // Term name = Georgian letter (ა, ბ …) — shown in Elementor Archive Title widget
        // Term slug = ASCII "letter-a" etc — avoids all encoding/length issues
        register_taxonomy( self::LETTER_TAX, self::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Letters',                  'wp-glossary-tooltip' ),
                'singular_name' => __( 'Letter',                   'wp-glossary-tooltip' ),
                'all_items'     => __( 'All Letters',              'wp-glossary-tooltip' ),
                'edit_item'     => __( 'Edit Letter',              'wp-glossary-tooltip' ),
                'view_item'     => __( 'View Letter',              'wp-glossary-tooltip' ),
                'update_item'   => __( 'Update Letter',            'wp-glossary-tooltip' ),
                'add_new_item'  => __( 'Add New Letter',           'wp-glossary-tooltip' ),
                'search_items'  => __( 'Search Letters',           'wp-glossary-tooltip' ),
                'not_found'     => __( 'No letters found.',        'wp-glossary-tooltip' ),
                'menu_name'     => __( 'Letters',                  'wp-glossary-tooltip' ),
                'archives'      => __( 'Glossary by Letter',       'wp-glossary-tooltip' ),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => false,
            'show_ui'            => true,
            'show_in_rest'       => true,
            'show_admin_column'  => true,
            'show_in_nav_menus'  => true,
            'show_tagcloud'      => false,
            'query_var'          => true,
            'rewrite'            => [
                'slug'       => 'glossary-letter',
                'with_front' => false,
            ],
        ] );

        // ── Glossary Group Taxonomy ───────────────────────────────────────
        // Flat tag-style taxonomy for grouping terms into logical glossaries.
        // Post categories are then mapped to groups in Settings → Category Rules.
        register_taxonomy( self::GROUP_TAX, self::POST_TYPE, [
            'labels' => [
                'name'              => __( 'Glossary Groups',         'wp-glossary-tooltip' ),
                'singular_name'     => __( 'Glossary Group',          'wp-glossary-tooltip' ),
                'all_items'         => __( 'All Groups',              'wp-glossary-tooltip' ),
                'edit_item'         => __( 'Edit Group',              'wp-glossary-tooltip' ),
                'update_item'       => __( 'Update Group',            'wp-glossary-tooltip' ),
                'add_new_item'      => __( 'Add New Group',           'wp-glossary-tooltip' ),
                'new_item_name'     => __( 'New Group Name',          'wp-glossary-tooltip' ),
                'search_items'      => __( 'Search Groups',           'wp-glossary-tooltip' ),
                'not_found'         => __( 'No groups found.',        'wp-glossary-tooltip' ),
                'menu_name'         => __( 'Glossary Groups',         'wp-glossary-tooltip' ),
            ],
            'public'             => false,   // no frontend URLs for groups
            'publicly_queryable' => false,
            'hierarchical'       => false,   // flat — like tags, not categories
            'show_ui'            => true,    // shows checkbox panel on term edit screen
            'show_in_rest'       => true,
            'show_admin_column'  => true,    // shows group column in term list table
            'show_in_nav_menus'  => false,
            'show_tagcloud'      => false,
            'query_var'          => false,
            'rewrite'            => false,
        ] );

        // ── Meta fields ───────────────────────────────────────────────────
        $meta_auth = static fn() => current_user_can( 'edit_posts' );

        register_post_meta( self::POST_TYPE, '_wpgt_is_loanword', [
            'type'          => 'boolean',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => $meta_auth,  // prevents unauthenticated REST updates
        ] );
        register_post_meta( self::POST_TYPE, '_wpgt_synonyms', [
            'type' => 'string', 'single' => true,
            'show_in_rest' => true, 'auth_callback' => $meta_auth,
        ] );
        register_post_meta( self::POST_TYPE, '_wpgt_tooltip_text', [
            'type' => 'string', 'single' => true,
            'show_in_rest' => true, 'auth_callback' => $meta_auth,
        ] );
        register_post_meta( self::POST_TYPE, '_wpgt_related_terms', [
            'type' => 'string', 'single' => true,
            'show_in_rest' => true, 'auth_callback' => $meta_auth,
        ] );
        // _wpgt_declined_forms: JSON array of morphological forms, admin-only view
        register_post_meta( self::POST_TYPE, '_wpgt_declined_forms', [
            'type' => 'string', 'single' => true,
            'show_in_rest' => false,  // internal use only
        ] );
        // _wpgt_skip_tooltips: per-post opt-out flag (set via the sidebar meta box on any post type)
        register_post_meta( '', '_wpgt_skip_tooltips', [
            'type'          => 'boolean',
            'single'        => true,
            'show_in_rest'  => false,  // admin-only
            'auth_callback' => $meta_auth,
        ] );
    }

    // ── Letter slug helpers ───────────────────────────────────────────────

    public static function letter_slug( string $letter ): string {
        $lower = mb_strtolower( $letter, 'UTF-8' );
        return self::$letter_slug_map[ $lower ] ?? '';
    }

    public static function get_letter_slug_map(): array {
        return self::$letter_slug_map;
    }

    // ── Auto-assign letter taxonomy on save ───────────────────────────────

    public static function assign_letter_taxonomy( int $post_id ) {
        if ( get_post_type( $post_id ) !== self::POST_TYPE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;

        $title = get_the_title( $post_id );
        if ( ! $title ) return;

        $letter = mb_strtoupper( mb_substr( $title, 0, 1, 'UTF-8' ), 'UTF-8' );
        $slug   = self::letter_slug( $letter );
        if ( ! $slug ) return;

        $term = get_term_by( 'slug', $slug, self::LETTER_TAX );
        if ( ! $term ) {
            $term = get_term_by( 'name', $letter, self::LETTER_TAX );
        }

        if ( ! $term ) {
            $result = wp_insert_term( $letter, self::LETTER_TAX, [ 'slug' => $slug ] );
            if ( is_wp_error( $result ) ) return;
            $term_id = $result['term_id'];
        } else {
            $term_id = $term->term_id;
            if ( $term->slug !== $slug ) {
                wp_update_term( $term_id, self::LETTER_TAX, [ 'slug' => $slug ] );
            }
        }

        wp_set_object_terms( $post_id, [ $term_id ], self::LETTER_TAX );
        wp_cache_delete( 'wpgt_all_terms', 'wpgt' );
    }

    /**
     * Fix all existing letter term slugs to the new letter-x format.
     * Deletes any orphaned terms that can't be mapped.
     * @return int number of terms fixed
     */
    public static function fix_letter_slugs(): int {
        $terms = get_terms( [ 'taxonomy' => self::LETTER_TAX, 'hide_empty' => false ] );
        if ( empty( $terms ) || is_wp_error( $terms ) ) return 0;

        $fixed = 0;
        foreach ( $terms as $term ) {
            $correct = self::letter_slug( $term->name );
            if ( ! $correct ) {
                // Can't map this term — delete it so it doesn't cause confusion
                wp_delete_term( $term->term_id, self::LETTER_TAX );
                continue;
            }
            if ( $term->slug !== $correct ) {
                wp_update_term( $term->term_id, self::LETTER_TAX, [ 'slug' => $correct ] );
                $fixed++;
            }
        }

        if ( $fixed > 0 ) flush_rewrite_rules( false );
        return $fixed;
    }

    // ── Declined forms generation ────────────────────────────────────────────

    /**
     * Generate and store all declined forms for a term and its synonyms.
     * Hooked to save_post at priority 20 (after save_meta at priority 10).
     * Result stored in _wpgt_declined_forms as JSON — hidden from public.
     */
    public static function generate_and_store_declined_forms( int $post_id ) {
        if ( get_post_type( $post_id ) !== self::POST_TYPE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        $title        = get_the_title( $post_id );
        $synonyms_raw = get_post_meta( $post_id, '_wpgt_synonyms', true );

        // Collect all manually entered trigger words (title + synonyms)
        $manual_words = array_filter( [ $title ] );
        if ( $synonyms_raw ) {
            foreach ( array_map( 'trim', explode( ',', $synonyms_raw ) ) as $s ) {
                if ( $s !== '' ) $manual_words[] = $s;
            }
        }

        $is_loanword = (bool) get_post_meta( $post_id, '_wpgt_is_loanword', true );

        // Generate full morphological paradigm for every word
        $all_forms = [];
        foreach ( $manual_words as $word ) {
            if ( WPGT_Georgian_Stemmer::is_georgian( $word ) ) {
                $forms = $is_loanword
                    ? WPGT_Georgian_Stemmer::generate_loanword_forms( $word )
                    : WPGT_Georgian_Stemmer::generate_all_forms( $word );
            } else {
                $forms = [ mb_strtolower( trim( $word ), 'UTF-8' ) ];
            }
            foreach ( $forms as $f ) {
                if ( $f !== '' ) $all_forms[] = $f;
            }
        }

        $all_forms = array_values( array_unique( $all_forms ) );

        // Store as JSON (admin-only view — shown in term meta box)
        update_post_meta(
            $post_id,
            '_wpgt_declined_forms',
            wp_json_encode( $all_forms, JSON_UNESCAPED_UNICODE )
        );

        wp_cache_delete( 'wpgt_all_terms', 'wpgt' );
    }

    /**
     * Regenerate declined forms for ALL published terms.
     * Called from Settings → Import/Export → Regenerate button.
     * @return int count of terms processed
     */
    public static function regenerate_all_declined_forms(): int {
        $posts = get_posts( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );
        foreach ( $posts as $id ) {
            self::generate_and_store_declined_forms( (int) $id );
        }
        wp_cache_delete( 'wpgt_all_terms', 'wpgt' );
        return count( $posts );
    }

    // ── Parsing cache ─────────────────────────────────────────────────────

    /**
     * Returns a parsing index used by WPGT_Tooltip_Parser.
     *
     * Structure:
     *   'terms'   => array of term data keyed by post ID
     *   'pattern' => ONE master regex matching every form of every term
     *   'map'     => [ lowercase_form => term_id, ... ]  (reverse lookup)
     *
     * The parser runs the master pattern ONCE per text node, then looks up
     * each match in the map to find which term it belongs to.
     * This is O(1) regex calls per text node regardless of term or form count.
     */
    public static function get_all_terms_for_parsing(): array {
        $cached = wp_cache_get( 'wpgt_all_terms', 'wpgt' );
        if ( false !== $cached ) return $cached;

        $posts = get_posts( [
            'post_type' => self::POST_TYPE, 'post_status' => 'publish',
            'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC',
        ] );

        $terms_data = [];  // post_id => term info
        $form_map   = [];  // normalized_form => post_id  (O(1) reverse lookup)

        foreach ( $posts as $post ) {
            $tooltip = get_post_meta( $post->ID, '_wpgt_tooltip_text', true )
                ?: ( $post->post_excerpt ?: wp_trim_words( strip_tags( $post->post_content ), 25 ) );

            // Collect all forms: title + synonyms + declined forms
            $all_forms = [ $post->post_title ];

            $synonyms = get_post_meta( $post->ID, '_wpgt_synonyms', true );
            if ( $synonyms ) {
                foreach ( array_map( 'trim', explode( ',', $synonyms ) ) as $s ) {
                    if ( $s !== '' ) $all_forms[] = $s;
                }
            }

            $stored_json = get_post_meta( $post->ID, '_wpgt_declined_forms', true );
            if ( $stored_json ) {
                $stored = json_decode( $stored_json, true );
                if ( is_array( $stored ) ) {
                    foreach ( $stored as $form ) {
                        $form = trim( (string) $form );
                        if ( $form !== '' ) $all_forms[] = $form;
                    }
                }
            }

            $min = WPGT_Georgian_Stemmer::MIN_STEM_LEN;
            $post_id = $post->ID;

            // Fetch group slugs for this term (used by category-rules filtering)
            $group_terms = wp_get_post_terms( $post_id, self::GROUP_TAX, [ 'fields' => 'slugs' ] );
            $group_slugs = ( ! is_wp_error( $group_terms ) && is_array( $group_terms ) ) ? $group_terms : [];

            $terms_data[ $post_id ] = [
                'id'      => $post_id,
                'title'   => $post->post_title,
                'tooltip' => $tooltip,
                'url'     => get_permalink( $post_id ),
                'groups'  => $group_slugs,   // [] = no group (strict mode: never shown unless globally unlocked)
            ];

            foreach ( $all_forms as $form ) {
                $form_clean = mb_strtolower(
                    str_replace( [ '&shy;', '&#173;', "\xc2\xad" ], '', trim( $form ) ),
                    'UTF-8'
                );
                if ( mb_strlen( $form_clean, 'UTF-8' ) < $min ) continue;
                // First writer wins — title/synonyms registered before declined forms.
                if ( ! isset( $form_map[ $form_clean ] ) ) {
                    $form_map[ $form_clean ] = $post_id;
                }
            }
        }

        $result = [ 'terms' => $terms_data, 'map' => $form_map ];
        wp_cache_set( 'wpgt_all_terms', $result, 'wpgt', 300 );
        return $result;
    }

    public static function bust_cache( int $post_id ) {
        if ( get_post_type( $post_id ) === self::POST_TYPE ) {
            wp_cache_delete( 'wpgt_all_terms', 'wpgt' );
        }
    }
}

add_action( 'save_post',   [ 'WPGT_Post_Type', 'bust_cache'                       ],  10 );
add_action( 'save_post',   [ 'WPGT_Post_Type', 'assign_letter_taxonomy'           ],  10 );
add_action( 'save_post',   [ 'WPGT_Post_Type', 'generate_and_store_declined_forms' ], 20 );
add_action( 'delete_post', [ 'WPGT_Post_Type', 'bust_cache'                       ],  10 );

/**
 * Sort glossary letter archives by menu_order (set via Glossary → Re-Order).
 * Fires on the main query AND Elementor Loop Grid "Current Query" which
 * inherits the main archive query via the same WP_Query.
 */
add_action( 'pre_get_posts', function( WP_Query $q ) {
    // Only touch the main query on wpgt_letter taxonomy archives
    if ( is_admin() ) return;
    if ( ! $q->is_main_query() ) return;
    if ( ! $q->is_tax( WPGT_Post_Type::LETTER_TAX ) ) return;

    $q->set( 'orderby', 'menu_order' );
    $q->set( 'order',   'ASC' );
    $q->set( 'posts_per_page', -1 );  // show all terms, no pagination
} );
