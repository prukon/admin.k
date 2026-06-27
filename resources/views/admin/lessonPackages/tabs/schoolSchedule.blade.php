{{--
  Расписание школы: недельная сетка времени (по умолчанию 09:00–21:00, настраивается в модалке), фильтр локации, назначение абонементов.
--}}
@php
    $weekLabels = $weekdays ?? [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];
@endphp

@once
    @vite(['resources/css/school-schedule-calendar.css'])
@endonce

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
                        <label class="school-cal__toolbar-k text-muted mb-0 text-nowrap" for="schoolCalLocation">Объект</label>
                        <select class="form-select form-select-sm flex-grow-1" id="schoolCalLocation" style="min-width: 8rem; max-width: 18rem">
                            @include('admin.lessonPackages.partials.locationFilterOptions', ['locationFilterSelected' => ''])
                        </select>
                    </div>
                @endcan
                <div class="d-flex align-items-center justify-content-md-end ms-md-auto">
                    <div class="wrap-icon btn"
                         data-bs-toggle="modal"
                         data-bs-target="#historyModal"
                         title="История изменений"
                         aria-label="История изменений">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>
                    <div class="wrap-icon btn ms-1"
                         data-bs-toggle="modal"
                         data-bs-target="#schoolCalViewSettingsModal"
                         title="Отображение календаря">
                        <i class="fa-solid fa-gear settings-icon"></i>
                    </div>
                    <div class="wrap-icon btn ms-1"
                         id="schoolCalFullscreenBtn"
                         title="Во весь экран"
                         aria-label="Во весь экран">
                        <i class="fas fa-expand"></i>
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
                    <h5 class="modal-title mb-0 text-break" id="schoolCalSlotModalTitle">—</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body pt-2">
                    <div class="school-cal-slot-summary rounded-3 border px-3 py-3 mb-3">
                        <div class="school-cal-slot-summary__when fw-semibold text-dark lh-sm" id="schoolCalSlotSummaryWhen">—</div>
                        @can('locations.view')
                        <div class="school-cal-slot-summary__meta small mt-3 pt-2 border-top border-light">
                            <div class="d-flex gap-2 justify-content-between">
                                <span class="text-muted flex-shrink-0">Объект</span>
                                <span class="text-end text-break" id="schoolCalSlotSummaryLoc">—</span>
                            </div>
                        </div>
                        @endcan
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
                                    <button type="button" class="btn btn-primary w-100 text-wrap" id="schoolCalOpenFlexible" disabled>Привязать гибкий абонемент</button>
                                </span>
                                <span class="d-inline-block school-cal-slot-action-host" title="">
                                    <button type="button" class="btn btn-primary w-100" id="schoolCalOpenFixed" disabled>Привязать фиксированный абонемент</button>
                                </span>
                            </div>
                            <div class="d-none mt-3 border-top pt-3" id="schoolCalSlotSingleFormWrap">
                                <div class="d-none mb-3" id="schoolCalSlotSingleBindFields">
                                    <label class="form-label small mb-1" for="schoolCalSlotSingleUlp">Назначение</label>
                                    <select class="form-select form-select-sm" id="schoolCalSlotSingleUlp" aria-describedby="schoolCalSlotSingleUlpErr"></select>
                                    <div class="small text-danger mt-1 d-none" id="schoolCalSlotSingleUlpErr" data-err="user_lesson_package_id" role="alert"></div>
                                </div>
                                <div class="d-none" id="schoolCalSlotSingleCreateFields">
                                    <div class="mb-3">
                                        <label class="form-label small mb-1" for="schoolCalSlotSingleTemplate">Шаблон абонемента</label>
                                        <select class="form-select form-select-sm" id="schoolCalSlotSingleTemplate" aria-describedby="schoolCalSlotSingleTemplateErr"></select>
                                        <div class="small text-danger mt-1 d-none" id="schoolCalSlotSingleTemplateErr" data-err="lesson_package_id" role="alert"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small mb-1" for="schoolCalSlotSingleFee">Стоимость, ₽</label>
                                        <input type="number" class="form-control form-control-sm" id="schoolCalSlotSingleFee" min="0" step="0.01" inputmode="decimal" aria-describedby="schoolCalSlotSingleFeeErr">
                                        <div class="small text-danger mt-1 d-none" id="schoolCalSlotSingleFeeErr" data-err="fee_amount" role="alert"></div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary w-100 btn-sm" id="schoolCalSlotSingleSubmit">Записать в расписание</button>
                            </div>
                            <div class="d-none mt-3 border-top pt-3" id="schoolCalSlotFlexFormWrap">
                                <div class="mb-3" id="schoolCalSlotFlexBindFields">
                                    <label class="form-label small mb-1" for="schoolCalSlotFlexUlp">Назначение абонемента</label>
                                    <select class="form-select form-select-sm" id="schoolCalSlotFlexUlp" aria-describedby="schoolCalSlotFlexUlpErr"></select>
                                    <div class="small text-danger mt-1 d-none" id="schoolCalSlotFlexUlpErr" data-err="user_lesson_package_id" role="alert"></div>
                                </div>
                                <button type="button" class="btn btn-primary w-100 btn-sm" id="schoolCalSlotFlexSubmit">Привязать абонемент</button>
                            </div>
                            <div class="d-none mt-3 border-top pt-3" id="schoolCalSlotFixedFormWrap">
                                <div class="d-none mb-3" id="schoolCalSlotFixedBindFields">
                                    <label class="form-label small mb-1" for="schoolCalSlotFixedUlp">Назначение абонемента</label>
                                    <select class="form-select form-select-sm" id="schoolCalSlotFixedUlp" aria-describedby="schoolCalSlotFixedUlpErr"></select>
                                    <div class="small text-danger mt-1 d-none" id="schoolCalSlotFixedUlpErr" data-err="user_lesson_package_id" role="alert"></div>
                                </div>
                                <div class="mb-3 border rounded-3 p-3 bg-light bg-opacity-50">
                                    <div class="fw-semibold small mb-2">Шаблон привязки</div>
                                    <div id="schoolCalFixedPatternsHost">
                                        <div class="school-cal-fixed-pattern-row border rounded p-2 mb-2 bg-white" data-pattern-row>
                                            <div class="row g-2 align-items-end">
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label small mb-1">День недели</label>
                                                    <select name="patterns[0][weekday]" class="form-select form-select-sm js-school-cal-fixed-weekday" required>
                                                        @foreach ($weekdays as $k => $label)
                                                            <option value="{{ $k }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <div class="small text-danger mt-1 d-none" data-err="patterns.0.weekday" role="alert"></div>
                                                </div>
                                                <div class="col-5 col-md-3">
                                                    <label class="form-label small mb-1">Начало</label>
                                                    <input type="time" name="patterns[0][time_start]" class="form-control form-control-sm js-school-cal-fixed-time-start" required>
                                                    <div class="small text-danger mt-1 d-none" data-err="patterns.0.time_start" role="alert"></div>
                                                </div>
                                                <div class="col-5 col-md-3">
                                                    <label class="form-label small mb-1">Окончание</label>
                                                    <input type="time" name="patterns[0][time_end]" class="form-control form-control-sm js-school-cal-fixed-time-end" required>
                                                    <div class="small text-danger mt-1 d-none" data-err="patterns.0.time_end" role="alert"></div>
                                                </div>
                                                <div class="col-2 col-md-2 text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary d-none mt-4" data-pattern-remove title="Удалить слот" aria-label="Удалить слот">×</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary w-100 mb-2" id="schoolCalFixedAddPattern">+ Добавить слот</button>
                                    <div class="small text-danger mt-1 d-none" data-err="patterns" role="alert"></div>
                                    <p class="small text-muted mb-0 mt-2">
                                        Первая строка по умолчанию совпадает со слотом, по которому вы открыли занятие; добавьте строки для остальных дней группы. Период абонемента и цепочка занятий строятся от якорной даты; для каждого выбранного дня и времени в периоде должно быть действующее занятие группы в расписании школы.
                                    </p>
                                </div>
                                <button type="button" class="btn btn-primary w-100 btn-sm" id="schoolCalSlotFixedSubmit">Привязать абонемент</button>
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

