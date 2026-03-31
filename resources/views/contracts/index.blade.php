@extends('layouts.admin2')

@section('title','Документы')

@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3">Документы</h4>
        <hr>

        {{-- Верхняя панель: фильтры слева, кнопки справа --}}
        <div class="buttons">
            <div class="row gy-2 index-contract-wrap">

                {{-- ЛЕВАЯ ЧАСТЬ: фильтры --}}
                <div id="search-container" class="col-12 col-md-6">
                    <div class="d-flex flex-wrap gap-2 align-items-center">

                        {{-- Поиск по имени/фамилии/телефону/email --}}
                        <input id="filter-search"
                               class="form-control search-input width-170 filter-half"
                               type="text"
                               placeholder="Поиск (имя, телефон, email)">

                        {{-- Фильтр по группе --}}
                        <select id="filter-group"
                                class="form-select search-select width-170 filter-half">
                            <option value="">Группа</option>
                            <option value="none">Без группы</option>
                            @foreach($allTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>

                        {{-- Фильтр по статусу (значения подправишь под свои статусы) --}}
                        <select id="filter-status"
                                class="form-select search-select width-170 filter-half">
                            <option value="">Все статусы</option>
                            <option value="draft">Черновик</option>
                            <option value="active">Активный</option>
                            <option value="signed">Подписан</option>
                            <option value="cancelled">Отменён</option>
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

                {{-- ПРАВАЯ ЧАСТЬ: кнопка создания + настройка колонок --}}
                <div class="col-12 col-md-6 text-start">
                    <div class="d-flex flex-wrap justify-content-md-end gap-2 align-items-center">

                        <a href="{{ url('/client-contracts/create') }}"
                           class="btn btn-primary width-170">
                            Создать договор
                        </a>

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
                                           data-column-key="user_name"
                                           id="colUserName"
                                           checked>
                                    <label class="form-check-label" for="colUserName">
                                        Имя
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="user_lastname"
                                           id="colUserLastname"
                                           checked>
                                    <label class="form-check-label" for="colUserLastname">
                                        Фамилия
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="team_title"
                                           id="colTeamTitle"
                                           checked>
                                    <label class="form-check-label" for="colTeamTitle">
                                        Группа
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="user_phone"
                                           id="colUserPhone"
                                           checked>
                                    <label class="form-check-label" for="colUserPhone">
                                        Телефон
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="user_email"
                                           id="colUserEmail"
                                           checked>
                                    <label class="form-check-label" for="colUserEmail">
                                        Email
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="status_label"
                                           id="colStatusLabel"
                                           checked>
                                    <label class="form-check-label" for="colStatusLabel">
                                        Статус
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="updated_at"
                                           id="colUpdatedAt"
                                           checked>
                                    <label class="form-check-label" for="colUpdatedAt">
                                        Обновлён
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
                    </div>
                </div>

            </div>
        </div>

        <hr>

        {{-- Сообщение об успехе (если есть) --}}
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        {{-- ТАБЛИЦА DataTables --}}
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
                <tbody>
                {{-- тело заполняет DataTables через AJAX --}}
                </tbody>
            </table>
        </div>
    </div>

    <style>
        @media (max-width: 767.98px) {
            .btn-reset-filters {
                display: none !important;
            }
        }

        @media (max-width: 767.98px) {
            #search-container .d-flex.flex-wrap {
                flex-wrap: wrap;
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
            const csrfToken = $('meta[name="csrf-token"]').attr('content');

            // Конфиг видимости колонок по умолчанию
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

            // Маппинг ключей на индексы колонок DataTables
            // 0 – нумерация (всегда видна)
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

            function applyVisibleColumns(config, table) {
                Object.keys(columnsMap).forEach(function (key) {
                    const colIndex = columnsMap[key];
                    const column = table.column(colIndex);
                    const isVisible = toBool(config[key], defaultColumnsVisibility[key]);

                    column.visible(isVisible);

                    $('.column-toggle[data-column-key="' + key + '"]')
                        .prop('checked', isVisible);
                });
            }

            function loadColumnsConfigFromServer(table) {
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
                        applyVisibleColumns(currentColumnsConfig, table);
                    },
                    error: function () {
                        currentColumnsConfig = {...defaultColumnsVisibility};
                        applyVisibleColumns(currentColumnsConfig, table);
                    }
                });
            }

            // --- Инициализация DataTables ---
            const table = $('#contracts-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 20,
                lengthMenu: [10, 20, 50, 100],
                ajax: {
                    url: '/client-contracts/data',
                    type: 'GET',
                    data: function (d) {
                        d.search_value = $('#filter-search').val();
                        d.group_id = $('#filter-group').val();
                        d.status = $('#filter-status').val();
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
                order: [[7, 'desc']], // по умолчанию сортируем по "Обновлён"
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

            // после инициализации — подгружаем конфиг колонок из БД
            loadColumnsConfigFromServer(table);

            // --- Фильтры ---
            $('#filter-apply').on('click', function () {
                table.ajax.reload();
            });

            $('#filter-reset').on('click', function () {
                $('#filter-search').val('');
                $('#filter-group').val('');
                $('#filter-status').val('');
                table.ajax.reload();
            });

            $('#filter-search').on('keyup', function (e) {
                if (e.key === 'Enter') {
                    table.ajax.reload();
                }
            });

            // --- Обработчик чекбоксов "Поля списка" ---
            $('.column-toggle').on('change', function () {
                const key = $(this).data('column-key');
                const isChecked = $(this).is(':checked');

                currentColumnsConfig[key] = isChecked ? 1 : 0;

                applyVisibleColumns(currentColumnsConfig, table);

                $.ajax({
                    url: '/client-contracts/columns-settings',
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        columns: currentColumnsConfig
                    },
                    success: function () {
                    },
                    error: function () {
                        console.error('Не удалось сохранить настройки колонок');
                    }
                });
            });
        });
    </script>
@endsection
