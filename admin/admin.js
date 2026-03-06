/* WP Glossary Tooltip — Admin JS  v2.0.2 */
jQuery(function ($) {

    /* ------------------------------------------------------------------
       TAB PANEL SWITCHING
       Topbar: .wpgt-topbar nav a.wpgt-tab-link[data-panel]
       Panels: div.wpgt-tab-panel#wpgt-panel-*
    ------------------------------------------------------------------ */
    var $links  = $('.wpgt-topbar nav a.wpgt-tab-link');
    var $panels = $('.wpgt-tab-panel');

    function activatePanel(id) {
        if (!id || !$('#' + id).length) return;
        $panels.removeClass('wpgt-active');
        $links.removeClass('wpgt-active');
        $('#' + id).addClass('wpgt-active');
        $links.filter('[data-panel="' + id + '"]').addClass('wpgt-active');
        sessionStorage.setItem('wpgt_panel', id);
        if (window.history && history.replaceState) {
            history.replaceState(null, '', location.pathname + location.search + '#' + id);
        }
    }

    $links.on('click', function (e) {
        e.preventDefault();
        activatePanel($(this).data('panel'));
    });

    /* Priority: URL hash → sessionStorage → default (general) */
    var fromHash = (location.hash || '').replace('#', '');
    var fromSess = sessionStorage.getItem('wpgt_panel') || '';
    var initial  = ($('#' + fromHash).hasClass('wpgt-tab-panel')) ? fromHash
                 : ($('#' + fromSess).hasClass('wpgt-tab-panel')) ? fromSess
                 : 'wpgt-panel-general';
    activatePanel(initial);

    /* ------------------------------------------------------------------
       SORT TAB — jQuery UI Sortable
    ------------------------------------------------------------------ */
    if ($('#wpgt-sortable').length) {
        $('#wpgt-sortable').sortable({
            placeholder: 'wpgt-sort-ph',
            handle: '.wpgt-drag-icon',
            update: function () {
                var ids = [];
                $('#wpgt-sortable li').each(function () {
                    ids.push($(this).data('id'));
                });
                $.post(ajaxurl, {
                    action:   'wpgt_save_order',
                    order:    ids,
                    _wpnonce: wpgtAdmin.sortNonce
                }, function (r) {
                    if (r.success) {
                        $('#wpgt-order-saved').fadeIn().delay(2000).fadeOut();
                    }
                });
            }
        });
    }

    /* ------------------------------------------------------------------
       WORDPRESS COLOR PICKER — standard settings tabs
    ------------------------------------------------------------------ */
    $('.wpgt-color-picker').wpColorPicker();

});