</div>

@can('scheduleSlots.manage')
    @include('admin.teamScheduleSlots.partials.slotModals')
@endcan

@push('scripts')
    <script>
        (function () {
            const routes = {
                slotUserBindActions: @json(route('admin.lesson-packages.school-schedule.slot-user-bind-actions')),
                week: @json(route('admin.lesson-packages.school-schedule.week')),
                usersSearch: @json(route('admin.lesson-packages.assignments.users-search')),
                flexAssign: @json(route('admin.lesson-packages.school-schedule.assign-flexible')),
                fixedAssign: @json(route('admin.lesson-packages.school-schedule.assign-fixed')),
                singleAssign: @json(route('admin.lesson-packages.school-schedule.assign-single-lesson')),
                singleUlps: @json(route('admin.lesson-packages.school-schedule.single-lesson-assignments')),
                singleUsersSearch: @json(route('admin.lesson-packages.school-schedule.single-lesson-users-search')),
                occurrenceStatusStore: @json(route('admin.lesson-packages.school-schedule.occurrence-status.store')),
                occurrenceStatusHistory: @json(route('admin.lesson-packages.school-schedule.occurrence-status.history')),
                trialRegistrationStore: @json(route('admin.lesson-packages.school-schedule.trial-registration.store')),
                trialRegistrationDestroy: @json(route('admin.lesson-packages.school-schedule.trial-registration.destroy', ['userTeamScheduleSlot' => 0])),
                singleLessonRegistrationStore: @json(route('admin.lesson-packages.school-schedule.single-lesson-registration.store')),
                singleLessonRegistrationDestroy: @json(route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', ['userTeamScheduleSlot' => 0])),
                viewSettingsSave: @json(route('admin.lesson-packages.school-schedule.view-settings.save')),
            };
            const viewSettingsInitial = @json($schoolScheduleViewSettings ?? ['view_start_min' => 540, 'view_end_min' => 1260]);
            const occurrenceStatuses = @json($schoolCalendarOccurrenceStatuses ?? []);
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const weekLabels = @json($weekLabels);
            const rootEl = document.querySelector('.school-cal');
            const fullscreenBtn = document.getElementById('schoolCalFullscreenBtn');
            const fullscreenBtnIcon = fullscreenBtn?.querySelector('i');

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

            /** Остаток / объём для подписи в модалке: «(2/3)» (абонемент или пробное). */
            function formatRegLessonBalance(reg) {
                if (!reg) {
                    return '';
                }
                const rem = reg.lessons_remaining;
                const tot = reg.lessons_total;
                if (rem == null || tot == null) {
                    return '';
                }
                return '(' + rem + '/' + tot + ')';
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

            function trialRegistrationDestroyUrl(id) {
                const base = String(routes.trialRegistrationDestroy || '');
                return base.replace(/\/0$/, '/' + String(id));
            }

            function singleLessonRegistrationDestroyUrl(id) {
                const base = String(routes.singleLessonRegistrationDestroy || '');
                return base.replace(/\/0$/, '/' + String(id));
            }

            let schoolCalSlotSinglePayload = null;
            let schoolCalSlotSingleFeeTouched = false;
            let schoolCalSlotFlexiblePayload = null;
            let schoolCalSlotFixedPayload = null;
            const schoolCalFlexibleButtonDefaultLabel = 'Привязать гибкий абонемент';
            const schoolCalFixedButtonDefaultLabel = 'Привязать фиксированный абонемент';

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
                                if (r.current_status && r.current_status.title) {
                                    var pc = escapeHtml(r.current_status.color || '#64748b');
                                    var pt = escapeHtml(r.current_status.title);
                                    html += '<span class="school-cal__reg-status-pill" style="--pill-bg:' + pc + '" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' + pt + '" aria-label="' + pt + '"></span>';
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

                // Подсказки статуса занятия
                var calGrid = document.getElementById('schoolCalGrid');
                if (window.KidsCrmTooltip && calGrid) {
                    KidsCrmTooltip.dispose(calGrid, { scopes: ['generic'] });
                    KidsCrmTooltip.init(calGrid, { scopes: ['generic'] });
                }
            }

            function calcFullscreenTopPx() {
                // Должны остаться видимыми вкладки (nav-tabs) и шапка контента, поэтому берём нижнюю границу ближайших вкладок.
                const tabs = document.querySelector('.nav-tabs');
                if (!tabs) return 0;
                const r = tabs.getBoundingClientRect();
                return Math.max(0, Math.round(r.bottom));
            }

            function setFullscreenTopVar() {
                if (!rootEl) return;
                rootEl.style.setProperty('--school-cal-fullscreen-top', calcFullscreenTopPx() + 'px');
            }

            // В некоторых layout'ах админки контейнеры имеют transform, из-за чего position:fixed
            // "прилипает" не к viewport. Поэтому в fullscreen переносим блок календаря в body.
            let fullscreenPlaceholder = null;

            function setFullscreenMode(enabled) {
                if (!rootEl) return;

                if (enabled) {
                    if (!fullscreenPlaceholder) {
                        fullscreenPlaceholder = document.createComment('school-cal-fullscreen-placeholder');
                    }
                    if (rootEl.parentNode) {
                        rootEl.parentNode.insertBefore(fullscreenPlaceholder, rootEl);
                    }
                    document.body.appendChild(rootEl);
                    rootEl.style.setProperty('--school-cal-fullscreen-top', '0px');
                } else {
                    if (fullscreenPlaceholder && fullscreenPlaceholder.parentNode) {
                        fullscreenPlaceholder.parentNode.insertBefore(rootEl, fullscreenPlaceholder);
                        fullscreenPlaceholder.parentNode.removeChild(fullscreenPlaceholder);
                    }
                }

                // fullscreen overlay starts at top of viewport
                rootEl.classList.toggle('school-cal--fullscreen', !!enabled);
                document.body.classList.toggle('no-scroll', !!enabled);
                if (fullscreenBtnIcon) {
                    fullscreenBtnIcon.className = enabled ? 'fas fa-compress' : 'fas fa-expand';
                }
            }

            // Toggle fullscreen
            fullscreenBtn?.addEventListener('click', function (e) {
                e.preventDefault();
                const enabled = rootEl?.classList.contains('school-cal--fullscreen');
                setFullscreenMode(!enabled);
            });

            window.addEventListener('resize', function () {
                if (!rootEl?.classList.contains('school-cal--fullscreen')) return;
                setFullscreenTopVar();
            });

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
                        row.className = 'min-w-0';
                        const oneline = document.createElement('div');
                        oneline.className = 'school-cal__reg-card__oneline w-100 min-w-0';
                        const fio = document.createElement('span');
                        fio.className = 'school-cal__reg-card__fio text-truncate';
                        fio.textContent = (r.user_label || '').trim() || '—';
                        const trialBalanceSpan = document.createElement('span');
                        trialBalanceSpan.className = 'text-muted flex-shrink-0 small ms-1 school-cal__reg-balance';
                        trialBalanceSpan.textContent = formatRegLessonBalance(r);
                        const kind = document.createElement('span');
                        kind.className = 'school-cal__reg-card__pkg text-truncate ms-2';
                        kind.textContent = 'Пробное занятие';
                        oneline.appendChild(fio);
                        oneline.appendChild(trialBalanceSpan);
                        oneline.appendChild(kind);

                        const cancel = document.createElement('a');
                        cancel.href = '#';
                        cancel.className = 'small text-decoration-none flex-shrink-0';
                        cancel.textContent = '(Отменить)';
                        cancel.style.marginLeft = '0.25rem';
                        oneline.appendChild(cancel);

                        row.appendChild(oneline);
                        li.appendChild(row);

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
                            if (data.trial_registration) {
                                r.lessons_remaining = data.trial_registration.lessons_remaining;
                                r.lessons_total = data.trial_registration.lessons_total;
                                trialBalanceSpan.textContent = formatRegLessonBalance(r);
                            }
                            showAlert('success', data.message || 'Статус сохранён.');
                            loadWeek();
                        });

                        cancel.addEventListener('click', async function (e) {
                            e.preventDefault();
                            errDiv.textContent = '';
                            errDiv.style.display = 'none';

                            const bindId = r.user_team_schedule_slot_id || r.id;
                            if (!bindId) {
                                errDiv.textContent = 'Не удалось определить запись для отмены.';
                                errDiv.style.display = 'block';
                                return;
                            }
                            if (!confirm('Отменить пробное занятие?')) {
                                return;
                            }

                            cancel.style.pointerEvents = 'none';
                            cancel.style.opacity = '0.6';
                            try {
                                const fd = new FormData();
                                fd.append('_token', token);
                                fd.append('_method', 'DELETE');
                                const url = trialRegistrationDestroyUrl(bindId);
                                const res = await fetch(url, {
                                    method: 'POST',
                                    body: fd,
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json',
                                    }
                                });
                                const data = await res.json().catch(function () { return {}; });
                                if (!res.ok) {
                                    const msg = (data && data.message) ? data.message : 'Не удалось отменить пробное занятие.';
                                    errDiv.textContent = msg;
                                    errDiv.style.display = 'block';
                                    showAlert('danger', msg);
                                    return;
                                }
                                showAlert('success', data.message || 'Готово');
                                bootstrap.Modal.getInstance(document.getElementById('schoolCalSlotModal'))?.hide();
                                loadWeek();
                            } finally {
                                cancel.style.pointerEvents = '';
                                cancel.style.opacity = '';
                            }
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
                    const balanceSpan = document.createElement('span');
                    balanceSpan.className = 'text-muted flex-shrink-0 small ms-1 school-cal__reg-balance';
                    balanceSpan.textContent = formatRegLessonBalance(r);
                    const kindEl = document.createElement('span');
                    kindEl.className = 'school-cal__reg-card__pkg text-truncate ms-2';
                    kindEl.textContent = registrationPackageDisplayName(r);
                    head.appendChild(nameEl);
                    head.appendChild(balanceSpan);
                    head.appendChild(kindEl);

                    const isSingleLesson = !!r.is_single_lesson || r.schedule_type === 'no_schedule';
                    let singleCancel = null;
                    if (isSingleLesson) {
                        singleCancel = document.createElement('a');
                        singleCancel.href = '#';
                        singleCancel.className = 'small text-decoration-none flex-shrink-0';
                        singleCancel.textContent = '(Отменить)';
                        singleCancel.style.marginLeft = '0.25rem';
                        head.appendChild(singleCancel);
                    }

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
                        if (data.user_lesson_package) {
                            r.lessons_remaining = data.user_lesson_package.lessons_remaining;
                            r.lessons_total = data.user_lesson_package.lessons_total;
                            balanceSpan.textContent = formatRegLessonBalance(r);
                        }
                        showAlert('success', data.message || 'Статус сохранён.');
                        loadWeek();
                    });

                    if (singleCancel) {
                        singleCancel.addEventListener('click', async function (e) {
                            e.preventDefault();
                            errDiv.textContent = '';
                            errDiv.style.display = 'none';

                            const bindId = r.user_team_schedule_slot_id || r.id;
                            if (!bindId) {
                                errDiv.textContent = 'Не удалось определить запись для отмены.';
                                errDiv.style.display = 'block';
                                return;
                            }
                            if (!confirm('Отменить запись разового занятия? Назначение абонемента сохранится.')) {
                                return;
                            }

                            singleCancel.style.pointerEvents = 'none';
                            singleCancel.style.opacity = '0.6';
                            try {
                                const fd = new FormData();
                                fd.append('_token', token);
                                fd.append('_method', 'DELETE');
                                const url = singleLessonRegistrationDestroyUrl(bindId);
                                const res = await fetch(url, {
                                    method: 'POST',
                                    body: fd,
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json',
                                    }
                                });
                                const data = await res.json().catch(function () { return {}; });
                                if (!res.ok) {
                                    const msg = (data && data.message) ? data.message : 'Не удалось отменить запись разового занятия.';
                                    errDiv.textContent = msg;
                                    errDiv.style.display = 'block';
                                    showAlert('danger', msg);
                                    return;
                                }
                                showAlert('success', data.message || 'Готово');
                                bootstrap.Modal.getInstance(document.getElementById('schoolCalSlotModal'))?.hide();
                                loadWeek();
                            } finally {
                                singleCancel.style.pointerEvents = '';
                                singleCancel.style.opacity = '';
                            }
                        });
                    }

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

            function clearSchoolCalSlotSingleFieldErrs() {
                const wrap = document.getElementById('schoolCalSlotSingleFormWrap');
                if (!wrap) {
                    return;
                }
                wrap.querySelectorAll('[data-err]').forEach(function (el) {
                    el.textContent = '';
                    el.classList.add('d-none');
                });
                document.getElementById('schoolCalSlotSingleUlp')?.classList.remove('is-invalid');
                document.getElementById('schoolCalSlotSingleTemplate')?.classList.remove('is-invalid');
                document.getElementById('schoolCalSlotSingleFee')?.classList.remove('is-invalid');
            }

            function showSchoolCalSlotSingleFieldErrs(errors) {
                clearSchoolCalSlotSingleFieldErrs();
                if (!errors || typeof errors !== 'object') {
                    return;
                }
                Object.keys(errors).forEach(function (key) {
                    const msg = errors[key] && errors[key][0] ? errors[key][0] : '';
                    if (!msg) {
                        return;
                    }
                    const el = document.querySelector('#schoolCalSlotSingleFormWrap [data-err="' + key + '"]');
                    if (el) {
                        el.textContent = msg;
                        el.classList.remove('d-none');
                    }
                    if (key === 'user_lesson_package_id') {
                        document.getElementById('schoolCalSlotSingleUlp')?.classList.add('is-invalid');
                    }
                    if (key === 'lesson_package_id') {
                        document.getElementById('schoolCalSlotSingleTemplate')?.classList.add('is-invalid');
                    }
                    if (key === 'fee_amount') {
                        document.getElementById('schoolCalSlotSingleFee')?.classList.add('is-invalid');
                    }
                    if (key === 'user_id') {
                        showSchoolCalSlotTrialFieldErr(msg);
                    }
                });
            }

            function resetSchoolCalSlotSingleForm() {
                schoolCalSlotSinglePayload = null;
                schoolCalSlotSingleFeeTouched = false;
                clearSchoolCalSlotSingleFieldErrs();
                const formWrap = document.getElementById('schoolCalSlotSingleFormWrap');
                const bindFields = document.getElementById('schoolCalSlotSingleBindFields');
                const createFields = document.getElementById('schoolCalSlotSingleCreateFields');
                const ulpSel = document.getElementById('schoolCalSlotSingleUlp');
                const tplSel = document.getElementById('schoolCalSlotSingleTemplate');
                const feeInp = document.getElementById('schoolCalSlotSingleFee');
                if (formWrap) {
                    formWrap.classList.add('d-none');
                }
                if (bindFields) {
                    bindFields.classList.add('d-none');
                }
                if (createFields) {
                    createFields.classList.add('d-none');
                }
                if (ulpSel) {
                    ulpSel.innerHTML = '';
                }
                if (tplSel) {
                    tplSel.innerHTML = '';
                }
                if (feeInp) {
                    feeInp.value = '';
                }
            }

            function populateSchoolCalSlotSingleForm(single) {
                schoolCalSlotSinglePayload = single || null;
                schoolCalSlotSingleFeeTouched = false;
                clearSchoolCalSlotSingleFieldErrs();
                const formWrap = document.getElementById('schoolCalSlotSingleFormWrap');
                const bindFields = document.getElementById('schoolCalSlotSingleBindFields');
                const createFields = document.getElementById('schoolCalSlotSingleCreateFields');
                const ulpSel = document.getElementById('schoolCalSlotSingleUlp');
                const tplSel = document.getElementById('schoolCalSlotSingleTemplate');
                const feeInp = document.getElementById('schoolCalSlotSingleFee');
                if (formWrap) {
                    formWrap.classList.add('d-none');
                }
                if (!single || !single.allowed) {
                    if (bindFields) {
                        bindFields.classList.add('d-none');
                    }
                    if (createFields) {
                        createFields.classList.add('d-none');
                    }
                    return;
                }
                const mode = single.mode || '';
                const existing = single.existing_assignments || [];
                const templates = single.templates || [];
                if (mode === 'bind_existing' && existing.length > 1 && ulpSel && bindFields) {
                    ulpSel.innerHTML = existing.map(function (item) {
                        return '<option value="' + String(item.id) + '">' + escapeHtml(item.label || ('#' + item.id)) + '</option>';
                    }).join('');
                    bindFields.classList.remove('d-none');
                    if (createFields) {
                        createFields.classList.add('d-none');
                    }
                } else if (mode === 'create_new' && tplSel && feeInp && createFields) {
                    tplSel.innerHTML = templates.map(function (item) {
                        return '<option value="' + String(item.id) + '" data-fee-default="' + String(item.fee_amount_default ?? '') + '">' + escapeHtml(item.label || ('#' + item.id)) + '</option>';
                    }).join('');
                    const first = templates[0];
                    if (first) {
                        feeInp.value = first.fee_amount_default != null ? String(first.fee_amount_default) : '';
                    }
                    createFields.classList.remove('d-none');
                    if (bindFields) {
                        bindFields.classList.add('d-none');
                    }
                } else {
                    if (bindFields) {
                        bindFields.classList.add('d-none');
                    }
                    if (createFields) {
                        createFields.classList.add('d-none');
                    }
                }
            }

            function schoolCalSlotSingleNeedsForm() {
                const single = schoolCalSlotSinglePayload || {};
                const mode = single.mode || '';
                const existing = single.existing_assignments || [];
                const templates = single.templates || [];
                if (mode === 'bind_existing') {
                    return existing.length > 1;
                }
                if (mode === 'create_new') {
                    return templates.length > 0;
                }
                return false;
            }

            function showSchoolCalSlotSingleFormIfNeeded() {
                const formWrap = document.getElementById('schoolCalSlotSingleFormWrap');
                if (formWrap && schoolCalSlotSingleNeedsForm()) {
                    resetSchoolCalSlotFlexibleForm();
                    hideSchoolCalSlotFixedForm();
                    formWrap.classList.remove('d-none');
                }
            }

            function clearSchoolCalSlotFlexibleFieldErrs() {
                const wrap = document.getElementById('schoolCalSlotFlexFormWrap');
                if (!wrap) {
                    return;
                }
                wrap.querySelectorAll('[data-err]').forEach(function (el) {
                    el.textContent = '';
                    el.classList.add('d-none');
                });
                document.getElementById('schoolCalSlotFlexUlp')?.classList.remove('is-invalid');
            }

            function showSchoolCalSlotFlexibleFieldErrs(errors) {
                clearSchoolCalSlotFlexibleFieldErrs();
                if (!errors || typeof errors !== 'object') {
                    return;
                }
                Object.keys(errors).forEach(function (key) {
                    const msg = errors[key] && errors[key][0] ? errors[key][0] : '';
                    if (!msg) {
                        return;
                    }
                    const el = document.querySelector('#schoolCalSlotFlexFormWrap [data-err="' + key + '"]');
                    if (el) {
                        el.textContent = msg;
                        el.classList.remove('d-none');
                    }
                    if (key === 'user_lesson_package_id') {
                        document.getElementById('schoolCalSlotFlexUlp')?.classList.add('is-invalid');
                    }
                });
            }

            function resetSchoolCalSlotFlexibleForm() {
                schoolCalSlotFlexiblePayload = null;
                clearSchoolCalSlotFlexibleFieldErrs();
                const formWrap = document.getElementById('schoolCalSlotFlexFormWrap');
                const ulpSel = document.getElementById('schoolCalSlotFlexUlp');
                if (formWrap) {
                    formWrap.classList.add('d-none');
                }
                if (ulpSel) {
                    ulpSel.innerHTML = '';
                }
            }

            function populateSchoolCalSlotFlexibleForm(flex) {
                schoolCalSlotFlexiblePayload = flex || null;
                clearSchoolCalSlotFlexibleFieldErrs();
                const formWrap = document.getElementById('schoolCalSlotFlexFormWrap');
                const ulpSel = document.getElementById('schoolCalSlotFlexUlp');
                if (formWrap) {
                    formWrap.classList.add('d-none');
                }
                if (!flex || !flex.allowed) {
                    return;
                }
                const existing = flex.existing_assignments || [];
                if (existing.length > 1 && ulpSel) {
                    ulpSel.innerHTML = existing.map(function (item) {
                        return '<option value="' + String(item.id) + '">' + escapeHtml(item.label || ('#' + item.id)) + '</option>';
                    }).join('');
                }
            }

            function schoolCalSlotFlexibleNeedsForm() {
                const flex = schoolCalSlotFlexiblePayload || {};
                const existing = flex.existing_assignments || [];
                return !!flex.allowed && existing.length > 1;
            }

            function showSchoolCalSlotFlexibleFormIfNeeded() {
                const formWrap = document.getElementById('schoolCalSlotFlexFormWrap');
                if (formWrap && schoolCalSlotFlexibleNeedsForm()) {
                    resetSchoolCalSlotSingleForm();
                    hideSchoolCalSlotFixedForm();
                    formWrap.classList.remove('d-none');
                }
            }

            function clearSchoolCalSlotFixedFieldErrs() {
                const wrap = document.getElementById('schoolCalSlotFixedFormWrap');
                if (!wrap) {
                    return;
                }
                wrap.querySelectorAll('[data-err]').forEach(function (el) {
                    el.textContent = '';
                    el.classList.add('d-none');
                });
                document.getElementById('schoolCalSlotFixedUlp')?.classList.remove('is-invalid');
            }

            function showSchoolCalSlotFixedFieldErrs(errors) {
                clearSchoolCalSlotFixedFieldErrs();
                if (!errors || typeof errors !== 'object') {
                    return;
                }
                Object.keys(errors).forEach(function (key) {
                    const msg = errors[key] && errors[key][0] ? errors[key][0] : '';
                    if (!msg) {
                        return;
                    }
                    const esc = key.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                    const el = document.querySelector('#schoolCalSlotFixedFormWrap [data-err="' + esc + '"]');
                    if (el) {
                        el.textContent = msg;
                        el.classList.remove('d-none');
                    }
                    if (key === 'user_lesson_package_id') {
                        document.getElementById('schoolCalSlotFixedUlp')?.classList.add('is-invalid');
                    }
                });
            }

            function hideSchoolCalSlotFixedForm() {
                clearSchoolCalSlotFixedFieldErrs();
                const formWrap = document.getElementById('schoolCalSlotFixedFormWrap');
                const bindFields = document.getElementById('schoolCalSlotFixedBindFields');
                if (formWrap) {
                    formWrap.classList.add('d-none');
                }
                if (bindFields) {
                    bindFields.classList.add('d-none');
                }
                schoolCalFixedResetPatternRows();
            }

            function resetSchoolCalSlotFixedForm() {
                schoolCalSlotFixedPayload = null;
                hideSchoolCalSlotFixedForm();
                const ulpSel = document.getElementById('schoolCalSlotFixedUlp');
                if (ulpSel) {
                    ulpSel.innerHTML = '';
                }
            }

            function populateSchoolCalSlotFixedForm(fixed) {
                schoolCalSlotFixedPayload = fixed || null;
                clearSchoolCalSlotFixedFieldErrs();
                const formWrap = document.getElementById('schoolCalSlotFixedFormWrap');
                const ulpSel = document.getElementById('schoolCalSlotFixedUlp');
                const bindFields = document.getElementById('schoolCalSlotFixedBindFields');
                if (formWrap) {
                    formWrap.classList.add('d-none');
                }
                if (bindFields) {
                    bindFields.classList.add('d-none');
                }
                if (!fixed || !fixed.allowed) {
                    return;
                }
                const existing = fixed.existing_assignments || [];
                if (existing.length > 1 && ulpSel) {
                    ulpSel.innerHTML = existing.map(function (item) {
                        return '<option value="' + String(item.id) + '">' + escapeHtml(item.label || ('#' + item.id)) + '</option>';
                    }).join('');
                } else if (ulpSel) {
                    ulpSel.innerHTML = '';
                }
            }

            function seedSchoolCalSlotFixedPatternsFromOccurrence() {
                schoolCalFixedResetPatternRows();
                if (!selectedOccurrence) {
                    return;
                }
                const firstRow = document.querySelector('#schoolCalFixedPatternsHost [data-pattern-row]');
                if (!firstRow) {
                    return;
                }
                const wEl = firstRow.querySelector('.js-school-cal-fixed-weekday');
                const tsEl = firstRow.querySelector('.js-school-cal-fixed-time-start');
                const teEl = firstRow.querySelector('.js-school-cal-fixed-time-end');
                if (wEl && selectedOccurrence.weekday) {
                    wEl.value = String(selectedOccurrence.weekday);
                }
                if (tsEl && selectedOccurrence.time_start) {
                    tsEl.value = String(selectedOccurrence.time_start).slice(0, 5);
                }
                if (teEl && selectedOccurrence.time_end) {
                    teEl.value = String(selectedOccurrence.time_end).slice(0, 5);
                }
            }

            function showSchoolCalSlotFixedForm() {
                const fixed = schoolCalSlotFixedPayload || {};
                if (!fixed.allowed) {
                    return;
                }
                resetSchoolCalSlotSingleForm();
                resetSchoolCalSlotFlexibleForm();
                clearSchoolCalSlotFixedFieldErrs();
                seedSchoolCalSlotFixedPatternsFromOccurrence();
                const bindFields = document.getElementById('schoolCalSlotFixedBindFields');
                const existing = fixed.existing_assignments || [];
                if (bindFields) {
                    if (existing.length > 1) {
                        bindFields.classList.remove('d-none');
                    } else {
                        bindFields.classList.add('d-none');
                    }
                }
                const formWrap = document.getElementById('schoolCalSlotFixedFormWrap');
                if (formWrap) {
                    formWrap.classList.remove('d-none');
                }
            }

            function appendSchoolCalSlotFixedPatternsToFormData(fd) {
                const host = document.getElementById('schoolCalFixedPatternsHost');
                if (!host) {
                    return;
                }
                host.querySelectorAll('[data-pattern-row]').forEach(function (row, i) {
                    const w = row.querySelector('.js-school-cal-fixed-weekday')?.value || '';
                    const ts = row.querySelector('.js-school-cal-fixed-time-start')?.value || '';
                    const te = row.querySelector('.js-school-cal-fixed-time-end')?.value || '';
                    fd.append('patterns[' + i + '][weekday]', w);
                    fd.append('patterns[' + i + '][time_start]', ts);
                    fd.append('patterns[' + i + '][time_end]', te);
                });
            }

            function applyFixedBindButtonState(fixed) {
                const btn = document.getElementById('schoolCalOpenFixed');
                if (!btn) {
                    return;
                }
                const existing = fixed && fixed.existing_assignments ? fixed.existing_assignments : [];
                if (fixed && fixed.allowed && existing.length === 1) {
                    const assignmentLabel = existing[0].label || ('#' + existing[0].id);
                    btn.innerHTML = escapeHtml(schoolCalFixedButtonDefaultLabel) + '<br>' + escapeHtml(assignmentLabel);
                } else {
                    btn.textContent = schoolCalFixedButtonDefaultLabel;
                }
                setSlotBindActionButtonState('schoolCalOpenFixed', !!(fixed && fixed.allowed), (fixed && fixed.reason) || '');
            }

            async function submitSchoolCalSlotFixedRegistration() {
                const $ = window.jQuery;
                if (!$ || !selectedOccurrence) {
                    return;
                }
                clearSchoolCalSlotFixedFieldErrs();
                const fixed = schoolCalSlotFixedPayload || {};
                const existing = fixed.existing_assignments || [];
                if (!fixed.allowed || existing.length < 1) {
                    showAlert('danger', 'Фиксированный абонемент недоступен для выбранного ученика.');
                    return;
                }
                const uid = $('#schoolCalSlotUserSelect').val();
                if (!uid) {
                    showSchoolCalSlotTrialFieldErr('Выберите ученика.');
                    return;
                }
                let ulpId = existing.length === 1
                    ? String(existing[0].id)
                    : String(document.getElementById('schoolCalSlotFixedUlp')?.value || '');
                if (!ulpId) {
                    showSchoolCalSlotFixedFieldErrs({ user_lesson_package_id: ['Выберите назначение абонемента.'] });
                    showSchoolCalSlotFixedForm();
                    return;
                }
                const locPick = document.getElementById('schoolCalLocation');
                const fd = new FormData();
                fd.append('_token', token);
                fd.append('user_id', String(uid));
                fd.append('user_lesson_package_id', ulpId);
                fd.append('team_schedule_slot_id', String(selectedOccurrence.id));
                fd.append('anchor_date', String(selectedOccurrence.date));
                fd.append('location_id', locPick ? (locPick.value || '') : '');
                appendSchoolCalSlotFixedPatternsToFormData(fd);
                const submitBtn = document.getElementById('schoolCalSlotFixedSubmit');
                const openBtn = document.getElementById('schoolCalOpenFixed');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                if (openBtn) {
                    openBtn.disabled = true;
                }
                try {
                    const res = await fetch(routes.fixedAssign, {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json().catch(function () { return {}; });
                    if (!res.ok) {
                        const err = data.errors || {};
                        showSchoolCalSlotFixedFieldErrs(err);
                        if (data.message) {
                            showAlert('danger', data.message);
                        } else if (!Object.keys(err).length) {
                            showAlert('danger', 'Не удалось привязать фиксированный абонемент.');
                        }
                        showSchoolCalSlotFixedForm();
                        return;
                    }
                    showAlert('success', data.message || 'Готово');
                    bootstrap.Modal.getInstance(document.getElementById('schoolCalSlotModal'))?.hide();
                    loadWeek();
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    if (openBtn && schoolCalSlotFixedPayload && schoolCalSlotFixedPayload.allowed) {
                        openBtn.disabled = false;
                    }
                }
            }

            function applyFlexibleBindButtonState(flex) {
                const btn = document.getElementById('schoolCalOpenFlexible');
                if (!btn) {
                    return;
                }
                const existing = flex && flex.existing_assignments ? flex.existing_assignments : [];
                if (flex && flex.allowed && existing.length === 1) {
                    const assignmentLabel = existing[0].label || ('#' + existing[0].id);
                    btn.innerHTML = escapeHtml(schoolCalFlexibleButtonDefaultLabel) + '<br>' + escapeHtml(assignmentLabel);
                } else {
                    btn.textContent = schoolCalFlexibleButtonDefaultLabel;
                }
                setSlotBindActionButtonState('schoolCalOpenFlexible', !!(flex && flex.allowed), (flex && flex.reason) || '');
            }

            async function submitSchoolCalSlotFlexibleRegistration() {
                if (!selectedOccurrence) {
                    return;
                }
                clearSchoolCalSlotFlexibleFieldErrs();
                const flex = schoolCalSlotFlexiblePayload || {};
                const existing = flex.existing_assignments || [];
                if (!flex.allowed || existing.length < 1) {
                    showAlert('danger', 'Гибкий абонемент недоступен для выбранного ученика.');
                    return;
                }
                let ulpId = existing.length === 1
                    ? String(existing[0].id)
                    : String(document.getElementById('schoolCalSlotFlexUlp')?.value || '');
                if (!ulpId) {
                    showSchoolCalSlotFlexibleFieldErrs({ user_lesson_package_id: ['Выберите назначение абонемента.'] });
                    showSchoolCalSlotFlexibleFormIfNeeded();
                    return;
                }
                const fd = new FormData();
                fd.append('_token', token);
                fd.append('user_lesson_package_id', ulpId);
                fd.append('team_schedule_slot_id', String(selectedOccurrence.id));
                fd.append('occurrence_date', String(selectedOccurrence.date));
                const submitBtn = document.getElementById('schoolCalSlotFlexSubmit');
                const openBtn = document.getElementById('schoolCalOpenFlexible');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                if (openBtn) {
                    openBtn.disabled = true;
                }
                try {
                    const res = await fetch(routes.flexAssign, {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json().catch(function () { return {}; });
                    if (!res.ok) {
                        const err = data.errors || {};
                        showSchoolCalSlotFlexibleFieldErrs(err);
                        if (data.message) {
                            showAlert('danger', data.message);
                        } else if (!Object.keys(err).length) {
                            showAlert('danger', 'Не удалось привязать гибкий абонемент.');
                        }
                        showSchoolCalSlotFlexibleFormIfNeeded();
                        return;
                    }
                    showAlert('success', data.message || 'Готово');
                    bootstrap.Modal.getInstance(document.getElementById('schoolCalSlotModal'))?.hide();
                    loadWeek();
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    if (openBtn && schoolCalSlotFlexiblePayload && schoolCalSlotFlexiblePayload.allowed) {
                        openBtn.disabled = false;
                    }
                }
            }

            async function submitSchoolCalSlotSingleRegistration() {
                const $ = window.jQuery;
                if (!$ || !selectedOccurrence) {
                    return;
                }
                clearSchoolCalSlotTrialFieldErr();
                clearSchoolCalSlotSingleFieldErrs();
                const uid = $('#schoolCalSlotUserSelect').val();
                if (!uid) {
                    showSchoolCalSlotTrialFieldErr('Выберите ученика.');
                    return;
                }
                const single = schoolCalSlotSinglePayload || {};
                const fd = new FormData();
                fd.append('_token', token);
                fd.append('user_id', String(uid));
                fd.append('team_schedule_slot_id', String(selectedOccurrence.id));
                fd.append('occurrence_date', String(selectedOccurrence.date));
                const mode = single.mode || '';
                const existing = single.existing_assignments || [];
                if (mode === 'bind_existing') {
                    let ulpId = existing.length === 1 ? String(existing[0].id) : String(document.getElementById('schoolCalSlotSingleUlp')?.value || '');
                    if (!ulpId) {
                        showSchoolCalSlotSingleFieldErrs({ user_lesson_package_id: ['Выберите назначение.'] });
                        showSchoolCalSlotSingleFormIfNeeded();
                        return;
                    }
                    fd.append('user_lesson_package_id', ulpId);
                } else if (mode === 'create_new') {
                    const tplId = document.getElementById('schoolCalSlotSingleTemplate')?.value || '';
                    const feeVal = document.getElementById('schoolCalSlotSingleFee')?.value ?? '';
                    if (!tplId) {
                        showSchoolCalSlotSingleFieldErrs({ lesson_package_id: ['Выберите шаблон абонемента.'] });
                        showSchoolCalSlotSingleFormIfNeeded();
                        return;
                    }
                    if (feeVal === '') {
                        showSchoolCalSlotSingleFieldErrs({ fee_amount: ['Укажите стоимость разового занятия.'] });
                        showSchoolCalSlotSingleFormIfNeeded();
                        return;
                    }
                    fd.append('lesson_package_id', String(tplId));
                    fd.append('fee_amount', String(feeVal));
                } else {
                    showAlert('danger', 'Разовое занятие недоступно для выбранного ученика.');
                    return;
                }
                const submitBtn = document.getElementById('schoolCalSlotSingleSubmit');
                const openBtn = document.getElementById('schoolCalOpenSingle');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                if (openBtn) {
                    openBtn.disabled = true;
                }
                try {
                    const res = await fetch(routes.singleLessonRegistrationStore, {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json().catch(function () { return {}; });
                    if (!res.ok) {
                        const err = data.errors || {};
                        showSchoolCalSlotSingleFieldErrs(err);
                        if (data.message) {
                            showAlert('danger', data.message);
                        } else if (!Object.keys(err).length) {
                            showAlert('danger', 'Не удалось записать разовое занятие.');
                        }
                        showSchoolCalSlotSingleFormIfNeeded();
                        return;
                    }
                    showAlert('success', data.message || 'Готово');
                    bootstrap.Modal.getInstance(document.getElementById('schoolCalSlotModal'))?.hide();
                    loadWeek();
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    if (openBtn && schoolCalSlotSinglePayload && schoolCalSlotSinglePayload.allowed) {
                        openBtn.disabled = false;
                    }
                }
            }

            function resetSlotModalUserPicker() {
                clearSchoolCalSlotTrialFieldErr();
                resetSchoolCalSlotSingleForm();
                resetSchoolCalSlotFlexibleForm();
                resetSchoolCalSlotFixedForm();
                const flexBtn = document.getElementById('schoolCalOpenFlexible');
                if (flexBtn) {
                    flexBtn.textContent = schoolCalFlexibleButtonDefaultLabel;
                }
                const fixedBtn = document.getElementById('schoolCalOpenFixed');
                if (fixedBtn) {
                    fixedBtn.textContent = schoolCalFixedButtonDefaultLabel;
                }
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
                applyFlexibleBindButtonState(flex);
                applyFixedBindButtonState(fixed);
                setSlotBindActionButtonState('schoolCalOpenSingle', !!single.allowed, single.reason || '');
                setSlotBindActionButtonState('schoolCalOpenTrial', !!trial.allowed, trial.reason || '');
                populateSchoolCalSlotSingleForm(single);
                populateSchoolCalSlotFlexibleForm(flex);
                populateSchoolCalSlotFixedForm(fixed);
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
                const titleEl = document.getElementById('schoolCalSlotModalTitle');
                const whenEl = document.getElementById('schoolCalSlotSummaryWhen');
                const locEl = document.getElementById('schoolCalSlotSummaryLoc');
                if (titleEl) {
                    titleEl.textContent = ev.team_title || '—';
                }
                if (whenEl) {
                    whenEl.textContent = formatSlotModalWhen(ev);
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

            document.getElementById('schoolCalOpenFlexible')?.addEventListener('click', async function () {
                const btn = document.getElementById('schoolCalOpenFlexible');
                if (btn && btn.disabled) {
                    return;
                }
                if (!selectedOccurrence) {
                    return;
                }
                if (schoolCalSlotFlexibleNeedsForm()) {
                    hideSchoolCalSlotFixedForm();
                    showSchoolCalSlotFlexibleFormIfNeeded();
                    return;
                }
                await submitSchoolCalSlotFlexibleRegistration();
            });

            document.getElementById('schoolCalSlotFlexSubmit')?.addEventListener('click', function () {
                submitSchoolCalSlotFlexibleRegistration();
            });

            function schoolCalFixedRenumberPatternRows() {
                const host = document.getElementById('schoolCalFixedPatternsHost');
                if (!host) return;
                const rows = host.querySelectorAll('[data-pattern-row]');
                rows.forEach(function (row, i) {
                    row.querySelectorAll('input[name^="patterns["], select[name^="patterns["]').forEach(function (el) {
                        const nm = el.getAttribute('name');
                        if (nm) {
                            el.setAttribute('name', nm.replace(/patterns\[\d+]/, 'patterns[' + i + ']'));
                        }
                    });
                    row.querySelectorAll('[data-err]').forEach(function (el) {
                        const d = el.getAttribute('data-err');
                        if (d && d.indexOf('patterns.') === 0 && /^patterns\.\d+\./.test(d)) {
                            el.setAttribute('data-err', d.replace(/^patterns\.\d+\./, 'patterns.' + i + '.'));
                        }
                    });
                    const rm = row.querySelector('[data-pattern-remove]');
                    if (rm) {
                        rm.classList.toggle('d-none', rows.length <= 1);
                    }
                });
            }

            function schoolCalFixedResetPatternRows() {
                const host = document.getElementById('schoolCalFixedPatternsHost');
                if (!host) return;
                while (host.querySelectorAll('[data-pattern-row]').length > 1) {
                    const rows = host.querySelectorAll('[data-pattern-row]');
                    rows[rows.length - 1].remove();
                }
                schoolCalFixedRenumberPatternRows();
            }

            document.getElementById('schoolCalFixedAddPattern')?.addEventListener('click', function () {
                const host = document.getElementById('schoolCalFixedPatternsHost');
                const first = host?.querySelector('[data-pattern-row]');
                if (!host || !first) return;
                const clone = first.cloneNode(true);
                clone.querySelectorAll('input[type="time"]').forEach(function (inp) { inp.value = ''; });
                host.appendChild(clone);
                schoolCalFixedRenumberPatternRows();
            });

            document.getElementById('schoolCalFixedPatternsHost')?.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-pattern-remove]');
                if (!btn || btn.classList.contains('d-none')) return;
                const row = btn.closest('[data-pattern-row]');
                const host = document.getElementById('schoolCalFixedPatternsHost');
                if (!row || !host || host.querySelectorAll('[data-pattern-row]').length <= 1) return;
                row.remove();
                schoolCalFixedRenumberPatternRows();
            });

            document.getElementById('schoolCalOpenFixed')?.addEventListener('click', function () {
                const btn = document.getElementById('schoolCalOpenFixed');
                if (btn && btn.disabled) {
                    return;
                }
                if (!selectedOccurrence) {
                    return;
                }
                showSchoolCalSlotFixedForm();
            });

            document.getElementById('schoolCalSlotFixedSubmit')?.addEventListener('click', function () {
                submitSchoolCalSlotFixedRegistration();
            });

            document.getElementById('schoolCalOpenSingle')?.addEventListener('click', async function () {
                const btn = document.getElementById('schoolCalOpenSingle');
                if (btn && btn.disabled) {
                    return;
                }
                if (!selectedOccurrence) {
                    return;
                }
                if (schoolCalSlotSingleNeedsForm()) {
                    resetSchoolCalSlotFlexibleForm();
                    hideSchoolCalSlotFixedForm();
                    showSchoolCalSlotSingleFormIfNeeded();
                    return;
                }
                await submitSchoolCalSlotSingleRegistration();
            });

            document.getElementById('schoolCalSlotSingleSubmit')?.addEventListener('click', function () {
                submitSchoolCalSlotSingleRegistration();
            });

            document.getElementById('schoolCalSlotSingleTemplate')?.addEventListener('change', function () {
                if (schoolCalSlotSingleFeeTouched) {
                    return;
                }
                const opt = this.options[this.selectedIndex];
                const feeInp = document.getElementById('schoolCalSlotSingleFee');
                if (!opt || !feeInp) {
                    return;
                }
                const def = opt.getAttribute('data-fee-default');
                feeInp.value = def != null ? String(def) : '';
            });

            document.getElementById('schoolCalSlotSingleFee')?.addEventListener('input', function () {
                schoolCalSlotSingleFeeTouched = true;
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
            document.getElementById('schoolCalLocation')?.addEventListener('change', function () {
                loadWeek();
                const createForm = document.getElementById('slotCreateForm');
                const editForm = document.getElementById('slotEditForm');
                if (typeof window.applySlotFormTeamFilter === 'function') {
                    window.applySlotFormTeamFilter(createForm);
                    window.applySlotFormTeamFilter(editForm);
                }
            });

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
                const locSel = form.querySelector('[name="location_id"]');
                if (locSel) {
                    let v = '';
                    if (o.locationId != null && o.locationId !== '') {
                        v = String(o.locationId);
                    } else {
                        const toolbarLoc = document.getElementById('schoolCalLocation');
                        v = toolbarLoc ? toolbarLoc.value : '';
                    }
                    if ([].some.call(locSel.options, function (opt) { return opt.value === v; })) {
                        locSel.value = v;
                    } else {
                        locSel.value = '';
                    }
                }
                if (typeof window.applySlotFormTeamFilter === 'function') {
                    window.applySlotFormTeamFilter(form);
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
                        language: @include('partials.select2.ru'),
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
                        language: @include('partials.select2.ru'),
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

    @include('includes.logModal')

    <script>
        document.getElementById('historyModal')?.addEventListener('show.bs.modal', function () {
            showLogModal(@json(route('logs.data.school-schedule')));
        });
    </script>
@endpush
