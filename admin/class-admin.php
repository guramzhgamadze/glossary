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
                    <?php submit_button( __( 'Save Settings', 'wp-glossary-tooltip' ) ); ?>
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


                    <?php submit_button( __( 'Save Settings', 'wp-glossary-tooltip' ) ); ?>
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
                    <?php submit_button( __( 'Save Settings', 'wp-glossary-tooltip' ) ); ?>
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
                    <?php submit_button( __( 'Save Settings', 'wp-glossary-tooltip' ) ); ?>
                </div>

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

            <div class="wpgt-styles-layout">

                <!-- ════ LEFT: form fields ════ -->
                <div class="wpgt-styles-fields">
                <?php

                // ─────────────────────────────────────────────────────────────
                // Field / card rendering helpers
                // Color pickers: class "wpgt-style-picker" (NOT "wpgt-color-picker")
                // so admin.js never double-initialises them.
                // ─────────────────────────────────────────────────────────────

                // Renders ONE field cell inside the grid
                $field = function( string $label, string $name, string $value, string $type = 'color', array $opts = [], string $unit = '', bool $full = false ) {
                    $id   = 'wpgt_s_' . $name;
                    $attr = 'id="' . esc_attr($id) . '" name="wpgt_styles[' . esc_attr($name) . ']" data-key="' . esc_attr($name) . '"';
                    $cls  = $full ? ' wpgt-field--full' : '';
                    echo '<div class="wpgt-field' . $cls . '">';
                    echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
                    switch ( $type ) {
                        case 'color':
                            echo '<div class="wpgt-field-color-wrap"><input type="text" ' . $attr . ' value="' . esc_attr($value) . '" class="wpgt-style-picker" /></div>';
                            break;
                        case 'number':
                            echo '<div class="wpgt-field-num-wrap"><input type="number" ' . $attr . ' value="' . esc_attr($value) . '" min="0" max="999" /><span>' . esc_html($unit) . '</span></div>';
                            break;
                        case 'float':
                            echo '<input type="number" step="0.01" min="0" max="10" ' . $attr . ' value="' . esc_attr($value) . '" />';
                            break;
                        case 'select':
                            echo '<select ' . $attr . '>';
                            foreach ( $opts as $v => $l ) {
                                echo '<option value="' . esc_attr($v) . '"' . selected($value, $v, false) . '>' . esc_html($l) . '</option>';
                            }
                            echo '</select>';
                            break;
                    }
                    echo '</div>';
                };

                // Card helpers
                $card_open  = function( string $icon, string $title, string $css_class ) {
                    echo '<div class="wpgt-card">';
                    echo '<div class="wpgt-card-head">'
                        . '<span class="wpgt-card-icon">' . $icon . '</span>'
                        . '<span>' . esc_html($title) . '</span>'
                        . '<code class="wpgt-css-badge">' . esc_html($css_class) . '</code>'
                        . '</div><div class="wpgt-card-body">';
                };
                $card_close = function() { echo '</div></div>'; };  // close body + card

                // Group label (sub-section inside a card)
                $group = function( string $label, string $css_class = '' ) {
                    echo '<div class="wpgt-group-label">' . esc_html($label);
                    if ( $css_class ) echo ' <code class="wpgt-css-badge wpgt-css-badge--sm">' . esc_html($css_class) . '</code>';
                    echo '</div>';
                };

                // Opens/closes the 2-col grid
                $grid_open  = function() { echo '<div class="wpgt-field-grid">'; };
                $grid_close = function() { echo '</div>'; };

                $weights    = ['400'=>'Normal','500'=>'Medium','600'=>'Semi-bold','700'=>'Bold'];
                $transforms = [''=>'None','uppercase'=>'UPPER','lowercase'=>'lower','capitalize'=>'Title'];
                $tip_shad   = ['none'=>'None','sm'=>'Subtle','default'=>'Default','lg'=>'Strong'];
                $card_shad  = ['none'=>'None','sm'=>'Subtle','md'=>'Medium','lg'=>'Strong'];
                $sr_shad    = ['none'=>'None','sm'=>'Subtle','default'=>'Default','lg'=>'Strong'];
                $aligns     = ['left'=>'Left','center'=>'Center','right'=>'Right'];
                $justifys   = ['flex-start'=>'Left','center'=>'Center','flex-end'=>'Right','space-between'=>'Spread'];
                $bstyles    = ['solid'=>'Solid','dashed'=>'Dashed','dotted'=>'Dotted','none'=>'None'];
                ?>

                <?php // ══════════════════════════════════════════════════════
                // CARD 1: Trigger Word
                $card_open('🔤', 'Trigger Word', '.wpgt-tooltip-trigger');

                $group('Colors');
                $grid_open();
                    $field('Underline Color', 'trigger_color',       $s['trigger_color'],       'color');
                    $field('Hover Color',     'trigger_hover_color', $s['trigger_hover_color'], 'color');
                $grid_close();

                $group('Underline');
                $grid_open();
                    $field('Style', 'trigger_underline_style', $s['trigger_underline_style'], 'select', ['dashed'=>'Dashed','solid'=>'Solid','dotted'=>'Dotted','none'=>'None']);
                    $field('Width', 'trigger_underline_width', $s['trigger_underline_width'], 'number', [], 'px');
                $grid_close();

                $group('Typography');
                $grid_open();
                    $field('Font Weight',    'trigger_font_weight',    $s['trigger_font_weight'],    'select', $weights);
                    $field('Font Size',      'trigger_font_size',      $s['trigger_font_size'],      'number', [], 'px');
                    $field('Text Transform', 'trigger_text_transform', $s['trigger_text_transform'], 'select', $transforms, '', true);
                $grid_close();

                $card_close();

                // ══════════════════════════════════════════════════════
                // CARD 2: Tooltip Bubble
                $card_open('💬', 'Tooltip Bubble', '.wpgt-tooltip-bubble');

                $group('Colors');
                $grid_open();
                    $field('Background',   'tooltip_bg',           $s['tooltip_bg'],           'color');
                    $field('Text',         'tooltip_text_color',   $s['tooltip_text_color'],   'color');
                    $field('Title',        'tooltip_title_color',  $s['tooltip_title_color'],  'color');
                    $field('"Read More"',  'tooltip_link_color',   $s['tooltip_link_color'],   'color');
                    $field('Border Color', 'tooltip_border_color', $s['tooltip_border_color'], 'color');
                $grid_close();

                $group('Size & Shape');
                $grid_open();
                    $field('Font Size',    'tooltip_font_size',     $s['tooltip_font_size'],     'number', [], 'px');
                    $field('Line Height',  'tooltip_line_height',   $s['tooltip_line_height'],   'float');
                    $field('Padding V',    'tooltip_padding_v',     $s['tooltip_padding_v'],     'number', [], 'px');
                    $field('Padding H',    'tooltip_padding_h',     $s['tooltip_padding_h'],     'number', [], 'px');
                    $field('Border Width', 'tooltip_border_width',  $s['tooltip_border_width'],  'number', [], 'px');
                    $field('Radius',       'tooltip_border_radius', $s['tooltip_border_radius'], 'number', [], 'px');
                    $field('Max Width',    'tooltip_max_width',     $s['tooltip_max_width'],     'number', [], 'px');
                    $field('Shadow',       'tooltip_shadow',        $s['tooltip_shadow'],        'select', $tip_shad);
                $grid_close();

                $card_close();

                // ══════════════════════════════════════════════════════
                // CARD 3: [wpgt_glossary] — 5 sub-sections
                $card_open('📋', '[wpgt_glossary] Index', '.wpgt-glossary-index');

                // ── A–Z Bar ────────────────────────────────────────────
                $group('A–Z Bar', '.wpgt-alphabet-bar');
                $grid_open();
                    $field('Background',     'az_bar_bg',           $s['az_bar_bg'],           'color');
                    $field('Border Color',   'az_bar_border_color', $s['az_bar_border_color'], 'color');
                    $field('Align',          'az_bar_justify',      $s['az_bar_justify'],      'select', $justifys);
                    $field('Radius',         'az_bar_radius',       $s['az_bar_radius'],       'number', [], 'px');
                    $field('Padding V',      'az_bar_padding_v',    $s['az_bar_padding_v'],    'number', [], 'px');
                    $field('Padding H',      'az_bar_padding_h',    $s['az_bar_padding_h'],    'number', [], 'px');
                $grid_close();

                // ── Letter Links ────────────────────────────────────────
                $group('Letter Links', '.wpgt-az-link');
                $grid_open();
                    $field('Color',       'az_link_color',       $s['az_link_color'],       'color');
                    $field('Hover BG',    'az_link_hover_bg',    $s['az_link_hover_bg'],    'color');
                    $field('Hover Color', 'az_link_hover_color', $s['az_link_hover_color'], 'color');
                    $field('Radius',      'az_link_radius',      $s['az_link_radius'],      'number', [], 'px');
                    $field('Font Size',   'az_link_size',        $s['az_link_size'],        'number', [], 'px');
                    $field('Font Weight', 'az_link_weight',      $s['az_link_weight'],      'select', $weights);
                $grid_close();

                // ── Active Letter ───────────────────────────────────────
                $group('Active Letter', '.wpgt-az-current');
                $grid_open();
                    $field('Background',  'az_current_bg',           $s['az_current_bg'],           'color');
                    $field('Color',       'az_current_color',        $s['az_current_color'],        'color');
                    $field('Hover BG',    'az_current_hover_bg',     $s['az_current_hover_bg'],     'color');
                    $field('Hover Color', 'az_current_hover_color',  $s['az_current_hover_color'],  'color');
                    $field('Border Color','az_current_border_color', $s['az_current_border_color'], 'color');
                    $field('Border Width','az_current_border_width', $s['az_current_border_width'], 'number', [], 'px');
                    $field('Border Style','az_current_border_style', $s['az_current_border_style'], 'select', $bstyles);
                    $field('Border Radius','az_current_border_radius',$s['az_current_border_radius'],'number', [], 'px');
                $grid_close();

                // ── Letter Headings ─────────────────────────────────────
                $group('Letter Headings', '.wpgt-letter-heading');
                $grid_open();
                    $field('Color',        'letter_heading_color',        $s['letter_heading_color'],        'color');
                    $field('Border Color', 'letter_heading_border',       $s['letter_heading_border'],       'color');
                    $field('Border Top',   'letter_heading_border_top',   $s['letter_heading_border_top'],   'number', [], 'px');
                    $field('Border Bottom','letter_heading_border_bottom',$s['letter_heading_border_bottom'],'number', [], 'px');
                    $field('Border Style', 'letter_heading_border_style', $s['letter_heading_border_style'], 'select', $bstyles);
                    $field('Font Size',    'letter_heading_size',         $s['letter_heading_size'],         'number', [], 'px');
                    $field('Font Weight',  'letter_heading_weight',       $s['letter_heading_weight'],       'select', $weights);
                    $field('Transform',    'letter_heading_transform',    $s['letter_heading_transform'],    'select', $transforms);
                    $field('Text Align',   'letter_heading_align',        $s['letter_heading_align'],        'select', $aligns);
                    $field('Display',      'letter_heading_display',      $s['letter_heading_display'],      'select', ['inline-block'=>'Inline','block'=>'Block']);
                    $field('Full Width',   'letter_heading_full_width',   $s['letter_heading_full_width'],   'select', ['0'=>'Auto','1'=>'100%']);
                    $field('Margin Bottom','letter_heading_mb',           $s['letter_heading_mb'],           'number', [], 'px');
                $grid_close();

                // ── Term Cards ──────────────────────────────────────────
                $group('Term Cards', '.wpgt-term-item');
                $grid_open();
                    $field('Background',   'term_card_bg',          $s['term_card_bg'],          'color');
                    $field('Border',       'term_card_border',      $s['term_card_border'],      'color');
                    $field('Hover Border', 'term_card_hover_border',$s['term_card_hover_border'],'color');
                    $field('Radius',       'term_card_radius',      $s['term_card_radius'],      'number', [], 'px');
                    $field('Shadow',       'term_card_shadow',      $s['term_card_shadow'],      'select', $card_shad);
                    $field('Padding V',    'term_card_padding_v',   $s['term_card_padding_v'],   'number', [], 'px');
                    $field('Padding H',    'term_card_padding_h',   $s['term_card_padding_h'],   'number', [], 'px');
                $grid_close();

                $group('Term Name', '.wpgt-term-link');
                $grid_open();
                    $field('Color',      'term_link_color',    $s['term_link_color'],   'color');
                    $field('Font Size',  'term_link_size',     $s['term_link_size'],    'number', [], 'px');
                    $field('Font Weight','term_link_weight',   $s['term_link_weight'],  'select', $weights);
                $grid_close();

                $group('Excerpt', '.wpgt-term-excerpt');
                $grid_open();
                    $field('Color',     'term_excerpt_color', $s['term_excerpt_color'], 'color');
                    $field('Font Size', 'term_excerpt_size',  $s['term_excerpt_size'],  'number', [], 'px');
                $grid_close();

                $card_close();

                // ══════════════════════════════════════════════════════
                // CARD 4: [wpgt_term] Term Box
                $card_open('📦', '[wpgt_term] — Term Box', '.wpgt-term-box');

                $group('Colors');
                $grid_open();
                    $field('Background',      'termbox_bg',           $s['termbox_bg'],           'color');
                    $field('Left Border',     'termbox_border_color', $s['termbox_border_color'], 'color');
                $grid_close();

                $group('Title', '.wpgt-term-box__title');
                $grid_open();
                    $field('Color',      'termbox_title_color',  $s['termbox_title_color'],  'color');
                    $field('Font Size',  'termbox_title_size',   $s['termbox_title_size'],   'number', [], 'px');
                    $field('Font Weight','termbox_title_weight', $s['termbox_title_weight'], 'select', $weights);
                $grid_close();

                $group('Definition', '.wpgt-term-box__definition');
                $grid_open();
                    $field('Color',     'termbox_text_color', $s['termbox_text_color'], 'color');
                    $field('Font Size', 'termbox_text_size',  $s['termbox_text_size'],  'number', [], 'px');
                $grid_close();

                $group('Shape & Spacing');
                $grid_open();
                    $field('Border Width', 'termbox_border_width', $s['termbox_border_width'], 'number', [], 'px');
                    $field('Radius',       'termbox_radius',       $s['termbox_radius'],       'number', [], 'px');
                    $field('Padding V',    'termbox_padding_v',    $s['termbox_padding_v'],    'number', [], 'px');
                    $field('Padding H',    'termbox_padding_h',    $s['termbox_padding_h'],    'number', [], 'px');
                    $field('Shadow',       'termbox_shadow',       $s['termbox_shadow'],       'select', $card_shad, '', true);
                $grid_close();

                $card_close();

                // ══════════════════════════════════════════════════════
                // CARD 5: [wpgt_search] Widget
                $card_open('🔍', '[wpgt_search] — Search Widget', '.wpgt-search-widget');

                $group('Input', '.wpgt-search-input');
                $grid_open();
                    $field('Border',     'search_input_border',    $s['search_input_border'],    'color');
                    $field('Focus Ring', 'search_input_focus',     $s['search_input_focus'],     'color');
                    $field('Radius',     'search_input_radius',    $s['search_input_radius'],    'number', [], 'px');
                    $field('Font Size',  'search_input_size',      $s['search_input_size'],      'number', [], 'px');
                    $field('Padding V',  'search_input_padding_v', $s['search_input_padding_v'], 'number', [], 'px');
                    $field('Padding H',  'search_input_padding_h', $s['search_input_padding_h'], 'number', [], 'px');
                $grid_close();

                $group('Results Dropdown', '.wpgt-search-results');
                $grid_open();
                    $field('Background', 'search_results_bg',      $s['search_results_bg'],      'color');
                    $field('Item Hover', 'search_result_hover_bg', $s['search_result_hover_bg'], 'color');
                    $field('Radius',     'search_results_radius',  $s['search_results_radius'],  'number', [], 'px');
                    $field('Shadow',     'search_results_shadow',  $s['search_results_shadow'],  'select', $sr_shad);
                $grid_close();

                $group('Result Item Text', '.wpgt-search-result-item');
                $grid_open();
                    $field('Title Color',   'search_result_title',     $s['search_result_title'],     'color');
                    $field('Excerpt Color', 'search_result_excerpt',   $s['search_result_excerpt'],   'color');
                    $field('Padding V',     'search_result_padding_v', $s['search_result_padding_v'], 'number', [], 'px');
                    $field('Padding H',     'search_result_padding_h', $s['search_result_padding_h'], 'number', [], 'px');
                $grid_close();

                $card_close();
                ?>

                <div style="margin:24px 0 32px;display:flex;gap:10px;align-items:center;">
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

                </div><!-- /.wpgt-styles-fields -->

                <!-- ════ RIGHT: sticky live preview ════ -->
                <div class="wpgt-styles-preview">
                    <div class="wpgt-pv-sticky">

                        <div class="wpgt-pv-header">
                            <strong><?php esc_html_e( 'Live Preview', 'wp-glossary-tooltip' ); ?></strong>
                            <span class="wpgt-pv-sub"><?php esc_html_e( 'Updates as you edit', 'wp-glossary-tooltip' ); ?></span>
                        </div>

                        <div class="wpgt-pv-tabs" role="tablist">
                            <button type="button" class="wpgt-pv-tab wpgt-pv-tab--active" data-panel="tooltip"><?php esc_html_e('Tooltip','wp-glossary-tooltip'); ?></button>
                            <button type="button" class="wpgt-pv-tab" data-panel="glossary"><?php esc_html_e('Glossary','wp-glossary-tooltip'); ?></button>
                            <button type="button" class="wpgt-pv-tab" data-panel="termbox"><?php esc_html_e('Term Box','wp-glossary-tooltip'); ?></button>
                            <button type="button" class="wpgt-pv-tab" data-panel="search"><?php esc_html_e('Search','wp-glossary-tooltip'); ?></button>
                        </div>

                        <!-- ── Tooltip panel ── -->
                        <div id="wpgt-pv-panel-tooltip" class="wpgt-pv-panel">
                            <p class="wpgt-pv-label"><?php esc_html_e('Trigger word in content:','wp-glossary-tooltip'); ?></p>
                            <p style="margin:0 0 16px;font-size:0.95rem;line-height:1.6;">A sentence with a <span id="wpgt-pv-trigger" style="border-bottom:2px dashed #2563eb;cursor:help;">glossary term</span> in it.</p>
                            <p class="wpgt-pv-label"><?php esc_html_e('Tooltip bubble:','wp-glossary-tooltip'); ?></p>
                            <div id="wpgt-pv-tip" style="display:inline-block;padding:12px 14px;border-radius:6px;max-width:270px;box-shadow:0 8px 24px rgba(0,0,0,.18);background:#1e293b;color:#f1f5f9;border:1px solid rgba(255,255,255,.08);">
                                <strong id="wpgt-pv-title" style="display:block;margin-bottom:5px;font-size:0.9375rem;color:#fff;line-height:1.3;">Sample Term</strong>
                                <span id="wpgt-pv-text" style="display:block;font-size:0.875rem;margin-bottom:6px;line-height:1.55;">Short tooltip definition text.</span>
                                <a id="wpgt-pv-more" href="#" onclick="return false;" style="font-size:0.8125rem;font-weight:500;color:#93c5fd;">Read more →</a>
                            </div>
                        </div>

                        <!-- ── Glossary panel ── -->
                        <div id="wpgt-pv-panel-glossary" class="wpgt-pv-panel" style="display:none;">
                            <p class="wpgt-pv-label"><?php esc_html_e('A–Z bar:','wp-glossary-tooltip'); ?></p>
                            <nav id="wpgt-pv-az" style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:12px;padding:7px 9px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;">
                                <?php foreach ( ['ა','ბ','გ','დ','ე','ვ'] as $gl ) : ?>
                                <span class="wpgt-pv-az-link" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:4px;font-weight:600;font-size:0.8rem;color:#2563eb;"><?php echo esc_html($gl); ?></span>
                                <?php endforeach; ?>
                            </nav>
                            <p class="wpgt-pv-label"><?php esc_html_e('Letter heading:','wp-glossary-tooltip'); ?></p>
                            <h3 id="wpgt-pv-letter" style="font-size:1.4rem;font-weight:700;color:#2563eb;margin:0 0 10px;padding-bottom:4px;border-bottom:2px solid #2563eb;display:inline-block;">ა</h3>
                            <p class="wpgt-pv-label" style="margin-top:4px;"><?php esc_html_e('Term card:','wp-glossary-tooltip'); ?></p>
                            <div id="wpgt-pv-card" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px 14px;">
                                <a id="wpgt-pv-term-link" href="#" onclick="return false;" style="display:block;font-weight:600;font-size:0.9375rem;text-decoration:none;color:#2563eb;margin-bottom:4px;">სტრესი</a>
                                <p id="wpgt-pv-excerpt" style="margin:0;font-size:0.8125rem;color:#64748b;line-height:1.45;">A state of mental or emotional strain.</p>
                            </div>
                        </div>

                        <!-- ── Term box panel ── -->
                        <div id="wpgt-pv-panel-termbox" class="wpgt-pv-panel" style="display:none;">
                            <p class="wpgt-pv-label"><?php esc_html_e('[wpgt_term] output:','wp-glossary-tooltip'); ?></p>
                            <div id="wpgt-pv-box" style="border-left:4px solid #2563eb;padding:14px 18px;background:#f8fafc;border-radius:0 6px 6px 0;">
                                <h4 style="margin:0 0 8px;font-size:1.05rem;"><a id="wpgt-pv-box-title" href="#" onclick="return false;" style="color:#2563eb;text-decoration:none;">Term Title</a></h4>
                                <p id="wpgt-pv-box-def" style="margin:0;color:#374151;font-size:0.9rem;">Definition text shown in the term box shortcode output.</p>
                            </div>
                        </div>

                        <!-- ── Search panel ── -->
                        <div id="wpgt-pv-panel-search" class="wpgt-pv-panel" style="display:none;">
                            <p class="wpgt-pv-label"><?php esc_html_e('[wpgt_search] output:','wp-glossary-tooltip'); ?></p>
                            <input id="wpgt-pv-search-input" type="text"
                                   placeholder="<?php esc_attr_e('Search glossary…','wp-glossary-tooltip'); ?>"
                                   style="width:100%;padding:10px 16px;font-size:0.9375rem;border:1.5px solid #d1d5db;border-radius:6px;box-sizing:border-box;margin-bottom:6px;" readonly />
                            <div id="wpgt-pv-search-results" style="background:#fff;border:1px solid #e2e8f0;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.12);overflow:hidden;">
                                <div style="padding:10px 14px;background:#f0f7ff;border-bottom:1px solid #f1f5f9;">
                                    <span id="wpgt-pv-result-title" style="display:block;font-weight:600;font-size:0.9rem;color:#2563eb;">სტრესი</span>
                                    <span id="wpgt-pv-result-excerpt" style="display:block;font-size:0.8rem;color:#64748b;margin-top:2px;">A state of mental tension.</span>
                                </div>
                                <div style="padding:10px 14px;">
                                    <span style="display:block;font-weight:600;font-size:0.9rem;color:#1e293b;">მედიტაცია</span>
                                    <span style="display:block;font-size:0.8rem;color:#64748b;margin-top:2px;">A practice of focused attention.</span>
                                </div>
                            </div>
                        </div>

                    </div><!-- /.wpgt-pv-sticky -->
                </div><!-- /.wpgt-styles-preview -->

            </div><!-- /.wpgt-styles-layout -->
        </form>

        <script>
        jQuery(function($){

            // ── Preview tab switcher ────────────────────────────────────
            $('.wpgt-pv-tab').on('click', function(){
                $('.wpgt-pv-tab').removeClass('wpgt-pv-tab--active');
                $(this).addClass('wpgt-pv-tab--active');
                $('.wpgt-pv-panel').hide();
                $('#wpgt-pv-panel-' + $(this).data('panel')).show();
            });

            // ── Initialize color pickers (class wpgt-style-picker, NOT wpgt-color-picker) ──
            $('#wpgt-tab-styles .wpgt-style-picker').wpColorPicker({
                change: function(e, ui){ wpgtStylePreview($(this).data('key'), ui.color.toString()); },
                clear:  function()     { wpgtStylePreview($(this).data('key'), ''); }
            });

            // ── Apply saved values to preview on page load ──────────────
            <?php foreach ( $s as $key => $val ) : ?>
            wpgtStylePreview('<?php echo esc_js($key); ?>', '<?php echo esc_js((string)$val); ?>');
            <?php endforeach; ?>

            // ── Number / float / select inputs ──────────────────────────
            $('#wpgt-tab-styles').on('input change', 'input[type="number"], select', function(){
                wpgtStylePreview($(this).data('key'), $(this).val());
            });

            // ── Preview update function ──────────────────────────────────

            function px(v, def){ return parseFloat(v) > 0 ? v+'px' : def; }

            function wpgtStylePreview(key, val) {
                if (!key) return;
                var tip  = $('#wpgt-pv-tip');
                var trig = $('#wpgt-pv-trigger');
                var box  = $('#wpgt-pv-box');
                var az   = $('#wpgt-pv-az');
                var card = $('#wpgt-pv-card');
                var si   = $('#wpgt-pv-search-input');
                var sr   = $('#wpgt-pv-search-results');

                switch(key) {
                    // ── Trigger ────────────────────────────────────────
                    case 'trigger_color':            trig.css('border-bottom-color', val||'#2563eb'); break;
                    case 'trigger_underline_style':  trig.css('border-bottom-style', val||'dashed'); break;
                    case 'trigger_underline_width':  trig.css('border-bottom-width', px(val,'2px')); break;
                    case 'trigger_hover_color':      trig.css('color', val||''); break;
                    case 'trigger_font_weight':      trig.css('font-weight', val||'400'); break;
                    case 'trigger_font_size':        trig.css('font-size', parseFloat(val)>0 ? val+'px' : ''); break;
                    case 'trigger_text_transform':   trig.css('text-transform', val||'none'); break;

                    // ── Tooltip bubble ─────────────────────────────────
                    case 'tooltip_bg':            tip.css('background', val||'#1e293b'); break;
                    case 'tooltip_text_color':    tip.css('color', val||''); $('#wpgt-pv-text').css('color',val||''); break;
                    case 'tooltip_title_color':   $('#wpgt-pv-title').css('color', val||'#fff'); break;
                    case 'tooltip_link_color':    $('#wpgt-pv-more').css('color', val||'#93c5fd'); break;
                    case 'tooltip_font_size':     tip.css('font-size', px(val,'')); break;
                    case 'tooltip_line_height':   tip.css('line-height', parseFloat(val)>0 ? val : '1.55'); $('#wpgt-pv-text').css('line-height', parseFloat(val)>0 ? val : '1.55'); break;
                    case 'tooltip_padding_v':     tip.css({'padding-top':px(val,'12px'),'padding-bottom':px(val,'10px')}); break;
                    case 'tooltip_padding_h':     tip.css({'padding-left':px(val,'14px'),'padding-right':px(val,'14px')}); break;
                    case 'tooltip_border_radius': tip.css('border-radius', px(val,'6px')); break;
                    case 'tooltip_border_width':  tip.css('border-width', px(val,'1px')); break;
                    case 'tooltip_border_color':  tip.css('border-color', val||'rgba(255,255,255,.08)'); break;
                    case 'tooltip_max_width':     tip.css('max-width', parseFloat(val)>0 ? val+'px' : '270px'); break;
                    case 'tooltip_shadow':        { var ts = {none:'none',sm:'0 1px 4px rgba(0,0,0,.10)',default:'0 8px 24px rgba(0,0,0,.18)',lg:'0 16px 40px rgba(0,0,0,.30)'}; tip.css('box-shadow', ts[val]||ts['default']); break; }

                    // ── A-Z bar ────────────────────────────────────────
                    case 'az_bar_bg':           az.css('background', val||'#f8fafc'); break;
                    case 'az_bar_border_color': az.css('border-color', val||'#e2e8f0'); break;
                    case 'az_bar_radius':       az.css('border-radius', px(val,'6px')); break;
                    case 'az_bar_padding_v':    az.css({'padding-top':px(val,'7px'),'padding-bottom':px(val,'7px')}); break;
                    case 'az_bar_padding_h':    az.css({'padding-left':px(val,'9px'),'padding-right':px(val,'9px')}); break;
                    case 'az_bar_justify':      az.css('justify-content', val||'flex-start'); break;
                    case 'az_link_color':       az.find('.wpgt-pv-az-link').css('color', val||'#2563eb'); break;
                    case 'az_link_radius':      az.find('.wpgt-pv-az-link').css('border-radius', px(val,'4px')); break;
                    case 'az_link_size':        az.find('.wpgt-pv-az-link').css('font-size', px(val,'')); break;
                    case 'az_link_weight':      az.find('.wpgt-pv-az-link').css('font-weight', val||'600'); break;
                    case 'az_current_bg':       break;
                    case 'az_current_color':    break;
                    case 'az_current_border_color': break;
                    case 'az_current_border_width': break;
                    case 'az_current_hover_bg':     break;
                    case 'az_current_hover_color':  break;

                    // ── Letter heading ─────────────────────────────────
                    case 'letter_heading_color':         $('#wpgt-pv-letter').css('color', val||'#2563eb'); break;
                    case 'letter_heading_border':        $('#wpgt-pv-letter').css({'border-top-color':val||'#2563eb','border-bottom-color':val||'#2563eb'}); break;
                    case 'letter_heading_border_top':    $('#wpgt-pv-letter').css('border-top-width', parseFloat(val)>0 ? val+'px' : '0'); break;
                    case 'letter_heading_border_bottom': $('#wpgt-pv-letter').css('border-bottom-width', parseFloat(val)>0 ? val+'px' : '2px'); break;
                    case 'letter_heading_border_style':  $('#wpgt-pv-letter').css({'border-top-style':val||'solid','border-bottom-style':val||'solid'}); break;
                    case 'letter_heading_size':          $('#wpgt-pv-letter').css('font-size', px(val,'1.4rem')); break;
                    case 'letter_heading_weight':        $('#wpgt-pv-letter').css('font-weight', val||'700'); break;
                    case 'letter_heading_transform':     $('#wpgt-pv-letter').css('text-transform', val||'none'); break;
                    case 'letter_heading_mb':            $('#wpgt-pv-letter').css('margin-bottom', px(val,'10px')); break;
                    case 'letter_heading_align':         $('#wpgt-pv-letter').css('text-align', val||'left'); break;
                    case 'letter_heading_display':       $('#wpgt-pv-letter').css('display', val||'inline-block'); break;
                    case 'letter_heading_full_width':    $('#wpgt-pv-letter').css('width', val==='1'||val===1 ? '100%' : 'auto'); break;
                    case 'letter_heading_justify':       break;

                    // ── Term cards ─────────────────────────────────────
                    case 'term_card_bg':          card.css('background', val||'#f8fafc'); break;
                    case 'term_card_border':      card.css('border-color', val||'#e2e8f0'); break;
                    case 'term_card_radius':      card.css('border-radius', px(val,'6px')); break;
                    case 'term_card_hover_border':break;
                    case 'term_card_shadow':      { var cs={none:'none',sm:'0 1px 3px rgba(0,0,0,.06)',md:'0 2px 8px rgba(0,0,0,.10)',lg:'0 8px 20px rgba(0,0,0,.14)'}; card.css('box-shadow',cs[val]||'none'); break; }
                    case 'term_card_padding_v':   card.css({'padding-top':px(val,'12px'),'padding-bottom':px(val,'12px')}); break;
                    case 'term_card_padding_h':   card.css({'padding-left':px(val,'14px'),'padding-right':px(val,'14px')}); break;
                    case 'term_link_color':       $('#wpgt-pv-term-link').css('color', val||'#2563eb'); break;
                    case 'term_link_size':        $('#wpgt-pv-term-link').css('font-size', px(val,'')); break;
                    case 'term_link_weight':      $('#wpgt-pv-term-link').css('font-weight', val||'600'); break;
                    case 'term_excerpt_color':    $('#wpgt-pv-excerpt').css('color', val||'#64748b'); break;
                    case 'term_excerpt_size':     $('#wpgt-pv-excerpt').css('font-size', px(val,'')); break;

                    // ── Term box ───────────────────────────────────────
                    case 'termbox_border_color': box.css('border-left-color', val||'#2563eb'); break;
                    case 'termbox_border_width': box.css('border-left-width', px(val,'4px')); break;
                    case 'termbox_bg':           box.css('background', val||'#f8fafc'); break;
                    case 'termbox_title_color':  $('#wpgt-pv-box-title').css('color', val||'#2563eb'); break;
                    case 'termbox_title_size':   $('#wpgt-pv-box-title').css('font-size', px(val,'')); break;
                    case 'termbox_title_weight': $('#wpgt-pv-box-title').css('font-weight', val||'600'); break;
                    case 'termbox_text_color':   $('#wpgt-pv-box-def').css('color', val||'#374151'); break;
                    case 'termbox_text_size':    $('#wpgt-pv-box-def').css('font-size', px(val,'')); break;
                    case 'termbox_radius':       { var r=parseFloat(val)||0; box.css('border-radius', r>0 ? '0 '+r+'px '+r+'px 0' : '0 6px 6px 0'); break; }
                    case 'termbox_padding_v':    box.css({'padding-top':px(val,'14px'),'padding-bottom':px(val,'14px')}); break;
                    case 'termbox_padding_h':    box.css({'padding-left':px(val,'18px'),'padding-right':px(val,'18px')}); break;
                    case 'termbox_shadow':       { var ts2={none:'none',sm:'0 1px 3px rgba(0,0,0,.06)',md:'0 2px 8px rgba(0,0,0,.10)',lg:'0 8px 20px rgba(0,0,0,.14)'}; box.css('box-shadow',ts2[val]||'none'); break; }

                    // ── Search ─────────────────────────────────────────
                    case 'search_input_border':    si.css('border-color', val||'#d1d5db'); break;
                    case 'search_input_focus':     break;
                    case 'search_input_radius':    si.css('border-radius', px(val,'6px')); break;
                    case 'search_input_size':      si.css('font-size', px(val,'')); break;
                    case 'search_input_padding_v': si.css({'padding-top':px(val,'10px'),'padding-bottom':px(val,'10px')}); break;
                    case 'search_input_padding_h': si.css({'padding-left':px(val,'16px'),'padding-right':px(val,'16px')}); break;
                    case 'search_results_bg':      sr.css('background', val||'#fff'); break;
                    case 'search_results_radius':  sr.css('border-radius', px(val,'6px')); break;
                    case 'search_results_shadow':  { var srs={none:'none',sm:'0 1px 3px rgba(0,0,0,.06)',default:'0 4px 16px rgba(0,0,0,.12)',lg:'0 8px 24px rgba(0,0,0,.18)'}; sr.css('box-shadow',srs[val]||srs['default']); break; }
                    case 'search_result_hover_bg': sr.find('div:first').css('background', val||'#f0f7ff'); break;
                    case 'search_result_title':    $('#wpgt-pv-result-title').css('color', val||'#2563eb'); break;
                    case 'search_result_excerpt':  $('#wpgt-pv-result-excerpt').css('color', val||'#64748b'); break;
                    case 'search_result_padding_v':sr.find('div').css({'padding-top':px(val,'10px'),'padding-bottom':px(val,'10px')}); break;
                    case 'search_result_padding_h':sr.find('div').css({'padding-left':px(val,'14px'),'padding-right':px(val,'14px')}); break;
                }
            }
        });
        </script>
        <style>
        /* ════════════════════════════════════════════════════════════
           OUTER LAYOUT  –  fields column + sticky preview column
        ════════════════════════════════════════════════════════════ */
        #wpgt-tab-styles .wpgt-styles-layout {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        #wpgt-tab-styles .wpgt-styles-fields  { flex: 1; min-width: 0; }
        #wpgt-tab-styles .wpgt-styles-preview { width: 200px; flex-shrink: 0; }
        @media (max-width: 900px) {
            #wpgt-tab-styles .wpgt-styles-layout  { flex-direction: column; }
            #wpgt-tab-styles .wpgt-styles-preview { width: 100%; }
        }

        /* ════════════════════════════════════════════════════════════
           CARDS  –  one per component section
        ════════════════════════════════════════════════════════════ */
        .wpgt-card {
            background: #fff;
            border: 1px solid #e0e3e8;
            border-radius: 8px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .wpgt-card-head {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: #f6f7f8;
            border-bottom: 1px solid #e0e3e8;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
        }
        .wpgt-card-icon { font-size: 1rem; line-height: 1; }
        .wpgt-card-body { padding: 14px; }

        /* ════════════════════════════════════════════════════════════
           GROUP LABELS  –  sub-sections inside a card
        ════════════════════════════════════════════════════════════ */
        .wpgt-group-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #64748b;
            margin: 14px 0 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #f1f3f5;
        }
        .wpgt-group-label:first-child { margin-top: 0; }

        /* ════════════════════════════════════════════════════════════
           2-COLUMN FIELD GRID
        ════════════════════════════════════════════════════════════ */
        .wpgt-field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 14px;
        }

        /* ════════════════════════════════════════════════════════════
           INDIVIDUAL FIELD  –  label on top, control below
        ════════════════════════════════════════════════════════════ */
        .wpgt-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }
        .wpgt-field--full {
            grid-column: 1 / -1;   /* spans both columns */
        }
        .wpgt-field label {
            font-size: 0.75rem;
            font-weight: 500;
            color: #475569;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ── Color picker row ─────────────────────────────────── */
        .wpgt-field-color-wrap .wp-color-result {
            width: 28px !important;
            height: 24px !important;
            border-radius: 4px !important;
            margin: 0 !important;
        }
        .wpgt-field-color-wrap .wp-picker-container {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .wpgt-field-color-wrap .wp-color-result-text { display: none; }
        /* Compact the hidden text input beside the swatch */
        .wpgt-field-color-wrap input[type=text].wpgt-style-picker {
            width: 72px !important;
            font-size: 0.75rem !important;
            height: 24px !important;
            padding: 0 4px !important;
            border-radius: 4px;
        }

        /* ── Number input row ─────────────────────────────────── */
        .wpgt-field-num-wrap {
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .wpgt-field-num-wrap input[type=number] {
            width: 54px !important;
            font-size: 0.8rem;
            padding: 3px 5px !important;
            height: 26px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            box-shadow: none;
        }
        .wpgt-field-num-wrap span {
            font-size: 0.72rem;
            color: #94a3b8;
        }

        /* ── Select ───────────────────────────────────────────── */
        .wpgt-field select {
            width: 100%;
            font-size: 0.8rem;
            height: 26px;
            padding: 0 4px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            background: #fff;
        }

        /* ── Float input ──────────────────────────────────────── */
        .wpgt-field input[type=number][step] {
            width: 64px !important;
            font-size: 0.8rem;
            padding: 3px 5px !important;
            height: 26px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
        }

        /* ════════════════════════════════════════════════════════════
           CSS CLASS BADGE
        ════════════════════════════════════════════════════════════ */
        .wpgt-css-badge {
            font-size: 0.68rem;
            font-weight: 400;
            background: #eff6ff;
            color: #2563eb;
            padding: 1px 5px;
            border-radius: 3px;
            font-family: monospace;
            letter-spacing: 0;
            flex-shrink: 0;
        }
        .wpgt-css-badge--sm { font-size: 0.64rem; }

        /* ════════════════════════════════════════════════════════════
           STICKY PREVIEW SIDEBAR
        ════════════════════════════════════════════════════════════ */
        .wpgt-pv-sticky {
            position: relative;
            background: #fff;
            border: 1px solid #e0e3e8;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
        }
        .wpgt-pv-header { margin-bottom: 10px; }
        .wpgt-pv-header strong { font-size: 0.8rem; display: block; color: #1e293b; }
        .wpgt-pv-sub { font-size: 0.68rem; color: #94a3b8; }

        /* Preview tab bar */
        .wpgt-pv-tabs {
            display: flex;
            gap: 1px;
            margin-bottom: 10px;
            border-bottom: 1px solid #e0e3e8;
            flex-wrap: wrap;
        }
        .wpgt-pv-tab {
            background: none;
            border: 1px solid transparent;
            border-bottom: none;
            padding: 3px 6px;
            cursor: pointer;
            border-radius: 3px 3px 0 0;
            font-size: 0.68rem;
            color: #64748b;
            margin-bottom: -1px;
            line-height: 1.4;
        }
        .wpgt-pv-tab:hover { background: #f8fafc; }
        .wpgt-pv-tab--active {
            background: #fff;
            border-color: #e0e3e8 #e0e3e8 #fff;
            color: #1e293b;
            font-weight: 600;
        }
        .wpgt-pv-label {
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #94a3b8;
            margin: 0 0 4px;
        }
        .wpgt-pv-panel { font-size: 0.78rem; }
        </style>
        <?php
    }

    /**
     * Style field defaults — mirrors public.css values so reset works correctly.
     */
    public static function get_style_defaults(): array {
        return [
            // ── Trigger word ──────────────────────────────────────────────
            'trigger_color'            => '#2563eb',   // combined underline + hover base (was trigger_underline_color)
            'trigger_underline_style'  => 'dashed',
            'trigger_underline_width'  => '2',         // px
            'trigger_hover_color'      => '#2563eb',
            'trigger_font_weight'      => '400',
            'trigger_font_size'        => '0',         // 0 = inherit
            'trigger_text_transform'   => '',
            // ── Tooltip bubble ────────────────────────────────────────────
            'tooltip_bg'               => '',          // empty = use theme default
            'tooltip_text_color'       => '',
            'tooltip_title_color'      => '',
            'tooltip_link_color'       => '',
            'tooltip_font_size'        => '14',        // px
            'tooltip_line_height'      => '1.55',
            'tooltip_padding_v'        => '12',        // px
            'tooltip_padding_h'        => '14',        // px
            'tooltip_border_radius'    => '6',         // px
            'tooltip_border_width'     => '1',         // px
            'tooltip_border_color'     => '',          // empty = theme border
            'tooltip_max_width'        => '0',         // 0 = auto
            'tooltip_shadow'           => 'default',
            // ── A-Z bar ───────────────────────────────────────────────────
            'az_bar_bg'                => '#f8fafc',
            'az_bar_border_color'      => '#e2e8f0',
            'az_bar_radius'            => '6',
            'az_bar_padding_v'         => '10',
            'az_bar_padding_h'         => '12',
            'az_bar_justify'           => 'flex-start',  // justify-content for the bar
            'az_link_color'            => '#2563eb',
            'az_link_hover_bg'         => '#2563eb',
            'az_link_hover_color'      => '#ffffff',
            'az_link_radius'           => '4',
            'az_link_size'             => '14',        // px
            'az_link_weight'           => '600',
            'az_current_bg'            => '#2563eb',
            'az_current_color'         => '#ffffff',
            'az_current_border_color'  => '',
            'az_current_border_width'  => '0',
            'az_current_border_style'  => 'solid',
            'az_current_border_radius' => '4',
            'az_current_hover_bg'      => '',
            'az_current_hover_color'   => '',
            // ── Letter headings ────────────────────────────────────────────
            'letter_heading_color'          => '#2563eb',
            'letter_heading_border'         => '#2563eb',
            'letter_heading_size'           => '24',
            'letter_heading_weight'         => '700',
            'letter_heading_transform'      => '',
            'letter_heading_mb'             => '12',
            'letter_heading_align'          => 'left',    // text-align
            'letter_heading_justify'        => 'flex-start', // justify-content (when flex)
            'letter_heading_display'        => 'inline-block', // inline-block | block
            'letter_heading_full_width'     => '0',       // 1 = width:100%
            'letter_heading_border_top'     => '0',       // px top border width
            'letter_heading_border_bottom'  => '2',       // px bottom border width (default underline)
            'letter_heading_border_style'   => 'solid',
            // ── Term cards ─────────────────────────────────────────────────
            'term_card_bg'             => '#f8fafc',
            'term_card_border'         => '#e2e8f0',
            'term_card_radius'         => '6',
            'term_card_hover_border'   => '#2563eb',
            'term_card_shadow'         => 'none',
            'term_card_padding_v'      => '12',
            'term_card_padding_h'      => '14',
            'term_link_color'          => '#2563eb',
            'term_link_size'           => '15',
            'term_link_weight'         => '600',
            'term_excerpt_color'       => '#64748b',
            'term_excerpt_size'        => '13',
            // ── Term box [wpgt_term] ────────────────────────────────────────
            'termbox_border_color'     => '#2563eb',
            'termbox_border_width'     => '4',
            'termbox_bg'               => '#f8fafc',
            'termbox_title_color'      => '#2563eb',
            'termbox_title_size'       => '17',
            'termbox_title_weight'     => '600',
            'termbox_text_color'       => '#374151',
            'termbox_text_size'        => '0',
            'termbox_radius'           => '6',
            'termbox_padding_v'        => '14',
            'termbox_padding_h'        => '18',
            'termbox_shadow'           => 'none',
            // ── Search widget ───────────────────────────────────────────────
            'search_input_border'      => '#d1d5db',
            'search_input_focus'       => '#2563eb',
            'search_input_radius'      => '6',
            'search_input_size'        => '15',
            'search_input_padding_v'   => '10',
            'search_input_padding_h'   => '16',
            'search_results_bg'        => '#ffffff',
            'search_results_radius'    => '6',
            'search_results_shadow'    => 'default',
            'search_result_hover_bg'   => '#f0f7ff',
            'search_result_title'      => '#1e293b',
            'search_result_excerpt'    => '#64748b',
            'search_result_padding_v'  => '10',
            'search_result_padding_h'  => '14',
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

        // Fields that accept hex colour OR the keyword 'transparent'
        $bg_keys = [
            'tooltip_bg', 'az_bar_bg', 'az_current_bg', 'az_link_hover_bg',
            'az_current_hover_bg',
            'term_card_bg', 'termbox_bg', 'search_results_bg', 'search_result_hover_bg',
        ];
        // Fields that accept hex colour only
        $color_keys = [
            'trigger_color','trigger_hover_color',
            'tooltip_text_color','tooltip_title_color','tooltip_link_color',
            'tooltip_border_color',
            'az_bar_border_color','az_link_color','az_link_hover_color',
            'az_current_color','az_current_border_color','az_current_hover_color',
            'letter_heading_color','letter_heading_border',
            'term_card_border','term_card_hover_border',
            'term_link_color','term_excerpt_color',
            'termbox_border_color','termbox_title_color','termbox_text_color',
            'search_input_border','search_input_focus',
            'search_result_title','search_result_excerpt',
        ];
        $select_keys = [
            'trigger_underline_style'      => ['dashed','solid','dotted','none'],
            'trigger_font_weight'          => ['400','500','600','700'],
            'trigger_text_transform'       => ['','uppercase','lowercase','capitalize'],
            'az_link_weight'               => ['400','500','600','700'],
            'az_bar_justify'               => ['flex-start','center','flex-end','space-between'],
            'az_current_border_style'      => ['solid','dashed','dotted','none'],
            'letter_heading_weight'        => ['400','500','600','700'],
            'letter_heading_transform'     => ['','uppercase','lowercase','capitalize'],
            'letter_heading_align'         => ['left','center','right'],
            'letter_heading_justify'       => ['flex-start','center','flex-end'],
            'letter_heading_display'       => ['inline-block','block'],
            'letter_heading_border_style'  => ['solid','dashed','dotted','none'],
            'term_link_weight'             => ['400','500','600','700'],
            'termbox_title_weight'         => ['400','500','600','700'],
            'tooltip_shadow'               => ['none','sm','default','lg'],
            'term_card_shadow'             => ['none','sm','md','lg'],
            'termbox_shadow'               => ['none','sm','md','lg'],
            'search_results_shadow'        => ['none','sm','default','lg'],
        ];

        foreach ( $defaults as $key => $default ) {
            if ( ! isset( $raw[ $key ] ) ) continue;
            $val = $raw[ $key ];
            if ( in_array( $key, $bg_keys, true ) ) {
                // Allow hex colours or the keyword 'transparent'
                $clean[ $key ] = ( $val === 'transparent' ) ? 'transparent' : ( sanitize_hex_color( $val ) ?: '' );
            } elseif ( in_array( $key, $color_keys, true ) ) {
                $clean[ $key ] = sanitize_hex_color( $val ) ?: '';
            } elseif ( isset( $select_keys[ $key ] ) ) {
                $clean[ $key ] = in_array( $val, $select_keys[$key], true ) ? $val : $default;
            } elseif ( $key === 'tooltip_line_height' ) {
                $v = trim( $val );
                $clean[ $key ] = ( $v !== '' && preg_match('/^\d*\.?\d+$/', $v) ) ? $v : '1.55';
            } elseif ( $key === 'letter_heading_full_width' ) {
                $clean[ $key ] = (int) $val ? '1' : '0';
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