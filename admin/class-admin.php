<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPGT_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menus'          ] );
        add_action( 'add_meta_boxes',        [ __CLASS__, 'add_meta_boxes'     ] );
        add_action( 'save_post',             [ __CLASS__, 'save_meta'          ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets'     ] );
        add_action( 'admin_post_wpgt_save_settings', [ __CLASS__, 'save_settings' ] );

        // Custom columns on the term list table
        add_filter( 'manage_' . WPGT_Post_Type::POST_TYPE . '_posts_columns',       [ __CLASS__, 'term_columns'      ] );
        add_action( 'manage_' . WPGT_Post_Type::POST_TYPE . '_posts_custom_column', [ __CLASS__, 'term_column_data'  ], 10, 2 );
    }

    // ------------------------------------------------------------------
    // Admin menu
    // ------------------------------------------------------------------
    public static function add_menus() {
        add_submenu_page(
            'edit.php?post_type=' . WPGT_Post_Type::POST_TYPE,
            __( 'WP Glossary Settings', 'wp-glossary-tooltip' ),
            __( 'Settings',            'wp-glossary-tooltip' ),
            'manage_options',
            'wpgt-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    // ------------------------------------------------------------------
    // Enqueue admin assets (only on our pages)
    // ------------------------------------------------------------------
    public static function enqueue_assets( string $hook ) {
        $screen = get_current_screen();
        if ( ! $screen ) return;

        $is_our_screen =
            $screen->post_type === WPGT_Post_Type::POST_TYPE ||
            ( isset( $_GET['page'] ) && $_GET['page'] === 'wpgt-settings' );

        if ( ! $is_our_screen ) return;

        wp_enqueue_style(
            'wpgt-admin',
            WPGT_PLUGIN_URL . 'admin/admin.css',
            [],
            WPGT_VERSION
        );
        wp_enqueue_script(
            'wpgt-admin',
            WPGT_PLUGIN_URL . 'admin/admin.js',
            [ 'jquery', 'wp-color-picker' ],
            WPGT_VERSION,
            true
        );
        wp_enqueue_style( 'wp-color-picker' );
    }

    // ------------------------------------------------------------------
    // Meta boxes
    // ------------------------------------------------------------------
    public static function add_meta_boxes() {
        add_meta_box(
            'wpgt-term-options',
            __( 'Glossary Term Options', 'wp-glossary-tooltip' ),
            [ __CLASS__, 'render_term_meta_box' ],
            WPGT_Post_Type::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_term_meta_box( WP_Post $post ) {
        wp_nonce_field( 'wpgt_save_meta', 'wpgt_meta_nonce' );

        $tooltip_text   = get_post_meta( $post->ID, '_wpgt_tooltip_text',    true );
        $synonyms       = get_post_meta( $post->ID, '_wpgt_synonyms',        true );
        $related        = get_post_meta( $post->ID, '_wpgt_related_terms',   true );
        ?>
        <div class="wpgt-meta-wrap">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wpgt_tooltip_text">
                            <?php esc_html_e( 'Tooltip Text', 'wp-glossary-tooltip' ); ?>
                        </label>
                    </th>
                    <td>
                        <textarea id="wpgt_tooltip_text" name="wpgt_tooltip_text"
                                  rows="3" class="large-text"><?php echo esc_textarea( $tooltip_text ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Short definition shown in the tooltip. Leave blank to use the post excerpt.', 'wp-glossary-tooltip' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wpgt_synonyms">
                            <?php esc_html_e( 'Synonyms / Aliases', 'wp-glossary-tooltip' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="wpgt_synonyms" name="wpgt_synonyms"
                               value="<?php echo esc_attr( $synonyms ); ?>"
                               class="large-text"
                               placeholder="<?php esc_attr_e( 'e.g. API, Application Programming Interface', 'wp-glossary-tooltip' ); ?>" />
                        <p class="description">
                            <?php esc_html_e( 'Comma-separated list of synonyms that will also trigger this tooltip.', 'wp-glossary-tooltip' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wpgt_related_terms">
                            <?php esc_html_e( 'Related Terms (IDs)', 'wp-glossary-tooltip' ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="wpgt_related_terms" name="wpgt_related_terms"
                               value="<?php echo esc_attr( $related ); ?>"
                               class="large-text"
                               placeholder="<?php esc_attr_e( 'e.g. 42, 77, 103', 'wp-glossary-tooltip' ); ?>" />
                        <p class="description">
                            <?php esc_html_e( 'Comma-separated post IDs of related glossary terms shown at the bottom of the tooltip.', 'wp-glossary-tooltip' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    public static function save_meta( int $post_id ) {
        if ( ! isset( $_POST['wpgt_meta_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['wpgt_meta_nonce'], 'wpgt_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( get_post_type( $post_id ) !== WPGT_Post_Type::POST_TYPE ) return;

        $fields = [
            'wpgt_tooltip_text'   => '_wpgt_tooltip_text',
            'wpgt_synonyms'       => '_wpgt_synonyms',
            'wpgt_related_terms'  => '_wpgt_related_terms',
        ];

        foreach ( $fields as $post_key => $meta_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_textarea_field( $_POST[ $post_key ] ) );
            }
        }
    }

    // ------------------------------------------------------------------
    // Custom columns
    // ------------------------------------------------------------------
    public static function term_columns( array $columns ): array {
        unset( $columns['date'] );
        return array_merge( $columns, [
            'wpgt_tooltip'  => __( 'Tooltip Preview',  'wp-glossary-tooltip' ),
            'wpgt_synonyms' => __( 'Synonyms',         'wp-glossary-tooltip' ),
            'date'          => __( 'Date',              'wp-glossary-tooltip' ),
        ] );
    }

    public static function term_column_data( string $column, int $post_id ) {
        if ( $column === 'wpgt_tooltip' ) {
            $text = get_post_meta( $post_id, '_wpgt_tooltip_text', true );
            if ( ! $text ) {
                $post = get_post( $post_id );
                $text = $post->post_excerpt ?: wp_trim_words( strip_tags( $post->post_content ), 12 );
            }
            echo esc_html( wp_trim_words( $text, 12 ) );
        }
        if ( $column === 'wpgt_synonyms' ) {
            $synonyms = get_post_meta( $post_id, '_wpgt_synonyms', true );
            echo $synonyms ? '<code>' . esc_html( $synonyms ) . '</code>' : '—';
        }
    }

    // ------------------------------------------------------------------
    // Settings page
    // ------------------------------------------------------------------
    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $settings     = WPGT_Settings::get_all();
        $post_types   = get_post_types( [ 'public' => true ], 'objects' );
        $saved_notice = isset( $_GET['wpgt_saved'] );
        ?>
        <div class="wrap wpgt-settings-wrap">
            <h1><?php esc_html_e( 'WP Glossary Tooltip — Settings', 'wp-glossary-tooltip' ); ?></h1>

            <?php if ( $saved_notice ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'Settings saved.', 'wp-glossary-tooltip' ); ?></p>
            </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'wpgt_settings_save', 'wpgt_settings_nonce' ); ?>
                <input type="hidden" name="action" value="wpgt_save_settings" />

                <h2 class="nav-tab-wrapper wpgt-tabs">
                    <a href="#wpgt-tab-general"  class="nav-tab nav-tab-active"><?php esc_html_e( 'General',   'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-tooltip"  class="nav-tab"><?php esc_html_e( 'Tooltip',    'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-index"    class="nav-tab"><?php esc_html_e( 'Index Page', 'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-advanced" class="nav-tab"><?php esc_html_e( 'Advanced',   'wp-glossary-tooltip' ); ?></a>
                </h2>

                <!-- GENERAL TAB -->
                <div id="wpgt-tab-general" class="wpgt-tab-content">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Enable Tooltips', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enable_tooltips" value="1"
                                           <?php checked( $settings['enable_tooltips'] ); ?> />
                                    <?php esc_html_e( 'Automatically add tooltips to glossary terms in content', 'wp-glossary-tooltip' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Parse Post Types', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <?php foreach ( $post_types as $pt ) :
                                    if ( in_array( $pt->name, [ WPGT_Post_Type::POST_TYPE, 'attachment' ], true ) ) continue;
                                ?>
                                <label style="display:block; margin-bottom:4px;">
                                    <input type="checkbox"
                                           name="parse_post_types[]"
                                           value="<?php echo esc_attr( $pt->name ); ?>"
                                           <?php checked( in_array( $pt->name, (array) $settings['parse_post_types'], true ) ); ?> />
                                    <?php echo esc_html( $pt->labels->name ); ?>
                                </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'First Occurrence Only', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="first_occurrence" value="1"
                                           <?php checked( $settings['first_occurrence'] ); ?> />
                                    <?php esc_html_e( 'Only highlight the first occurrence of each term per page', 'wp-glossary-tooltip' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Case Sensitive', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="case_sensitive" value="1"
                                           <?php checked( $settings['case_sensitive'] ); ?> />
                                    <?php esc_html_e( 'Match terms case-sensitively', 'wp-glossary-tooltip' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Exclude Headings', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="exclude_headings" value="1"
                                           <?php checked( $settings['exclude_headings'] ); ?> />
                                    <?php esc_html_e( 'Do not add tooltips inside H1–H6 tags', 'wp-glossary-tooltip' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Exclude Links', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="exclude_links" value="1"
                                           <?php checked( $settings['exclude_links'] ); ?> />
                                    <?php esc_html_e( 'Do not add tooltips inside &lt;a&gt; tags', 'wp-glossary-tooltip' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- TOOLTIP TAB -->
                <div id="wpgt-tab-tooltip" class="wpgt-tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Open On', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <select name="open_on">
                                    <option value="hover" <?php selected( $settings['open_on'], 'hover' ); ?>><?php esc_html_e( 'Hover', 'wp-glossary-tooltip' ); ?></option>
                                    <option value="click" <?php selected( $settings['open_on'], 'click' ); ?>><?php esc_html_e( 'Click', 'wp-glossary-tooltip' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Position', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <select name="tooltip_position">
                                    <?php foreach ( [ 'top', 'bottom', 'left', 'right' ] as $pos ) : ?>
                                    <option value="<?php echo $pos; ?>" <?php selected( $settings['tooltip_position'], $pos ); ?>>
                                        <?php echo ucfirst( $pos ); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Theme', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <select name="tooltip_theme">
                                    <option value="dark"    <?php selected( $settings['tooltip_theme'], 'dark'    ); ?>><?php esc_html_e( 'Dark',     'wp-glossary-tooltip' ); ?></option>
                                    <option value="light"   <?php selected( $settings['tooltip_theme'], 'light'   ); ?>><?php esc_html_e( 'Light',    'wp-glossary-tooltip' ); ?></option>
                                    <option value="branded" <?php selected( $settings['tooltip_theme'], 'branded' ); ?>><?php esc_html_e( 'Branded',  'wp-glossary-tooltip' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Brand Color', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <input type="text" name="brand_color"
                                       value="<?php echo esc_attr( $settings['brand_color'] ); ?>"
                                       class="wpgt-color-picker" />
                                <p class="description"><?php esc_html_e( 'Used for the "branded" theme and term underline color.', 'wp-glossary-tooltip' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Tooltip Width', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <input type="number" name="tooltip_width" min="180" max="600"
                                       value="<?php echo (int) $settings['tooltip_width']; ?>" /> px
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Show "See More" Link', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="show_see_more" value="1"
                                           <?php checked( $settings['show_see_more'] ); ?> />
                                    <?php esc_html_e( 'Show a "Read more →" link in the tooltip pointing to the term page', 'wp-glossary-tooltip' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Open Link in New Tab', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="link_new_tab" value="1"
                                           <?php checked( $settings['link_new_tab'] ?? true ); ?> />
                                    <?php esc_html_e( 'Open the "Read more" link in a new browser tab', 'wp-glossary-tooltip' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Glass Opacity', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <input type="range" name="glass_opacity" min="0" max="100"
                                       value="<?php echo (int) ( $settings['glass_opacity'] ?? 85 ); ?>"
                                       oninput="document.getElementById('wpgt-opacity-val').textContent=this.value+'%'" />
                                <span id="wpgt-opacity-val"><?php echo (int) ( $settings['glass_opacity'] ?? 85 ); ?>%</span>
                                <p class="description"><?php esc_html_e( '0 = fully transparent, 100 = fully opaque', 'wp-glossary-tooltip' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Glass Blur', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <input type="range" name="glass_blur" min="0" max="40"
                                       value="<?php echo (int) ( $settings['glass_blur'] ?? 12 ); ?>"
                                       oninput="document.getElementById('wpgt-blur-val').textContent=this.value+'px'" />
                                <span id="wpgt-blur-val"><?php echo (int) ( $settings['glass_blur'] ?? 12 ); ?>px</span>
                                <p class="description"><?php esc_html_e( '0 = no blur, 20–40 = heavy frosted glass', 'wp-glossary-tooltip' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- INDEX PAGE TAB -->
                <div id="wpgt-tab-index" class="wpgt-tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Glossary Slug', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <input type="text" name="glossary_slug"
                                       value="<?php echo esc_attr( $settings['glossary_slug'] ); ?>" />
                                <p class="description"><?php esc_html_e( 'URL slug for the glossary archive (requires saving permalinks after change).', 'wp-glossary-tooltip' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Index Columns', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <select name="index_columns">
                                    <?php for ( $c = 1; $c <= 4; $c++ ) : ?>
                                    <option value="<?php echo $c; ?>" <?php selected( (int) $settings['index_columns'], $c ); ?>>
                                        <?php echo $c; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Show A–Z Bar', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="show_alphabet_bar" value="1"
                                           <?php checked( $settings['show_alphabet_bar'] ); ?> />
                                    <?php esc_html_e( 'Show the A–Z navigation bar on the [wpgt_glossary] shortcode output', 'wp-glossary-tooltip' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    <div class="wpgt-shortcode-help">
                        <h3><?php esc_html_e( 'Available Shortcodes', 'wp-glossary-tooltip' ); ?></h3>
                        <dl>
                            <dt><code>[wpgt_glossary]</code></dt>
                            <dd><?php esc_html_e( 'Full A–Z glossary index. Accepts: columns, show_alphabet, category, orderby.', 'wp-glossary-tooltip' ); ?></dd>
                            <dt><code>[wpgt_term id="123"]</code></dt>
                            <dd><?php esc_html_e( 'Inline definition box for a single term. Also accepts slug="my-term".', 'wp-glossary-tooltip' ); ?></dd>
                            <dt><code>[wpgt_search]</code></dt>
                            <dd><?php esc_html_e( 'Live AJAX search widget. Accepts: placeholder.', 'wp-glossary-tooltip' ); ?></dd>
                        </dl>
                    </div>
                </div>

                <!-- ADVANCED TAB -->
                <div id="wpgt-tab-advanced" class="wpgt-tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'REST API', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <p><?php esc_html_e( 'Endpoints are available at:', 'wp-glossary-tooltip' ); ?></p>
                                <ul>
                                    <li><code><?php echo esc_html( rest_url( 'wpgt/v1/terms' ) ); ?></code></li>
                                    <li><code><?php echo esc_html( rest_url( 'wpgt/v1/terms/{id}' ) ); ?></code></li>
                                    <li><code><?php echo esc_html( rest_url( 'wpgt/v1/search?q=…' ) ); ?></code></li>
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Flush Rewrite Rules', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <a href="<?php echo esc_url( add_query_arg( [ 'wpgt_flush' => '1', '_wpnonce' => wp_create_nonce( 'wpgt_flush' ) ], admin_url() ) ); ?>"
                                   class="button">
                                    <?php esc_html_e( 'Flush Rewrite Rules', 'wp-glossary-tooltip' ); ?>
                                </a>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button( __( 'Save Settings', 'wp-glossary-tooltip' ) ); ?>
            </form>
        </div>
        <?php
    }

    public static function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'wpgt_settings_save', 'wpgt_settings_nonce' );

        $data = $_POST;
        // Cast checkboxes
        foreach ( [ 'enable_tooltips', 'first_occurrence', 'case_sensitive', 'exclude_headings',
                    'exclude_links', 'show_see_more', 'show_alphabet_bar', 'link_new_tab' ] as $cb ) {
            $data[ $cb ] = ! empty( $data[ $cb ] );
        }
        // Sanitise scalars
        $data['glossary_slug']   = sanitize_title( $data['glossary_slug']  ?? 'glossary' );
        $data['tooltip_width']   = (int) ( $data['tooltip_width']           ?? 280 );
        $data['index_columns']   = (int) ( $data['index_columns']           ?? 3 );
        $data['brand_color']     = sanitize_hex_color( $data['brand_color'] ?? '#2563eb' );
        $data['glass_opacity']   = min( 100, max( 0, (int) ( $data['glass_opacity'] ?? 85 ) ) );
        $data['glass_blur']      = min( 40,  max( 0, (int) ( $data['glass_blur']    ?? 12 ) ) );
        $data['parse_post_types'] = array_map( 'sanitize_key', (array) ( $data['parse_post_types'] ?? [] ) );

        WPGT_Settings::update( $data );

        wp_redirect( add_query_arg( 'wpgt_saved', '1', wp_get_referer() ) );
        exit;
    }
}
