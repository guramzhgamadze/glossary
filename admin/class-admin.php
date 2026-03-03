<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPGT_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menus'          ] );
        // Remove any "Re-Order" submenu injected by third-party plugins (e.g. Post Types Order)
        add_action( 'admin_menu',            [ __CLASS__, 'remove_foreign_menus' ], 9999 );
        add_action( 'add_meta_boxes',        [ __CLASS__, 'add_meta_boxes'     ] );
        add_action( 'save_post',             [ __CLASS__, 'save_meta'          ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets'     ] );
        add_action( 'admin_post_wpgt_save_settings', [ __CLASS__, 'save_settings' ] );
        add_action( 'admin_post_wpgt_export',        [ __CLASS__, 'export_csv'    ] );
        add_action( 'admin_post_wpgt_import',        [ __CLASS__, 'import_csv'    ] );
        add_action( 'admin_post_wpgt_sync_letters',     [ __CLASS__, 'sync_letter_taxonomy'  ] );
        add_action( 'admin_post_wpgt_regen_forms',       [ __CLASS__, 'regen_declined_forms'  ] );
        add_action( 'admin_post_wpgt_save_styles',       [ __CLASS__, 'save_styles'           ] );
        add_action( 'save_post',                         [ __CLASS__, 'save_skip_meta'         ] );
        add_action( 'wp_ajax_wpgt_save_order',        [ __CLASS__, 'ajax_save_order'       ] );

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

    public static function remove_foreign_menus() {
        $cpt = 'edit.php?post_type=' . WPGT_Post_Type::POST_TYPE;
        // "Post Types Order" plugin registers slug 'post-types-order-{post_type}'
        remove_submenu_page( $cpt, 'post-types-order-' . WPGT_Post_Type::POST_TYPE );
        // Some versions use just 'reorder' or 're-order'
        remove_submenu_page( $cpt, 'reorder' );
        remove_submenu_page( $cpt, 're-order' );
        // Catch-all: remove any submenu whose menu title contains "Re-Order" or "Reorder"
        global $submenu;
        if ( isset( $submenu[ $cpt ] ) ) {
            foreach ( $submenu[ $cpt ] as $key => $item ) {
                $title = $item[0] ?? '';
                // Strip HTML tags (some plugins bold the label)
                $clean = wp_strip_all_tags( $title );
                if ( stripos( $clean, 're-order' ) !== false || stripos( $clean, 'reorder' ) !== false ) {
                    unset( $submenu[ $cpt ][ $key ] );
                }
            }
        }
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
        wp_enqueue_script( 'jquery-ui-sortable' );

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

        // "Skip tooltips" checkbox on all public non-glossary post types
        $public_types = get_post_types( [ 'public' => true ], 'names' );
        foreach ( $public_types as $pt ) {
            if ( in_array( $pt, [ WPGT_Post_Type::POST_TYPE, 'attachment' ], true ) ) continue;
            add_meta_box(
                'wpgt-skip-tooltips',
                __( 'Glossary Tooltips', 'wp-glossary-tooltip' ),
                [ __CLASS__, 'render_skip_meta_box' ],
                $pt,
                'side',
                'default'
            );
        }
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
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Declined Forms', 'wp-glossary-tooltip' ); ?>
                    </th>
                    <td>
                        <?php
                        $forms_json = get_post_meta( $post->ID, '_wpgt_declined_forms', true );
                        $forms      = $forms_json ? json_decode( $forms_json, true ) : [];
                        if ( ! empty( $forms ) ) :
                        ?>
                        <details>
                            <summary style="cursor:pointer; color:#2563eb; font-size:0.875rem;">
                                <?php printf(
                                    esc_html__( '%d forms stored (click to view)', 'wp-glossary-tooltip' ),
                                    count( $forms )
                                ); ?>
                            </summary>
                            <p style="margin:8px 0 0; font-size:0.8rem; color:#555; line-height:1.8;">
                                <?php echo esc_html( implode( ', ', $forms ) ); ?>
                            </p>
                        </details>
                        <?php else : ?>
                        <em style="color:#999; font-size:0.875rem;">
                            <?php esc_html_e( 'Not generated yet — save this term to generate.', 'wp-glossary-tooltip' ); ?>
                        </em>
                        <?php endif; ?>
                        <p class="description">
                            <?php esc_html_e( 'Auto-generated from the title and synonyms. Only admins can see this.', 'wp-glossary-tooltip' ); ?>
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
                    <a href="#wpgt-tab-general"  class="nav-tab nav-tab-active"><?php esc_html_e( 'General',        'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-tooltip"  class="nav-tab"><?php esc_html_e( 'Tooltip',         'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-index"    class="nav-tab"><?php esc_html_e( 'Index Page',      'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-advanced" class="nav-tab"><?php esc_html_e( 'Advanced',        'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-import"   class="nav-tab"><?php esc_html_e( 'Import / Export', 'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-sort"     class="nav-tab"><?php esc_html_e( 'Sort Terms',      'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-styles"   class="nav-tab"><?php esc_html_e( '🎨 Styles',       'wp-glossary-tooltip' ); ?></a>
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

                    <h3 style="margin-top:1em; padding-bottom:6px; border-bottom:1px solid #ddd;"><?php esc_html_e( 'Behaviour', 'wp-glossary-tooltip' ); ?></h3>
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
                                    <?php foreach ( [ 'top', 'bottom' ] as $pos ) : ?>
                                    <option value="<?php echo $pos; ?>" <?php selected( $settings['tooltip_position'], $pos ); ?>><?php echo ucfirst( $pos ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Show "Read More" Link', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="show_see_more" value="1" <?php checked( $settings['show_see_more'] ); ?> />
                                    <?php esc_html_e( 'Show a "Read more \u2192" link pointing to the term page', 'wp-glossary-tooltip' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Open Link in New Tab', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="link_new_tab" value="1" <?php checked( $settings['link_new_tab'] ?? true ); ?> />
                                    <?php esc_html_e( 'Open the "Read more" link in a new browser tab', 'wp-glossary-tooltip' ); ?>
                                </label>
                            </td>
                        </tr>

                    </table>

                    <h3 style="margin-top:2em; padding-bottom:6px; border-bottom:1px solid #ddd;"><?php esc_html_e( 'Appearance', 'wp-glossary-tooltip' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Theme', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <select name="tooltip_theme">
                                    <option value="dark"    <?php selected( $settings['tooltip_theme'], 'dark'    ); ?>><?php esc_html_e( 'Dark',    'wp-glossary-tooltip' ); ?></option>
                                    <option value="light"   <?php selected( $settings['tooltip_theme'], 'light'   ); ?>><?php esc_html_e( 'Light',   'wp-glossary-tooltip' ); ?></option>
                                    <option value="branded" <?php selected( $settings['tooltip_theme'], 'branded' ); ?>><?php esc_html_e( 'Branded', 'wp-glossary-tooltip' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Base colour palette. Override colours below.', 'wp-glossary-tooltip' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php esc_html_e( '"Read More" Link Color', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <input type="text" name="see_more_color"
                                       value="<?php echo esc_attr( $settings['see_more_color'] ?? '' ); ?>"
                                       class="wpgt-color-picker" />
                                <p class="description"><?php esc_html_e( 'Leave blank to use the theme default.', 'wp-glossary-tooltip' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Brand / Underline Color', 'wp-glossary-tooltip' ); ?></th>
                            <td>
                                <input type="text" name="brand_color"
                                       value="<?php echo esc_attr( $settings['brand_color'] ); ?>"
                                       class="wpgt-color-picker" />
                                <p class="description"><?php esc_html_e( 'Used for the "Branded" theme and the dashed underline on trigger words.', 'wp-glossary-tooltip' ); ?></p>
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

                <!-- IMPORT / EXPORT TAB — outside the settings form to avoid nesting -->
                <div id="wpgt-tab-import" class="wpgt-tab-content" style="display:none;">

                    <h3><?php esc_html_e( 'Export Glossary', 'wp-glossary-tooltip' ); ?></h3>
                    <p><?php esc_html_e( 'Download all glossary terms as an Excel (.xlsx) file. Edit in Excel or Google Sheets, then re-import.', 'wp-glossary-tooltip' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=wpgt_export&_wpnonce=' . wp_create_nonce('wpgt_export') ) ); ?>"
                       class="button button-secondary">
                        ⬇ <?php esc_html_e( 'Download Excel (.xlsx)', 'wp-glossary-tooltip' ); ?>
                    </a>

                    <hr style="margin:2em 0;">

                    <h3><?php esc_html_e( 'Import Glossary', 'wp-glossary-tooltip' ); ?></h3>
                    <p><?php esc_html_e( 'Upload an Excel (.xlsx) file to bulk-create or update glossary terms.', 'wp-glossary-tooltip' ); ?></p>
                    <p><?php esc_html_e( 'Two columns only:', 'wp-glossary-tooltip' ); ?>
                        <code>word</code> <?php esc_html_e( 'and', 'wp-glossary-tooltip' ); ?> <code>explanation</code> <?php esc_html_e( '(column headers in row 1)', 'wp-glossary-tooltip' ); ?>
                    </p>
                    <p><?php esc_html_e( 'If a word already exists it will be updated. New words will be created.', 'wp-glossary-tooltip' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'wpgt_import', 'wpgt_import_nonce' ); ?>
                        <input type="hidden" name="action" value="wpgt_import">
                        <input type="file" name="wpgt_csv" accept=".xlsx" required style="margin-right:8px;">
                        <input type="submit" class="button button-primary" value="⬆ <?php esc_attr_e( 'Import Excel', 'wp-glossary-tooltip' ); ?>">
                    </form>
                    <?php if ( ! empty($_GET['wpgt_imported']) ) : ?>
                    <div class="notice notice-success inline" style="margin-top:1em;">
                        <p><?php printf( esc_html__( 'Import complete: %d terms created, %d updated.', 'wp-glossary-tooltip' ),
                            (int) ($_GET['created'] ?? 0), (int) ($_GET['updated'] ?? 0) ); ?></p>
                    </div>
                    <?php endif; ?>

                    <hr style="margin:2em 0;">

                    <h3><?php esc_html_e( 'Letter Taxonomy', 'wp-glossary-tooltip' ); ?></h3>
                    <p><?php esc_html_e( 'Assigns every glossary term to its first-letter taxonomy term (ა, ბ, გ…). Run this once after importing terms, or whenever you add new terms starting with a new letter. Each letter gets its own archive URL at /glossary/letter/letter-a/ which Elementor sees as a taxonomy archive — build one template and apply it to all letter archives.', 'wp-glossary-tooltip' ); ?></p>
                    <?php
                    $letter_terms = get_terms( [ 'taxonomy' => WPGT_Post_Type::LETTER_TAX, 'hide_empty' => false ] );
                    $letter_list  = ! is_wp_error( $letter_terms ) && ! empty( $letter_terms )
                        ? implode( ', ', array_map( fn($t) => $t->name . ' <small>(' . $t->count . ')</small>', $letter_terms ) )
                        : '<em>' . esc_html__( 'None yet', 'wp-glossary-tooltip' ) . '</em>';
                    ?>
                    <p><?php esc_html_e( 'Current letters:', 'wp-glossary-tooltip' ); ?> <?php echo $letter_list; ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                        <?php wp_nonce_field( 'wpgt_sync_letters', 'wpgt_sync_letters_nonce' ); ?>
                        <input type="hidden" name="action" value="wpgt_sync_letters">
                        <input type="submit" class="button button-secondary"
                               value="⟳ <?php esc_attr_e( 'Sync Letter Taxonomy', 'wp-glossary-tooltip' ); ?>">
                    </form>
                    <?php if ( isset( $_GET['wpgt_synced'] ) ) : ?>
                    <div class="notice notice-success inline" style="margin-top:1em;">
                        <p><?php printf(
                            esc_html__( 'Done — %d terms synced, %d letter slug(s) repaired.', 'wp-glossary-tooltip' ),
                            (int) $_GET['wpgt_synced'],
                            (int) ( $_GET['wpgt_fixed'] ?? 0 )
                        ); ?></p>
                        <p><?php esc_html_e( 'If letter archive pages still 404, go to Settings → Permalinks and click Save.', 'wp-glossary-tooltip' ); ?></p>
                    </div>
                    <?php endif; ?>

                    <hr style="margin:2em 0;">

                    <h3><?php esc_html_e( 'Declined Forms', 'wp-glossary-tooltip' ); ?></h3>
                    <p><?php esc_html_e( 'For each glossary term, the plugin auto-generates all declined forms (სტრესი, სტრესს, სტრესმა, სტრესისგან…) and stores them invisibly. The tooltip highlighter matches these exact forms — no guesswork, no false positives.', 'wp-glossary-tooltip' ); ?></p>
                    <p><?php esc_html_e( 'Forms are regenerated automatically each time you save a term. Click below to regenerate all terms at once (useful after bulk import).', 'wp-glossary-tooltip' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                        <?php wp_nonce_field( 'wpgt_regen_forms', 'wpgt_regen_nonce' ); ?>
                        <input type="hidden" name="action" value="wpgt_regen_forms">
                        <input type="submit" class="button button-secondary"
                               value="⟳ <?php esc_attr_e( 'Regenerate All Declined Forms', 'wp-glossary-tooltip' ); ?>">
                    </form>
                    <?php if ( isset( $_GET['wpgt_regenok'] ) ) : ?>
                    <div class="notice notice-success inline" style="margin-top:1em;">
                        <p><?php printf(
                            esc_html__( 'Done — declined forms regenerated for %d terms.', 'wp-glossary-tooltip' ),
                            (int) $_GET['wpgt_regenok']
                        ); ?></p>
                    </div>
                    <?php endif; ?>

                </div>

                <!-- SORT TERMS TAB — outside the settings form -->
                <div id="wpgt-tab-sort" class="wpgt-tab-content" style="display:none;">
                    <?php
                    $letter_terms = get_terms( [
                        'taxonomy'   => WPGT_Post_Type::LETTER_TAX,
                        'hide_empty' => true,
                        'orderby'    => 'name',
                        'order'      => 'ASC',
                    ] );

                    $selected_slug = isset( $_GET['wpgt_letter'] ) ? sanitize_text_field( $_GET['wpgt_letter'] ) : '';
                    $selected_term = null;

                    if ( $selected_slug && ! is_wp_error( $letter_terms ) ) {
                        foreach ( $letter_terms as $lt ) {
                            if ( $lt->slug === $selected_slug ) { $selected_term = $lt; break; }
                        }
                    }
                    if ( ! $selected_term && ! is_wp_error( $letter_terms ) && ! empty( $letter_terms ) ) {
                        $selected_term = $letter_terms[0];
                        $selected_slug = $selected_term->slug;
                    }

                    $query_args = [
                        'post_type'      => WPGT_Post_Type::POST_TYPE,
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'orderby'        => 'menu_order',
                        'order'          => 'ASC',
                    ];
                    if ( $selected_term ) {
                        $query_args['tax_query'] = [ [
                            'taxonomy' => WPGT_Post_Type::LETTER_TAX,
                            'field'    => 'term_id',
                            'terms'    => $selected_term->term_id,
                        ] ];
                    }
                    $sort_posts = get_posts( $query_args );
                    ?>

                    <p class="description"><?php esc_html_e( 'Drag and drop to set the display order within each letter. Saves automatically.', 'wp-glossary-tooltip' ); ?></p>

                    <?php if ( ! is_wp_error( $letter_terms ) && ! empty( $letter_terms ) ) : ?>
                    <div style="margin:14px 0; display:flex; flex-wrap:wrap; gap:6px;">
                        <?php foreach ( $letter_terms as $lt ) :
                            $url = add_query_arg( [
                                'post_type'   => WPGT_Post_Type::POST_TYPE,
                                'page'        => 'wpgt-settings',
                                'wpgt_letter' => $lt->slug,
                                '#'           => 'wpgt-tab-sort',
                            ], admin_url( 'edit.php' ) );
                            $active = ( $lt->slug === $selected_slug );
                        ?>
                        <a href="<?php echo esc_url( $url ); ?>#wpgt-tab-sort"
                           style="display:inline-block; padding:5px 13px; border-radius:4px;
                                  text-decoration:none; font-size:1.1rem; font-weight:700;
                                  background:<?php echo $active ? '#2563eb' : '#f0f0f0'; ?>;
                                  color:<?php echo $active ? '#fff' : '#333'; ?>;">
                            <?php echo esc_html( $lt->name ); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ( empty( $sort_posts ) ) : ?>
                        <p><?php esc_html_e( 'No terms found.', 'wp-glossary-tooltip' ); ?></p>
                    <?php else : ?>
                    <ul id="wpgt-sortable" style="list-style:none; margin:0; padding:0; max-width:680px;">
                        <?php foreach ( $sort_posts as $sp ) : ?>
                        <li data-id="<?php echo (int) $sp->ID; ?>"
                            style="display:flex; align-items:center; gap:12px; background:#fff;
                                   border:1px solid #ddd; border-radius:6px; padding:11px 15px;
                                   margin-bottom:7px; cursor:grab; user-select:none;">
                            <span style="color:#bbb; font-size:18px; flex-shrink:0;">&#9776;</span>
                            <span style="font-weight:600;"><?php echo esc_html( $sp->post_title ); ?></span>
                            <?php
                            $ex = $sp->post_excerpt ?: wp_trim_words( strip_tags( $sp->post_content ), 10 );
                            if ( $ex ) echo '<span style="color:#888;font-size:0.82rem;">' . esc_html( $ex ) . '</span>';
                            ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <p id="wpgt-order-saved" style="display:none; color:#2563eb; margin-top:10px; font-weight:600;">
                        ✓ <?php esc_html_e( 'Order saved!', 'wp-glossary-tooltip' ); ?>
                    </p>
                    <script>
                    jQuery(function($){
                        $('#wpgt-sortable').sortable({
                            placeholder: 'wpgt-sort-ph',
                            update: function(){
                                var ids = [];
                                $('#wpgt-sortable li').each(function(){ ids.push($(this).data('id')); });
                                $.post(ajaxurl,{
                                    action:'wpgt_save_order', order:ids,
                                    _wpnonce:'<?php echo wp_create_nonce("wpgt_save_order"); ?>'
                                }, function(r){ if(r.success){$('#wpgt-order-saved').fadeIn().delay(2000).fadeOut();} });
                            }
                        });
                    });
                    </script>
                    <style>
                    .wpgt-sort-ph{background:#e8f0fe;border:2px dashed #2563eb;border-radius:6px;height:48px;margin-bottom:7px;list-style:none;}
                    #wpgt-sortable li:active{cursor:grabbing;}
                    #wpgt-tab-sort{padding-bottom:80px;}
                    </style>
                    <?php endif; ?>
                </div>

                <!-- STYLES TAB — outside the settings form, has its own form -->
                <div id="wpgt-tab-styles" class="wpgt-tab-content" style="display:none;">
                    <?php self::render_styles_tab(); ?>
                </div>

        </div>
        <?php
    }

    // ------------------------------------------------------------------
    // Styles tab
    // ------------------------------------------------------------------
    public static function render_styles_tab() {
        $defaults = self::get_style_defaults();
        $saved    = get_option( 'wpgt_styles', [] );
        $s        = wp_parse_args( $saved, $defaults );

        if ( isset( $_GET['wpgt_styles_saved'] ) ) : ?>
            <div class="notice notice-success inline" style="margin:0 0 16px;">
                <p><?php esc_html_e( 'Styles saved.', 'wp-glossary-tooltip' ); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'wpgt_styles_save', 'wpgt_styles_nonce' ); ?>
            <input type="hidden" name="action" value="wpgt_save_styles" />

            <!-- Live preview -->
            <div style="display:flex;gap:28px;align-items:flex-start;margin-bottom:28px;flex-wrap:wrap;">
                <div style="flex:1;min-width:260px;max-width:420px;">
                    <h3 style="margin-top:0;"><?php esc_html_e( 'Live Preview', 'wp-glossary-tooltip' ); ?></h3>
                    <p style="font-size:0.85rem;color:#666;margin-top:-8px;"><?php esc_html_e( 'Updates as you edit fields below.', 'wp-glossary-tooltip' ); ?></p>

                    <p style="margin:0 0 6px;font-size:0.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#888;">Tooltip bubble</p>
                    <div id="wpgt-pv-tip" style="display:inline-block;padding:12px 14px;border-radius:6px;max-width:300px;margin-bottom:16px;box-shadow:0 4px 16px rgba(0,0,0,.2);background:#1e293b;color:#f1f5f9;">
                        <strong id="wpgt-pv-title" style="display:block;margin-bottom:5px;font-size:0.9375rem;color:#fff;">Sample Term</strong>
                        <span id="wpgt-pv-text" style="display:block;font-size:0.875rem;margin-bottom:6px;">Short tooltip definition text.</span>
                        <a id="wpgt-pv-more" href="#" style="font-size:0.8125rem;font-weight:500;color:#93c5fd;" onclick="return false;">Read more →</a>
                    </div>

                    <p style="margin:8px 0 4px;font-size:0.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#888;">Trigger word</p>
                    <span id="wpgt-pv-trigger" style="border-bottom:1.5px dashed #2563eb;cursor:help;font-size:1rem;">glossary term</span>

                    <p style="margin:16px 0 4px;font-size:0.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#888;">[wpgt_term] box</p>
                    <div id="wpgt-pv-box" style="border-left:4px solid #2563eb;padding:12px 16px;border-radius:0 6px 6px 0;background:#f8fafc;">
                        <h4 style="margin:0 0 5px;font-size:1rem;"><a id="wpgt-pv-box-title" href="#" style="color:#2563eb;text-decoration:none;" onclick="return false;">Term Title</a></h4>
                        <p id="wpgt-pv-box-def" style="margin:0;font-size:0.9rem;color:#374151;">Definition text shown in the term box shortcode.</p>
                    </div>
                </div>
            </div>

            <?php
            // ── field renderer ──────────────────────────────────────────
            // NOTE: color pickers use class "wpgt-style-picker" (not "wpgt-color-picker")
            // so admin.js does NOT double-initialize them.
            $row = function( string $label, string $name, string $value, string $type = 'color', string $extra = '', string $unit = '' ) {
                $id = 'wpgt_s_' . $name;
                echo '<tr>';
                echo '<th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th>';
                echo '<td>';
                if ( $type === 'color' ) {
                    echo '<input type="text" id="' . esc_attr($id) . '" '
                        . 'name="wpgt_styles[' . esc_attr($name) . ']" '
                        . 'value="' . esc_attr($value) . '" '
                        . 'class="wpgt-style-picker" '
                        . 'data-key="' . esc_attr($name) . '" />';
                } elseif ( $type === 'number' ) {
                    echo '<input type="number" id="' . esc_attr($id) . '" '
                        . 'name="wpgt_styles[' . esc_attr($name) . ']" '
                        . 'value="' . esc_attr($value) . '" '
                        . 'min="0" max="999" style="width:76px;" '
                        . 'data-key="' . esc_attr($name) . '" /> ' . esc_html($unit);
                } elseif ( $type === 'select' ) {
                    $opts = json_decode( $extra, true );
                    echo '<select id="' . esc_attr($id) . '" '
                        . 'name="wpgt_styles[' . esc_attr($name) . ']" '
                        . 'data-key="' . esc_attr($name) . '">';
                    foreach ( $opts as $v => $l ) {
                        echo '<option value="' . esc_attr($v) . '"' . selected($value, $v, false) . '>' . esc_html($l) . '</option>';
                    }
                    echo '</select>';
                    $extra = '';
                }
                if ( $extra && $type !== 'select' ) echo '<p class="description">' . esc_html($extra) . '</p>';
                echo '</td></tr>';
            };
            ?>

            <h3 class="wpgt-style-section-heading">🔤 <?php esc_html_e( 'Trigger Words', 'wp-glossary-tooltip' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Underlined words in post content that open the tooltip.', 'wp-glossary-tooltip' ); ?></p>
            <table class="form-table wpgt-style-table">
                <?php
                $row( 'Underline Color',     'trigger_underline_color', $s['trigger_underline_color'], 'color' );
                $row( 'Underline Style',     'trigger_underline_style', $s['trigger_underline_style'], 'select', json_encode(['dashed'=>'Dashed','solid'=>'Solid','dotted'=>'Dotted','none'=>'None']) );
                $row( 'Hover / Focus Color', 'trigger_hover_color',     $s['trigger_hover_color'],     'color' );
                $row( 'Font Weight',         'trigger_font_weight',     $s['trigger_font_weight'],     'select', json_encode(['400'=>'Normal (400)','500'=>'Medium (500)','600'=>'Semi-bold (600)','700'=>'Bold (700)']) );
                ?>
            </table>

            <h3 class="wpgt-style-section-heading">💬 <?php esc_html_e( 'Tooltip Bubble', 'wp-glossary-tooltip' ); ?></h3>
            <p class="description"><?php esc_html_e( 'The popup card. Background color is controlled by the Tooltip tab (Theme / Brand Color).', 'wp-glossary-tooltip' ); ?></p>
            <table class="form-table wpgt-style-table">
                <?php
                $row( 'Text Color',         'tooltip_text_color',    $s['tooltip_text_color'],    'color',  'Leave blank to use the theme default.' );
                $row( 'Title Color',        'tooltip_title_color',   $s['tooltip_title_color'],   'color',  'Leave blank to use the theme default.' );
                $row( '"Read More" Color',  'tooltip_link_color',    $s['tooltip_link_color'],    'color',  'Leave blank to use the theme default.' );
                $row( 'Font Size',          'tooltip_font_size',     $s['tooltip_font_size'],     'number', '', 'px' );
                $row( 'Border Radius',      'tooltip_border_radius', $s['tooltip_border_radius'], 'number', '', 'px' );
                $row( 'Max Width',          'tooltip_max_width',     $s['tooltip_max_width'],     'number', 'Set 0 for auto (up to 500 px).', 'px' );
                ?>
            </table>

            <h3 class="wpgt-style-section-heading">📋 <?php esc_html_e( '[wpgt_glossary] — Glossary Index', 'wp-glossary-tooltip' ); ?></h3>
            <table class="form-table wpgt-style-table">
                <tr><td colspan="2" style="padding:4px 0 2px;"><strong style="font-size:0.8rem;text-transform:uppercase;letter-spacing:.05em;color:#555;">A–Z Navigation Bar</strong></td></tr>
                <?php
                $row( 'Bar Background',       'az_bar_bg',           $s['az_bar_bg'],           'color' );
                $row( 'Letter Color',         'az_link_color',       $s['az_link_color'],       'color' );
                $row( 'Letter Hover BG',      'az_link_hover_bg',    $s['az_link_hover_bg'],    'color' );
                $row( 'Letter Hover Color',   'az_link_hover_color', $s['az_link_hover_color'], 'color' );
                $row( 'Letter Border Radius', 'az_link_radius',      $s['az_link_radius'],      'number', '', 'px' );
                ?>
                <tr><td colspan="2" style="padding:8px 0 2px;"><strong style="font-size:0.8rem;text-transform:uppercase;letter-spacing:.05em;color:#555;">Letter Headings (A, B, C…)</strong></td></tr>
                <?php
                $row( 'Heading Color',        'letter_heading_color',  $s['letter_heading_color'],  'color' );
                $row( 'Heading Border Color', 'letter_heading_border', $s['letter_heading_border'], 'color' );
                $row( 'Heading Font Size',    'letter_heading_size',   $s['letter_heading_size'],   'number', '', 'px' );
                ?>
                <tr><td colspan="2" style="padding:8px 0 2px;"><strong style="font-size:0.8rem;text-transform:uppercase;letter-spacing:.05em;color:#555;">Term Cards</strong></td></tr>
                <?php
                $row( 'Card Background',      'term_card_bg',           $s['term_card_bg'],           'color' );
                $row( 'Card Border Color',    'term_card_border',       $s['term_card_border'],       'color' );
                $row( 'Card Border Radius',   'term_card_radius',       $s['term_card_radius'],       'number', '', 'px' );
                $row( 'Card Hover Border',    'term_card_hover_border', $s['term_card_hover_border'], 'color' );
                $row( 'Term Name Color',      'term_link_color',        $s['term_link_color'],        'color' );
                $row( 'Term Name Size',       'term_link_size',         $s['term_link_size'],         'number', '', 'px' );
                $row( 'Excerpt Text Color',   'term_excerpt_color',     $s['term_excerpt_color'],     'color' );
                ?>
            </table>

            <h3 class="wpgt-style-section-heading">📦 <?php esc_html_e( '[wpgt_term] — Single Term Box', 'wp-glossary-tooltip' ); ?></h3>
            <table class="form-table wpgt-style-table">
                <?php
                $row( 'Left Border Color',     'termbox_border_color', $s['termbox_border_color'], 'color' );
                $row( 'Left Border Width',     'termbox_border_width', $s['termbox_border_width'], 'number', '', 'px' );
                $row( 'Background',            'termbox_bg',           $s['termbox_bg'],           'color' );
                $row( 'Title Color',           'termbox_title_color',  $s['termbox_title_color'],  'color' );
                $row( 'Title Font Size',       'termbox_title_size',   $s['termbox_title_size'],   'number', '', 'px' );
                $row( 'Definition Text Color', 'termbox_text_color',   $s['termbox_text_color'],   'color' );
                $row( 'Border Radius',         'termbox_radius',       $s['termbox_radius'],       'number', '', 'px' );
                ?>
            </table>

            <h3 class="wpgt-style-section-heading">🔍 <?php esc_html_e( '[wpgt_search] — Search Widget', 'wp-glossary-tooltip' ); ?></h3>
            <table class="form-table wpgt-style-table">
                <?php
                $row( 'Input Border Color',   'search_input_border',    $s['search_input_border'],    'color' );
                $row( 'Input Focus Color',    'search_input_focus',     $s['search_input_focus'],     'color' );
                $row( 'Input Border Radius',  'search_input_radius',    $s['search_input_radius'],    'number', '', 'px' );
                $row( 'Input Font Size',      'search_input_size',      $s['search_input_size'],      'number', '', 'px' );
                $row( 'Results Background',   'search_results_bg',      $s['search_results_bg'],      'color' );
                $row( 'Result Hover BG',      'search_result_hover_bg', $s['search_result_hover_bg'], 'color' );
                $row( 'Result Title Color',   'search_result_title',    $s['search_result_title'],    'color' );
                $row( 'Result Excerpt Color', 'search_result_excerpt',  $s['search_result_excerpt'],  'color' );
                ?>
            </table>

            <div style="margin:12px 0 28px;display:flex;gap:10px;align-items:center;">
                <?php submit_button( __( 'Save Styles', 'wp-glossary-tooltip' ), 'primary', 'submit', false ); ?>
                <a href="<?php echo esc_url( wp_nonce_url(
                    admin_url( 'admin-post.php?action=wpgt_save_styles&wpgt_reset_styles=1' ),
                    'wpgt_styles_save', 'wpgt_styles_nonce'
                ) ); ?>"
                   class="button button-secondary"
                   onclick="return confirm('<?php esc_attr_e( 'Reset all styles to plugin defaults?', 'wp-glossary-tooltip' ); ?>')">
                    <?php esc_html_e( 'Reset to Defaults', 'wp-glossary-tooltip' ); ?>
                </a>
            </div>
        </form>

        <script>
        jQuery(function($){
            // ── Initialize color pickers (class wpgt-style-picker, NOT wpgt-color-picker)
            // so admin.js never double-initializes them.
            $('#wpgt-tab-styles .wpgt-style-picker').wpColorPicker({
                change: function(e, ui){
                    wpgtStylePreview($(this).data('key'), ui.color.toString());
                },
                clear: function(){
                    wpgtStylePreview($(this).data('key'), '');
                }
            });

            // ── Apply saved values to preview on page load ──────────────
            <?php foreach ( $s as $key => $val ) :
                $val_js = esc_js( (string) $val );
            ?>
            wpgtStylePreview('<?php echo esc_js($key); ?>', '<?php echo $val_js; ?>');
            <?php endforeach; ?>

            // ── Number and select inputs ────────────────────────────────
            $('#wpgt-tab-styles').on('input change', 'input[type="number"], select', function(){
                wpgtStylePreview($(this).data('key'), $(this).val());
            });

            function wpgtStylePreview(key, val) {
                var tip   = $('#wpgt-pv-tip');
                var trig  = $('#wpgt-pv-trigger');
                var box   = $('#wpgt-pv-box');
                switch(key) {
                    case 'trigger_underline_color': trig.css('border-bottom-color', val||''); break;
                    case 'trigger_underline_style': trig.css('border-bottom-style', val||'dashed'); break;
                    case 'trigger_hover_color':     trig.css('color', val||''); break;
                    case 'trigger_font_weight':     trig.css('font-weight', val||'400'); break;

                    case 'tooltip_text_color':      tip.css('color', val||''); $('#wpgt-pv-text').css('color',val||''); break;
                    case 'tooltip_title_color':     $('#wpgt-pv-title').css('color', val||'#fff'); break;
                    case 'tooltip_link_color':      $('#wpgt-pv-more').css('color', val||'#93c5fd'); break;
                    case 'tooltip_font_size':       tip.css('font-size', val > 0 ? val+'px' : ''); break;
                    case 'tooltip_border_radius':   tip.css('border-radius', val > 0 ? val+'px' : '6px'); break;
                    case 'tooltip_max_width':       tip.css('max-width', val > 0 ? val+'px' : ''); break;

                    case 'termbox_border_color':    box.css('border-left-color', val||'#2563eb'); break;
                    case 'termbox_border_width':    box.css('border-left-width', val > 0 ? val+'px' : '4px'); break;
                    case 'termbox_bg':              box.css('background', val||'#f8fafc'); break;
                    case 'termbox_title_color':     $('#wpgt-pv-box-title').css('color', val||'#2563eb'); break;
                    case 'termbox_title_size':      $('#wpgt-pv-box-title').css('font-size', val > 0 ? val+'px' : ''); break;
                    case 'termbox_text_color':      $('#wpgt-pv-box-def').css('color', val||'#374151'); break;
                    case 'termbox_radius':          box.css('border-radius', val > 0 ? '0 '+val+'px '+val+'px 0' : '0 6px 6px 0'); break;
                }
            }
        });
        </script>
        <style>
        .wpgt-style-section-heading{margin:28px 0 4px;padding:10px 14px;background:#f6f7f7;border-left:4px solid #2563eb;font-size:.95rem;border-radius:0 4px 4px 0;}
        .wpgt-style-table th{width:220px;padding:8px 10px 8px 0;}
        .wpgt-style-table td{padding:6px 10px;}
        </style>
        <?php
    }

    /**
     * Style field defaults — mirrors public.css values so reset works correctly.
     */
    public static function get_style_defaults(): array {
        return [
            // Trigger words
            'trigger_underline_color'  => '#2563eb',
            'trigger_underline_style'  => 'dashed',
            'trigger_hover_color'      => '#2563eb',
            'trigger_font_weight'      => '400',
            // Tooltip bubble
            'tooltip_text_color'       => '',
            'tooltip_title_color'      => '',
            'tooltip_link_color'       => '',
            'tooltip_font_size'        => '14',
            'tooltip_border_radius'    => '6',
            'tooltip_max_width'        => '0',
            // Glossary index – A-Z bar
            'az_bar_bg'                => '#f8fafc',
            'az_link_color'            => '#2563eb',
            'az_link_hover_bg'         => '#2563eb',
            'az_link_hover_color'      => '#ffffff',
            'az_link_radius'           => '4',
            // Glossary index – letter headings
            'letter_heading_color'     => '#2563eb',
            'letter_heading_border'    => '#2563eb',
            'letter_heading_size'      => '24',
            // Glossary index – term cards
            'term_card_bg'             => '#f8fafc',
            'term_card_border'         => '#e2e8f0',
            'term_card_radius'         => '6',
            'term_card_hover_border'   => '#2563eb',
            'term_link_color'          => '#2563eb',
            'term_link_size'           => '15',
            'term_excerpt_color'       => '#64748b',
            // Term box [wpgt_term]
            'termbox_border_color'     => '#2563eb',
            'termbox_border_width'     => '4',
            'termbox_bg'               => '#f8fafc',
            'termbox_title_color'      => '#2563eb',
            'termbox_title_size'       => '17',
            'termbox_text_color'       => '#374151',
            'termbox_radius'           => '6',
            // Search widget [wpgt_search]
            'search_input_border'      => '#d1d5db',
            'search_input_focus'       => '#2563eb',
            'search_input_radius'      => '6',
            'search_input_size'        => '15',
            'search_results_bg'        => '#ffffff',
            'search_result_hover_bg'   => '#f0f7ff',
            'search_result_title'      => '#1e293b',
            'search_result_excerpt'    => '#64748b',
        ];
    }

    public static function save_styles() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'wpgt_styles_save', 'wpgt_styles_nonce' );

        // Reset to defaults?
        if ( isset( $_GET['wpgt_reset_styles'] ) ) {
            delete_option( 'wpgt_styles' );
            wp_redirect( add_query_arg(
                [ 'page' => 'wpgt-settings', 'wpgt_styles_saved' => '1', '#' => 'wpgt-tab-styles' ],
                admin_url( 'edit.php?post_type=' . WPGT_Post_Type::POST_TYPE )
            ) );
            exit;
        }

        $raw      = (array) ( $_POST['wpgt_styles'] ?? [] );
        $defaults = self::get_style_defaults();
        $clean    = [];

        foreach ( $defaults as $key => $default ) {
            if ( ! isset( $raw[ $key ] ) ) continue;
            $val = $raw[ $key ];
            // Colour fields
            if ( in_array( $key, [
                'trigger_underline_color','trigger_hover_color',
                'tooltip_text_color','tooltip_title_color','tooltip_link_color',
                'az_bar_bg','az_link_color','az_link_hover_bg','az_link_hover_color',
                'letter_heading_color','letter_heading_border',
                'term_card_bg','term_card_border','term_card_hover_border',
                'term_link_color','term_excerpt_color',
                'termbox_border_color','termbox_bg','termbox_title_color','termbox_text_color',
                'search_input_border','search_input_focus','search_results_bg',
                'search_result_hover_bg','search_result_title','search_result_excerpt',
            ], true ) ) {
                $clean[ $key ] = sanitize_hex_color( $val ) ?: '';
            // Select / enum fields
            } elseif ( $key === 'trigger_underline_style' ) {
                $clean[ $key ] = in_array( $val, ['dashed','solid','dotted','none'], true ) ? $val : 'dashed';
            } elseif ( $key === 'trigger_font_weight' ) {
                $clean[ $key ] = in_array( $val, ['400','500','600','700'], true ) ? $val : '400';
            // Numeric (px) fields
            } else {
                $clean[ $key ] = max( 0, (int) $val );
            }
        }

        update_option( 'wpgt_styles', $clean );

        wp_redirect( add_query_arg(
            [ 'page' => 'wpgt-settings', 'wpgt_styles_saved' => '1', 'wpgt_tab' => 'wpgt-tab-styles' ],
            admin_url( 'edit.php?post_type=' . WPGT_Post_Type::POST_TYPE )
        ) );
        exit;
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
        $data['index_columns']   = (int) ( $data['index_columns']           ?? 3 );
        $data['brand_color']     = sanitize_hex_color( $data['brand_color'] ?? '#2563eb' );
        $data['see_more_color']  = sanitize_hex_color( $data['see_more_color'] ?? '' ) ?: '';
        $data['parse_post_types'] = array_map( 'sanitize_key', (array) ( $data['parse_post_types'] ?? [] ) );

        WPGT_Settings::update( $data );

        wp_redirect( add_query_arg( 'wpgt_saved', '1', wp_get_referer() ) );
        exit;
    }

    // ------------------------------------------------------------------
    // Export: stream a CSV of all glossary terms
    // ------------------------------------------------------------------
    public static function export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'wpgt_export' );

        $posts = get_posts( [
            'post_type'      => WPGT_Post_Type::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        // Build xlsx in-memory as a zip of XML parts — no library needed.
        $rows   = [];
        $rows[] = [ 'word', 'explanation' ];
        foreach ( $posts as $post ) {
            $tooltip = get_post_meta( $post->ID, '_wpgt_tooltip_text', true );
            if ( ! $tooltip ) $tooltip = $post->post_excerpt;
            $rows[] = [ $post->post_title, $tooltip ];
        }

        $xlsx = self::build_xlsx( $rows );

        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="glossary-' . date('Y-m-d') . '.xlsx"' );
        header( 'Content-Length: ' . strlen( $xlsx ) );
        header( 'Pragma: no-cache' );
        echo $xlsx;
        exit;
    }

    /**
     * Build a minimal valid .xlsx binary string from a 2D array of rows.
     * Uses ZipArchive + SpreadsheetML XML — no external library required.
     */
    private static function build_xlsx( array $rows ): string {
        $tmp = tempnam( sys_get_temp_dir(), 'wpgt_xlsx_' );

        $zip = new ZipArchive();
        $zip->open( $tmp, ZipArchive::OVERWRITE );

        // [Content_Types].xml
        $zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>' );

        // _rels/.rels
        $zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>
</Relationships>' );

        // xl/_rels/workbook.xml.rels
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
    Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"
    Target="sharedStrings.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
    Target="styles.xml"/>
</Relationships>' );

        // xl/workbook.xml
        $zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Glossary" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>' );

        // xl/styles.xml — minimal, one bold style for header row (styleIndex 1)
        $zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Arial"/></font>
    <font><b/><sz val="11"/><name val="Arial"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>
  </cellXfs>
</styleSheet>' );

        // Build shared strings table (all cell values stored here, cells reference by index)
        $strings     = [];
        $string_map  = [];
        $sheet_rows  = [];

        foreach ( $rows as $r_idx => $row ) {
            $cells = [];
            $col   = 0;
            foreach ( $row as $value ) {
                $value = (string) $value;
                if ( ! isset( $string_map[ $value ] ) ) {
                    $string_map[ $value ] = count( $strings );
                    $strings[]            = $value;
                }
                $si      = $string_map[ $value ];
                $col_ltr = self::xlsx_col( $col );
                $row_num = $r_idx + 1;
                $style   = ( $r_idx === 0 ) ? ' s="1"' : '';
                $cells[] = '<c r="' . $col_ltr . $row_num . '" t="s"' . $style . '><v>' . $si . '</v></c>';
                $col++;
            }
            $sheet_rows[] = '<row r="' . ( $r_idx + 1 ) . '">' . implode( '', $cells ) . '</row>';
        }

        // xl/sharedStrings.xml
        $ss_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "
";
        $ss_xml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $strings ) . '" uniqueCount="' . count( $strings ) . '">';
        foreach ( $strings as $s ) {
            $ss_xml .= '<si><t xml:space="preserve">' . htmlspecialchars( $s, ENT_XML1, 'UTF-8' ) . '</t></si>';
        }
        $ss_xml .= '</sst>';
        $zip->addFromString( 'xl/sharedStrings.xml', $ss_xml );

        // xl/worksheets/sheet1.xml — set col widths: A=30, B=80
        $sheet_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "
";
        $sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $sheet_xml .= '<cols><col min="1" max="1" width="30" customWidth="1"/><col min="2" max="2" width="80" customWidth="1"/></cols>';
        $sheet_xml .= '<sheetData>' . implode( '', $sheet_rows ) . '</sheetData>';
        $sheet_xml .= '</worksheet>';
        $zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );

        $zip->close();

        $data = file_get_contents( $tmp );
        unlink( $tmp );
        return $data;
    }

    /** Convert 0-based column index to Excel letter(s): 0→A, 25→Z, 26→AA */
    private static function xlsx_col( int $n ): string {
        $s = '';
        for ( $n++; $n > 0; $n = intdiv( $n, 26 ) ) {
            $s = chr( 65 + ( ( $n - 1 ) % 26 ) ) . $s;
        }
        return $s;
    }

        public static function import_csv() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'wpgt_import', 'wpgt_import_nonce' );

        if ( empty( $_FILES['wpgt_csv']['tmp_name'] ) ) {
            wp_redirect( add_query_arg( 'wpgt_error', 'no_file', wp_get_referer() ) );
            exit;
        }

        $rows = self::read_xlsx( $_FILES['wpgt_csv']['tmp_name'] );
        if ( empty( $rows ) ) wp_die( 'Could not read file or file is empty.' );

        // First row = header. Normalise cell text to find columns.
        $header   = array_map( fn( $h ) => strtolower( trim( (string) $h ) ), array_shift( $rows ) );
        $col      = array_flip( $header );
        $word_col = $col['word'] ?? $col['title'] ?? $col['term'] ?? $col['name'] ?? null;
        $expl_col = $col['explanation'] ?? $col['tooltip_text'] ?? $col['definition'] ?? $col['description'] ?? $col['meaning'] ?? null;

        // No recognised header — assume no header row, column 0 = word, column 1 = explanation
        if ( $word_col === null ) {
            array_unshift( $rows, array_values( array_combine( $header, $header ) ) );
            $word_col = 0;
            $expl_col = 1;
        }

        $created = 0;
        $updated = 0;

        foreach ( $rows as $row ) {
            $word        = isset( $row[ $word_col ] ) ? trim( $row[ $word_col ] ) : '';
            $explanation = ( $expl_col !== null && isset( $row[ $expl_col ] ) ) ? trim( $row[ $expl_col ] ) : '';

            if ( $word === '' ) continue;

            $existing = get_page_by_title( $word, OBJECT, WPGT_Post_Type::POST_TYPE );

            $post_data = [
                'post_type'    => WPGT_Post_Type::POST_TYPE,
                'post_title'   => sanitize_text_field( $word ),
                'post_content' => sanitize_textarea_field( $explanation ),
                'post_excerpt' => sanitize_text_field( $explanation ),
                'post_status'  => 'publish',
            ];

            if ( $existing ) {
                $post_data['ID'] = $existing->ID;
                wp_update_post( $post_data );
                $post_id = $existing->ID;
                $updated++;
            } else {
                $post_id = wp_insert_post( $post_data );
                $created++;
            }

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, '_wpgt_tooltip_text', sanitize_text_field( $explanation ) );
            }
        }


        wp_redirect( add_query_arg( [
            'page'          => 'wpgt-settings',
            'wpgt_imported' => '1',
            'created'       => $created,
            'updated'       => $updated,
        ], admin_url( 'edit.php?post_type=' . WPGT_Post_Type::POST_TYPE ) ) );
        exit;
    }

    /**
     * Read an .xlsx file and return its first sheet as a 2D array of strings.
     * Parses SpreadsheetML XML directly — no library needed.
     */
    private static function read_xlsx( string $path ): array {
        if ( ! class_exists( 'ZipArchive' ) ) return [];

        $zip = new ZipArchive();
        if ( $zip->open( $path ) !== true ) return [];

        // Shared strings table
        $shared_strings = [];
        $ss_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
        if ( $ss_xml ) {
            $ss_dom = new DOMDocument();
            @$ss_dom->loadXML( $ss_xml );
            foreach ( $ss_dom->getElementsByTagName( 'si' ) as $si ) {
                $text = '';
                foreach ( $si->getElementsByTagName( 't' ) as $t ) {
                    $text .= $t->nodeValue;
                }
                $shared_strings[] = $text;
            }
        }

        // Find first sheet path via workbook relationships
        $sheet_path = 'xl/worksheets/sheet1.xml';
        $rels_xml   = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
        if ( $rels_xml ) {
            $r_dom = new DOMDocument();
            @$r_dom->loadXML( $rels_xml );
            foreach ( $r_dom->getElementsByTagName( 'Relationship' ) as $rel ) {
                if ( str_ends_with( $rel->getAttribute( 'Type' ), '/worksheet' ) ) {
                    $target     = ltrim( $rel->getAttribute( 'Target' ), '/' );
                    $sheet_path = strpos( $target, 'xl/' ) === 0 ? $target : 'xl/' . $target;
                    break;
                }
            }
        }

        $sheet_xml = $zip->getFromName( $sheet_path );
        $zip->close();
        if ( ! $sheet_xml ) return [];

        $sheet_dom = new DOMDocument();
        @$sheet_dom->loadXML( $sheet_xml );

        $rows = [];
        foreach ( $sheet_dom->getElementsByTagName( 'row' ) as $row_el ) {
            $row     = [];
            $max_col = -1;

            foreach ( $row_el->getElementsByTagName( 'c' ) as $cell ) {
                // Parse column letter(s) from cell ref e.g. "B3" → col index 1
                $ref = $cell->getAttribute( 'r' );
                $col = 0;
                for ( $i = 0; $i < strlen( $ref ); $i++ ) {
                    $ch = $ref[ $i ];
                    if ( $ch >= 'A' && $ch <= 'Z' ) {
                        $col = $col * 26 + ( ord( $ch ) - ord( 'A' ) + 1 );
                    } else {
                        break;
                    }
                }
                $col--;

                $type  = $cell->getAttribute( 't' );
                $v_el  = $cell->getElementsByTagName( 'v' )->item( 0 );
                $value = $v_el ? $v_el->nodeValue : '';

                if ( $type === 's' ) {
                    $value = $shared_strings[ (int) $value ] ?? '';
                } elseif ( $type === 'inlineStr' ) {
                    $is_el = $cell->getElementsByTagName( 'is' )->item( 0 );
                    $t_el  = $is_el ? $is_el->getElementsByTagName( 't' )->item( 0 ) : null;
                    $value = $t_el ? $t_el->nodeValue : '';
                }

                $row[ $col ] = (string) $value;
                if ( $col > $max_col ) $max_col = $col;
            }

            // Fill sparse column gaps with empty strings
            $filled = [];
            for ( $c = 0; $c <= $max_col; $c++ ) {
                $filled[] = $row[ $c ] ?? '';
            }
            $rows[] = $filled;
        }

        return $rows;
    }
    // ------------------------------------------------------------------
    // Sync letter taxonomy
    // ------------------------------------------------------------------
    public static function sync_letter_taxonomy() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'wpgt_sync_letters', 'wpgt_sync_letters_nonce' );

        // Step 1: fix any bad slugs (letter-ს, %e1%83%90, etc.) → letter-a, letter-b
        $fixed = WPGT_Post_Type::fix_letter_slugs();

        // Step 2: assign every published term to its letter
        $posts = get_posts( [
            'post_type'      => WPGT_Post_Type::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $posts as $post_id ) {
            WPGT_Post_Type::assign_letter_taxonomy( $post_id );
        }

        // Step 3: flush so new /glossary/letter/letter-a/ URLs resolve immediately
        flush_rewrite_rules( false );

        wp_redirect( add_query_arg( [
            'wpgt_synced' => count( $posts ),
            'wpgt_fixed'  => $fixed,
        ], wp_get_referer() ) );
        exit;
    }
    // ------------------------------------------------------------------
    // Re-Order page
    // ------------------------------------------------------------------
    public static function render_reorder_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Get all letters that have terms
        $letter_terms = get_terms( [
            'taxonomy'   => WPGT_Post_Type::LETTER_TAX,
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );

        // Default: show first letter's terms, or all if no letters
        $selected_slug = isset( $_GET['letter'] ) ? sanitize_text_field( $_GET['letter'] ) : '';
        $selected_term = null;

        if ( $selected_slug && ! is_wp_error( $letter_terms ) ) {
            foreach ( $letter_terms as $lt ) {
                if ( $lt->slug === $selected_slug ) { $selected_term = $lt; break; }
            }
        }

        if ( ! $selected_term && ! is_wp_error( $letter_terms ) && ! empty( $letter_terms ) ) {
            $selected_term = $letter_terms[0];
            $selected_slug = $selected_term->slug;
        }

        // Fetch posts for selected letter
        $query_args = [
            'post_type'      => WPGT_Post_Type::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ];
        if ( $selected_term ) {
            $query_args['tax_query'] = [ [
                'taxonomy' => WPGT_Post_Type::LETTER_TAX,
                'field'    => 'term_id',
                'terms'    => $selected_term->term_id,
            ] ];
        }
        $posts = get_posts( $query_args );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Re-Order Glossary Terms', 'wp-glossary-tooltip' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Drag and drop terms to set the display order. Changes save automatically.', 'wp-glossary-tooltip' ); ?></p>

            <!-- Letter filter tabs -->
            <?php if ( ! is_wp_error( $letter_terms ) && ! empty( $letter_terms ) ) : ?>
            <div style="margin: 16px 0; display:flex; flex-wrap:wrap; gap:6px;">
                <?php foreach ( $letter_terms as $lt ) :
                    $url = add_query_arg( [
                        'post_type' => WPGT_Post_Type::POST_TYPE,
                        'page'      => 'wpgt-settings',
                        'letter'    => $lt->slug,
                    ], admin_url( 'edit.php' ) );
                    $active = $lt->slug === $selected_slug;
                ?>
                <a href="<?php echo esc_url( $url ); ?>"
                   style="display:inline-block; padding:6px 14px; border-radius:4px; text-decoration:none;
                          font-size:1.1rem; font-weight:600;
                          background:<?php echo $active ? '#2563eb' : '#f0f0f0'; ?>;
                          color:<?php echo $active ? '#fff' : '#333'; ?>;">
                    <?php echo esc_html( $lt->name ); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Sortable list -->
            <div id="wpgt-reorder-wrap" style="max-width:700px;">
                <?php if ( empty( $posts ) ) : ?>
                <p><?php esc_html_e( 'No terms found for this letter.', 'wp-glossary-tooltip' ); ?></p>
                <?php else : ?>
                <ul id="wpgt-sortable" style="list-style:none; margin:0; padding:0;">
                    <?php foreach ( $posts as $post ) : ?>
                    <li data-id="<?php echo (int) $post->ID; ?>"
                        style="display:flex; align-items:center; gap:12px;
                               background:#fff; border:1px solid #ddd; border-radius:6px;
                               padding:12px 16px; margin-bottom:8px; cursor:grab;
                               user-select:none;">
                        <span style="color:#aaa; font-size:18px;">&#9776;</span>
                        <span style="font-weight:600;"><?php echo esc_html( $post->post_title ); ?></span>
                        <?php
                        $excerpt = $post->post_excerpt ?: wp_trim_words( strip_tags( $post->post_content ), 10 );
                        if ( $excerpt ) :
                        ?>
                        <span style="color:#888; font-size:0.85rem;"><?php echo esc_html( $excerpt ); ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p id="wpgt-order-saved" style="display:none; color:#2563eb; margin-top:10px; font-weight:600;">
                    ✓ <?php esc_html_e( 'Order saved!', 'wp-glossary-tooltip' ); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(function($){
            $('#wpgt-sortable').sortable({
                handle: 'li',
                placeholder: 'wpgt-sort-placeholder',
                update: function() {
                    var ids = [];
                    $('#wpgt-sortable li').each(function(){
                        ids.push( $(this).data('id') );
                    });
                    $.post( ajaxurl, {
                        action:   'wpgt_save_order',
                        order:    ids,
                        _wpnonce: '<?php echo wp_create_nonce("wpgt_save_order"); ?>'
                    }, function(response){
                        if ( response.success ) {
                            $('#wpgt-order-saved').fadeIn().delay(2000).fadeOut();
                        }
                    });
                }
            });
        });
        </script>
        <style>
        .wpgt-sort-placeholder {
            background: #e8f0fe; border: 2px dashed #2563eb;
            border-radius: 6px; height: 50px; margin-bottom: 8px;
            list-style: none;
        }
        #wpgt-sortable li:active { cursor: grabbing; }
        </style>
        <?php
    }

    public static function ajax_save_order() {
        check_ajax_referer( 'wpgt_save_order' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $order = array_map( 'intval', (array) ( $_POST['order'] ?? [] ) );
        foreach ( $order as $position => $post_id ) {
            wp_update_post( [ 'ID' => $post_id, 'menu_order' => $position ] );
        }

        wp_send_json_success();
    }

    // ------------------------------------------------------------------
    // Skip tooltips meta box (on posts/pages)
    // ------------------------------------------------------------------
    public static function render_skip_meta_box( WP_Post $post ) {
        wp_nonce_field( 'wpgt_skip_tooltips', 'wpgt_skip_nonce' );
        $skip = get_post_meta( $post->ID, '_wpgt_skip_tooltips', true );
        ?>
        <label style="display:flex; align-items:flex-start; gap:8px; margin-top:4px;">
            <input type="checkbox" name="wpgt_skip_tooltips" value="1"
                   <?php checked( $skip, '1' ); ?> style="margin-top:2px;" />
            <span><?php esc_html_e( 'Disable glossary tooltips on this post', 'wp-glossary-tooltip' ); ?></span>
        </label>
        <?php
    }

    public static function save_skip_meta( int $post_id ) {
        if ( ! isset( $_POST['wpgt_skip_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['wpgt_skip_nonce'], 'wpgt_skip_tooltips' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( get_post_type( $post_id ) === WPGT_Post_Type::POST_TYPE ) return;

        if ( ! empty( $_POST['wpgt_skip_tooltips'] ) ) {
            update_post_meta( $post_id, '_wpgt_skip_tooltips', '1' );
        } else {
            delete_post_meta( $post_id, '_wpgt_skip_tooltips' );
        }
    }

    // ------------------------------------------------------------------
    // Regenerate all declined forms
    // ------------------------------------------------------------------
    public static function regen_declined_forms() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'wpgt_regen_forms', 'wpgt_regen_nonce' );

        $count = WPGT_Post_Type::regenerate_all_declined_forms();

        wp_redirect( add_query_arg( [
            'page'         => 'wpgt-settings',
            'wpgt_regenok' => $count,
        ], admin_url( 'edit.php?post_type=' . WPGT_Post_Type::POST_TYPE ) ) );
        exit;
    }

}