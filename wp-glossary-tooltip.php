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
        $styles   = get_option( 'wpgt_styles', [] );
        $sd       = WPGT_Admin::get_style_defaults();
        $sm       = wp_parse_args( $styles, $sd );

        // Always enqueue public CSS — shortcodes need it even when tooltips are disabled
        wp_enqueue_style(
            'wpgt-public',
            WPGT_PLUGIN_URL . 'public/css/public.css',
            [],
            WPGT_VERSION
        );

        if ( empty( $settings['enable_tooltips'] ) ) {
            return;
        }

        wp_enqueue_script(
            'wpgt-public',
            WPGT_PLUGIN_URL . 'public/js/public.js',
            [ 'jquery' ],
            WPGT_VERSION,
            true
        );

        // Styles option overrides main settings for all visual properties
        $brand_color   = $sm['trigger_color']      ?: ( $settings['brand_color']      ?? '#2563eb' );
        $tooltip_theme = $sm['tooltip_theme']      ?: ( $settings['tooltip_theme']    ?? 'dark'    );
        $tooltip_bg    = $sm['tooltip_bg']         ?: ( $settings['tooltip_bg_color'] ?? ''        );
        $see_more_clr  = $sm['tooltip_link_color'] ?: ( $settings['see_more_color']   ?? ''        );

        wp_localize_script( 'wpgt-public', 'wpgtData', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'restUrl'  => rest_url( 'wpgt/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'settings' => [
                'tooltip_position' => $settings['tooltip_position'] ?? 'top',
                'tooltip_theme'    => $tooltip_theme,
                'open_on'          => $settings['open_on']          ?? 'hover',
                'show_see_more'    => $settings['show_see_more']    ?? true,
                'link_new_tab'     => $settings['link_new_tab']     ?? true,
                'brand_color'      => $brand_color,
                'tooltip_bg_color' => $tooltip_bg,
                'see_more_color'   => $see_more_clr,
            ],
        ] );
    }

    public function output_style_overrides() {
        $saved = get_option( 'wpgt_styles', [] );
        if ( empty( $saved ) ) return;

        $defaults = WPGT_Admin::get_style_defaults();
        $s = wp_parse_args( $saved, $defaults );

        $col = function( string $k ) use ( $s ): string {
            $v = trim( (string)( $s[$k] ?? '' ) );
            if ( $v === 'transparent' ) return 'transparent';
            return ( $v !== '' && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $v ) ) ? $v : '';
        };
        $px = function( string $k ) use ( $s ): string {
            $v = (int)( $s[$k] ?? 0 );
            return $v > 0 ? $v . 'px' : '';
        };
        $rule = function( string $sel, array $decls ): string {
            $body = '';
            foreach ( $decls as $p => $v ) { if ( $v !== '' ) $body .= $p . ':' . $v . ';'; }
            return $body !== '' ? $sel . '{' . $body . '}' : '';
        };
        $shadow_preset = function( string $k, array $map ) use ( $s ): string {
            $v = $s[$k] ?? '';
            return $map[$v] ?? '';
        };

        $tip_shadows  = [ 'none'=>'none','sm'=>'0 1px 4px rgba(0,0,0,.10)','default'=>'0 8px 24px rgba(0,0,0,.18)','lg'=>'0 16px 40px rgba(0,0,0,.30)' ];
        $card_shadows = [ 'none'=>'none','sm'=>'0 1px 3px rgba(0,0,0,.06)','md'=>'0 2px 8px rgba(0,0,0,.10)','lg'=>'0 8px 20px rgba(0,0,0,.14)' ];
        $sr_shadows   = [ 'none'=>'none','sm'=>'0 1px 3px rgba(0,0,0,.06)','default'=>'0 4px 16px rgba(0,0,0,.12)','lg'=>'0 8px 24px rgba(0,0,0,.18)' ];

        $css = '';

        // ── CSS variable — propagates brand colour to all components that use var(--wpgt-brand) ──
        $brand = $col('trigger_color');
        if ( $brand ) $css .= ':root{--wpgt-brand:' . $brand . ';}';

        // ── Trigger word ──────────────────────────────────────────────────────
        $trig = [];
        if ( $col('trigger_color') )     $trig['border-bottom-color'] = $col('trigger_color');
        $ust = $s['trigger_underline_style'] ?? '';
        if ( in_array( $ust, ['dashed','solid','dotted','none'], true ) ) $trig['border-bottom-style'] = $ust;
        $ubw = (int)( $s['trigger_underline_width'] ?? 2 );
        if ( $ubw > 0 ) $trig['border-bottom-width'] = $ubw . 'px';
        $fw = $s['trigger_font_weight'] ?? '';
        if ( in_array( $fw, ['400','500','600','700'], true ) ) $trig['font-weight'] = $fw;
        if ( $px('trigger_font_size') ) $trig['font-size'] = $px('trigger_font_size');
        $tt = $s['trigger_text_transform'] ?? '';
        if ( in_array( $tt, ['uppercase','lowercase','capitalize'], true ) ) $trig['text-transform'] = $tt;
        $css .= $rule( '.wpgt-tooltip-trigger', $trig );
        $hc = $col('trigger_hover_color');
        if ( $hc ) $css .= '.wpgt-tooltip-trigger:hover,.wpgt-tooltip-trigger:focus{color:' . $hc . ';border-bottom-color:' . $hc . ';}';

        // ── Tooltip bubble ─────────────────────────────────────────────────────
        $tip = [];
        if ( $col('tooltip_bg') )           $tip['background']    = $col('tooltip_bg');
        if ( $col('tooltip_text_color') )   $tip['color']         = $col('tooltip_text_color');
        if ( $px('tooltip_font_size') )     $tip['font-size']     = $px('tooltip_font_size');
        $lh = trim( $s['tooltip_line_height'] ?? '' );
        if ( $lh && preg_match( '/^\d*\.?\d+$/', $lh ) ) $tip['line-height'] = $lh;
        $pv = (int)( $s['tooltip_padding_v'] ?? 12 ); $ph = (int)( $s['tooltip_padding_h'] ?? 14 );
        if ( $pv !== 12 || $ph !== 14 ) $tip['padding'] = $pv . 'px ' . $ph . 'px';
        $br = (int)( $s['tooltip_border_radius'] ?? 6 );
        if ( $br !== 6 ) $tip['border-radius'] = $br . 'px';
        $bw = (int)( $s['tooltip_border_width'] ?? 1 );
        if ( $bw !== 1 ) $tip['border-width'] = $bw . 'px';
        if ( $col('tooltip_border_color') ) $tip['border-color'] = $col('tooltip_border_color');
        if ( $px('tooltip_max_width') )     $tip['max-width']    = $px('tooltip_max_width');
        $ts = $shadow_preset( 'tooltip_shadow', $tip_shadows );
        if ( $ts !== '' && ( $s['tooltip_shadow'] ?? '' ) !== 'default' ) $tip['box-shadow'] = $ts;
        $css .= $rule( '.wpgt-tooltip-bubble', $tip );
        if ( $col('tooltip_title_color') ) $css .= '.wpgt-tooltip-title{color:' . $col('tooltip_title_color') . ';}';
        if ( $col('tooltip_link_color') )  $css .= '.wpgt-tooltip-see-more{color:' . $col('tooltip_link_color') . ';}';

        // ── A-Z bar ────────────────────────────────────────────────────────────
        $az_pv = (int)( $s['az_bar_padding_v'] ?? 10 ); $az_ph = (int)( $s['az_bar_padding_h'] ?? 12 );
        $css .= $rule( '.wpgt-alphabet-bar', [
            'background'    => $col('az_bar_bg'),
            'border-color'  => $col('az_bar_border_color'),
            'border-radius' => ( (int)($s['az_bar_radius']??6) !== 6 ) ? $px('az_bar_radius') : '',
            'padding'       => ( $az_pv !== 10 || $az_ph !== 12 ) ? $az_pv.'px '.$az_ph.'px' : '',
        ]);
        $alw = $s['az_link_weight'] ?? '';
        $css .= $rule( '.wpgt-az-link', [
            'color'         => $col('az_link_color'),
            'border-radius' => $px('az_link_radius'),
            'font-size'     => $px('az_link_size'),
            'font-weight'   => in_array( $alw, ['400','500','600','700'], true ) ? $alw : '',
        ]);
        $css .= $rule( '.wpgt-az-link:hover,.wpgt-az-link:focus', [
            'background' => $col('az_link_hover_bg'),
            'color'      => $col('az_link_hover_color'),
        ]);
        // Active letter (az-current)
        $az_cur_bw  = (int)( $s['az_current_border_width'] ?? 0 );
        $az_cur_bs  = $s['az_current_border_style'] ?? 'solid';
        $az_cur_br  = (int)( $s['az_current_border_radius'] ?? 4 );
        $css .= $rule( '.wpgt-az-current', [
            'background'    => $col('az_current_bg'),
            'color'         => $col('az_current_color'),
            'border-width'  => $az_cur_bw > 0 ? $az_cur_bw . 'px' : '',
            'border-style'  => ( $az_cur_bw > 0 && in_array( $az_cur_bs, ['solid','dashed','dotted','none'], true ) ) ? $az_cur_bs : '',
            'border-color'  => $col('az_current_border_color'),
            'border-radius' => ( $az_cur_br !== 4 ) ? $az_cur_br . 'px' : '',
        ]);
        if ( $col('az_current_hover_bg') || $col('az_current_hover_color') ) {
            $css .= $rule( '.wpgt-az-current:hover,.wpgt-az-current:focus', [
                'background' => $col('az_current_hover_bg'),
                'color'      => $col('az_current_hover_color'),
            ]);
        }

        // A-Z bar alignment
        $az_justify = $s['az_bar_justify'] ?? 'flex-start';
        if ( in_array( $az_justify, ['flex-start','center','flex-end','space-between'], true ) && $az_justify !== 'flex-start' ) {
            $css .= '.wpgt-alphabet-bar{justify-content:' . $az_justify . ';}';
        }

        // ── Letter headings ────────────────────────────────────────────────────
        $lhw  = $s['letter_heading_weight'] ?? '700';
        $lht  = $s['letter_heading_transform'] ?? '';
        $lhmb = (int)( $s['letter_heading_mb'] ?? 12 );
        $lha  = $s['letter_heading_align'] ?? 'left';
        $lhd  = $s['letter_heading_display'] ?? 'inline-block';
        $lhfw = (int)( $s['letter_heading_full_width'] ?? 0 );
        $lhbt = (int)( $s['letter_heading_border_top'] ?? 0 );
        $lhbb = (int)( $s['letter_heading_border_bottom'] ?? 2 );
        $lhbs = $s['letter_heading_border_style'] ?? 'solid';

        $lh_decls = [
            'color'          => $col('letter_heading_color'),
            'font-size'      => $px('letter_heading_size'),
            'font-weight'    => in_array( $lhw, ['400','500','600','700'], true ) ? $lhw : '',
            'text-transform' => in_array( $lht, ['uppercase','lowercase','capitalize'], true ) ? $lht : '',
            'margin-bottom'  => $lhmb !== 12 ? $lhmb . 'px' : '',
            'text-align'     => in_array( $lha, ['left','center','right'], true ) && $lha !== 'left' ? $lha : '',
            'display'        => in_array( $lhd, ['inline-block','block'], true ) && $lhd !== 'inline-block' ? $lhd : '',
            'width'          => $lhfw ? '100%' : '',
        ];

        // Build border properties explicitly — top, bottom, left (the existing decorative underline)
        $lh_border_color = $col('letter_heading_border');
        if ( $lh_border_color ) {
            // We manage each side to avoid overriding the bottom-border shorthand from public.css
            $lh_decls['border-left']   = 'none';
            $lh_decls['border-right']  = 'none';
            $lh_decls['border-top']    = $lhbt > 0 ? $lhbt . 'px ' . $lhbs . ' ' . $lh_border_color : 'none';
            $lh_decls['border-bottom'] = $lhbb > 0 ? $lhbb . 'px ' . $lhbs . ' ' . $lh_border_color : 'none';
        }
        $css .= $rule( '.wpgt-letter-heading', $lh_decls );

        // ── Term cards ─────────────────────────────────────────────────────────
        $tc_pv = (int)($s['term_card_padding_v']??12); $tc_ph = (int)($s['term_card_padding_h']??14);
        $tcs = $shadow_preset( 'term_card_shadow', $card_shadows );
        $tlw = $s['term_link_weight'] ?? '';
        $css .= $rule( '.wpgt-term-item', [
            'background'    => $col('term_card_bg'),
            'border-color'  => $col('term_card_border'),
            'border-radius' => $px('term_card_radius'),
            'padding'       => ( $tc_pv !== 12 || $tc_ph !== 14 ) ? $tc_pv.'px '.$tc_ph.'px' : '',
            'box-shadow'    => $tcs,
        ]);
        $css .= $rule( '.wpgt-term-item:hover', ['border-color' => $col('term_card_hover_border')] );
        $css .= $rule( '.wpgt-term-link', [
            'color'       => $col('term_link_color'),
            'font-size'   => $px('term_link_size'),
            'font-weight' => in_array( $tlw, ['400','500','600','700'], true ) ? $tlw : '',
        ]);
        $css .= $rule( '.wpgt-term-excerpt', [
            'color'     => $col('term_excerpt_color'),
            'font-size' => $px('term_excerpt_size'),
        ]);

        // ── Term box ───────────────────────────────────────────────────────────
        $tb_pv = (int)($s['termbox_padding_v']??14); $tb_ph = (int)($s['termbox_padding_h']??18);
        $tb_br = (int)($s['termbox_radius']??6);
        $tbs  = $shadow_preset( 'termbox_shadow', $card_shadows );
        $tbtw = $s['termbox_title_weight'] ?? '';
        $css .= $rule( '.wpgt-term-box', [
            'border-left-color' => $col('termbox_border_color'),
            'border-left-width' => $px('termbox_border_width'),
            'background'        => $col('termbox_bg'),
            'border-radius'     => $tb_br > 0 ? '0 '.$tb_br.'px '.$tb_br.'px 0' : '',
            'padding'           => ( $tb_pv !== 14 || $tb_ph !== 18 ) ? $tb_pv.'px '.$tb_ph.'px' : '',
            'box-shadow'        => $tbs,
        ]);
        $css .= $rule( '.wpgt-term-box__title', [
            'font-size'   => $px('termbox_title_size'),
            'font-weight' => in_array( $tbtw, ['400','500','600','700'], true ) ? $tbtw : '',
        ]);
        $css .= $rule( '.wpgt-term-box__title a', ['color' => $col('termbox_title_color')] );
        $css .= $rule( '.wpgt-term-box__definition', [
            'color'     => $col('termbox_text_color'),
            'font-size' => $px('termbox_text_size'),
        ]);

        // ── Search widget ──────────────────────────────────────────────────────
        $si_pv = (int)($s['search_input_padding_v']??10); $si_ph = (int)($s['search_input_padding_h']??16);
        $sr_pv = (int)($s['search_result_padding_v']??10); $sr_ph = (int)($s['search_result_padding_h']??14);
        $srs   = $shadow_preset( 'search_results_shadow', $sr_shadows );
        $css .= $rule( '.wpgt-search-input', [
            'border-color'  => $col('search_input_border'),
            'border-radius' => $px('search_input_radius'),
            'font-size'     => $px('search_input_size'),
            'padding'       => ( $si_pv !== 10 || $si_ph !== 16 ) ? $si_pv.'px '.$si_ph.'px' : '',
        ]);
        $focus = $col('search_input_focus');
        if ( $focus ) $css .= '.wpgt-search-input:focus{border-color:'.$focus.';box-shadow:0 0 0 3px '.$focus.'40;}';
        $css .= $rule( '.wpgt-search-results', [
            'background'    => $col('search_results_bg'),
            'border-radius' => $px('search_results_radius'),
            'box-shadow'    => $srs,
        ]);
        $css .= $rule( '.wpgt-search-result-item:hover,.wpgt-search-result-item:focus', [
            'background' => $col('search_result_hover_bg'),
        ]);
        $css .= $rule( '.wpgt-search-result-item', [
            'padding' => ( $sr_pv !== 10 || $sr_ph !== 14 ) ? $sr_pv.'px '.$sr_ph.'px' : '',
        ]);
        $css .= $rule( '.wpgt-search-result-title',   ['color' => $col('search_result_title')]   );
        $css .= $rule( '.wpgt-search-result-excerpt', ['color' => $col('search_result_excerpt')] );

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
