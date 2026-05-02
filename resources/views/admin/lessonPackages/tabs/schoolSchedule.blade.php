{{--
  Расписание школы: недельная сетка 09:00–21:00, фильтр локации, назначение абонементов.
--}}
@php
    $weekLabels = $weekdays ?? [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];
@endphp

<div class="school-cal">
    <div class="school-cal__hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                {{-- <h4 class="school-cal__title mb-1">Расписание школы</h4> --}}
                {{-- <p class="school-cal__subtitle mb-0 text-muted">
                    Недельный вид по локации: занятия команд и привязка абонементов к слотам расписания.
                </p> --}}
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @can('scheduleSlots.manage')
                    <a href="{{ route('admin.team-schedule-slots.index') }}" class="btn btn-outline-secondary btn-sm">
                        Таблица занятий
                    </a>
                @endcan
            </div>
        </div>
    </div>

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
                    <button type="button" class="btn btn-link btn-sm py-0 px-1 text-nowrap" id="schoolCalToday">Сегодня</button>
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
            </div>
        </div>
    </div>

    <div id="schoolCalAlert" class="alert d-none" role="alert"></div>

    <div class="school-cal__grid-wrap card border-0 shadow-sm overflow-hidden">
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
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0 align-items-start">
                    <div class="flex-grow-1 me-2">
                        <h5 class="modal-title mb-0">Слот расписания</h5>
                        <div class="small text-muted" id="schoolCalSlotModalMeta"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-2">
                    <dl class="row mb-2 small mb-md-3">
                        <dt class="col-4 text-muted">Группа</dt>
                        <dd class="col-8" id="schoolCalSlotTeam">—</dd>
                        <dt class="col-4 text-muted">Локация</dt>
                        <dd class="col-8" id="schoolCalSlotLocation">—</dd>
                        <dt class="col-4 text-muted">Время</dt>
                        <dd class="col-8" id="schoolCalSlotTime">—</dd>
                    </dl>
                    <div class="mb-3 d-none" id="schoolCalSlotRegistrationsWrap">
                        <div class="text-muted text-uppercase small mb-1" style="font-size:0.68rem;letter-spacing:0.06em">Записаны</div>
                        <ul class="list-unstyled mb-0 small" id="schoolCalSlotRegistrationsList"></ul>
                    </div>
                    <hr>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="schoolCalOpenFlexible">
                            Привязать гибкий абонемент
                        </button>
                        <button type="button" class="btn btn-primary" id="schoolCalOpenFixed">
                            Привязать фиксированный абонемент
                        </button>
                        @can('scheduleSlots.manage')
                            <button type="button" class="btn btn-outline-danger" id="schoolCalSlotChangeLessonBtn">
                                Изменить занятие
                            </button>
                        @endcan
                    </div>
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

    {{-- Фиксированное назначение --}}
    <div class="modal fade" id="schoolCalFixedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
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
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Ученик</label>
                                <select class="form-select" id="schoolCalFixedUser" style="width:100%" required></select>
                                <div class="invalid-feedback d-block" data-err="user_id"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Назначение абонемента</label>
                                <select class="form-select" name="user_lesson_package_id" id="schoolCalFixedUlp" required>
                                    <option value="">Сначала выберите ученика</option>
                                </select>
                                <div class="invalid-feedback d-block" data-err="user_lesson_package_id"></div>
                            </div>
                        </div>
                        <p class="small text-muted mt-3 mb-0">
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
        margin-top: 4px;
        font-size: 10px;
        line-height: 1.25;
        color: #334155;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 2px 6px;
        max-height: 2.5em;
        overflow: hidden;
    }
    .school-cal__reg-preview .school-cal__reg-name:not(:last-child)::after {
        content: ',';
        margin-right: 2px;
        color: #94a3b8;
    }
    .school-cal__reg-ellipsis {
        color: #64748b;
        font-weight: 600;
    }
    .school-cal__corner {
        border-bottom: 1px solid rgba(148,163,184,.35);
        background: #f8fafc;
    }
</style>

