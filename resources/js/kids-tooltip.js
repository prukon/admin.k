/**
 * Единый модуль подсказок Kids CRM (Bootstrap Tooltip).
 *
 * API: window.KidsCrmTooltip
 */
(function (window) {
    'use strict';

    if (window.KidsCrmTooltip) {
        return;
    }

    const TOOLTIP_CLASS = 'ulp-assignment-paid-tooltip';
    const LIST_TOOLTIP_CLASS = 'kids-hover-list-tooltip ulp-assignment-paid-tooltip';

    const SCOPES = {
        list: '.js-kids-hover-list-dropdown',
        text: '.js-dt-cell-ellipsis-tooltip',
        hint: '[data-kids-tooltip-hint][data-bs-toggle="tooltip"]',
        manualPaid: '.ulp-paid-manual-hint[data-bs-toggle="tooltip"], .user-manual-info-icon[data-bs-toggle="tooltip"]',
        generic: '[data-bs-toggle="tooltip"]',
    };

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Yajra DataTables экранирует текст в JSON (&quot; и т.д.).
     * Перед повторным escapeHtml в ячейке — нормализуем к обычной строке.
     */
    function decodeHtmlEntities(value) {
        let s = String(value || '');
        if (s.indexOf('&') === -1) {
            return s;
        }

        let prev;
        let guard = 0;

        do {
            prev = s;
            s = s
                .replace(/&amp;/g, '&')
                .replace(/&quot;/g, '"')
                .replace(/&#0*39;/g, "'")
                .replace(/&#x27;/gi, "'")
                .replace(/&lt;/g, '<')
                .replace(/&gt;/g, '>');
            guard++;
        } while (s !== prev && guard < 5);

        return s;
    }

    function normalizeDisplayText(value) {
        return decodeHtmlEntities(String(value || '')).trim();
    }

    function normalizeItems(items) {
        if (!Array.isArray(items)) {
            return [];
        }

        return items
            .map(function (item) {
                return String(item || '').trim();
            })
            .filter(function (item) {
                return item !== '';
            });
    }

    function buildListTooltipTitle(items) {
        const listItems = items
            .map(function (item) {
                return '<li>' + escapeHtml(item) + '</li>';
            })
            .join('');

        return '<ul class="kids-hover-list-tooltip__list">' + listItems + '</ul>';
    }

    function bootstrapAvailable() {
        return typeof bootstrap !== 'undefined' && bootstrap.Tooltip;
    }

    function disposeElement(el) {
        if (!bootstrapAvailable()) {
            return;
        }

        const existing = bootstrap.Tooltip.getInstance(el);
        if (existing) {
            existing.dispose();
        }
    }

    function initListElement(el) {
        disposeElement(el);

        let items = [];
        const rawItems = el.getAttribute('data-kids-hover-list-items');

        if (rawItems) {
            try {
                items = normalizeItems(JSON.parse(rawItems));
            } catch (error) {
                items = [];
            }
        }

        if (items.length >= 2) {
            el.setAttribute('title', buildListTooltipTitle(items));
        }

        new bootstrap.Tooltip(el, {
            html: true,
            placement: el.getAttribute('data-bs-placement') || 'top',
            customClass: LIST_TOOLTIP_CLASS,
            trigger: 'hover focus',
        });
    }

    function isTextOverflowing(el) {
        return el.scrollWidth > el.clientWidth + 1;
    }

    function initTextElement(el) {
        disposeElement(el);
        el.classList.remove('dt-cell-ellipsis--truncated');
        el.removeAttribute('data-bs-toggle');

        const isEllipsisCell = el.classList.contains('dt-cell-ellipsis');

        if (isEllipsisCell) {
            el.removeAttribute('title');

            if (!isTextOverflowing(el)) {
                return;
            }

            const title = el.getAttribute('data-dt-ellipsis-title') || el.textContent.trim();
            if (!title) {
                return;
            }

            el.setAttribute('title', title);
            el.setAttribute('data-bs-toggle', 'tooltip');
            el.setAttribute('data-bs-placement', 'top');
            el.setAttribute('data-bs-custom-class', TOOLTIP_CLASS);
            el.classList.add('dt-cell-ellipsis--truncated');
        }

        if (!el.getAttribute('data-bs-toggle')) {
            return;
        }

        new bootstrap.Tooltip(el, {
            placement: el.getAttribute('data-bs-placement') || 'top',
            customClass: el.getAttribute('data-bs-custom-class') || TOOLTIP_CLASS,
            trigger: 'hover focus',
        });
    }

    function initHintElement(el) {
        disposeElement(el);

        new bootstrap.Tooltip(el, {
            placement: el.getAttribute('data-bs-placement') || 'top',
            customClass: TOOLTIP_CLASS,
            trigger: 'hover focus',
        });
    }

    function initManualPaidElement(el) {
        disposeElement(el);

        new bootstrap.Tooltip(el, {
            placement: el.getAttribute('data-bs-placement') || 'top',
            customClass: el.getAttribute('data-bs-custom-class') || TOOLTIP_CLASS,
            trigger: 'hover focus',
        });
    }

    function initGenericElement(el) {
        disposeElement(el);

        if (!el.getAttribute('title')) {
            const bsTitle = el.getAttribute('data-bs-title');
            if (bsTitle) {
                el.setAttribute('title', bsTitle);
            }
        }

        new bootstrap.Tooltip(el, {
            html: el.getAttribute('data-bs-html') === 'true',
            placement: el.getAttribute('data-bs-placement') || 'top',
            customClass: el.getAttribute('data-bs-custom-class') || TOOLTIP_CLASS,
            trigger: 'hover focus',
        });
    }

    function resolveScopes(options) {
        if (!options || !options.scopes) {
            return ['list', 'text', 'hint'];
        }

        return options.scopes.filter(function (scope) {
            return Object.prototype.hasOwnProperty.call(SCOPES, scope);
        });
    }

    function queryElements(root, scopes) {
        const base = root || document;
        const elements = [];

        scopes.forEach(function (scope) {
            base.querySelectorAll(SCOPES[scope]).forEach(function (el) {
                elements.push({ scope: scope, el: el });
            });
        });

        return elements;
    }

    const KidsCrmTooltip = {
        escapeHtml: escapeHtml,
        decodeHtmlEntities: decodeHtmlEntities,
        normalizeDisplayText: normalizeDisplayText,

        renderText: function (text, options) {
            options = options || {};
            const raw = normalizeDisplayText(text);

            if (raw === '') {
                return options.emptyHtml || '<span class="dt-cell-empty text-muted">—</span>';
            }

            const escaped = escapeHtml(raw);

            return '<span class="dt-cell-ellipsis js-dt-cell-ellipsis-tooltip" '
                + 'data-dt-ellipsis-title="' + escaped + '" '
                + 'tabindex="0" '
                + 'aria-label="' + escaped + '">'
                + escaped
                + '</span>';
        },

        renderLink: function (text, options) {
            options = options || {};
            const inner = KidsCrmTooltip.renderText(text, options);

            if (inner.indexOf('js-dt-cell-ellipsis-tooltip') === -1) {
                return inner;
            }

            const linkClass = String(options.linkClass || '').trim();
            const extraAttrs = options.extraAttrs || '';
            const hrefOption = options.href;
            const href = (hrefOption != null && String(hrefOption).trim() !== '')
                ? String(hrefOption)
                : 'javascript:void(0);';

            return '<a href="' + escapeHtml(href) + '" class="' + escapeHtml(linkClass) + '" ' + extraAttrs + '>'
                + inner
                + '</a>';
        },

        renderList: function (shortLabel, items, options) {
            options = options || {};
            const titles = normalizeItems(items);
            const label = String(shortLabel || '').trim();

            if (titles.length === 0) {
                return options.emptyHtml || '<span class="dt-cell-empty text-muted">—</span>';
            }

            const visibleLabel = label !== '' ? label : titles.join(', ');
            const minItemsForHover = typeof options.minItemsForHover === 'number'
                ? options.minItemsForHover
                : 2;

            if (titles.length < minItemsForHover) {
                return escapeHtml(visibleLabel);
            }

            return '<span class="js-kids-hover-list-dropdown kids-hover-list-dropdown__trigger" '
                + 'data-bs-toggle="tooltip" '
                + 'data-bs-html="true" '
                + 'data-bs-placement="top" '
                + 'data-bs-custom-class="' + LIST_TOOLTIP_CLASS + '" '
                + 'data-kids-hover-list-items="' + escapeHtml(JSON.stringify(titles)) + '" '
                + 'title="' + escapeHtml(buildListTooltipTitle(titles)) + '" '
                + 'tabindex="0" '
                + 'aria-label="' + escapeHtml(visibleLabel) + '">'
                + escapeHtml(visibleLabel)
                + '</span>';
        },

        init: function (root, options) {
            if (!bootstrapAvailable()) {
                return;
            }

            const scopes = resolveScopes(options);

            queryElements(root, scopes).forEach(function (entry) {
                if (entry.scope === 'list') {
                    initListElement(entry.el);
                } else if (entry.scope === 'text') {
                    initTextElement(entry.el);
                } else if (entry.scope === 'hint') {
                    initHintElement(entry.el);
                } else if (entry.scope === 'manualPaid') {
                    initManualPaidElement(entry.el);
                } else if (entry.scope === 'generic') {
                    initGenericElement(entry.el);
                }
            });
        },

        dispose: function (root, options) {
            if (!bootstrapAvailable()) {
                return;
            }

            const scopes = resolveScopes(options);

            queryElements(root, scopes).forEach(function (entry) {
                disposeElement(entry.el);
            });
        },

        bindDataTable: function (tableElement) {
            if (!tableElement) {
                return;
            }

            const init = function () {
                requestAnimationFrame(function () {
                    KidsCrmTooltip.init(tableElement, { scopes: ['text', 'list', 'manualPaid'] });
                });
            };

            if (typeof $ !== 'undefined' && $.fn.DataTable && $.fn.DataTable.isDataTable(tableElement)) {
                $(tableElement)
                    .off('draw.dt.kidsCrmTooltip')
                    .on('draw.dt.kidsCrmTooltip', init);
            }

            init();
        },
    };

    window.KidsCrmTooltip = KidsCrmTooltip;

    document.addEventListener('DOMContentLoaded', function () {
        KidsCrmTooltip.init(document, { scopes: ['hint'] });
    });
})(window);
