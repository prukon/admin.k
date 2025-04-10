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
                                data-user-name="{{ $user->name }}"

                                data-date="{{ $day->format('Y-m-d') }}"
                                style= "background-color: {{ $cellColor }};"

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
                                         style="position: absolute;
                                          top: 0;
                                          right: 0;
                                          width: 0;
                                           height: 0;
                                            border-top: 5px solid red;
                                             border-left: 5px solid transparent;">

                                    </div>
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

                    {{-- Добавленный блок: информация о редактируемой ячейке --}}
                    <div class="mb-3">
                        <div><span id="edit-user-name-display"></span></div>
                        <div><span id="edit-date-display"></span></div>
                    </div>


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
    @vite(['resources/js/schedule.js',])

@endsection