@push('scripts')
    <script>
        (function () {
            const routes = {
                week: @json(route('admin.lesson-packages.school-schedule.week')),
                usersSearch: @json(route('admin.lesson-packages.assignments.users-search')),
                flexAssign: @json(route('admin.lesson-packages.school-schedule.assign-flexible')),
                fixedAssign: @json(route('admin.lesson-packages.school-schedule.assign-fixed')),
                flexUlps: @json(route('admin.lesson-packages.school-schedule.flexible-assignments')),
                fixedUlps: @json(route('admin.lesson-packages.school-schedule.fixed-assignments')),
                flexUsersSearch: @json(route('admin.lesson-packages.school-schedule.flexible-users-search')),
            };
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const weekLabels = @json($weekLabels);

            const START_MIN = 9 * 60;
            const END_MIN = 21 * 60;
            const TOTAL_MIN = END_MIN - START_MIN;
            const SLOT_PX = 40;
            const GRID_HEIGHT_PX = (TOTAL_MIN / 30) * SLOT_PX;

            let weekMonday = startOfWeekMonday(new Date());
            let selectedOccurrence = null;

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
                let slotStart = START_MIN + ratio * TOTAL_MIN;
                let snapped = Math.floor(slotStart / 30) * 30;
                if (snapped < START_MIN) snapped = START_MIN;
                if (snapped > END_MIN - 30) snapped = END_MIN - 30;
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

                renderGrid(data.occurrences || []);
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

                html += '<div class="school-cal__time-col d-flex flex-column border-end" style="height:' + GRID_HEIGHT_PX + 'px">';
                for (let m = START_MIN; m < END_MIN; m += 30) {
                    const hh = Math.floor(m / 60);
                    const mm = m % 60;
                    html += '<div class="school-cal__time-label" style="min-height:' + SLOT_PX + 'px;height:' + SLOT_PX + 'px">' + pad(hh) + ':' + pad(mm) + '</div>';
                }
                html += '</div>';

                days.forEach(d => {
                    const ymd = formatYmd(d);
                    const isToday = ymd === today;
                    html += '<div class="school-cal__day-col school-cal__day-col--body' + (isToday ? ' school-cal__day-col--today' : '') + '" ';
                    html += 'style="min-height:' + GRID_HEIGHT_PX + 'px;height:' + GRID_HEIGHT_PX + 'px" data-date="' + ymd + '">';
                    const list = byDate[ymd] || [];
                    list.forEach(ev => {
                        const start = minutesFromMidnight(ev.time_start);
                        const end = minutesFromMidnight(ev.time_end);
                        const top = ((start - START_MIN) / TOTAL_MIN) * 100;
                        const h = Math.max(8, ((end - start) / TOTAL_MIN) * 100);
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
                                html += '<span class="school-cal__reg-name">' + escapeHtml(r.user_label || '') + '</span>';
                            });
                            if (regs.length > 3) {
                                html += '<span class="school-cal__reg-ellipsis">…</span>';
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
                    li.textContent = r.line || r.user_label || '';
                    ul.appendChild(li);
                });
            }

            function openSlotModal(ev) {
                selectedOccurrence = ev;
                document.getElementById('schoolCalSlotTeam').textContent = ev.team_title || '—';
                document.getElementById('schoolCalSlotLocation').textContent = ev.location_name || '—';
                document.getElementById('schoolCalSlotTime').textContent = ev.time_start + '–' + ev.time_end;
                document.getElementById('schoolCalSlotModalMeta').textContent = formatRuLongDate(parseYmd(ev.date));
                fillRegistrationsList(ev);
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
                bootstrap.Modal.getInstance(document.getElementById('schoolCalSlotModal'))?.hide();
                if (!selectedOccurrence) return;
                document.getElementById('schoolCalFlexSlotId').value = selectedOccurrence.id;
                document.getElementById('schoolCalFlexDate').value = selectedOccurrence.date;
                document.getElementById('schoolCalFlexUlp').innerHTML = '<option value="">Сначала выберите ученика</option>';
                new bootstrap.Modal(document.getElementById('schoolCalFlexModal')).show();
            });

            document.getElementById('schoolCalOpenFixed')?.addEventListener('click', () => {
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
                new bootstrap.Modal(document.getElementById('schoolCalFixedModal')).show();
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
            });
        })();
    </script>
@endpush
