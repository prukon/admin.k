@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
            <h4 class="mb-0">Добавить абонемент</h4>
            <a href="{{ route('admin.lesson-packages.index') }}" class="btn btn-outline-secondary">
                Назад к списку
            </a>
        </div>

        <hr>

        <form method="POST" action="{{ route('admin.lesson-packages.store') }}" class="mt-3" novalidate>
            @csrf

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label">Название абонемента *</label>
                    <input type="text"
                           name="name"
                           value="{{ old('name') }}"
                           class="form-control @error('name') is-invalid @enderror"
                           maxlength="255"
                           required>
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Тип *</label>
                    <select name="schedule_type"
                            id="schedule_type"
                            class="form-select @error('schedule_type') is-invalid @enderror"
                            required>
                        @php
                            $type = old('schedule_type', 'fixed');
                        @endphp
                        <option value="fixed" {{ $type === 'fixed' ? 'selected' : '' }}>Фиксированное расписание</option>
                        <option value="flexible" {{ $type === 'flexible' ? 'selected' : '' }}>Гибкое расписание</option>
                        <option value="no_schedule" {{ $type === 'no_schedule' ? 'selected' : '' }}>Разовое занятие</option>
                    </select>
                    @error('schedule_type')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">Длительность (дни) *</label>
                    <input type="number"
                           id="duration_days"
                           name="duration_days"
                           value="{{ old('duration_days', 30) }}"
                           class="form-control @error('duration_days') is-invalid @enderror"
                           min="1"
                           max="3650"
                           required>
                    @error('duration_days')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">Кол-во занятий *</label>
                    <input type="number"
                           id="lessons_count"
                           name="lessons_count"
                           value="{{ old('lessons_count', 8) }}"
                           class="form-control @error('lessons_count') is-invalid @enderror"
                           min="1"
                           max="1000"
                           required>
                    @error('lessons_count')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">Стоимость (руб.) *</label>
                    <input type="number"
                           name="price"
                           value="{{ old('price', '0') }}"
                           class="form-control @error('price') is-invalid @enderror"
                           min="0"
                           max="99999999.99"
                           step="0.01"
                           required>
                    @error('price')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12" id="freeze_section">
                    <div class="form-check">
                        <input class="form-check-input @error('freeze_enabled') is-invalid @enderror"
                               type="checkbox"
                               value="1"
                               id="freeze_enabled"
                               name="freeze_enabled"
                            {{ old('freeze_enabled') ? 'checked' : '' }}>
                        <label class="form-check-label" for="freeze_enabled">
                            Разрешена заморозка
                        </label>
                    </div>
                    @error('freeze_enabled')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-4" id="freeze_days_wrap">
                    <label class="form-label">Кол-во дней заморозки</label>
                    <input type="number"
                           name="freeze_days"
                           value="{{ old('freeze_days', 7) }}"
                           class="form-control @error('freeze_days') is-invalid @enderror"
                           min="1"
                           max="3650">
                    @error('freeze_days')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <hr class="my-4" id="template_hr">

            <div id="time-slots-section">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0">Шаблон расписания (для фиксированного)</h5>
                    <button type="button" id="add-slot" class="btn btn-outline-primary btn-sm">
                        Добавить слот
                    </button>
                </div>

                @error('time_slots')
                <div class="text-danger mt-2">{{ $message }}</div>
                @enderror

                @php
                    $oldSlots = old('time_slots');
                    $slots = is_array($oldSlots) && count($oldSlots) ? $oldSlots : [ ['weekday' => 1, 'time_start' => '18:00', 'time_end' => '19:00'] ];
                @endphp

                <div class="table-responsive mt-3">
                    <table class="table table-bordered align-middle" id="slots-table">
                        <thead>
                        <tr>
                            <th style="width: 140px;">День</th>
                            <th style="width: 160px;">Начало</th>
                            <th style="width: 160px;">Окончание</th>
                            <th style="width: 80px;"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($slots as $i => $slot)
                            <tr>
                                <td>
                                    <select name="time_slots[{{ $i }}][weekday]"
                                            class="form-select @error("time_slots.$i.weekday") is-invalid @enderror">
                                        @foreach ($weekdays as $k => $label)
                                            <option value="{{ $k }}" {{ (int)($slot['weekday'] ?? 1) === (int)$k ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error("time_slots.$i.weekday")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td>
                                    <input type="time"
                                           name="time_slots[{{ $i }}][time_start]"
                                           value="{{ $slot['time_start'] ?? '' }}"
                                           class="form-control @error("time_slots.$i.time_start") is-invalid @enderror">
                                    @error("time_slots.$i.time_start")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td>
                                    <input type="time"
                                           name="time_slots[{{ $i }}][time_end]"
                                           value="{{ $slot['time_end'] ?? '' }}"
                                           class="form-control @error("time_slots.$i.time_end") is-invalid @enderror">
                                    @error("time_slots.$i.time_end")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-slot">
                                        ×
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    Сохранить
                </button>
                <a href="{{ route('admin.lesson-packages.index') }}" class="btn btn-outline-secondary">
                    Отмена
                </a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            const scheduleTypeEl = document.getElementById('schedule_type');
            const timeSlotsSection = document.getElementById('time-slots-section');
            const slotsTableBody = document.querySelector('#slots-table tbody');
            const addSlotBtn = document.getElementById('add-slot');

            const freezeEnabledEl = document.getElementById('freeze_enabled');
            const freezeDaysWrap = document.getElementById('freeze_days_wrap');
            const freezeSection = document.getElementById('freeze_section');
            const templateHr = document.getElementById('template_hr');
            const durationDaysEl = document.getElementById('duration_days');
            const lessonsCountEl = document.getElementById('lessons_count');
            let snapshotBeforeSingle = null;

            function toggleSlotsSection() {
                const type = scheduleTypeEl.value;
                const isFixed = type === 'fixed';
                timeSlotsSection.style.display = isFixed ? '' : 'none';
                if (templateHr) {
                    templateHr.style.display = isFixed ? '' : 'none';
                }
            }

            function toggleFreezeDays() {
                freezeDaysWrap.style.display = freezeEnabledEl.checked ? '' : 'none';
            }

            function applyScheduleTypeUi() {
                const type = scheduleTypeEl.value;
                if (type === 'no_schedule') {
                    snapshotBeforeSingle = {
                        duration: durationDaysEl ? durationDaysEl.value : '30',
                        lessons: lessonsCountEl ? lessonsCountEl.value : '8',
                    };
                    if (durationDaysEl) {
                        durationDaysEl.value = '1';
                        durationDaysEl.readOnly = true;
                    }
                    if (lessonsCountEl) {
                        lessonsCountEl.value = '1';
                        lessonsCountEl.readOnly = true;
                    }
                    if (freezeSection) {
                        freezeSection.style.display = 'none';
                    }
                    freezeEnabledEl.checked = false;
                    toggleFreezeDays();
                    if (templateHr) {
                        templateHr.style.display = 'none';
                    }
                    timeSlotsSection.style.display = 'none';
                    if (slotsTableBody) {
                        slotsTableBody.innerHTML = '';
                    }
                } else {
                    if (snapshotBeforeSingle) {
                        if (durationDaysEl) {
                            durationDaysEl.value = snapshotBeforeSingle.duration;
                        }
                        if (lessonsCountEl) {
                            lessonsCountEl.value = snapshotBeforeSingle.lessons;
                        }
                        snapshotBeforeSingle = null;
                    }
                    if (durationDaysEl) {
                        durationDaysEl.readOnly = false;
                    }
                    if (lessonsCountEl) {
                        lessonsCountEl.readOnly = false;
                    }
                    if (freezeSection) {
                        freezeSection.style.display = '';
                    }
                    toggleSlotsSection();
                    toggleFreezeDays();
                    if (type === 'fixed' && slotsTableBody && slotsTableBody.querySelectorAll('tr').length === 0) {
                        slotsTableBody.appendChild(buildSlotRow(0));
                    }
                }
            }

            function nextIndex() {
                const rows = slotsTableBody.querySelectorAll('tr');
                return rows.length;
            }

            function buildSlotRow(i) {
                const weekdays = @json($weekdays);

                const weekdayOptions = Object.keys(weekdays).map(function (k) {
                    return '<option value="' + k + '">' + weekdays[k] + '</option>';
                }).join('');

                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' +
                    '  <select name="time_slots[' + i + '][weekday]" class="form-select">' + weekdayOptions + '</select>' +
                    '</td>' +
                    '<td>' +
                    '  <input type="time" name="time_slots[' + i + '][time_start]" class="form-control" value="18:00">' +
                    '</td>' +
                    '<td>' +
                    '  <input type="time" name="time_slots[' + i + '][time_end]" class="form-control" value="19:00">' +
                    '</td>' +
                    '<td class="text-center">' +
                    '  <button type="button" class="btn btn-outline-danger btn-sm remove-slot">×</button>' +
                    '</td>';
                return tr;
            }

            function onRemoveClick(e) {
                const btn = e.target.closest('.remove-slot');
                if (!btn) return;
                const tr = btn.closest('tr');
                if (tr) tr.remove();
            }

            scheduleTypeEl.addEventListener('change', applyScheduleTypeUi);
            freezeEnabledEl.addEventListener('change', toggleFreezeDays);
            slotsTableBody.addEventListener('click', onRemoveClick);

            addSlotBtn.addEventListener('click', function () {
                const i = nextIndex();
                slotsTableBody.appendChild(buildSlotRow(i));
            });

            if (scheduleTypeEl.value === 'no_schedule') {
                snapshotBeforeSingle = null;
                if (durationDaysEl) {
                    durationDaysEl.readOnly = true;
                }
                if (lessonsCountEl) {
                    lessonsCountEl.readOnly = true;
                }
                if (freezeSection) {
                    freezeSection.style.display = 'none';
                }
                freezeEnabledEl.checked = false;
                toggleFreezeDays();
                if (templateHr) {
                    templateHr.style.display = 'none';
                }
                timeSlotsSection.style.display = 'none';
                if (slotsTableBody) {
                    slotsTableBody.innerHTML = '';
                }
            } else {
                toggleSlotsSection();
                toggleFreezeDays();
            }
        })();
    </script>
@endsection

