/**
 * Пресет DataTables для admin-списков Kids CRM.
 *
 * API: window.KidsCrmDataTable.create(selector, options)
 *
 * Зависимости: jQuery, DataTables, KidsCrmTooltip (layouts/admin2 через Vite).
 */
(function (window, $) {
    'use strict';

    if (window.KidsCrmDataTable) {
        return;
    }

    const DEFAULTS = {
        processing: true,
        serverSide: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [10, 20, 50, 100],
    };

    function toBool(val, fallback) {
        if (val === undefined || val === null) {
            return fallback;
        }

        if (typeof val === 'boolean') {
            return val;
        }

        if (typeof val === 'number') {
            return val === 1;
        }

        if (typeof val === 'string') {
            const normalized = val.toLowerCase().trim();
            if (normalized === 'true' || normalized === '1') {
                return true;
            }
            if (normalized === 'false' || normalized === '0') {
                return false;
            }
        }

        return fallback;
    }

    function displayOrRaw(data, type) {
        if (type !== 'display') {
            return data || '';
        }

        return window.KidsCrmTooltip.renderText(data);
    }

    function renderDateTime(value, type) {
        if (value === null || value === undefined || value === '') {
            if (type === 'display') {
                return '<span class="dt-cell-empty text-muted">—</span>';
            }

            return '';
        }

        if (type !== 'display') {
            return value;
        }

        const date = new Date(value);
        if (isNaN(date.getTime())) {
            return window.KidsCrmTooltip.renderText(String(value));
        }

        const day = ('0' + date.getDate()).slice(-2);
        const month = ('0' + (date.getMonth() + 1)).slice(-2);
        const year = date.getFullYear();
        const hours = ('0' + date.getHours()).slice(-2);
        const minutes = ('0' + date.getMinutes()).slice(-2);
        const seconds = ('0' + date.getSeconds()).slice(-2);
        const dateLine = day + '.' + month + '.' + year;
        const timeLine = hours + ':' + minutes + ':' + seconds;

        return '<div class="pay-cell-datetime" role="text" aria-label="'
            + dateLine + ', ' + timeLine + '">'
            + '<span class="pay-cell-datetime__date">' + dateLine + '</span>'
            + '<span class="pay-cell-datetime__time">' + timeLine + '</span>'
            + '</div>';
    }

    function escapeInlineActionsAttr(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function escapeImageAttr(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;');
    }

    function renderIconItem(item) {
        const esc = window.KidsCrmTooltip.escapeHtml;
        const iconClass = item.iconClass || item.icon || '';
        const title = item.title || item.ariaLabel || '';
        const ariaLabel = item.ariaLabel || title;
        const styleColor = item.color ? ' style="color:' + esc(item.color) + ';"' : '';
        const inner = '<i class="' + esc(iconClass) + '"' + styleColor + ' aria-hidden="true"></i>';
        const linkClass = item.linkClass ? ' class="' + esc(item.linkClass) + '"' : '';

        if (item.href) {
            const target = item.target || '_blank';
            const rel = item.rel || 'noopener noreferrer';
            return '<a href="' + esc(item.href) + '" target="' + esc(target) + '" rel="' + esc(rel) + '"'
                + ' title="' + esc(title) + '" aria-label="' + esc(ariaLabel) + '"' + linkClass + '>' + inner + '</a>';
        }

        return '<span title="' + esc(title) + '" aria-label="' + esc(ariaLabel) + '">' + inner + '</span>';
    }

    function renderIcon(value, type, col, row) {
        row = row || {};

        if (type !== 'display') {
            if (col.sortKey) {
                return row[col.sortKey] ?? '';
            }

            return value ?? '';
        }

        const itemsKey = col.itemsKey || 'icons';
        let items = [];

        if (Array.isArray(value)) {
            items = value;
        } else if (row[itemsKey] && Array.isArray(row[itemsKey])) {
            items = row[itemsKey];
        }

        if (!items.length) {
            return '';
        }

        const gap = col.iconGap || '8px';
        const html = items.map(renderIconItem).join('');

        if (items.length === 1) {
            return html;
        }

        return '<span class="kids-dt-icon-group" style="display:inline-flex;align-items:center;gap:' + gap + ';">'
            + html
            + '</span>';
    }

    function renderInlineSelect(value, type, row, col) {
        row = row || {};
        const opts = col.inlineSelect || {};
        const statusKey = opts.statusKey || 'status';
        const labelKey = opts.labelKey || 'status_label';
        const rowIdKey = opts.rowIdKey || 'id';
        const status = row[statusKey];
        const label = row[labelKey] || '—';
        const rowId = row[rowIdKey];
        const esc = window.KidsCrmTooltip.escapeHtml;

        if (type !== 'display') {
            return value || label || '';
        }

        let badgeClass = 'bg-secondary';
        if (typeof opts.badgeClassFn === 'function') {
            badgeClass = opts.badgeClassFn(status, row) || badgeClass;
        } else if (opts.badgeClassMap && Object.prototype.hasOwnProperty.call(opts.badgeClassMap, status)) {
            badgeClass = opts.badgeClassMap[status];
        }

        let badgeStyle = '';
        if (typeof opts.badgeStyleFn === 'function') {
            badgeStyle = opts.badgeStyleFn(status, row) || '';
        }

        const options = Array.isArray(opts.options) ? opts.options : [];
        let optionsHtml = '';
        options.forEach(function (option) {
            const selected = (option.value === (status || '')) ? ' selected' : '';
            optionsHtml += '<option value="' + esc(option.value) + '"' + selected + '>' + esc(option.label) + '</option>';
        });

        const badgeSelector = opts.badgeSelector || 'kids-inline-select-badge';
        const selectSelector = opts.selectSelector || 'kids-inline-select';
        const badgeExtraClass = opts.badgeExtraClass || '';
        const selectExtraClass = opts.selectExtraClass || 'form-select form-select-sm d-none';

        const badgeClassPart = badgeStyle ? 'badge' : ('badge ' + esc(badgeClass));
        const badgeStyleAttr = badgeStyle ? (' style="' + esc(badgeStyle) + '"') : '';

        return ''
            + '<div class="d-flex align-items-center gap-1">'
            + '<span class="' + esc(badgeClassPart) + ' ' + esc(badgeSelector) + ' ' + esc(badgeExtraClass) + '"'
            + ' data-id="' + esc(rowId) + '" data-status="' + esc(status || '') + '"' + badgeStyleAttr + '>' + esc(label) + '</span>'
            + '<select class="' + esc(selectExtraClass) + ' ' + esc(selectSelector) + '" data-id="' + esc(rowId) + '">'
            + optionsHtml
            + '</select>'
            + '</div>';
    }

    function renderImage(value, type, col) {
        if (type !== 'display') {
            return value || '';
        }

        const imageOptions = col.image || {};
        const fallback = imageOptions.fallbackUrl || col.fallbackUrl || '/img/default-avatar.png';
        const rawUrl = value !== null && value !== undefined && String(value).trim() !== ''
            ? String(value)
            : fallback;
        const size = imageOptions.size || col.imageSize || 32;
        const imgClass = imageOptions.imgClass || col.imageClass || 'rounded-circle';
        const alt = imageOptions.alt || col.imageAlt || '';

        return ''
            + '<img src="' + escapeImageAttr(rawUrl) + '"'
            + ' alt="' + escapeImageAttr(alt) + '"'
            + ' class="' + escapeImageAttr(imgClass) + '"'
            + ' style="width:' + size + 'px;height:' + size + 'px;object-fit:cover;"'
            + ' loading="lazy">';
    }

    function renderInlineActions(value, type, options) {
        options = options || {};
        const raw = value === null || value === undefined ? '' : String(value);

        if (type !== 'display') {
            return raw;
        }

        if (raw.trim() === '') {
            return window.KidsCrmTooltip.renderText('');
        }

        const escaped = window.KidsCrmTooltip.escapeHtml(
            window.KidsCrmTooltip.normalizeDisplayText(raw)
        );
        const modalTitle = escapeInlineActionsAttr(options.modalTitle || '');
        const format = escapeInlineActionsAttr(options.format || '');

        return ''
            + '<div class="kids-dt-inline-actions"'
            + (modalTitle ? ' data-modal-title="' + modalTitle + '"' : '')
            + (format ? ' data-format="' + format + '"' : '')
            + '>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary js-kids-dt-inline-actions-show" title="Показать">'
            + '<i class="fas fa-eye" aria-hidden="true"></i>'
            + '<span class="visually-hidden">Показать</span>'
            + '</button>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary js-kids-dt-inline-actions-copy" title="Копировать">'
            + '<i class="fas fa-copy" aria-hidden="true"></i>'
            + '<span class="visually-hidden">Копировать</span>'
            + '</button>'
            + '<span class="kids-dt-inline-actions__full d-none">' + escaped + '</span>'
            + '</div>';
    }

    function bindNavLinks(tableElement) {
        if (!tableElement || tableElement.dataset.dtNavLinksBound === '1') {
            return;
        }

        tableElement.dataset.dtNavLinksBound = '1';

        tableElement.addEventListener('click', function (event) {
            const link = event.target.closest('.js-dt-nav-link');
            if (!link) {
                return;
            }

            const dataHref = link.getAttribute('data-href');
            const rawHref = link.getAttribute('href');
            const href = dataHref
                || (rawHref && rawHref !== 'javascript:void(0);' && rawHref !== '#' ? rawHref : null);

            if (!href || href === 'javascript:void(0);' || href === '#') {
                return;
            }

            event.preventDefault();
            window.location.assign(href);
        });
    }

    function applyPresetColumnOptions(def, col) {
        if (!def) {
            return def;
        }

        if (col.visible === false) {
            def.visible = false;
        }

        if (col.defaultContent !== undefined) {
            def.defaultContent = col.defaultContent;
        }

        return def;
    }

    function buildColumnDefinition(col) {
        if (col.when === false) {
            return null;
        }

        if (col.type === 'custom') {
            return col.column || null;
        }

        const data = col.data;
        const name = col.name || col.key || data;
        let def = null;

        switch (col.type) {
            case 'id':
                def = {
                    data: data || 'id',
                    name: name,
                    className: 'dt-col-id text-center',
                    defaultContent: col.defaultContent || '',
                };
                break;

            case 'rownum':
                def = {
                    data: null,
                    name: name || 'rownum',
                    orderable: false,
                    searchable: false,
                    className: 'dt-col-id text-center',
                    render: col.render || function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    },
                };
                break;

            case 'sort':
                def = {
                    data: data || 'sort',
                    name: name,
                    className: 'dt-col-sort text-center',
                    defaultContent: col.defaultContent || '',
                    render: col.render || function (value) {
                        return value !== null && value !== undefined && value !== '' ? value : '';
                    },
                };
                break;

            case 'text':
                def = {
                    data: data,
                    name: name,
                    className: col.className || 'dt-col-text',
                    defaultContent: col.defaultContent || '',
                    orderable: col.orderable,
                    searchable: col.searchable,
                    render: col.render || displayOrRaw,
                };
                break;

            case 'text-long':
                return buildColumnDefinition(Object.assign({}, col, { type: 'text' }));

            case 'datetime':
                def = {
                    data: data,
                    name: name,
                    className: col.className || 'dt-col-text dt-col-text--wrap',
                    defaultContent: col.defaultContent || '',
                    orderable: col.orderable,
                    searchable: col.searchable,
                    render: col.render || renderDateTime,
                };
                break;

            case 'list':
                def = {
                    data: data,
                    name: name,
                    className: col.className || 'dt-col-list',
                    defaultContent: col.defaultContent || '',
                    orderable: col.orderable,
                    searchable: col.searchable,
                    render: col.render || function (value, type, row) {
                        if (type !== 'display') {
                            return value || '';
                        }

                        if (!value) {
                            return col.emptyHtml || '<span class="dt-cell-empty text-muted">—</span>';
                        }

                        const itemsKey = col.itemsKey || (name + '_items');
                        return window.KidsCrmTooltip.renderList(value, row[itemsKey] || []);
                    },
                };
                break;

            case 'badge':
                def = {
                    data: data,
                    name: name,
                    className: col.className || 'dt-col-badge text-center',
                    defaultContent: col.defaultContent || '',
                    orderable: col.orderable,
                    searchable: col.searchable,
                    render: col.render || function (value, type, row) {
                        const badgeKey = col.badgeKey || 'is_enabled';
                        const badgeClass = row[badgeKey] ? 'bg-success' : 'bg-secondary';
                        return '<span class="badge ' + badgeClass + '">' + value + '</span>';
                    },
                };
                break;

            case 'count':
                def = {
                    data: data,
                    name: name,
                    className: col.className || 'dt-col-count text-center',
                    defaultContent: col.defaultContent || '0',
                    orderable: col.orderable,
                    searchable: col.searchable,
                    render: col.render || function (value) {
                        return value || 0;
                    },
                };
                break;

            case 'money':
                def = {
                    data: data,
                    name: name,
                    className: col.className || 'dt-col-count',
                    orderable: col.orderable,
                    searchable: col.searchable,
                    render: col.render || function (value, type) {
                        if (value === null || value === undefined || value === '') {
                            if (type === 'sort' || type === 'filter') {
                                return '';
                            }

                            return '<span class="dt-cell-empty text-muted">—</span>';
                        }

                        const parsed = parseInt(value, 10);
                        if (Number.isNaN(parsed)) {
                            return type === 'display' ? '<span class="dt-cell-empty text-muted">—</span>' : '';
                        }

                        if (type === 'sort' || type === 'filter') {
                            return parsed;
                        }

                        const formatted = parsed.toLocaleString('ru-RU') + (col.suffix || ' руб');
                        return '<span class="dt-col-money-value">' + formatted + '</span>';
                    },
                };
                break;

            case 'inline-actions':
                def = {
                    data: data,
                    name: name,
                    orderable: col.orderable !== undefined ? col.orderable : false,
                    searchable: col.searchable,
                    className: col.className || 'dt-col-inline-actions',
                    render: col.render || function (value, type) {
                        return renderInlineActions(value, type, col.inlineActions || {});
                    },
                };
                break;

            case 'image':
                def = {
                    data: data,
                    name: name,
                    orderable: col.orderable !== undefined ? col.orderable : false,
                    searchable: col.searchable !== undefined ? col.searchable : false,
                    className: col.className || 'dt-col-image text-center',
                    render: col.render || function (value, type) {
                        return renderImage(value, type, col);
                    },
                };
                break;

            case 'icon':
                def = {
                    data: data,
                    name: name,
                    orderable: col.orderable !== undefined ? col.orderable : false,
                    searchable: col.searchable !== undefined ? col.searchable : false,
                    className: col.className || 'dt-col-icon text-center',
                    render: col.render || function (value, type, row) {
                        return renderIcon(value, type, col, row);
                    },
                };
                break;

            case 'inline-select':
                def = {
                    data: data,
                    name: name,
                    orderable: col.orderable,
                    searchable: col.searchable,
                    className: col.className || 'dt-col-inline-select',
                    render: col.render || function (value, type, row) {
                        return renderInlineSelect(value, type, row, col);
                    },
                };
                break;

            case 'link':
                def = {
                    data: data,
                    name: name,
                    className: col.className || 'dt-col-text',
                    orderable: col.orderable,
                    searchable: col.searchable,
                    render: col.render || function (value, type, row) {
                        if (type !== 'display') {
                            return value || '';
                        }

                        const extraAttrsRaw = typeof col.linkAttrs === 'function'
                            ? col.linkAttrs(row)
                            : (col.linkAttrs || '');
                        const hrefValue = typeof col.href === 'function'
                            ? col.href(row)
                            : col.href;
                        let linkClass = String(col.linkClass || '').trim();
                        let extraAttrs = extraAttrsRaw;

                        if (col.navigate && hrefValue) {
                            linkClass = (linkClass + ' js-dt-nav-link').trim();
                            extraAttrs += ' data-href="' + window.KidsCrmTooltip.escapeHtml(String(hrefValue)) + '"';
                        }

                        return window.KidsCrmTooltip.renderLink(value, {
                            linkClass: linkClass,
                            extraAttrs: extraAttrs,
                            href: hrefValue,
                        });
                    },
                };
                break;

            case 'actions':
                def = {
                    data: null,
                    name: name || 'actions',
                    orderable: false,
                    searchable: false,
                    className: col.className || 'dt-col-actions text-end',
                    render: col.render,
                };
                break;

            default:
                return null;
        }

        return applyPresetColumnOptions(def, col);
    }

    function buildColumnsMap(columnDefinitions) {
        const map = {};

        columnDefinitions.forEach(function (col, index) {
            if (col && col.key) {
                map[col.key] = index;
            }
        });

        return map;
    }

    /**
     * Скрывает блок номеров страниц (prev/next, [1]…), если страница одна.
     * Учитывает текущий pageLength: 15 записей при «показать 20» → pages=1 → скрыть;
     * при «показать 10» → pages=2 → показать.
     */
    function syncSinglePagePaginationVisibility(settings) {
        const api = new $.fn.dataTable.Api(settings);
        const wrapper = api.table().container();

        if (!wrapper) {
            return;
        }

        const pages = api.page.info().pages;
        const paginate = wrapper.querySelector('.dataTables_paginate');

        if (!paginate) {
            return;
        }

        paginate.style.display = pages > 1 ? '' : 'none';
    }

    function ensureTableScrollHost(tableNode, settings) {
        const $table = $(tableNode);
        const $wrapper = $table.closest('.dataTables_wrapper');

        if (!$wrapper.length) {
            return;
        }

        $wrapper.css('overflow-x', 'visible');

        if (settings && settings.oInit && settings.oInit.fixedColumns) {
            return;
        }

        if ($wrapper.find('.DTFC_ScrollWrapper').length) {
            return;
        }

        const $scrollX = $wrapper.children('.dataTables_scroll');
        if ($scrollX.length) {
            $scrollX.css({
                overflowX: 'auto',
                width: '100%',
                maxWidth: '100%',
            });
            return;
        }

        if ($table.parent().hasClass('kids-dt-scroll-x')) {
            return;
        }

        $table.wrap('<div class="kids-dt-scroll-x"></div>');
    }

    function create(selector, options) {
        options = options || {};

        if (!$ || !$.fn.DataTable) {
            throw new Error('KidsCrmDataTable requires jQuery DataTables');
        }

        if (!window.KidsCrmTooltip) {
            throw new Error('KidsCrmDataTable requires KidsCrmTooltip');
        }

        const $table = $(selector);
        if (!$table.length) {
            throw new Error('KidsCrmDataTable: table not found for selector ' + selector);
        }

        if (options.managedClass !== false) {
            $table.addClass('dt-columns-managed');
        }

        const activeColumns = (options.columns || []).filter(function (col) {
            return col.when !== false;
        });

        const builtColumns = activeColumns
            .map(buildColumnDefinition)
            .filter(function (col) {
                return col !== null;
            });

        const columnsMap = buildColumnsMap(activeColumns);

        const settings = options.columnsSettings || null;
        const defaultColumnsVisibility = settings ? (settings.defaults || {}) : {};
        let currentColumnsConfig = Object.assign({}, defaultColumnsVisibility);

        const userDataTableOptions = Object.assign({}, options.dataTable || {});
        const hidePaginationWhenSinglePage = userDataTableOptions.hidePaginationWhenSinglePage !== false;
        const userDrawCallback = userDataTableOptions.drawCallback;

        delete userDataTableOptions.hidePaginationWhenSinglePage;

        const dtOptions = Object.assign({}, DEFAULTS, userDataTableOptions, {
            columns: builtColumns,
            drawCallback: function (drawSettings) {
                if (typeof userDrawCallback === 'function') {
                    userDrawCallback.call(this, drawSettings);
                }

                if (hidePaginationWhenSinglePage) {
                    syncSinglePagePaginationVisibility(drawSettings);
                }
            },
        });

        const table = $table.DataTable(dtOptions);
        const tableElement = $table.get(0);

        window.KidsCrmTooltip.bindDataTable(tableElement);

        function applyVisibleColumns(config) {
            Object.keys(columnsMap).forEach(function (key) {
                const colIndex = columnsMap[key];
                const column = table.column(colIndex);
                let isVisible = toBool(config[key], defaultColumnsVisibility[key]);

                if (settings && typeof settings.resolveColumnVisible === 'function') {
                    isVisible = !!settings.resolveColumnVisible(key, isVisible, config);
                }

                column.visible(isVisible);

                const toggleSelector = settings && settings.toggleSelector
                    ? settings.toggleSelector
                    : '.column-toggle';

                $(toggleSelector + '[data-column-key="' + key + '"]').prop('checked', isVisible);
            });

            try {
                table.columns.adjust();
            } catch (e) {
                /* no-op */
            }

            if (settings && typeof settings.afterApplyVisibleColumns === 'function') {
                settings.afterApplyVisibleColumns(table, config);
            }
        }

        function loadColumnsConfigFromServer() {
            if (!settings || !settings.urls || !settings.urls.get) {
                return;
            }

            $.ajax({
                url: settings.urls.get,
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    const merged = {};

                    Object.keys(defaultColumnsVisibility).forEach(function (key) {
                        merged[key] = toBool(
                            Object.prototype.hasOwnProperty.call(response, key)
                                ? response[key]
                                : defaultColumnsVisibility[key],
                            defaultColumnsVisibility[key]
                        );
                    });

                    if (settings && typeof settings.mergeColumnsConfig === 'function') {
                        currentColumnsConfig = settings.mergeColumnsConfig(merged, response);
                    } else {
                        currentColumnsConfig = merged;
                    }

                    applyVisibleColumns(currentColumnsConfig);
                },
                error: function () {
                    currentColumnsConfig = Object.assign({}, defaultColumnsVisibility);
                    applyVisibleColumns(currentColumnsConfig);
                },
            });
        }

        function bindColumnToggles() {
            if (!settings || !settings.urls || !settings.urls.save) {
                return;
            }

            const toggleSelector = settings.toggleSelector || '.column-toggle';
            const csrfToken = settings.csrfToken
                || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || '';

            $(toggleSelector).on('change.kidsCrmDataTable', function () {
                const key = $(this).data('column-key');
                const isChecked = $(this).is(':checked');

                currentColumnsConfig[key] = isChecked ? 1 : 0;
                applyVisibleColumns(currentColumnsConfig);

                $.ajax({
                    url: settings.urls.save,
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        columns: currentColumnsConfig,
                    },
                    error: function () {
                        console.error('Не удалось сохранить настройки колонок');
                    },
                });
            });
        }

        loadColumnsConfigFromServer();
        bindColumnToggles();
        table.columns.adjust();

        return {
            table: table,
            reload: function (options) {
                options = options || {};

                if (options.keepPage) {
                    table.ajax.reload(null, false);
                    return;
                }

                table.ajax.reload();
            },
            applyVisibleColumns: applyVisibleColumns,
            loadColumnsConfigFromServer: loadColumnsConfigFromServer,
            getColumnsConfig: function () {
                return Object.assign({}, currentColumnsConfig);
            },
        };
    }

    window.KidsCrmDataTable = {
        create: create,
        bindNavLinks: bindNavLinks,
        toBool: toBool,
        buildColumnDefinition: buildColumnDefinition,
        renderDateTime: renderDateTime,
        renderImage: renderImage,
        renderIcon: renderIcon,
        renderInlineSelect: renderInlineSelect,
        renderInlineActions: renderInlineActions,
        syncSinglePagePaginationVisibility: syncSinglePagePaginationVisibility,
        ensureTableScrollHost: ensureTableScrollHost,
    };

    if ($ && $.fn.dataTable) {
        $(document).on('init.dt', function (event, settings) {
            ensureTableScrollHost(settings.nTable, settings);
        });
    }
})(window, window.jQuery);
