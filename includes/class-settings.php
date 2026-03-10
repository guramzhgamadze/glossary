<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPGT_Settings {

    const OPTION_KEY = 'wpgt_settings';
    const RULES_KEY  = 'wpgt_cat_rules';   // category → group mapping

    // ── Category Rules (separate option — variable-length, not fixed defaults) ──

    /**
     * Returns the category→group mapping.
     * Format: [ cat_id (string) => [ group_slug, ... ] ]
     * Empty array = no rules configured = backward-compat mode.
     */
    public static function get_cat_rules(): array {
        $raw = get_option( self::RULES_KEY, [] );
        return is_array( $raw ) ? $raw : [];
    }

    /**
     * Save the category→group mapping.
     * Expects [ cat_id => [ slug, ... ] ] — sanitizes slugs on the way in.
     */
    public static function update_cat_rules( array $rules ): bool {
        $clean = [];
        foreach ( $rules as $cat_id => $slugs ) {
            $cat_id = (int) $cat_id;
            if ( $cat_id < 1 ) continue;
            $clean_slugs = array_values( array_filter( array_map( 'sanitize_key', (array) $slugs ) ) );
            if ( ! empty( $clean_slugs ) ) {
                $clean[ (string) $cat_id ] = $clean_slugs;
            }
        }
        return update_option( self::RULES_KEY, $clean );
    }

    public static function get_defaults(): array {
        return [
            'enable_tooltips'    => true,
            'parse_post_types'   => [ 'post', 'page' ],
            'tooltip_position'   => 'top',        // top | bottom | left | right
            'tooltip_theme'      => 'dark',       // dark | light | branded
            'open_on'            => 'hover',      // hover | click
            'show_see_more'      => true,
            'case_sensitive'     => false,
            'first_occurrence'   => true,         // only highlight first occurrence per page
            'exclude_headings'   => true,
            'exclude_links'      => true,
            'glossary_slug'      => 'glossary',
            'index_columns'      => 3,
            'show_alphabet_bar'  => true,
            'tooltip_width'      => 280,          // px
            'brand_color'        => '#2563eb',
            'text_color'         => '#ffffff',
            'link_new_tab'       => true,          // open "Read more" in new tab
            'tooltip_bg_color'   => '',            // custom bg colour (empty = use theme default)
            'see_more_color'     => '',            // custom "Read more" link colour (empty = theme default)
        ];
    }

    public static function install_defaults() {
        if ( ! get_option( self::OPTION_KEY ) ) {
            update_option( self::OPTION_KEY, self::get_defaults() );
        }
    }

    public static function get_all(): array {
        $saved = get_option( self::OPTION_KEY, [] );
        return wp_parse_args( $saved, self::get_defaults() );
    }

    public static function get( string $key, $fallback = null ) {
        $all = self::get_all();
        return $all[ $key ] ?? $fallback;
    }

    public static function update( array $data ): bool {
        $current  = self::get_all();
        $defaults = self::get_defaults();
        $merged   = [];

        foreach ( $defaults as $k => $default ) {
            if ( isset( $data[ $k ] ) ) {
                $merged[ $k ] = $data[ $k ];
            } else {
                // Checkboxes that are unchecked won't be in $data
                $merged[ $k ] = is_bool( $default ) ? false : $current[ $k ];
            }
        }

        return update_option( self::OPTION_KEY, $merged );
    }
}
