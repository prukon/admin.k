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
                               class="form-control search-input width-170"
                               type="text"
                               placeholder="Имя">

                        <select id="filter-team"
                                class="form-select search-select width-170">
                            <option value="">Группа</option>
                            <option value="none">Без группы</option>
                            @foreach($allTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>

                        <select id="filter-status"
                                class="form-select search-select width-170">
                            <option value="">Все пользователи</option>
                            <option value="active" selected>Только активные</option>
                            <option value="inactive">Только неактивные</option>
                        </select>

                        <button id="filter-apply" class="btn btn-primary">
                            Найти
                        </button>

                        <button id="filter-reset" class="btn btn-outline-secondary">
                            Сбросить
                        </button>
                    </div>
                </div>

                {{-- ПРАВАЯ ЧАСТЬ: кнопки действий и настройка колонок --}}
                <div class="col-12 col-md-6 text-start d-flex flex-wrap justify-content-md-end gap-2 align-items-center">
                    <button id="new-user"
                            type="button"
                            class="btn btn-primary mr-2 new-user width-170"
                            data-bs-toggle="modal"
                            data-bs-target="#createUserModal">
                        Новый пользователь
                    </button>

                    {{-- Старая кнопка настроек доп. полей (не трогаю) --}}
                    <button id="field-modal"
                            type="button"
                            class="btn btn-primary mr-2"
                            data-bs-toggle="modal"
                            data-bs-target="#fieldModal">
                        Настройки
                    </button>

                    {{-- Новая кнопка для настройки отображаемых колонок списка пользователей --}}
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle"
                                type="button"
                                id="columnsDropdown"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">
                            Поля списка
                        </button>
                        <div class="dropdown-menu p-3" aria-labelledby="columnsDropdown" style="min-width: 220px;">
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

                            {{-- При желании сюда же можно добавить чекбоксы под кастомные поля партнёра --}}
                        </div>
                    </div>

                    {{-- Логи, как и было --}}
                    <div class="wrap-icon btn" data-bs-toggle="modal" data-bs-target="#historyModal">
                        <i class="fa-solid fa-clock-rotate-left logs"></i>
                    </div>

                    {{-- Модалки, как и были --}}
                    @include('includes.modal.createUser')
                    @include('includes.modal.editUser')
                    @include('includes.modal.fieldModal')
                    @include('includes.logModal')
                </div>
            </div>
        </div>

        <hr>

        {{-- НОВАЯ ТАБЛИЦА DataTables вместо старого списка --}}
        <div class="table-responsive">
            <table id="users-table" class="table table-striped table-bordered align-middle w-100">
                <thead>
                <tr>
                    <th>Аватар</th>
                    <th>Имя</th>
                    <th>Группа</th>
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
@endsection

@section('scripts')
    <script>
        $(document).ready(function () {
            const visibleColumnsConfigKey = 'usersTableVisibleColumns';

            // Инициализация DataTables
            const table = $('#users-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 20,
                lengthMenu: [10, 20, 50, 100],
                ajax: {
                    url: '/admin/users/data', // <--- прямая строка, как просил
                    type: 'GET',
                    data: function (d) {
                        d.name    = $('#filter-name').val();
                        d.team_id = $('#filter-team').val();
                        d.status  = $('#filter-status').val();
                    }
                },

                columns: [
                    {
                        data: 'avatar',
                        name: 'avatar',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data, type, row) {
                            const url = data || '/images/default-avatar.png';
                            return '<img src="' + url + '" alt="" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">';
                        }
                    },
                    {
                        data: 'name',
                        name: 'name',
                        render: function (data, type, row) {
                            // 👉 здесь добавляем атрибуты Bootstrap для модалки
                            return '<a href="javascript:void(0);" ' +
                                'class="edit-user-link" ' +
                                'data-id="' + row.id + '" ' +
                                'data-bs-toggle="modal" ' +
                                'data-bs-target="#editUserModal">' +
                                data +
                                '</a>';
                        }
                    },
                    {
                        data: 'teams',
                        name: 'teams',
                        defaultContent: ''
                    },
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
                            // 👉 и тут тоже
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

                order: [[1, 'asc']], // сортировка по имени по умолчанию
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

            // -----------------------------
            // Фильтры (поиск/сброс)
            // -----------------------------
            $('#filter-apply').on('click', function () {
                table.ajax.reload();
            });

            $('#filter-reset').on('click', function () {
                $('#filter-name').val('');
                $('#filter-team').val('');
                $('#filter-status').val('');
                table.ajax.reload();
            });

            // По Enter в поле имени
            $('#filter-name').on('keyup', function (e) {
                if (e.key === 'Enter') {
                    table.ajax.reload();
                }
            });

            // -----------------------------
            // Настройка видимости колонок
            // -----------------------------
            const defaultColumnsVisibility = {
                avatar: true,
                name: true,
                teams: true,
                status_label: true,
                actions: true
            };

            function loadVisibleColumnsConfig() {
                const saved = localStorage.getItem(visibleColumnsConfigKey);
                if (!saved) {
                    return {...defaultColumnsVisibility};
                }

                try {
                    const parsed = JSON.parse(saved);
                    return {...defaultColumnsVisibility, ...parsed};
                } catch (e) {
                    return {...defaultColumnsVisibility};
                }
            }

            function applyVisibleColumns(config) {
                // Маппинг ключей на индексы колонок в DataTables
                const map = {
                    avatar: 0,
                    name: 1,
                    teams: 2,
                    status_label: 3,
                    actions: 4
                };

                Object.keys(map).forEach(function (key) {
                    const column = table.column(map[key]);
                    const isVisible = !!config[key];
                    column.visible(isVisible);

                    // поддерживаем чекбоксы в dropdown
                    $('.column-toggle[data-column-key="' + key + '"]')
                        .prop('checked', isVisible);
                });
            }

            let currentColumnsConfig = loadVisibleColumnsConfig();

            // Применяем сохранённую конфигурацию после инициализации таблицы
            table.on('init', function () {
                applyVisibleColumns(currentColumnsConfig);
            });

            // Обработчик чекбоксов в dropdown "Поля списка"
            $('.column-toggle').on('change', function () {
                const key = $(this).data('column-key');
                const isChecked = $(this).is(':checked');

                currentColumnsConfig[key] = isChecked;
                localStorage.setItem(visibleColumnsConfigKey, JSON.stringify(currentColumnsConfig));
                applyVisibleColumns(currentColumnsConfig);
            });

            // -----------------------------
            // Логи (как у тебя было)
            // -----------------------------
            showLogModal("{{ route('logs.data.user') }}");
        });
    </script>
@endsection
