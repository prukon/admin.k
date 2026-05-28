{{--
    Hover-список в стиле подсказок «Установка цен → По месяцам» (Bootstrap Tooltip + ulp-assignment-paid-tooltip).

    Подключение:
    @include('partials.ui.hover-list-dropdown')

    JS:
    KidsCrmHoverListDropdown.renderCell(shortLabel, itemsArray)
    KidsCrmHoverListDropdown.init(rootElement)
--}}
@once
    @push('styles')
        <style>
            .kids-hover-list-dropdown__trigger {
                cursor: help;
                border-bottom: 1px dotted rgba(33, 37, 41, 0.45);
            }

            .tooltip.kids-hover-list-tooltip .tooltip-inner,
            .tooltip.ulp-assignment-paid-tooltip.kids-hover-list-tooltip .tooltip-inner {
                max-width: min(22rem, 85vw);
                text-align: left;
                white-space: normal;
                font-size: 0.8125rem;
                line-height: 1.45;
            }

            .kids-hover-list-tooltip__list {
                margin: 0;
                padding-left: 1.1rem;
                text-align: left;
            }

            .kids-hover-list-tooltip__list li {
                margin-bottom: 0.15rem;
            }

            .kids-hover-list-tooltip__list li:last-child {
                margin-bottom: 0;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function (window) {
                'use strict';

                if (window.KidsCrmHoverListDropdown) {
                    return;
                }

                function escapeHtml(value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
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

                function buildTooltipTitle(items) {
                    const listItems = items
                        .map(function (item) {
                            return '<li>' + escapeHtml(item) + '</li>';
                        })
                        .join('');

                    return '<ul class="kids-hover-list-tooltip__list">' + listItems + '</ul>';
                }

                window.KidsCrmHoverListDropdown = {
                    renderCell: function (shortLabel, items, options) {
                        options = options || {};
                        const titles = normalizeItems(items);
                        const label = String(shortLabel || '').trim();

                        if (titles.length === 0) {
                            return '<span class="text-muted">—</span>';
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
                            + 'data-bs-custom-class="kids-hover-list-tooltip ulp-assignment-paid-tooltip" '
                            + 'data-kids-hover-list-items="' + escapeHtml(JSON.stringify(titles)) + '" '
                            + 'title="' + escapeHtml(buildTooltipTitle(titles)) + '" '
                            + 'tabindex="0" '
                            + 'aria-label="' + escapeHtml(visibleLabel) + '">'
                            + escapeHtml(visibleLabel)
                            + '</span>';
                    },

                    init: function (root) {
                        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
                            return;
                        }

                        const base = root || document;
                        base.querySelectorAll('.js-kids-hover-list-dropdown').forEach(function (el) {
                            const existing = bootstrap.Tooltip.getInstance(el);
                            if (existing) {
                                existing.dispose();
                            }

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
                                el.setAttribute('title', buildTooltipTitle(items));
                            }

                            new bootstrap.Tooltip(el, {
                                html: true,
                                placement: 'top',
                                customClass: 'kids-hover-list-tooltip ulp-assignment-paid-tooltip',
                            });
                        });
                    },

                    dispose: function (root) {
                        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
                            return;
                        }

                        const base = root || document;
                        base.querySelectorAll('.js-kids-hover-list-dropdown').forEach(function (el) {
                            const existing = bootstrap.Tooltip.getInstance(el);
                            if (existing) {
                                existing.dispose();
                            }
                        });
                    }
                };
            })(window);
        </script>
    @endpush
@endonce
