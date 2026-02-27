/**
 * WP Glossary Tooltip – Public JavaScript v1.0.5
 * Solid background, auto-width with min/max constraints.
 */
( function () {
    'use strict';

    var cfg     = ( typeof wpgtData !== 'undefined' ) ? wpgtData.settings : {};
    var restUrl = ( typeof wpgtData !== 'undefined' ) ? wpgtData.restUrl  : '';
    var nonce   = ( typeof wpgtData !== 'undefined' ) ? wpgtData.nonce    : '';

    var POSITION     = cfg.tooltip_position || 'top';
    var THEME        = cfg.tooltip_theme    || 'dark';
    var OPEN_ON      = cfg.open_on          || 'hover';
    var SHOW_MORE    = ( cfg.show_see_more  == '1' || cfg.show_see_more  === true );
    var LINK_NEWTAB  = ( cfg.link_new_tab   == '1' || cfg.link_new_tab   === true );
    var BRAND        = cfg.brand_color      || '#2563eb';
    var SEE_MORE_CLR = cfg.see_more_color   || '';

    /* Solid background colour per theme — no opacity/blur */
    var BG_COLOR;
    if ( cfg.tooltip_bg_color ) {
        BG_COLOR = cfg.tooltip_bg_color;
    } else if ( THEME === 'light' ) {
        BG_COLOR = '#ffffff';
    } else if ( THEME === 'branded' ) {
        BG_COLOR = BRAND;
    } else {
        BG_COLOR = '#1e293b';
    }

    /* Brand underline colour — only non-dynamic rule, so a tiny style tag */
    ( function() {
        var s = document.createElement('style');
        s.textContent =
            '.wpgt-tooltip-trigger{border-bottom-color:'+BRAND+' !important}' +
            '.wpgt-tooltip-trigger:hover,.wpgt-tooltip-trigger:focus{color:'+BRAND+' !important}';
        document.head.appendChild(s);
    } )();

    /* ----------------------------------------------------------------
       Tooltip bubble singleton
    ---------------------------------------------------------------- */
    var bubble = null, currentTrigger = null, hideTimer = null, ARROW_H = 7;

    function applyBubbleStyle( el ) {
        el.style.background = BG_COLOR;
        /* Width is controlled by CSS (min/max-content + clamp) — don't set inline width */
        el.style.backdropFilter       = '';
        el.style.webkitBackdropFilter = '';
    }

    function createBubble() {
        if ( bubble ) return bubble;
        bubble = document.createElement('div');
        bubble.className = 'wpgt-tooltip-bubble wpgt-theme-'+THEME;
        bubble.setAttribute('role','tooltip');
        applyBubbleStyle( bubble );
        document.body.appendChild(bubble);
        bubble.addEventListener('mouseenter', function(){ clearTimeout(hideTimer); });
        bubble.addEventListener('mouseleave', function(){ scheduleHide(); });
        return bubble;
    }

    function showTooltip( trigger ) {
        clearTimeout(hideTimer);
        currentTrigger = trigger;
        var b       = createBubble();
        var title   = trigger.dataset.title   || '';
        var tooltip = trigger.dataset.tooltip || '';
        var url     = trigger.dataset.url     || '';

        var html = '';
        if ( title )   html += '<strong class="wpgt-tooltip-title">'   + escHtml(title)   + '</strong>';
        if ( tooltip ) html += '<span class="wpgt-tooltip-text">'       + escHtml(tooltip) + '</span>';
        if ( SHOW_MORE && url ) {
            var attrs = 'href="' + url + '" class="wpgt-tooltip-see-more"';
            if ( LINK_NEWTAB )  attrs += ' target="_blank" rel="noopener noreferrer"';
            if ( SEE_MORE_CLR ) attrs += ' style="color:' + SEE_MORE_CLR + '"';
            html += '<a ' + attrs + '>Read more \u2192</a>';
        }
        b.innerHTML = html;

        applyBubbleStyle( b );
        b.id = 'wpgt-tip-' + ( trigger.dataset.wpgt || Math.random() );
        trigger.setAttribute('aria-describedby', b.id);
        b.classList.remove('wpgt-visible','wpgt-above','wpgt-below');
        b.style.left = '-9999px';
        b.style.top  = '-9999px';
        requestAnimationFrame(function(){ requestAnimationFrame(function(){
            positionBubble(trigger, b);
            b.classList.add('wpgt-visible');
        }); });
    }

    function positionBubble( trigger, b ) {
        var tr  = trigger.getBoundingClientRect();
        var bh  = b.offsetHeight, bw = b.offsetWidth;
        var vw  = window.innerWidth, vh = window.innerHeight;
        var GAP = 10 + ARROW_H;
        var left  = tr.left + tr.width/2 - bw/2;
        var above = ( POSITION !== 'bottom' ) && ( tr.top - bh - GAP >= 8 );
        var top   = above ? tr.top - bh - GAP : tr.bottom + GAP;
        top  = Math.max(8, Math.min(top,  vh - bh - 8));
        left = Math.max(8, Math.min(left, vw - bw  - 8));
        b.classList.remove('wpgt-above','wpgt-below');
        b.classList.add( above ? 'wpgt-above' : 'wpgt-below' );
        var arrowLeft = (tr.left + tr.width/2) - left;
        b.style.setProperty('--wpgt-arrow-left', Math.max(14,Math.min(arrowLeft,bw-14)) + 'px');
        b.style.setProperty('--wpgt-arrow-color', BG_COLOR);
        b.style.top  = top  + 'px';
        b.style.left = left + 'px';
    }

    function hideTooltip() {
        if (!bubble) return;
        bubble.classList.remove('wpgt-visible');
        if (currentTrigger) { currentTrigger.removeAttribute('aria-describedby'); currentTrigger=null; }
    }
    function scheduleHide(d){ hideTimer=setTimeout(hideTooltip, d||200); }

    /* ----------------------------------------------------------------
       Event delegation
    ---------------------------------------------------------------- */
    function isTrigger(el)     { return el && el.classList && el.classList.contains('wpgt-tooltip-trigger'); }
    function closestTrigger(el){ while(el&&el!==document.body){ if(isTrigger(el))return el; el=el.parentElement; } return null; }
    function bindTriggers()    { /* no-op: delegation handles everything */ }

    function setupDelegation() {
        if ( OPEN_ON === 'click' ) {
            document.addEventListener('click', function(e){
                var tr = closestTrigger(e.target);
                if (tr) { e.preventDefault(); (currentTrigger===tr&&bubble&&bubble.classList.contains('wpgt-visible'))?hideTooltip():showTooltip(tr); return; }
                if (bubble&&!bubble.contains(e.target)) hideTooltip();
            });
        } else {
            document.addEventListener('mouseover', function(e){ var tr=closestTrigger(e.target); if(tr){clearTimeout(hideTimer);showTooltip(tr);} });
            document.addEventListener('mouseout',  function(e){ var tr=closestTrigger(e.target); if(tr&&!(bubble&&bubble.contains(e.relatedTarget)))scheduleHide(); });
        }
        document.addEventListener('focusin',  function(e){ if(isTrigger(e.target))showTooltip(e.target); });
        document.addEventListener('focusout', function(e){ if(isTrigger(e.target))scheduleHide(300); });
        document.addEventListener('keydown',  function(e){ if(e.key==='Escape')hideTooltip(); });
        document.addEventListener('click',    function(e){ if(OPEN_ON==='click')return; if(bubble&&!bubble.contains(e.target)&&!closestTrigger(e.target))hideTooltip(); });
    }
    window.addEventListener('resize', function(){ if(currentTrigger&&bubble&&bubble.classList.contains('wpgt-visible'))positionBubble(currentTrigger,bubble); });

    /* ----------------------------------------------------------------
       Live search widget
    ---------------------------------------------------------------- */
    function initSearchWidgets() {
        document.querySelectorAll('.wpgt-search-widget').forEach(function(widget){
            var input=widget.querySelector('.wpgt-search-input'), results=widget.querySelector('.wpgt-search-results');
            if(!input||!results)return;
            var deb;
            input.addEventListener('input', function(){ clearTimeout(deb); var q=input.value.trim(); if(q.length<2){results.hidden=true;results.innerHTML='';return;} deb=setTimeout(function(){fetchSearch(q,results);},280); });
            input.addEventListener('keydown', function(e){ if(e.key==='Escape'){results.hidden=true;input.value='';} if(e.key==='ArrowDown'){var f=results.querySelector('.wpgt-search-result-item');if(f){e.preventDefault();f.focus();}} });
            results.addEventListener('keydown', function(e){ var items=Array.from(results.querySelectorAll('.wpgt-search-result-item')),idx=items.indexOf(document.activeElement); if(e.key==='ArrowDown'&&idx<items.length-1){e.preventDefault();items[idx+1].focus();} if(e.key==='ArrowUp'){e.preventDefault();(idx>0?items[idx-1]:input).focus();} if(e.key==='Escape'){results.hidden=true;input.focus();} });
            document.addEventListener('click', function(e){ if(!widget.contains(e.target))results.hidden=true; });
        });
    }

    function fetchSearch(q,el){
        fetch(restUrl+'search?q='+encodeURIComponent(q),{headers:{'X-WP-Nonce':nonce}})
            .then(function(r){return r.json();}).then(function(data){renderSearchResults(data,el);})
            .catch(function(){el.innerHTML='<span class="wpgt-search-no-results">Error.</span>';el.hidden=false;});
    }
    function renderSearchResults(items,el){
        el.innerHTML='';
        if(!items||!items.length){el.innerHTML='<span class="wpgt-search-no-results">No terms found.</span>';el.hidden=false;return;}
        items.forEach(function(item){
            var a=document.createElement('a'); a.href=item.url||'#'; a.className='wpgt-search-result-item'; a.setAttribute('role','option');
            if(LINK_NEWTAB){a.target='_blank';a.rel='noopener noreferrer';}
            a.innerHTML='<span class="wpgt-search-result-title">'+escHtml(item.title)+'</span><span class="wpgt-search-result-excerpt">'+escHtml(item.tooltip_text)+'</span>';
            el.appendChild(a);
        });
        el.hidden=false;
    }

    function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

    function init(){ setupDelegation(); initSearchWidgets(); }
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
    window.wpgtRebind = bindTriggers;
}());
