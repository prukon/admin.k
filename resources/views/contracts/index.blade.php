@extends('layouts.admin2')

@section('title','Документы')

@php
    $contractsHasActiveFilters = false;
    $shouldOpenCreateModal = $shouldOpenCreateModal ?? false;
    $partner = $partner ?? null;
    $contractTemplates = $contractTemplates ?? collect();
    $preselectedUser = $preselectedUser ?? null;
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])

    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Документы</h4>

        <div class="">
            @include('contracts._contracts_section_tabs', ['activeTab' => $activeTab ?? 'contracts'])

            <div class="tab-content">
        <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Договоры</h1>
                    <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                        <button type="button"
                                class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                data-bs-toggle="modal"
                                data-bs-target="#createContractModal">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-plus payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Создать</span>
                        </button>

                        <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#contractsReportFiltersCollapse"
                                aria-expanded="{{ $contractsHasActiveFilters ? 'true' : 'false' }}"
                                aria-controls="contractsReportFiltersCollapse"
                                id="contractsReportFiltersToggle">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                            <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="dropdown payments-report-toolbar-dropdown">
                            <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                    type="button"
                                    id="contractsColumnsDropdown"
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
                                 aria-labelledby="contractsColumnsDropdown">
                                <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="user_name"
                                           id="colUserName"
                                           checked>
                                    <label class="form-check-label" for="colUserName">Имя</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="user_lastname"
                                           id="colUserLastname"
                                           checked>
                                    <label class="form-check-label" for="colUserLastname">Фамилия</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="team_title"
                                           id="colTeamTitle"
                                           checked>
                                    <label class="form-check-label" for="colTeamTitle">Группа</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="user_phone"
                                           id="colUserPhone"
                                           checked>
                                    <label class="form-check-label" for="colUserPhone">Телефон</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="user_email"
                                           id="colUserEmail"
                                           checked>
                                    <label class="form-check-label" for="colUserEmail">Email</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="status_label"
                                           id="colStatusLabel"
                                           checked>
                                    <label class="form-check-label" for="colStatusLabel">Статус</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="updated_at"
                                           id="colUpdatedAt"
                                           checked>
                                    <label class="form-check-label" for="colUpdatedAt">Обновлён</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="actions"
                                           id="colActions"
                                           checked>
                                    <label class="form-check-label" for="colActions">Действия</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="collapse {{ $contractsHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="contractsReportFiltersCollapse">
            <form id="contracts-report-filters" class="border rounded p-2 p-md-3 bg-light">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-search">Поиск</label>
                        <input id="filter-search"
                               class="form-control"
                               type="text"
                               placeholder="Имя, телефон, email">
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-group">Группа</label>
                        <select id="filter-group" class="form-select">
                            <option value="">Все группы</option>
                            <option value="none">Без группы</option>
                            @foreach($allTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-status">Статус</label>
                        <select id="filter-status" class="form-select">
                            <option value="">Все статусы</option>
                            <option value="draft">Черновик</option>
                            <option value="awaiting_client_fill">Ожидает заполнения</option>
                            <option value="sent">Отправлено</option>
                            <option value="opened">Открыто</option>
                            <option value="signed">Подписан</option>
                            <option value="revoked">Отозван</option>
                            <option value="expired">Истёк срок</option>
                            <option value="failed">Ошибка</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                        <button id="filter-apply" class="btn btn-primary payments-report-filters-submit" type="button">Применить</button>
                        <button id="filter-reset" class="btn btn-outline-secondary payments-report-filters-reset" type="button">Сброс</button>
                    </div>
                </div>
            </form>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="table-responsive">
            <table id="contracts-table"
                   class="table table-striped table-bordered align-middle w-100">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Группа</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Статус</th>
                    <th>Обновлён</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
            </div>
        </div>
    </div>

    @include('contracts.partials.create-modal')
@endsection

@section('scripts')
    <script>
        $(document).ready(function () {
            const csrfToken = $('meta[name="csrf-token"]').attr('content');

            const defaultColumnsVisibility = {
                user_name: true,
                user_lastname: true,
                team_title: true,
                user_phone: true,
                user_email: true,
                status_label: true,
                updated_at: true,
                actions: true
            };

            let currentColumnsConfig = {...defaultColumnsVisibility};

            const columnsMap = {
                user_name: 1,
                user_lastname: 2,
                team_title: 3,
                user_phone: 4,
                user_email: 5,
                status_label: 6,
                updated_at: 7,
                actions: 8
            };

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
                    url: '/client-contracts/columns-settings',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        const merged = {};
                        Object.keys(defaultColumnsVisibility).forEach(function (key) {
                            merged[key] = toBool(
                                response.hasOwnProperty(key) ? response[key] : defaultColumnsVisibility[key],
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

            function contractsFilterParams() {
                return {
                    search_value: $('#filter-search').val() || '',
                    group_id: $('#filter-group').val() || '',
                    status: $('#filter-status').val() || ''
                };
            }

            function contractsHasNonDefaultFilters() {
                const params = contractsFilterParams();
                return params.search_value !== ''
                    || params.group_id !== ''
                    || params.status !== '';
            }

            function syncContractsFiltersCollapseState() {
                const hasActive = contractsHasNonDefaultFilters();
                const collapseEl = document.getElementById('contractsReportFiltersCollapse');
                const $toggle = $('#contractsReportFiltersToggle');

                if (collapseEl && hasActive && !collapseEl.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                }

                if ($toggle.length && collapseEl) {
                    $toggle.attr('aria-expanded', collapseEl.classList.contains('show') ? 'true' : 'false');
                }
            }

            const table = $('#contracts-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 20,
                lengthMenu: [10, 20, 50, 100],
                ajax: {
                    url: '/client-contracts/data',
                    type: 'GET',
                    data: function (d) {
                        const params = contractsFilterParams();
                        d.search_value = params.search_value;
                        d.group_id = params.group_id;
                        d.status = params.status;
                    }
                },
                columns: [
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
                    {data: 'user_name', name: 'user_name', defaultContent: ''},
                    {data: 'user_lastname', name: 'user_lastname', defaultContent: ''},
                    {data: 'team_title', name: 'team_title', defaultContent: ''},
                    {data: 'user_phone', name: 'user_phone', defaultContent: ''},
                    {data: 'user_email', name: 'user_email', defaultContent: ''},
                    {
                        data: 'status_label',
                        name: 'status_label',
                        render: function (data, type, row) {
                            const badgeClass = row.status_badge_class || 'bg-secondary';
                            const label = data || '';
                            return '<span class="badge ' + badgeClass + '">' + label + '</span>';
                        }
                    },
                    {data: 'updated_at', name: 'updated_at', defaultContent: ''},
                    {
                        data: null,
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        render: function (data, type, row) {
                            const url = '/client-contracts/' + row.id;
                            return '<a class="btn btn-sm btn-outline-secondary" href="' + url + '">Подробнее</a>';
                        }
                    }
                ],
                order: [[7, 'desc']],
                language: {
                    "processing": "Обработка...",
                    "search": "",
                    "searchPlaceholder": "Поиск...",
                    "lengthMenu": "Показать _MENU_",
                    "info": "С _START_ до _END_ из _TOTAL_ записей",
                    "infoEmpty": "С 0 до 0 из 0 записей",
                    "infoFiltered": "(отфильтровано из _MAX_ записей)",
                    "loadingRecords": "Загрузка записей...",
                    "zeroRecords": "Записи отсутствуют.",
                    "emptyTable": "В таблице отсутствуют данные",
                    "paginate": {
                        "first": "",
                        "previous": "",
                        "next": "",
                        "last": ""
                    },
                    "aria": {
                        "sortAscending": ": активировать для сортировки столбца по возрастанию",
                        "sortDescending": ": активировать для сортировки столбца по убыванию"
                    }
                }
            });

            loadColumnsConfigFromServer();

            function reloadContractsTable() {
                table.ajax.reload();
                syncContractsFiltersCollapseState();
            }

            $('#filter-apply').on('click', function () {
                reloadContractsTable();
            });

            $('#contracts-report-filters').on('submit', function (e) {
                e.preventDefault();
                reloadContractsTable();
            });

            $('#filter-reset').on('click', function () {
                $('#filter-search').val('');
                $('#filter-group').val('');
                $('#filter-status').val('');
                reloadContractsTable();
            });

            $('#filter-search').on('keyup', function (e) {
                if (e.key === 'Enter') {
                    reloadContractsTable();
                }
            });

            $('#contractsReportFiltersCollapse').on('shown.bs.collapse hidden.bs.collapse', function () {
                $('#contractsReportFiltersToggle').attr(
                    'aria-expanded',
                    $('#contractsReportFiltersCollapse').hasClass('show') ? 'true' : 'false'
                );
            });

            $('.column-toggle').on('change', function () {
                const key = $(this).data('column-key');
                const isChecked = $(this).is(':checked');

                currentColumnsConfig[key] = isChecked ? 1 : 0;

                applyVisibleColumns(currentColumnsConfig);

                $.ajax({
                    url: '/client-contracts/columns-settings',
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
        });
    </script>
@endsection
