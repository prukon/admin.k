<div class="tab-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
        <h4 class="mb-0">Абонементы</h4>

        @can('lessonPackages.manage')
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#lessonPackageCreateModal">
                Добавить абонемент
            </button>
        @endcan
    </div>

    <hr>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle w-100">
            <thead>
            <tr>
                <th>#</th>
                <th>Название</th>
                <th>Тип</th>
                <th>Длительность (дни)</th>
                <th>Занятий</th>
                <th>Стоимость</th>
                <th>Заморозка</th>
                <th>Расписание</th>
                @can('lessonPackages.manage')
                    <th style="width: 140px;">Действия</th>
                @endcan
            </tr>
            </thead>
            <tbody>
            @forelse ($packages as $package)
                <tr>
                    <td class="text-center">{{ $package->id }}</td>
                    <td>{{ $package->name }}</td>
                    <td>
                        @if ($package->schedule_type === 'fixed')
                            Фиксированное
                        @elseif($package->schedule_type === 'flexible')
                            Гибкое
                        @else
                            Без расписания
                        @endif
                    </td>
                    <td class="text-center">{{ $package->duration_days }}</td>
                    <td class="text-center">{{ $package->lessons_count }}</td>
                    <td class="text-end">
                        {{ number_format($package->price_cents / 100, 2, ',', ' ') }} ₽
                    </td>
                    <td class="text-center">
                        @if ($package->freeze_enabled)
                            {{ $package->freeze_days }}
                        @else
                            нет
                        @endif
                    </td>
                    <td>
                        @if ($package->schedule_type !== 'fixed')
                            <span class="text-muted">—</span>
                        @else
                            @php
                                $weekdayMap = [1=>'Пн',2=>'Вт',3=>'Ср',4=>'Чт',5=>'Пт',6=>'Сб',7=>'Вс'];
                            @endphp
                            @if ($package->timeSlots->count() === 0)
                                <span class="text-muted">—</span>
                            @else
                                <div class="d-flex flex-column gap-1">
                                    @foreach ($package->timeSlots as $slot)
                                        <div>
                                            <span class="badge bg-secondary">{{ $weekdayMap[$slot->weekday] ?? $slot->weekday }}</span>
                                            {{ substr((string)$slot->time_start, 0, 5) }}–{{ substr((string)$slot->time_end, 0, 5) }}
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </td>
                    @can('lessonPackages.manage')
                        <td class="text-end">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary lesson-package-edit-btn"
                                    data-id="{{ $package->id }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#lessonPackageEditModal">
                                Редактировать
                            </button>
                        </td>
                    @endcan
                </tr>
            @empty
                <tr>
                    <td colspan="@can('lessonPackages.manage') 9 @else 8 @endcan" class="text-center text-muted">
                        Абонементов пока нет.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center">
        {{ $packages->links() }}
    </div>
</div>

