/**
 * WP Glossary Tooltip – Public JavaScript
 * Handles tooltip rendering, positioning, and live search widget.
 */
( function () {
    'use strict';

    /* ----------------------------------------------------------------
       Configuration from wp_localize_script
    ---------------------------------------------------------------- */
    var cfg = ( typeof wpgtData !== 'undefined' ) ? wpgtData.settings : {};
    var restUrl = ( typeof wpgtData !== 'undefined' ) ? wpgtData.restUrl : '';
    var nonce   = ( typeof wpgtData !== 'undefined' ) ? wpgtData.nonce   : '';

    var POSITION    = cfg.tooltip_position || 'top';
    var THEME       = cfg.tooltip_theme    || 'dark';
    var OPEN_ON     = cfg.open_on          || 'hover';
    var SHOW_MORE   = cfg.show_see_more    !== false;
    var LINK_NEWTAB = !! cfg.link_new_tab;  // wp_localize_script gives '1' or '', not true/false
    var WIDTH       = parseInt( cfg.tooltip_width, 10 ) || 280;
    var OPACITY     = ( cfg.glass_opacity !== undefined ) ? parseInt( cfg.glass_opacity, 10 ) : 85;
    var BLUR        = ( cfg.glass_blur    !== undefined ) ? parseInt( cfg.glass_blur,    10 ) : 12;

    /* apply CSS variables — set full rgba colors from JS to avoid rgba(var()) browser issues */
    var alpha = ( OPACITY / 100 ).toFixed(2);
    document.documentElement.style.setProperty( '--wpgt-width',          WIDTH + 'px' );
    document.documentElement.style.setProperty( '--wpgt-glass-blur',     BLUR  + 'px' );
    document.documentElement.style.setProperty( '--wpgt-dark-glass-bg',  'rgba(20,30,48,'    + alpha + ')' );
    document.documentElement.style.setProperty( '--wpgt-light-glass-bg', 'rgba(255,255,255,' + alpha + ')' );
    // branded uses brand color — compute it
    var brandHex = cfg.brand_color || '#2563eb';
    var br = parseInt( brandHex.slice(1,3), 16 );
    var bg = parseInt( brandHex.slice(3,5), 16 );
    var bb = parseInt( brandHex.slice(5,7), 16 );
    document.documentElement.style.setProperty( '--wpgt-branded-glass-bg', 'rgba(' + br + ',' + bg + ',' + bb + ',' + alpha + ')' );
    if ( cfg.brand_color ) {
        document.documentElement.style.setProperty( '--wpgt-brand', cfg.brand_color );
    }

    /* ----------------------------------------------------------------
       Tooltip bubble singleton
    ---------------------------------------------------------------- */
    var bubble   = null;
    var currentTrigger = null;
    var hideTimer      = null;

    function createBubble() {
        if ( bubble ) return bubble;
        bubble = document.createElement( 'div' );
        bubble.className = 'wpgt-tooltip-bubble wpgt-theme-' + THEME + ' wpgt-pos-' + POSITION;
        bubble.setAttribute( 'role', 'tooltip' );
        document.body.appendChild( bubble );

        // Keep tooltip open when mouse is over it
        bubble.addEventListener( 'mouseenter', function () {
            clearTimeout( hideTimer );
        } );
        bubble.addEventListener( 'mouseleave', function () {
            scheduleHide();
        } );

        return bubble;
    }

    function showTooltip( trigger ) {
        clearTimeout( hideTimer );
        currentTrigger = trigger;

        var b       = createBubble();
        var title   = trigger.dataset.title   || '';
        var tooltip = trigger.dataset.tooltip || '';
        var url     = trigger.dataset.url     || '';

        var html = '';
        if ( title ) {
            html += '<strong class="wpgt-tooltip-title">' + escHtml( title ) + '</strong>';
        }
        if ( tooltip ) {
            html += '<span class="wpgt-tooltip-text">' + escHtml( tooltip ) + '</span>';
        }
        if ( SHOW_MORE && url ) {
            var target = LINK_NEWTAB ? ' target="_blank" rel="noopener noreferrer"' : '';
            html += '<a href="' + url + '" class="wpgt-tooltip-see-more"' + target + '>Read more →</a>';
        }

        b.innerHTML = html;

        // Set ARIA
        var tipId = 'wpgt-tip-' + ( trigger.dataset.wpgt || Math.random() );
        b.id = tipId;
        trigger.setAttribute( 'aria-describedby', tipId );

        b.classList.remove( 'wpgt-visible', 'wpgt-above', 'wpgt-below' );
        b.style.left = '-9999px';
        b.style.top  = '-9999px';

        // Two rAFs: first lets browser render content to measure height,
        // second applies the final position and shows the bubble.
        requestAnimationFrame( function () {
            requestAnimationFrame( function () {
                positionBubble( trigger, b );
                b.classList.add( 'wpgt-visible' );
            } );
        } );
    }

    var ARROW_H = 7; // must match border width in CSS ::after

    function positionBubble( trigger, b ) {
        // Bubble is position:fixed — use viewport coords (no scroll offset needed)
        var tr  = trigger.getBoundingClientRect();
        var bh  = b.offsetHeight;
        var vw  = window.innerWidth;
        var vh  = window.innerHeight;
        var GAP = 10 + ARROW_H; // space between trigger and bubble

        var left = tr.left + ( tr.width / 2 ) - ( WIDTH / 2 );
        var top;
        var above;

        // Prefer above; flip below if not enough room
        if ( POSITION === 'bottom' ) {
            above = false;
        } else if ( tr.top - bh - GAP >= 8 ) {
            above = true;
        } else {
            above = false;
        }

        if ( above ) {
            top = tr.top - bh - GAP;
        } else {
            top = tr.bottom + GAP;
        }

        // Clamp vertically so it doesn't go off screen
        top = Math.max( 8, Math.min( top, vh - bh - 8 ) );

        // Clamp horizontally
        left = Math.max( 8, Math.min( left, vw - WIDTH - 8 ) );

        // Set arrow direction class
        b.classList.remove( 'wpgt-above', 'wpgt-below' );
        b.classList.add( above ? 'wpgt-above' : 'wpgt-below' );

        // Move arrow horizontally to point at the trigger word center
        var triggerCenter = tr.left + ( tr.width / 2 );
        var arrowLeft = triggerCenter - left;
        arrowLeft = Math.max( 14, Math.min( arrowLeft, WIDTH - 14 ) );
        b.style.setProperty( '--wpgt-arrow-left', arrowLeft + 'px' );

        b.style.top  = top  + 'px';
        b.style.left = left + 'px';
    }

    function hideTooltip() {
        if ( ! bubble ) return;
        bubble.classList.remove( 'wpgt-visible' );
        if ( currentTrigger ) {
            currentTrigger.removeAttribute( 'aria-describedby' );
            currentTrigger = null;
        }
    }

    function scheduleHide( delay ) {
        hideTimer = setTimeout( hideTooltip, delay || 200 );
    }

    /* ----------------------------------------------------------------
       Event binding — use delegation so Elementor lazy-rendered spans work too.
       We attach one listener to document for each event type.
    ---------------------------------------------------------------- */
    function isTrigger( el ) {
        return el && el.classList && el.classList.contains( 'wpgt-tooltip-trigger' );
    }

    function closestTrigger( el ) {
        while ( el && el !== document.body ) {
            if ( isTrigger( el ) ) return el;
            el = el.parentElement;
        }
        return null;
    }

    function bindTriggers() {
        // Delegation is set up once in init(); this is a no-op kept for back-compat.
    }

    function setupDelegation() {
        if ( OPEN_ON === 'click' ) {
            document.addEventListener( 'click', function ( e ) {
                var tr = closestTrigger( e.target );
                if ( tr ) {
                    e.preventDefault();
                    if ( currentTrigger === tr && bubble && bubble.classList.contains( 'wpgt-visible' ) ) {
                        hideTooltip();
                    } else {
                        showTooltip( tr );
                    }
                    return;
                }
                // Click outside — close
                if ( bubble && ! bubble.contains( e.target ) ) {
                    hideTooltip();
                }
            } );
        } else {
            // hover
            document.addEventListener( 'mouseover', function ( e ) {
                var tr = closestTrigger( e.target );
                if ( tr ) {
                    clearTimeout( hideTimer );
                    showTooltip( tr );
                }
            } );
            document.addEventListener( 'mouseout', function ( e ) {
                var tr = closestTrigger( e.target );
                if ( tr ) {
                    // Only schedule hide if not moving to bubble
                    var related = e.relatedTarget;
                    if ( bubble && bubble.contains( related ) ) return;
                    scheduleHide();
                }
            } );
        }

        // Keyboard — focus/blur via delegation
        document.addEventListener( 'focusin', function ( e ) {
            if ( isTrigger( e.target ) ) showTooltip( e.target );
        } );
        document.addEventListener( 'focusout', function ( e ) {
            if ( isTrigger( e.target ) ) scheduleHide( 300 );
        } );
        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) hideTooltip();
        } );
    }

    /* Close on outside click (hover mode) */
    document.addEventListener( 'click', function ( e ) {
        if ( OPEN_ON === 'click' ) return; // handled above
        if ( bubble && ! bubble.contains( e.target ) && ! closestTrigger( e.target ) ) {
            hideTooltip();
        }
    } );

    /* Re-position on resize */
    var resizeTimer;
    window.addEventListener( 'resize', function () {
        clearTimeout( resizeTimer );
        resizeTimer = setTimeout( function () {
            if ( currentTrigger && bubble && bubble.classList.contains( 'wpgt-visible' ) ) {
                positionBubble( currentTrigger, bubble );
            }
        }, 100 );
    } );

    /* ----------------------------------------------------------------
       Live search widget
    ---------------------------------------------------------------- */
    function initSearchWidgets() {
        var widgets = document.querySelectorAll( '.wpgt-search-widget' );
        widgets.forEach( function ( widget ) {
            var input   = widget.querySelector( '.wpgt-search-input'   );
            var results = widget.querySelector( '.wpgt-search-results' );
            if ( ! input || ! results ) return;

            var debounce;

            input.addEventListener( 'input', function () {
                clearTimeout( debounce );
                var q = input.value.trim();
                if ( q.length < 2 ) {
                    results.hidden = true;
                    results.innerHTML = '';
                    return;
                }
                debounce = setTimeout( function () {
                    fetchSearch( q, results );
                }, 280 );
            } );

            input.addEventListener( 'keydown', function ( e ) {
                if ( e.key === 'Escape' ) {
                    results.hidden = true;
                    input.value = '';
                }
                if ( e.key === 'ArrowDown' ) {
                    var first = results.querySelector( '.wpgt-search-result-item' );
                    if ( first ) { e.preventDefault(); first.focus(); }
                }
            } );

            results.addEventListener( 'keydown', function ( e ) {
                var items = Array.from( results.querySelectorAll( '.wpgt-search-result-item' ) );
                var idx   = items.indexOf( document.activeElement );
                if ( e.key === 'ArrowDown' && idx < items.length - 1 ) { e.preventDefault(); items[ idx + 1 ].focus(); }
                if ( e.key === 'ArrowUp'  ) { e.preventDefault(); ( idx > 0 ? items[ idx - 1 ] : input ).focus(); }
                if ( e.key === 'Escape'   ) { results.hidden = true; input.focus(); }
            } );

            document.addEventListener( 'click', function ( e ) {
                if ( ! widget.contains( e.target ) ) results.hidden = true;
            } );
        } );
    }

    function fetchSearch( q, resultsEl ) {
        var url = restUrl + 'search?q=' + encodeURIComponent( q );
        fetch( url, {
            headers: { 'X-WP-Nonce': nonce }
        } )
            .then( function ( r ) { return r.json(); } )
            .then( function ( data ) {
                renderSearchResults( data, resultsEl );
            } )
            .catch( function () {
                resultsEl.innerHTML = '<span class="wpgt-search-no-results">Error fetching results.</span>';
                resultsEl.hidden = false;
            } );
    }

    function renderSearchResults( items, el ) {
        el.innerHTML = '';
        if ( ! items || ! items.length ) {
            el.innerHTML = '<span class="wpgt-search-no-results">No terms found.</span>';
            el.hidden = false;
            return;
        }
        items.forEach( function ( item ) {
            var a = document.createElement( 'a' );
            a.href      = item.url || '#';
            a.className = 'wpgt-search-result-item';
            a.setAttribute( 'role', 'option' );
            a.innerHTML =
                '<span class="wpgt-search-result-title">'   + escHtml( item.title        ) + '</span>' +
                '<span class="wpgt-search-result-excerpt">' + escHtml( item.tooltip_text ) + '</span>';
            el.appendChild( a );
        } );
        el.hidden = false;
    }

    /* ----------------------------------------------------------------
       Helpers
    ---------------------------------------------------------------- */
    function escHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;'  )
            .replace( /</g, '&lt;'   )
            .replace( />/g, '&gt;'   )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#039;' );
    }

    /* ----------------------------------------------------------------
       Initialise
    ---------------------------------------------------------------- */
    function init() {
        setupDelegation();   // replaces direct bindTriggers() — works with Elementor lazy render
        initSearchWidgets();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    // Support dynamic content (e.g. AJAX page builders)
    window.wpgtRebind = bindTriggers;

} )();
