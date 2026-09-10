/**
 * Закреплённая шапка (FixedHeader) и липкий горизонтальный скролл таблиц отчётов.
 *
 * API: window.KidsCrmReportTableSticky.bind(selector)
 *
 * Страницы: #payments-table, #payments-monthly-table, #ltv-table, #debts-table,
 * #tbank-payments-table, #payment-intents-table, #fiscal-receipts-table, #emails-table.
 * Не пресет KidsCrmDataTable. Зависимости: jQuery, DataTables FixedHeader (опционально).
 *
 * Первый drawCallback бывает до обёртки .kids-dt-scroll-x (она на init.dt).
 * Тогда bind ждёт init.dt той же таблицы и повторяет — иначе полоса не создаётся,
 * а нативный скролл остаётся внизу длинной таблицы.
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

    function scheduleBindWhenHostReady(selector) {
        var ns = eventNs(selector);
        var eventName = 'init.dt.' + ns;

        $(document).off(eventName);
        $(document).on(eventName, function (event, settings) {
            if (!settings || !settings.nTable || !$(settings.nTable).is(selector)) {
                return;
            }
            $(document).off(eventName);
            bind(selector);
        });
    }

    function bindColumnEvents($table, selector) {
        var ns = eventNs(selector);
        var tableNode = $table.get(0);
        var tableApi;

        if ($table.data('kidsDtStickyColsBound')) {
            return;
        }

        if (!$.fn.dataTable || typeof $.fn.dataTable.isDataTable !== 'function' || !$.fn.dataTable.isDataTable(tableNode)) {
            return;
        }

        $table.data('kidsDtStickyColsBound', true);
        tableApi = $table.DataTable();
        tableApi.on('column-visibility.' + ns + ' column-sizing.' + ns, function () {
            window.requestAnimationFrame(function () {
                bind(selector);
                if (tableApi.fixedHeader && typeof tableApi.fixedHeader.adjust === 'function') {
                    tableApi.fixedHeader.adjust();
                }
            });
        });
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
            if ($table.length) {
                scheduleBindWhenHostReady(selector);
            }
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
            bindColumnEvents($table, selector);
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
