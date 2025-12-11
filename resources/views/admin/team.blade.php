@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3">Группы</h4>
        <hr>

        <div class="buttons">
            <div class="row gy-2 index-team-wrap">
                {{-- ЛЕВАЯ ЧАСТЬ: фильтры --}}
                <div id="search-container" class="col-12 col-md-6">
                    <div class="d-flex flex-wrap gap-2 align-items-center">

                        <input id="filter-title"
                               class="form-control search-input width-170 filter-half"
                               type="text"
                               placeholder="Название группы">

                        <select id="filter-status"
                                class="form-select search-select width-170 filter-half">
                            <option value="">Все группы</option>
                            <option value="active" selected>Только активные</option>
                            <option value="inactive">Только неактивные</option>
                        </select>

                        <button id="filter-apply"
                                class="btn btn-primary filter-half filter-apply">
                            Найти
                        </button>

                        <button id="filter-reset"
                                class="btn btn-secondary btn-reset-filters">
                            Сбросить
                        </button>
                    </div>
                </div>

                {{-- ПРАВАЯ ЧАСТЬ: действия + поля списка + логи --}}
                <div class="col-12 col-md-6 text-start">
                    <div class="d-flex flex-wrap justify-content-md-end gap-2 align-items-center index-team-actions">

                        {{-- Добавить группу --}}
                        <button id="new-team"
                                type="button"
                                class="btn btn-primary mr-2 new-team width-170"
                                data-bs-toggle="modal"
                                data-bs-target="#createTeamModal">
                            Добавить группу
                        </button>

                        {{-- Dropdown "Поля списка" --}}
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle wrap-icon wrap-select"
                                    type="button"
                                    id="columnsDropdown"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                    title="Поля списка">
                                <i class="fa-solid fa-table-columns icon-columns"></i>
                            </button>

                            <div class="dropdown-menu p-3"
                                 aria-labelledby="columnsDropdown"
                                 style="min-width: 220px;">

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="order_by"
                                           id="colOrderBy"
                                           checked>
                                    <label class="form-check-label" for="colOrderBy">
                                        Сортировка
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="title"
                                           id="colTitle"
                                           checked>
                                    <label class="form-check-label" for="colTitle">
                                        Название
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="weekdays_label"
                                           id="colWeekdays"
                                           checked>
                                    <label class="form-check-label" for="colWeekdays">
                                        Расписание
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="status_label"
                                           id="colStatus"
                                           checked>
                                    <label class="form-check-label" for="colStatus">
                                        Статус
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="actions"
                                           id="colActions"
                                           checked>
                                    <label class="form-check-label" for="colActions">
                                        Действия
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Логи --}}
                        <div class="wrap-icon btn"
                             data-bs-toggle="modal"
                             data-bs-target="#historyModal">
                            <i class="fa-solid fa-clock-rotate-left logs"></i>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <hr>

        {{-- ТАБЛИЦА DataTables --}}
        <div class="table-responsive">
            <table id="teams-table" class="table table-striped table-bordered align-middle w-100">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Сортировка</th>
                    <th>Название</th>
                    <th>Расписание</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                {{-- тело будет заполняться DataTables через AJAX --}}
                </tbody>
            </table>
        </div>
    </div>

    {{-- Модальные окна --}}
    @include('includes.modal.createTeam')
    @include('includes.modal.editTeam')
    @include('includes.logModal')

    <style>
        /* Скрываем "Сбросить" на мобилках */
        @media (max-width: 767.98px) {
            .btn-reset-filters {
                display: none !important;
            }

            #search-container .filter-half {
                flex: 0 0 calc(50% - .5rem);
                max-width: calc(50% - .5rem);
            }

            #search-container .search-input,
            #search-container .search-select {
                width: 100%;
            }
        }

        .icon-columns {
            color: #000;
        }

        .wrap-select:hover .icon-columns {
            color: #fff;
        }

        .wrap-select:hover {
            border-color: #f3a12b;
        }

        .filter-apply {
            height: 34px !important;
        }

        .btn-reset-filters {
            height: 34px !important;
        }
    </style>
@endsection

@section('scripts')
    <script>
        $(document).ready(function () {

            const defaultColumnsVisibility = {
                order_by: true,
                title: true,
                weekdays_label: true,
                status_label: true,
                actions: true
            };

            let currentColumnsConfig = {...defaultColumnsVisibility};

            // 0 – нумерация, не настраивается
            const columnsMap = {
                order_by: 1,
                title: 2,
                weekdays_label: 3,
                status_label: 4,
                actions: 5
            };

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

            // --- Инициализация DataTables ---
            const table = $('#teams-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 20,
                lengthMenu: [10, 20, 50, 100],
                ajax: {
                    url: '/admin/teams/data',
                    type: 'GET',
                    data: function (d) {
                        d.title  = $('#filter-title').val();
                        d.status = $('#filter-status').val();
                    }
                },

                columns: [
                    // 0) Нумерация строк
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
                    // 1) Сортировка (order_by)
                    {
                        data: 'order_by',
                        name: 'order_by',
                        className: 'text-center',
                        defaultContent: '',
                        render: function (data, type, row) {
                            return data !== null && data !== undefined && data !== '' ? data : '';
                        }
                    },
                    // 2) Название группы
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
                    // 3) Расписание (список дней недели)
                    {
                        data: 'weekdays_label',
                        name: 'weekdays_label',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (!data) {
                                return '<span class="text-muted">—</span>';
                            }
                            // Можно добавить title для ховера, если вдруг будут сокращения
                            return '<span title="' + data + '">' + data + '</span>';
                        }
                    },
                    // 4) Статус
                    {
                        data: 'status_label',
                        name: 'status_label',
                        render: function (data, type, row) {
                            const badgeClass = row.is_enabled ? 'bg-success' : 'bg-secondary';
                            return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                        }
                    },
                    // 5) Действия
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
                ],

                // по умолчанию сортируем по полю order_by
                order: [[1, 'asc']],
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

            // после инициализации — подтянуть конфиг колонок
            loadColumnsConfigFromServer();

            // --- Фильтры ---
            $('#filter-apply').on('click', function () {
                table.ajax.reload();
            });

            $('#filter-reset').on('click', function () {
                $('#filter-title').val('');
                $('#filter-status').val('');
                table.ajax.reload();
            });

            $('#filter-title').on('keyup', function (e) {
                if (e.key === 'Enter') {
                    table.ajax.reload();
                }
            });

            // --- Обработчик чекбоксов "Поля списка" ---
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
                    success: function () {},
                    error: function () {
                        console.error('Не удалось сохранить настройки колонок');
                    }
                });
            });

            // Логи для групп
            showLogModal("{{ route('logs.data.team') }}");
        });
    </script>
@endsection
