{{--
  Расписание школы: недельная сетка времени (по умолчанию 09:00–21:00, настраивается в модалке), фильтр локации, назначение абонементов.
--}}
@php
    $weekLabels = $weekdays ?? [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];
@endphp

<div class="school-cal">
    <div class="school-cal__toolbar card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-column flex-md-row flex-md-wrap align-items-stretch align-items-md-center gap-2 gap-md-3">
                <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                    <span class="school-cal__toolbar-k text-muted text-nowrap">Неделя</span>
                    <div class="btn-group btn-group-sm school-cal__week-nav flex-grow-1" role="group" style="max-width: 22rem">
                        <button type="button" class="btn btn-outline-primary px-2" id="schoolCalPrevWeek" title="Предыдущая">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button type="button" class="btn btn-primary px-2 text-truncate" id="schoolCalWeekLabel" style="max-width: 14rem">
                            —
                        </button>
                        <button type="button" class="btn btn-outline-primary px-2" id="schoolCalNextWeek" title="Следующая">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <button type="button" class="btn btn-link btn-sm py-0 px-1 text-nowrap d-none d-md-inline-block" id="schoolCalToday">Сегодня</button>
                </div>
                @can('locations.view')
                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                        <label class="school-cal__toolbar-k text-muted mb-0 text-nowrap" for="schoolCalLocation">Локация</label>
                        <select class="form-select form-select-sm flex-grow-1" id="schoolCalLocation" @if($locations->isEmpty()) disabled @endif style="min-width: 8rem; max-width: 18rem">
                            @forelse ($locations as $loc)
                                <option value="{{ $loc->id }}" @if($loop->first) selected @endif>{{ $loc->name }}</option>
                            @empty
                                <option value="">Нет локаций</option>
                            @endforelse
                        </select>
                    </div>
                @endcan
                <div class="d-flex align-items-center justify-content-md-end ms-md-auto">
                    <div class="wrap-icon btn"
                         data-bs-toggle="modal"
                         data-bs-target="#schoolCalViewSettingsModal"
                         title="Отображение календаря">
                        <i class="fa-solid fa-gear settings-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="schoolCalViewSettingsModal" tabindex="-1" aria-labelledby="schoolCalViewSettingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="schoolCalViewSettingsModalLabel">График отображения календаря</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Настраивается только сетка на экране. Слоты и записи в системе этим окном не ограничиваются.</p>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="schoolCalViewStart">С</label>
                            <select class="form-select" id="schoolCalViewStart">
                                @for ($m = 0; $m <= 1380; $m += 30)
                                    <option value="{{ $m }}">{{ sprintf('%02d:%02d', intdiv($m, 60), $m % 60) }}</option>
                                @endfor
                            </select>
                            <div class="invalid-feedback d-block" data-err="view_start_min"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="schoolCalViewEnd">До</label>
                            <select class="form-select" id="schoolCalViewEnd">
                                @for ($m = 60; $m <= 1440; $m += 30)
                                    <option value="{{ $m }}">{{ $m >= 1440 ? '24:00' : sprintf('%02d:%02d', intdiv($m, 60), $m % 60) }}</option>
                                @endfor
                            </select>
                            <div class="invalid-feedback d-block" data-err="view_end_min"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm me-auto" id="schoolCalViewSettingsReset">По умолчанию</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="schoolCalViewSettingsSave">Сохранить</button>
                </div>
            </div>
        </div>
    </div>

    <div id="schoolCalAlert" class="alert d-none" role="alert"></div>

    <div class="school-cal__grid-wrap card border-0 shadow-sm school-cal__grid-wrap--events-visible">
        <div class="school-cal__grid-scroll">
            <div class="school-cal__grid @can('scheduleSlots.manage') school-cal__grid--manage-slots @endcan" id="schoolCalGrid" aria-busy="true">
                <div class="school-cal__loading p-5 text-center text-muted w-100">
                    <span class="spinner-border spinner-border-sm me-2"></span> Загрузка расписания…
                </div>
            </div>
        </div>
    </div>

    {{-- Детали слота --}}
    <div class="modal fade" id="schoolCalSlotModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0 align-items-start">
                    <h5 class="modal-title mb-0">Слот расписания</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body pt-2">
                    <div class="school-cal-slot-summary rounded-3 border px-3 py-3 mb-3">
                        <div class="school-cal-slot-summary__when fw-semibold text-dark lh-sm" id="schoolCalSlotSummaryWhen">—</div>
                        <div class="school-cal-slot-summary__meta small mt-3 pt-2 border-top border-light">
                            <div class="d-flex gap-2 justify-content-between">
                                <span class="text-muted flex-shrink-0">Группа</span>
                                <span class="text-end text-break" id="schoolCalSlotSummaryTeam">—</span>
                            </div>
                            <div class="d-flex gap-2 justify-content-between mt-2">
                                <span class="text-muted flex-shrink-0">Локация</span>
                                <span class="text-end text-break" id="schoolCalSlotSummaryLoc">—</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 border rounded-3 px-3 py-3 bg-light bg-opacity-50" id="schoolCalSlotAddBlock">
                        <div class="text-muted text-uppercase small mb-2 fw-semibold" style="font-size:0.68rem;letter-spacing:0.06em">Добавить запись</div>
                        {{-- <label class="form-label small mb-1" for="schoolCalSlotUserSelect">Ученик</label> --}}
                        <select class="form-select" id="schoolCalSlotUserSelect" style="width:100%" aria-describedby="schoolCalSlotTrialErr"></select>
                        <div class="small text-danger mt-1 d-none" id="schoolCalSlotTrialErr" role="alert"></div>
                        <div class="d-none small text-muted mt-2" id="schoolCalSlotBindActionsLoading">Проверяем назначения…</div>
                        <div class="d-none small text-danger mt-2" id="schoolCalSlotBindActionsError"></div>
                        <div class="d-none mt-3" id="schoolCalSlotBindButtonsWrap">
                            <hr class="my-3">
                            <div class="d-grid gap-2" id="schoolCalSlotBindButtons">
                                <span class="d-inline-block school-cal-slot-action-host" title="">
                                    <button type="button" class="btn btn-primary w-100" id="schoolCalOpenTrial" disabled>Добавить пробное занятие</button>
                                </span>
                                <span class="d-inline-block school-cal-slot-action-host" title="">
                                    <button type="button" class="btn btn-primary w-100" id="schoolCalOpenSingle" disabled>Добавить разовое занятие</button>
                                </span>
                                <span class="d-inline-block school-cal-slot-action-host" title="">
                                    <button type="button" class="btn btn-primary w-100" id="schoolCalOpenFlexible" disabled>Привязать гибкий абонемент</button>
                                </span>
                                <span class="d-inline-block school-cal-slot-action-host" title="">
                                    <button type="button" class="btn btn-primary w-100" id="schoolCalOpenFixed" disabled>Привязать фиксированный абонемент</button>
                                </span>
                            </div>
                        </div>
                    </div>
                    {{-- <hr class="my-3"> --}}
                    <div class="mb-3 d-none" id="schoolCalSlotRegistrationsWrap">
                        <div class="text-muted text-uppercase small mb-2 fw-semibold" style="font-size:0.68rem;letter-spacing:0.06em">Записаны на занятие</div>
                        <ul class="list-unstyled mb-0" id="schoolCalSlotRegistrationsList"></ul>
                    </div>
                    @can('scheduleSlots.manage')
                        <hr class="my-3">
                        <button type="button" class="btn btn-outline-danger w-100" id="schoolCalSlotChangeLessonBtn">
                            Изменить занятие
                        </button>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    {{-- Гибкое назначение --}}
    <div class="modal fade" id="schoolCalFlexModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Гибкий абонемент</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="schoolCalFlexForm">
                        @csrf
                        <input type="hidden" name="team_schedule_slot_id" id="schoolCalFlexSlotId">
                        <input type="hidden" name="occurrence_date" id="schoolCalFlexDate">
                        <div class="mb-3">
                            <label class="form-label">Ученик</label>
                            <select class="form-select" id="schoolCalFlexUser" style="width:100%" required></select>
                            <div class="invalid-feedback d-block" data-err="user_id"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Назначение абонемента</label>
                            <select class="form-select" name="user_lesson_package_id" id="schoolCalFlexUlp" required>
                                <option value="">Сначала выберите ученика</option>
                            </select>
                            <div class="invalid-feedback d-block" data-err="user_lesson_package_id"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="schoolCalFlexSubmit">Привязать</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Разовое занятие (no_schedule) --}}
    <div class="modal fade" id="schoolCalSingleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Разовое занятие</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="schoolCalSingleForm">
                        @csrf
                        <input type="hidden" name="team_schedule_slot_id" id="schoolCalSingleSlotId">
                        <input type="hidden" name="occurrence_date" id="schoolCalSingleDate">
                        <div class="mb-3">
                            <label class="form-label">Ученик</label>
                            <select class="form-select" id="schoolCalSingleUser" style="width:100%" required></select>
                            <div class="invalid-feedback d-block" data-err="user_id"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Назначение</label>
                            <select class="form-select" name="user_lesson_package_id" id="schoolCalSingleUlp" required>
                                <option value="">Сначала выберите ученика</option>
                            </select>
                            <div class="invalid-feedback d-block" data-err="user_lesson_package_id"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="schoolCalSingleSubmit">Записать в расписание</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Фиксированное назначение --}}
    <div class="modal fade" id="schoolCalFixedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Фиксированный абонемент</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="schoolCalFixedForm">
                        @csrf
                        <input type="hidden" name="team_schedule_slot_id" id="schoolCalFixedSlotId">
                        <input type="hidden" name="anchor_date" id="schoolCalFixedAnchor">
                        <input type="hidden" name="location_id" id="schoolCalFixedLocation">
                        <div class="mb-3">
                            <label class="form-label">Ученик</label>
                            <select class="form-select" id="schoolCalFixedUser" style="width:100%" required></select>
                            <div class="invalid-feedback d-block" data-err="user_id"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Назначение абонемента</label>
                            <select class="form-select" name="user_lesson_package_id" id="schoolCalFixedUlp" required>
                                <option value="">Сначала выберите ученика</option>
                            </select>
                            <div class="invalid-feedback d-block" data-err="user_lesson_package_id"></div>
                        </div>
                        <p class="small text-muted mb-0">
                            Период действия и цепочка занятий задаются от якорной даты; список содержит только назначения без периода (созданные на странице «Назначение абонементов»).
                        </p>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="schoolCalFixedSubmit">Назначить</button>
                </div>
            </div>
        </div>
    </div>
