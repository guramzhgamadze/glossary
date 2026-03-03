<?php
/**
 * Plugin Name:       ! Glossary Tooltip
 * Plugin URI:        https://github.com/guramzhgamadze/glossary
 * Description:       A powerful glossary plugin that automatically adds hover tooltips to defined terms throughout your content. Built with full Georgian language support including declension-aware matching.
 * Version:           1.0.7
 * Author:            Guram Zhgamadze
 * Author URI:        https://github.com/guramzhgamadze
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-glossary-tooltip
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPGT_VERSION',     '1.0.7' );
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
        add_action( 'wp_head',            [ $this, 'output_style_overrides' ], 99 );
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
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'restUrl'  => rest_url( 'wpgt/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'settings' => [
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

    public function output_style_overrides() {
        $saved = get_option( 'wpgt_styles', [] );
        // Nothing saved yet — no overrides needed, keep plugin CSS untouched.
        if ( empty( $saved ) ) return;

        $defaults = WPGT_Admin::get_style_defaults();
        $s        = wp_parse_args( $saved, $defaults );

        // Safe colour helper — returns value only if it is a non-empty hex colour.
        $col = function( string $k ) use ( $s ): string {
            $v = trim( (string) ( $s[ $k ] ?? '' ) );
            return ( $v !== '' && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $v ) ) ? $v : '';
        };

        // Integer pixel helper — returns "NNpx" only if > 0.
        $px = function( string $k ) use ( $s ): string {
            $v = (int) ( $s[ $k ] ?? 0 );
            return $v > 0 ? $v . 'px' : '';
        };

        // Build a CSS rule only if at least one declaration is non-empty.
        $rule = function( string $selector, array $decls ): string {
            $body = '';
            foreach ( $decls as $prop => $val ) {
                if ( $val !== '' ) $body .= $prop . ':' . $val . ';';
            }
            return $body !== '' ? $selector . '{' . $body . '}' : '';
        };

        $css = '';

        // ── Trigger words ─────────────────────────────────────────────
        $css .= $rule( '.wpgt-tooltip-trigger', [
            'border-bottom-color' => $col('trigger_underline_color'),
            'border-bottom-style' => ( in_array( $s['trigger_underline_style'] ?? '', ['dashed','solid','dotted','none'], true ) ? $s['trigger_underline_style'] : '' ),
            'font-weight'         => ( in_array( $s['trigger_font_weight'] ?? '', ['400','500','600','700'], true ) ? $s['trigger_font_weight'] : '' ),
        ] );
        $hc = $col('trigger_hover_color');
        if ( $hc ) {
            $css .= '.wpgt-tooltip-trigger:hover,.wpgt-tooltip-trigger:focus{'
                . 'color:' . $hc . ';border-bottom-color:' . $hc . ';}';
        }

        // ── Tooltip bubble ────────────────────────────────────────────
        $css .= $rule( '.wpgt-tooltip-bubble', [
            'color'         => $col('tooltip_text_color'),
            'font-size'     => $px('tooltip_font_size'),
            'border-radius' => $px('tooltip_border_radius'),
            'max-width'     => $px('tooltip_max_width'),
        ] );
        $css .= $rule( '.wpgt-tooltip-title',    [ 'color' => $col('tooltip_title_color') ] );
        $css .= $rule( '.wpgt-tooltip-see-more', [ 'color' => $col('tooltip_link_color')  ] );

        // ── A–Z bar ───────────────────────────────────────────────────
        $css .= $rule( '.wpgt-alphabet-bar', [ 'background' => $col('az_bar_bg') ] );
        $css .= $rule( '.wpgt-az-link', [
            'color'         => $col('az_link_color'),
            'border-radius' => $px('az_link_radius'),
        ] );
        $css .= $rule( '.wpgt-az-link:hover,.wpgt-az-link:focus', [
            'background' => $col('az_link_hover_bg'),
            'color'      => $col('az_link_hover_color'),
        ] );

        // ── Letter headings ───────────────────────────────────────────
        $css .= $rule( '.wpgt-letter-heading', [
            'color'        => $col('letter_heading_color'),
            'border-color' => $col('letter_heading_border'),
            'font-size'    => $px('letter_heading_size'),
        ] );

        // ── Term cards ────────────────────────────────────────────────
        $css .= $rule( '.wpgt-term-item', [
            'background'    => $col('term_card_bg'),
            'border-color'  => $col('term_card_border'),
            'border-radius' => $px('term_card_radius'),
        ] );
        $css .= $rule( '.wpgt-term-item:hover', [ 'border-color' => $col('term_card_hover_border') ] );
        $css .= $rule( '.wpgt-term-link', [
            'color'     => $col('term_link_color'),
            'font-size' => $px('term_link_size'),
        ] );
        $css .= $rule( '.wpgt-term-excerpt', [ 'color' => $col('term_excerpt_color') ] );

        // ── Single term box ───────────────────────────────────────────
        $br = (int)( $s['termbox_radius'] ?? 6 );
        $css .= $rule( '.wpgt-term-box', [
            'border-left-color' => $col('termbox_border_color'),
            'border-left-width' => $px('termbox_border_width'),
            'background'        => $col('termbox_bg'),
            'border-radius'     => $br > 0 ? '0 ' . $br . 'px ' . $br . 'px 0' : '',
        ] );
        $css .= $rule( '.wpgt-term-box__title a', [
            'color'     => $col('termbox_title_color'),
            'font-size' => $px('termbox_title_size'),
        ] );
        $css .= $rule( '.wpgt-term-box__definition', [ 'color' => $col('termbox_text_color') ] );

        // ── Search widget ─────────────────────────────────────────────
        $css .= $rule( '.wpgt-search-input', [
            'border-color'  => $col('search_input_border'),
            'border-radius' => $px('search_input_radius'),
            'font-size'     => $px('search_input_size'),
        ] );
        $focus = $col('search_input_focus');
        if ( $focus ) {
            $css .= '.wpgt-search-input:focus{border-color:' . $focus . ';'
                . 'box-shadow:0 0 0 3px ' . $focus . '40;}';
        }
        $css .= $rule( '.wpgt-search-results', [ 'background' => $col('search_results_bg') ] );
        $css .= $rule( '.wpgt-search-result-item:hover,.wpgt-search-result-item:focus', [
            'background' => $col('search_result_hover_bg'),
        ] );
        $css .= $rule( '.wpgt-search-result-title',   [ 'color' => $col('search_result_title')   ] );
        $css .= $rule( '.wpgt-search-result-excerpt', [ 'color' => $col('search_result_excerpt') ] );

        if ( $css ) {
            echo '<style id="wpgt-style-overrides">' . $css . '</style>' . "\n";
        }
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
