/* WP Glossary Tooltip – Admin Scripts */
jQuery( function( $ ) {

    /* ----------------------------------------------------------------
       Tab navigation
    ---------------------------------------------------------------- */
    var $tabs    = $( '.wpgt-tabs .nav-tab' );
    var $panels  = $( '.wpgt-tab-content' );

    function activateTab( hash ) {
        var $panel = $( hash );
        if ( ! $panel.length ) return;
        $panels.hide();
        $panel.show();
        $tabs.removeClass( 'nav-tab-active' );
        $tabs.filter( '[href="' + hash + '"]' ).addClass( 'nav-tab-active' );
        sessionStorage.setItem( 'wpgt_active_tab', hash );

        // Reset sticky preview on tab switch so it remeasures
        if ( hash === '#wpgt-tab-styles' ) {
            $( '.wpgt-pv-sticky' ).css( 'transform', '' );
            setTimeout( updateStickyPreview, 50 );
        }
    }

    // 1. URL query param wins (used after save redirect)
    var urlParams    = new URLSearchParams( window.location.search );
    var tabFromUrl   = urlParams.get( 'wpgt_tab' );
    var tabFromSess  = sessionStorage.getItem( 'wpgt_active_tab' );
    var initialTab   = ( tabFromUrl && $( '#' + tabFromUrl ).length )
        ? '#' + tabFromUrl
        : ( tabFromSess && $( tabFromSess ).length ? tabFromSess : null );

    if ( initialTab ) {
        activateTab( initialTab );
    } else {
        activateTab( $tabs.first().attr( 'href' ) );
    }

    $tabs.on( 'click', function( e ) {
        e.preventDefault();
        activateTab( $( this ).attr( 'href' ) );
    } );

    /* ----------------------------------------------------------------
       Sticky preview scroll-follower for Styles tab.

       WP admin's #wpcontent has overflow-x:hidden which breaks CSS
       position:sticky, so we implement it in JS instead.

       Strategy: reset the element's transform, measure where it
       naturally sits in the viewport, then apply a translateY offset
       so it stays pinned below the admin bar as the user scrolls.
    ---------------------------------------------------------------- */
    function updateStickyPreview() {
        var $sticky  = $( '.wpgt-pv-sticky' );
        var $preview = $( '.wpgt-styles-preview' );
        if ( ! $sticky.length || ! $preview.is( ':visible' ) ) return;

        // Must be taller than the sticky element to be worth sliding
        var $fields = $( '.wpgt-styles-fields' );
        if ( ! $fields.length ) return;

        var adminBarH = $( '#wpadminbar' ).outerHeight() || 32;
        var topPad    = adminBarH + 20;

        // 1. Clear any previous transform so we measure the natural position
        $sticky.css( 'transform', 'none' );

        // 2. Measure where the element sits in the viewport right now
        var naturalTop = $sticky[0].getBoundingClientRect().top;

        // 3. How many px do we need to shift it to sit at topPad from top?
        var needed = topPad - naturalTop;

        // 4. Clamp: don't go above natural position; don't overshoot the column bottom
        var maxSlide = $fields.outerHeight() - $sticky.outerHeight() - 8;
        var offset   = Math.max( 0, Math.min( needed, Math.max( 0, maxSlide ) ) );

        $sticky.css( 'transform', offset > 0 ? 'translateY(' + offset + 'px)' : '' );
    }

    $( window ).on( 'scroll.wpgt resize.wpgt', updateStickyPreview );
    // Also fire when Elementor's resizer or any dynamic content changes layout
    $( document ).on( 'heartbeat-send', updateStickyPreview );

    /* ----------------------------------------------------------------
       WordPress color picker — only the main settings tabs
       (Styles tab initialises its own pickers separately)
    ---------------------------------------------------------------- */
    $( '.wpgt-color-picker' ).wpColorPicker();

} );
