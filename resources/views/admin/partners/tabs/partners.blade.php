@php
    $partnersHasActiveFilters = $partnersHasActiveFilters ?? true;
@endphp

@vite(['resources/css/admin-list-toolbar.css'])

<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Партнеры</h1>
                    <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                        <button id="new-partner"
                                type="button"
                                class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                data-bs-toggle="modal"
                                data-bs-target="#createPartnerModal">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-plus payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Добавить</span>
                        </button>

                        <button type="button"
                                class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                data-bs-toggle="modal"
                                data-bs-target="#historyModal"
                                title="История изменений">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-clock-rotate-left payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">История</span>
                        </button>

                        <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#partnersReportFiltersCollapse"
                                aria-expanded="{{ $partnersHasActiveFilters ? 'true' : 'false' }}"
                                aria-controls="partnersReportFiltersCollapse"
                                id="partnersReportFiltersToggle">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                            <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="dropdown payments-report-toolbar-dropdown">
                            <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                    type="button"
                                    id="partnersColumnsDropdown"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    aria-expanded="false"
                                    aria-haspopup="true"
                                    title="Какие колонки показывать в таблице">
                                <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-table-columns payments-report-toolbar-icon"></i>
                                </span>
                                <span class="payments-report-toolbar-label d-none d-sm-inline">Колонки</span>
                                <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                            </button>

                            <div class="dropdown-menu dropdown-menu-end payments-report-toolbar-dropdown-panel payments-report-columns-menu"
                                 aria-labelledby="partnersColumnsDropdown">
                                <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="order_by"
                                           id="colPartnerOrderBy"
                                           checked>
                                    <label class="form-check-label" for="colPartnerOrderBy">Сортировка</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="title"
                                           id="colPartnerTitle"
                                           checked>
                                    <label class="form-check-label" for="colPartnerTitle">Наименование</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="organization_name"
                                           id="colPartnerOrganization"
                                           checked>
                                    <label class="form-check-label" for="colPartnerOrganization">Организация</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="tax_id"
                                           id="colPartnerTaxId"
                                           checked>
                                    <label class="form-check-label" for="colPartnerTaxId">ИНН</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="email"
                                           id="colPartnerEmail"
                                           checked>
                                    <label class="form-check-label" for="colPartnerEmail">E-mail</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="phone"
                                           id="colPartnerPhone"
                                           checked>
                                    <label class="form-check-label" for="colPartnerPhone">Телефон</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="status_label"
                                           id="colPartnerStatus"
                                           checked>
                                    <label class="form-check-label" for="colPartnerStatus">Статус</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="actions"
                                           id="colPartnerActions"
                                           checked>
                                    <label class="form-check-label" for="colPartnerActions">Действия</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="collapse {{ $partnersHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="partnersReportFiltersCollapse">
            <form id="partners-report-filters" class="border rounded p-2 p-md-3 bg-light">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="filter-title">Поиск</label>
                        <input id="filter-title"
                               class="form-control"
                               type="text"
                               placeholder="Название, ИНН, email, телефон">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="filter-status">Статус</label>
                        <select id="filter-status" class="form-select">
                            <option value="">Все партнеры</option>
                            <option value="active" selected>Только активные</option>
                            <option value="inactive">Только неактивные</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                        <button id="filter-apply" class="btn btn-primary payments-report-filters-submit" type="button">Применить</button>
                        <button id="filter-reset" class="btn btn-outline-secondary payments-report-filters-reset" type="button">Сброс</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table id="partners-table" class="table table-striped table-bordered align-middle w-100">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Сортировка</th>
                    <th>Наименование</th>
                    <th>Организация</th>
                    <th>ИНН</th>
                    <th>E-mail</th>
                    <th>Телефон</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

@include('includes.modal.editPartner')
@include('includes.logModal')