@can('lessonPackages.manage')
    {{-- Create Modal --}}
    <div class="modal fade" id="lessonPackageCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить абонемент</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form id="lessonPackageCreateForm" novalidate>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Название *</label>
                                <input type="text" name="create[name]" class="form-control" maxlength="255" required>
                                <div class="invalid-feedback d-none" data-error-for="create[name]"></div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Тип *</label>
                                <select name="create[schedule_type]" id="create_schedule_type" class="form-select" required>
                                    <option value="fixed">Фиксированное расписание</option>
                                    <option value="flexible">Гибкое расписание</option>
                                    <option value="no_schedule">Без расписания</option>
                                </select>
                                <div class="invalid-feedback d-none" data-error-for="create[schedule_type]"></div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Длительность (дни) *</label>
                                <input type="number" name="create[duration_days]" class="form-control" min="1" max="3650" value="30" required>
                                <div class="invalid-feedback d-none" data-error-for="create[duration_days]"></div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Занятий *</label>
                                <input type="number" name="create[lessons_count]" class="form-control" min="1" max="1000" value="8" required>
                                <div class="invalid-feedback d-none" data-error-for="create[lessons_count]"></div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Стоимость (руб.) *</label>
                                <input type="number" name="create[price]" class="form-control" min="0" max="99999999.99" step="0.01" value="0" required>
                                <div class="invalid-feedback d-none" data-error-for="create[price]"></div>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="create_freeze_enabled" name="create[freeze_enabled]">
                                    <label class="form-check-label" for="create_freeze_enabled">Разрешена заморозка</label>
                                </div>
                                <div class="invalid-feedback d-none" data-error-for="create[freeze_enabled]"></div>
                            </div>

                            <div class="col-12 col-md-4" id="create_freeze_days_wrap">
                                <label class="form-label">Дней заморозки</label>
                                <input type="number" name="create[freeze_days]" class="form-control" min="1" max="3650" value="7">
                                <div class="invalid-feedback d-none" data-error-for="create[freeze_days]"></div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div id="create-time-slots-section">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <h6 class="mb-0">Шаблон расписания (для фиксированного)</h6>
                                <button type="button" id="create-add-slot" class="btn btn-outline-primary btn-sm">Добавить слот</button>
                            </div>
                            <div class="text-danger mt-2 d-none" data-error-for="create[time_slots]"></div>

                            <div class="table-responsive mt-3">
                                <table class="table table-bordered align-middle" id="create-slots-table">
                                    <thead>
                                    <tr>
                                        <th style="width: 140px;">День</th>
                                        <th style="width: 160px;">Начало</th>
                                        <th style="width: 160px;">Окончание</th>
                                        <th style="width: 80px;"></th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div class="modal fade" id="lessonPackageEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Редактировать абонемент</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form id="lessonPackageEditForm" novalidate>
                        <input type="hidden" id="edit_id" value="">

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Название *</label>
                                <input type="text" name="edit[name]" class="form-control" maxlength="255" required>
                                <div class="invalid-feedback d-none" data-error-for="edit[name]"></div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Тип *</label>
                                <select name="edit[schedule_type]" id="edit_schedule_type" class="form-select" required>
                                    <option value="fixed">Фиксированное расписание</option>
                                    <option value="flexible">Гибкое расписание</option>
                                    <option value="no_schedule">Без расписания</option>
                                </select>
                                <div class="invalid-feedback d-none" data-error-for="edit[schedule_type]"></div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Длительность (дни) *</label>
                                <input type="number" name="edit[duration_days]" class="form-control" min="1" max="3650" required>
                                <div class="invalid-feedback d-none" data-error-for="edit[duration_days]"></div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Занятий *</label>
                                <input type="number" name="edit[lessons_count]" class="form-control" min="1" max="1000" required>
                                <div class="invalid-feedback d-none" data-error-for="edit[lessons_count]"></div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Стоимость (руб.) *</label>
                                <input type="number" name="edit[price]" class="form-control" min="0" max="99999999.99" step="0.01" required>
                                <div class="invalid-feedback d-none" data-error-for="edit[price]"></div>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="edit_freeze_enabled" name="edit[freeze_enabled]">
                                    <label class="form-check-label" for="edit_freeze_enabled">Разрешена заморозка</label>
                                </div>
                                <div class="invalid-feedback d-none" data-error-for="edit[freeze_enabled]"></div>
                            </div>

                            <div class="col-12 col-md-4" id="edit_freeze_days_wrap">
                                <label class="form-label">Дней заморозки</label>
                                <input type="number" name="edit[freeze_days]" class="form-control" min="1" max="3650">
                                <div class="invalid-feedback d-none" data-error-for="edit[freeze_days]"></div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div id="edit-time-slots-section">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <h6 class="mb-0">Шаблон расписания (для фиксированного)</h6>
                                <button type="button" id="edit-add-slot" class="btn btn-outline-primary btn-sm">Добавить слот</button>
                            </div>
                            <div class="text-danger mt-2 d-none" data-error-for="edit[time_slots]"></div>

                            <div class="table-responsive mt-3">
                                <table class="table table-bordered align-middle" id="edit-slots-table">
                                    <thead>
                                    <tr>
                                        <th style="width: 140px;">День</th>
                                        <th style="width: 160px;">Начало</th>
                                        <th style="width: 160px;">Окончание</th>
                                        <th style="width: 80px;"></th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endcan

