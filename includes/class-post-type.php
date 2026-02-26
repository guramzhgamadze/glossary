<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPGT_Post_Type {

    const POST_TYPE = 'wpgt_term';
    const TAXONOMY  = 'wpgt_category';

    public static function register() {
        // Register custom post type
        register_post_type( self::POST_TYPE, [
            'labels' => [
                'name'               => __( 'Glossary Terms',          'wp-glossary-tooltip' ),
                'singular_name'      => __( 'Glossary Term',           'wp-glossary-tooltip' ),
                'add_new'            => __( 'Add New Term',             'wp-glossary-tooltip' ),
                'add_new_item'       => __( 'Add New Glossary Term',    'wp-glossary-tooltip' ),
                'edit_item'          => __( 'Edit Glossary Term',       'wp-glossary-tooltip' ),
                'view_item'          => __( 'View Glossary Term',       'wp-glossary-tooltip' ),
                'search_items'       => __( 'Search Glossary Terms',    'wp-glossary-tooltip' ),
                'not_found'          => __( 'No glossary terms found.', 'wp-glossary-tooltip' ),
                'menu_name'          => __( 'Glossary',                 'wp-glossary-tooltip' ),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'glossary' ],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-book-alt',
            'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ],
        ] );

        // Register taxonomy
        register_taxonomy( self::TAXONOMY, self::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Glossary Categories', 'wp-glossary-tooltip' ),
                'singular_name' => __( 'Glossary Category',  'wp-glossary-tooltip' ),
                'search_items'  => __( 'Search Categories',  'wp-glossary-tooltip' ),
                'all_items'     => __( 'All Categories',      'wp-glossary-tooltip' ),
                'edit_item'     => __( 'Edit Category',       'wp-glossary-tooltip' ),
                'add_new_item'  => __( 'Add New Category',    'wp-glossary-tooltip' ),
                'menu_name'     => __( 'Categories',          'wp-glossary-tooltip' ),
            ],
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'glossary-category' ],
        ] );

        // Meta fields for synonyms & tooltip override.
        // auth_callback is required for underscore-prefixed (protected) meta keys;
        // without it WordPress refuses to save them and throws
        // "you are not allowed to edit the _wpgt_* custom field".
        $meta_auth = static function () {
            return current_user_can( 'edit_posts' );
        };

        register_post_meta( self::POST_TYPE, '_wpgt_synonyms', [
            'type'          => 'string',
            'description'   => 'Comma-separated synonyms that also trigger this tooltip.',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => $meta_auth,
        ] );

        register_post_meta( self::POST_TYPE, '_wpgt_tooltip_text', [
            'type'          => 'string',
            'description'   => 'Short tooltip text override (leave blank to use excerpt).',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => $meta_auth,
        ] );

        register_post_meta( self::POST_TYPE, '_wpgt_related_terms', [
            'type'          => 'string',
            'description'   => 'Comma-separated post IDs of related terms.',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => $meta_auth,
        ] );
    }

    /**
     * Fetch all glossary terms with their trigger words.
     * Returns array of [ 'id' => int, 'title' => string, 'triggers' => string[],
     *                     'tooltip' => string, 'url' => string ]
     */
    public static function get_all_terms_for_parsing(): array {
        $cached = wp_cache_get( 'wpgt_all_terms', 'wpgt' );
        if ( false !== $cached ) {
            return $cached;
        }

        $posts = get_posts( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $terms = [];
        foreach ( $posts as $post ) {
            $tooltip_text = get_post_meta( $post->ID, '_wpgt_tooltip_text', true );
            if ( ! $tooltip_text ) {
                $tooltip_text = $post->post_excerpt
                    ? $post->post_excerpt
                    : wp_trim_words( strip_tags( $post->post_content ), 25 );
            }

            $synonyms = get_post_meta( $post->ID, '_wpgt_synonyms', true );
            $triggers  = [ $post->post_title ];
            if ( $synonyms ) {
                foreach ( array_map( 'trim', explode( ',', $synonyms ) ) as $s ) {
                    if ( $s ) $triggers[] = $s;
                }
            }

            $terms[] = [
                'id'       => $post->ID,
                'title'    => $post->post_title,
                'triggers' => $triggers,
                'tooltip'  => $tooltip_text,
                'url'      => get_permalink( $post->ID ),
            ];
        }

        wp_cache_set( 'wpgt_all_terms', $terms, 'wpgt', 300 );
        return $terms;
    }

    /** Bust term cache when a term is saved */
    public static function bust_cache( int $post_id ) {
        if ( get_post_type( $post_id ) === self::POST_TYPE ) {
            wp_cache_delete( 'wpgt_all_terms', 'wpgt' );
        }
    }
}

add_action( 'save_post',   [ 'WPGT_Post_Type', 'bust_cache' ] );
add_action( 'delete_post', [ 'WPGT_Post_Type', 'bust_cache' ] );
