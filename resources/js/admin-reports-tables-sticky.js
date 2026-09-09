/**
 * Закреплённая шапка (FixedHeader) и липкий горизонтальный скролл таблиц отчётов.
 *
 * API: window.KidsCrmReportTableSticky.bind(selector)
 *
 * Страницы: #payments-table, #payments-monthly-table, #ltv-table, #debts-table.
 * Не пресет KidsCrmDataTable. Зависимости: jQuery, DataTables FixedHeader (опционально).
 */
(function (window, $) {
    'use strict';

    if (window.KidsCrmReportTableSticky) {
        return;
    }

    function scrollHost($table) {
        return $table.closest('.kids-dt-scroll-x');
    }

    function matchFloatingHeaderWidths(srcTable, cloneTable) {
        if (!srcTable || !cloneTable) {
            return;
        }

        var srcRow = srcTable.tBodies[0] && srcTable.tBodies[0].rows[0]
            ? srcTable.tBodies[0].rows[0]
            : (srcTable.tHead ? srcTable.tHead.rows[0] : null);
        var cloneThs = cloneTable.tHead ? cloneTable.tHead.querySelectorAll('th') : [];
        var srcCells = srcRow ? srcRow.cells : [];
        var i;
        var n = Math.min(srcCells.length, cloneThs.length);

        cloneTable.style.tableLayout = 'fixed';
        cloneTable.style.boxSizing = 'border-box';

        for (i = 0; i < n; i++) {
            var widthPx = srcCells[i].offsetWidth + 'px';
            cloneThs[i].style.boxSizing = 'border-box';
            cloneThs[i].style.width = widthPx;
            cloneThs[i].style.minWidth = widthPx;
            cloneThs[i].style.maxWidth = widthPx;
        }

        cloneTable.style.marginLeft = '0px';
        cloneTable.style.setProperty('width', srcTable.offsetWidth + 'px', 'important');
    }

    function syncHeaderHScroll($table) {
        var $host = scrollHost($table);
        var parent = document.querySelector('.dtfh-floatingparenthead');
        if (!$host.length || !parent) {
            return;
        }

        var hostEl = $host.get(0);
        var hostRect = hostEl.getBoundingClientRect();
        var style = parent.style;

        style.setProperty('left', hostRect.left + 'px', 'important');
        style.setProperty('width', hostRect.width + 'px', 'important');
        style.setProperty('overflow', 'hidden', 'important');

        matchFloatingHeaderWidths($table.get(0), parent.querySelector('table'));
        parent.scrollLeft = hostEl.scrollLeft;
    }

    function updateStickyHScroll($table) {
        var $host = scrollHost($table);
        var $wrapper = $table.closest('.dataTables_wrapper');
        var $bar = $wrapper.children('.kids-dt-sticky-hscroll');
        if (!$host.length || !$bar.length) {
            return;
        }

        var hostEl = $host.get(0);
        var tableWidth = hostEl.scrollWidth;
        var hostWidth = hostEl.clientWidth;
        var needsBar = tableWidth > hostWidth + 1;

        $bar.children('.kids-dt-sticky-hscroll-spacer').css('width', tableWidth + 'px');
        $host.toggleClass('kids-dt-scroll-x--has-sticky-bar', needsBar);
        $bar.prop('hidden', !needsBar);

        if (needsBar) {
            $bar.scrollLeft($host.scrollLeft());
        }

        syncHeaderHScroll($table);
    }

    function eventNs(selector) {
        return 'reportStickyH' + String(selector || '').replace(/[^a-zA-Z0-9_-]/g, '');
    }

    function bind(selector) {
        if (!$ || !selector) {
            return;
        }

        var $table = $(selector);
        var $wrapper = $table.closest('.dataTables_wrapper');
        var $host = scrollHost($table);
        var ns = eventNs(selector);

        if (!$table.length || !$wrapper.length || !$host.length) {
            return;
        }

        var $bar = $wrapper.children('.kids-dt-sticky-hscroll');
        if (!$bar.length) {
            $bar = $('<div class="kids-dt-sticky-hscroll" hidden aria-label="Горизонтальная прокрутка таблицы">'
                + '<div class="kids-dt-sticky-hscroll-spacer"></div>'
                + '</div>');
            $host.after($bar);

            var syncing = false;
            $host.on('scroll.' + ns, function () {
                if (syncing) {
                    return;
                }
                syncing = true;
                $bar.scrollLeft($host.scrollLeft());
                syncHeaderHScroll($table);
                syncing = false;
            });
            $bar.on('scroll.' + ns, function () {
                if (syncing) {
                    return;
                }
                syncing = true;
                $host.scrollLeft($bar.scrollLeft());
                syncHeaderHScroll($table);
                syncing = false;
            });
            $(window).on('resize.' + ns, function () {
                updateStickyHScroll($table);
            });
            $(window).on('scroll.' + ns, function () {
                syncHeaderHScroll($table);
            });
        }

        updateStickyHScroll($table);
    }

    window.KidsCrmReportTableSticky = {
        bind: bind,
        update: function (selector) {
            if (!$ || !selector) {
                return;
            }

            updateStickyHScroll($(selector));
        },
    };
})(window, window.jQuery);