@push('scripts')
    <script>
        $(document).ready(function () {
            const defaultFilterStatus = 'active';

            const defaultColumnsVisibility = {
                order_by: true,
                title: true,
                organization_name: true,
                tax_id: true,
                email: true,
                phone: true,
                status_label: true,
                actions: true,
            };

            let currentColumnsConfig = {...defaultColumnsVisibility};

            const columnsMap = {
                order_by: 1,
                title: 2,
                organization_name: 3,
                tax_id: 4,
                email: 5,
                phone: 6,
                status_label: 7,
                actions: 8,
            };

            const csrfToken = $('meta[name="csrf-token"]').attr('content');

            function escapeHtml(text) {
                if (text === null || text === undefined) return '';
                return String(text)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function toBool(val, fallback = true) {
                if (val === undefined || val === null) return fallback;

                if (typeof val === 'boolean') return val;

                if (typeof val === 'number') return val === 1;

                if (typeof val === 'string') {
                    const v = val.toLowerCase().trim();
                    if (v === 'true' || v === '1') return true;
                    if (v === 'false' || v === '0') return false;
                }

                return fallback;
            }

            function applyVisibleColumns(config) {
                Object.keys(columnsMap).forEach(function (key) {
                    const colIndex = columnsMap[key];
                    const column = table.column(colIndex);
                    const isVisible = toBool(config[key], defaultColumnsVisibility[key]);

                    column.visible(isVisible);

                    $('.column-toggle[data-column-key="' + key + '"]')
                        .prop('checked', isVisible);
                });
            }

            function loadColumnsConfigFromServer() {
                $.ajax({
                    url: @json(route('admin.partner.columns-settings.get')),
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        const merged = {};

                        Object.keys(defaultColumnsVisibility).forEach(function (key) {
                            merged[key] = toBool(
                                Object.prototype.hasOwnProperty.call(response, key) ? response[key] : defaultColumnsVisibility[key],
                                defaultColumnsVisibility[key]
                            );
                        });

                        currentColumnsConfig = merged;
                        applyVisibleColumns(currentColumnsConfig);
                    },
                    error: function () {
                        currentColumnsConfig = {...defaultColumnsVisibility};
                        applyVisibleColumns(currentColumnsConfig);
                    }
                });
            }

            function partnersFilterParams() {
                return {
                    title: $('#filter-title').val() || '',
                    status: $('#filter-status').val() || '',
                };
            }

            function partnersHasNonDefaultFilters() {
                const params = partnersFilterParams();
                return params.title !== '' || params.status !== defaultFilterStatus;
            }

            function syncPartnersFiltersCollapseState() {
                const hasActive = partnersHasNonDefaultFilters();
                const collapseEl = document.getElementById('partnersReportFiltersCollapse');
                const $toggle = $('#partnersReportFiltersToggle');

                if (collapseEl && hasActive && !collapseEl.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                }

                if ($toggle.length && collapseEl) {
                    $toggle.attr('aria-expanded', collapseEl.classList.contains('show') ? 'true' : 'false');
                }
            }

            window.reloadPartnersTable = function () {
                if ($.fn.DataTable.isDataTable('#partners-table')) {
                    $('#partners-table').DataTable().ajax.reload(null, false);
                }
                syncPartnersFiltersCollapseState();
            };

            const dataTableColumns = [
                {
                    data: null,
                    name: 'rownum',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                {
                    data: 'order_by',
                    name: 'order_by',
                    className: 'text-center',
                    defaultContent: '',
                },
                {
                    data: 'title',
                    name: 'title',
                    render: function (data, type, row) {
                        const style = row.is_enabled ? '' : ' style="color: red;"';
                        return '<a href="javascript:void(0);" ' +
                            'class="edit-partner-link"' + style + ' ' +
                            'data-id="' + row.id + '">' +
                            escapeHtml(data) +
                            '</a>';
                    }
                },
                {
                    data: 'organization_name',
                    name: 'organization_name',
                    defaultContent: '',
                    render: function (data) {
                        if (!data) return '<span class="text-muted">—</span>';
                        return '<span title="' + escapeHtml(data) + '">' + escapeHtml(data) + '</span>';
                    }
                },
                {
                    data: 'tax_id',
                    name: 'tax_id',
                    defaultContent: '',
                    render: function (data) {
                        if (!data) return '<span class="text-muted">—</span>';
                        return escapeHtml(data);
                    }
                },
                {
                    data: 'email',
                    name: 'email',
                    defaultContent: '',
                    render: function (data) {
                        if (!data) return '<span class="text-muted">—</span>';
                        return '<span title="' + escapeHtml(data) + '">' + escapeHtml(data) + '</span>';
                    }
                },
                {
                    data: 'phone',
                    name: 'phone',
                    defaultContent: '',
                    render: function (data) {
                        if (!data) return '<span class="text-muted">—</span>';
                        return escapeHtml(data);
                    }
                },
                {
                    data: 'status_label',
                    name: 'status_label',
                    render: function (data, type, row) {
                        const badgeClass = row.is_enabled ? 'bg-success' : 'bg-secondary';
                        return '<span class="badge ' + badgeClass + '">' + escapeHtml(data) + '</span>';
                    }
                },
                {
                    data: null,
                    name: 'actions',
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render: function (data, type, row) {
                        return '<button type="button" ' +
                            'class="btn btn-sm btn-outline-primary edit-partner-link" ' +
                            'data-id="' + row.id + '">' +
                            'Редактировать' +
                            '</button>';
                    }
                }
            ];

            const table = $('#partners-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 10,
                lengthMenu: [10, 20, 50, 100],
                ajax: {
                    url: @json(route('admin.partner.data')),
                    type: 'GET',
                    data: function (d) {
                        const params = partnersFilterParams();
                        d.title = params.title;
                        d.status = params.status;
                    }
                },
                columns: dataTableColumns,
                order: [[1, 'asc']],
                scrollX: true,
                language: {
                    processing: 'Обработка...',
                    search: '',
                    searchPlaceholder: 'Поиск...',
                    lengthMenu: 'Показать _MENU_',
                    info: 'С _START_ до _END_ из _TOTAL_ записей',
                    infoEmpty: 'С 0 до 0 из 0 записей',
                    infoFiltered: '(отфильтровано из _MAX_ записей)',
                    loadingRecords: 'Загрузка записей...',
                    zeroRecords: 'Записи отсутствуют.',
                    emptyTable: 'В таблице отсутствуют данные',
                    paginate: {
                        first: '',
                        previous: '',
                        next: '',
                        last: ''
                    },
                    aria: {
                        sortAscending: ': активировать для сортировки столбца по возрастанию',
                        sortDescending: ': активировать для сортировки столбца по убыванию'
                    }
                }
            });

            loadColumnsConfigFromServer();
            table.columns.adjust();

            function reloadPartnersTable() {
                table.ajax.reload();
                syncPartnersFiltersCollapseState();
            }

            $('#filter-apply').on('click', function () {
                reloadPartnersTable();
            });

            $('#partners-report-filters').on('submit', function (e) {
                e.preventDefault();
                reloadPartnersTable();
            });

            $('#filter-reset').on('click', function () {
                $('#filter-title').val('');
                $('#filter-status').val(defaultFilterStatus);
                reloadPartnersTable();
            });

            $('#filter-title').on('keyup', function (e) {
                if (e.key === 'Enter') {
                    reloadPartnersTable();
                }
            });

            $('#partnersReportFiltersCollapse').on('shown.bs.collapse hidden.bs.collapse', function () {
                $('#partnersReportFiltersToggle').attr(
                    'aria-expanded',
                    $('#partnersReportFiltersCollapse').hasClass('show') ? 'true' : 'false'
                );
            });

            $('.column-toggle').on('change', function () {
                const key = $(this).data('column-key');
                const isChecked = $(this).is(':checked');

                currentColumnsConfig[key] = isChecked ? 1 : 0;
                applyVisibleColumns(currentColumnsConfig);

                $.ajax({
                    url: @json(route('admin.partner.columns-settings.save')),
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        columns: currentColumnsConfig
                    },
                    error: function () {
                        console.error('Не удалось сохранить настройки колонок');
                    }
                });
            });

            showLogModal(@json(route('logs.data.partner')));
        });
    </script>
@endpush
