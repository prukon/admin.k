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
                               placeholder="Название, email, телефон">
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
            <table id="partners-table" class="table table-striped table-bordered align-middle w-100 dt-columns-managed">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Сортировка</th>
                    <th>Наименование</th>
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

            function escapeHtml(text) {
                if (text === null || text === undefined) return '';
                return String(text)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
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

            const dtApi = KidsCrmDataTable.create('#partners-table', {
                columnsSettings: {
                    defaults: {
                        order_by: true,
                        title: true,
                        email: true,
                        phone: true,
                        status_label: true,
                        actions: true,
                    },
                    urls: {
                        get: @json(route('admin.partner.columns-settings.get')),
                        save: @json(route('admin.partner.columns-settings.save')),
                    },
                    csrfToken: $('meta[name="csrf-token"]').attr('content'),
                },
                dataTable: {
                    ajax: {
                        url: @json(route('admin.partner.data')),
                        type: 'GET',
                        data: function (d) {
                            const params = partnersFilterParams();
                            d.title = params.title;
                            d.status = params.status;
                        },
                    },
                    order: [[1, 'asc']],
                    language: @include('partials.datatables.ru'),
                },
                columns: [
                    { type: 'rownum' },
                    { key: 'order_by', type: 'sort', data: 'order_by' },
                    {
                        key: 'title',
                        type: 'link',
                        data: 'title',
                        name: 'title',
                        className: 'dt-col-text',
                        linkClass: 'edit-partner-link',
                        linkAttrs: function (row) {
                            return 'data-id="' + row.id + '"';
                        },
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data || '';
                            }

                            const linkClass = 'edit-partner-link' + (row.is_enabled ? '' : ' text-danger');
                            return window.KidsCrmTooltip.renderLink(data, {
                                linkClass: linkClass,
                                extraAttrs: 'data-id="' + row.id + '"',
                            });
                        },
                    },
                    { key: 'email', type: 'text-long', data: 'email' },
                    { key: 'phone', type: 'text', data: 'phone', className: 'dt-col-text text-nowrap' },
                    {
                        key: 'status_label',
                        type: 'badge',
                        data: 'status_label',
                        badgeKey: 'is_enabled',
                    },
                    {
                        key: 'actions',
                        type: 'actions',
                        render: function (data, type, row) {
                            return '<button type="button" '
                                + 'class="btn btn-sm btn-outline-primary edit-partner-link" '
                                + 'data-id="' + row.id + '">'
                                + 'Редактировать'
                                + '</button>';
                        },
                    },
                ],
            });

            const table = dtApi.table;

            window.reloadPartnersTable = function () {
                dtApi.reload({ keepPage: true });
                syncPartnersFiltersCollapseState();
            };

            function reloadPartnersTable() {
                dtApi.reload({ keepPage: true });
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

            showLogModal(@json(route('logs.data.partner')));
        });
    </script>
@endpush
