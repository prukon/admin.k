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
                        $hasPlaceable = collect($userAssignments)->contains(fn ($a) => !empty($a['placeable']));
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
                        <td class="text-center">
                            @if($hasPlaceable)
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary journal-abonement-btn"
                                        data-user-id="{{ $user->id }}"
                                        title="Разложить абонемент">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            @endif
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
                                $canOpenEmptyPostpay = $isPostpayUser && $count === 0 && !$isPostpayLocked;
                                $cellClickable = $count > 0 || $canOpenEmptyPostpay;
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
                                @if($isPostpayLocked)
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
                                            <i class="fa-solid fa-circle text-secondary schedule-cell-empty-dot" title="{{ $primary['package_name'] ?? 'Занятие' }}"></i>
                                        @endif
                                    </span>
                                    @if(!empty($primary['comment']))
                                        <div class="cell-comment-indicator"
                                             style="position: absolute; top: 0; right: 0; width: 0; height: 0; border-top: 5px solid red; border-left: 5px solid transparent;"></div>
                                    @endif
                                @elseif($canOpenEmptyPostpay)
                                    <i class="fa-regular fa-circle text-muted schedule-cell-empty-dot" style="opacity: 0.45;" title="Постоплата: отметить посещение"></i>
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
            <div class="modal-content schedule-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cellEditModalLabel">Статус занятия</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div><span id="edit-user-name-display"></span></div>
                        <div><small class="text-muted" id="edit-user-teams-display"></small></div>
                        <div><span id="edit-date-display"></span></div>
                        <div><small class="text-muted" id="edit-occurrence-meta"></small></div>
                    </div>

                    <div class="mb-3 d-none" id="edit-postpay-team-wrap">
                        <label class="form-label" for="edit-postpay-team-select">Группа для отметки</label>
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

                        <div class="mb-3">
                            <label class="form-label d-block">Статус</label>
                            <div class="invalid-feedback d-block" id="cell-status-error" style="display:none;"></div>

                            @foreach($availableStatuses as $st)
                                <div class="form-check mb-2 d-flex align-items-center">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="lesson_occurrence_status_id"
                                           id="status-{{ $st->id }}"
                                           value="{{ $st->id }}"
                                           data-icon="{{ $st->icon }}"
                                           data-color="{{ $st->color }}"
                                           data-consumes-lesson="{{ !empty($st->consumes_lesson) ? '1' : '0' }}"
                                           @if(!empty($visitedStatusId) && (int) $st->id === (int) $visitedStatusId) data-is-visited="1" @endif>
                                    <label class="form-check-label ms-2" for="status-{{ $st->id }}">
                                        <span class="schedule-status-option-chip" style="background-color: {{ $st->color }};">
                                            <i class="{{ $st->icon }}" aria-hidden="true"></i>
                                        </span>
                                        <span class="ms-1">{{ $st->title }}</span>
                                    </label>
                                    @if(!empty($st->consumes_lesson))
                                        <i class="fa-solid fa-circle-info text-muted ms-2 cell-status-postpay-billing-hint d-none"
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

                        <div class="mb-3 d-none" id="cell-trainer-wrap">
                            <label for="cell-trainer-profile-id" class="form-label">Тренер</label>
                            <select class="form-select" id="cell-trainer-profile-id" name="trainer_profile_id">
                                <option value="">Без тренера</option>
                            </select>
                            <div class="form-text text-muted" id="cell-trainer-hint"></div>
                            <div class="invalid-feedback" id="cell-trainer-error"></div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Комментарий</label>
                            <textarea class="form-control" id="description" name="comment" rows="3"></textarea>
                            <div class="invalid-feedback" id="cell-comment-error"></div>
                        </div>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Раскладка фиксированного абонемента --}}
    <div class="modal fade" id="abonementPlaceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Разложить абонемент</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><strong id="abonement-user-name"></strong></div>
                    <form id="abonementPlaceForm">
                        <div class="mb-3">
                            <label for="abonement-ulp-id" class="form-label">Абонемент</label>
                            <select class="form-select" id="abonement-ulp-id" name="user_lesson_package_id"></select>
                            <div class="invalid-feedback" id="abonement-ulp-error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="abonement-team-id" class="form-label">Группа</label>
                            <select class="form-select" id="abonement-team-id" name="team_id"></select>
                            <div class="invalid-feedback" id="abonement-team-error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="abonement-start-date" class="form-label">Дата начала</label>
                            <input type="date" class="form-control" id="abonement-start-date" name="start_date">
                            <div class="invalid-feedback" id="abonement-start-date-error"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label d-block">Дни недели</label>
                            <div id="abonement-weekdays" class="d-flex flex-wrap gap-2"></div>
                            <div class="invalid-feedback d-block" id="abonement-weekdays-error"></div>
                        </div>
                        <div class="mb-3" id="abonement-preview-wrap" style="display:none;">
                            <label class="form-label">Превью</label>
                            <div class="small text-muted" id="abonement-preview-text"></div>
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

    @include('includes.logModal')
