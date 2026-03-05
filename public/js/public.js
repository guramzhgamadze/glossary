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
    var READ_MORE_TXT= cfg.read_more_text   || 'Read more \u2192';
    

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
            html += '<a ' + attrs + '>' + escHtml(READ_MORE_TXT) + '</a>';
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
       Live search widget — WAI-ARIA 1.2 combobox pattern
       Spec: https://www.w3.org/WAI/ARIA/apg/patterns/combobox/
    ---------------------------------------------------------------- */
    function initSearchWidgets() {
        document.querySelectorAll('.wpgt-search-widget').forEach(function(widget) {
            var input   = widget.querySelector('.wpgt-search-input');
            var listbox = widget.querySelector('.wpgt-search-results');
            var clearBtn= widget.querySelector('.wpgt-search-clear');
            var status  = widget.querySelector('.wpgt-search-status');
            if (!input || !listbox) return;

            var deb        = null;
            var activeIdx  = -1;   // index of aria-activedescendant option
            var currentQ   = '';   // last fetched query
            var optPrefix  = input.id ? input.id.replace('wpgt-input','wpgt-opt') : 'wpgt-opt-x';

            // ── Helpers ──────────────────────────────────────────────
            function getOptions() {
                return Array.from(listbox.querySelectorAll('.wpgt-search-result-item'));
            }

            function setActive(idx) {
                var opts = getOptions();
                // Clear previous
                opts.forEach(function(o) {
                    o.classList.remove('wpgt-active');
                    o.setAttribute('aria-selected', 'false');
                });
                if (idx < 0 || idx >= opts.length) {
                    activeIdx = -1;
                    input.setAttribute('aria-activedescendant', '');
                    return;
                }
                activeIdx = idx;
                var target = opts[idx];
                target.classList.add('wpgt-active');
                target.setAttribute('aria-selected', 'true');
                // aria-activedescendant: DOM focus stays on input (WAI-ARIA spec §3.1)
                input.setAttribute('aria-activedescendant', target.id);
                // Scroll into view if needed
                target.scrollIntoView({ block: 'nearest' });
            }

            function openListbox() {
                listbox.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            }

            function closeListbox() {
                listbox.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                setActive(-1);
            }

            function setLoading(on) {
                widget.classList.toggle('wpgt-loading', on);
            }

            function updateClearBtn() {
                if (clearBtn) clearBtn.hidden = !input.value;
            }

            function announce(msg) {
                if (status) status.textContent = msg;
            }

            // ── Match highlighting ───────────────────────────────────
            // <mark> is the semantically correct element for search matches (HTML §4.5.23)
            function highlightMatch(text, query) {
                if (!query) return escHtml(text);
                var ltext = text.toLowerCase();
                var lq    = query.toLowerCase();
                var idx   = ltext.indexOf(lq);
                if (idx === -1) return escHtml(text);
                return escHtml(text.substring(0, idx))
                    + '<mark class="wpgt-search-match">'
                    + escHtml(text.substring(idx, idx + query.length))
                    + '</mark>'
                    + escHtml(text.substring(idx + query.length));
            }

            // ── Render results ───────────────────────────────────────
            function renderResults(items, q) {
                listbox.innerHTML = '';
                activeIdx = -1;
                input.setAttribute('aria-activedescendant', '');

                if (!items || !items.length) {
                    var none = document.createElement('span');
                    none.className = 'wpgt-search-no-results';
                    none.setAttribute('role', 'option');
                    none.textContent = wpgtData.i18n ? wpgtData.i18n.noResults : 'No terms found.';
                    listbox.appendChild(none);
                    openListbox();
                    announce(wpgtData.i18n ? wpgtData.i18n.noResults : 'No terms found.');
                    return;
                }

                items.forEach(function(item, i) {
                    var a = document.createElement('a');
                    // Unique ID per option — required for aria-activedescendant
                    a.id   = optPrefix + '-' + i;
                    a.href = item.url || '#';
                    a.className = 'wpgt-search-result-item';
                    a.setAttribute('role', 'option');
                    a.setAttribute('aria-selected', 'false');
                    // tabindex="-1": focusable programmatically, not in tab order
                    // (DOM focus stays on input per WAI-ARIA combobox spec)
                    a.setAttribute('tabindex', '-1');
                    if (LINK_NEWTAB) { a.target = '_blank'; a.rel = 'noopener noreferrer'; }
                    a.innerHTML =
                        '<span class="wpgt-search-result-title">'   + highlightMatch(item.title || '', q)       + '</span>'
                      + '<span class="wpgt-search-result-excerpt">' + escHtml(item.tooltip_text || '') + '</span>';
                    listbox.appendChild(a);
                });

                openListbox();
                var count = items.length;
                announce(count + (count === 1
                    ? (wpgtData.i18n ? wpgtData.i18n.resultSingular : ' result found.')
                    : (wpgtData.i18n ? wpgtData.i18n.resultPlural   : ' results found.')));
            }

            // ── Fetch ────────────────────────────────────────────────
            function doSearch(q) {
                currentQ = q;
                setLoading(true);
                fetch(restUrl + 'search?q=' + encodeURIComponent(q), { headers: { 'X-WP-Nonce': nonce } })
                    .then(function(r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function(data) { renderResults(data, q); })
                    .catch(function() {
                        listbox.innerHTML = '<span class="wpgt-search-error" role="alert">'
                            + (wpgtData.i18n ? wpgtData.i18n.error : 'Search error. Please try again.') + '</span>';
                        openListbox();
                        announce(wpgtData.i18n ? wpgtData.i18n.error : 'Search error.');
                    })
                    .finally(function() { setLoading(false); });
            }

            // ── Input events ─────────────────────────────────────────
            input.addEventListener('input', function() {
                clearTimeout(deb);
                updateClearBtn();
                var q = input.value.trim();
                if (q.length < 2) { closeListbox(); listbox.innerHTML = ''; return; }
                // 280 ms debounce — balances responsiveness vs network requests
                deb = setTimeout(function() { doSearch(q); }, 280);
            });

            // ── Keyboard navigation ──────────────────────────────────
            // Per WAI-ARIA combobox spec: DOM focus STAYS on input.
            // Arrow keys move aria-activedescendant; Enter navigates.
            input.addEventListener('keydown', function(e) {
                var opts = getOptions().filter(function(o) { return o.classList.contains('wpgt-search-result-item'); });

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (listbox.hidden && input.value.trim().length >= 2) { /* re-open if closed */ }
                    setActive(activeIdx < opts.length - 1 ? activeIdx + 1 : 0);
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    setActive(activeIdx > 0 ? activeIdx - 1 : opts.length - 1);
                    return;
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (activeIdx >= 0 && opts[activeIdx]) {
                        opts[activeIdx].click(); // follows href, respects target="_blank"
                    }
                    return;
                }
                if (e.key === 'Escape') {
                    closeListbox();
                    input.value = '';
                    updateClearBtn();
                    announce('');
                    return;
                }
                if (e.key === 'Tab') {
                    closeListbox();
                }
            });

            // ── Clear button ─────────────────────────────────────────
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    input.value = '';
                    closeListbox();
                    listbox.innerHTML = '';
                    updateClearBtn();
                    announce('');
                    input.focus();
                });
            }

            // ── Click outside to close ───────────────────────────────
            document.addEventListener('click', function(e) {
                if (!widget.contains(e.target)) closeListbox();
            });
        });
    }

    function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

    function init(){ setupDelegation(); initSearchWidgets(); }
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
    window.wpgtRebind = bindTriggers;
}());
