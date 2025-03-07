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
            <!-- Кнопка Легенда -->
            <div class="col-auto wrap-filter-legend">
                <button id="btn-legend" class="btn btn-primary schedule-btn-legend">Легенда</button>
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
        </div>

        <!-- Таблица расписания -->
        <div class="table-responsive schedule-table-container">
            <table id="schedule-table" class="table table-bordered schedule-table">
                <thead>
                <tr>
                    {{--                    <th class="text-center align-middle table-number" style="width:auto;">#</th>--}}
                    {{--                    <th style="max-width:150px;" class="schedule-user-name">ФИО</th>--}}

                    <th class="text-center align-middle sticky-col-1 zi-50 col-number">№</th>
                    <th class="sticky-col-2 zi-50 col-name">ФИО</th>

                    <th class="schedule-payment-status sticky-col-2"><i class="nav-icon fa-solid fa-ruble-sign"></i>
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
                        {{--                        <td class="text-center align-middle">{{ $index + 1 }}</td>--}}
                        {{--                        <td class="schedule-user-name">{{ $user->name }}</td>--}}

                        <td class="text-center align-middle sticky-col-1">{{ $index + 1 }}</td>
                        <td class="schedule-user-name sticky-col-2">{{ $user->name }}</td>

                        <td class="text-center">
                            @if(isset($userPrices[$user->id]) && $userPrices[$user->id]->is_paid == 1)
                                <i class="fas fa-circle-check text-success"></i>
                            @endif
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
                            <td class="schedule-cell text-center @if(isset($teamWeekdays) && count($teamWeekdays) && in_array($day->format('N'), $teamWeekdays)) highlight-column @endif"
                                data-user-id="{{ $user->id }}"
                                data-date="{{ $day->format('Y-m-d') }}"
                                data-comment="{{ $entry ? $entry->description : '' }}"
                                style="width: 5px; height: 5px; padding: 0; margin: 0; background-color: {{ $bgColor }}; position: relative; cursor: pointer;">
                                <div class="cell-content d-flex justify-content-center align-items-center cell-size">
                                    {!! $cellStatus ?: $cellIcon !!}
                                </div>
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
                            <label class="form-label">Статус</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="statusR" value="R">
                                <label class="form-check-label" for="statusR">
                                    <i class="fas fa-check"></i> Рабочий день
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="statusN" value="N">
                                <label class="form-check-label" for="statusN">
                                    <span style="color: #fff; background-color: red; padding: 2px 4px;">Н</span> Не был
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="statusZ" value="Z">
                                <label class="form-check-label" for="statusZ">
                                    <i class="fas fa-snowflake"></i> Заморозка
                                </label>
                            </div>
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

    <!-- Модальное окно для легенды -->
    <div class="modal fade" id="legendModal" tabindex="-1" aria-labelledby="legendModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content schedule-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="legendModalLabel">Легенда</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body" style="text-align: left;">
                    <ul class="list-unstyled">
                        <li>
                <span style="display:inline-block; background: #fff; padding:2px;">
                    <i class="fas fa-check text-success"></i>
                </span>
                            &nbsp;Оплаченный период.
                        </li>
                        <li>
                <span style="display:inline-block; background: rgba(255,255,0,0.3); padding:2px;">
                    <i class="fas fa-check" style="color: black;"></i>
                </span>
                            &nbsp;Учебный день согласно расписанию.
                        </li>
                        <li>
                <span style="display:inline-block; background: rgba(0,191,255,0.3); padding:2px;">
                    <i class="fas fa-snowflake"></i>
                </span>
                            &nbsp;Заморозка.
                        </li>
                        <li>
                <span style="display:inline-block; background: rgba(255,0,0,0.3); padding:2px;">
                    Н
                </span>
                            &nbsp;Не был.
                        </li>
                        <li>
                            <span style="display:inline-block; width:0; height:0; border-top:5px solid red; border-left:5px solid transparent;"></span>
                            &nbsp;Комментарий.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
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

    <style>
        /* Убираем скролл страницы при полноэкранном режиме */
        body.no-scroll {
            overflow: hidden !important;
        }

        /* Более конкретные селекторы для избежания конфликтов */
        .schedule-table-container .schedule-table th,
        .schedule-table-container .schedule-table td {
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .schedule-table-container .schedule-table tr:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }

        .schedule-user-name {
            white-space: nowrap;
            /*max-width: 200px;*/
            overflow: hidden;
            text-overflow: ellipsis;
        }


        #schedule-table {
            border-collapse: collapse;
        }

        #schedule-table table.dataTable tbody .schedule-user-name {
            /*padding: 0!important;*/
            border-collapse: collapse;

        }

        table.dataTable tbody th, table.dataTable tbody td {
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            padding-left: 2px !important;
            padding-right: 2px !important;
        }


        .schedule-day-header {
            padding-top: 5px !important;
            padding-bottom: 5px !important;
            padding-left: 10px !important;
            padding-right: 10px !important;

        }

        .table-number {
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            padding-left: 2px !important;
            padding-right: 2px !important;
        }

        .schedule-payment-status {
            padding-top: 10px !important;
            padding-bottom: 10px !important;
            padding-left: 4px !important;
            padding-right: 4px !important;
            text-align: center !important;
            width: 30px;
            position: unset;
        }

        .schedule-payment-status i {
            width: 30px;
        }


        .schedule-user-name {
            padding-top: 10px !important;
            padding-bottom: 10px !important;
            padding-left: 4px !important;
            padding-right: 4px !important;
        }


        .schedule-fullscreen-wrapper .wrap-filter-year {
            padding-left: 2px;
            padding-right: 2px;
            padding-top: 0;
            padding-bottom: 0;
        }

        .schedule-fullscreen-wrapper .wrap-filter-month {
            padding-left: 2px;
            padding-right: 2px;
            padding-top: 0;
            padding-bottom: 0;
        }

        .schedule-fullscreen-wrapper .wrap-filter-team {
            padding-left: 2px;
            padding-right: 2px;
            padding-top: 0;
            padding-bottom: 0;
        }

        .schedule-fullscreen-wrapper .wrap-filter-legend {
            padding-left: 2px;
            padding-right: 2px;
            padding-top: 0;
            padding-bottom: 0;
        }

        .schedule-fullscreen-wrapper .wrap-filter-fullscreen {
            padding-left: 2px;
            padding-right: 2px;
            padding-top: 0;
            padding-bottom: 0;
        }

        .schedule-fullscreen-wrapper .wrap-filter-search {
            padding-left: 2px;
            padding-right: 2px;
            padding-top: 0;
            padding-bottom: 0;
            text-align: left;
        }


        .schedule-day-header {
            min-width: 5px;
            min-height: 5px;
            padding: 0;
            font-size: 8px;
        }

        .schedule-cell {
            min-width: 5px;
            min-height: 5px;
            padding: 0;
            font-size: 8px;
        }

        /* Подсветка столбцов расписания выбранной группы */
        .highlight-column {
            /*border: 2px solid rgba(197, 132, 38, 0.72) !important;*/
            border: solid 1px #f3a12b !important;

        }

        /* Полноэкранный режим: обёртка для фильтров и таблицы */
        .schedule-fullscreen-wrapper.fullscreen {
            position: fixed !important;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #fff;
            z-index: 1050;
            overflow: hidden; /* убираем скролл всей страницы */
            padding-top: 50px; /* Отступ для фиксированных фильтров */
        }

        .schedule-fullscreen-wrapper.fullscreen .schedule-controls {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1060;
            background: #fff;
            padding: 10px;
            margin: 0; /* Устраняем возможные "рывки" */
        }

        /* Скролл только для таблицы */
        .schedule-fullscreen-wrapper.fullscreen .schedule-table-container {
            overflow: auto;
            height: calc(100% - 50px);
        }

        /* Закрепление заголовков таблицы – top: 0, чтобы примыкал к верхней границе контейнера */
        /*.schedule-fullscreen-wrapper.fullscreen .schedule-table thead th {*/
        /*    position: sticky;*/
        /*    top: 0;*/
        /*    background-color: #fff;*/
        /*    z-index: 2;*/
        /*}*/
        /* Замените на такой вариант */
        .schedule-fullscreen-wrapper.fullscreen .schedule-table thead th {
            position: sticky;
            top: 0;
            background-color: #fff;
            z-index: 500; /* увеличенный z-index, чтобы не было «прокрутки» за заголовком */
        }


        /* Выравнивание радиобатонов по левому краю */
        .schedule-modal-content .form-check {
            text-align: left;
        }

        .schedule-controls .table-search {
            width: 200px;
        }

        @media only screen and (max-width: 768px) {
            .schedule-fullscreen-wrapper .schedule-filter-team {
                font-size: 12px;
                width: 100px;
                padding-left: 2px;
                padding-right: 2px;
            }

            .schedule-fullscreen-wrapper .schedule-filter-year {
                font-size: 12px;
                width: 65px;
                padding-left: 2px;
                padding-right: 2px;
            }

            .schedule-fullscreen-wrapper .schedule-filter-month {
                font-size: 12px;
                width: 90px;
                padding-left: 2px;
                padding-right: 2px;
            }

            .schedule-fullscreen-wrapper .wrap-filter-search input {
                font-size: 12px;
                /*width: 85px;*/
                padding-left: 2px;
                padding-right: 0px;
                height: 33px;
                width: 100%;;
            }

            .schedule-fullscreen-wrapper .wrap-filter-fullscreen button {
                font-size: 12px;
                width: 30px;
                padding-left: 2px;
                padding-right: 2px;
            }

            #btn-legend {
                display: none;
            }


            .schedule-fullscreen-wrapper .wrap-filter-year {
                padding-left: 0px;
            }

            /*wrap-filter-year*/
            /*wrap-filter-month*/
            /*wrap-filter-team*/
            /*wrap-filter-legend*/
            /*wrap-filter-fullscreen*/
            /*wrap-filter-search*/

        }

        /* Закрепление первых двух столбцов */
        .schedule-table .sticky-col-1 {
            position: sticky;
            left: 0;
            background-color: #fff;
            z-index: 5; /* значение z-index может быть скорректировано при необходимости */
        }

        /*.schedule-table .sticky-col-2 {*/
        /*    position: sticky;*/
        /*    !*left: 50px; !* подберите отступ, чтобы не перекрывало первый столбец *!*!*/
        /*    background-color: #fff;*/
        /*    z-index: 5;*/
        /*}*/

        /* Первый столбец (#) */
        .schedule-table .sticky-col-1 {
            position: sticky;
            left: 0;
            background-color: #fff;
            /*z-index: 501; !* чтобы точно было выше «прокручиваемой» части *!*/
        }

        /* Второй столбец (ФИО).
           left подберите так, чтобы второй столбец не налезал на первый.
           Если ширина первого столбца около 40-50px, то left: 40px или 50px.
        */
        .schedule-table .sticky-col-2 {
            position: sticky;
            /*left: 50px;*/
            background-color: #fff;
            /*z-index: 501;*/
        }


        /* Исправляем левый бордер при подсветке столбца */
        .schedule-table-container .schedule-table td.highlight-column,
        .schedule-table-container .schedule-table th.highlight-column {
            /*border: 1px solid RED !important;*/
            border: solid 1px #f3a12b !important;

            box-sizing: border-box;
        }


        /* ------------------------------------- */
        /* === Глобальная настройка таблицы ===  */
        /* ------------------------------------- */

        /* Чтобы между ячейками не было "щелей", через которые видно прокрутку */
        .schedule-table {
            border-collapse: separate !important;
            border-spacing: 0 !important;
        }

        /* Общие границы */
        .schedule-table th,
        .schedule-table td {
            border: 1px solid rgba(0, 0, 0, 0.2);
            background-color: #fff; /* Белый фон, чтобы за ней не было видно движения */
            box-sizing: border-box;
        }

        /* ----------------------------------------------- */
        /* === Закрепление первой строки (thead) ===       */
        /* ----------------------------------------------- */
        /* Только в полноэкранном режиме */
        .schedule-fullscreen-wrapper.fullscreen .schedule-table thead th {
            position: sticky;
            top: 0;
            z-index: 40; /* достаточно большое, чтобы быть выше рядов и прокрутки */
        }

        /* А в обычном режиме заголовок "не липкий" */
        /*.schedule-fullscreen-wrapper:not(.fullscreen) .schedule-table thead th {*/
        /*    position: static;*/
        /*    z-index: auto;*/
        /*}*/

        /* ----------------------------------------------- */
        /* === Закрепление первых 2-х столбцов ===         */
        /* ----------------------------------------------- */
        /* В ОБОИХ режимах (обычном и fullscreen) */
        .schedule-table .sticky-col-1 {
            position: sticky;
            left: 0;
            z-index: 41; /* чуть выше ячеек */
            background-color: #fff;
        }

        .schedule-table .sticky-col-2 {
            position: sticky;
            /*left: 60px; !* подберите под вашу ширину первого столбца *!*/
            z-index: 41;
            background-color: #fff;
        }

        /* ----------------------------------------------- */
        /* === "Подсвеченные" колонки расписания ===       */
        /* ----------------------------------------------- */
        .schedule-table td.highlight-column,
        .schedule-table th.highlight-column {
            border: 1px solid #f3a12b !important; /* или ваш цвет, можно #007bff */
            box-sizing: border-box;
        }

        /* ----------------------------------------------- */
        /* === Прокрутка контейнера + fullscreen ===       */
        /* ----------------------------------------------- */

        /* В полноэкранном режиме делаем контейнер
           с таблицей прокручиваемым */
        .schedule-fullscreen-wrapper.fullscreen .schedule-table-container {
            position: relative;
            overflow: auto;
            height: calc(100% - 50px);
        }

        /* А само тело страницы в этот момент
           не прокручивается (видели в вашем коде).
           Это можно оставить, если уже работает. */

        /* Если нужно (иногда помогает скрыть "ползунок"
           за закреплёнными столбцами), можно
           задать стили для полосы прокрутки:
        .schedule-table-container::-webkit-scrollbar {
            background: transparent;
        }
        ...
        */

        /* ------------------------------------ */
        /* === Дополнительная мелкая подстройка */
        /* ------------------------------------ */

        /* Уменьшим "пустоты", чтобы
           нигде не просвечивало: */
        .schedule-table td,
        .schedule-table th {
            padding: 2px !important; /* или как вам удобно */
            margin: 0;
        }


        .zi-50 {
            z-index: 50 !important;
        }


        .cell-size {
            width: 20px!important;
            /*width: 100%;*/
            height: 100%;
            font-size: 10px;
        }

        .col-number {
            width: 20px !important;
        }

        .col-name {
            max-width: 140px;
            left: 23px;
            position: sticky!important;
            z-index: 50!important;
        }
        .schedule-user-name {
            max-width: 140px;
            left: 23px;
            font-size: 13px;
        }
        .schedule-day-header div {
            font-size: 10px;
        }


    </style>

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
            var numDays = {{ count($days) }};
            var dtColumns = [
                {orderable: false}, // Номер строки
                {orderable: true}, // ФИО
                {orderable: true}  // Статус оплаты
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
                var userId = $(this).attr('data-user-id');
                var date = $(this).attr('data-date');
                // Используем attr для получения актуального комментария
                var comment = $(this).attr('data-comment') || '';
                $('#edit-user-id').val(userId);
                $('#edit-date').val(date);
                $('#description').val(comment);

                // Предустановка выбранного радиобатона, если значение уже установлено
                var cellContent = $(this).find('.cell-content').html().trim();
                if (cellContent.indexOf('fa-check') !== -1) {
                    $('#statusR').prop('checked', true);
                } else if (cellContent.indexOf('Н') !== -1) {
                    $('#statusN').prop('checked', true);
                } else if (cellContent.indexOf('fa-snowflake') !== -1) {
                    $('#statusZ').prop('checked', true);
                } else {
                    $('input[name="status"]').prop('checked', false);
                }
                cellEditModal.show();
            });

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
                            var status = $('input[name="status"]:checked').val();
                            var content = '';
                            var bgColor = '';
                            if (status === 'R') {
                                content = '<i class="fas fa-check"></i>';
                                bgColor = 'rgba(255,255,0,0.3)';
                            } else if (status === 'N') {
                                content = 'Н';
                                bgColor = 'rgba(255,0,0,0.3)';
                            } else if (status === 'Z') {
                                content = '<i class="fas fa-snowflake"></i>';
                                bgColor = 'rgba(0,191,255,0.3)';
                            }
                            currentCell.find('.cell-content').html(content);
                            currentCell.css('background-color', bgColor);

                            // Обработка комментария: обновляем атрибут data-comment
                            var comment = $('#description').val().trim();
                            currentCell.attr('data-comment', comment);
                            if (comment !== '') {
                                if (currentCell.find('.cell-comment-indicator').length == 0) {
                                    currentCell.append('<div class="cell-comment-indicator" style="position: absolute; top: 0; right: 0; width: 0; height: 0; border-top: 5px solid red; border-left: 5px solid transparent;"></div>');
                                }
                            } else {
                                currentCell.find('.cell-comment-indicator').remove();
                            }
                            cellEditModal.hide();
                        }
                    }
                });
            });

            // Открытие модального окна легенды
            $('#btn-legend').on('click', function () {
                var legendModal = new bootstrap.Modal(document.getElementById('legendModal'), {});
                legendModal.show();
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
    </script>
@endsection