@can('lessonPackages.manage')
    @section('scripts')
        @parent
        <script>
            (function () {
                const weekdays = @json($weekdays);
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                function clearErrors(modalEl) {
                    modalEl.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                    modalEl.querySelectorAll('[data-error-for]').forEach(el => { el.textContent = ''; el.classList.add('d-none'); });
                }

                function setFieldError(modalEl, name, message) {
                    const field = modalEl.querySelector('[name="' + CSS.escape(name) + '"]');
                    if (field) {
                        field.classList.add('is-invalid');
                    }

                    const err = modalEl.querySelector('[data-error-for="' + name + '"]');
                    if (err) {
                        err.textContent = message;
                        err.classList.remove('d-none');
                    }
                }

                function buildSlotRow(prefix, i, slot) {
                    const weekdayOptions = Object.keys(weekdays).map(function (k) {
                        const selected = (parseInt(slot.weekday || 1, 10) === parseInt(k, 10)) ? 'selected' : '';
                        return '<option value="' + k + '" ' + selected + '>' + weekdays[k] + '</option>';
                    }).join('');

                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' +
                        '  <select name="' + prefix + '[time_slots][' + i + '][weekday]" class="form-select">' + weekdayOptions + '</select>' +
                        '  <div class="invalid-feedback d-none" data-error-for="' + prefix + '[time_slots][' + i + '][weekday]"></div>' +
                        '</td>' +
                        '<td>' +
                        '  <input type="time" name="' + prefix + '[time_slots][' + i + '][time_start]" class="form-control" value="' + (slot.time_start || '18:00') + '">' +
                        '  <div class="invalid-feedback d-none" data-error-for="' + prefix + '[time_slots][' + i + '][time_start]"></div>' +
                        '</td>' +
                        '<td>' +
                        '  <input type="time" name="' + prefix + '[time_slots][' + i + '][time_end]" class="form-control" value="' + (slot.time_end || '19:00') + '">' +
                        '  <div class="invalid-feedback d-none" data-error-for="' + prefix + '[time_slots][' + i + '][time_end]"></div>' +
                        '</td>' +
                        '<td class="text-center">' +
                        '  <button type="button" class="btn btn-outline-danger btn-sm remove-slot">×</button>' +
                        '</td>';

                    return tr;
                }

                function normalizePayload(formData, prefix) {
                    const payload = {
                        name: (formData.get(prefix + '[name]') || '').toString(),
                        schedule_type: (formData.get(prefix + '[schedule_type]') || '').toString(),
                        duration_days: (formData.get(prefix + '[duration_days]') || '').toString(),
                        lessons_count: (formData.get(prefix + '[lessons_count]') || '').toString(),
                        price: (formData.get(prefix + '[price]') || '').toString(),
                        freeze_enabled: formData.get(prefix + '[freeze_enabled]') ? 1 : 0,
                        freeze_days: (formData.get(prefix + '[freeze_days]') || '').toString(),
                    };

                    // Слоты собираем напрямую из DOM (надёжно для динамических строк)
                    const tableId = prefix === 'edit' ? 'edit-slots-table' : 'create-slots-table';
                    const tbody = document.querySelector('#' + tableId + ' tbody');
                    const rows = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];

                    payload.time_slots = rows.map(function (tr) {
                        const weekday = tr.querySelector('select[name^="' + prefix + '[time_slots]"]')?.value || '';
                        const times = tr.querySelectorAll('input[type="time"][name^="' + prefix + '[time_slots]"]');
                        const timeStart = times[0] ? times[0].value : '';
                        const timeEnd = times[1] ? times[1].value : '';

                        return {
                            weekday: weekday,
                            time_start: timeStart,
                            time_end: timeEnd,
                        };
                    });

                    return payload;
                }

                async function requestJson(method, url, data) {
                    const res = await fetch(url, {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: data ? JSON.stringify(data) : undefined
                    });

                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const err = new Error(json.message || 'Ошибка запроса');
                        err.payload = json;
                        err.status = res.status;
                        throw err;
                    }
                    return json;
                }

                function applyValidationErrors(modalEl, errors, prefix) {
                    Object.keys(errors || {}).forEach(function (k) {
                        const msg = (errors[k] && errors[k][0]) ? errors[k][0] : 'Ошибка';

                        if (k.startsWith('time_slots.')) {
                            const parts = k.split('.');
                            const i = parts[1];
                            const field = parts[2];
                            const inputName = prefix + '[time_slots][' + i + '][' + field + ']';
                            setFieldError(modalEl, inputName, msg);
                            return;
                        }

                        const inputName = prefix + '[' + k + ']';
                        setFieldError(modalEl, inputName, msg);
                    });
                }

                const createModalEl = document.getElementById('lessonPackageCreateModal');
                const createFormEl = document.getElementById('lessonPackageCreateForm');
                const createSlotsBody = document.querySelector('#create-slots-table tbody');
                const createAddSlotBtn = document.getElementById('create-add-slot');
                const createFreezeEnabled = document.getElementById('create_freeze_enabled');
                const createFreezeDaysWrap = document.getElementById('create_freeze_days_wrap');
                const createScheduleType = document.getElementById('create_schedule_type');
                const createSlotsSection = document.getElementById('create-time-slots-section');

                function createToggleFreezeDays() {
                    createFreezeDaysWrap.style.display = createFreezeEnabled.checked ? '' : 'none';
                }
                function createToggleSlotsSection() {
                    createSlotsSection.style.display = (createScheduleType.value === 'fixed') ? '' : 'none';
                }
                function createNextIndex() {
                    return createSlotsBody.querySelectorAll('tr').length;
                }
                function createEnsureAtLeastOneRow() {
                    if (createSlotsBody.querySelectorAll('tr').length === 0) {
                        createSlotsBody.appendChild(buildSlotRow('create', 0, {weekday: 1, time_start: '18:00', time_end: '19:00'}));
                    }
                }

                createAddSlotBtn?.addEventListener('click', function () {
                    createSlotsBody.appendChild(buildSlotRow('create', createNextIndex(), {weekday: 1, time_start: '18:00', time_end: '19:00'}));
                });
                createSlotsBody?.addEventListener('click', function (e) {
                    const btn = e.target.closest('.remove-slot');
                    if (!btn) return;
                    btn.closest('tr')?.remove();
                });
                createFreezeEnabled?.addEventListener('change', createToggleFreezeDays);
                createScheduleType?.addEventListener('change', createToggleSlotsSection);

                createFormEl?.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    clearErrors(createModalEl);

                    const fd = new FormData(createFormEl);
                    const payload = normalizePayload(fd, 'create');

                    try {
                        await requestJson('POST', @json(route('admin.lesson-packages.store')), payload);
                        window.location.reload();
                    } catch (err) {
                        const p = err.payload || {};
                        if (p.errors) {
                            applyValidationErrors(createModalEl, p.errors, 'create');
                        }
                    }
                });

                createModalEl?.addEventListener('shown.bs.modal', function () {
                    createToggleFreezeDays();
                    createToggleSlotsSection();
                    createEnsureAtLeastOneRow();
                });

                // ---------- EDIT MODAL ----------
                const editModalEl = document.getElementById('lessonPackageEditModal');
                const editFormEl = document.getElementById('lessonPackageEditForm');
                const editSlotsBody = document.querySelector('#edit-slots-table tbody');
                const editAddSlotBtn = document.getElementById('edit-add-slot');
                const editFreezeEnabled = document.getElementById('edit_freeze_enabled');
                const editFreezeDaysWrap = document.getElementById('edit_freeze_days_wrap');
                const editScheduleType = document.getElementById('edit_schedule_type');
                const editSlotsSection = document.getElementById('edit-time-slots-section');
                const editIdEl = document.getElementById('edit_id');

                function editToggleFreezeDays() {
                    editFreezeDaysWrap.style.display = editFreezeEnabled.checked ? '' : 'none';
                }
                function editToggleSlotsSection() {
                    editSlotsSection.style.display = (editScheduleType.value === 'fixed') ? '' : 'none';
                }
                function editNextIndex() {
                    return editSlotsBody.querySelectorAll('tr').length;
                }
                function editSetSlots(slots) {
                    editSlotsBody.innerHTML = '';
                    (slots || []).forEach(function (s, i) {
                        editSlotsBody.appendChild(buildSlotRow('edit', i, s));
                    });
                    if (editSlotsBody.querySelectorAll('tr').length === 0) {
                        editSlotsBody.appendChild(buildSlotRow('edit', 0, {weekday: 1, time_start: '18:00', time_end: '19:00'}));
                    }
                }

                editAddSlotBtn?.addEventListener('click', function () {
                    editSlotsBody.appendChild(buildSlotRow('edit', editNextIndex(), {weekday: 1, time_start: '18:00', time_end: '19:00'}));
                });
                editSlotsBody?.addEventListener('click', function (e) {
                    const btn = e.target.closest('.remove-slot');
                    if (!btn) return;
                    btn.closest('tr')?.remove();
                });
                editFreezeEnabled?.addEventListener('change', editToggleFreezeDays);
                editScheduleType?.addEventListener('change', editToggleSlotsSection);

                editFormEl?.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    clearErrors(editModalEl);

                    const id = editIdEl.value;
                    if (!id) return;

                    const fd = new FormData(editFormEl);
                    const payload = normalizePayload(fd, 'edit');

                    try {
                        await requestJson('PUT', '/admin/lesson-packages/' + id, payload);
                        window.location.reload();
                    } catch (err) {
                        const p = err.payload || {};
                        if (p.errors) {
                            applyValidationErrors(editModalEl, p.errors, 'edit');
                        }
                    }
                });

                document.querySelectorAll('.lesson-package-edit-btn').forEach(function (btn) {
                    btn.addEventListener('click', async function () {
                        clearErrors(editModalEl);
                        const id = btn.getAttribute('data-id');
                        editIdEl.value = id;

                        try {
                            const json = await requestJson('GET', '/admin/lesson-packages/' + id);
                            const lp = json.lesson_package || {};

                            editModalEl.querySelector('[name="edit[name]"]').value = lp.name || '';
                            editModalEl.querySelector('[name="edit[schedule_type]"]').value = lp.schedule_type || 'fixed';
                            editModalEl.querySelector('[name="edit[duration_days]"]').value = lp.duration_days || 30;
                            editModalEl.querySelector('[name="edit[lessons_count]"]').value = lp.lessons_count || 8;
                            editModalEl.querySelector('[name="edit[price]"]').value = (lp.price !== undefined && lp.price !== null) ? lp.price : 0;

                            editFreezeEnabled.checked = !!lp.freeze_enabled;
                            editModalEl.querySelector('[name="edit[freeze_days]"]').value = lp.freeze_days || 7;

                            editSetSlots(lp.time_slots || []);
                            editToggleFreezeDays();
                            editToggleSlotsSection();
                        } catch (err) {
                            // silent
                        }
                    });
                });

                editModalEl?.addEventListener('shown.bs.modal', function () {
                    editToggleFreezeDays();
                    editToggleSlotsSection();
                });
            })();
        </script>
    @endsection
@endcan

