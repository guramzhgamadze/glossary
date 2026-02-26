<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shortcodes:
 *   [wpgt_glossary]           - Full glossary index with A–Z bar
 *   [wpgt_term id="123"]      - Single term definition box
 *   [wpgt_search]             - Live search widget
 */
class WPGT_Shortcodes {

    public static function register() {
        add_shortcode( 'wpgt_glossary', [ __CLASS__, 'glossary_index' ] );
        add_shortcode( 'wpgt_term',     [ __CLASS__, 'single_term'    ] );
        add_shortcode( 'wpgt_search',   [ __CLASS__, 'search_widget'  ] );
    }

    // ------------------------------------------------------------------
    // [wpgt_glossary columns="3" show_alphabet="true" category="slug"]
    // ------------------------------------------------------------------
    public static function glossary_index( $atts ): string {
        $atts = shortcode_atts( [
            'columns'        => WPGT_Settings::get( 'index_columns', 3 ),
            'show_alphabet'  => WPGT_Settings::get( 'show_alphabet_bar', true ) ? 'true' : 'false',
            'category'       => '',
            'orderby'        => 'title',   // title | date
        ], $atts, 'wpgt_glossary' );

        $query_args = [
            'post_type'      => WPGT_Post_Type::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => $atts['orderby'] === 'date' ? 'date' : 'title',
            'order'          => 'ASC',
        ];

        if ( ! empty( $atts['category'] ) ) {
            $query_args['tax_query'] = [ [
                'taxonomy' => WPGT_Post_Type::TAXONOMY,
                'field'    => 'slug',
                'terms'    => explode( ',', $atts['category'] ),
            ] ];
        }

        $posts = get_posts( $query_args );
        if ( empty( $posts ) ) {
            return '<p class="wpgt-no-terms">' . esc_html__( 'No glossary terms found.', 'wp-glossary-tooltip' ) . '</p>';
        }

        // Group by first letter
        $groups = [];
        foreach ( $posts as $post ) {
            $letter = strtoupper( mb_substr( $post->post_title, 0, 1 ) );
            if ( ! ctype_alpha( $letter ) ) $letter = '#';
            $groups[ $letter ][] = $post;
        }
        ksort( $groups );

        $columns = max( 1, min( 6, (int) $atts['columns'] ) );
        $show_az = $atts['show_alphabet'] !== 'false';

        ob_start();
        ?>
        <div class="wpgt-glossary-index" data-columns="<?php echo $columns; ?>">
            <?php if ( $show_az ) : ?>
            <nav class="wpgt-alphabet-bar" aria-label="<?php esc_attr_e( 'Jump to letter', 'wp-glossary-tooltip' ); ?>">
                <?php foreach ( $groups as $letter => $_ ) : ?>
                    <a href="#wpgt-letter-<?php echo esc_attr( $letter ); ?>"
                       class="wpgt-az-link"><?php echo esc_html( $letter ); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>

            <?php foreach ( $groups as $letter => $letter_posts ) : ?>
            <section class="wpgt-letter-group" id="wpgt-letter-<?php echo esc_attr( $letter ); ?>">
                <h3 class="wpgt-letter-heading"><?php echo esc_html( $letter ); ?></h3>
                <ul class="wpgt-term-list wpgt-columns-<?php echo $columns; ?>">
                    <?php foreach ( $letter_posts as $post ) : ?>
                    <li class="wpgt-term-item">
                        <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"
                           class="wpgt-term-link">
                            <?php echo esc_html( $post->post_title ); ?>
                        </a>
                        <?php
                        $excerpt = $post->post_excerpt
                            ? $post->post_excerpt
                            : wp_trim_words( strip_tags( $post->post_content ), 18 );
                        if ( $excerpt ) :
                        ?>
                        <p class="wpgt-term-excerpt"><?php echo esc_html( $excerpt ); ?></p>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // ------------------------------------------------------------------
    // [wpgt_term id="123"]  or  [wpgt_term slug="wordpress"]
    // ------------------------------------------------------------------
    public static function single_term( $atts ): string {
        $atts = shortcode_atts( [
            'id'   => 0,
            'slug' => '',
        ], $atts, 'wpgt_term' );

        if ( (int) $atts['id'] ) {
            $post = get_post( (int) $atts['id'] );
        } elseif ( $atts['slug'] ) {
            $posts = get_posts( [
                'post_type'   => WPGT_Post_Type::POST_TYPE,
                'name'        => sanitize_title( $atts['slug'] ),
                'numberposts' => 1,
            ] );
            $post = $posts[0] ?? null;
        }

        if ( empty( $post ) || $post->post_type !== WPGT_Post_Type::POST_TYPE ) {
            return '<p class="wpgt-error">' . esc_html__( 'Glossary term not found.', 'wp-glossary-tooltip' ) . '</p>';
        }

        $tooltip_text = get_post_meta( $post->ID, '_wpgt_tooltip_text', true );
        if ( ! $tooltip_text ) {
            $tooltip_text = $post->post_excerpt
                ? $post->post_excerpt
                : wp_trim_words( strip_tags( $post->post_content ), 30 );
        }

        ob_start();
        ?>
        <div class="wpgt-term-box">
            <h4 class="wpgt-term-box__title">
                <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>">
                    <?php echo esc_html( $post->post_title ); ?>
                </a>
            </h4>
            <?php if ( $tooltip_text ) : ?>
            <div class="wpgt-term-box__definition">
                <?php echo wpautop( esc_html( $tooltip_text ) ); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // ------------------------------------------------------------------
    // [wpgt_search placeholder="Search glossary…"]
    // ------------------------------------------------------------------
    public static function search_widget( $atts ): string {
        $atts = shortcode_atts( [
            'placeholder' => __( 'Search glossary…', 'wp-glossary-tooltip' ),
        ], $atts, 'wpgt_search' );

        ob_start();
        ?>
        <div class="wpgt-search-widget">
            <input
                type="search"
                class="wpgt-search-input"
                placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"
                aria-label="<?php esc_attr_e( 'Search glossary terms', 'wp-glossary-tooltip' ); ?>"
                autocomplete="off"
            />
            <div class="wpgt-search-results" role="listbox" aria-label="<?php esc_attr_e( 'Search results', 'wp-glossary-tooltip' ); ?>" hidden></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
