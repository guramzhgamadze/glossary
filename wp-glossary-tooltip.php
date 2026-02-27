<?php
/**
 * Plugin Name:       !!! Glossary Tooltip
 * Plugin URI:        https://example.com/wp-glossary-tooltip
 * Description:       A powerful glossary plugin that automatically adds hover tooltips to defined terms throughout your content.
 * Version:           1.0.6
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-glossary-tooltip
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPGT_VERSION',     '1.0.6' );
define( 'WPGT_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WPGT_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'WPGT_PLUGIN_FILE', __FILE__ );

// Load sub-modules
require_once WPGT_PLUGIN_DIR . 'includes/class-post-type.php';
require_once WPGT_PLUGIN_DIR . 'includes/class-settings.php';
require_once WPGT_PLUGIN_DIR . 'includes/class-georgian-stemmer.php';
require_once WPGT_PLUGIN_DIR . 'includes/class-tooltip-parser.php';
require_once WPGT_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once WPGT_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once WPGT_PLUGIN_DIR . 'admin/class-admin.php';

/**
 * Main plugin class.
 */
class WP_Glossary_Tooltip {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'init' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_assets' ] );
        register_activation_hook( WPGT_PLUGIN_FILE,   [ $this, 'activate' ] );
        register_deactivation_hook( WPGT_PLUGIN_FILE, [ $this, 'deactivate' ] );
    }

    public function init() {
        WPGT_Post_Type::register();
        WPGT_Shortcodes::register();
        WPGT_REST_API::register();

        if ( is_admin() ) {
            WPGT_Admin::init();
        }
    }

    public function enqueue_public_assets() {
        $settings = WPGT_Settings::get_all();

        if ( empty( $settings['enable_tooltips'] ) ) {
            return;
        }

        wp_enqueue_style(
            'wpgt-public',
            WPGT_PLUGIN_URL . 'public/css/public.css',
            [],
            WPGT_VERSION
        );

        wp_enqueue_script(
            'wpgt-public',
            WPGT_PLUGIN_URL . 'public/js/public.js',
            [ 'jquery' ],
            WPGT_VERSION,
            true
        );

        wp_localize_script( 'wpgt-public', 'wpgtData', [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'restUrl'      => rest_url( 'wpgt/v1/' ),
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'settings'     => [
                'tooltip_position' => $settings['tooltip_position'] ?? 'top',
                'tooltip_theme'    => $settings['tooltip_theme']    ?? 'dark',
                'open_on'          => $settings['open_on']          ?? 'hover',
                'show_see_more'    => $settings['show_see_more']    ?? true,
                'link_new_tab'     => $settings['link_new_tab']     ?? true,
                'brand_color'      => $settings['brand_color']      ?? '#2563eb',
                'see_more_color'   => $settings['see_more_color']   ?? '',
            ],
        ] );
    }

    public function activate() {
        WPGT_Post_Type::register();
        flush_rewrite_rules();
        WPGT_Settings::install_defaults();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

WP_Glossary_Tooltip::instance();
