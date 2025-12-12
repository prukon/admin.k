@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3">Пользователи</h4>
        <hr>

        <div class="buttons">
            <div class="row gy-2 index-user-wrap">
                {{-- ЛЕВАЯ ЧАСТЬ: фильтры --}}
                <div id="search-container" class="col-12 col-md-6">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <input id="filter-name"
                               class="form-control search-input width-170 filter-half"
                               type="text"
                               placeholder="Имя">

                        <select id="filter-team"
                                class="form-select search-select width-170 filter-half">
                            <option value="">Группа</option>
                            <option value="none">Без группы</option>
                            @foreach($allTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>

                        <select id="filter-status"
                                class="form-select search-select width-170 filter-half">
                            <option value="">Все пользователи</option>
                            <option value="active" selected>Только активные</option>
                            <option value="inactive">Только неактивные</option>
                        </select>

                        <button id="filter-apply" class="btn btn-primary filter-half filter-apply">
                            Найти
                        </button>


                        <button id="filter-reset" class="btn btn-secondary btn-reset-filters">
                            Сбросить
                        </button>
                    </div>
                </div>

                {{-- ПРАВАЯ ЧАСТЬ: кнопки действий и настройка колонок --}}
                <div class="col-12 col-md-6 text-start">
                    <div class="d-flex flex-wrap justify-content-md-end gap-2 align-items-center index-user-actions">


                        <button id="new-user" type="button" class="btn btn-primary mr-2 new-user width-170"
                                data-bs-toggle="modal"
                                data-bs-target="#createUserModal">
                            Новый пользователь
                        </button>
                        
                        <div class="wrap-icon btn" data-bs-toggle="modal" data-bs-target="#fieldModal">
                            <i class="fa-solid fa-gear settings-icon"></i>
                        </div>

                        {{-- Группа: поля списка + история --}}
                        <div class="d-flex align-items-center gap-2">

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
                                               data-column-key="avatar"
                                               id="colAvatar"
                                               checked>
                                        <label class="form-check-label" for="colAvatar">
                                            Аватар
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input column-toggle"
                                               type="checkbox"
                                               data-column-key="name"
                                               id="colName"
                                               checked>
                                        <label class="form-check-label" for="colName">
                                            Имя
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input column-toggle"
                                               type="checkbox"
                                               data-column-key="teams"
                                               id="colTeams"
                                               checked>
                                        <label class="form-check-label" for="colTeams">
                                            Группа
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input column-toggle"
                                               type="checkbox"
                                               data-column-key="birthday"
                                               id="colBirthday"
                                               checked>
                                        <label class="form-check-label" for="colBirthday">
                                            Дата рождения
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input column-toggle"
                                               type="checkbox"
                                               data-column-key="email"
                                               id="colEmail"
                                               checked>
                                        <label class="form-check-label" for="colEmail">
                                            Email
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input column-toggle"
                                               type="checkbox"
                                               data-column-key="phone"
                                               id="colPhone"
                                               checked>
                                        <label class="form-check-label" for="colPhone">
                                            Телефон
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

                        {{-- Модалки, как и были --}}
                        @include('includes.modal.createUser')
                        @include('includes.modal.editUser')
                        @include('includes.modal.fieldModal')
                        @include('includes.logModal')
                    </div>
                </div>
            </div>
        </div>

        <hr>

        {{-- ТАБЛИЦА DataTables --}}
        <div class="table-responsive">
            <table id="users-table" class="table table-striped table-bordered align-middle w-100">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Аватар</th>
                    <th>Имя</th>
                    <th>Группа</th>
                    <th>Дата рождения</th>
                    <th>Email</th>
                    <th>Телефон</th>
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

    <style>
        /* Скрываем "Сбросить" на мобилках (то, что уже обсуждали) */
        @media (max-width: 767.98px) {
            .btn-reset-filters {
                display: none !important;
            }
        }

        /* Имя + Группа в одну строку на мобиле */
        @media (max-width: 767.98px) {
            #search-container .d-flex.flex-wrap {
                flex-wrap: wrap;
            }

            #filter-name,
            #filter-team {
                flex: 1 1 calc(50% - 4px);
                min-width: 0;
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

        @media (max-width: 767.98px) {
            /* элементы, помеченные filter-half, делим строку пополам */
            #search-container .filter-half {
                flex: 0 0 calc(50% - .5rem);
                max-width: calc(50% - .5rem);
            }

            /* на маленьких экранах убираем фиксированную width-170 */
            #search-container .search-input,
            #search-container .search-select {
                width: 100%;
            }
        }

        .filter-apply {
            height: 34px!important;
        }

        .btn-reset-filters {
            height: 34px!important;
        }
    </style>

