<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shortcodes:
 *   [wpgt_glossary]           - Full glossary index with A–Z bar
 *   [wpgt_term id="123"]      - Single term definition box
 *   [wpgt_search]             - Live search widget
 *   [wpgt_letter_grid]        - Georgian letter grid linking to letter archives
 */
class WPGT_Shortcodes {

    public static function register() {
        add_shortcode( 'wpgt_glossary',    [ __CLASS__, 'glossary_index' ] );
        add_shortcode( 'wpgt_term',        [ __CLASS__, 'single_term'    ] );
        add_shortcode( 'wpgt_search',      [ __CLASS__, 'search_widget'  ] );
        add_shortcode( 'wpgt_letter_grid', [ __CLASS__, 'letter_grid'    ] );
    }

    // ------------------------------------------------------------------
    // [wpgt_glossary columns="3" show_alphabet="true" letter="letter-a"]
    //
    // When placed inside an Elementor letter-archive template,
    // it auto-detects the current wpgt_letter term and filters accordingly.
    // You can also pass letter="letter-a" explicitly.
    // ------------------------------------------------------------------
    public static function glossary_index( $atts ): string {
        // Ensure public CSS is always loaded (needed even if enable_tooltips is off)
        if ( defined('WPGT_PLUGIN_URL') && defined('WPGT_VERSION') ) {
            wp_enqueue_style( 'wpgt-public', WPGT_PLUGIN_URL . 'public/css/public.css', [], WPGT_VERSION );
        }

        $atts = shortcode_atts( [
            'columns'       => WPGT_Settings::get( 'index_columns', 3 ),
            'show_alphabet' => WPGT_Settings::get( 'show_alphabet_bar', true ) ? 'true' : 'false',
            'letter'        => '',   // wpgt_letter term slug e.g. "letter-a"
            'orderby'       => 'menu_order',  // menu_order | title | date
        ], $atts, 'wpgt_glossary' );

        // Auto-detect letter from current taxonomy archive context
        $current_letter_term = null;
        if ( empty( $atts['letter'] ) && is_tax( WPGT_Post_Type::LETTER_TAX ) ) {
            $current_letter_term = get_queried_object();
        } elseif ( ! empty( $atts['letter'] ) ) {
            $current_letter_term = get_term_by( 'slug', $atts['letter'], WPGT_Post_Type::LETTER_TAX );
        }

        // Orderby logic
        if ( $atts['orderby'] === 'title' ) {
            $orderby = 'title';
            $order   = 'ASC';
        } elseif ( $atts['orderby'] === 'date' ) {
            $orderby = 'date';
            $order   = 'DESC';
        } else {
            $orderby = 'menu_order';
            $order   = 'ASC';
        }

        $query_args = [
            'post_type'      => WPGT_Post_Type::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => $orderby,
            'order'          => $order,
        ];

        // Filter by letter if we have one
        if ( $current_letter_term && ! is_wp_error( $current_letter_term ) ) {
            $query_args['tax_query'] = [ [
                'taxonomy' => WPGT_Post_Type::LETTER_TAX,
                'field'    => 'term_id',
                'terms'    => $current_letter_term->term_id,
            ] ];
        }

        $posts   = get_posts( $query_args );
        $columns = max( 1, min( 6, (int) $atts['columns'] ) );
        $show_az = $atts['show_alphabet'] !== 'false';

        // Always fetch ALL active letters from the taxonomy so the bar is complete
        // regardless of which letter is currently being viewed.
        $letter_urls        = [];
        $all_tax_terms      = get_terms( [
            'taxonomy'   => WPGT_Post_Type::LETTER_TAX,
            'hide_empty' => true,
        ] );
        if ( ! is_wp_error( $all_tax_terms ) ) {
            foreach ( $all_tax_terms as $tax_term ) {
                $key = mb_strtoupper( $tax_term->name, 'UTF-8' );
                $url = get_term_link( $tax_term, WPGT_Post_Type::LETTER_TAX );
                $letter_urls[ $key ] = is_wp_error( $url ) ? '' : $url;
            }
        }

        // Current letter slug for "active" detection in the bar
        $current_letter_slug = ( $current_letter_term && ! is_wp_error( $current_letter_term ) )
            ? $current_letter_term->slug
            : '';

        // Display name: use term name, strip "letter-" prefixes (e.g. "letter-a" → "A")
        $current_letter_name = '';
        if ( $current_letter_term && ! is_wp_error( $current_letter_term ) ) {
            $raw_name = $current_letter_term->name;
            // If looks like "letter-X" slug was used as name, extract the X part
            if ( preg_match( '/^letter[-_\s]*(.+)$/iu', $raw_name, $m ) ) {
                $raw_name = $m[1];
            }
            $current_letter_name = mb_strtoupper( $raw_name, 'UTF-8' );
        }

        // Sort letter_urls keys alphabetically for consistent bar order
        ksort( $letter_urls );

        ob_start();
        ?>
        <div class="wpgt-glossary-index" data-columns="<?php echo $columns; ?>">

            <?php if ( $show_az && ! empty( $letter_urls ) ) : ?>
            <nav class="wpgt-alphabet-bar" aria-label="<?php esc_attr_e( 'Jump to letter', 'wp-glossary-tooltip' ); ?>">
                <?php foreach ( $letter_urls as $letter => $arc_url ) :
                    $is_current = ( $letter === $current_letter_name );
                ?>
                    <?php if ( $is_current ) : ?>
                        <span class="wpgt-az-link wpgt-az-current"
                              aria-current="page"><?php echo esc_html( $letter ); ?></span>
                    <?php elseif ( $arc_url ) : ?>
                        <a href="<?php echo esc_url( $arc_url ); ?>"
                           class="wpgt-az-link"><?php echo esc_html( $letter ); ?></a>
                    <?php else : ?>
                        <span class="wpgt-az-link"><?php echo esc_html( $letter ); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>

            <?php if ( empty( $posts ) ) : ?>
                <p class="wpgt-no-terms"><?php esc_html_e( 'No glossary terms found.', 'wp-glossary-tooltip' ); ?></p>

            <?php elseif ( $current_letter_term && ! is_wp_error( $current_letter_term ) ) : ?>
                <?php // Single-letter view — show heading then flat list ?>
                <section class="wpgt-letter-group" id="wpgt-letter-<?php echo esc_attr( $current_letter_name ); ?>">
                    <h3 class="wpgt-letter-heading"><?php echo esc_html( $current_letter_name ); ?></h3>
                    <?php echo self::render_flat_list( $posts, $columns ); ?>
                </section>

            <?php else : ?>
                <?php // Full glossary — group by first letter ?>
                <?php
                $groups = [];
                foreach ( $posts as $post ) {
                    $grp_letter = mb_strtoupper( mb_substr( $post->post_title, 0, 1, 'UTF-8' ), 'UTF-8' );
                    $groups[ $grp_letter ][] = $post;
                }
                ksort( $groups );
                ?>
                <?php foreach ( $groups as $grp_letter => $letter_posts ) : ?>
                <section class="wpgt-letter-group" id="wpgt-letter-<?php echo esc_attr( $grp_letter ); ?>">
                    <h3 class="wpgt-letter-heading"><?php echo esc_html( $grp_letter ); ?></h3>
                    <ul class="wpgt-term-list wpgt-columns-<?php echo $columns; ?>">
                        <?php foreach ( $letter_posts as $post ) : ?>
                        <li class="wpgt-term-item">
                            <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="wpgt-term-link">
                                <?php echo esc_html( $post->post_title ); ?>
                            </a>
                            <?php
                            $excerpt = $post->post_excerpt ?: wp_trim_words( strip_tags( $post->post_content ), 18 );
                            if ( $excerpt ) :
                            ?>
                            <p class="wpgt-term-excerpt"><?php echo esc_html( $excerpt ); ?></p>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Flat term list — used on letter archive pages (no letter grouping needed).
     */
    private static function render_flat_list( array $posts, int $columns ): string {
        $columns = max( 1, min( 6, $columns ) );
        ob_start();
        ?>
        <ul class="wpgt-term-list wpgt-columns-<?php echo $columns; ?>">
            <?php foreach ( $posts as $post ) : ?>
            <li class="wpgt-term-item">
                <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="wpgt-term-link">
                    <?php echo esc_html( $post->post_title ); ?>
                </a>
                <?php
                $excerpt = $post->post_excerpt ?: wp_trim_words( strip_tags( $post->post_content ), 18 );
                if ( $excerpt ) :
                ?>
                <p class="wpgt-term-excerpt"><?php echo esc_html( $excerpt ); ?></p>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return ob_get_clean();
    }

    // ------------------------------------------------------------------
    // [wpgt_letter_grid]
    // ------------------------------------------------------------------
    public static function letter_grid( $atts ): string {
        $atts = shortcode_atts( [], $atts, 'wpgt_letter_grid' );

        $letters = [
            'ა','ბ','გ','დ','ე','ვ','ზ','თ','ი','კ','ლ','მ','ნ',
            'ო','პ','ჟ','რ','ს','ტ','უ','ფ','ქ','ღ','ყ','შ','ჩ',
            'ც','ძ','წ','ჭ','ხ','ჯ','ჰ',
        ];

        $term_map  = [];
        $tax_terms = get_terms( [ 'taxonomy' => WPGT_Post_Type::LETTER_TAX, 'hide_empty' => true ] );
        if ( ! is_wp_error( $tax_terms ) ) {
            foreach ( $tax_terms as $t ) {
                $term_map[ mb_strtoupper( $t->name, 'UTF-8' ) ] = $t;
            }
        }

        // Current letter (for active state)
        $current_slug = is_tax( WPGT_Post_Type::LETTER_TAX )
            ? get_queried_object()->slug
            : '';

        ob_start();
        ?>
        <div class="wpgt-letter-grid">
            <?php foreach ( $letters as $letter ) :
                $tax_term  = $term_map[ $letter ] ?? null;
                $has_terms = $tax_term !== null;
                $url       = $has_terms ? get_term_link( $tax_term, WPGT_Post_Type::LETTER_TAX ) : '';
                $is_active = $has_terms && $tax_term->slug === $current_slug;
            ?>
            <?php if ( $has_terms && $url && ! is_wp_error( $url ) ) : ?>
                <a href="<?php echo esc_url( $url ); ?>"
                   class="wpgt-letter-grid__item wpgt-letter-grid__item--active<?php echo $is_active ? ' wpgt-letter-grid__item--current' : ''; ?>">
                    <?php echo esc_html( $letter ); ?>
                </a>
            <?php else : ?>
                <span class="wpgt-letter-grid__item wpgt-letter-grid__item--empty">
                    <?php echo esc_html( $letter ); ?>
                </span>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // ------------------------------------------------------------------
    // [wpgt_term id="123"]  or  [wpgt_term slug="wordpress"]
    // ------------------------------------------------------------------
    public static function single_term( $atts ): string {
        $atts = shortcode_atts( [ 'id' => 0, 'slug' => '' ], $atts, 'wpgt_term' );

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

        $tooltip_text = get_post_meta( $post->ID, '_wpgt_tooltip_text', true )
            ?: ( $post->post_excerpt ?: wp_trim_words( strip_tags( $post->post_content ), 30 ) );

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
            <input type="search" class="wpgt-search-input"
                   placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"
                   aria-label="<?php esc_attr_e( 'Search glossary terms', 'wp-glossary-tooltip' ); ?>"
                   autocomplete="off" />
            <div class="wpgt-search-results" role="listbox"
                 aria-label="<?php esc_attr_e( 'Search results', 'wp-glossary-tooltip' ); ?>" hidden></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
