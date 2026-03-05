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
       WordPress color picker — only the main settings tabs
       (Styles tab initialises its own pickers separately)
    ---------------------------------------------------------------- */
    $( '.wpgt-color-picker' ).wpColorPicker();

} );