@endsection

@section('scripts')
    <script>
        $(document).ready(function () {

            const defaultColumnsVisibility = {
                avatar: true,
                name: true,
                teams: true,
                birthday: true,
                email: true,
                phone: true,
                status_label: true,
                actions: true
            };

            let currentColumnsConfig = {...defaultColumnsVisibility};

            // Маппинг ключей на ИНДЕКСЫ колонок DataTables
            // 0 – нумерация (всегда видна, не настраиваем)
            const columnsMap = {
                avatar: 1,
                name: 2,
                teams: 3,
                birthday: 4,
                email: 5,
                phone: 6,
                status_label: 7,
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
                    url: '/admin/users/columns-settings',
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
            const table = $('#users-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 20,
                lengthMenu: [10, 20, 50, 100],
                ajax: {
                    url: '/admin/users/data',
                    type: 'GET',
                    data: function (d) {
                        d.name = $('#filter-name').val();
                        d.team_id = $('#filter-team').val();
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
                            // meta.row — индекс в пределах страницы (0..)
                            // _iDisplayStart — с какого элемента начинается страница
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    // 1) Аватар
                    {
                        data: 'avatar',
                        name: 'avatar',
                        orderable: true,
                        searchable: false,
                        className: 'text-center',
                        render: function (data, type, row) {
                            const url = data || '/img/default-avatar.png';
                            return '<img src="' + url + '" alt="" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">';
                        }
                    },
                    // 2) Имя
                    {
                        data: 'name',
                        name: 'name',
                        render: function (data, type, row) {
                            return '<a href="javascript:void(0);" ' +
                                'class="edit-user-link" ' +
                                'data-id="' + row.id + '" ' +
                                'data-bs-toggle="modal" ' +
                                'data-bs-target="#editUserModal">' +
                                data +
                                '</a>';
                        }
                    },
                    // 3) Группа
                    {data: 'teams', name: 'teams', defaultContent: ''},
                    // 4) Дата рождения
                    {data: 'birthday', name: 'birthday', defaultContent: ''},
                    // 5) Email
                    {data: 'email', name: 'email', defaultContent: ''},
                    // 6) Телефон
                    {data: 'phone', name: 'phone', defaultContent: ''},
                    // 7) Статус
                    {
                        data: 'status_label',
                        name: 'status_label',
                        render: function (data, type, row) {
                            const badgeClass = row.is_enabled ? 'bg-success' : 'bg-secondary';
                            return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                        }
                    },
                    // 8) Действия
                    {
                        data: null,
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        render: function (data, type, row) {
                            return '<button type="button" ' +
                                'class="btn btn-sm btn-outline-primary edit-user-link" ' +
                                'data-id="' + row.id + '" ' +
                                'data-bs-toggle="modal" ' +
                                'data-bs-target="#editUserModal">' +
                                'Редактировать' +
                                '</button>';
                        }
                    }
                ],

                order: [[2, 'asc']], // теперь имя на индексе 2 (0 – номер, 1 – аватар)
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

            // после инициализации — подгружаем конфиг из БД
            loadColumnsConfigFromServer();

            // --- Фильтры ---
            $('#filter-apply').on('click', function () {
                table.ajax.reload();
            });

            $('#filter-reset').on('click', function () {
                $('#filter-name').val('');
                $('#filter-team').val('');
                $('#filter-status').val('');
                table.ajax.reload();
            });

            $('#filter-name').on('keyup', function (e) {
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
                    url: '/admin/users/columns-settings',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        columns: currentColumnsConfig
                    },
                    success: function () {
                    },
                    error: function () {
                        console.error('Не удалось сохранить настройки колонок');
                    }
                });
            });

            showLogModal("{{ route('logs.data.user') }}");
        });
    </script>
@endsection



