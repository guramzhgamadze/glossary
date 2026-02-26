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

    // Restore active tab from session
    var saved = sessionStorage.getItem( 'wpgt_active_tab' );
    if ( saved && $( saved ).length ) {
        activateTab( saved );
    } else {
        // Show first tab
        var firstHash = $tabs.first().attr( 'href' );
        activateTab( firstHash );
    }

    $tabs.on( 'click', function( e ) {
        e.preventDefault();
        activateTab( $( this ).attr( 'href' ) );
    } );

    /* ----------------------------------------------------------------
       WordPress color picker
    ---------------------------------------------------------------- */
    $( '.wpgt-color-picker' ).wpColorPicker();

} );
