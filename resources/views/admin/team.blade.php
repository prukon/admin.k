@extends('layouts.admin2')

@php
    $teamsHasActiveFilters = false;
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])

    @push('styles')
        <style>
            .team-weekdays-badges {
                display: flex;
                flex-wrap: nowrap;
                gap: 0.25rem;
                white-space: nowrap;
            }

            .team-weekday-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 2rem;
                padding: 0.15rem 0.45rem;
                font-size: 0.7rem;
                font-weight: 600;
                line-height: 1.2;
                letter-spacing: 0.01em;
                border-radius: 999px;
                white-space: nowrap;
                background: var(--bs-primary-bg-subtle, #cfe2ff);
                color: var(--bs-body-color, #212529);
                border: 1px solid #b6d4fe;
            }
        </style>
    @endpush

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

                                @can('groups.training_base.view')
                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="training_base"
                                           id="colTrainingBase"
                                           checked>
                                    <label class="form-check-label" for="colTrainingBase">Тренировочная база</label>
                                </div>
                                @endcan

                                @can('groups.address.view')
                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="address"
                                           id="colAddress"
                                           checked>
                                    <label class="form-check-label" for="colAddress">Адрес</label>
                                </div>
                                @endcan

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

                                @can('locations.view')
                                @if($locationOptions->isNotEmpty())
                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="locations_label"
                                           id="colLocations"
                                           checked>
                                    <label class="form-check-label" for="colLocations">Объекты</label>
                                </div>
                                @endif
                                @endcan

                                @can('sport_types.view')
                                @if($sportTypeOptions->isNotEmpty())
                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="sport_type_label"
                                           id="colSportType"
                                           checked>
                                    <label class="form-check-label" for="colSportType">Вид спорта</label>
                                </div>
                                @endif
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
                                           data-column-key="month_price"
                                           id="colMonthPrice"
                                           checked>
                                    <label class="form-check-label" for="colMonthPrice">Стоимость</label>
                                </div>

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

                    @can('locations.view')
                    @if($locationOptions->isNotEmpty())
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-location">Объект</label>
                        <select id="filter-location" class="form-select">
                            <option value="">Все объекты</option>
                            <option value="none">Без привязки к объектам</option>
                            @foreach($locationOptions as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    @endcan

                    @can('sport_types.view')
                    @if($sportTypeOptions->isNotEmpty())
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-sport-type">Вид спорта</label>
                        <select id="filter-sport-type" class="form-select">
                            <option value="">Все виды спорта</option>
                            <option value="none">Без вида спорта</option>
                            @foreach($sportTypeOptions as $sportType)
                                <option value="{{ $sportType->id }}">{{ $sportType->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
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
            <table id="teams-table" class="table table-striped table-bordered align-middle w-100 dt-columns-managed">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Сортировка</th>
                    <th>Название</th>
                    @can('groups.training_base.view')
                    <th>Тренировочная база</th>
                    @endcan
                    @can('groups.address.view')
                    <th>Адрес</th>
                    @endcan
                    @can('trainers.view')
                    <th>Тренер</th>
                    @endcan
                    @can('locations.view')
                    @if($locationOptions->isNotEmpty())
                    <th>Объекты</th>
                    @endif
                    @endcan
                    @can('sport_types.view')
                    @if($sportTypeOptions->isNotEmpty())
                    <th>Вид спорта</th>
                    @endif
                    @endcan
                    @can('schedule.view')
                    <th>Расписание</th>
                    @endcan
                    <th>Стоимость</th>
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
    @can('locations.view')
        @if($locationOptions->isNotEmpty())
            @include('partials.select2.generic-multiselect')
        @endif
    @endcan
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {

            const canViewSchedule = @json(auth()->user()->can('schedule.view'));
            const canViewTrainers = @json(auth()->user()->can('trainers.view'));
            const canViewLocations = @json(auth()->user()->can('locations.view') && $locationOptions->isNotEmpty());
            const canViewSportTypes = @json(auth()->user()->can('sport_types.view') && $sportTypeOptions->isNotEmpty());
            const canViewTrainingBase = @json(auth()->user()->can('groups.training_base.view'));
            const canViewAddress = @json(auth()->user()->can('groups.address.view'));
            const defaultFilterStatus = 'active';

            function escapeHtml(text) {
                return KidsCrmTooltip.escapeHtml(text);
            }

            function renderTeamWeekdayBadges(row) {
                const items = Array.isArray(row.weekdays_items) ? row.weekdays_items : [];
                if (!items.length) {
                    return '<span class="text-muted">—</span>';
                }

                const fullTitle = row.weekdays_label ? escapeHtml(row.weekdays_label) : '';
                const badges = items.map(function (item) {
                    const short = escapeHtml(item.short || '');
                    return '<span class="team-weekday-badge">' + short + '</span>';
                }).join('');

                return '<div class="team-weekdays-badges"' +
                    (fullTitle ? ' title="' + fullTitle + '"' : '') +
                    '>' + badges + '</div>';
            }

            function teamsFilterParams() {
                return {
                    title: $('#filter-title').val() || '',
                    status: $('#filter-status').val() || '',
                    trainer_profile_id: canViewTrainers ? ($('#filter-trainer').val() || '') : '',
                    location_id: canViewLocations ? ($('#filter-location').val() || '') : '',
                    sport_type_id: canViewSportTypes ? ($('#filter-sport-type').val() || '') : ''
                };
            }

            function teamsHasNonDefaultFilters() {
                const params = teamsFilterParams();
                return params.title !== ''
                    || params.trainer_profile_id !== ''
                    || params.location_id !== ''
                    || params.sport_type_id !== ''
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

            const dtApi = KidsCrmDataTable.create('#teams-table', {
                columnsSettings: {
                    defaults: {
                        order_by: true,
                        title: true,
                        ...(canViewTrainingBase ? { training_base: true } : {}),
                        ...(canViewAddress ? { address: true } : {}),
                        ...(canViewTrainers ? { trainer_label: true } : {}),
                        ...(canViewLocations ? { locations_label: true } : {}),
                        ...(canViewSportTypes ? { sport_type_label: true } : {}),
                        ...(canViewSchedule ? { weekdays_label: true } : {}),
                        month_price: true,
                        status_label: true,
                        actions: true,
                    },
                    urls: {
                        get: '/admin/teams/columns-settings',
                        save: '/admin/teams/columns-settings',
                    },
                    csrfToken: $('meta[name="csrf-token"]').attr('content'),
                },
                dataTable: {
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
                            if (canViewLocations) {
                                d.location_id = params.location_id;
                            }
                            if (canViewSportTypes) {
                                d.sport_type_id = params.sport_type_id;
                            }
                        }
                    },
                    order: [[1, 'asc']],
                    language: @include('partials.datatables.ru')
                },
                columns: [
                    { type: 'rownum' },
                    { key: 'order_by', type: 'sort', data: 'order_by' },
                    {
                        key: 'title',
                        type: 'link',
                        data: 'title',
                        linkClass: 'edit-team-link',
                        linkAttrs: function (row) {
                            return 'data-id="' + row.id + '" data-bs-toggle="modal" data-bs-target="#editTeamModal"';
                        },
                    },
                    { key: 'training_base', type: 'text', data: 'training_base', when: canViewTrainingBase, searchable: false },
                    { key: 'address', type: 'text', data: 'address', when: canViewAddress, searchable: false },
                    { key: 'trainer_label', type: 'text', data: 'trainer_label', when: canViewTrainers, searchable: false },
                    {
                        key: 'locations_label',
                        type: 'list',
                        data: 'locations_label',
                        itemsKey: 'locations_names',
                        when: canViewLocations,
                        searchable: false,
                        emptyHtml: '<span class="text-muted">Все</span>',
                    },
                    { key: 'sport_type_label', type: 'text', data: 'sport_type_label', when: canViewSportTypes, searchable: false },
                    {
                        key: 'weekdays_label',
                        type: 'list',
                        data: 'weekdays_label',
                        name: 'weekdays_label',
                        when: canViewSchedule,
                        className: 'dt-col-schedule',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (type === 'sort' || type === 'filter') {
                                return data || '';
                            }
                            return renderTeamWeekdayBadges(row);
                        },
                    },
                    { key: 'month_price', type: 'money', data: 'month_price', searchable: false },
                    {
                        key: 'status_label',
                        type: 'badge',
                        data: 'status_label',
                        badgeKey: 'is_enabled',
                        className: 'dt-col-badge',
                    },
                    {
                        key: 'actions',
                        type: 'actions',
                        render: function (data, type, row) {
                            return '<button type="button" ' +
                                'class="btn btn-sm btn-outline-primary edit-team-link" ' +
                                'data-id="' + row.id + '" ' +
                                'data-bs-toggle="modal" ' +
                                'data-bs-target="#editTeamModal">' +
                                'Редактировать' +
                                '</button>';
                        },
                    },
                ],
            });

            const table = dtApi.table;

            function reloadTeamsTable() {
                dtApi.reload();
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
                if (canViewLocations) {
                    $('#filter-location').val('');
                }
                if (canViewSportTypes) {
                    $('#filter-sport-type').val('');
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

            showLogModal("{{ route('logs.data.team') }}");
        });
    </script>
@endpush
