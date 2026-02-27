<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPGT_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menus'          ] );
        add_action( 'add_meta_boxes',        [ __CLASS__, 'add_meta_boxes'     ] );
        add_action( 'save_post',             [ __CLASS__, 'save_meta'          ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets'     ] );
        add_action( 'admin_post_wpgt_save_settings', [ __CLASS__, 'save_settings' ] );
        add_action( 'admin_post_wpgt_export',        [ __CLASS__, 'export_csv'    ] );
        add_action( 'admin_post_wpgt_import',        [ __CLASS__, 'import_csv'    ] );

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
                    <a href="#wpgt-tab-general"  class="nav-tab nav-tab-active"><?php esc_html_e( 'General',        'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-tooltip"  class="nav-tab"><?php esc_html_e( 'Tooltip',         'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-index"    class="nav-tab"><?php esc_html_e( 'Index Page',      'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-advanced" class="nav-tab"><?php esc_html_e( 'Advanced',        'wp-glossary-tooltip' ); ?></a>
                    <a href="#wpgt-tab-import"   class="nav-tab"><?php esc_html_e( 'Import / Export', 'wp-glossary-tooltip' ); ?></a>
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
                </div>

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
}
