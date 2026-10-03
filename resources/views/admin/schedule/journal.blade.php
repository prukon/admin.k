    <!-- Обёртка для фильтров и таблицы (для полноэкранного режима) -->
    <div class="schedule-fullscreen-wrapper mt-3">
        <div class="schedule-controls">
            <div class="schedule-controls__filters">
            <div class="wrap-filter-year">
                <select id="filter-year" class="form-select schedule-filter-year">
                    @for($y = date('Y') - 5; $y <= date('Y') + 5; $y++)
                        <option value="{{ $y }}" @if($year == $y) selected @endif>{{ $y }}</option>
                    @endfor
                </select>
                @error('year')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
            <div class="wrap-filter-month">
                <select id="filter-month" class="form-select schedule-filter-month">
                    @php
                        $months = [
                            '01' => 'Январь', '02' => 'Февраль', '03' => 'Март', '04' => 'Апрель',
                            '05' => 'Май', '06' => 'Июнь', '07' => 'Июль', '08' => 'Август',
                            '09' => 'Сентябрь', '10' => 'Октябрь', '11' => 'Ноябрь', '12' => 'Декабрь',
                        ];
                    @endphp
                    @foreach($months as $mKey => $mName)
                        <option value="{{ $mKey }}" @if($month == $mKey) selected @endif>{{ $mName }}</option>
                    @endforeach
                </select>
                @error('month')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
            @php
                $selectedTeamTokens = $selectedTeamTokens ?? [];
                $journalTeamFilter = $journalTeamFilter ?? \App\Support\Schedule\ScheduleJournalTeamFilter::fromMixed($team_id ?? 'all');
            @endphp
            <div class="wrap-filter-team generic-multiselect-field">
                <select id="filter-team"
                        class="form-select schedule-filter-team js-generic-multiselect-select"
                        name="team_ids[]"
                        multiple
                        data-placeholder="Все группы">
                    <option value="none" @selected(in_array('none', $selectedTeamTokens, true))>Без группы</option>
                    @foreach($teams as $team)
                        <option value="{{ $team->id }}"
                                @selected(in_array((string) $team->id, $selectedTeamTokens, true))>{{ $team->title }}</option>
                    @endforeach
                </select>
                @php
                    $teamFilterErrorMessages = collect($errors->get('team', []))
                        ->merge($errors->get('team_ids', []))
                        ->merge(collect($errors->getMessages())
                            ->filter(static fn ($messages, $key) => str_starts_with((string) $key, 'team_ids.'))
                            ->flatten())
                        ->unique()
                        ->values();
                @endphp
                @foreach($teamFilterErrorMessages as $teamFilterError)
                    <div class="text-danger small mt-1">{{ $teamFilterError }}</div>
                @endforeach
            </div>
            </div>

            @php
                $journalAttendance = $journalAttendance ?? [
                    'by_date' => [],
                    'average_label' => '—',
                ];
            @endphp
            <div class="schedule-attendance-average" id="schedule-attendance-average">
                Средняя посещаемость: <span id="schedule-attendance-average-value">{{ $journalAttendance['average_label'] ?? '—' }}</span>
            </div>

            <div class="schedule-controls__actions">
            <div class="wrap-filter-search">
                <form method="get" action="{{ route('schedule.index') }}" class="schedule-controls__search">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    @foreach($selectedTeamTokens as $teamToken)
                        <input type="hidden" name="team_ids[]" value="{{ $teamToken }}">
                    @endforeach
                    @if(request('fullscreen') == '1')
                        <input type="hidden" name="fullscreen" value="1">
                    @endif
                    <input type="text"
                           id="table-search"
                           name="q"
                           value="{{ $searchQ ?? '' }}"
                           class="form-control table-search"
                           placeholder="Поиск"
                           autocomplete="off">
                    <button type="submit" class="btn btn-outline-secondary">Найти</button>
                </form>
                @error('q')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
            <div id="schedule-bulk-bar" class="schedule-bulk-bar d-none">
                <span id="schedule-bulk-count">Выбрано: 0</span>
                <button type="button" class="btn btn-primary btn-sm" id="schedule-bulk-add">Добавить занятие</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="schedule-bulk-clear">Снять выделение</button>
            </div>
            <div class="wrap-filter-fullscreen">
                <button id="btn-fullscreen" class="btn btn-primary schedule-btn-fullscreen" type="button" aria-label="На весь экран">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
            <div class="wrap-icon btn btn-history-modal" data-bs-toggle="modal" data-bs-target="#historyModal" aria-label="История изменений">
                <i class="fa-solid fa-clock-rotate-left logs "></i>
            </div>
            </div>
        </div>

        <div id="schedule-journal-stage" aria-busy="true">
            <div class="schedule-journal-preloader" aria-hidden="true">
                <div class="spinner-border text-secondary" role="status" aria-label="Загрузка"></div>
            </div>
            <div class="schedule-journal-table-stack">
            <div class="table-responsive schedule-table-container">
            <table id="schedule-table" class="table table-bordered schedule-table"
                   data-group-rows-url="{{ route('schedule.group-rows') }}"
                   data-group-bulk-url="{{ route('schedule.group-bulk-candidates') }}"
                   data-group-per-page="{{ \App\Services\Schedule\ScheduleJournalGroupBoardService::PER_PAGE }}">
                <thead>
                <tr>
                    <th class="text-center align-middle sticky-col-1 zi-50 col-number">№</th>
                    <th class="sticky-col-2 zi-50 col-name">ФИО</th>
                    <th class="schedule-payment-status sticky-col-2">
                        @include('partials.ui.tooltip-hint', [
                            'title' => 'Статус оплаты',
                            'placement' => 'top',
                            'innerHtml' => '<i class="nav-icon fa-solid fa-ruble-sign"></i>',
                            'wrapperClass' => 'journal-col-header-hint',
                            'container' => 'body',
                        ])
                    </th>
                    <th class="schedule-consuming-count sticky-col-2 text-center">
                        @include('partials.ui.tooltip-hint', [
                            'title' => 'Кол-во посещений',
                            'placement' => 'top',
                            'innerHtml' => '<i class="nav-icon fa-solid fa-person-circle-check"></i>',
                            'wrapperClass' => 'journal-col-header-hint',
                            'container' => 'body',
                        ])
                    </th>
                    <th class="schedule-col-setup sticky-col-3 text-center">
                        @include('partials.ui.tooltip-hint', [
                            'title' => 'Название абонемента',
                            'placement' => 'top',
                            'innerHtml' => '<i class="fa-solid fa-ticket"></i>',
                            'wrapperClass' => 'journal-col-header-hint',
                            'container' => 'body',
                        ])
                    </th>

                    @php
                        $days = [];
                        $start = $startOfMonth->copy();
                        $end = $endOfMonth->copy();
                        while ($start->lte($end)) {
                            $days[] = $start->copy();
                            $start->addDay();
                        }
                    @endphp
                        @foreach($days as $day)
                            <th class="schedule-day-header @if(isset($teamWeekdays) && count($teamWeekdays) && in_array($day->format('N'), $teamWeekdays)) highlight-column @endif"
                            data-date="{{ $day->format('Y-m-d') }}">
                            <div class="d-flex flex-column justify-content-center align-items-center">
                                <span>{{ $day->format('d') }}</span>
                                <span>{{ mb_substr($day->locale('ru_RU')->isoFormat('ddd'), 0, 2) }}</span>
                            </div>
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @forelse($journalGroups as $group)
                    <tr class="schedule-group-row" data-group-key="{{ $group['key'] }}" @if($group['team_id']) data-team-id="{{ $group['team_id'] }}" @endif>
                        <td class="sticky-col-1"></td>
                        <td class="schedule-user-name sticky-col-2 schedule-group-head-cell">
                            <div class="schedule-group-head">
                                <button type="button" class="schedule-group-toggle" aria-expanded="false" aria-label="Развернуть">
                                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                </button>
                                <span class="schedule-group-title" title="{{ $group['title'] }}">{{ $group['title'] }}</span>
                                <span class="schedule-group-count">{{ $group['users']->total() }}</span>
                            </div>
                        </td>
                        <td class="schedule-payment-status"></td>
                        <td class="schedule-consuming-count"></td>
                        <td class="schedule-col-setup"></td>
                        @foreach($days as $day)
                            <td class="schedule-group-day text-center @if(count($group['weekdays']) && in_array($day->format('N'), $group['weekdays'])) highlight-column @endif"
                                data-date="{{ $day->format('Y-m-d') }}"><span class="schedule-group-day-check" aria-hidden="true"></span></td>
                        @endforeach
                    </tr>
                    @include('admin.schedule._journal_group_users', ['journalGroupCollapsed' => true])
                @empty
                    <tr class="schedule-journal-empty">
                        <td class="sticky-col-1"></td>
                        <td class="schedule-user-name sticky-col-2 text-muted">Нет учеников</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        @foreach($days as $day)
                            <td></td>
                        @endforeach
                    </tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr class="schedule-attendance-total">
                    <td class="sticky-col-1"></td>
                    <td class="sticky-col-2 schedule-attendance-total-label">Итого</td>
                    <td class="schedule-payment-status"></td>
                    <td class="schedule-consuming-count"></td>
                    <td class="schedule-col-setup"></td>
                    @foreach($days as $day)
                        @php
                            $attendanceDate = $day->format('Y-m-d');
                            $attendanceCount = (int) ($journalAttendance['by_date'][$attendanceDate] ?? 0);
                        @endphp
                        <td class="text-center schedule-attendance-day @if(isset($teamWeekdays) && count($teamWeekdays) && in_array($day->format('N'), $teamWeekdays)) highlight-column @endif"
                            data-date="{{ $attendanceDate }}">@if($attendanceCount > 0){{ $attendanceCount }}@endif</td>
                    @endforeach
                </tr>
                </tfoot>
            </table>
            </div>
            </div>
        </div>
    </div>

    {{-- Список занятий за день (×N) --}}
    <div class="modal fade" id="dayOccurrencesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dayOccurrencesModalLabel">Занятия за день</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body" id="dayOccurrencesModalBody"></div>
            </div>
        </div>
    </div>

    {{-- Массовая постановка занятия в выбранные пустые ячейки --}}
    <div class="modal fade" id="bulkPlaceModal" tabindex="-1" aria-labelledby="bulkPlaceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content schedule-modal-content cell-edit-modal">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkPlaceModalLabel">Добавить занятие</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="cell-edit-context">
                        <div class="cell-edit-context__teams" id="bulk-place-group"></div>
                        <div class="cell-edit-context__date" id="bulk-place-date"></div>
                        <div class="cell-edit-context__meta" id="bulk-place-count"></div>
                    </div>
                    <div id="bulk-students" class="bulk-students"></div>
                    <form id="bulkPlaceForm" novalidate>
                        <div class="cell-edit-section">
                            <div class="cell-edit-section__label">Статус</div>
                            <div class="invalid-feedback d-block" id="bulk-status-error" style="display:none;"></div>
                            <div class="cell-status-options">
                                @foreach($availableStatuses as $st)
                                    <div class="cell-status-option form-check">
                                        <label class="cell-status-option__main" for="bulk-status-{{ $st->id }}">
                                            <input class="form-check-input cell-status-option__input"
                                                   type="radio"
                                                   name="bulk_lesson_occurrence_status_id"
                                                   id="bulk-status-{{ $st->id }}"
                                                   value="{{ $st->id }}"
                                                   @if($st->code === \App\Models\LessonOccurrenceStatus::CODE_SCHEDULED) data-is-scheduled="1" @endif
                                                   @if(!empty($visitedStatusId) && (int) $st->id === (int) $visitedStatusId) checked data-is-visited="1" @endif>
                                            <span class="schedule-status-option-chip" style="background-color: {{ $st->color }};">
                                                <i class="{{ $st->icon }}" aria-hidden="true"></i>
                                            </span>
                                            <span class="cell-status-option__title">{{ $st->title }}</span>
                                        </label>
                                        @if(!empty($st->consumes_lesson))
                                            <i class="fa-solid fa-circle-info text-muted bulk-postpay-hint d-none"
                                               tabindex="0"
                                               role="img"
                                               aria-label="Идёт в расчёт постоплаты. Влияет на сумму за месяц."
                                               data-kids-tooltip-hint="1"
                                               data-bs-toggle="tooltip"
                                               data-bs-placement="top"
                                               data-bs-custom-class="ulp-assignment-paid-tooltip"
                                               data-bs-container="body"
                                               title="Идёт в расчёт постоплаты. Влияет на сумму за месяц."></i>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="cell-edit-section d-none generic-multiselect-field" id="bulk-trainer-wrap">
                            <label for="bulk-trainer-profile-ids" class="cell-edit-section__label">Тренеры</label>
                            <select id="bulk-trainer-profile-ids"
                                    name="trainer_profile_ids[]"
                                    class="form-select js-generic-multiselect-select"
                                    multiple
                                    data-placeholder="Без тренера">
                            </select>
                            <div class="form-text text-muted" id="bulk-trainer-hint"></div>
                            <div class="invalid-feedback d-block" id="bulk-trainer-error" style="display:none;"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer cell-edit-modal__footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" form="bulkPlaceForm" class="btn btn-primary" id="bulk-place-submit">Сохранить</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Редактирование статуса одного занятия --}}
    <div class="modal fade" id="cellEditModal" tabindex="-1" aria-labelledby="cellEditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content schedule-modal-content cell-edit-modal">
                <div class="modal-header">
                    <h5 class="modal-title" id="cellEditModalLabel">Статус занятия</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="cell-edit-context">
                        <div class="cell-edit-context__name" id="edit-user-name-display"></div>
                        <div class="cell-edit-context__teams" id="edit-user-teams-display"></div>
                        <div class="cell-edit-context__date" id="edit-date-display"></div>
                        <div class="cell-edit-context__meta" id="edit-occurrence-meta"></div>
                    </div>

                    <div class="cell-edit-section d-none" id="edit-add-flexible-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btn-add-flexible-lesson">
                            <i class="fa-solid fa-plus me-1"></i>Добавить занятие из абонемента предоплаты
                        </button>
                    </div>

                    <div class="cell-edit-section d-none" id="edit-postpay-team-wrap">
                        <label class="cell-edit-section__label" for="edit-postpay-team-select">Группа для отметки</label>
                        <select class="form-select" id="edit-postpay-team-select" aria-label="Группа для отметки постоплаты"></select>
                        <div class="form-control-plaintext d-none" id="edit-postpay-team-readonly"></div>
                        <div class="invalid-feedback d-block" id="edit-postpay-team-error" style="display:none;"></div>
                    </div>

                    <form id="cellEditForm">
                        <input type="hidden" name="user_id" id="edit-user-id">
                        <input type="hidden" name="utss_id" id="edit-utss-id">
                        <input type="hidden" name="occurrence_date" id="edit-date">
                        <input type="hidden" name="create_postpay" id="edit-create-postpay" value="0">
                        <input type="hidden" name="team_id" id="edit-team-id" value="">

                        <div class="cell-edit-section">
                            <div class="cell-edit-section__label">Статус</div>
                            <div class="invalid-feedback d-block" id="cell-status-error" style="display:none;"></div>
                            <div class="cell-status-options">
                                @foreach($availableStatuses as $st)
                                    <div class="cell-status-option form-check">
                                        <label class="cell-status-option__main" for="status-{{ $st->id }}">
                                            <input class="form-check-input cell-status-option__input"
                                                   type="radio"
                                                   name="lesson_occurrence_status_id"
                                                   id="status-{{ $st->id }}"
                                                   value="{{ $st->id }}"
                                                   data-icon="{{ $st->icon }}"
                                                   data-color="{{ $st->color }}"
                                                   data-consumes-lesson="{{ !empty($st->consumes_lesson) ? '1' : '0' }}"
                                                   @if(!empty($visitedStatusId) && (int) $st->id === (int) $visitedStatusId) data-is-visited="1" @endif>
                                            <span class="schedule-status-option-chip" style="background-color: {{ $st->color }};">
                                                <i class="{{ $st->icon }}" aria-hidden="true"></i>
                                            </span>
                                            <span class="cell-status-option__title">{{ $st->title }}</span>
                                        </label>
                                        @if(!empty($st->consumes_lesson))
                                            <i class="fa-solid fa-circle-info text-muted cell-status-postpay-billing-hint d-none"
                                               tabindex="0"
                                               role="img"
                                               aria-label="Идёт в расчёт постоплаты. Влияет на сумму за месяц."
                                               data-kids-tooltip-hint="1"
                                               data-bs-toggle="tooltip"
                                               data-bs-placement="top"
                                               data-bs-custom-class="ulp-assignment-paid-tooltip"
                                               data-bs-container="body"
                                               title="Идёт в расчёт постоплаты. Влияет на сумму за месяц."></i>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="cell-edit-section d-none generic-multiselect-field" id="cell-trainer-wrap">
                            <label for="cell-trainer-profile-ids" class="cell-edit-section__label">Тренеры</label>
                            <select id="cell-trainer-profile-ids"
                                    name="trainer_profile_ids[]"
                                    class="form-select js-generic-multiselect-select"
                                    multiple
                                    data-placeholder="Без тренера">
                            </select>
                            <div class="form-text text-muted" id="cell-trainer-hint"></div>
                            <div class="invalid-feedback d-block" id="cell-trainer-error" style="display:none;"></div>
                        </div>

                        <div class="cell-edit-section cell-edit-section--last">
                            <label for="description" class="cell-edit-section__label">Комментарий</label>
                            <textarea class="form-control" id="description" name="comment" rows="2"></textarea>
                            <div class="invalid-feedback" id="cell-comment-error"></div>
                        </div>
                        <div class="invalid-feedback d-block" id="cell-delete-error" style="display:none;"></div>
                    </form>
                </div>
                <div class="modal-footer cell-edit-modal__footer">
                    <span id="btn-cell-delete-wrap" class="d-none me-auto">
                        <button type="button"
                                class="btn btn-outline-danger"
                                id="btn-cell-delete"
                                data-kids-tooltip-hint="1"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-custom-class="ulp-assignment-paid-tooltip"
                                data-bs-container="body"
                                title="">
                            Удалить
                        </button>
                    </span>
                    <button type="submit" form="cellEditForm" class="btn btn-primary">Сохранить</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Подтверждение удаления занятия --}}
    <div class="modal fade" id="cellDeleteConfirmModal" tabindex="-1" aria-labelledby="cellDeleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cellDeleteConfirmModalLabel">Удалить занятие?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="cell-delete-confirm">
                        <div class="cell-delete-confirm__icon" aria-hidden="true">
                            <span class="cell-delete-confirm__icon-circle">
                                <i class="fa-solid fa-trash-can"></i>
                            </span>
                        </div>
                        <div class="cell-delete-confirm__name" id="cell-delete-confirm-name"></div>
                        <div class="cell-delete-confirm__date" id="cell-delete-confirm-date"></div>
                        <div class="cell-delete-confirm__chips d-none" id="cell-delete-confirm-chips">
                            <span class="cell-delete-confirm__chip d-none" id="cell-delete-confirm-status"></span>
                            <span class="cell-delete-confirm__chip d-none" id="cell-delete-confirm-context"></span>
                        </div>
                        <div class="cell-delete-confirm__hint" id="cell-delete-confirm-hint"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-danger" id="btn-cell-delete-confirm">Удалить</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Раскладка фиксированного абонемента --}}
    <div class="modal fade" id="abonementPlaceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content schedule-modal-content cell-edit-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Разложить абонемент</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="cell-edit-context">
                        <div class="cell-edit-context__name" id="abonement-user-name"></div>
                        <div class="cell-edit-context__teams" id="abonement-team-display"></div>
                    </div>
                    <form id="abonementPlaceForm" novalidate>
                        <div class="cell-edit-section" id="abonement-team-wrap">
                            <label for="abonement-team-id" class="cell-edit-section__label">Выберите группу</label>
                            <select class="form-select" id="abonement-team-id" name="team_id" aria-label="Выберите группу"></select>
                            <div class="form-control-plaintext d-none" id="abonement-team-readonly"></div>
                            <div class="invalid-feedback" id="abonement-team-error"></div>
                        </div>
                        <div class="cell-edit-section">
                            <label for="abonement-ulp-id" class="cell-edit-section__label">Абонемент</label>
                            <select class="form-select" id="abonement-ulp-id" name="user_lesson_package_id"></select>
                            <div class="invalid-feedback" id="abonement-ulp-error"></div>
                        </div>
                        <div class="cell-edit-section">
                            <label for="abonement-start-date" class="cell-edit-section__label">Дата начала</label>
                            <input type="date" class="form-control" id="abonement-start-date" name="start_date">
                            <div class="invalid-feedback" id="abonement-start-date-error"></div>
                            <div class="d-flex flex-wrap gap-3 mt-1 d-none" id="abonement-start-date-quick" role="group" aria-label="Быстрый набор даты начала">
                                <button type="button"
                                        class="abonement-start-quick-link"
                                        id="abonement-start-quick-month-start"
                                        data-date=""></button>
                                <button type="button"
                                        class="abonement-start-quick-link d-none"
                                        id="abonement-start-quick-today"
                                        data-date=""></button>
                            </div>
                            <div class="form-text" id="abonement-start-date-hint" style="display:none;"></div>
                        </div>
                        <div class="cell-edit-section" id="abonement-ends-at-wrap" style="display:none;">
                            <label for="abonement-ends-at" class="cell-edit-section__label">Дата окончания</label>
                            <input type="date" class="form-control" id="abonement-ends-at" name="ends_at_display" readonly disabled>
                        </div>
                        <div class="cell-edit-section">
                            <label class="cell-edit-section__label d-block">Дни недели</label>
                            <div id="abonement-weekdays" class="d-flex flex-wrap gap-2"></div>
                            <div class="invalid-feedback d-block" id="abonement-weekdays-error"></div>
                            <div class="abonement-weekdays-legend" id="abonement-weekdays-legend">
                                <div class="abonement-weekdays-legend__title">Подсказка</div>
                                <div class="abonement-weekdays-legend__row">
                                    <span class="abonement-weekdays-legend__swatch abonement-weekdays-legend__swatch--border" aria-hidden="true"></span>
                                    <span class="abonement-weekdays-legend__text">день недели согласно расписанию</span>
                                </div>
                                <div class="abonement-weekdays-legend__row">
                                    <span class="abonement-weekdays-legend__swatch abonement-weekdays-legend__swatch--check" aria-hidden="true">
                                        <input type="checkbox" class="form-check-input" tabindex="-1" checked disabled>
                                    </span>
                                    <span class="abonement-weekdays-legend__text">на этот день недели вы установите расписание</span>
                                </div>
                            </div>
                        </div>
                        <div class="cell-edit-section" id="abonement-preview-wrap" style="display:none;">
                            <label class="cell-edit-section__label">Превью</label>
                            <div class="small text-muted abonement-preview-text" id="abonement-preview-text"></div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="btnAbonementPreview">Превью</button>
                            <button type="submit" class="btn btn-primary" id="btnAbonementPlace">Разложить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Занятие из абонемента предоплаты (установка цен) --}}
    <div class="modal fade" id="flexiblePlaceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content schedule-modal-content cell-edit-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Занятие из абонемента предоплаты</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="cell-edit-context">
                        <div class="cell-edit-context__name" id="flexible-user-name"></div>
                        <div class="cell-edit-context__teams" id="flexible-team-display"></div>
                        <div class="cell-edit-context__date" id="flexible-date-display"></div>
                        <div class="cell-edit-context__summary" id="flexible-package-summary"></div>
                    </div>

                    <form id="flexiblePlaceForm" novalidate>
                        <input type="hidden" id="flexible-user-id" name="user_id" value="">
                        <input type="hidden" id="flexible-ulp-id" name="user_lesson_package_id" value="">
                        <input type="hidden" id="flexible-occurrence-date" name="occurrence_date" value="">

                        <div class="cell-edit-section" id="flexible-team-wrap">
                            <label for="flexible-team-id" class="cell-edit-section__label">Выберите группу</label>
                            <select class="form-select" id="flexible-team-id" name="team_id" aria-label="Выберите группу"></select>
                            <div class="form-control-plaintext d-none" id="flexible-team-readonly"></div>
                            <div class="invalid-feedback" id="flexible-team-error"></div>
                        </div>
                        <div class="invalid-feedback d-block mb-2" id="flexible-ulp-error" style="display:none;"></div>
                        <div class="invalid-feedback d-block mb-2" id="flexible-date-error" style="display:none;"></div>

                        <div class="cell-edit-section">
                            <div class="cell-edit-section__label">Статус</div>
                            <div class="invalid-feedback d-block" id="flexible-status-error" style="display:none;"></div>
                            <div class="cell-status-options">
                                @foreach($availableStatuses as $st)
                                    <div class="cell-status-option form-check">
                                        <label class="cell-status-option__main" for="flexible-status-{{ $st->id }}">
                                            <input class="form-check-input cell-status-option__input"
                                                   type="radio"
                                                   name="flexible_lesson_occurrence_status_id"
                                                   id="flexible-status-{{ $st->id }}"
                                                   value="{{ $st->id }}"
                                                   data-icon="{{ $st->icon }}"
                                                   data-color="{{ $st->color }}"
                                                   data-consumes-lesson="{{ !empty($st->consumes_lesson) ? '1' : '0' }}"
                                                   @if(!empty($scheduledStatusId) && (int) $st->id === (int) $scheduledStatusId) checked @endif
                                                   @if(!empty($visitedStatusId) && (int) $st->id === (int) $visitedStatusId) data-is-visited="1" @endif>
                                            <span class="schedule-status-option-chip" style="background-color: {{ $st->color }};">
                                                <i class="{{ $st->icon }}" aria-hidden="true"></i>
                                            </span>
                                            <span class="cell-status-option__title">{{ $st->title }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="cell-edit-section d-none generic-multiselect-field" id="flexible-trainer-wrap">
                            <label for="flexible-trainer-profile-ids" class="cell-edit-section__label">Тренеры</label>
                            <select id="flexible-trainer-profile-ids"
                                    name="trainer_profile_ids[]"
                                    class="form-select js-generic-multiselect-select"
                                    multiple
                                    data-placeholder="Без тренера">
                            </select>
                            <div class="form-text text-muted" id="flexible-trainer-hint"></div>
                            <div class="invalid-feedback d-block" id="flexible-trainer-error" style="display:none;"></div>
                        </div>

                        <div class="cell-edit-section cell-edit-section--last">
                            <label for="flexible-comment" class="cell-edit-section__label">Комментарий</label>
                            <textarea class="form-control" id="flexible-comment" name="comment" rows="2"></textarea>
                            <div class="invalid-feedback" id="flexible-comment-error"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer cell-edit-modal__footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" form="flexiblePlaceForm" class="btn btn-primary" id="btnFlexiblePlace">Поставить занятие</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="emptyCellPlaceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content schedule-modal-content cell-edit-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Установить занятие</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="cell-edit-context">
                        <div class="cell-edit-context__name" id="empty-cell-user-name"></div>
                        <div class="cell-edit-context__teams" id="empty-cell-team-display"></div>
                        <div class="cell-edit-context__date" id="empty-cell-date-display"></div>
                    </div>

                    <form id="emptyCellPlaceForm" novalidate>
                        <input type="hidden" id="empty-cell-user-id" name="user_id" value="">
                        <input type="hidden" id="empty-cell-occurrence-date" name="occurrence_date" value="">
                        <input type="hidden" id="empty-cell-kind" name="kind" value="">
                        <input type="hidden" id="empty-cell-mode" name="mode" value="">
                        <input type="hidden" id="empty-cell-ulp-id" name="user_lesson_package_id" value="">
                        <input type="hidden" id="empty-cell-package-id" name="lesson_package_id" value="">

                        <div class="cell-edit-section" id="empty-cell-choice-wrap">
                            <div class="cell-edit-section__label">Что установить</div>
                            <div class="invalid-feedback d-block" id="empty-cell-choice-error" style="display:none;"></div>
                            <div class="cell-status-options" id="empty-cell-choice-options"></div>
                        </div>

                        <div id="empty-cell-details-wrap" class="d-none">
                            <div class="cell-edit-section" id="empty-cell-team-wrap">
                                <label for="empty-cell-team-id" class="cell-edit-section__label">Группа</label>
                                <select class="form-select" id="empty-cell-team-id" name="team_id"></select>
                                <div class="form-control-plaintext d-none" id="empty-cell-team-readonly"></div>
                                <div class="invalid-feedback" id="empty-cell-team-error"></div>
                            </div>

                            <div class="cell-edit-section d-none" id="empty-cell-fee-wrap">
                                <label for="empty-cell-fee-amount" class="cell-edit-section__label">Стоимость, руб</label>
                                <div class="kids-user-discount-price-wrap">
                                    <input type="number" class="form-control" id="empty-cell-fee-amount" name="fee_amount" min="0" step="0.01">
                                </div>
                                <div class="invalid-feedback" id="empty-cell-fee-error"></div>
                            </div>

                            <div class="cell-edit-section">
                                <div class="cell-edit-section__label">Статус</div>
                                <div class="invalid-feedback d-block" id="empty-cell-status-error" style="display:none;"></div>
                                <div class="cell-status-options">
                                    @foreach($availableStatuses as $st)
                                        <div class="cell-status-option form-check">
                                            <label class="cell-status-option__main" for="empty-cell-status-{{ $st->id }}">
                                                <input class="form-check-input cell-status-option__input"
                                                       type="radio"
                                                       name="empty_cell_lesson_occurrence_status_id"
                                                       id="empty-cell-status-{{ $st->id }}"
                                                       value="{{ $st->id }}"
                                                       data-icon="{{ $st->icon }}"
                                                       data-color="{{ $st->color }}"
                                                       data-consumes-lesson="{{ !empty($st->consumes_lesson) ? '1' : '0' }}"
                                                       @if(!empty($scheduledStatusId) && (int) $st->id === (int) $scheduledStatusId) checked @endif
                                                       @if(!empty($visitedStatusId) && (int) $st->id === (int) $visitedStatusId) data-is-visited="1" @endif>
                                                <span class="schedule-status-option-chip" style="background-color: {{ $st->color }};">
                                                    <i class="{{ $st->icon }}" aria-hidden="true"></i>
                                                </span>
                                                <span class="cell-status-option__title">{{ $st->title }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="cell-edit-section d-none generic-multiselect-field" id="empty-cell-trainer-wrap">
                                <label for="empty-cell-trainer-profile-ids" class="cell-edit-section__label">Тренеры</label>
                                <select id="empty-cell-trainer-profile-ids"
                                        name="trainer_profile_ids[]"
                                        class="form-select js-generic-multiselect-select"
                                        multiple
                                        data-placeholder="Без тренера">
                                </select>
                                <div class="form-text text-muted" id="empty-cell-trainer-hint"></div>
                                <div class="invalid-feedback d-block" id="empty-cell-trainer-error" style="display:none;"></div>
                            </div>

                            <div class="cell-edit-section cell-edit-section--last">
                                <label for="empty-cell-comment" class="cell-edit-section__label">Комментарий</label>
                                <textarea class="form-control" id="empty-cell-comment" name="comment" rows="2"></textarea>
                                <div class="invalid-feedback" id="empty-cell-comment-error"></div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer cell-edit-modal__footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" form="emptyCellPlaceForm" class="btn btn-primary" id="btnEmptyCellPlace" disabled>Установить</button>
                </div>
            </div>
        </div>
    </div>

    @include('includes.logModal')
