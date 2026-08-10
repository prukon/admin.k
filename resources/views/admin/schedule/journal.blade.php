    <!-- Обёртка для фильтров и таблицы (для полноэкранного режима) -->
    <div class="schedule-fullscreen-wrapper mt-3">
        <div class="row mb-3 align-items-center schedule-controls">
            <div class="col-auto wrap-filter-year">
                <select id="filter-year" class="form-select schedule-filter-year">
                    @for($y = date('Y') - 5; $y <= date('Y') + 5; $y++)
                        <option value="{{ $y }}" @if($year == $y) selected @endif>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto wrap-filter-month">
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
            </div>
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

            <div class="col-auto wrap-filter-fullscreen">
                <button id="btn-fullscreen" class="btn btn-primary schedule-btn-fullscreen" type="button">
                    <i class="fas fa-expand"></i>
                </button>
            </div>

            <div class="col wrap-filter-search">
                <input type="text" id="table-search" class="form-control table-search" placeholder="Поиск">
            </div>
            <div class="wrap-icon btn btn-history-modal" data-bs-toggle="modal" data-bs-target="#historyModal">
                <i class="fa-solid fa-clock-rotate-left logs "></i>
            </div>
        </div>

        <div class="table-responsive schedule-table-container">
            <table id="schedule-table" class="table table-bordered schedule-table">
                <thead>
                <tr>
                    <th class="text-center align-middle sticky-col-1 zi-50 col-number">№</th>
                    <th class="sticky-col-2 zi-50 col-name">ФИО</th>
                    <th class="schedule-payment-status sticky-col-2">
                        <i class="nav-icon fa-solid fa-ruble-sign"></i>
                    </th>
                    <th class="schedule-col-setup sticky-col-3 text-center" title="Абонементы">
                        <i class="fa-solid fa-ticket"></i>
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
                    @php
                        $studentTeamIds = $user->teams->pluck('id')->all();
                        $journalContextTeamId = null;
                        if (is_numeric($team_id) && $team_id !== 'none') {
                            $journalContextTeamId = (int) $team_id;
                        } elseif ($studentTeamIds !== []) {
                            $journalContextTeamId = (int) $studentTeamIds[0];
                        }
                        $userAssignments = $journalAssignments[(int) $user->id] ?? [];
                        $settingPricesPlaceable = collect($userAssignments)->filter(
                            static fn ($a) => ! empty($a['placeable'])
                                && ! empty($a['from_setting_prices'])
                                && (int) ($a['team_id'] ?? 0) > 0
                        )->values();
                        $hasPlaceable = $settingPricesPlaceable->isNotEmpty();
                        $placeableHoverLines = [];
                        foreach ($settingPricesPlaceable as $assignment) {
                            $placeableHoverLines[] = \App\Services\Schedule\ScheduleJournalMonthService::fixedAbonementPlaceButtonHoverLine(
                                (string) ($assignment['name'] ?? 'Абонемент'),
                                (int) ($assignment['lessons_remaining'] ?? 0),
                                (int) ($assignment['lessons_total'] ?? 0),
                                (int) ($assignment['fee_amount_cents'] ?? 0),
                            );
                        }
                        $placeableHoverText = implode("\n", $placeableHoverLines);
                        $userFlexibleAssignments = $flexibleByUser[(int) $user->id] ?? [];
                        $hasFlexibleAssignable = !empty($flexibleUsers[(int) $user->id]) && $userFlexibleAssignments !== [];
                        $flexibleHintCount = count($userFlexibleAssignments);
                        // Фильтр группы сужает список до одного; без фильтра при нескольких — иконка.
                        $flexibleHintShowRatio = $flexibleHintCount === 1;
                        $flexibleHintText = '';
                        $flexibleHintRatio = '';
                        if ($hasFlexibleAssignable && $flexibleHintCount === 1) {
                            $fa = $userFlexibleAssignments[0];
                            $flexName = (string) ($fa['name'] ?? 'Гибкий абонемент');
                            $flexRem = (int) ($fa['slots_remaining'] ?? 0);
                            $flexTotal = (int) ($fa['lessons_total'] ?? 0);
                            $flexFee = (int) ($fa['fee_amount_cents'] ?? 0);
                            $flexibleHintRatio = \App\Services\Schedule\ScheduleJournalMonthService::flexibleAbonementColumnLabel(
                                $flexRem,
                                $flexTotal
                            );
                            $flexibleHintText = \App\Services\Schedule\ScheduleJournalMonthService::flexibleAbonementColumnHoverLine(
                                $flexName,
                                $flexFee
                            );
                        } elseif ($hasFlexibleAssignable && $flexibleHintCount > 1) {
                            $flexLines = [];
                            foreach ($userFlexibleAssignments as $fa) {
                                $flexLines[] = \App\Services\Schedule\ScheduleJournalMonthService::flexibleAbonementColumnHoverLine(
                                    (string) ($fa['name'] ?? 'Гибкий абонемент'),
                                    (int) ($fa['fee_amount_cents'] ?? 0),
                                    true,
                                    (int) ($fa['slots_remaining'] ?? 0),
                                    (int) ($fa['lessons_total'] ?? 0),
                                );
                            }
                            $flexibleHintText = implode("\n", $flexLines);
                        }
                        $userPostpayHints = $postpayByUser[(int) $user->id] ?? [];
                        $postpayHintLabels = [];
                        $postpayHintHovers = [];
                        foreach ($userPostpayHints as $ph) {
                            $postpayHintLabels[] = (string) ($ph['label'] ?? '');
                            $postpayHintHovers[] = (string) ($ph['hover'] ?? ($ph['label'] ?? ''));
                        }
                        $postpayHintLabels = array_values(array_filter($postpayHintLabels, static fn ($v) => $v !== ''));
                        $postpayHintText = implode("\n", $postpayHintLabels);
                        $postpayHintHover = implode("\n", array_values(array_filter($postpayHintHovers, static fn ($v) => $v !== '')));
                    @endphp
                    <tr data-user-id="{{ $user->id }}">
                        <td class="text-center align-middle sticky-col-1 number-line">{{ $index + 1 }}</td>
                        <td class="schedule-user-name sticky-col-2">
                            <div>{{ $user?->full_name ?: 'Без имени' }}</div>
                            @if($team_id === 'all' && $user->teams->isNotEmpty())
                                <small class="text-muted d-block">{{ $user->teams->pluck('title')->join(', ') }}</small>
                            @endif
                        </td>
                        <td class="text-center">
                            @if(isset($userPrices[$user->id]) && $userPrices[$user->id]->is_paid == 1)
                                <i class="fas fa-circle-check text-success"></i>
                            @endif
                        </td>
                        <td class="text-center align-middle schedule-col-setup schedule-col-abonements">
                            <div class="journal-abonement-cell d-inline-flex align-items-center gap-1 justify-content-center flex-wrap">
                                @if($hasPlaceable)
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary journal-abonement-btn kids-tooltip-hint"
                                            data-user-id="{{ $user->id }}"
                                            data-kids-tooltip-hint="1"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-custom-class="ulp-assignment-paid-tooltip"
                                            title="{{ $placeableHoverText }}"
                                            aria-label="{{ $placeableHoverText }}">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                @endif
                                @if($hasFlexibleAssignable && $flexibleHintText !== '')
                                    @if($flexibleHintShowRatio)
                                        @php $fa = $userFlexibleAssignments[0]; @endphp
                                        <span class="kids-tooltip-hint text-muted journal-flexible-hint journal-flexible-hint--ratio"
                                              tabindex="0"
                                              role="img"
                                              aria-label="{{ $flexibleHintText }}"
                                              data-kids-tooltip-hint="1"
                                              data-bs-toggle="tooltip"
                                              data-bs-placement="top"
                                              data-bs-custom-class="ulp-assignment-paid-tooltip"
                                              data-flexible-ulp-id="{{ (int) ($fa['id'] ?? 0) }}"
                                              data-slots-remaining="{{ (int) ($fa['slots_remaining'] ?? 0) }}"
                                              data-lessons-total="{{ (int) ($fa['lessons_total'] ?? 0) }}"
                                              data-fee-amount-cents="{{ (int) ($fa['fee_amount_cents'] ?? 0) }}"
                                              data-package-name="{{ $fa['name'] ?? 'Гибкий абонемент' }}"
                                              title="{{ $flexibleHintText }}">{{ $flexibleHintRatio }}</span>
                                    @else
                                        <i class="fa-solid fa-circle-info text-muted journal-flexible-hint journal-flexible-hint--multi"
                                           tabindex="0"
                                           role="img"
                                           aria-label="{{ $flexibleHintText }}"
                                           data-kids-tooltip-hint="1"
                                           data-bs-toggle="tooltip"
                                           data-bs-placement="top"
                                           data-bs-custom-class="ulp-assignment-paid-tooltip"
                                           data-flexible-items="{{ e(json_encode(array_map(static fn ($fa) => [
                                               'id' => (int) ($fa['id'] ?? 0),
                                               'name' => (string) ($fa['name'] ?? 'Гибкий абонемент'),
                                               'slots_remaining' => (int) ($fa['slots_remaining'] ?? 0),
                                               'lessons_total' => (int) ($fa['lessons_total'] ?? 0),
                                               'fee_amount_cents' => (int) ($fa['fee_amount_cents'] ?? 0),
                                           ], $userFlexibleAssignments), JSON_UNESCAPED_UNICODE)) }}"
                                           title="{{ $flexibleHintText }}"></i>
                                    @endif
                                @endif
                                @if($postpayHintText !== '')
                                    <span class="kids-tooltip-hint text-muted journal-postpay-hint"
                                          tabindex="0"
                                          role="img"
                                          aria-label="{{ $postpayHintHover !== '' ? $postpayHintHover : $postpayHintText }}"
                                          data-kids-tooltip-hint="1"
                                          data-bs-toggle="tooltip"
                                          data-bs-placement="top"
                                          data-bs-custom-class="ulp-assignment-paid-tooltip"
                                          title="{{ $postpayHintHover !== '' ? $postpayHintHover : $postpayHintText }}">{{ $postpayHintText }}</span>
                                @endif
                            </div>
                        </td>

                        @foreach($days as $day)
                            @php
                                $dateKey = $user->id . '_' . $day->format('Y-m-d');
                                $dayItems = $journalOccurrences[$dateKey] ?? [];
                                $count = count($dayItems);
                                $primary = $count === 1 ? $dayItems[0] : null;
                                // Без статуса (типично после привязки в календаре школы) ячейка всё равно должна быть видна.
                                $cellColor = $primary['status_color'] ?? ($count > 0 ? '#e9ecef' : '');
                                $cellIcon = $primary['status_icon'] ?? '';
                                $cellTitle = $primary['status_title'] ?? '';
                                $hasStatusVisual = ($cellIcon !== '' && $cellIcon !== null) || ($cellTitle !== '' && $cellTitle !== null);
                                $isPostpayUser = !empty($postpayUsers[(int) $user->id]);
                                $isPostpayLocked = !empty($postpayLockedUsers[(int) $user->id]);
                                $isFlexibleUser = !empty($flexibleUsers[(int) $user->id]);
                                $flexibleRemainingTotal = 0;
                                foreach ($userFlexibleAssignments as $faRow) {
                                    $flexibleRemainingTotal += max(0, (int) ($faRow['slots_remaining'] ?? 0));
                                }
                                $flexibleHasRemaining = $isFlexibleUser && $flexibleRemainingTotal > 0;
                                $flexibleAtLimit = $isFlexibleUser && $flexibleRemainingTotal < 1;
                                // Прямой гибкий: есть остаток, либо лимит без права на пробное/разовое.
                                $canOpenEmptyFlexible = $count === 0 && (
                                    $flexibleHasRemaining
                                    || ($flexibleAtLimit && empty($canPlaceEmptyCellLesson))
                                );
                                // Постоплата важнее chooser'а при лимите гибкого.
                                $canOpenEmptyPostpay = $isPostpayUser && $count === 0 && !$isPostpayLocked && !$flexibleHasRemaining;
                                // Пробное/разовое (+ гибкий в выборе при лимите).
                                $canOpenEmptyLesson = !empty($canPlaceEmptyCellLesson)
                                    && $count === 0
                                    && !$canOpenEmptyPostpay
                                    && (!$isFlexibleUser || $flexibleAtLimit);
                                $cellClickable = $count > 0 || $canOpenEmptyPostpay || $canOpenEmptyFlexible || $canOpenEmptyLesson;
                                $cellPackageHover = '';
                                if ($count === 1) {
                                    $cellPackageHover = (string) ($primary['package_hover'] ?? $primary['package_name'] ?? '');
                                } elseif ($count > 1) {
                                    $hoverLines = [];
                                    foreach ($dayItems as $dayItem) {
                                        $line = trim((string) ($dayItem['package_hover'] ?? $dayItem['package_name'] ?? ''));
                                        if ($line !== '') {
                                            $hoverLines[] = $line;
                                        }
                                    }
                                    $cellPackageHover = implode("\n", $hoverLines);
                                }
                            @endphp
                            <td class="schedule-cell text-center position-relative
                                @if(isset($teamWeekdays) && count($teamWeekdays) && in_array($day->format('N'), $teamWeekdays)) highlight-column @endif"
                                data-user-id="{{ $user->id }}"
                                data-user-name="{{ $user?->full_name ?: 'Без имени' }}"
                                data-context-team-id="{{ $journalContextTeamId ?? '' }}"
                                data-team-ids="{{ implode(',', $studentTeamIds) }}"
                                data-date="{{ $day->format('Y-m-d') }}"
                                data-occurrence-count="{{ $count }}"
                                data-postpay="{{ $isPostpayUser ? '1' : '0' }}"
                                data-postpay-locked="{{ $isPostpayLocked ? '1' : '0' }}"
                                data-flexible="{{ $isFlexibleUser ? '1' : '0' }}"
                                data-flexible-remaining="{{ $isFlexibleUser ? (int) $flexibleRemainingTotal : 0 }}"
                                data-empty-lesson="{{ $canOpenEmptyLesson ? '1' : '0' }}"
                                @if($cellPackageHover !== '')
                                    data-package-hover="{{ $cellPackageHover }}"
                                    data-kids-tooltip-hint="1"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-bs-custom-class="ulp-assignment-paid-tooltip"
                                    title="{{ $cellPackageHover }}"
                                @elseif($isPostpayLocked)
                                    data-kids-tooltip-hint="1"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-bs-custom-class="ulp-assignment-paid-tooltip"
                                    title="Изменить данные нельзя, поскольку уже была произведена оплата"
                                @endif
                                @if($primary)
                                    data-utss-id="{{ $primary['utss_id'] }}"
                                    data-status-id="{{ $primary['lesson_occurrence_status_id'] }}"
                                    data-comment="{{ $primary['comment'] }}"
                                @endif
                                style="cursor: {{ $cellClickable || $isPostpayLocked ? 'pointer' : 'default' }};">
                                @if($count > 1)
                                    <span class="schedule-cell__swatch" @if($cellColor) style="background-color: {{ $cellColor }};" @endif>
                                        <span class="badge bg-primary">×{{ $count }}</span>
                                    </span>
                                @elseif($count === 1)
                                    <span class="schedule-cell__swatch" @if($cellColor) style="background-color: {{ $cellColor }};" @endif>
                                        @if($hasStatusVisual)
                                            @if($cellIcon)
                                                <i class="{{ $cellIcon }} schedule-cell-status-icon" aria-hidden="true"></i>
                                            @else
                                                {{ $cellTitle }}
                                            @endif
                                        @else
                                            <i class="fa-solid fa-circle text-secondary schedule-cell-empty-dot" aria-hidden="true"></i>
                                        @endif
                                    </span>
                                    @if(!empty($primary['comment']))
                                        <div class="cell-comment-indicator"
                                             style="position: absolute; top: 0; right: 0; width: 0; height: 0; border-top: 5px solid red; border-left: 5px solid transparent;"></div>
                                    @endif
                                @elseif($canOpenEmptyFlexible)
                                    <i class="fa-regular fa-circle text-primary schedule-cell-empty-dot" style="opacity: 0.4;" title="Гибкий абонемент: поставить занятие"></i>
                                @elseif($canOpenEmptyPostpay)
                                    <i class="fa-regular fa-circle text-muted schedule-cell-empty-dot" style="opacity: 0.45;" title="Постоплата: отметить посещение"></i>
                                @elseif($canOpenEmptyLesson)
                                    <i class="fa-regular fa-circle text-secondary schedule-cell-empty-dot" style="opacity: 0.35;"
                                       title="{{ $flexibleAtLimit ? 'Пробное, разовое или занятие из гибкого абонемента' : 'Пробное или разовое занятие' }}"></i>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
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
                            <i class="fa-solid fa-plus me-1"></i>Добавить занятие из гибкого абонемента
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

    {{-- Занятие из гибкого абонемента (установка цен) --}}
    <div class="modal fade" id="flexiblePlaceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content schedule-modal-content cell-edit-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Занятие из гибкого абонемента</h5>
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
                                <input type="number" class="form-control" id="empty-cell-fee-amount" name="fee_amount" min="0" step="0.01">
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

                            <div class="cell-edit-section d-none" id="empty-cell-trainer-wrap">
                                <label for="empty-cell-trainer-profile-id" class="cell-edit-section__label">Тренер</label>
                                <select class="form-select" id="empty-cell-trainer-profile-id" name="trainer_profile_id">
                                    <option value="">Без тренера</option>
                                </select>
                                <div class="form-text text-muted" id="empty-cell-trainer-hint"></div>
                                <div class="invalid-feedback" id="empty-cell-trainer-error"></div>
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
