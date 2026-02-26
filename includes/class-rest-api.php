<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST endpoints under /wp-json/wpgt/v1/
 *
 *   GET  /terms              - paginated list
 *   GET  /terms/{id}         - single term
 *   GET  /search?q=…         - live search (used by frontend widget)
 */
class WPGT_REST_API {

    public static function register() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        $ns = 'wpgt/v1';

        register_rest_route( $ns, '/terms', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_terms' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'per_page' => [
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
                'page' => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'category' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        register_rest_route( $ns, '/terms/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_single_term' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => fn( $v ) => is_numeric( $v ),
                ],
            ],
        ] );

        register_rest_route( $ns, '/search', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'search_terms' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'q' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => fn( $v ) => strlen( $v ) >= 2,
                ],
            ],
        ] );
    }

    public static function get_terms( WP_REST_Request $request ): WP_REST_Response {
        $per_page = $request->get_param( 'per_page' );
        $page     = $request->get_param( 'page' );
        $category = $request->get_param( 'category' );

        $args = [
            'post_type'      => WPGT_Post_Type::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        if ( $category ) {
            $args['tax_query'] = [ [
                'taxonomy' => WPGT_Post_Type::TAXONOMY,
                'field'    => 'slug',
                'terms'    => $category,
            ] ];
        }

        $query = new WP_Query( $args );
        $items = array_map( [ __CLASS__, 'format_term' ], $query->posts );

        $response = new WP_REST_Response( $items, 200 );
        $response->header( 'X-WP-Total',      $query->found_posts );
        $response->header( 'X-WP-TotalPages', $query->max_num_pages );
        return $response;
    }

    public static function get_single_term( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post = get_post( $request->get_param( 'id' ) );
        if ( ! $post || $post->post_type !== WPGT_Post_Type::POST_TYPE || $post->post_status !== 'publish' ) {
            return new WP_Error( 'not_found', __( 'Term not found.', 'wp-glossary-tooltip' ), [ 'status' => 404 ] );
        }
        return new WP_REST_Response( self::format_term( $post ), 200 );
    }

    public static function search_terms( WP_REST_Request $request ): WP_REST_Response {
        $q     = $request->get_param( 'q' );
        $posts = get_posts( [
            'post_type'      => WPGT_Post_Type::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            's'              => $q,
            'orderby'        => 'relevance',
        ] );
        return new WP_REST_Response( array_map( [ __CLASS__, 'format_term' ], $posts ), 200 );
    }

    private static function format_term( WP_Post $post ): array {
        $tooltip_text = get_post_meta( $post->ID, '_wpgt_tooltip_text', true );
        if ( ! $tooltip_text ) {
            $tooltip_text = $post->post_excerpt
                ? $post->post_excerpt
                : wp_trim_words( strip_tags( $post->post_content ), 25 );
        }

        $categories = wp_get_post_terms( $post->ID, WPGT_Post_Type::TAXONOMY, [ 'fields' => 'names' ] );
        $synonyms   = get_post_meta( $post->ID, '_wpgt_synonyms', true );

        return [
            'id'           => $post->ID,
            'title'        => $post->post_title,
            'slug'         => $post->post_name,
            'tooltip_text' => $tooltip_text,
            'content'      => apply_filters( 'the_content', $post->post_content ),
            'excerpt'      => $post->post_excerpt,
            'url'          => get_permalink( $post->ID ),
            'synonyms'     => $synonyms ? array_map( 'trim', explode( ',', $synonyms ) ) : [],
            'categories'   => is_wp_error( $categories ) ? [] : $categories,
            'date'         => $post->post_date,
            'modified'     => $post->post_modified,
        ];
    }
}
