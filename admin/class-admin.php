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
    // ------------------------------------------------------------------
    // Enqueue admin assets
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
            [ 'jquery', 'wp-color-picker', 'jquery-ui-sortable' ],
            WPGT_VERSION,
            true
        );
        wp_localize_script( 'wpgt-admin', 'wpgtAdmin', [
            'sortNonce' => wp_create_nonce( 'wpgt_save_order' ),
        ] );
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
        if ( ! wp_verify_nonce( sanitize_key( $_POST['wpgt_meta_nonce'] ), 'wpgt_save_meta' ) ) return;
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
                update_post_meta( $post_id, $meta_key, sanitize_textarea_field( wp_unslash( $_POST[ $post_key ] ) ) );
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

        $settings   = WPGT_Settings::get_all();
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $saved      = isset( $_GET['wpgt_saved'] );
        ?>
        <div class="wrap wpgt-settings-wrap">

        <!-- TOPBAR ──────────────────────────────────────────────── -->
        <div class="wpgt-topbar">
            <div class="wpgt-topbar-brand">🗂 WP Glossary</div>
            <nav>
                <a href="#" class="wpgt-tab-link wpgt-active" data-panel="wpgt-panel-general">⚙ <?php esc_html_e('General','wp-glossary-tooltip'); ?></a>
                <a href="#" class="wpgt-tab-link" data-panel="wpgt-panel-tooltip">💬 <?php esc_html_e('Tooltip','wp-glossary-tooltip'); ?></a>
                <a href="#" class="wpgt-tab-link" data-panel="wpgt-panel-index">📋 <?php esc_html_e('Index Page','wp-glossary-tooltip'); ?></a>
                <a href="#" class="wpgt-tab-link" data-panel="wpgt-panel-advanced">🔧 <?php esc_html_e('Advanced','wp-glossary-tooltip'); ?></a>
                <a href="#" class="wpgt-tab-link" data-panel="wpgt-panel-import">📦 <?php esc_html_e('Import / Export','wp-glossary-tooltip'); ?></a>
                <a href="#" class="wpgt-tab-link" data-panel="wpgt-panel-styles">🎨 <?php esc_html_e('Styles','wp-glossary-tooltip'); ?></a>
            </nav>
            <?php if ( $saved ) : ?>
            <div class="wpgt-topbar-saved">✓ <?php esc_html_e('Saved','wp-glossary-tooltip'); ?></div>
            <?php endif; ?>
        </div>

        <?php
        /* ─── Helper: wrap panel that has a form+savebar ───────────
           Structure per panel:
             .wpgt-tab-panel  (flex column, fixed)
               form (flex:1, display:flex, flex-direction:column)
                 .wpgt-panel-body   (flex:1, overflow-y:auto)  ← scrolls
                   .wpgt-panel-card
                 .wpgt-panel-savebar (flex:0)                  ← sticky bottom
        ─────────────────────────────────────────────────────── */
        ?>

        <!-- GENERAL ─────────────────────────────────────────────── -->
        <div id="wpgt-panel-general" class="wpgt-tab-panel wpgt-active">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;flex-direction:column;flex:1;min-height:0;">
                <?php wp_nonce_field('wpgt_settings_save','wpgt_settings_nonce'); ?>
                <input type="hidden" name="action" value="wpgt_save_settings">
                <div class="wpgt-panel-body">
                    <div class="wpgt-panel-card">
                        <h3><?php esc_html_e('Tooltip Behaviour','wp-glossary-tooltip'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Enable Tooltips','wp-glossary-tooltip'); ?></th>
                                <td><label><input type="checkbox" name="enable_tooltips" value="1" <?php checked($settings['enable_tooltips']); ?>>
                                    <?php esc_html_e('Automatically add tooltips to glossary terms in content','wp-glossary-tooltip'); ?></label></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Parse Post Types','wp-glossary-tooltip'); ?></th>
                                <td><?php foreach ($post_types as $pt) :
                                    if (in_array($pt->name,[WPGT_Post_Type::POST_TYPE,'attachment'],true)) continue; ?>
                                    <label style="display:block;margin-bottom:5px;">
                                        <input type="checkbox" name="parse_post_types[]" value="<?php echo esc_attr($pt->name); ?>"
                                               <?php checked(in_array($pt->name,(array)$settings['parse_post_types'],true)); ?>>
                                        <?php echo esc_html($pt->labels->name); ?>
                                    </label>
                                <?php endforeach; ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('First Occurrence Only','wp-glossary-tooltip'); ?></th>
                                <td><label><input type="checkbox" name="first_occurrence" value="1" <?php checked($settings['first_occurrence']); ?>>
                                    <?php esc_html_e('Only highlight the first occurrence of each term per page','wp-glossary-tooltip'); ?></label></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Case Sensitive','wp-glossary-tooltip'); ?></th>
                                <td><label><input type="checkbox" name="case_sensitive" value="1" <?php checked($settings['case_sensitive']); ?>>
                                    <?php esc_html_e('Match terms case-sensitively','wp-glossary-tooltip'); ?></label></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Exclude Headings','wp-glossary-tooltip'); ?></th>
                                <td><label><input type="checkbox" name="exclude_headings" value="1" <?php checked($settings['exclude_headings']); ?>>
                                    <?php esc_html_e('Do not add tooltips inside H1–H6 tags','wp-glossary-tooltip'); ?></label></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Exclude Links','wp-glossary-tooltip'); ?></th>
                                <td><label><input type="checkbox" name="exclude_links" value="1" <?php checked($settings['exclude_links']); ?>>
                                    <?php esc_html_e('Do not add tooltips inside &lt;a&gt; tags','wp-glossary-tooltip'); ?></label></td>
                            </tr>
                        </table>
                    </div>
                </div><!-- /.wpgt-panel-body -->
                <div class="wpgt-panel-savebar">
                    <?php submit_button(__('Save Settings','wp-glossary-tooltip'),'primary','submit',false); ?>
                </div>
            </form>
        </div>

        <!-- TOOLTIP ─────────────────────────────────────────────── -->
        <div id="wpgt-panel-tooltip" class="wpgt-tab-panel">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;flex-direction:column;flex:1;min-height:0;">
                <?php wp_nonce_field('wpgt_settings_save','wpgt_settings_nonce'); ?>
                <input type="hidden" name="action" value="wpgt_save_settings">
                <div class="wpgt-panel-body">
                    <div class="wpgt-panel-card">
                        <h3><?php esc_html_e('Behaviour','wp-glossary-tooltip'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Open On','wp-glossary-tooltip'); ?></th>
                                <td><select name="open_on">
                                    <option value="hover" <?php selected($settings['open_on'],'hover'); ?>><?php esc_html_e('Hover','wp-glossary-tooltip'); ?></option>
                                    <option value="click" <?php selected($settings['open_on'],'click'); ?>><?php esc_html_e('Click','wp-glossary-tooltip'); ?></option>
                                </select></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Position','wp-glossary-tooltip'); ?></th>
                                <td><select name="tooltip_position">
                                    <?php foreach (['top','bottom'] as $pos) : ?>
                                    <option value="<?php echo $pos; ?>" <?php selected($settings['tooltip_position'],$pos); ?>><?php echo ucfirst($pos); ?></option>
                                    <?php endforeach; ?>
                                </select></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Show "Read More" Link','wp-glossary-tooltip'); ?></th>
                                <td><label><input type="checkbox" name="show_see_more" value="1" <?php checked($settings['show_see_more']); ?>>
                                    <?php esc_html_e('Show a "Read more →" link pointing to the term page','wp-glossary-tooltip'); ?></label></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Open in New Tab','wp-glossary-tooltip'); ?></th>
                                <td><label><input type="checkbox" name="link_new_tab" value="1" <?php checked($settings['link_new_tab'] ?? true); ?>>
                                    <?php esc_html_e('Open "Read more" link in a new browser tab','wp-glossary-tooltip'); ?></label></td>
                            </tr>
                        </table>
                        <p class="description" style="margin-top:16px;">
                            <?php $styles_url = admin_url('edit.php?post_type='.WPGT_Post_Type::POST_TYPE.'&page=wpgt-settings');
                            printf(wp_kses(__('Colours, fonts and visual options are in the <a href="%s">Styles tab</a>.','wp-glossary-tooltip'),['a'=>['href'=>[]]]),esc_url($styles_url)); ?>
                        </p>
                    </div>
                </div>
                <div class="wpgt-panel-savebar">
                    <?php submit_button(__('Save Settings','wp-glossary-tooltip'),'primary','submit',false); ?>
                </div>
            </form>
        </div>

        <!-- INDEX PAGE ──────────────────────────────────────────── -->
        <div id="wpgt-panel-index" class="wpgt-tab-panel">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;flex-direction:column;flex:1;min-height:0;">
                <?php wp_nonce_field('wpgt_settings_save','wpgt_settings_nonce'); ?>
                <input type="hidden" name="action" value="wpgt_save_settings">
                <div class="wpgt-panel-body">
                    <div class="wpgt-panel-card">
                        <h3><?php esc_html_e('Glossary Index','wp-glossary-tooltip'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Glossary Slug','wp-glossary-tooltip'); ?></th>
                                <td><input type="text" name="glossary_slug" value="<?php echo esc_attr($settings['glossary_slug']); ?>">
                                    <p class="description"><?php esc_html_e('URL slug for the glossary archive (save permalinks after change).','wp-glossary-tooltip'); ?></p></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Index Columns','wp-glossary-tooltip'); ?></th>
                                <td><select name="index_columns">
                                    <?php for ($c=1;$c<=4;$c++) : ?>
                                    <option value="<?php echo $c; ?>" <?php selected((int)$settings['index_columns'],$c); ?>><?php echo $c; ?></option>
                                    <?php endfor; ?>
                                </select></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Show A–Z Bar','wp-glossary-tooltip'); ?></th>
                                <td><label><input type="checkbox" name="show_alphabet_bar" value="1" <?php checked($settings['show_alphabet_bar']); ?>>
                                    <?php esc_html_e('Show the A–Z navigation bar on the [wpgt_glossary] output','wp-glossary-tooltip'); ?></label></td>
                            </tr>
                        </table>
                        <div class="wpgt-shortcode-help">
                            <h3><?php esc_html_e('Available Shortcodes','wp-glossary-tooltip'); ?></h3>
                            <dl>
                                <dt><code>[wpgt_glossary]</code></dt>
                                <dd><?php esc_html_e('Full A–Z glossary index. Accepts: columns, show_alphabet, category, orderby.','wp-glossary-tooltip'); ?></dd>
                                <dt><code>[wpgt_term id="123"]</code></dt>
                                <dd><?php esc_html_e('Inline definition box for a single term. Also accepts slug="my-term".','wp-glossary-tooltip'); ?></dd>
                                <dt><code>[wpgt_search]</code></dt>
                                <dd><?php esc_html_e('Live AJAX search widget. Accepts: placeholder.','wp-glossary-tooltip'); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="wpgt-panel-savebar">
                    <?php submit_button(__('Save Settings','wp-glossary-tooltip'),'primary','submit',false); ?>
                </div>
            </form>
        </div>

        <!-- ADVANCED ────────────────────────────────────────────── -->
        <div id="wpgt-panel-advanced" class="wpgt-tab-panel">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;flex-direction:column;flex:1;min-height:0;">
                <?php wp_nonce_field('wpgt_settings_save','wpgt_settings_nonce'); ?>
                <input type="hidden" name="action" value="wpgt_save_settings">
                <div class="wpgt-panel-body">
                    <div class="wpgt-panel-card">
                        <h3><?php esc_html_e('REST API','wp-glossary-tooltip'); ?></h3>
                        <p style="font-size:0.82rem;color:#555;margin:0 0 10px;"><?php esc_html_e('Endpoints available at:','wp-glossary-tooltip'); ?></p>
                        <ul style="margin:0 0 0 16px;font-size:0.82rem;color:#555;line-height:2;">
                            <li><code><?php echo esc_html(rest_url('wpgt/v1/terms')); ?></code></li>
                            <li><code><?php echo esc_html(rest_url('wpgt/v1/terms/{id}')); ?></code></li>
                            <li><code><?php echo esc_html(rest_url('wpgt/v1/search?q=…')); ?></code></li>
                        </ul>
                    </div>
                    <div class="wpgt-panel-card">
                        <h3><?php esc_html_e('Maintenance','wp-glossary-tooltip'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Flush Rewrite Rules','wp-glossary-tooltip'); ?></th>
                                <td><a href="<?php echo esc_url(add_query_arg(['wpgt_flush'=>'1','_wpnonce'=>wp_create_nonce('wpgt_flush')],admin_url())); ?>" class="button">
                                    <?php esc_html_e('Flush Rewrite Rules','wp-glossary-tooltip'); ?></a></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="wpgt-panel-savebar">
                    <?php submit_button(__('Save Settings','wp-glossary-tooltip'),'primary','submit',false); ?>
                </div>
            </form>
        </div>

        <!-- IMPORT / EXPORT ─────────────────────────────────────── -->
        <div id="wpgt-panel-import" class="wpgt-tab-panel">
            <div class="wpgt-panel-body">

                <div class="wpgt-panel-card">
                    <h3><?php esc_html_e('Export Glossary','wp-glossary-tooltip'); ?></h3>
                    <p style="font-size:0.82rem;color:#555;margin:0 0 14px;"><?php esc_html_e('Download all glossary terms as an Excel (.xlsx) file.','wp-glossary-tooltip'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin-post.php?action=wpgt_export&_wpnonce='.wp_create_nonce('wpgt_export'))); ?>"
                       class="button button-secondary">⬇ <?php esc_html_e('Download Excel (.xlsx)','wp-glossary-tooltip'); ?></a>
                </div>

                <div class="wpgt-panel-card">
                    <h3><?php esc_html_e('Import Glossary','wp-glossary-tooltip'); ?></h3>
                    <p style="font-size:0.82rem;color:#555;margin:0 0 8px;"><?php esc_html_e('Upload an Excel (.xlsx) file to bulk-create or update glossary terms.','wp-glossary-tooltip'); ?></p>
                    <p style="font-size:0.82rem;color:#555;margin:0 0 14px;">
                        <?php esc_html_e('Two columns only:','wp-glossary-tooltip'); ?> <code>word</code> <?php esc_html_e('and','wp-glossary-tooltip'); ?> <code>explanation</code>
                    </p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field('wpgt_import','wpgt_import_nonce'); ?>
                        <input type="hidden" name="action" value="wpgt_import">
                        <input type="file" name="wpgt_csv" accept=".xlsx" required style="margin-right:10px;">
                        <input type="submit" class="button button-primary" value="⬆ <?php esc_attr_e('Import Excel','wp-glossary-tooltip'); ?>">
                    </form>
                    <?php if (!empty($_GET['wpgt_imported'])) : ?>
                    <div class="wpgt-notice wpgt-notice-success" style="margin-top:14px;">
                        <?php printf(esc_html__('Import complete: %d terms created, %d updated.','wp-glossary-tooltip'),(int)($_GET['created']??0),(int)($_GET['updated']??0)); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="wpgt-panel-card">
                    <h3><?php esc_html_e('Letter Taxonomy','wp-glossary-tooltip'); ?></h3>
                    <p style="font-size:0.82rem;color:#555;margin:0 0 10px;"><?php esc_html_e('Assigns every term to its first-letter taxonomy. Run after importing.','wp-glossary-tooltip'); ?></p>
                    <?php
                    $letter_terms = get_terms(['taxonomy'=>WPGT_Post_Type::LETTER_TAX,'hide_empty'=>false]);
                    $letter_list  = !is_wp_error($letter_terms) && !empty($letter_terms)
                        ? implode(', ', array_map(fn($t)=>$t->name.' <small>('.$t->count.')</small>', $letter_terms))
                        : '<em>'.esc_html__('None yet','wp-glossary-tooltip').'</em>';
                    ?>
                    <p style="font-size:0.82rem;color:#555;margin:0 0 14px;"><?php esc_html_e('Current letters:','wp-glossary-tooltip'); ?> <?php echo $letter_list; ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('wpgt_sync_letters','wpgt_sync_letters_nonce'); ?>
                        <input type="hidden" name="action" value="wpgt_sync_letters">
                        <input type="submit" class="button button-secondary" value="⟳ <?php esc_attr_e('Sync Letter Taxonomy','wp-glossary-tooltip'); ?>">
                    </form>
                    <?php if (isset($_GET['wpgt_synced'])) : ?>
                    <div class="wpgt-notice wpgt-notice-success" style="margin-top:14px;">
                        <?php printf(esc_html__('Done — %d terms synced, %d slug(s) repaired.','wp-glossary-tooltip'),(int)$_GET['wpgt_synced'],(int)($_GET['wpgt_fixed']??0)); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="wpgt-panel-card">
                    <h3><?php esc_html_e('Declined Forms','wp-glossary-tooltip'); ?></h3>
                    <p style="font-size:0.82rem;color:#555;margin:0 0 10px;"><?php esc_html_e('Regenerate all declined forms for every term. Useful after bulk import.','wp-glossary-tooltip'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('wpgt_regen_forms','wpgt_regen_nonce'); ?>
                        <input type="hidden" name="action" value="wpgt_regen_forms">
                        <input type="submit" class="button button-secondary" value="⟳ <?php esc_attr_e('Regenerate All Declined Forms','wp-glossary-tooltip'); ?>">
                    </form>
                    <?php if (isset($_GET['wpgt_regenok'])) : ?>
                    <div class="wpgt-notice wpgt-notice-success" style="margin-top:14px;">
                        <?php printf(esc_html__('Done — declined forms regenerated for %d terms.','wp-glossary-tooltip'),(int)$_GET['wpgt_regenok']); ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div><!-- /.wpgt-panel-body -->
        </div>

        <!-- STYLES ──────────────────────────────────────────────── -->
        <div id="wpgt-panel-styles" class="wpgt-tab-panel">
            <?php self::render_styles_tab(); ?>
        </div>

        </div><!-- /.wpgt-settings-wrap -->
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

        <?php
        // ─────────────────────────────────────────────────────────────────
        // Helpers
        // ─────────────────────────────────────────────────────────────────

        // ONE field (label on top, control below)
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
                case 'slider':
                    $sl_min     = $opts['min']      ?? 0;
                    $sl_max     = $opts['max']       ?? 1920;
                    $sl_units   = $opts['units']     ?? ['px'];
                    $sl_ukey    = $opts['unit_key']  ?? '';
                    $sl_uval    = $opts['unit_val']  ?? 'px';
                    echo '<div class="wpgt-field-slider-wrap" style="width:100%">';
                    echo '<input type="range" class="wpgt-slider-range" min="' . (int)$sl_min . '" max="' . (int)$sl_max . '" value="' . esc_attr($value) . '" data-linked="' . esc_attr($id) . '" />';
                    echo '<div class="wpgt-slider-input-row">';
                    echo '<input type="number" ' . $attr . ' value="' . esc_attr($value) . '" min="' . (int)$sl_min . '" max="' . (int)$sl_max . '" class="wpgt-slider-number" />';
                    if ( count($sl_units) > 1 && $sl_ukey ) {
                        $ua = 'id="wpgt_s_' . esc_attr($sl_ukey) . '" name="wpgt_styles[' . esc_attr($sl_ukey) . ']" data-key="' . esc_attr($sl_ukey) . '"';
                        echo '<div class="wpgt-unit-toggle">';
                        foreach ( $sl_units as $u ) {
                            $active = $sl_uval === $u ? ' wpgt-unit-btn--active' : '';
                            echo '<button type="button" class="wpgt-unit-btn' . $active . '" data-unit="' . esc_attr($u) . '" data-target="wpgt_s_' . esc_attr($sl_ukey) . '" data-range-max-px="' . (int)$sl_max . '" data-range-max-pct="100">' . esc_html($u) . '</button>';
                        }
                        echo '<input type="hidden" ' . $ua . ' value="' . esc_attr($sl_uval) . '" class="wpgt-unit-hidden" />';
                        echo '</div>';
                    } else {
                        echo '<span class="wpgt-slider-unit">' . esc_html($sl_units[0] ?? 'px') . '</span>';
                    }
                    echo '</div></div>';
                    break;
                case 'float':
                    echo '<div class="wpgt-field-num-wrap"><input type="number" step="0.01" min="0" max="10" ' . $attr . ' value="' . esc_attr($value) . '" /><span>' . esc_html($unit) . '</span></div>';
                    break;
                case 'select':
                    echo '<select ' . $attr . '>';
                    foreach ( $opts as $v => $l ) echo '<option value="' . esc_attr($v) . '"' . selected($value, $v, false) . '>' . esc_html($l) . '</option>';
                    echo '</select>';
                    break;
                case 'text':
                    echo '<input type="text" ' . $attr . ' value="' . esc_attr($value) . '" class="wpgt-field-text" />';
                    break;
            }
            echo '</div>';
        };

        // Group label inside a card
        $group = function( string $label, string $css_class = '' ) {
            echo '<div class="wpgt-group-label">' . esc_html($label);
            if ( $css_class ) echo ' <code class="wpgt-css-badge wpgt-css-badge--sm">' . esc_html($css_class) . '</code>';
            echo '</div>';
        };

        // 2-col grid open/close
        $go = function() { echo '<div class="wpgt-field-grid">'; };
        $gc = function() { echo '</div>'; };

        // Card with inline preview:
        // $pv_html is echoed on the RIGHT side of the card
        $card = function( string $icon, string $title, string $css_class, callable $fields_fn, string $pv_html ) {
            global $wpgt_previews;
            static $card_idx = 0;
            $card_id  = 'wpgt-card-' . $card_idx++;
            $is_first = ( $card_id === 'wpgt-card-0' );
            echo '<div class="wpgt-card' . ( $is_first ? ' wpgt-open' : '' ) . '" data-card="' . esc_attr($card_id) . '">';
            echo '<div class="wpgt-card-head">'
                . '<span class="wpgt-card-icon">' . $icon . '</span>'
                . '<span class="wpgt-card-title">' . esc_html($title) . '</span>'
                . '<code class="wpgt-css-badge">' . esc_html($css_class) . '</code>'
                . '</div>';
            echo '<div class="wpgt-card-inner">';
            echo '<div class="wpgt-card-fields">';
            $fields_fn();
            echo '</div>';
            echo '</div></div>'; // inner + card
            // Collect preview HTML for the right pane
            $wpgt_previews[ $card_id ] = '<div class="wpgt-pv-block" data-card="' . esc_attr($card_id) . '" style="' . ( $is_first ? '' : 'display:none;' ) . '">'
                . '<p class="wpgt-pv-eyebrow">' . esc_html($title) . '</p>'
                . $pv_html
                . '</div>';
        };

        $weights    = ['400'=>'Normal','500'=>'Medium','600'=>'Semi-bold','700'=>'Bold'];
        $transforms = [''=>'None','uppercase'=>'UPPER','lowercase'=>'lower','capitalize'=>'Title'];
        $tip_shad   = ['none'=>'None','sm'=>'Subtle','default'=>'Default','lg'=>'Strong'];
        $card_shad  = ['none'=>'None','sm'=>'Subtle','md'=>'Medium','lg'=>'Strong'];
        $sr_shad    = ['none'=>'None','sm'=>'Subtle','default'=>'Default','lg'=>'Strong'];
        $aligns     = ['left'=>'Left','center'=>'Center','right'=>'Right'];
        $justifys   = ['flex-start'=>'Left','center'=>'Center','flex-end'=>'Right','space-between'=>'Spread'];
        $bstyles    = ['solid'=>'Solid','dashed'=>'Dashed','dotted'=>'Dotted','none'=>'None'];

        // ── Two-column panel: LEFT = accordion cards, RIGHT = live previews ──
        echo '<div class="wpgt-styles-panel" id="wpgt-styles-panel">';

        // ── Columns row ──────────────────────────────────────────────────
        echo '<div class="wpgt-panel-cols">';
        echo '<div class="wpgt-panel-left">';
        echo '<div class="wpgt-panel-left-inner" id="wpgt-panel-left-inner">';

        // ══════════════════════════════════════════════════════════════════
        // CARD 0: Global Font
        // ══════════════════════════════════════════════════════════════════
        $popular_fonts = [
            ''                  => '— Site Default (inherit) —',
            'Roboto'            => 'Roboto',
            'Open Sans'         => 'Open Sans',
            'Lato'              => 'Lato',
            'Poppins'           => 'Poppins',
            'Montserrat'        => 'Montserrat',
            'Raleway'           => 'Raleway',
            'Nunito'            => 'Nunito',
            'Inter'             => 'Inter',
            'Playfair Display'  => 'Playfair Display',
            'Merriweather'      => 'Merriweather',
            'Source Sans 3'     => 'Source Sans 3',
            'Oswald'            => 'Oswald',
            'Noto Sans Georgian'=> 'Noto Sans Georgian',
            'Noto Serif Georgian'=> 'Noto Serif Georgian',
            'BPG Arial'         => 'BPG Arial',
            'FiraGO'            => 'FiraGO',
        ];
        $current_font = $s['global_font_family'] ?? '';
        $current_custom = ! isset( $popular_fonts[ $current_font ] ) && $current_font !== '' ? $current_font : '';

        // Build preview sentence with font applied inline
        $pv_font_style = $current_font ? 'font-family:"' . esc_attr($current_font) . '",sans-serif;' : '';
        $card( '🔠', 'Global Font', 'font-family',
            function() use ( $popular_fonts, $current_font, $current_custom, $s ) {
                ?>
                <p style="font-size:0.75rem;color:var(--el-text-muted,#7a8799);margin:0 0 12px;line-height:1.5;padding:10px 16px 0;">
                    Choose a font for all glossary elements — tooltip, index, term box, search widget.<br>
                    Leave blank to inherit the site's own font automatically. <strong style="color:var(--el-text,#cfd3d8);">This fixes font mismatch on tooltips.</strong>
                </p>
                <div style="padding:0 0 6px;">
                    <div class="wpgt-field wpgt-field--full">
                        <label for="wpgt_s_global_font_family">Font</label>
                        <select id="wpgt_s_global_font_family"
                                name="wpgt_styles[global_font_family]"
                                data-key="global_font_family">
                            <?php foreach ( $popular_fonts as $val => $label ) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected( $val, $current_font && ! $current_custom ? $current_font : ( $current_custom ? '__custom__' : $current_font ) ); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="__custom__" <?php selected( (bool) $current_custom, true ); ?>>Custom font name…</option>
                        </select>
                    </div>
                    <div class="wpgt-field wpgt-field--full" id="wpgt-font-custom-wrap" style="<?php echo $current_custom ? '' : 'display:none;'; ?>">
                        <label for="wpgt_s_global_font_custom">Custom Font Name (Google Fonts)</label>
                        <input type="text"
                               id="wpgt_s_global_font_custom"
                               name="wpgt_styles[global_font_custom]"
                               data-key="global_font_custom"
                               value="<?php echo esc_attr( $s['global_font_custom'] ?? '' ); ?>"
                               placeholder="e.g. Noto Serif Georgian"
                               class="wpgt-field-text" />
                        <span style="font-size:0.7rem;color:var(--el-text-muted,#7a8799);padding:0 16px 8px;display:block;">
                            Enter any <a href="https://fonts.google.com" target="_blank" style="color:var(--el-accent,#6a8dff);">Google Fonts</a> family name exactly as listed.
                        </span>
                    </div>
                </div>
                <?php
            },
            '<div id="wpgt-pv-font-sample" style="' . esc_attr($pv_font_style) . 'padding:10px 0;">
                <p style="margin:0 0 6px;font-size:0.78rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Preview</p>
                <p style="margin:0 0 4px;font-size:1rem;font-weight:700;color:#1e293b;">Yoga Practice — ყოველდღიური</p>
                <p style="margin:0 0 8px;font-size:0.875rem;color:#374151;line-height:1.55;">The body and mind united through breath — სული და სხეული.</p>
                <a style="font-size:0.8125rem;color:#2563eb;text-decoration:none;">Read more →</a>
            </div>'
        );

        // ══════════════════════════════════════════════════════════════════
        // CARD 1: Trigger Word
        // ══════════════════════════════════════════════════════════════════
        $card( '🔤', 'Trigger Word', '.wpgt-tooltip-trigger',
            function() use ( $field, $group, $go, $gc, $s, $weights, $transforms ) {
                $group('Colors');
                $go();
                    $field('Underline Color', 'trigger_color',       $s['trigger_color'],       'color');
                    $field('Hover Color',     'trigger_hover_color', $s['trigger_hover_color'], 'color');
                $gc();
                $group('Underline');
                $go();
                    $field('Style', 'trigger_underline_style', $s['trigger_underline_style'], 'select', ['dashed'=>'Dashed','solid'=>'Solid','dotted'=>'Dotted','none'=>'None']);
                    $field('Width', 'trigger_underline_width', $s['trigger_underline_width'], 'number', [], 'px');
                $gc();
                $group('Typography');
                $go();
                    $field('Weight',    'trigger_font_weight',    $s['trigger_font_weight'],    'select', $weights);
                    $field('Size',      'trigger_font_size',      $s['trigger_font_size'],      'number', [], 'px');
                    $field('Transform', 'trigger_text_transform', $s['trigger_text_transform'], 'select', $transforms, '', true);
                $gc();
            },
            '<p class="wpgt-pv-sentence">A sentence with a
                <span id="wpgt-pv-trigger" style="border-bottom:2px dashed #2563eb;cursor:help;color:inherit;">glossary term</span>
            in it.</p>'
        );

        // ══════════════════════════════════════════════════════════════════
        // CARD 2: Tooltip Bubble
        // ══════════════════════════════════════════════════════════════════
        $rmt = esc_html( $s['tooltip_read_more_text'] ?: 'Read more →' );
        $card( '💬', 'Tooltip Bubble', '.wpgt-tooltip-bubble',
            function() use ( $field, $group, $go, $gc, $s, $weights, $tip_shad ) {
                $group('Colors');
                $go();
                    $field('Background',  'tooltip_bg',           $s['tooltip_bg'],           'color');
                    $field('Text',        'tooltip_text_color',   $s['tooltip_text_color'],   'color');
                    $field('Title',       'tooltip_title_color',  $s['tooltip_title_color'],  'color');
                    $field('"Read More"', 'tooltip_link_color',   $s['tooltip_link_color'],   'color');
                    $field('Border',      'tooltip_border_color', $s['tooltip_border_color'], 'color');
                $gc();
                $group('Content');
                $go();
                    $field('"Read More" Text', 'tooltip_read_more_text', $s['tooltip_read_more_text'], 'text', [], '', true);
                $gc();
                $group('Size & Shape');
                $go();
                    $field('Font Size',    'tooltip_font_size',     $s['tooltip_font_size'],     'number', [], 'px');
                    $field('Line Height',  'tooltip_line_height',   $s['tooltip_line_height'],   'float', [], '');
                    $field('Padding V',    'tooltip_padding_v',     $s['tooltip_padding_v'],     'number', [], 'px');
                    $field('Padding H',    'tooltip_padding_h',     $s['tooltip_padding_h'],     'number', [], 'px');
                    $field('Border Width', 'tooltip_border_width',  $s['tooltip_border_width'],  'number', [], 'px');
                    $field('Radius',       'tooltip_border_radius', $s['tooltip_border_radius'], 'number', [], 'px');
                    $field('Max Width',    'tooltip_max_width',     $s['tooltip_max_width'],     'number', [], 'px');
                    $field('Shadow',       'tooltip_shadow',        $s['tooltip_shadow'],        'select', $tip_shad, '', true);
                $gc();
            },
            '<p class="wpgt-pv-sentence" style="margin-bottom:10px;">A sentence with a
                <span style="border-bottom:2px dashed #2563eb;cursor:help;">glossary term</span>
            in it.</p>
            <div id="wpgt-pv-tip" style="display:inline-block;padding:12px 14px;border-radius:6px;width:100%;box-sizing:border-box;box-shadow:0 8px 24px rgba(0,0,0,.18);background:#1e293b;color:#f1f5f9;border:1px solid rgba(255,255,255,.08);">
                <strong id="wpgt-pv-title" style="display:block;margin-bottom:5px;font-size:0.9rem;color:#fff;line-height:1.3;">Sample Term</strong>
                <span id="wpgt-pv-text" style="display:block;font-size:0.8rem;margin-bottom:6px;line-height:1.55;color:#f1f5f9;">Short definition text for the tooltip.</span>
                <a id="wpgt-pv-more" href="#" onclick="return false;" style="font-size:0.78rem;font-weight:500;color:#93c5fd;text-decoration:none;">' . $rmt . '</a>
            </div>'
        );

        // ══════════════════════════════════════════════════════════════════
        // CARD 3: [wpgt_glossary] Index
        // ══════════════════════════════════════════════════════════════════
        $card( '📋', '[wpgt_glossary] Index', '.wpgt-glossary-index',
            function() use ( $field, $group, $go, $gc, $s, $weights, $transforms, $aligns, $justifys, $bstyles, $card_shad ) {
                $group('A–Z Bar', '.wpgt-alphabet-bar');
                $go();
                    $field('Background',   'az_bar_bg',           $s['az_bar_bg'],           'color');
                    $field('Border Color', 'az_bar_border_color', $s['az_bar_border_color'], 'color');
                    $field('Alignment',    'az_bar_justify',      $s['az_bar_justify'],      'select', $justifys);
                    $field('Radius',       'az_bar_radius',       $s['az_bar_radius'],       'number', [], 'px');
                    $field('Padding V',    'az_bar_padding_v',    $s['az_bar_padding_v'],    'number', [], 'px');
                    $field('Padding H',    'az_bar_padding_h',    $s['az_bar_padding_h'],    'number', [], 'px');
                $gc();
                $group('Letter Links', '.wpgt-az-link');
                $go();
                    $field('Color',       'az_link_color',       $s['az_link_color'],       'color');
                    $field('Hover BG',    'az_link_hover_bg',    $s['az_link_hover_bg'],    'color');
                    $field('Hover Color', 'az_link_hover_color', $s['az_link_hover_color'], 'color');
                    $field('Radius',      'az_link_radius',      $s['az_link_radius'],      'number', [], 'px');
                    $field('Font Size',   'az_link_size',        $s['az_link_size'],        'number', [], 'px');
                    $field('Font Weight', 'az_link_weight',      $s['az_link_weight'],      'select', $weights);
                $gc();
                $group('Active Letter', '.wpgt-az-current');
                $go();
                    $field('Background',   'az_current_bg',            $s['az_current_bg'],            'color');
                    $field('Color',        'az_current_color',         $s['az_current_color'],         'color');
                    $field('Hover BG',     'az_current_hover_bg',      $s['az_current_hover_bg'],      'color');
                    $field('Hover Color',  'az_current_hover_color',   $s['az_current_hover_color'],   'color');
                    $field('Border Color', 'az_current_border_color',  $s['az_current_border_color'],  'color');
                    $field('Border Width', 'az_current_border_width',  $s['az_current_border_width'],  'number', [], 'px');
                    $field('Border Style', 'az_current_border_style',  $s['az_current_border_style'],  'select', $bstyles);
                    $field('Border Radius','az_current_border_radius', $s['az_current_border_radius'], 'number', [], 'px');
                $gc();
                $group('Letter Headings', '.wpgt-letter-heading');
                $go();
                    $field('Color',         'letter_heading_color',         $s['letter_heading_color'],         'color');
                    $field('Border Color',  'letter_heading_border',        $s['letter_heading_border'],        'color');
                    $field('Border Top',    'letter_heading_border_top',    $s['letter_heading_border_top'],    'number', [], 'px');
                    $field('Border Bottom', 'letter_heading_border_bottom', $s['letter_heading_border_bottom'], 'number', [], 'px');
                    $field('Border Style',  'letter_heading_border_style',  $s['letter_heading_border_style'],  'select', $bstyles);
                    $field('Font Size',     'letter_heading_size',          $s['letter_heading_size'],          'number', [], 'px');
                    $field('Font Weight',   'letter_heading_weight',        $s['letter_heading_weight'],        'select', $weights);
                    $field('Transform',     'letter_heading_transform',     $s['letter_heading_transform'],     'select', $transforms);
                    $field('Text Align',    'letter_heading_align',         $s['letter_heading_align'],         'select', $aligns);
                    $field('Display',       'letter_heading_display',       $s['letter_heading_display'],       'select', ['inline-block'=>'Inline','block'=>'Block']);
                    $field('Full Width',    'letter_heading_full_width',    $s['letter_heading_full_width'],    'select', ['0'=>'Auto','1'=>'100%']);
                    $field('Margin Bottom', 'letter_heading_mb',            $s['letter_heading_mb'],            'number', [], 'px');
                $gc();
                $group('Term Cards', '.wpgt-term-item');
                $go();
                    $field('Background',   'term_card_bg',          $s['term_card_bg'],          'color');
                    $field('Border',       'term_card_border',      $s['term_card_border'],      'color');
                    $field('Hover Border', 'term_card_hover_border',$s['term_card_hover_border'],'color');
                    $field('Radius',       'term_card_radius',      $s['term_card_radius'],      'number', [], 'px');
                    $field('Shadow',       'term_card_shadow',      $s['term_card_shadow'],      'select', $card_shad);
                    $field('Padding V',    'term_card_padding_v',   $s['term_card_padding_v'],   'number', [], 'px');
                    $field('Padding H',    'term_card_padding_h',   $s['term_card_padding_h'],   'number', [], 'px');
                $gc();
                $group('Term Name', '.wpgt-term-link');
                $go();
                    $field('Color',  'term_link_color',  $s['term_link_color'],  'color');
                    $field('Size',   'term_link_size',   $s['term_link_size'],   'number', [], 'px');
                    $field('Weight', 'term_link_weight', $s['term_link_weight'], 'select', $weights, '', true);
                $gc();
                $group('Excerpt', '.wpgt-term-excerpt');
                $go();
                    $field('Color', 'term_excerpt_color', $s['term_excerpt_color'], 'color');
                    $field('Size',  'term_excerpt_size',  $s['term_excerpt_size'],  'number', [], 'px');
                $gc();
            },
            '<nav id="wpgt-pv-az" style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:10px;padding:7px 9px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;">
                ' . implode('', array_map( fn($l) => '<span class="wpgt-pv-az-link" style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:4px;font-weight:600;font-size:0.78rem;color:#2563eb;cursor:pointer;">' . esc_html($l) . '</span>', ['ა','ბ'] )) . '
                <span id="wpgt-pv-az-current" style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:4px;font-weight:600;font-size:0.78rem;color:#94a3b8;border:1px solid #e2e8f0;cursor:default;">გ</span>
                ' . implode('', array_map( fn($l) => '<span class="wpgt-pv-az-link" style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:4px;font-weight:600;font-size:0.78rem;color:#2563eb;cursor:pointer;">' . esc_html($l) . '</span>', ['დ','ე','ვ','ზ'] )) . '
                <span style="font-size:0.6rem;color:#94a3b8;align-self:center;margin-left:4px;">← hover links</span>
            </nav>
            <h3 id="wpgt-pv-letter" style="font-size:1.3rem;font-weight:700;color:#2563eb;margin:0 0 8px;padding-bottom:3px;border-bottom:2px solid #2563eb;display:inline-block;">ა</h3>
            <div id="wpgt-pv-card" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px 12px;cursor:pointer;">
                <a id="wpgt-pv-term-link" href="#" onclick="return false;" style="display:block;font-weight:600;font-size:0.875rem;text-decoration:none;color:#2563eb;margin-bottom:3px;">სტრესი</a>
                <p id="wpgt-pv-excerpt" style="margin:0;font-size:0.775rem;color:#64748b;line-height:1.4;">A state of mental or emotional strain.</p>
            </div>
            <p style="font-size:0.65rem;color:#94a3b8;margin:5px 0 0;">Hover the letters and card to preview hover states</p>'
        );

        // ══════════════════════════════════════════════════════════════════
        // CARD 4: [wpgt_term] Term Box
        // ══════════════════════════════════════════════════════════════════
        $card( '📦', '[wpgt_term] — Term Box', '.wpgt-term-box',
            function() use ( $field, $group, $go, $gc, $s, $weights, $card_shad ) {
                $group('Colors');
                $go();
                    $field('Background', 'termbox_bg',           $s['termbox_bg'],           'color');
                    $field('Left Border','termbox_border_color',  $s['termbox_border_color'], 'color');
                $gc();
                $group('Title', '.wpgt-term-box__title');
                $go();
                    $field('Color',  'termbox_title_color',  $s['termbox_title_color'],  'color');
                    $field('Size',   'termbox_title_size',   $s['termbox_title_size'],   'number', [], 'px');
                    $field('Weight', 'termbox_title_weight', $s['termbox_title_weight'], 'select', $weights, '', true);
                $gc();
                $group('Definition', '.wpgt-term-box__definition');
                $go();
                    $field('Color', 'termbox_text_color', $s['termbox_text_color'], 'color');
                    $field('Size',  'termbox_text_size',  $s['termbox_text_size'],  'number', [], 'px');
                $gc();
                $group('Shape & Spacing');
                $go();
                    $field('Border Width', 'termbox_border_width', $s['termbox_border_width'], 'number', [], 'px');
                    $field('Radius',       'termbox_radius',       $s['termbox_radius'],       'number', [], 'px');
                    $field('Padding V',    'termbox_padding_v',    $s['termbox_padding_v'],    'number', [], 'px');
                    $field('Padding H',    'termbox_padding_h',    $s['termbox_padding_h'],    'number', [], 'px');
                    $field('Shadow',       'termbox_shadow',       $s['termbox_shadow'],       'select', $card_shad, '', true);
                $gc();
            },
            '<div id="wpgt-pv-box" style="border-left:4px solid #2563eb;padding:14px 18px;background:#f8fafc;border-radius:0 6px 6px 0;">
                <h4 style="margin:0 0 7px;font-size:0.95rem;">
                    <a id="wpgt-pv-box-title" href="#" onclick="return false;" style="color:#2563eb;text-decoration:none;font-weight:600;">Term Title</a>
                </h4>
                <p id="wpgt-pv-box-def" style="margin:0;color:#374151;font-size:0.85rem;line-height:1.5;">Definition text shown in the [wpgt_term] shortcode output.</p>
            </div>'
        );

        // ══════════════════════════════════════════════════════════════════
        // CARD 5: [wpgt_search] Widget
        // ══════════════════════════════════════════════════════════════════
        $card( '🔍', '[wpgt_search] — Search Widget', '.wpgt-search-widget',
            function() use ( $field, $group, $go, $gc, $s, $sr_shad ) {

                $group('Widget', '.wpgt-search-widget');
                $go();
                    $field('Width', 'search_max_width', $s['search_max_width'], 'slider', [
                        'min'      => 0,
                        'max'      => 1920,
                        'units'    => ['px', '%'],
                        'unit_key' => 'search_max_width_unit',
                        'unit_val' => $s['search_max_width_unit'] ?? 'px',
                    ], '', true);
                    $field('Input Height', 'search_input_height', $s['search_input_height'] ?? '44', 'number', [], 'px');
                $gc();

                $group('Input', '.wpgt-search-input');
                $go();
                    $field('Background',  'search_input_bg',         $s['search_input_bg'],         'color');
                    $field('Text Color',  'search_input_text_color',  $s['search_input_text_color'],  'color');
                    $field('Border',      'search_input_border',      $s['search_input_border'],      'color');
                    $field('Focus Ring',  'search_input_focus',       $s['search_input_focus'],       'color');
                    $field('Radius',      'search_input_radius',      $s['search_input_radius'],      'number', [], 'px');
                    $field('Font Size',   'search_input_size',        $s['search_input_size'],        'number', [], 'px');
                    $field('Padding V',   'search_input_padding_v',   $s['search_input_padding_v'],   'number', [], 'px');
                    $field('Padding H',   'search_input_padding_h',   $s['search_input_padding_h'],   'number', [], 'px');
                $gc();

                $group('Icons', '.wpgt-search-icon / .wpgt-search-clear');
                $go();
                    $field('Search Icon Color', 'search_icon_color',  $s['search_icon_color'],  'color');
                    $field('Clear Button Color', 'search_clear_color', $s['search_clear_color'], 'color');
                $gc();

                $group('Results Dropdown', '.wpgt-search-results');
                $go();
                    $field('Background',  'search_results_bg',       $s['search_results_bg'],       'color');
                    $field('Max Height',  'search_results_max_height',$s['search_results_max_height'],'number', [], 'px');
                    $field('Radius',      'search_results_radius',    $s['search_results_radius'],    'number', [], 'px');
                    $field('Shadow',      'search_results_shadow',    $s['search_results_shadow'],    'select', $sr_shad);
                $gc();

                $group('Result Item', '.wpgt-search-result-item');
                $go();
                    $field('Text Color',     'search_result_text',       $s['search_result_text'],       'color');
                    $field('Separator',      'search_separator_color',   $s['search_separator_color'],   'color');
                    $field('Hover Bg',       'search_result_hover_bg',   $s['search_result_hover_bg'],   'color');
                    $field('Hover Color',    'search_result_hover_color',$s['search_result_hover_color'],'color');
                    $field('Title Color',    'search_result_title',      $s['search_result_title'],      'color');
                    $field('Excerpt Color',  'search_result_excerpt',    $s['search_result_excerpt'],    'color');
                    $field('Match Highlight','search_match_color',       $s['search_match_color'],       'color');
                    $field('No-results',     'search_noresults_color',   $s['search_noresults_color'],   'color');
                    $field('Padding V',      'search_result_padding_v',  $s['search_result_padding_v'],  'number', [], 'px');
                    $field('Padding H',      'search_result_padding_h',  $s['search_result_padding_h'],  'number', [], 'px');
                $gc();
            },

            /* Live preview — mirrors the real widget structure */
            '<div style="position:relative;">
              <div style="position:relative;display:flex;align-items:center;">
                <span id="wpgt-pv-search-icon" style="position:absolute;left:12px;color:#94a3b8;display:flex;pointer-events:none;z-index:1;">
                  <svg width="15" height="15" viewBox="0 0 20 20" fill="none"><circle cx="8.5" cy="8.5" r="6" stroke="currentColor" stroke-width="1.75"/><path d="M13.5 13.5L18 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                </span>
                <input id="wpgt-pv-search-input" type="text"
                  placeholder="Search glossary…"
                  style="width:100%;padding:8px 34px 8px 44px;font-size:0.85rem;border:1.5px solid #d1d5db;border-radius:6px;box-sizing:border-box;outline:none;background:#fff;color:#1e293b;border-bottom-left-radius:0;border-bottom-right-radius:0;border-bottom-color:transparent;" readonly />
                <span id="wpgt-pv-search-clear" style="position:absolute;right:10px;color:#94a3b8;display:flex;cursor:pointer;">
                  <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M1 1L11 11M11 1L1 11" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                </span>
              </div>
              <div id="wpgt-pv-search-results" style="background:#fff;border:1.5px solid #d1d5db;border-top:none;border-bottom-left-radius:6px;border-bottom-right-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.12);overflow:hidden;">
                <div id="wpgt-pv-result-active" style="padding:8px 12px;background:#f0f7ff;border-bottom:1px solid #f1f5f9;">
                  <span id="wpgt-pv-result-title" style="display:block;font-weight:600;font-size:0.83rem;color:#1e293b;"><mark id="wpgt-pv-result-match" style="background:transparent;font-weight:700;color:#2563eb;">სტრ</mark>ესი</span>
                  <span id="wpgt-pv-result-excerpt" style="display:block;font-size:0.75rem;color:#64748b;margin-top:1px;">A state of mental tension.</span>
                </div>
                <div style="padding:8px 12px;border-bottom:1px solid #f1f5f9;">
                  <span id="wpgt-pv-result-title2" style="display:block;font-weight:600;font-size:0.83rem;color:#1e293b;">მედიტაცია</span>
                  <span style="display:block;font-size:0.75rem;color:#64748b;margin-top:1px;">A practice of focused attention.</span>
                </div>
              </div>
            </div>'
        );
        ?>

        <!-- Save bar inside left panel -->
        <div class="wpgt-save-bar">
            <?php submit_button( __( 'Save Styles', 'wp-glossary-tooltip' ), 'primary', 'submit', false ); ?>
            <a href="<?php echo esc_url( wp_nonce_url(
                admin_url( 'admin-post.php?action=wpgt_save_styles&wpgt_reset_styles=1' ),
                'wpgt_styles_save', 'wpgt_styles_nonce'
            ) ); ?>"
               class="button button-secondary"
               onclick="return confirm('<?php esc_attr_e( 'Reset all styles to plugin defaults?', 'wp-glossary-tooltip' ); ?>')">
                <?php esc_html_e( 'Reset', 'wp-glossary-tooltip' ); ?>
            </a>
        </div>

        </div><!-- /.wpgt-panel-left-inner -->
        </div><!-- /.wpgt-panel-left -->

        <div class="wpgt-drag-handle" id="wpgt-drag-handle" title="Drag to resize"></div>

        <!-- RIGHT: live preview pane — collects previews from all cards -->
        <div class="wpgt-panel-right">
            <div class="wpgt-panel-right-head">Live Preview</div>
            <div class="wpgt-panel-right-body" id="wpgt-panel-right-body">
                <?php
                // Output all preview HTML collected by $card calls
                global $wpgt_previews;
                if ( ! empty( $wpgt_previews ) ) {
                    foreach ( $wpgt_previews as $pv ) { echo $pv; }
                }
                ?>
            </div>
        </div>

        </div><!-- /.wpgt-panel-cols -->
        </div><!-- /.wpgt-styles-panel -->

        </form>

        <script>
        jQuery(function($){

            // ── Inner tab bar: switch tabs from inside fullscreen panel ──
            $(document).on('click', '#wpgt-panel-styles .wpgt-inner-tab', function(e){
                e.preventDefault();
                var href = $(this).attr('href'); // e.g. "#wpgt-tab-general"
                // Fire click on the matching outer nav-tab
                var $outer = $('a.nav-tab[href="' + href + '"]');
                if ( $outer.length ) $outer.trigger('click');
            });

            // ── Accordion: click header to open/close card ───────────────
            $(document).on('click', '#wpgt-panel-styles .wpgt-card-head', function(){
                var $card   = $(this).closest('.wpgt-card');
                var cardId  = $card.data('card');
                var isOpen  = $card.hasClass('wpgt-open');

                // Close all, open clicked
                $('#wpgt-panel-styles .wpgt-card').removeClass('wpgt-open');
                if ( ! isOpen ) {
                    $card.addClass('wpgt-open');
                    // Show matching preview in right pane
                    $('#wpgt-panel-right-body .wpgt-pv-block').hide();
                    $('#wpgt-panel-right-body .wpgt-pv-block[data-card="' + cardId + '"]').show();
                } else {
                    // All closed: show first preview
                    $('#wpgt-panel-right-body .wpgt-pv-block').hide().first().show();
                }
            });

            // ── Drag-to-resize left panel ────────────────────────────────
            (function(){
                var $handle = $('#wpgt-drag-handle');
                var $left   = $handle.prev('.wpgt-panel-left');
                var $panel  = $('#wpgt-styles-panel');
                var dragging = false, startX, startW;

                $handle.on('mousedown', function(e){
                    dragging = true;
                    startX   = e.clientX;
                    startW   = $left.outerWidth();
                    $handle.addClass('wpgt-dragging');
                    // Overlay to capture mouse while dragging over iframe/preview
                    $('<div id="wpgt-drag-overlay">').css({
                        position:'fixed', top:0, left:0, right:0, bottom:0,
                        zIndex:99999, cursor:'col-resize'
                    }).appendTo('body');
                    e.preventDefault();
                });

                $(document).on('mousemove.wpgtdrag', function(e){
                    if ( ! dragging ) return;
                    var dx      = e.clientX - startX;
                    var newW    = Math.max(180, Math.min(startW + dx, $panel.outerWidth() * 0.7));
                    $left.css('flex', '0 0 ' + newW + 'px');
                });

                $(document).on('mouseup.wpgtdrag', function(){
                    if ( ! dragging ) return;
                    dragging = false;
                    $handle.removeClass('wpgt-dragging');
                    $('#wpgt-drag-overlay').remove();
                });
            })();

            // ═══════════════════════════════════════════════════════════════
            // HOVER STATE STORE — must be declared FIRST so it is assigned
            // before wpgtStylePreview() is called by anything below.
            // (var declarations are hoisted but their values are not.)
            // ═══════════════════════════════════════════════════════════════
            var hov = {
                triggerColor:        '#2563eb',
                triggerHoverColor:   '#2563eb',
                triggerUlStyle:      'dashed',
                azLinkColor:         '#2563eb',
                azLinkHoverBg:       '#2563eb',
                azLinkHoverColor:    '#fff',
                azCurrentBg:         'transparent',
                azCurrentColor:      '#94a3b8',
                azCurrentHoverBg:    '',
                azCurrentHoverColor: '',
                cardBorder:          '#e2e8f0',
                cardHoverBorder:     '#2563eb',
            };

            // ── Initialize color pickers — per-input closure so data-key is reliable ──
            // Bulk .wpColorPicker() loses the original `this` context in the callback;
            // closing over $input guarantees we always read data-key from the right element.
            $('#wpgt-panel-styles .wpgt-style-picker').each(function(){
                var $input = $(this);
                $input.wpColorPicker({
                    change: function(e, ui){ wpgtStylePreview($input.data('key'), ui.color.toString()); },
                    clear:  function()     { wpgtStylePreview($input.data('key'), ''); }
                });
            });

            // ── Apply saved values to all previews on page load ─────────
            <?php foreach ( $s as $key => $val ) : ?>
            wpgtStylePreview('<?php echo esc_js($key); ?>', '<?php echo esc_js((string)$val); ?>');
            <?php endforeach; ?>

            // ── Number / float / select / text inputs ───────────────────
            $('#wpgt-panel-styles').on('input change', 'input, select', function(){
                wpgtStylePreview($(this).data('key'), $(this).val());
            });

            // ── Hover: trigger word ─────────────────────────────────────
            $(document).on('mouseenter', '#wpgt-pv-trigger', function(){
                $(this).css({'color': hov.triggerHoverColor||hov.triggerColor, 'border-bottom-style': 'solid'});
            }).on('mouseleave', '#wpgt-pv-trigger', function(){
                $(this).css({'color': '', 'border-bottom-style': hov.triggerUlStyle||'dashed'});
            });

            // ── Hover: A–Z letter links ─────────────────────────────────
            $(document).on('mouseenter', '.wpgt-pv-az-link', function(){
                $(this).css({'background': hov.azLinkHoverBg, 'color': hov.azLinkHoverColor});
            }).on('mouseleave', '.wpgt-pv-az-link', function(){
                $(this).css({'background': '', 'color': hov.azLinkColor});
            });

            // ── Hover: active letter ────────────────────────────────────
            $(document).on('mouseenter', '#wpgt-pv-az-current', function(){
                if (hov.azCurrentHoverBg)    $(this).css('background', hov.azCurrentHoverBg);
                if (hov.azCurrentHoverColor) $(this).css('color', hov.azCurrentHoverColor);
            }).on('mouseleave', '#wpgt-pv-az-current', function(){
                $(this).css({'background': hov.azCurrentBg||'transparent', 'color': hov.azCurrentColor||'#94a3b8'});
            });

            // ── Hover: term card ────────────────────────────────────────
            $(document).on('mouseenter', '#wpgt-pv-card', function(){
                $(this).css('border-color', hov.cardHoverBorder);
            }).on('mouseleave', '#wpgt-pv-card', function(){
                $(this).css('border-color', hov.cardBorder);
            });

            // ── Preview update function ──────────────────────────────────
            function px(v, def){ return parseFloat(v) > 0 ? v+'px' : def; }

            function wpgtStylePreview(key, val) {
                if (!key) return;
                var tip  = $('#wpgt-pv-tip');
                var az   = $('#wpgt-pv-az');
                var cur  = $('#wpgt-pv-az-current');
                var card = $('#wpgt-pv-card');
                var box  = $('#wpgt-pv-box');
                var si   = $('#wpgt-pv-search-input');
                var sr   = $('#wpgt-pv-search-results');

                switch(key) {
                    // ── Global font ────────────────────────────────────
                    case 'global_font_family':
                    case 'global_font_custom':
                        var ff = val && val !== '__custom__' ? '"'+val+'",sans-serif' : '';
                        $('#wpgt-pv-font-sample').css('font-family', ff||'inherit');
                        $('#wpgt-pv-trigger,#wpgt-pv-tip,#wpgt-pv-az,#wpgt-pv-card,#wpgt-pv-box,#wpgt-pv-search-input,#wpgt-pv-search-results').css('font-family', ff||'inherit');
                        if (ff) {
                            var link = document.getElementById('wpgt-gfont-preview');
                            if (!link) { link=document.createElement('link'); link.rel='stylesheet'; link.id='wpgt-gfont-preview'; document.head.appendChild(link); }
                            link.href='https://fonts.googleapis.com/css2?family='+encodeURIComponent(val)+':wght@400;500;600;700&display=swap';
                        }
                        break;

                    // ── Trigger ────────────────────────────────────────
                    case 'trigger_color':
                        $('#wpgt-pv-trigger').css('border-bottom-color', val||'#2563eb');
                        hov.triggerColor = val||'#2563eb';
                        break;
                    case 'trigger_underline_style':
                        $('#wpgt-pv-trigger').css('border-bottom-style', val||'dashed');
                        hov.triggerUlStyle = val||'dashed';
                        break;
                    case 'trigger_underline_width': $('#wpgt-pv-trigger').css('border-bottom-width', px(val,'2px')); break;
                    case 'trigger_hover_color':
                        hov.triggerHoverColor = val||'#2563eb';
                        break;
                    case 'trigger_font_weight':    $('#wpgt-pv-trigger').css('font-weight', val||''); break;
                    case 'trigger_font_size':      $('#wpgt-pv-trigger').css('font-size', parseFloat(val)>0 ? val+'px' : ''); break;
                    case 'trigger_text_transform': $('#wpgt-pv-trigger').css('text-transform', val||'none'); break;

                    // ── Tooltip bubble ─────────────────────────────────
                    case 'tooltip_bg':             tip.css('background', val||'#1e293b'); break;
                    case 'tooltip_text_color':     tip.css('color', val||'#f1f5f9'); $('#wpgt-pv-text').css('color',val||''); break;
                    case 'tooltip_title_color':    $('#wpgt-pv-title').css('color', val||'#fff'); break;
                    case 'tooltip_link_color':     $('#wpgt-pv-more').css('color', val||'#93c5fd'); break;
                    case 'tooltip_read_more_text': $('#wpgt-pv-more').text(val||'Read more →'); break;
                    case 'tooltip_font_size':      tip.css('font-size', px(val,'')); break;
                    case 'tooltip_line_height':    tip.css('line-height', parseFloat(val)>0 ? val : '1.55'); break;
                    case 'tooltip_padding_v':      tip.css({'padding-top':px(val,'12px'),'padding-bottom':px(val,'10px')}); break;
                    case 'tooltip_padding_h':      tip.css({'padding-left':px(val,'14px'),'padding-right':px(val,'14px')}); break;
                    case 'tooltip_border_radius':  tip.css('border-radius', px(val,'6px')); break;
                    case 'tooltip_border_width':   tip.css('border-width', px(val,'1px')); break;
                    case 'tooltip_border_color':   tip.css('border-color', val||'rgba(255,255,255,.08)'); break;
                    case 'tooltip_max_width':      tip.css('max-width', parseFloat(val)>0 ? val+'px' : 'none'); break;
                    case 'tooltip_shadow':         { var ts={none:'none',sm:'0 1px 4px rgba(0,0,0,.10)',default:'0 8px 24px rgba(0,0,0,.18)',lg:'0 16px 40px rgba(0,0,0,.30)'}; tip.css('box-shadow',ts[val]||ts['default']); break; }

                    // ── A–Z Bar ────────────────────────────────────────
                    case 'az_bar_bg':           az.css('background', val||'#f8fafc'); break;
                    case 'az_bar_border_color': az.css('border-color', val||'#e2e8f0'); break;
                    case 'az_bar_radius':       az.css('border-radius', px(val,'6px')); break;
                    case 'az_bar_padding_v':    az.css({'padding-top':px(val,'7px'),'padding-bottom':px(val,'7px')}); break;
                    case 'az_bar_padding_h':    az.css({'padding-left':px(val,'9px'),'padding-right':px(val,'9px')}); break;
                    case 'az_bar_justify':      az.css('justify-content', val||'flex-start'); break;
                    case 'az_link_color':
                        az.find('.wpgt-pv-az-link').css('color', val||'#2563eb');
                        hov.azLinkColor = val||'#2563eb';
                        break;
                    case 'az_link_radius':
                        az.find('.wpgt-pv-az-link').css('border-radius', px(val,'4px'));
                        cur.css('border-radius', px(val,'4px'));
                        break;
                    case 'az_link_size':
                        az.find('.wpgt-pv-az-link').css('font-size', px(val,''));
                        cur.css('font-size', px(val,''));
                        break;
                    case 'az_link_weight':
                        az.find('.wpgt-pv-az-link').css('font-weight', val||'600');
                        cur.css('font-weight', val||'600');
                        break;
                    case 'az_link_hover_bg':
                        hov.azLinkHoverBg = val||'#2563eb';
                        break;
                    case 'az_link_hover_color':
                        hov.azLinkHoverColor = val||'#fff';
                        break;

                    // ── Active Letter (.wpgt-az-current) ──────────────
                    case 'az_current_bg':
                        cur.css('background', val||'transparent');
                        hov.azCurrentBg = val||'transparent';
                        break;
                    case 'az_current_color':
                        cur.css('color', val||'#94a3b8');
                        hov.azCurrentColor = val||'#94a3b8';
                        break;
                    case 'az_current_border_color':
                        cur.css('border-color', val||'#e2e8f0');
                        break;
                    case 'az_current_border_width':
                        cur.css('border-width', parseFloat(val)>0 ? val+'px' : '1px');
                        break;
                    case 'az_current_border_style':
                        cur.css('border-style', val||'solid');
                        break;
                    case 'az_current_border_radius':
                        cur.css('border-radius', px(val,'4px'));
                        break;
                    case 'az_current_hover_bg':
                        hov.azCurrentHoverBg = val||'';
                        break;
                    case 'az_current_hover_color':
                        hov.azCurrentHoverColor = val||'';
                        break;

                    // ── Letter heading ─────────────────────────────────
                    case 'letter_heading_color':         $('#wpgt-pv-letter').css('color', val||'#2563eb'); break;
                    case 'letter_heading_border':        $('#wpgt-pv-letter').css({'border-top-color':val,'border-bottom-color':val}); break;
                    case 'letter_heading_border_top':    $('#wpgt-pv-letter').css('border-top-width', parseFloat(val)>0 ? val+'px' : '0'); break;
                    case 'letter_heading_border_bottom': $('#wpgt-pv-letter').css('border-bottom-width', parseFloat(val)>0 ? val+'px' : '2px'); break;
                    case 'letter_heading_border_style':  $('#wpgt-pv-letter').css({'border-top-style':val||'solid','border-bottom-style':val||'solid'}); break;
                    case 'letter_heading_size':          $('#wpgt-pv-letter').css('font-size', px(val,'1.3rem')); break;
                    case 'letter_heading_weight':        $('#wpgt-pv-letter').css('font-weight', val||'700'); break;
                    case 'letter_heading_transform':     $('#wpgt-pv-letter').css('text-transform', val||'none'); break;
                    case 'letter_heading_mb':            $('#wpgt-pv-letter').css('margin-bottom', px(val,'8px')); break;
                    case 'letter_heading_align':         $('#wpgt-pv-letter').css('text-align', val||'left'); break;
                    case 'letter_heading_display':       $('#wpgt-pv-letter').css('display', val||'inline-block'); break;
                    case 'letter_heading_full_width':    $('#wpgt-pv-letter').css('width', val==='1' ? '100%' : 'auto'); break;

                    // ── Term cards ─────────────────────────────────────
                    case 'term_card_bg':
                        card.css('background', val||'#f8fafc');
                        break;
                    case 'term_card_border':
                        card.css('border-color', val||'#e2e8f0');
                        hov.cardBorder = val||'#e2e8f0';
                        break;
                    case 'term_card_hover_border':
                        hov.cardHoverBorder = val||'#2563eb';
                        break;
                    case 'term_card_radius':     card.css('border-radius', px(val,'6px')); break;
                    case 'term_card_shadow':     { var cs={none:'none',sm:'0 1px 3px rgba(0,0,0,.06)',md:'0 2px 8px rgba(0,0,0,.10)',lg:'0 8px 20px rgba(0,0,0,.14)'}; card.css('box-shadow',cs[val]||'none'); break; }
                    case 'term_card_padding_v':  card.css({'padding-top':px(val,'10px'),'padding-bottom':px(val,'10px')}); break;
                    case 'term_card_padding_h':  card.css({'padding-left':px(val,'12px'),'padding-right':px(val,'12px')}); break;
                    case 'term_link_color':      $('#wpgt-pv-term-link').css('color', val||'#2563eb'); break;
                    case 'term_link_size':       $('#wpgt-pv-term-link').css('font-size', px(val,'')); break;
                    case 'term_link_weight':     $('#wpgt-pv-term-link').css('font-weight', val||'600'); break;
                    case 'term_excerpt_color':   $('#wpgt-pv-excerpt').css('color', val||'#64748b'); break;
                    case 'term_excerpt_size':    $('#wpgt-pv-excerpt').css('font-size', px(val,'')); break;

                    // ── Term box ───────────────────────────────────────
                    case 'termbox_border_color': box.css('border-left-color', val||'#2563eb'); break;
                    case 'termbox_border_width': box.css('border-left-width', px(val,'4px')); break;
                    case 'termbox_bg':           box.css('background', val||'#f8fafc'); break;
                    case 'termbox_title_color':  $('#wpgt-pv-box-title').css('color', val||'#2563eb'); break;
                    case 'termbox_title_size':   $('#wpgt-pv-box-title').css('font-size', px(val,'')); break;
                    case 'termbox_title_weight': $('#wpgt-pv-box-title').css('font-weight', val||'600'); break;
                    case 'termbox_text_color':   $('#wpgt-pv-box-def').css('color', val||'#374151'); break;
                    case 'termbox_text_size':    $('#wpgt-pv-box-def').css('font-size', px(val,'')); break;
                    case 'termbox_radius':       { var r2=parseFloat(val)||0; box.css('border-radius', r2>0 ? '0 '+r2+'px '+r2+'px 0' : '0 6px 6px 0'); break; }
                    case 'termbox_padding_v':    box.css({'padding-top':px(val,'14px'),'padding-bottom':px(val,'14px')}); break;
                    case 'termbox_padding_h':    box.css({'padding-left':px(val,'18px'),'padding-right':px(val,'18px')}); break;
                    case 'termbox_shadow':       { var tb={none:'none',sm:'0 1px 3px rgba(0,0,0,.06)',md:'0 2px 8px rgba(0,0,0,.10)',lg:'0 8px 20px rgba(0,0,0,.14)'}; box.css('box-shadow',tb[val]||'none'); break; }

                    // ── Search ─────────────────────────────────────────
                    case 'search_input_bg':         si.css('background', val||'#fff'); break;
                    case 'search_input_text_color': si.css('color', val||'#1e293b'); break;
                    case 'search_input_border':    si.css('border-color', val||'#d1d5db'); $('#wpgt-pv-search-results').css('border-color', val||'#d1d5db'); break;
                    case 'search_input_focus':     break; // can't preview :focus without clicking
                    case 'search_input_radius':    si.css('border-radius', px(val,'6px')+' '+px(val,'6px')+' 0 0'); sr.css('border-radius','0 0 '+px(val,'6px')+' '+px(val,'6px')); break;
                    case 'search_input_size':      si.css('font-size', px(val,'')); break;
                    case 'search_input_padding_v': si.css({'padding-top':px(val,'8px'),'padding-bottom':px(val,'8px')}); break;
                    case 'search_input_padding_h': si.css({'padding-left':px(val,'36px'),'padding-right':px(val,'34px')}); break;
                    case 'search_icon_color':      $('#wpgt-pv-search-icon').css('color', val||'#94a3b8'); break;
                    case 'search_clear_color':     $('#wpgt-pv-search-clear').css('color', val||'#94a3b8'); break;
                    case 'search_results_bg':      sr.css('background', val||'#fff'); break;
                    case 'search_results_max_height': sr.css('max-height', val ? val+'px' : ''); break;
                    case 'search_results_radius':  si.css('border-radius', '6px 6px 0 0'); sr.css('border-radius','0 0 '+px(val,'6px')+' '+px(val,'6px')); break;
                    case 'search_results_shadow':  { var srs={none:'none',sm:'0 1px 3px rgba(0,0,0,.06)',default:'0 4px 16px rgba(0,0,0,.12)',lg:'0 8px 24px rgba(0,0,0,.18)'}; sr.css('box-shadow',srs[val]||srs['default']); break; }
                    case 'search_result_text':     sr.find('span[style*="color:#1e293b"]').css('color', val||'#1e293b'); $('#wpgt-pv-result-title2').css('color', val||'#1e293b'); break;
                    case 'search_result_hover_bg': $('#wpgt-pv-result-active').css('background', val||'#f0f7ff'); break;
                    case 'search_result_hover_color': $('#wpgt-pv-result-title').css('color', val ? val : ($('#wpgt-pv-result-title').data('base-color')||'#1e293b')); break;
                    case 'search_separator_color': sr.find('div').css('border-bottom-color', val||'#f1f5f9'); break;
                    case 'search_result_title':    $('#wpgt-pv-result-title').css('color', val||'#1e293b'); break;
                    case 'search_result_excerpt':  $('#wpgt-pv-result-excerpt').css('color', val||'#64748b'); break;
                    case 'search_match_color':     $('#wpgt-pv-result-match').css('color', val||'#2563eb'); break;
                    case 'search_noresults_color': break; // no-results state not shown in preview
                    case 'search_result_padding_v':sr.find('div').css({'padding-top':px(val,'8px'),'padding-bottom':px(val,'8px')}); break;
                    case 'search_result_padding_h':sr.find('div').css({'padding-left':px(val,'12px'),'padding-right':px(val,'12px')}); break;
                    case 'search_max_width':       break; // width not constrained in admin card preview
                    case 'search_max_width_unit':  break;
                    case 'search_input_height':    si.css({'min-height':val?val+'px':'','padding-top':val?Math.max(0,Math.round((parseInt(val)-24)/2))+'px':'','padding-bottom':val?Math.max(0,Math.round((parseInt(val)-24)/2))+'px':''}); break;
                }
            }

            // Expose globally so external script blocks can call it
            window.wpgtStylePreview = wpgtStylePreview;

            // ── Slider range ↔ number input sync ────────────────────────
            $('#wpgt-panel-styles').on('input', '.wpgt-slider-range', function(){
                var $r = $(this), $n = $('#' + $r.data('linked'));
                $n.val($r.val());
                wpgtStylePreview($n.data('key'), $r.val());
            });
            $('#wpgt-panel-styles').on('input', '.wpgt-slider-number', function(){
                var $n = $(this), $r = $n.closest('.wpgt-field-slider-wrap').find('.wpgt-slider-range');
                $r.val($n.val());
                wpgtStylePreview($n.data('key'), $n.val());
            });

            // ── Unit toggle buttons ──────────────────────────────────────
            $('#wpgt-panel-styles').on('click', '.wpgt-unit-btn', function(){
                var $btn = $(this), unit = $btn.data('unit'), target = $btn.data('target');
                var maxPx = parseInt($btn.data('range-max-px')) || 1920;
                var maxPct= parseInt($btn.data('range-max-pct')) || 100;
                $btn.closest('.wpgt-unit-toggle').find('.wpgt-unit-btn').removeClass('wpgt-unit-btn--active');
                $btn.addClass('wpgt-unit-btn--active');
                $('#' + target).val(unit);
                var $wrap = $btn.closest('.wpgt-field-slider-wrap');
                var $r = $wrap.find('.wpgt-slider-range'), $n = $wrap.find('.wpgt-slider-number');
                var newMax = unit === '%' ? maxPct : maxPx;
                $r.attr('max', newMax); $n.attr('max', newMax);
                var cur = parseInt($n.val()) || 0;
                if (cur > newMax) { cur = newMax; $n.val(cur); $r.val(cur); }
                wpgtStylePreview($n.data('key'), $n.val());
                wpgtStylePreview($('#' + target).data('key'), unit);
            });

            // ── Font card: show/hide custom input ───────────────────────
            $('#wpgt_s_global_font_family').on('change', function(){
                if ( $(this).val() === '__custom__' ) {
                    $('#wpgt-font-custom-wrap').show();
                } else {
                    $('#wpgt-font-custom-wrap').hide();
                }
            });

        });
        </script>
        <style>
        /* ═══════════════════════════════════════════════════════
           ELEMENTOR WHITE THEME — CSS variables
           Matches Elementor's actual light panel palette
        ═══════════════════════════════════════════════════════ */
        #wpgt-panel-styles {
            --el-bg:          #f0f0f1;
            --el-panel-bg:    #ffffff;
            --el-left-bg:     #ffffff;
            --el-border:      #e0e0e0;
            --el-border2:     #f0f0f1;
            --el-text:        #1e1e1e;
            --el-text-muted:  #8a8a8a;
            --el-text-head:   #1e1e1e;
            --el-accent:      #4054b2;
            --el-accent2:     #4054b2;
            --el-input-bg:    #f9f9f9;
            --el-input-bd:    #d5d5d5;
            --el-input-focus: #4054b2;
            --el-head-bg:     #fafafa;
            --el-head-border: #e0e0e0;
            --el-section-bg:  #f7f7f7;
            --el-hover-bg:    #eef0fb;
            --el-active-bg:   #eef0fb;
            --el-active-left: #4054b2;
        }

        /* ════════════════════════════════════════════════════
           STYLES PANEL — fills its fixed parent #wpgt-panel-styles
        ════════════════════════════════════════════════════ */
        #wpgt-panel-styles .wpgt-styles-panel {
            display: flex;
            flex-direction: column;
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--el-bg);
            overflow: hidden;
        }

        /* ── Inner tab bar — HIDDEN (outer topbar handles navigation) ── */
        #wpgt-panel-styles .wpgt-inner-tabbar { display: none; }

        /* ── Columns row ─────────────────────────────────── */
        #wpgt-panel-styles .wpgt-panel-cols,
        #wpgt-panel-styles .wpgt-panel-cols { display: flex; flex: 1; min-height: 0; }

        /* ── Left panel ──────────────────────────────────── */
        #wpgt-panel-styles .wpgt-panel-left {
            flex: 0 0 280px;
            min-width: 200px;
            max-width: 55%;
            background: var(--el-left-bg);
            border-right: 1px solid var(--el-border);
            display: flex;
            flex-direction: column;
            overflow: visible;
        }
        #wpgt-panel-styles .wpgt-panel-left-inner {
            overflow-y: auto;
            flex: 1;
        }

        /* ── Drag handle ─────────────────────────────────── */
        #wpgt-panel-styles .wpgt-drag-handle {
            flex: 0 0 4px;
            background: var(--el-border);
            cursor: col-resize;
            position: relative;
            z-index: 10;
            transition: background .15s;
        }
        #wpgt-panel-styles .wpgt-drag-handle:hover,
        #wpgt-panel-styles .wpgt-drag-handle.wpgt-dragging { background: var(--el-accent); }
        #wpgt-panel-styles .wpgt-drag-handle::after {
            content: "⋮";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%,-50%);
            color: #bbb;
            font-size: 13px;
            line-height: 1;
            pointer-events: none;
        }

        /* ── Right panel (live preview) ──────────────────── */
        #wpgt-panel-styles .wpgt-panel-right {
            flex: 1;
            min-width: 200px;
            background: #f7f7f7;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        #wpgt-panel-styles .wpgt-panel-right-head {
            padding: 11px 20px;
            border-bottom: 1px solid var(--el-border);
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: #aaa;
            background: #fff;
            flex-shrink: 0;
        }
        #wpgt-panel-styles .wpgt-panel-right-body {
            padding: 24px;
            flex: 1;
            overflow-y: auto;
        }

        /* ── Color picker — fixed so it escapes overflow:hidden ── */
        #wpgt-panel-styles .wp-picker-container {
            position: relative;
            z-index: 200;
        }
        #wpgt-panel-styles .wp-picker-container .wp-picker-holder {
            position: fixed !important;
            z-index: 999999 !important;
        }
        .iris-picker { z-index: 999999 !important; }

        /* ════════════════════════════════════════════════════
           ACCORDION CARDS
        ════════════════════════════════════════════════════ */
        #wpgt-panel-styles .wpgt-card {
            background: var(--el-left-bg);
            border: none;
            border-radius: 0;
            margin-bottom: 0;
            border-bottom: 1px solid var(--el-border2);
            overflow: visible;
        }

        /* Card header */
        #wpgt-panel-styles .wpgt-card-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 16px 11px 20px;
            background: var(--el-left-bg);
            border-bottom: none;
            border-left: 3px solid transparent;
            border-radius: 0;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--el-text);
            cursor: pointer;
            user-select: none;
            transition: background .12s, border-color .12s;
            position: relative;
        }
        #wpgt-panel-styles .wpgt-card-head:hover {
            background: var(--el-hover-bg);
            border-left-color: #c5cef7;
        }
        #wpgt-panel-styles .wpgt-card.wpgt-open > .wpgt-card-head {
            background: var(--el-active-bg);
            border-left-color: var(--el-active-left);
            color: var(--el-accent);
            font-weight: 600;
        }

        /* Chevron arrow */
        #wpgt-panel-styles .wpgt-card-head::after {
            content: "";
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%) rotate(-90deg);
            width: 0; height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-top: 5px solid #bbb;
            transition: transform .18s;
        }
        #wpgt-panel-styles .wpgt-card.wpgt-open > .wpgt-card-head::after {
            transform: translateY(-50%) rotate(0deg);
            border-top-color: var(--el-accent);
        }

        #wpgt-panel-styles .wpgt-card-icon { font-size: 1rem; line-height: 1; flex-shrink: 0; }
        #wpgt-panel-styles .wpgt-card-title { flex: 1; }
        #wpgt-panel-styles .wpgt-card-head > .wpgt-css-badge { display: none; }

        /* Card body */
        #wpgt-panel-styles .wpgt-card-inner { display: none; }
        #wpgt-panel-styles .wpgt-card.wpgt-open > .wpgt-card-inner { display: block; }
        #wpgt-panel-styles .wpgt-card-fields { padding: 4px 0; border-right: none; }
        #wpgt-panel-styles .wpgt-card-preview { display: none; }

        /* ════════════════════════════════════════════════════
           GROUP LABEL
        ════════════════════════════════════════════════════ */
        #wpgt-panel-styles .wpgt-group-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--el-text-muted);
            padding: 10px 20px 7px;
            border-bottom: 1px solid var(--el-border2);
            background: var(--el-section-bg);
        }
        #wpgt-panel-styles .wpgt-group-label .wpgt-css-badge {
            background: #e8ecfb;
            color: var(--el-accent);
            border: none;
        }

        /* ════════════════════════════════════════════════════
           FIELDS — inline row (label left, control right)
        ════════════════════════════════════════════════════ */
        #wpgt-panel-styles .wpgt-field-grid { display: block; }
        #wpgt-panel-styles .wpgt-field {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 8px;
            padding: 7px 16px 7px 20px;
            border-bottom: 1px solid var(--el-border2);
            min-width: 0;
        }
        #wpgt-panel-styles .wpgt-field:last-child { border-bottom: none; }
        #wpgt-panel-styles .wpgt-field--full {
            flex-direction: column;
            align-items: flex-start;
        }
        #wpgt-panel-styles .wpgt-field label {
            flex: 0 0 105px;
            font-size: 0.75rem;
            font-weight: 400;
            color: var(--el-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: default;
        }
        #wpgt-panel-styles .wpgt-field--full label {
            flex: none;
            margin-bottom: 5px;
            color: var(--el-text-muted);
            font-size: 0.64rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        #wpgt-panel-styles .wpgt-field > div,
        #wpgt-panel-styles .wpgt-field > select,
        #wpgt-panel-styles .wpgt-field > input { flex: 1; min-width: 0; }

        /* ── Color swatch ─────────────────────────────────── */
        #wpgt-panel-styles .wpgt-field-color-wrap {
            display: flex; align-items: center; gap: 6px; flex: 1;
        }
        #wpgt-panel-styles .wpgt-field-color-wrap .wp-color-result {
            width: 28px !important; height: 26px !important;
            border-radius: 4px !important; margin: 0 !important;
            border: 1px solid var(--el-input-bd) !important;
        }
        #wpgt-panel-styles .wpgt-field-color-wrap .wp-picker-container {
            display: flex; align-items: center; gap: 6px; width: 100%;
            position: relative;
        }
        #wpgt-panel-styles .wpgt-field-color-wrap .wp-color-result-text { display: none; }
        #wpgt-panel-styles .wpgt-field-color-wrap input[type=text].wpgt-style-picker {
            background: var(--el-input-bg) !important;
            color: var(--el-text) !important;
            border: 1px solid var(--el-input-bd) !important;
            border-radius: 4px !important;
            height: 26px !important;
            font-size: 0.72rem !important;
            padding: 0 6px !important;
            width: 88px !important;
            box-shadow: none !important;
        }

        /* ── Number input ─────────────────────────────────── */
        #wpgt-panel-styles .wpgt-field-num-wrap { display: flex; align-items: center; gap: 4px; }
        #wpgt-panel-styles .wpgt-field-num-wrap input[type=number] {
            width: 60px !important;
            background: var(--el-input-bg) !important;
            color: var(--el-text) !important;
            border: 1px solid var(--el-input-bd) !important;
            border-radius: 4px !important;
            height: 28px !important;
            font-size: 0.78rem !important;
            padding: 0 6px !important;
            box-shadow: none !important;
            text-align: center;
        }
        #wpgt-panel-styles .wpgt-field-num-wrap input[type=number]:focus {
            border-color: var(--el-input-focus) !important;
            box-shadow: 0 0 0 2px rgba(64,84,178,.15) !important;
            outline: none;
        }
        #wpgt-panel-styles .wpgt-field-num-wrap span {
            font-size: 0.68rem; color: var(--el-text-muted);
            background: #f0f0f0; padding: 2px 6px;
            border-radius: 3px; border: 1px solid var(--el-input-bd);
        }

        /* ── Slider (Width control) ──────────────────────── */
        #wpgt-panel-styles .wpgt-field-slider-wrap {
            width: 100%; display: flex; flex-direction: column; gap: 6px;
        }
        #wpgt-panel-styles .wpgt-slider-range {
            -webkit-appearance: none; appearance: none;
            width: 100%; height: 3px; border-radius: 3px;
            background: #ddd; outline: none; cursor: pointer;
            accent-color: var(--el-accent);
        }
        #wpgt-panel-styles .wpgt-slider-range::-webkit-slider-thumb {
            -webkit-appearance: none; appearance: none;
            width: 14px; height: 14px; border-radius: 50%;
            background: var(--el-accent); cursor: pointer;
            border: 2px solid #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,.2);
        }
        #wpgt-panel-styles .wpgt-slider-range::-moz-range-thumb {
            width: 14px; height: 14px; border-radius: 50%;
            background: var(--el-accent); cursor: pointer;
            border: 2px solid #fff; box-shadow: 0 1px 4px rgba(0,0,0,.2);
        }
        #wpgt-panel-styles .wpgt-slider-input-row { display: flex; align-items: center; gap: 6px; }
        #wpgt-panel-styles .wpgt-slider-number {
            width: 60px !important; font-size: 0.78rem;
            padding: 3px 6px !important; height: 28px;
            border: 1px solid var(--el-input-bd); border-radius: 4px;
            box-shadow: none !important; text-align: center;
            background: var(--el-input-bg); color: var(--el-text);
        }
        #wpgt-panel-styles .wpgt-slider-unit { font-size: 0.68rem; color: var(--el-text-muted); }
        #wpgt-panel-styles .wpgt-unit-toggle {
            display: flex; border: 1px solid var(--el-input-bd);
            border-radius: 4px; overflow: hidden;
        }
        #wpgt-panel-styles .wpgt-unit-btn {
            padding: 3px 8px; font-size: 0.72rem; font-weight: 500;
            background: #f5f5f5; border: none;
            border-right: 1px solid var(--el-input-bd);
            cursor: pointer; color: #555; line-height: 1; height: 26px;
            transition: background .1s, color .1s;
        }
        #wpgt-panel-styles .wpgt-unit-btn:last-of-type { border-right: none; }
        #wpgt-panel-styles .wpgt-unit-btn--active { background: var(--el-accent); color: #fff; }
        #wpgt-panel-styles .wpgt-unit-btn:hover:not(.wpgt-unit-btn--active) { background: #e8ecfb; color: var(--el-accent); }

        /* ── Select ───────────────────────────────────────── */
        #wpgt-panel-styles .wpgt-field select {
            background: var(--el-input-bg) !important;
            color: var(--el-text) !important;
            border: 1px solid var(--el-input-bd) !important;
            border-radius: 4px !important; height: 28px !important;
            font-size: 0.78rem !important; padding: 0 6px !important;
            box-shadow: none !important; width: 100%;
        }
        #wpgt-panel-styles .wpgt-field select:focus {
            border-color: var(--el-input-focus) !important;
            box-shadow: 0 0 0 2px rgba(64,84,178,.15) !important; outline: none;
        }

        /* ── Text input ───────────────────────────────────── */
        #wpgt-panel-styles .wpgt-field-text,
        #wpgt-panel-styles .wpgt-field input[type=text]:not(.wpgt-style-picker) {
            background: var(--el-input-bg) !important; color: var(--el-text) !important;
            border: 1px solid var(--el-input-bd) !important; border-radius: 4px !important;
            height: 28px !important; font-size: 0.78rem !important;
            padding: 0 8px !important; box-shadow: none !important;
            box-sizing: border-box; width: 100% !important;
        }

        /* ── Checkbox ─────────────────────────────────────── */
        #wpgt-panel-styles .wpgt-field input[type=checkbox] {
            width: 16px; height: 16px;
            accent-color: var(--el-accent); cursor: pointer;
        }

        /* ════════════════════════════════════════════════════
           SAVE BAR
        ════════════════════════════════════════════════════ */
        #wpgt-panel-styles .wpgt-save-bar {
            position: sticky; bottom: 0; z-index: 99;
            background: #fff; border-top: 1px solid var(--el-border);
            padding: 10px 16px; display: flex; align-items: center; gap: 8px;
        }
        #wpgt-panel-styles .wpgt-save-bar .button-primary {
            background: var(--el-accent) !important;
            border-color: var(--el-accent) !important;
            color: #fff !important; font-weight: 600 !important;
            box-shadow: none !important; text-shadow: none !important;
            border-radius: 4px !important; font-size: 0.8rem !important;
        }
        #wpgt-panel-styles .wpgt-save-bar .button-primary:hover {
            background: #354299 !important; border-color: #354299 !important;
        }
        #wpgt-panel-styles .wpgt-save-bar .button-secondary {
            background: transparent !important;
            border-color: var(--el-input-bd) !important;
            color: var(--el-text-muted) !important;
            box-shadow: none !important; text-shadow: none !important;
            border-radius: 4px !important; font-size: 0.8rem !important;
        }

        /* ════════════════════════════════════════════════════
           CSS BADGE
        ════════════════════════════════════════════════════ */
        .wpgt-css-badge {
            font-size: 0.65rem; font-weight: 400;
            background: #e8ecfb; color: var(--el-accent,#4054b2);
            padding: 1px 5px; border-radius: 3px;
            font-family: monospace; flex-shrink: 0;
        }
        .wpgt-css-badge--sm { font-size: 0.6rem; }

        /* ════════════════════════════════════════════════════
           PREVIEW PANE utilities
        ════════════════════════════════════════════════════ */
        .wpgt-pv-eyebrow {
            font-size: 0.62rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .08em;
            color: #aaa; margin: 0 0 10px;
        }
        .wpgt-pv-sentence { font-size: 0.9rem; line-height: 1.6; color: #374151; margin: 0 0 10px; }

        /* ════════════════════════════════════════════════════
           RESPONSIVE
        ════════════════════════════════════════════════════ */
        @media (max-width: 820px) {
            #wpgt-panel-styles .wpgt-styles-panel { left: 0; flex-direction: column; }
            #wpgt-panel-styles .wpgt-panel-left { flex: 0 0 auto; max-width: 100%; }
            #wpgt-panel-styles .wpgt-drag-handle { display: none; }
            #wpgt-panel-styles .wpgt-panel-right { min-height: 300px; }
        }
        </style>
        <?php
    }


    /**
     * Style field defaults — mirrors public.css values so reset works correctly.
     */
    public static function get_style_defaults(): array {
        return [
            // ── Global font ───────────────────────────────────────────────
            'global_font_family'       => '',          // empty = inherit site font
            'global_font_custom'       => '',          // custom Google Font name
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
            'tooltip_read_more_text'   => 'Read more →',
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
            'search_max_width'         => '480',
            'search_max_width_unit'    => 'px',
            'search_input_height'      => '44',
            'search_results_max_height'=> '320',
            'search_input_bg'          => '#ffffff',
            'search_input_text_color'  => '#1e293b',
            'search_input_border'      => '#d1d5db',
            'search_input_focus'       => '#2563eb',
            'search_input_radius'      => '6',
            'search_input_size'        => '15',
            'search_input_padding_v'   => '10',
            'search_input_padding_h'   => '16',
            'search_icon_color'        => '#94a3b8',
            'search_clear_color'       => '#94a3b8',
            'search_results_bg'        => '#ffffff',
            'search_results_radius'    => '6',
            'search_results_shadow'    => 'default',
            'search_result_text'       => '#1e293b',
            'search_result_hover_bg'   => '#f0f7ff',
            'search_result_hover_color'=> '#2563eb',
            'search_separator_color'   => '#f1f5f9',
            'search_result_title'      => '#1e293b',
            'search_result_excerpt'    => '#64748b',
            'search_match_color'       => '#2563eb',
            'search_noresults_color'   => '#94a3b8',
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

        $raw      = (array) wp_unslash( $_POST['wpgt_styles'] ?? [] );
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
            'search_input_bg','search_input_text_color',
            'search_icon_color','search_clear_color',
            'search_result_text','search_result_title','search_result_excerpt',
            'search_result_hover_color','search_separator_color',
            'search_match_color','search_noresults_color',
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
            'search_max_width_unit'        => ['px','%'],
        ];

        foreach ( $defaults as $key => $default ) {
            if ( ! isset( $raw[ $key ] ) ) continue;
            $val = $raw[ $key ];
            // Free-text fields (font names, button text, etc.)
            $text_keys = ['tooltip_read_more_text', 'global_font_custom'];
            if ( in_array( $key, $text_keys, true ) ) {
                $clean[ $key ] = sanitize_text_field( $val );
            } elseif ( $key === 'global_font_family' ) {
                // '__custom__' means use global_font_custom value; empty = site default
                if ( $val === '__custom__' ) {
                    // actual font name comes from global_font_custom, store empty here
                    $clean[ $key ] = '';
                } else {
                    $clean[ $key ] = sanitize_text_field( $val );
                }
            } elseif ( in_array( $key, $bg_keys, true ) ) {
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
            } elseif ( $key === 'tooltip_read_more_text' ) {
                $clean[ $key ] = sanitize_text_field( $val );
            } else {
                $clean[ $key ] = max( 0, (int) $val );
            }
        }

        // If user selected "Custom font name", copy the custom value into global_font_family
        if ( empty( $clean['global_font_family'] ) && ! empty( $clean['global_font_custom'] ) ) {
            $clean['global_font_family'] = $clean['global_font_custom'];
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

        $data = wp_unslash( $_POST );
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

            // get_page_by_title() deprecated since WP 6.2 — use WP_Query instead
            $existing_q = new WP_Query( [
                'post_type'              => WPGT_Post_Type::POST_TYPE,
                'post_status'            => 'publish',
                'title'                  => $word,
                'posts_per_page'         => 1,
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => false,
                'orderby'                => 'ID',
                'order'                  => 'ASC',
            ] );
            $existing = $existing_q->have_posts() ? $existing_q->posts[0] : null;

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

        // order is cast to int so no unslash needed; nonce verified by check_ajax_referer above
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
        if ( ! wp_verify_nonce( sanitize_key( $_POST['wpgt_skip_nonce'] ), 'wpgt_skip_tooltips' ) ) return;
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