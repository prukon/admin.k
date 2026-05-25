@extends('layouts.admin2')

@php
    $teamsHasActiveFilters = false;
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])

    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Группы</h4>

        <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Группы</h1>
                    <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                        <button id="new-team"
                                type="button"
                                class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                data-bs-toggle="modal"
                                data-bs-target="#createTeamModal">
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
                                data-bs-target="#teamsReportFiltersCollapse"
                                aria-expanded="{{ $teamsHasActiveFilters ? 'true' : 'false' }}"
                                aria-controls="teamsReportFiltersCollapse"
                                id="teamsReportFiltersToggle">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                            <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="dropdown payments-report-toolbar-dropdown">
                            <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                    type="button"
                                    id="columnsDropdown"
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
                                 aria-labelledby="columnsDropdown">
                                <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="order_by"
                                           id="colOrderBy"
                                           checked>
                                    <label class="form-check-label" for="colOrderBy">Сортировка</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="title"
                                           id="colTitle"
                                           checked>
                                    <label class="form-check-label" for="colTitle">Название</label>
                                </div>

                                @can('trainers.view')
                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="trainer_label"
                                           id="colTrainer"
                                           checked>
                                    <label class="form-check-label" for="colTrainer">Тренер</label>
                                </div>
                                @endcan

                                @can('schedule.view')
                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="weekdays_label"
                                           id="colWeekdays"
                                           checked>
                                    <label class="form-check-label" for="colWeekdays">Расписание</label>
                                </div>
                                @endcan

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="status_label"
                                           id="colStatus"
                                           checked>
                                    <label class="form-check-label" for="colStatus">Статус</label>
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

        <div class="collapse {{ $teamsHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="teamsReportFiltersCollapse">
            <form id="teams-report-filters" class="border rounded p-2 p-md-3 bg-light">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-title">Название</label>
                        <input id="filter-title"
                               class="form-control"
                               type="text"
                               placeholder="Название группы">
                    </div>

                    @can('trainers.view')
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-trainer">Тренер</label>
                        <select id="filter-trainer" class="form-select">
                            <option value="">Все тренеры</option>
                            <option value="none">Без тренера</option>
                            @foreach($trainerOptions as $trainer)
                                <option value="{{ $trainer->id }}">{{ $trainer->user?->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endcan

                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-status">Статус</label>
                        <select id="filter-status" class="form-select">
                            <option value="">Все группы</option>
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
            <table id="teams-table" class="table table-striped table-bordered align-middle w-100">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Сортировка</th>
                    <th>Название</th>
                    @can('trainers.view')
                    <th>Тренер</th>
                    @endcan
                    @can('schedule.view')
                    <th>Расписание</th>
                    @endcan
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    @include('includes.modal.createTeam')
    @include('includes.modal.editTeam')
    @include('includes.logModal')
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {

            const canViewSchedule = @json(auth()->user()->can('schedule.view'));
            const canViewTrainers = @json(auth()->user()->can('trainers.view'));
            const defaultFilterStatus = 'active';

            const defaultColumnsVisibility = {
                order_by: true,
                title: true,
                ...(canViewTrainers ? { trainer_label: true } : {}),
                ...(canViewSchedule ? { weekdays_label: true } : {}),
                status_label: true,
                actions: true
            };

            let currentColumnsConfig = {...defaultColumnsVisibility};

            const columnsMap = (function () {
                const map = { order_by: 1, title: 2 };
                let idx = 3;
                if (canViewTrainers) {
                    map.trainer_label = idx++;
                }
                if (canViewSchedule) {
                    map.weekdays_label = idx++;
                }
                map.status_label = idx++;
                map.actions = idx;
                return map;
            })();

            const csrfToken = $('meta[name="csrf-token"]').attr('content');

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
                    url: '/admin/teams/columns-settings',
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

            function teamsFilterParams() {
                return {
                    title: $('#filter-title').val() || '',
                    status: $('#filter-status').val() || '',
                    trainer_profile_id: canViewTrainers ? ($('#filter-trainer').val() || '') : ''
                };
            }

            function teamsHasNonDefaultFilters() {
                const params = teamsFilterParams();
                return params.title !== ''
                    || params.trainer_profile_id !== ''
                    || params.status !== defaultFilterStatus;
            }

            function syncTeamsFiltersCollapseState() {
                const hasActive = teamsHasNonDefaultFilters();
                const collapseEl = document.getElementById('teamsReportFiltersCollapse');
                const $toggle = $('#teamsReportFiltersToggle');

                if (collapseEl && hasActive && !collapseEl.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                }

                if ($toggle.length && collapseEl) {
                    $toggle.attr('aria-expanded', collapseEl.classList.contains('show') ? 'true' : 'false');
                }
            }

            const trainerColumn = {
                data: 'trainer_label',
                name: 'trainer_label',
                orderable: true,
                searchable: false,
                render: function (data) {
                    if (!data) {
                        return '<span class="text-muted">—</span>';
                    }
                    return '<span title="' + data + '">' + data + '</span>';
                }
            };

            const scheduleColumn = {
                data: 'weekdays_label',
                name: 'weekdays_label',
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    if (!data) {
                        return '<span class="text-muted">—</span>';
                    }
                    return '<span title="' + data + '">' + data + '</span>';
                }
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
                    render: function (data, type, row) {
                        return data !== null && data !== undefined && data !== '' ? data : '';
                    }
                },
                {
                    data: 'title',
                    name: 'title',
                    render: function (data, type, row) {
                        return '<a href="javascript:void(0);" ' +
                            'class="edit-team-link" ' +
                            'data-id="' + row.id + '" ' +
                            'data-bs-toggle="modal" ' +
                            'data-bs-target="#editTeamModal">' +
                            data +
                            '</a>';
                    }
                },
                ...(canViewTrainers ? [trainerColumn] : []),
                ...(canViewSchedule ? [scheduleColumn] : []),
                {
                    data: 'status_label',
                    name: 'status_label',
                    render: function (data, type, row) {
                        const badgeClass = row.is_enabled ? 'bg-success' : 'bg-secondary';
                        return '<span class="badge ' + badgeClass + '">' + data + '</span>';
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
                            'class="btn btn-sm btn-outline-primary edit-team-link" ' +
                            'data-id="' + row.id + '" ' +
                            'data-bs-toggle="modal" ' +
                            'data-bs-target="#editTeamModal">' +
                            'Редактировать' +
                            '</button>';
                    }
                }
            ];

            const table = $('#teams-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 10,
                lengthMenu: [10, 20, 50, 100],
                ajax: {
                    url: '/admin/teams/data',
                    type: 'GET',
                    data: function (d) {
                        const params = teamsFilterParams();
                        d.title = params.title;
                        d.status = params.status;
                        if (canViewTrainers) {
                            d.trainer_profile_id = params.trainer_profile_id;
                        }
                    }
                },

                columns: dataTableColumns,

                order: [[1, 'asc']],
                scrollX: true,
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
            table.columns.adjust();

            function reloadTeamsTable() {
                table.ajax.reload();
                syncTeamsFiltersCollapseState();
            }

            $('#filter-apply').on('click', function () {
                reloadTeamsTable();
            });

            $('#teams-report-filters').on('submit', function (e) {
                e.preventDefault();
                reloadTeamsTable();
            });

            $('#filter-reset').on('click', function () {
                $('#filter-title').val('');
                $('#filter-status').val(defaultFilterStatus);
                if (canViewTrainers) {
                    $('#filter-trainer').val('');
                }
                reloadTeamsTable();
            });

            $('#filter-title').on('keyup', function (e) {
                if (e.key === 'Enter') {
                    reloadTeamsTable();
                }
            });

            $('#teamsReportFiltersCollapse').on('shown.bs.collapse hidden.bs.collapse', function () {
                $('#teamsReportFiltersToggle').attr(
                    'aria-expanded',
                    $('#teamsReportFiltersCollapse').hasClass('show') ? 'true' : 'false'
                );
            });

            $('.column-toggle').on('change', function () {
                const key = $(this).data('column-key');
                const isChecked = $(this).is(':checked');

                currentColumnsConfig[key] = isChecked ? 1 : 0;

                applyVisibleColumns(currentColumnsConfig);

                $.ajax({
                    url: '/admin/teams/columns-settings',
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

            showLogModal("{{ route('logs.data.team') }}");
        });
    </script>
@endpush