</div>

@can('scheduleSlots.manage')
    @include('admin.teamScheduleSlots.partials.slotModals')
@endcan

<style>
    .school-cal__title {
        font-weight: 700;
        letter-spacing: -0.02em;
        background: linear-gradient(120deg, #1e3a5f 0%, #2563eb 55%, #7c3aed 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .school-cal__subtitle { max-width: 52rem; line-height: 1.5; }
    .school-cal__toolbar {
        border-radius: 14px !important;
        background: linear-gradient(145deg, rgba(255,255,255,.96), rgba(248,250,252,.92));
    }
    .school-cal__toolbar-k {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 600;
    }
    /* Стрелки недели: оранжевый как у .btn-primary в resources/css/style.css (#f3a12b) */
    .school-cal__week-nav > .btn-outline-primary {
        color: #f3a12b;
        border-color: #f3a12b;
    }
    .school-cal__week-nav > .btn-outline-primary:hover {
        color: #fff;
        background-color: #f3a12b;
        border-color: #f3a12b;
    }
    .school-cal__week-nav > .btn-primary {
        border-color: #f3a12b;
    }
    /* Не даём глобальному .btn-primary:hover убрать рамку (там border-color: white !important) */
    .school-cal__week-nav > .btn-primary:hover,
    .school-cal__week-nav > .btn-primary:focus,
    .school-cal__week-nav > .btn-primary:active,
    .school-cal__week-nav > .btn-primary:focus-visible {
        border-color: #f3a12b !important;
    }
    /* Сегодня: тонкая рамка как у стрелок (1px), цвет как в админке */
    .school-cal__day-head--today {
        border-top: 1px solid #f3a12b;
        border-left: 1px solid #f3a12b;
        border-right: 1px solid #f3a12b;
    }
    .school-cal__grid-wrap {
        border-radius: 14px !important;
        background: #f8fafc;
    }
    /* Не обрезаем карточки занятий по вертикали; горизонтальный скролл — внутри .school-cal__grid-scroll */
    .school-cal__grid-wrap--events-visible {
        overflow: visible;
    }
    .school-cal__grid-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .school-cal__grid {
        min-width: 880px;
    }
    .school-cal__head-row {
        display: grid;
        grid-template-columns: 56px repeat(7, minmax(104px, 1fr));
    }
    .school-cal__body-row {
        display: grid;
        grid-template-columns: 56px repeat(7, minmax(104px, 1fr));
        border-top: 1px solid rgba(148,163,184,.35);
    }
    .school-cal__time-col {
        border-right: 1px solid rgba(148,163,184,.35);
        background: linear-gradient(180deg, #f1f5f9 0%, #f8fafc 100%);
    }
    .school-cal__time-label {
        height: 40px;
        font-size: 11px;
        color: #64748b;
        padding: 2px 6px 0;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .school-cal__day-head {
        padding: 10px 8px;
        text-align: center;
        border-bottom: 1px solid rgba(148,163,184,.35);
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }
    .school-cal__day-name {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
        font-weight: 600;
    }
    .school-cal__day-num {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
    }
    .school-cal__day-col {
        position: relative;
        border-right: 1px solid rgba(226,232,240,.8);
        background-color: rgba(255,255,255,.5);
        background-image: linear-gradient(to bottom, rgba(226,232,240,.5) 1px, transparent 1px);
        background-size: 100% 40px;
    }
    .school-cal__day-col--today {
        background: rgba(219,234,254,.35);
    }
    .school-cal__grid--manage-slots .school-cal__day-col--body {
        cursor: pointer;
    }
    .school-cal__event {
        position: absolute;
        left: 4px;
        right: 4px;
        border-radius: 10px;
        padding: 6px 8px;
        font-size: 12px;
        line-height: 1.35;
        color: #0f172a;
        cursor: pointer;
        border: 1px solid rgba(255,255,255,.6);
        box-shadow: 0 6px 14px rgba(15,23,42,.08);
        transition: transform .12s ease, box-shadow .12s ease;
        overflow: hidden;
    }
    .school-cal__event:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(15,23,42,.12);
        z-index: 2;
    }
    .school-cal__event-time {
        font-size: 10px;
        opacity: .85;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    .school-cal__event-team {
        font-weight: 600;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .school-cal__reg-preview {
        margin-top: 5px;
        font-size: 10px;
        line-height: 1.3;
        color: #334155;
        display: flex;
        flex-direction: column;
        gap: 3px;
        max-height: 3.15rem;
        overflow: hidden;
    }
    .school-cal__reg-chip {
        display: flex;
        align-items: center;
        gap: 5px;
        min-width: 0;
        width: 100%;
    }
    .school-cal__reg-name {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 600;
        color: #1e293b;
    }
    .school-cal__reg-status-pill {
        flex: 0 1 auto;
        font-size: 9px;
        font-weight: 600;
        line-height: 1.15;
        padding: 2px 6px;
        border-radius: 999px;
        background: var(--pill-bg, #64748b);
        color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 5.2rem;
        box-shadow: 0 1px 2px rgba(15,23,42,.12);
    }
    .school-cal__reg-ellipsis {
        color: #64748b;
        font-weight: 600;
        font-size: 10px;
        margin-top: 1px;
    }
    .school-cal__corner {
        border-bottom: 1px solid rgba(148,163,184,.35);
        background: #f8fafc;
    }
    .school-cal-slot-summary {
        background: linear-gradient(145deg, rgba(248,250,252,.98), rgba(241,245,249,.92));
        border-color: rgba(148,163,184,.35) !important;
    }
    .school-cal-slot-summary__when {
        font-size: 1.05rem;
        letter-spacing: -0.01em;
    }
    .school-cal__reg-card {
        background: #fff;
        border-color: rgba(226,232,240,.95) !important;
        box-shadow: 0 1px 2px rgba(15,23,42,.04);
    }
    .school-cal__reg-modal-status {
        font-size: 0.78rem;
        font-weight: 600;
        padding: 0.35rem 0.65rem;
        max-width: 100%;
        white-space: normal;
        line-height: 1.25;
    }
    .school-cal__reg-trial-pill {
        flex: 0 0 auto;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 2px 6px;
        border-radius: 6px;
        background: rgba(99, 102, 241, 0.18);
        color: #4338ca;
        border: 1px solid rgba(99, 102, 241, 0.35);
        white-space: nowrap;
    }
    .school-cal__reg-controls select.form-select {
        min-width: 0;
    }
    .school-cal__reg-item {
        background: rgba(248,250,252,.95);
    }
    .school-cal__reg-hist {
        font-size: 0.78rem;
        line-height: 1.35;
    }
    .school-cal__reg-hist-row {
        padding: 0.2rem 0;
        border-bottom: 1px solid rgba(226,232,240,.9);
    }
    .school-cal__reg-hist-row:last-child {
        border-bottom: 0;
    }
    /* Модалка слота: отключённые кнопки привязки — явные цвета (без белого на белом из глобальных стилей) */
    #schoolCalSlotBindButtons .btn:disabled,
    #schoolCalSlotBindButtons .btn.disabled {
        opacity: 1;
        pointer-events: none;
    }
    #schoolCalSlotBindButtons .btn-primary:disabled,
    #schoolCalSlotBindButtons .btn-primary.disabled {
        color: #fff !important;
        background-color: #b8bec5 !important;
        border-color: #a8b0b8 !important;
    }
    .school-cal__reg-card--compact {
        padding: 0.5rem 0.65rem !important;
        margin-bottom: 0.45rem !important;
    }
    .school-cal__reg-card__oneline {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        min-width: 0;
        width: 100%;
        line-height: 1.25;
    }
    .school-cal__reg-card__fio {
        font-weight: 600;
        color: #0f172a;
        flex: 0 1 auto;
        max-width: 48%;
        min-width: 0;
    }
    .school-cal__reg-card__pkg {
        font-weight: 500;
        color: #475569;
        font-size: 0.8125rem;
        flex: 1 1 0;
        min-width: 0;
    }
    .school-cal__reg-card--compact .school-cal__reg-controls-row {
        margin-top: 0.4rem;
        padding-top: 0.4rem;
        border-top: 1px solid rgba(226, 232, 240, 0.95);
    }
</style>

@push('scripts')
    <script>
        (function () {
            const routes = {
                slotUserBindActions: @json(route('admin.lesson-packages.school-schedule.slot-user-bind-actions')),
                week: @json(route('admin.lesson-packages.school-schedule.week')),
                usersSearch: @json(route('admin.lesson-packages.assignments.users-search')),
                flexAssign: @json(route('admin.lesson-packages.school-schedule.assign-flexible')),
                fixedAssign: @json(route('admin.lesson-packages.school-schedule.assign-fixed')),
                flexUlps: @json(route('admin.lesson-packages.school-schedule.flexible-assignments')),
                fixedUlps: @json(route('admin.lesson-packages.school-schedule.fixed-assignments')),
                flexUsersSearch: @json(route('admin.lesson-packages.school-schedule.flexible-users-search')),
                singleAssign: @json(route('admin.lesson-packages.school-schedule.assign-single-lesson')),
                singleUlps: @json(route('admin.lesson-packages.school-schedule.single-lesson-assignments')),
                singleUsersSearch: @json(route('admin.lesson-packages.school-schedule.single-lesson-users-search')),
                occurrenceStatusStore: @json(route('admin.lesson-packages.school-schedule.occurrence-status.store')),
                occurrenceStatusHistory: @json(route('admin.lesson-packages.school-schedule.occurrence-status.history')),
                trialRegistrationStore: @json(route('admin.lesson-packages.school-schedule.trial-registration.store')),
                trialRegistrationRoot: @json(url('/admin/lesson-packages/school-schedule/trial-registration')),
                viewSettingsSave: @json(route('admin.lesson-packages.school-schedule.view-settings.save')),
            };
            const viewSettingsInitial = @json($schoolScheduleViewSettings ?? ['view_start_min' => 540, 'view_end_min' => 1260]);
            const occurrenceStatuses = @json($schoolCalendarOccurrenceStatuses ?? []);
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const weekLabels = @json($weekLabels);

            const SLOT_PX = 40;
            let viewStartMin = viewSettingsInitial.view_start_min;
            let viewEndMin = viewSettingsInitial.view_end_min;
            let lastOccurrences = [];

            function viewTotalMin() {
                return viewEndMin - viewStartMin;
            }

            function gridHeightPx() {
                return (viewTotalMin() / 30) * SLOT_PX;
            }

            let weekMonday = startOfWeekMonday(new Date());
            let selectedOccurrence = null;
            let schoolCalSlotRegisteredUserIds = [];
            let schoolCalSlotBindFetchTimer = null;
            const SchoolCalSlotBindDebounceMs = 350;

            function pad(n) { return n < 10 ? '0' + n : '' + n; }

            function startOfWeekMonday(d) {
                const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
                const day = x.getDay();
                const diff = day === 0 ? -6 : 1 - day;
                x.setDate(x.getDate() + diff);
                return x;
            }

            function formatYmd(d) {
                return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            }

            const RU_MONTHS_GEN = [
                'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
                'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
            ];

            function formatRuLongDate(d) {
                return d.getDate() + ' ' + RU_MONTHS_GEN[d.getMonth()] + ' ' + d.getFullYear();
            }

            /** «5 мая 2026 11:30–12:00» для шапки модалки слота */
            function formatSlotModalWhen(ev) {
                return formatRuLongDate(parseYmd(ev.date)) + ' ' + (ev.time_start || '') + '–' + (ev.time_end || '');
            }

            /** Подпись после ФИО из поля line (fallback, если нет lesson_package_name). */
            function registrationPackageKind(r) {
                const ul = (r.user_label || '').trim();
                const ln = (r.line || '').trim();
                if (ul && ln.length >= ul.length && ln.slice(0, ul.length) === ul) {
                    return ln.slice(ul.length).replace(/^\s*,\s*/, '').trim() || '—';
                }
                return ln || '—';
            }

            function registrationPackageDisplayName(r) {
                const n = (r.lesson_package_name || '').trim();
                if (n) {
                    return n;
                }
                return registrationPackageKind(r);
            }

            function parseYmd(s) {
                const p = s.split('-');
                return new Date(+p[0], +p[1] - 1, +p[2]);
            }

            function addDays(d, n) {
                const x = new Date(d.getTime());
                x.setDate(x.getDate() + n);
                return x;
            }

            function minutesFromMidnight(hm) {
                const p = hm.split(':');
                return (+p[0]) * 60 + (+p[1]);
            }

            function escapeHtml(text) {
                const d = document.createElement('div');
                d.textContent = text == null ? '' : String(text);
                return d.innerHTML;
            }

            /** ISO weekday 1=Пн … 7=Вс для локальной даты Y-m-d */
            function isoWeekdayFromYmd(ymd) {
                const p = ymd.split('-');
                const d = new Date(+p[0], +p[1] - 1, +p[2]);
                const day = d.getDay();
                return day === 0 ? 7 : day;
            }

            /** Время начала/конца (шаг 30 мин) по вертикали клика в колонке дня */
            function slotTimesFromColumnClick(clientY, colEl) {
                const rect = colEl.getBoundingClientRect();
                let y = clientY - rect.top;
                if (y < 0) y = 0;
                if (y > rect.height) y = rect.height;
                const ratio = rect.height > 0 ? y / rect.height : 0;
                let slotStart = viewStartMin + ratio * viewTotalMin();
                let snapped = Math.floor(slotStart / 30) * 30;
                if (snapped < viewStartMin) snapped = viewStartMin;
                if (snapped > viewEndMin - 30) snapped = viewEndMin - 30;
                const snappedEnd = snapped + 30;
                return {
                    time_start: pad(Math.floor(snapped / 60)) + ':' + pad(snapped % 60),
                    time_end: pad(Math.floor(snappedEnd / 60)) + ':' + pad(snappedEnd % 60)
                };
            }

            function eventColor(teamId) {
                const h = (Math.abs(teamId * 47) % 360);
                return 'hsl(' + h + ' 78% 93%)';
            }

            function showAlert(type, msg) {
                const el = document.getElementById('schoolCalAlert');
                el.className = 'alert alert-' + type;
                el.textContent = msg;
                el.classList.remove('d-none');
                clearTimeout(el._t);
                el._t = setTimeout(() => el.classList.add('d-none'), 5200);
            }

            async function loadWeek() {
                const locEl = document.getElementById('schoolCalLocation');
                const loc = locEl ? locEl.value : '';
                const url = new URL(routes.week, window.location.origin);
                url.searchParams.set('week', formatYmd(weekMonday));
                if (loc) url.searchParams.set('location_id', loc);

                document.getElementById('schoolCalGrid').setAttribute('aria-busy', 'true');

                const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    document.getElementById('schoolCalGrid').innerHTML = '<div class="p-4 text-danger">Не удалось загрузить расписание.</div>';
                    return;
                }

                const occ = data.occurrences || [];
                lastOccurrences = occ;
                renderGrid(occ);
                updateWeekLabel();
                document.getElementById('schoolCalGrid').setAttribute('aria-busy', 'false');
            }

            window.schoolCalReloadWeek = loadWeek;
            window.schoolCalNotifySlotMutationSuccess = function (msg) {
                showAlert('success', msg || 'Готово');
            };

            function updateWeekLabel() {
                const end = addDays(weekMonday, 6);
                const el = document.getElementById('schoolCalWeekLabel');
                el.textContent = formatRuLongDate(weekMonday) + ' - ' + formatRuLongDate(end);
            }

            function renderGrid(occurrences) {
                const today = formatYmd(new Date());
                const byDate = {};
                occurrences.forEach(o => {
                    (byDate[o.date] = byDate[o.date] || []).push(o);
                });

                const days = [];
                for (let i = 0; i < 7; i++) {
                    days.push(addDays(weekMonday, i));
                }

                let html = '<div class="school-cal__head-row">';

                html += '<div class="school-cal__corner"></div>';
                days.forEach(d => {
                    const ymd = formatYmd(d);
                    const isToday = ymd === today;
                    const dow = d.getDay() === 0 ? 7 : d.getDay();
                    html += '<div class="school-cal__day-head' + (isToday ? ' school-cal__day-head--today rounded-top' : '') + '">';
                    html += '<div class="school-cal__day-name">' + (weekLabels[dow] || '') + '</div>';
                    html += '<div class="school-cal__day-num">' + d.getDate() + '</div>';
                    html += '</div>';
                });

                html += '</div><div class="school-cal__body-row">';

                html += '<div class="school-cal__time-col d-flex flex-column border-end" style="height:' + gridHeightPx() + 'px">';
                for (let m = viewStartMin; m < viewEndMin; m += 30) {
                    const hh = Math.floor(m / 60);
                    const mm = m % 60;
                    html += '<div class="school-cal__time-label" style="min-height:' + SLOT_PX + 'px;height:' + SLOT_PX + 'px">' + pad(hh) + ':' + pad(mm) + '</div>';
                }
                html += '</div>';

                days.forEach(d => {
                    const ymd = formatYmd(d);
                    const isToday = ymd === today;
                    html += '<div class="school-cal__day-col school-cal__day-col--body' + (isToday ? ' school-cal__day-col--today' : '') + '" ';
                    html += 'style="min-height:' + gridHeightPx() + 'px;height:' + gridHeightPx() + 'px" data-date="' + ymd + '">';
                    const list = byDate[ymd] || [];
                    list.forEach(ev => {
                        const start = minutesFromMidnight(ev.time_start);
                        const end = minutesFromMidnight(ev.time_end);
                        const top = ((start - viewStartMin) / viewTotalMin()) * 100;
                        const h = Math.max(8, ((end - start) / viewTotalMin()) * 100);
                        const bg = eventColor(ev.team_id);
                        html += '<div class="school-cal__event" style="top:' + top + '%;height:' + h + '%;background:' + bg + '" ';
                        html += 'data-ev="' + encodeURIComponent(JSON.stringify(ev)) + '">';
                        html += '<div class="school-cal__event-time">' + ev.time_start + '–' + ev.time_end + '</div>';
                        html += '<div class="school-cal__event-team">' + escapeHtml(ev.team_title || 'Группа') + '</div>';
                        if (ev.location_name) {
                            html += '<div class="small text-muted text-truncate">' + escapeHtml(ev.location_name) + '</div>';
                        }
                        const regs = ev.registrations || [];
                        if (regs.length) {
                            html += '<div class="school-cal__reg-preview">';
                            regs.slice(0, 3).forEach(function (r) {
                                html += '<div class="school-cal__reg-chip">';
                                html += '<span class="school-cal__reg-name">' + escapeHtml(r.user_label || '') + '</span>';
                                if (r.registration_kind === 'trial' || r.is_trial_lesson) {
                                    html += '<span class="school-cal__reg-trial-pill">пробное</span>';
                                } else if (r.current_status && r.current_status.title) {
                                    var pc = escapeHtml(r.current_status.color || '#64748b');
                                    var pt = escapeHtml(r.current_status.title);
                                    html += '<span class="school-cal__reg-status-pill" style="--pill-bg:' + pc + '" title="' + pt + '">' + pt + '</span>';
                                }
                                html += '</div>';
                            });
                            if (regs.length > 3) {
                                html += '<span class="school-cal__reg-ellipsis">ещё +' + (regs.length - 3) + '</span>';
                            }
                            html += '</div>';
                        }
                        html += '</div>';
                    });
                    html += '</div>';
                });

                html += '</div>';

                document.getElementById('schoolCalGrid').innerHTML = html;
                document.querySelectorAll('.school-cal__event').forEach(el => {
                    el.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const ev = JSON.parse(decodeURIComponent(el.getAttribute('data-ev')));
                        openSlotModal(ev);
                    });
                });
            }

            function fillRegistrationsList(ev) {
                const wrap = document.getElementById('schoolCalSlotRegistrationsWrap');
                const ul = document.getElementById('schoolCalSlotRegistrationsList');
                if (!ul || !wrap) return;
                ul.innerHTML = '';
                const regs = ev.registrations || [];
                if (!regs.length) {
                    wrap.classList.add('d-none');
                    return;
                }
                wrap.classList.remove('d-none');
                regs.forEach(function (r) {
                    const li = document.createElement('li');
                    const isTrial = r.registration_kind === 'trial' || r.is_trial_lesson;

                    if (isTrial) {
                        li.className = 'school-cal__reg-card school-cal__reg-card--compact border rounded-2';
                        const row = document.createElement('div');
                        row.className = 'd-flex align-items-center justify-content-between gap-2 min-w-0';
                        const oneline = document.createElement('div');
                        oneline.className = 'school-cal__reg-card__oneline flex-grow-1 min-w-0';
                        const fio = document.createElement('span');
                        fio.className = 'school-cal__reg-card__fio text-truncate';
                        fio.textContent = (r.user_label || '').trim() || '—';
                        const sep = document.createElement('span');
                        sep.className = 'text-muted flex-shrink-0 small';
                        sep.textContent = '—';
                        const kind = document.createElement('span');
                        kind.className = 'school-cal__reg-card__pkg text-truncate';
                        kind.textContent = 'Пробное занятие';
                        oneline.appendChild(fio);
                        oneline.appendChild(sep);
                        oneline.appendChild(kind);

                        const delBtn = document.createElement('button');
                        delBtn.type = 'button';
                        delBtn.className = 'btn btn-sm btn-outline-danger flex-shrink-0';
                        delBtn.textContent = 'Удалить';
                        delBtn.addEventListener('click', async function () {
                            if (!window.confirm('Убрать пробное занятие из расписания на эту дату?')) {
                                return;
                            }
                            const url = routes.trialRegistrationRoot + '/' + encodeURIComponent(r.user_team_schedule_slot_id);
                            const res = await fetch(url, {
                                method: 'DELETE',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': token,
                                    'Accept': 'application/json',
                                },
                            });
                            const data = await res.json().catch(function () { return {}; });
                            if (!res.ok) {
                                showAlert('danger', data.message || 'Не удалось удалить запись.');
                                return;
                            }
                            showAlert('success', data.message || 'Готово');
                            bootstrap.Modal.getInstance(document.getElementById('schoolCalSlotModal'))?.hide();
                            loadWeek();
                        });

                        row.appendChild(oneline);
                        row.appendChild(delBtn);
                        li.appendChild(row);
                        ul.appendChild(li);
                        return;
                    }

                    if (!r.user_lesson_package_id) {
                        li.className = 'school-cal__reg-card school-cal__reg-card--compact border rounded-2 small text-muted';
                        li.textContent = r.line || r.user_label || '';
                        ul.appendChild(li);
                        return;
                    }

                    li.className = 'school-cal__reg-card school-cal__reg-card--compact border rounded-2';

                    const head = document.createElement('div');
                    head.className = 'school-cal__reg-card__oneline mb-0';
                    const nameEl = document.createElement('span');
                    nameEl.className = 'school-cal__reg-card__fio text-truncate';
                    nameEl.textContent = (r.user_label || '').trim() || '—';
                    const sepEl = document.createElement('span');
                    sepEl.className = 'text-muted flex-shrink-0 small';
                    sepEl.textContent = '—';
                    const kindEl = document.createElement('span');
                    kindEl.className = 'school-cal__reg-card__pkg text-truncate';
                    kindEl.textContent = registrationPackageDisplayName(r);
                    head.appendChild(nameEl);
                    head.appendChild(sepEl);
                    head.appendChild(kindEl);

                    const sel = document.createElement('select');
                    sel.className = 'form-select form-select-sm w-100';
                    sel.setAttribute('aria-label', 'Статус занятия');
                    const emptyOpt = document.createElement('option');
                    emptyOpt.value = '';
                    emptyOpt.textContent = 'Выберите статус';
                    sel.appendChild(emptyOpt);
                    (occurrenceStatuses || []).forEach(function (st) {
                        const o = document.createElement('option');
                        o.value = String(st.id);
                        o.textContent = st.title;
                        if (r.current_status && String(st.id) === String(r.current_status.id)) {
                            o.selected = true;
                        }
                        sel.appendChild(o);
                    });

                    const badge = document.createElement('span');
                    badge.className = 'badge rounded-pill school-cal__reg-modal-status';
                    badge.style.display = r.current_status ? 'inline-block' : 'none';
                    if (r.current_status) {
                        badge.style.background = r.current_status.color || '#6c757d';
                        badge.style.color = '#fff';
                        badge.textContent = r.current_status.title || '';
                    }

                    const errDiv = document.createElement('div');
                    errDiv.className = 'invalid-feedback d-block small w-100 mt-1';
                    errDiv.style.display = 'none';

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-sm btn-primary text-nowrap';
                    btn.textContent = 'Сохранить';
                    btn.addEventListener('click', async function () {
                        errDiv.textContent = '';
                        errDiv.style.display = 'none';
                        sel.classList.remove('is-invalid');
                        if (!sel.value) {
                            errDiv.textContent = 'Выберите статус.';
                            errDiv.style.display = 'block';
                            sel.classList.add('is-invalid');
                            return;
                        }
                        const fd = new FormData();
                        fd.append('_token', token);
                        fd.set('team_schedule_slot_id', String(ev.id));
                        fd.set('occurrence_date', String(ev.date));
                        fd.set('user_id', String(r.user_id));
                        fd.set('user_lesson_package_id', String(r.user_lesson_package_id));
                        fd.set('lesson_occurrence_status_id', sel.value);
                        const res = await fetch(routes.occurrenceStatusStore, {
                            method: 'POST',
                            body: fd,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            }
                        });
                        const data = await res.json().catch(function () { return {}; });
                        if (!res.ok) {
                            let msg = data.message || 'Не удалось сохранить.';
                            if (data.errors) {
                                for (const k of Object.keys(data.errors)) {
                                    if (data.errors[k] && data.errors[k][0]) {
                                        msg = data.errors[k][0];
                                        break;
                                    }
                                }
                            }
                            errDiv.textContent = msg;
                            errDiv.style.display = 'block';
                            sel.classList.add('is-invalid');
                            return;
                        }
                        if (data.event && data.event.lesson_occurrence_status) {
                            r.current_status = data.event.lesson_occurrence_status;
                            badge.style.display = 'inline-block';
                            badge.style.background = r.current_status.color || '#6c757d';
                            badge.textContent = r.current_status.title || '';
                        }
                        showAlert('success', data.message || 'Статус сохранён.');
                        loadWeek();
                    });

                    const controlsRow = document.createElement('div');
                    controlsRow.className = 'row g-2 align-items-center school-cal__reg-controls-row';
                    const colSel = document.createElement('div');
                    colSel.className = 'col-12 col-md';
                    colSel.appendChild(sel);
                    const colBadge = document.createElement('div');
                    colBadge.className = 'col-12 col-md-auto';
                    colBadge.appendChild(badge);
                    const colBtn = document.createElement('div');
                    colBtn.className = 'col-12 col-md-auto';
                    colBtn.appendChild(btn);
                    controlsRow.appendChild(colSel);
                    controlsRow.appendChild(colBadge);
                    controlsRow.appendChild(colBtn);

                    const histToggle = document.createElement('button');
                    histToggle.type = 'button';
                    histToggle.className = 'btn btn-link btn-sm text-muted text-decoration-none p-0';
                    histToggle.textContent = 'История изменений';
                    const histBox = document.createElement('div');
                    histBox.className = 'school-cal__reg-hist mt-2 pt-2 border-top border-light d-none';
                    const histCount = Number(r.occurrence_status_history_count || 0);

                    histToggle.addEventListener('click', async function () {
                        if (histBox.dataset.loaded === '1') {
                            histBox.classList.toggle('d-none');
                            return;
                        }
                        histBox.textContent = 'Загрузка…';
                        histBox.classList.remove('d-none');
                        const url = new URL(routes.occurrenceStatusHistory, window.location.origin);
                        url.searchParams.set('team_schedule_slot_id', String(ev.id));
                        url.searchParams.set('occurrence_date', String(ev.date));
                        url.searchParams.set('user_id', String(r.user_id));
                        url.searchParams.set('user_lesson_package_id', String(r.user_lesson_package_id));
                        const hres = await fetch(url.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const hdata = await hres.json().catch(function () { return {}; });
                        if (!hres.ok) {
                            histBox.textContent = (hdata.message || 'Не удалось загрузить историю.');
                            histBox.dataset.loaded = '1';
                            return;
                        }
                        histBox.innerHTML = '';
                        const evs = hdata.events || [];
                        if (!evs.length) {
                            histBox.textContent = 'Пока нет записей.';
                        } else {
                            evs.forEach(function (histEv) {
                                const rowEl = document.createElement('div');
                                rowEl.className = 'school-cal__reg-hist-row';
                                const st = histEv.lesson_occurrence_status || {};
                                const dot = document.createElement('span');
                                dot.className = 'd-inline-block rounded-circle align-middle me-1';
                                dot.style.width = '8px';
                                dot.style.height = '8px';
                                dot.style.background = st.color || '#94a3b8';
                                rowEl.appendChild(dot);
                                const meta = document.createElement('span');
                                const when = histEv.created_at ? new Date(histEv.created_at).toLocaleString('ru-RU') : '';
                                meta.textContent = (st.title || '—') + (when ? ' · ' + when : '');
                                if (histEv.created_by_label) {
                                    meta.textContent += ' · ' + histEv.created_by_label;
                                }
                                rowEl.appendChild(meta);
                                histBox.appendChild(rowEl);
                            });
                        }
                        histBox.dataset.loaded = '1';
                    });

                    li.appendChild(head);
                    li.appendChild(controlsRow);
                    li.appendChild(errDiv);
                    if (histCount > 0) {
                        const histFoot = document.createElement('div');
                        histFoot.className = 'd-flex justify-content-end mt-2';
                        histFoot.appendChild(histToggle);
                        li.appendChild(histFoot);
                        li.appendChild(histBox);
                    }
                    ul.appendChild(li);
                });
            }

            function setSlotBindActionButtonState(btnId, allowed, reasonText) {
                const btn = document.getElementById(btnId);
                const host = btn ? btn.closest('.school-cal-slot-action-host') : null;
                if (!btn || !host) {
                    return;
                }
                btn.disabled = !allowed;
                btn.style.pointerEvents = allowed ? '' : 'none';
                host.title = allowed ? '' : (reasonText || '');
            }

            function clearSchoolCalSlotTrialFieldErr() {
                const el = document.getElementById('schoolCalSlotTrialErr');
                if (el) {
                    el.textContent = '';
                    el.classList.add('d-none');
                }
                const $s = window.jQuery('#schoolCalSlotUserSelect');
                if ($s.length) {
                    $s.removeClass('is-invalid');
                    const c = $s.next('.select2-container');
                    if (c.length) {
                        c.find('.select2-selection').removeClass('is-invalid');
                    }
                }
            }

            function showSchoolCalSlotTrialFieldErr(msg) {
                const el = document.getElementById('schoolCalSlotTrialErr');
                if (el) {
                    el.textContent = msg || '';
                    el.classList.remove('d-none');
                }
                const $s = window.jQuery('#schoolCalSlotUserSelect');
                if ($s.length) {
                    $s.addClass('is-invalid');
                    const c = $s.next('.select2-container');
                    if (c.length) {
                        c.find('.select2-selection').addClass('is-invalid');
                    }
                }
            }

            function resetSlotModalUserPicker() {
                clearSchoolCalSlotTrialFieldErr();
                const loading = document.getElementById('schoolCalSlotBindActionsLoading');
                const err = document.getElementById('schoolCalSlotBindActionsError');
                const wrap = document.getElementById('schoolCalSlotBindButtonsWrap');
                if (loading) {
                    loading.classList.add('d-none');
                }
                if (err) {
                    err.classList.add('d-none');
                    err.textContent = '';
                }
                if (wrap) {
                    wrap.classList.add('d-none');
                }
                ['schoolCalOpenTrial', 'schoolCalOpenSingle', 'schoolCalOpenFlexible', 'schoolCalOpenFixed'].forEach(function (bid) {
                    setSlotBindActionButtonState(bid, false, '');
                });
                const $s = window.jQuery('#schoolCalSlotUserSelect');
                if ($s.length) {
                    $s.val(null).trigger('change');
                }
            }

            function applySlotUserBindActionsPayload(data) {
                const flex = data.flexible || {};
                const fixed = data.fixed || {};
                const single = data.single_lesson || {};
                const trial = data.trial || {};
                setSlotBindActionButtonState('schoolCalOpenFlexible', !!flex.allowed, flex.reason || '');
                setSlotBindActionButtonState('schoolCalOpenFixed', !!fixed.allowed, fixed.reason || '');
                setSlotBindActionButtonState('schoolCalOpenSingle', !!single.allowed, single.reason || '');
                setSlotBindActionButtonState('schoolCalOpenTrial', !!trial.allowed, trial.reason || '');
            }

            function scheduleFetchSlotUserBindActions() {
                if (schoolCalSlotBindFetchTimer) {
                    clearTimeout(schoolCalSlotBindFetchTimer);
                }
                schoolCalSlotBindFetchTimer = setTimeout(function () {
                    fetchSlotUserBindActions();
                }, SchoolCalSlotBindDebounceMs);
            }

            async function fetchSlotUserBindActions() {
                schoolCalSlotBindFetchTimer = null;
                const $ = window.jQuery;
                if (!$) {
                    return;
                }
                const uid = $('#schoolCalSlotUserSelect').val();
                const wrap = document.getElementById('schoolCalSlotBindButtonsWrap');
                const loading = document.getElementById('schoolCalSlotBindActionsLoading');
                const errEl = document.getElementById('schoolCalSlotBindActionsError');
                if (!uid || !selectedOccurrence) {
                    if (wrap) {
                        wrap.classList.add('d-none');
                    }
                    if (loading) {
                        loading.classList.add('d-none');
                    }
                    return;
                }
                if (wrap) {
                    wrap.classList.remove('d-none');
                }
                if (loading) {
                    loading.classList.remove('d-none');
                }
                if (errEl) {
                    errEl.classList.add('d-none');
                    errEl.textContent = '';
                }
                const url = routes.slotUserBindActions
                    + '?user_id=' + encodeURIComponent(uid)
                    + '&team_schedule_slot_id=' + encodeURIComponent(selectedOccurrence.id)
                    + '&occurrence_date=' + encodeURIComponent(selectedOccurrence.date);
                const res = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json().catch(function () { return {}; });
                if (loading) {
                    loading.classList.add('d-none');
                }
                if (!res.ok) {
                    const msg = data.message || 'Не удалось проверить доступные действия.';
                    if (errEl) {
                        errEl.textContent = msg;
                        errEl.classList.remove('d-none');
                    }
                    ['schoolCalOpenTrial', 'schoolCalOpenSingle', 'schoolCalOpenFlexible', 'schoolCalOpenFixed'].forEach(function (bid) {
                        setSlotBindActionButtonState(bid, false, msg);
                    });
                    return;
                }
                applySlotUserBindActionsPayload(data);
            }

            function syncUserSelectFromSlotTo($target) {
                const $ = window.jQuery;
                const $slot = $('#schoolCalSlotUserSelect');
                if (!$slot.length || !$target.length) {
                    return;
                }
                const dataArr = $slot.select2('data');
                if (!dataArr || !dataArr.length || !dataArr[0].id) {
                    return;
                }
                const id = String(dataArr[0].id);
                const text = dataArr[0].text || '';
                const hasOpt = $target.find('option').filter(function () { return String(this.value) === id; }).length > 0;
                if (!hasOpt) {
                    $target.append(new Option(text, id, true, true));
                }
                $target.val(id).trigger('change');
            }

            async function openSlotModal(ev) {
                selectedOccurrence = ev;
                const whenEl = document.getElementById('schoolCalSlotSummaryWhen');
                const teamEl = document.getElementById('schoolCalSlotSummaryTeam');
                const locEl = document.getElementById('schoolCalSlotSummaryLoc');
                if (whenEl) {
                    whenEl.textContent = formatSlotModalWhen(ev);
                }
                if (teamEl) {
                    teamEl.textContent = ev.team_title || '—';
                }
                if (locEl) {
                    locEl.textContent = ev.location_name || '—';
                }
                fillRegistrationsList(ev);
                schoolCalSlotRegisteredUserIds = (ev.registrations || []).map(function (r) {
                    return parseInt(r.user_id, 10);
                }).filter(function (n) { return n > 0; });
                resetSlotModalUserPicker();

                new bootstrap.Modal(document.getElementById('schoolCalSlotModal')).show();
            }

            document.getElementById('schoolCalSlotChangeLessonBtn')?.addEventListener('click', async function () {
                if (!selectedOccurrence || typeof window.openTeamScheduleSlotEdit !== 'function') return;
                bootstrap.Modal.getInstance(document.getElementById('schoolCalSlotModal'))?.hide();
                await window.openTeamScheduleSlotEdit(selectedOccurrence.id, {
                    applyChangesFrom: selectedOccurrence.date,
                    occurrenceMutationDate: selectedOccurrence.date,
                });
            });

            document.getElementById('schoolCalOpenFlexible')?.addEventListener('click', () => {
                const btn = document.getElementById('schoolCalOpenFlexible');
                if (btn && btn.disabled) {
                    return;
                }
                bootstrap.Modal.getInstance(document.getElementById('schoolCalSlotModal'))?.hide();
                if (!selectedOccurrence) return;
                document.getElementById('schoolCalFlexSlotId').value = selectedOccurrence.id;
                document.getElementById('schoolCalFlexDate').value = selectedOccurrence.date;
                document.getElementById('schoolCalFlexUlp').innerHTML = '<option value="">Сначала выберите ученика</option>';
                syncUserSelectFromSlotTo(window.jQuery('#schoolCalFlexUser'));
                new bootstrap.Modal(document.getElementById('schoolCalFlexModal')).show();
            });

            document.getElementById('schoolCalOpenFixed')?.addEventListener('click', () => {
                const btn = document.getElementById('schoolCalOpenFixed');
                if (btn && btn.disabled) {
                    return;
                }
                bootstrap.Modal.getInstance(document.getElementById('schoolCalSlotModal'))?.hide();
                if (!selectedOccurrence) return;
                document.getElementById('schoolCalFixedSlotId').value = selectedOccurrence.id;
                document.getElementById('schoolCalFixedAnchor').value = selectedOccurrence.date;
                const locPick = document.getElementById('schoolCalLocation');
                document.getElementById('schoolCalFixedLocation').value = locPick ? (locPick.value || '') : '';
                const ulpSel = document.getElementById('schoolCalFixedUlp');
                if (ulpSel) {
                    ulpSel.innerHTML = '<option value="">Сначала выберите ученика</option>';
                }
                syncUserSelectFromSlotTo(window.jQuery('#schoolCalFixedUser'));
                new bootstrap.Modal(document.getElementById('schoolCalFixedModal')).show();
            });

            document.getElementById('schoolCalOpenSingle')?.addEventListener('click', () => {
                const btn = document.getElementById('schoolCalOpenSingle');
                if (btn && btn.disabled) {
                    return;
                }
                bootstrap.Modal.getInstance(document.getElementById('schoolCalSlotModal'))?.hide();
                if (!selectedOccurrence) {
                    return;
                }
                document.getElementById('schoolCalSingleSlotId').value = selectedOccurrence.id;
                document.getElementById('schoolCalSingleDate').value = selectedOccurrence.date;
                document.getElementById('schoolCalSingleUlp').innerHTML = '<option value="">Сначала выберите ученика</option>';
                syncUserSelectFromSlotTo(window.jQuery('#schoolCalSingleUser'));
                new bootstrap.Modal(document.getElementById('schoolCalSingleModal')).show();
            });

            document.getElementById('schoolCalOpenTrial')?.addEventListener('click', async () => {
                const btn = document.getElementById('schoolCalOpenTrial');
                if (btn && btn.disabled) {
                    return;
                }
                if (!selectedOccurrence) {
                    return;
                }
                const $ = window.jQuery;
                if (!$) {
                    return;
                }
                clearSchoolCalSlotTrialFieldErr();
                const uid = $('#schoolCalSlotUserSelect').val();
                if (!uid) {
                    showSchoolCalSlotTrialFieldErr('Выберите ученика.');
                    return;
                }
                const fd = new FormData();
                fd.append('_token', token);
                fd.append('user_id', String(uid));
                fd.append('team_schedule_slot_id', String(selectedOccurrence.id));
                fd.append('occurrence_date', String(selectedOccurrence.date));
                btn.disabled = true;
                try {
                    const res = await fetch(routes.trialRegistrationStore, {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const err = data.errors || {};
                        if (err.user_id && err.user_id[0]) {
                            showSchoolCalSlotTrialFieldErr(err.user_id[0]);
                        }
                        let alerted = false;
                        if (data.message) {
                            showAlert('danger', data.message);
                            alerted = true;
                        }
                        if (!alerted) {
                            for (const k of Object.keys(err)) {
                                if (k === 'user_id') {
                                    continue;
                                }
                                if (err[k] && err[k][0]) {
                                    showAlert('danger', err[k][0]);
                                    break;
                                }
                            }
                        }
                        return;
                    }
                    showAlert('success', data.message || 'Готово');
                    bootstrap.Modal.getInstance(document.getElementById('schoolCalSlotModal'))?.hide();
                    loadWeek();
                } finally {
                    btn.disabled = false;
                }
            });

            document.getElementById('schoolCalPrevWeek')?.addEventListener('click', () => {
                weekMonday = addDays(weekMonday, -7);
                loadWeek();
            });
            document.getElementById('schoolCalNextWeek')?.addEventListener('click', () => {
                weekMonday = addDays(weekMonday, 7);
                loadWeek();
            });
            document.getElementById('schoolCalToday')?.addEventListener('click', () => {
                weekMonday = startOfWeekMonday(new Date());
                loadWeek();
            });
            document.getElementById('schoolCalLocation')?.addEventListener('change', loadWeek);

            function clearSchoolCalViewSettingsErrors() {
                const modalEl = document.getElementById('schoolCalViewSettingsModal');
                if (!modalEl) return;
                modalEl.querySelectorAll('[data-err]').forEach(function (el) {
                    el.textContent = '';
                    el.style.display = 'none';
                });
                document.getElementById('schoolCalViewStart')?.classList.remove('is-invalid');
                document.getElementById('schoolCalViewEnd')?.classList.remove('is-invalid');
            }

            function showSchoolCalViewSettingsErrors(errors) {
                clearSchoolCalViewSettingsErrors();
                if (!errors || typeof errors !== 'object') return;
                if (errors.view_start_min && errors.view_start_min[0]) {
                    const el = document.querySelector('[data-err="view_start_min"]');
                    if (el) {
                        el.textContent = errors.view_start_min[0];
                        el.style.display = 'block';
                    }
                    document.getElementById('schoolCalViewStart')?.classList.add('is-invalid');
                }
                if (errors.view_end_min && errors.view_end_min[0]) {
                    const el = document.querySelector('[data-err="view_end_min"]');
                    if (el) {
                        el.textContent = errors.view_end_min[0];
                        el.style.display = 'block';
                    }
                    document.getElementById('schoolCalViewEnd')?.classList.add('is-invalid');
                }
            }

            function syncSchoolCalViewSettingsModal() {
                const startSel = document.getElementById('schoolCalViewStart');
                const endSel = document.getElementById('schoolCalViewEnd');
                if (startSel) startSel.value = String(viewStartMin);
                if (endSel) endSel.value = String(viewEndMin);
            }

            function applySchoolCalViewRange(pair) {
                if (!pair || pair.view_start_min == null || pair.view_end_min == null) return;
                viewStartMin = pair.view_start_min;
                viewEndMin = pair.view_end_min;
                renderGrid(lastOccurrences);
            }

            document.getElementById('schoolCalViewSettingsModal')?.addEventListener('show.bs.modal', function () {
                clearSchoolCalViewSettingsErrors();
                syncSchoolCalViewSettingsModal();
            });

            document.getElementById('schoolCalViewSettingsReset')?.addEventListener('click', function () {
                const startSel = document.getElementById('schoolCalViewStart');
                const endSel = document.getElementById('schoolCalViewEnd');
                if (startSel) startSel.value = '540';
                if (endSel) endSel.value = '1260';
                clearSchoolCalViewSettingsErrors();
            });

            document.getElementById('schoolCalViewSettingsSave')?.addEventListener('click', async function () {
                clearSchoolCalViewSettingsErrors();
                const startSel = document.getElementById('schoolCalViewStart');
                const endSel = document.getElementById('schoolCalViewEnd');
                if (!startSel || !endSel) return;
                const res = await fetch(routes.viewSettingsSave, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({
                        view_start_min: parseInt(startSel.value, 10),
                        view_end_min: parseInt(endSel.value, 10),
                    }),
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok) {
                    if (data.errors) {
                        showSchoolCalViewSettingsErrors(data.errors);
                    } else {
                        showAlert('danger', data.message || 'Не удалось сохранить настройки.');
                    }
                    return;
                }
                applySchoolCalViewRange(data);
                const modalEl = document.getElementById('schoolCalViewSettingsModal');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getInstance(modalEl)?.hide();
                }
                showAlert('success', 'Настройки отображения сохранены.');
            });

            @can('scheduleSlots.manage')
            /** Открытие модалки создания слота с предзаполнением (локально, без глобала из partial слотов). */
            function schoolCalOpenSlotCreateModal(opts) {
                const form = document.getElementById('slotCreateForm');
                const modalEl = document.getElementById('slotCreateModal');
                if (!form || !modalEl || !window.bootstrap || !bootstrap.Modal) return;
                form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
                form.querySelectorAll('[data-error-for]').forEach(function (el) { el.textContent = ''; });
                const o = opts || {};
                if (o.weekday != null && o.weekday !== '') {
                    const wd = String(o.weekday);
                    const wHidden = form.querySelector('[name="weekday"]');
                    if (wHidden) wHidden.value = wd;
                    document.querySelectorAll('.js-slot-weekday-create').forEach(function (btn) {
                        const on = String(btn.getAttribute('data-weekday')) === wd;
                        btn.classList.toggle('btn-primary', on);
                        btn.classList.toggle('btn-outline-secondary', !on);
                    });
                }
                if (o.dateStart) {
                    const ds = form.querySelector('[name="date_start"]');
                    if (ds) ds.value = o.dateStart;
                }
                if (o.timeStart) {
                    const ts = form.querySelector('[name="time_start"]');
                    if (ts) ts.value = o.timeStart;
                }
                if (o.timeEnd) {
                    const te = form.querySelector('[name="time_end"]');
                    if (te) te.value = o.timeEnd;
                }
                if (o.locationId != null && o.locationId !== '') {
                    const sel = form.querySelector('[name="location_id"]');
                    if (sel) {
                        const v = String(o.locationId);
                        if ([].some.call(sel.options, function (opt) { return opt.value === v; })) {
                            sel.value = v;
                        }
                    }
                }
                const teamSel = form.querySelector('[name="team_id"]');
                if (teamSel) teamSel.value = '';
                form.querySelector('[name="date_start"]')?.dispatchEvent(new Event('change', { bubbles: true }));
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }

            document.getElementById('schoolCalGrid')?.addEventListener('click', function (e) {
                const grid = document.getElementById('schoolCalGrid');
                const col = e.target.closest('.school-cal__day-col--body');
                if (!grid || !col || !grid.contains(col)) return;
                if (e.target.closest('.school-cal__event')) return;
                const ymd = col.getAttribute('data-date');
                if (!ymd) return;
                const times = slotTimesFromColumnClick(e.clientY, col);
                const loc = document.getElementById('schoolCalLocation')?.value || '';
                schoolCalOpenSlotCreateModal({
                    weekday: isoWeekdayFromYmd(ymd),
                    dateStart: ymd,
                    timeStart: times.time_start,
                    timeEnd: times.time_end,
                    locationId: loc
                });
            });
            @endcan

            function clearFieldErrors(form) {
                form.querySelectorAll('[data-err]').forEach(e => e.textContent = '');
            }

            document.getElementById('schoolCalFlexSubmit')?.addEventListener('click', async () => {
                const form = document.getElementById('schoolCalFlexForm');
                clearFieldErrors(form);
                const fd = new FormData(form);
                const res = await fetch(routes.flexAssign, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const err = data.errors || {};
                    Object.keys(err).forEach(k => {
                        const n = form.querySelector('[data-err="' + k + '"]');
                        if (n) n.textContent = (err[k] && err[k][0]) ? err[k][0] : '';
                    });
                    if (data.message) showAlert('danger', data.message);
                    return;
                }
                showAlert('success', data.message || 'Готово');
                bootstrap.Modal.getInstance(document.getElementById('schoolCalFlexModal'))?.hide();
                loadWeek();
            });

            document.getElementById('schoolCalFixedSubmit')?.addEventListener('click', async () => {
                const form = document.getElementById('schoolCalFixedForm');
                clearFieldErrors(form);
                const fd = new FormData(form);
                const uid = window.jQuery('#schoolCalFixedUser').val();
                fd.set('user_id', uid);
                const res = await fetch(routes.fixedAssign, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const err = data.errors || {};
                    Object.keys(err).forEach(k => {
                        const n = form.querySelector('[data-err="' + k + '"]');
                        if (n) n.textContent = (err[k] && err[k][0]) ? err[k][0] : '';
                    });
                    if (data.message) showAlert('danger', data.message);
                    return;
                }
                showAlert('success', data.message || 'Готово');
                bootstrap.Modal.getInstance(document.getElementById('schoolCalFixedModal'))?.hide();
                loadWeek();
            });

            document.getElementById('schoolCalSingleSubmit')?.addEventListener('click', async () => {
                const form = document.getElementById('schoolCalSingleForm');
                clearFieldErrors(form);
                const fd = new FormData(form);
                const res = await fetch(routes.singleAssign, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const err = data.errors || {};
                    Object.keys(err).forEach(k => {
                        const n = form.querySelector('[data-err="' + k + '"]');
                        if (n) {
                            n.textContent = (err[k] && err[k][0]) ? err[k][0] : '';
                        }
                    });
                    if (data.message) {
                        showAlert('danger', data.message);
                    }
                    return;
                }
                showAlert('success', data.message || 'Готово');
                bootstrap.Modal.getInstance(document.getElementById('schoolCalSingleModal'))?.hide();
                loadWeek();
            });

            loadWeek();

            window.jQuery(document).ready(function ($) {
                if (!$.fn.select2) return;

                function bindUserSelect($el, searchUrl, onSelect) {
                    $el.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Поиск ученика',
                        allowClear: true,
                        ajax: {
                            url: searchUrl,
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                return { q: params.term || '' };
                            },
                            processResults: function (data) {
                                return data;
                            }
                        },
                        minimumInputLength: 0
                    });
                    $el.on('select2:select change', onSelect);
                }

                const $slotUser = $('#schoolCalSlotUserSelect');
                if ($slotUser.length && !$slotUser.data('select2')) {
                    $slotUser.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Имя, фамилия или телефон',
                        allowClear: false,
                        minimumInputLength: 0,
                        dropdownParent: $('#schoolCalSlotModal'),
                        ajax: {
                            url: routes.usersSearch,
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                return { q: params.term || '' };
                            },
                            processResults: function (data) {
                                const regs = schoolCalSlotRegisteredUserIds || [];
                                const results = (data.results || []).map(function (item) {
                                    const idNum = parseInt(item.id, 10);
                                    return {
                                        id: item.id,
                                        text: item.text,
                                        disabled: regs.indexOf(idNum) !== -1,
                                    };
                                });
                                return { results: results };
                            },
                        },
                    });
                    $slotUser.on('select2:select change', function () {
                        clearSchoolCalSlotTrialFieldErr();
                        const v = $slotUser.val();
                        const wrap = document.getElementById('schoolCalSlotBindButtonsWrap');
                        if (!v) {
                            if (wrap) {
                                wrap.classList.add('d-none');
                            }
                            document.getElementById('schoolCalSlotBindActionsError')?.classList.add('d-none');
                            return;
                        }
                        scheduleFetchSlotUserBindActions();
                    });
                }

                bindUserSelect($('#schoolCalFlexUser'), routes.flexUsersSearch, async function () {
                    const uid = $('#schoolCalFlexUser').val();
                    const ulp = document.getElementById('schoolCalFlexUlp');
                    ulp.innerHTML = '<option value="">Загрузка…</option>';
                    if (!uid) {
                        ulp.innerHTML = '<option value="">Сначала выберите ученика</option>';
                        return;
                    }
                    const res = await fetch(routes.flexUlps + '?user_id=' + encodeURIComponent(uid), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json().catch(() => ({}));
                    ulp.innerHTML = '<option value="">Выберите назначение</option>';
                    (data.assignments || []).forEach(function (a) {
                        const o = document.createElement('option');
                        o.value = a.id;
                        o.textContent = a.label;
                        ulp.appendChild(o);
                    });
                });

                bindUserSelect($('#schoolCalFixedUser'), routes.usersSearch, async function () {
                    const uid = $('#schoolCalFixedUser').val();
                    const ulp = document.getElementById('schoolCalFixedUlp');
                    if (!ulp) return;
                    ulp.innerHTML = '<option value="">Загрузка…</option>';
                    if (!uid) {
                        ulp.innerHTML = '<option value="">Сначала выберите ученика</option>';
                        return;
                    }
                    const res = await fetch(routes.fixedUlps + '?user_id=' + encodeURIComponent(uid), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json().catch(() => ({}));
                    ulp.innerHTML = '<option value="">Выберите назначение</option>';
                    (data.assignments || []).forEach(function (a) {
                        const o = document.createElement('option');
                        o.value = a.id;
                        o.textContent = a.label;
                        ulp.appendChild(o);
                    });
                });

                bindUserSelect($('#schoolCalSingleUser'), routes.singleUsersSearch, async function () {
                    const uid = $('#schoolCalSingleUser').val();
                    const ulp = document.getElementById('schoolCalSingleUlp');
                    if (!ulp) {
                        return;
                    }
                    ulp.innerHTML = '<option value="">Загрузка…</option>';
                    if (!uid) {
                        ulp.innerHTML = '<option value="">Сначала выберите ученика</option>';
                        return;
                    }
                    const res = await fetch(routes.singleUlps + '?user_id=' + encodeURIComponent(uid), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json().catch(() => ({}));
                    ulp.innerHTML = '<option value="">Выберите назначение</option>';
                    (data.assignments || []).forEach(function (a) {
                        const o = document.createElement('option');
                        o.value = a.id;
                        o.textContent = a.label;
                        ulp.appendChild(o);
                    });
                });

            });
        })();
    </script>
@endpush
