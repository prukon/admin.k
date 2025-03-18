@extends('layouts.admin2')

@section('content')
    <!-- Обёртка для фильтров и таблицы (для полноэкранного режима) -->
    <div class="schedule-fullscreen-wrapper mt-3">
        <!-- Ряд с фильтрами, легендой, кнопками полноэкранного/обычного режима и поиском -->
        <div class="row mb-3 align-items-center schedule-controls">
            <!-- Фильтр по году -->
            <div class="col-auto wrap-filter-year">
                <select id="filter-year" class="form-select schedule-filter-year">
                    @for($y = date('Y') - 5; $y <= date('Y') + 5; $y++)
                        <option value="{{ $y }}" @if($year == $y) selected @endif>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <!-- Фильтр по месяцу -->
            <div class="col-auto wrap-filter-month">
                <select id="filter-month" class="form-select schedule-filter-month">
                    @php
                        $months = [
                            '01' => 'Январь',
                            '02' => 'Февраль',
                            '03' => 'Март',
                            '04' => 'Апрель',
                            '05' => 'Май',
                            '06' => 'Июнь',
                            '07' => 'Июль',
                            '08' => 'Август',
                            '09' => 'Сентябрь',
                            '10' => 'Октябрь',
                            '11' => 'Ноябрь',
                            '12' => 'Декабрь'
                        ];
                    @endphp
                    @foreach($months as $mKey => $mName)
                        <option value="{{ $mKey }}" @if($month == $mKey) selected @endif>{{ $mName }}</option>
                    @endforeach
                </select>
            </div>
            <!-- Фильтр по группам -->
            <div class="col-auto wrap-filter-team">
                <select id="filter-team" class="form-select schedule-filter-team">
                    <option value="all" @if($team_id=='all') selected @endif>Все группы</option>
                    <option value="none" @if($team_id=='none') selected @endif>Без группы</option>
                    @foreach($teams as $team)
                        <option value="{{ $team->id }}"
                                @if($team_id == $team->id) selected @endif>{{ $team->title }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Кнопка Настройки -->
            <div class="col-auto wrap-filter-setting">
                <button class="btn btn-primary" id="btn-settings">Настройки</button>
            </div>

            <!-- Кнопка полноэкранного/обычного режима -->
            <div class="col-auto wrap-filter-fullscreen">
                <button id="btn-fullscreen" class="btn btn-primary schedule-btn-fullscreen">
                    <i class="fas fa-expand"></i>
                </button>
            </div>

            <!-- Поисковая строка -->
            <div class="col wrap-filter-search">
                <input type="text" id="table-search" class="form-control table-search" placeholder="Поиск">
            </div>
            {{--Логи--}}
            <div class="wrap-icon btn btn-history-modal" data-bs-toggle="modal" data-bs-target="#historyModal">
                <i class="fa-solid fa-clock-rotate-left logs "></i>
            </div>
        </div>

        <!-- Таблица расписания -->
        <div class="table-responsive schedule-table-container">
            <table id="schedule-table" class="table table-bordered schedule-table">
                <thead>
                <tr>
                    <th class="text-center align-middle sticky-col-1 zi-50 col-number">№</th>
                    <th class="sticky-col-2 zi-50 col-name">ФИО</th>

                    <th class="schedule-payment-status sticky-col-2">
                        <i class="nav-icon fa-solid fa-ruble-sign"></i>
                    </th>
                    <th class="schedule-col-setup sticky-col-3 text-center">
                        <i class="fa-solid fa-user-pen"></i>
                    </th>

                    @php
                        $days = [];
                        $start = $startOfMonth->copy();
                        $end   = $endOfMonth->copy();
                        while($start->lte($end)){
                            $days[] = $start->copy();
                            $start->addDay();
                        }
                    @endphp
                    @foreach($days as $day)
                        <th class="schedule-day-header @if(isset($teamWeekdays) && count($teamWeekdays) && in_array($day->format('N'), $teamWeekdays)) highlight-column @endif"
                            style="width: 5px; height: 5px;">
                            <div class="d-flex flex-column justify-content-center align-items-center">
                                <span>{{ $day->format('d') }}</span>
                                <span>{{ mb_substr($day->locale('ru_RU')->isoFormat('ddd'), 0, 2) }}</span>
                            </div>
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($users as $index => $user)
                    <tr data-user-id="{{ $user->id }}">

                        <td class="text-center align-middle sticky-col-1 number-line">{{ $index + 1 }}</td>
                        <td class="schedule-user-name sticky-col-2">{{ $user->name }}</td>


                        <td class="text-center">
                            @if(isset($userPrices[$user->id]) && $userPrices[$user->id]->is_paid == 1)
                                <i class="fas fa-circle-check text-success"></i>
                            @endif
                        </td>

                        <!-- В ячейке таблицы, рядом с юзером -->
                        <td class="text-center">
                            <i class="fa-regular fa-pen-to-square edit-user-schedule"
                               data-user-id="{{ $user->id }}"
                               style="cursor: pointer;"
                               title="Редактировать расписание"
                            ></i>
                        </td>


                        @foreach($days as $day)
                            @php
                                $dateKey = $user->id . '_' . $day->format('Y-m-d');
                                $entry   = $scheduleEntries->has($dateKey) ? $scheduleEntries->get($dateKey) : null;
                                $cellStatus = '';
                                $cellIcon   = '';
                                $bgColor    = '';
                                if($entry){
                                    if($entry->status == 'N'){
                                        $cellStatus = 'Н';
                                        $bgColor    = 'rgba(255,0,0,0.3)';
                                    } elseif($entry->status == 'R'){
                                        $cellIcon = '<i class="fas fa-check"></i>';
                                        $bgColor  = 'rgba(255,255,0,0.3)';
                                    } elseif($entry->status == 'Z'){
                                        $cellIcon = '<i class="fas fa-snowflake"></i>';
                                        $bgColor  = 'rgba(0,191,255,0.3)';
                                    }
                                }
                            @endphp

                            @php
                                $entry   = $scheduleEntries->has($dateKey) ? $scheduleEntries->get($dateKey) : null;
                                $statusId = $entry?->status_id; // null или число
                                $statusObject = $statusId ? $availableStatuses->where('id', $statusId)->first() : null;
                                $cellName = $statusObject?->name ?? '';
                                $cellIcon = $statusObject?->icon ?? '';
                                $cellColor= $statusObject?->color ?? '';
                            @endphp

                            <td class="schedule-cell text-center
                                @if(isset($teamWeekdays) && count($teamWeekdays) && in_array($day->format('N'), $teamWeekdays)) highlight-column @endif"

                                data-user-id="{{ $user->id }}"
                                data-date="{{ $day->format('Y-m-d') }}"
                                style=
                                "width: 5px;
                                        height: 5px;
                                        padding: 0;
                                        margin: 0;
                                        background-color: {{ $cellColor }};
                                        position: relative;
                                        cursor: pointer;"

                                data-status-id="{{ $statusId }}"
                                data-comment="{{ $entry?->description }}"
                                style="background-color:{{ $cellColor ? $cellColor : 'transparent' }};">
                                @if($cellIcon)
                                    <i class="{{ $cellIcon }}"></i>
                                @else
                                    {{ $cellName }}
                                @endif

                                @if($entry && !empty($entry->description))
                                    <div class="cell-comment-indicator"
                                         style="position: absolute; top: 0; right: 0; width: 0; height: 0; border-top: 5px solid red; border-left: 5px solid transparent;"></div>
                                @endif
                            </td>

                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Модальное окно для редактирования ячейки -->
    <div class="modal fade" id="cellEditModal" tabindex="-1" aria-labelledby="cellEditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content schedule-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cellEditModalLabel">Редактирование ячейки</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form id="cellEditForm">

                        <input type="hidden" name="user_id" id="edit-user-id">
                        <input type="hidden" name="date" id="edit-date">

                        <div class="mb-3">
                            <label class="form-label d-block">Статус</label>

                            <!-- Радиокнопка для варианта "не выбрано" -->
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio"
                                       name="status_id" id="status-empty" value="">
                                <label class="form-check-label" for="status-empty">
                                    -- не выбрано --
                                </label>
                            </div>

                            @foreach($availableStatuses as $st)
                                <div class="form-check mb-2 d-flex align-items-center">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="status_id"
                                           id="status-{{ $st->id }}"
                                           value="{{ $st->id }}"
                                           data-icon="{{ $st->icon }}"
                                           data-color="{{ $st->color }}">

                                    <label class="form-check-label ms-2" for="status-{{ $st->id }}">
            <span style="display: inline-block;
                    background-color: {{ $st->color }};
                    padding: 0.3rem;
                    border-radius: 0.25rem;">
                <i class="{{ $st->icon }}"></i>
            </span>
                                        <span class="ms-1">{{ $st->name }}</span>
                                    </label>
                                </div>
                            @endforeach

                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Комментарий</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Модалка №1: редактирование расписания конкретного пользователя -->
    <div class="modal fade" id="userScheduleModal" tabindex="-1" aria-labelledby="userScheduleModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="userScheduleModalLabel" class="modal-title">Личное расписание ученика</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body" id="userScheduleModalContent">
                    <!-- Содержимое будет динамически подставляться через JS -->
                    Загрузка...
                </div>
            </div>
        </div>
    </div>

    <!-- Модалка №2: выбор группы, если у пользователя группы нет -->
    <div class="modal fade" id="chooseGroupModal" tabindex="-1" aria-labelledby="chooseGroupModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="chooseGroupModalLabel" class="modal-title">Выбрать группу</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="selectGroup" class="form-label">Доступные группы:</label>
                        <select class="form-select" id="selectGroup">
                            <option value="">-- выбрать --</option>
                            @foreach($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button id="btnSaveUserGroup" class="btn btn-primary">Сохранить</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Модальное окно "Настройки" -->
    <!-- МОДАЛКА НАСТРОЕК (СПИСОК СТАТУСОВ) -->
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <!-- Обратите внимание на .modal-dialog, стили см. в CSS -->
        <div class="modal-dialog" id="settingsModalDialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="settingsModalLabel">Настройки статусов</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="text-start">
                        <!-- Кнопка "Новый статус" (открывает createStatusModal) -->
                        <button type="button" class="btn btn-primary mb-3" id="btn-new-status">
                            Новый статус
                        </button>
                    </div>
                    <!-- Таблица статусов -->
                    <table id="statuses-table" class="table">
                        <thead>
                        <tr>
                            <th>Название</th>
                            <th>Иконка/Цвет</th>
                            <th>Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        <!-- Заполняется динамически -->
                        </tbody>
                    </table>
                </div>

                <!-- Кнопки внизу: "Отменить" (закрыть), "Сохранить" (перезагрузить страницу) -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                    {{--<button type="button" class="btn btn-primary" id="btnSaveChanges">Сохранить</button>--}}
                </div>
            </div>
        </div>
    </div>

    <!-- МОДАЛКА СОЗДАНИЯ СТАТУСА -->
    <div class="modal fade" id="createStatusModal" tabindex="-1" aria-labelledby="createStatusModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="createStatusForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createStatusModalLabel">Создать статус</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="createName" class="form-label">Название</label>
                            <input type="text" class="form-control" id="createName" name="name" required>
                        </div>

                        <!-- Выбор иконки -->
                        <div class="mb-3">
                            <label class="form-label">Иконка</label>
                            <input type="hidden" id="createIcon" name="icon">
                            <div id="createIconList" class="d-flex flex-wrap gap-2">
                                <div class="icon-item border p-2 text-center" data-icon="fas fa-snowflake">
                                    <i class="fas fa-snowflake fa-2x"></i>
                                </div>
                                <div class="icon-item border p-2 text-center" data-icon="fas fa-check">
                                    <i class="fas fa-check fa-2x"></i>
                                </div>
                                <div class="icon-item border p-2 text-center" data-icon="fas fa-bell">
                                    <i class="fas fa-bell fa-2x"></i>
                                </div>
                                <div class="icon-item border p-2 text-center" data-icon="fas fa-star">
                                    <i class="fas fa-star fa-2x"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Выбор цвета -->
                        <div class="mb-3">
                            <label for="createColor" class="form-label">Цвет</label>
                            <input type="color" class="form-control form-control-color" id="createColor" name="color"
                                   title="Выберите цвет">
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-success">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- МОДАЛКА РЕДАКТИРОВАНИЯ СТАТУСА -->
    <div class="modal fade" id="editStatusModal" tabindex="-1" aria-labelledby="editStatusModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editStatusForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editStatusModalLabel">Редактирование статуса</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Скрытое поле для ID -->
                        <input type="hidden" id="editStatusId" name="status_id">

                        <div class="mb-3">
                            <label for="editName" class="form-label">Название</label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>

                        <!-- Выбор иконки -->
                        <div class="mb-3">
                            <label class="form-label">Иконка</label>
                            <input type="hidden" id="editIcon" name="icon">
                            <div id="editIconList" class="d-flex flex-wrap gap-2">
                                <div class="icon-item border p-2 text-center" data-icon="fas fa-snowflake">
                                    <i class="fas fa-snowflake fa-2x"></i>
                                </div>
                                <div class="icon-item border p-2 text-center" data-icon="fas fa-check">
                                    <i class="fas fa-check fa-2x"></i>
                                </div>
                                <div class="icon-item border p-2 text-center" data-icon="fas fa-bell">
                                    <i class="fas fa-bell fa-2x"></i>
                                </div>
                                <div class="icon-item border p-2 text-center" data-icon="fas fa-star">
                                    <i class="fas fa-star fa-2x"></i>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editColor" class="form-label">Цвет</label>
                            <input type="color" class="form-control form-control-color" id="editColor" name="color"
                                   title="Выберите цвет">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Модальное окно подтверждения удаления -->
    @include('includes.modal.confirmDeleteModal')

    <!-- Модальное окно успешного обновления данных -->
    @include('includes.modal.successModal')

    <!-- Модальное окно логов -->
    @include('includes.logModal')

@endsection

@section('scripts')
    <!-- Подключение необходимых CDN -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <!-- Datepicker (Bootstrap Datepicker) -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/locales/bootstrap-datepicker.ru.min.js"></script>
    @vite(['resources/css/schedule.css',])


    {{--Настройки--}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Модалки
            var settingsModalEl = document.getElementById('settingsModal');
            var settingsModal = new bootstrap.Modal(settingsModalEl);

            var createStatusModalEl = document.getElementById('createStatusModal');
            var createStatusModal = new bootstrap.Modal(createStatusModalEl);

            var editStatusModalEl = document.getElementById('editStatusModal');
            var editStatusModal = new bootstrap.Modal(editStatusModalEl);

            // Кнопка "Настройки"
            var btnSettings = document.getElementById('btn-settings');
            // Таблица статусов
            var statusesTableBody = document.querySelector('#statuses-table tbody');
            // Кнопка "Новый статус"
            var btnNewStatus = document.getElementById('btn-new-status');

            // При клике "Настройки" — грузим статусы и показываем модалку
            btnSettings.addEventListener('click', function () {
                loadStatuses();
                settingsModal.show();
            });

            // "Новый статус" — сбрасываем форму и показываем модалку создания
            btnNewStatus.addEventListener('click', function () {
                document.getElementById('createStatusForm').reset();
                // Сбрасываем выбранные иконки
                document.querySelectorAll('#createIconList .icon-item').forEach(i => i.classList.remove('selected'));
                document.getElementById('createIcon').value = '';
                createStatusModal.show();
            });


            // Загрузка статусов
            function loadStatuses() {
                fetch("{{ route('statuses.index') }}")
                    .then(resp => resp.json())
                    .then(data => {
                        statusesTableBody.innerHTML = '';
                        data.statuses.forEach(st => {
                            let tr = document.createElement('tr');
                            tr.innerHTML = `
                        <td>
                            ${st.name}
                            ${
                                // Если системный, показываем иконку с ховером (пример)
                                st.is_system
                                    ? `<i class="fas fa-question-circle ms-1"
                                      data-bs-toggle="tooltip"
                                      title="Системный статус. Невозможно удалить"
                                   ></i>`
                                    : ''
                                }
                        </td>
                        <td>
                            ${
                                st.icon
                                    ? `<i class="${st.icon}"
                                     style="background-color: ${st.color};
                                            color: #000000;
                                            padding: 5px;
                                            border-radius: 3px;"></i>`
                                    : ''
                                }
                        </td>
                        <td>
                            ${
                                st.is_system
                                    ? ''
                                    : `<button class="btn btn-sm btn-success"
                                           data-action="edit"
                                           data-id="${st.id}"
                                           data-name="${st.name}"
                                           data-icon="${st.icon ?? ''}"
                                           data-color="${st.color ?? ''}">
                                       Изменить
                                   </button>
                                   <button class="btn btn-sm btn-danger"
                                           data-action="delete"
                                           data-id="${st.id}">
                                       Удалить
                                   </button>`
                                }
                        </td>
                    `;
                            statusesTableBody.appendChild(tr);
                        });

                        // Инициализируем Bootstrap Tooltip (иконки вопроса)
                        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                            return new bootstrap.Tooltip(tooltipTriggerEl);
                        });
                    })
                    .catch(err => console.error(err));
            }

            // СОЗДАНИЕ СТАТУСА
            var createStatusForm = document.getElementById('createStatusForm');
            createStatusForm.addEventListener('submit', function (e) {
                e.preventDefault();
                let formData = new FormData(createStatusForm);

                fetch("{{ route('statuses.store') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                })
                    .then(resp => resp.json())
                    .then(data => {
                        if (data.success) {
                            showSuccessModal("Создание статуса", "Статус успешно создан.", 1);
                        } else {
                            // alert(data.error ?? 'Ошибка при создании статуса');
                            $('#errorModal').modal('show');
                        }
                    })
                    .catch(err => console.error(err));
            });

            // РЕДАКТИРОВАНИЕ / УДАЛЕНИЕ
            var editStatusForm = document.getElementById('editStatusForm');
            statusesTableBody.addEventListener('click', function (e) {
                let action = e.target.dataset.action;
                if (action === 'edit') {
                    let id = e.target.dataset.id;
                    let name = e.target.dataset.name;
                    let icon = e.target.dataset.icon;
                    let color = e.target.dataset.color || '#ffffff';

                    // Заполняем форму
                    document.getElementById('editStatusId').value = id;
                    document.getElementById('editName').value = name;
                    document.getElementById('editIcon').value = icon;
                    document.getElementById('editColor').value = color;

                    // Сбрасываем/подсвечиваем нужную иконку
                    document.querySelectorAll('#editIconList .icon-item').forEach(item => {
                        item.classList.remove('selected');
                        if (item.dataset.icon === icon) {
                            item.classList.add('selected');
                        }
                    });
                    editStatusModal.show();
                } else if (action === 'delete') {


                    showConfirmDeleteModal(
                        "Удаление статуса",
                        "Вы уверены, что хотите удалить этот статус? (Ранее установленные значения для дней с этим статусом останутся без изменений.)",
                        function () {
                            let id = e.target.dataset.id;


                            fetch("{{ url('admin/statuses') }}/" + id, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                }
                            })
                                .then(resp => resp.json())
                                .then(d => {
                                    if (d.success) {
                                        showSuccessModal("Удаление статуса", "Статус успешно удален.", 1);
                                        // loadStatuses();
                                    } else {
                                        // alert(d.error ?? 'Ошибка при удалении статуса');
                                        $('#errorModal').modal('show');

                                    }
                                })
                                .catch(err => console.error(err));
                        });


                }
            });

            // Сабмит формы редактирования (исправление 405: POST + _method=PATCH)
            editStatusForm.addEventListener('submit', function (e) {
                e.preventDefault();

                let statusId = document.getElementById('editStatusId').value;
                let formData = new FormData(editStatusForm);
                formData.append('_method', 'PATCH'); // Laravel "увидит" PATCH

                fetch("{{ url('admin/statuses') }}/" + statusId, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                })
                    .then(resp => resp.json())
                    .then(d => {
                        if (d.success) {
                            showSuccessModal("Редактирование статуса", "Статус успешно обновлен.", 1);

                            // editStatusModal.hide();
                            // loadStatuses();

                        } else {
                            alert(d.error ?? 'Ошибка при обновлении статуса');
                        }
                    })
                    .catch(err => console.error(err));
            });

            // Выбор иконки (Create)
            let createIconList = document.getElementById('createIconList');
            createIconList.querySelectorAll('.icon-item').forEach(item => {
                item.addEventListener('click', function () {
                    createIconList.querySelectorAll('.icon-item').forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
                    document.getElementById('createIcon').value = this.dataset.icon;
                });
            });

            // Выбор иконки (Edit)
            let editIconList = document.getElementById('editIconList');
            editIconList.querySelectorAll('.icon-item').forEach(item => {
                item.addEventListener('click', function () {
                    editIconList.querySelectorAll('.icon-item').forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
                    document.getElementById('editIcon').value = this.dataset.icon;
                });
            });
        });
    </script>

    <script>
        $(document).ready(function () {
            // Проверяем, есть ли в URL fullscreen=1; если да, активируем полноэкранный режим
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('fullscreen') == '1') {
                $('.schedule-fullscreen-wrapper').addClass('fullscreen');
                $('body').addClass('no-scroll');
                $('#btn-fullscreen').html('<i class="fas fa-compress"></i>');
            }

            // Инициализация DataTables (без стандартного поля поиска – убрали 'f' из dom)
                    {{--            var numDays = {{ count($days    ) }};--}}
            var numDays = $('.schedule-day-header').length;

            var dtColumns = [
                {orderable: false}, // Номер строки
                {orderable: true}, // ФИО
                {orderable: true}, // Статус оплаты
                {orderable: false}  // Кнопка редактирования

            ];
            for (var i = 0; i < numDays; i++) {
                dtColumns.push({orderable: false});
            }
            var table = $('#schedule-table').DataTable({
                paging: false,
                info: false,
                ordering: true,
                order: [],
                columns: dtColumns,
                dom: 'lrtip', // отключён стандартный поиск DataTables
                language: {
                    search: "Поиск:",
                    zeroRecords: "Ничего не найдено",
                    infoEmpty: "",
                }
            });

            // Привязка кастомного поиска к DataTables
            $('#table-search').on('keyup', function () {
                table.search(this.value).draw();
            });


            // Открытие модального окна редактирования расписания
            $(document).on('click', '.fa-pen-to-square', function () {
                var userId = $(this).closest('tr').data('user-id');

                $.ajax({
                    url: '/schedule/user/' + userId + '/edit',
                    method: 'GET',
                    success: function (response) {
                        if (response.success) {
                            $('#editUserModal .modal-body').html(response.html);
                            $('#editUserModal').modal('show');
                        }
                    }
                });
            });


            // Перезагрузка страницы при изменении фильтров; проверяем, в полноэкранном ли режиме
            $('.schedule-filter-year, .schedule-filter-month, .schedule-filter-team').on('change', function () {
                var year = $('#filter-year').val();
                var month = $('#filter-month').val();
                var team = $('#filter-team').val();

                // Формируем новый URL с учётом выбранных параметров
                var newUrl = new URL(window.location.href);
                newUrl.searchParams.set('year', year);
                newUrl.searchParams.set('month', month);
                newUrl.searchParams.set('team', team);

                // Если мы сейчас в полноэкранном режиме, добавим fullscreen=1
                if ($('.schedule-fullscreen-wrapper').hasClass('fullscreen')) {
                    newUrl.searchParams.set('fullscreen', '1');
                } else {
                    newUrl.searchParams.delete('fullscreen');
                }
                // Переходим по новому URL
                window.location.href = newUrl.toString();
            });

            // Открытие модального окна для редактирования ячейки
            var cellEditModal = new bootstrap.Modal(document.getElementById('cellEditModal'), {});
            var currentCell;


            $(document).on('click', '.schedule-cell', function () {
                currentCell = $(this);
                let userId = $(this).data('user-id');
                let date = $(this).data('date');
                let statusId = $(this).data('status-id') || '';
                let comment = $(this).data('comment') || '';

                $('#edit-user-id').val(userId);
                $('#edit-date').val(date);
                $('#edit-status-id').val(statusId);
                $('#description').val(comment);

                cellEditModal.show();
            })


            // Отправка данных формы редактирования ячейки
            $('#cellEditForm').on('submit', function (e) {
                e.preventDefault();

                $.ajax({
                    url: "{{ route('schedule.update') }}",
                    method: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },


                    success: function (response) {
                        if (response.success) {
                            // Вместо #edit-status-id ищем выбранную радиокнопку
                            let chosenRadio = $('input[name="status_id"]:checked');
                            let chosenStatusId = chosenRadio.val();

                            // Достаем data-icon, data-color из выбранной радиокнопки
                            let icon = chosenRadio.data('icon') || '';
                            let color = chosenRadio.data('color') || '';

                            console.log(icon);
                            console.log(color);

                            // Чтобы получить текст (название статуса) – берём текст из соответствующего label
                            // (или его часть). Можно просто отрезать иконку, а можно хранить имя статуса
                            // в отдельном data-атрибуте, если нужна точность.
                            // Ниже – упрощённо всё из label, не забыв trim()
                            let text = chosenRadio.closest('.form-check').find('label').text().trim();

                            // Вставляем иконку или текст в ячейку
                            if (icon) {
                                currentCell.html('<i class="' + icon + '"></i>');
                            } else {
                                currentCell.text(text);
                            }

                            // Фоновый цвет
                            currentCell.css('background-color', color);
                            console.log(currentCell);


                            // Ставим атрибуты на ячейку – чтобы при повторном клике данные были актуальны
                            currentCell.attr('data-status-id', chosenStatusId);
                            currentCell.attr('data-comment', $('#description').val().trim());

                            // ... остальной код (уголок для комментария и т.д.) ...

                            // Закрываем модалку
                            cellEditModal.hide();
                        }
                    }


                });
            });


            // Полноэкранный / обычный режим
            $('#btn-fullscreen').on('click', function () {
                $('.schedule-fullscreen-wrapper').toggleClass('fullscreen');
                $('body').toggleClass('no-scroll');
                // Обновляем параметр в URL (без перезагрузки) – для сохранения/удаления fullscreen
                var newUrl = new URL(window.location.href);
                if ($('.schedule-fullscreen-wrapper').hasClass('fullscreen')) {
                    $('#btn-fullscreen').html('<i class="fas fa-compress"></i>');
                    newUrl.searchParams.set('fullscreen', '1');
                    // $('#btn-legend').hide();
                    $('.wrap-filter-year').hide();

                } else {
                    $('#btn-fullscreen').html('<i class="fas fa-expand"></i>');
                    newUrl.searchParams.delete('fullscreen');
                    // $('#btn-legend').show();
                    $('.wrap-filter-year').show();

                }
                window.history.replaceState({}, '', newUrl);
            });
        });

        {{--Редактирования расписания юзера--}}
        $(document).ready(function () {

            let userScheduleModalEl = document.getElementById('userScheduleModal');
            let userScheduleModal = new bootstrap.Modal(userScheduleModalEl);

            // При клике на иконку "карандаш"
            $(document).on('click', '.edit-user-schedule', function () {
                let userId = $(this).data('user-id');
                // Очищаем содержимое перед загрузкой
                $('#userScheduleModalContent').html('Загрузка...');

                // Запрашиваем информацию о пользователе
                $.ajax({
                    url: '/admin/user-schedule/' + userId,
                    method: 'GET',
                    success: function (resp) {
                        if (!resp.success) {
                            $('#userScheduleModalContent').html('Ошибка при получении данных.');
                            userScheduleModal.show();
                            return;
                        }
                        let user = resp.user; // { id, name, team_id, team_title }
                        let groupWeekdays = resp.groupWeekdays; // [1,2,4] например
                        let defaultFrom = resp.defaultFrom;   // "2025-03-17"
                        let defaultTo = resp.defaultTo;     // "2025-08-31"

                        let html = `
                    <div>
                        <p><strong>ФИО:</strong> ${user.name}</p>
                `;
                        // Если нет группы — кнопка "Выбрать группу", иначе название группы
                        if (!user.team_id) {
                            html += `
                        <p><strong>Группа:</strong> <span class="text-danger">не выбрана</span></p>
                        <button type="button"
                                class="btn btn-primary mb-3"
                                id="btnChooseGroup"
                                data-user-id="${user.id}">
                            Выбрать группу
                        </button>
                    `;
                        } else {
                            html += `<p><strong>Группа:</strong> ${user.team_title}</p>`;
                        }

                        // Далее – чекбоксы пн–вс:
                        // Понедельник=1, Вторник=2, ..., Воскресенье=7 (по ISO)
                        let days = [
                            {id: 1, label: 'Пн'},
                            {id: 2, label: 'Вт'},
                            {id: 3, label: 'Ср'},
                            {id: 4, label: 'Чт'},
                            {id: 5, label: 'Пт'},
                            {id: 6, label: 'Суб'},
                            {id: 7, label: 'Вск'},
                        ];
                        html += `<div class="mb-3">`;
                        html += `<div  class="label-ind-day"><strong>Новое расписание:</strong></div >`;
                        days.forEach((d) => {
                            // Если этот день есть в groupWeekdays, добавляем класс highlight (чтобы бордер был "ярче")
                            let highlight = groupWeekdays.includes(d.id) ? 'highlight-border' : '';

                            // Формируем единый label с классами
                            html += `
        <label class="day-checkbox ${highlight}" style="margin-right: 0.5rem;">
            <input class="form-check-input user-day-chk"
                   type="checkbox"
                   value="${d.id}"
                   id="chk_${d.id}"
                   style="margin-right: 0.3rem;" />
            ${d.label}
        </label>
    `;
                        });
                        html += `</div>`;

                        // Поля "От" и "До" (datepickers)
                        html += `
<div><strong>Период действия нового расписания:</strong></div>
                    <div class="mb-3">
                        <label for="dateFrom" class="form-label">От:</label>
                        <input type="date" id="dateFrom" class="form-control" value="${defaultFrom}">
                    </div>
                    <div class="mb-3">
                        <label for="dateTo" class="form-label">До:</label>
                        <input type="date" id="dateTo" class="form-control" value="${defaultTo}">
                    </div>
                `;

                        // Кнопка "Сохранить"
                        html += `
                    <button type="button" class="btn btn-success" id="btnSaveUserSchedule" data-user-id="${user.id}">
                        Изменить расписание
                    </button>
                `;

                        html += `</div>`;

                        // Помещаем всё в модалку
                        $('#userScheduleModalContent').html(html);

                        // Дополнительно можем раскрасить рамку, если dayId есть в groupWeekdays
                        $('.highlight-border').css('border', '2px dashed #0d6efd');

                        // Показываем модалку
                        userScheduleModal.show();
                    },
                    error: function () {
                        $('#userScheduleModalContent').html('Ошибка AJAX-запроса.');
                        userScheduleModal.show();
                    }
                });
            });


            // Клик по кнопке "Выбрать группу" (внутри основной модалки)
            let chooseGroupModalEl = document.getElementById('chooseGroupModal');
            let chooseGroupModal = new bootstrap.Modal(chooseGroupModalEl);

            $(document).on('click', '#btnChooseGroup', function () {
                let userId = $(this).data('user-id');
                $('#chooseGroupModal').data('user-id', userId);
                $('#selectGroup').val(''); // сбрасываем
                chooseGroupModal.show();
            });

            // Сохранение группы пользователю
            $('#btnSaveUserGroup').on('click', function () {
                let userId = $('#chooseGroupModal').data('user-id');
                let teamId = $('#selectGroup').val();

                $.ajax({
                    url: '/admin/user/' + userId + '/set-group',
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    data: {team_id: teamId},
                    success: function (resp) {
                        if (resp.success) {
                            alert('Группа назначена пользователю!');
                            // Закрываем chooseGroupModal
                            chooseGroupModal.hide();
                            // И перезагружаем блок в userScheduleModal,
                            // чтобы теперь отобразилось новое название группы.
                            // Самый простой способ — просто заново кликнуть по иконке:
                            userScheduleModal.hide();
                            $('.edit-user-schedule[data-user-id="' + userId + '"]').trigger('click');
                        }
                    }
                });
            });


            // Клик по кнопке "Сохранить расписание" (чекбоксы + диапазон дат)
            $(document).on('click', '#btnSaveUserSchedule', function () {

                let userId = $(this).data('user-id');
                showConfirmDeleteModal(
                    "Изменение расписания",
                    "Вы уверены, что хотите изменить расписание пользователя?",
                    function () {

                        // let userId = $(this).data('user-id');
                        let checked = [];
                        $('.user-day-chk:checked').each(function () {
                            checked.push($(this).val());
                        });
                        let dateFrom = $('#dateFrom').val();
                        let dateTo = $('#dateTo').val();

                        $.ajax({
                            url: '/admin/user/' + userId + '/update-schedule-range',
                            method: 'POST',
                            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                            data: {
                                weekdays: checked,
                                date_from: dateFrom,
                                date_to: dateTo
                            },
                            success: function (resp) {
                                if (resp.success) {
                                    showSuccessModal("Изменение расписания", "Индивидуальное расписание пользователя изменено.", 1);
                                } else {
                                    $('#errorModal').modal('show');

                                }
                            }
                        });

                    });
            });

        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            showLogModal("{{ route('logs.data.schedule') }}"); // Здесь можно динамически передать route
        });
    </script>

@endsection
