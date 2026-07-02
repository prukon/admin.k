<div class="tab-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
        <h4 class="mb-0">Абонементы</h4>

        @can('lessonPackages.view')
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
                <th>Название</th>
                <th>Тип</th>
                <th>Длительность</th>
                <th>Занятий</th>
                <th>Стоимость</th>
                <th>Заморозка</th>
                @can('lessonPackages.view')
                    <th class="text-start" style="min-width: 220px;">Действия</th>
                @endcan
            </tr>
            </thead>
            <tbody>
            @forelse ($packages as $package)
                <tr>
                    <td>{{ $package->name }}</td>
                    <td>
                        @if ($package->schedule_type === 'fixed')
                            Фиксированный
                        @elseif($package->schedule_type === 'flexible')
                            Гибкий
                        @else
                            Разовое занятие
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
                    @can('lessonPackages.view')
                        <td class="text-start">
                            <div class="d-flex flex-wrap gap-1 justify-content-start">
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary lesson-package-edit-btn"
                                        data-id="{{ $package->id }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#lessonPackageEditModal">
                                    Изменить
                                </button>
                                @if ((int) ($package->partner_assignments_count ?? 0) === 0 && (int) ($package->partner_linked_lessons_count ?? 0) === 0)
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger lesson-package-delete-btn"
                                            data-id="{{ $package->id }}"
                                            data-name="{{ $package->name }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#lessonPackageDeleteModal">
                                        Удалить
                                    </button>
                                @endif
                            </div>
                        </td>
                    @endcan
                </tr>
            @empty
                <tr>
                    <td colspan="@can('lessonPackages.view') 7 @else 6 @endcan" class="text-center text-muted">
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

@can('lessonPackages.view')
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
                            <div class="col-12">
                                <label class="form-label">Название</label>
                                <input type="text" name="create[name]" class="form-control" maxlength="255" required>
                                <div class="invalid-feedback d-none" data-error-for="create[name]"></div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Тип</label>
                                <select name="create[schedule_type]" id="create_schedule_type" class="form-select" required>
                                    <option value="fixed">Фиксированный</option>
                                    <option value="flexible">Гибкий</option>
                                    <option value="no_schedule">Разовое занятие</option>
                                </select>
                                <div class="invalid-feedback d-none" data-error-for="create[schedule_type]"></div>
                            </div>

                            <div class="col-12">
                                <div class="row g-3">
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label">Длительность (дни)</label>
                                        <input type="number" name="create[duration_days]" id="create_duration_days" class="form-control" min="1" max="3650" value="30" required>
                                        <div class="invalid-feedback d-none" data-error-for="create[duration_days]"></div>
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label">Занятий</label>
                                        <input type="number" name="create[lessons_count]" id="create_lessons_count" class="form-control" min="1" max="1000" value="8" required>
                                        <div class="invalid-feedback d-none" data-error-for="create[lessons_count]"></div>
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label">Стоимость (руб.)</label>
                                        <input type="number" name="create[price]" class="form-control" min="0" max="99999999.99" step="0.01" value="0" required>
                                        <div class="invalid-feedback d-none" data-error-for="create[price]"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" id="create_freeze_section">
                                <div class="rounded border bg-light p-3">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" value="1" id="create_freeze_enabled" name="create[freeze_enabled]">
                                        <label class="form-check-label" for="create_freeze_enabled">Разрешена заморозка</label>
                                    </div>
                                    <div class="invalid-feedback d-none" data-error-for="create[freeze_enabled]"></div>

                                    <div id="create_freeze_days_wrap" class="d-none mt-3 pt-3 border-top">
                                        <div class="row g-3">
                                            <div class="col-12 col-sm-4">
                                                <label class="form-label mb-1" for="create_freeze_days">Дней заморозки</label>
                                                <input type="number" name="create[freeze_days]" id="create_freeze_days" class="form-control" min="1" max="3650" value="7">
                                                <div class="invalid-feedback d-none" data-error-for="create[freeze_days]"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" id="create_auto_attendance_section">
                                <div class="rounded border bg-light p-3">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" value="1" id="create_auto_attendance_enabled" name="create[auto_attendance_enabled]">
                                        <label class="form-check-label" for="create_auto_attendance_enabled">Автосписание</label>
                                    </div>
                                    <div class="form-text mt-2 mb-0">
                                        В конце дня занятия автоматически ставится статус «Посетил» и списывается занятие, если статус не был выставлен вручную.
                                    </div>
                                    <div class="invalid-feedback d-none" data-error-for="create[auto_attendance_enabled]"></div>
                                </div>
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
                                    <option value="fixed">Фиксированный</option>
                                    <option value="flexible">Гибкий</option>
                                    <option value="no_schedule">Разовое занятие</option>
                                </select>
                                <div class="invalid-feedback d-none" data-error-for="edit[schedule_type]"></div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Длительность (дни) *</label>
                                <input type="number" name="edit[duration_days]" id="edit_duration_days" class="form-control" min="1" max="3650" required>
                                <div class="invalid-feedback d-none" data-error-for="edit[duration_days]"></div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Занятий *</label>
                                <input type="number" name="edit[lessons_count]" id="edit_lessons_count" class="form-control" min="1" max="1000" required>
                                <div class="invalid-feedback d-none" data-error-for="edit[lessons_count]"></div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Стоимость (руб.) *</label>
                                <input type="number" name="edit[price]" class="form-control" min="0" max="99999999.99" step="0.01" required>
                                <div class="invalid-feedback d-none" data-error-for="edit[price]"></div>
                            </div>

                            <div class="col-12" id="edit_freeze_section">
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

                            <div class="col-12" id="edit_auto_attendance_section">
                                <div class="rounded border bg-light p-3">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" value="1" id="edit_auto_attendance_enabled" name="edit[auto_attendance_enabled]">
                                        <label class="form-check-label" for="edit_auto_attendance_enabled">Автосписание</label>
                                    </div>
                                    <div class="form-text mt-2 mb-0">
                                        В конце дня занятия автоматически ставится статус «Посетил» и списывается занятие, если статус не был выставлен вручную.
                                    </div>
                                    <div class="invalid-feedback d-none" data-error-for="edit[auto_attendance_enabled]"></div>
                                </div>
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

    {{-- Delete confirm --}}
    <div class="modal fade" id="lessonPackageDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Удаление абонемента</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Удалить абонемент «<span id="lessonPackageDeleteName"></span>»? Это действие нельзя отменить.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-danger" id="lessonPackageDeleteConfirmBtn">Удалить</button>
                </div>
            </div>
        </div>
    </div>
@endcan

@can('lessonPackages.view')
    @section('scripts')
        @parent
        <script>
            (function () {
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

                function normalizePayload(formData, prefix) {
                    const scheduleType = (formData.get(prefix + '[schedule_type]') || '').toString();
                    return {
                        name: (formData.get(prefix + '[name]') || '').toString(),
                        schedule_type: scheduleType,
                        duration_days: (formData.get(prefix + '[duration_days]') || '').toString(),
                        lessons_count: (formData.get(prefix + '[lessons_count]') || '').toString(),
                        price: (formData.get(prefix + '[price]') || '').toString(),
                        freeze_enabled: formData.get(prefix + '[freeze_enabled]') ? 1 : 0,
                        freeze_days: (formData.get(prefix + '[freeze_days]') || '').toString(),
                        auto_attendance_enabled: formData.get(prefix + '[auto_attendance_enabled]') ? 1 : 0,
                        time_slots: [],
                    };
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
                        const inputName = prefix + '[' + k + ']';
                        setFieldError(modalEl, inputName, msg);
                    });
                }

                const createModalEl = document.getElementById('lessonPackageCreateModal');
                const createFormEl = document.getElementById('lessonPackageCreateForm');
                const createFreezeEnabled = document.getElementById('create_freeze_enabled');
                const createFreezeDaysWrap = document.getElementById('create_freeze_days_wrap');
                const createScheduleType = document.getElementById('create_schedule_type');
                const createFreezeSection = document.getElementById('create_freeze_section');
                const createAutoAttendanceSection = document.getElementById('create_auto_attendance_section');
                const createAutoAttendanceEnabled = document.getElementById('create_auto_attendance_enabled');
                const createDuration = document.getElementById('create_duration_days');
                const createLessons = document.getElementById('create_lessons_count');
                let createSnapshotBeforeSingle = null;

                function createToggleFreezeDays() {
                    if (!createFreezeDaysWrap || !createFreezeEnabled) {
                        return;
                    }
                    createFreezeDaysWrap.classList.toggle('d-none', !createFreezeEnabled.checked);
                }

                function applyCreateScheduleTypeUi() {
                    if (!createScheduleType) {
                        return;
                    }
                    const t = createScheduleType.value;
                    if (t === 'no_schedule') {
                        createSnapshotBeforeSingle = {
                            duration: (createDuration && createDuration.value) ? createDuration.value : '30',
                            lessons: (createLessons && createLessons.value) ? createLessons.value : '8',
                        };
                        if (createDuration) {
                            createDuration.value = '1';
                            createDuration.readOnly = true;
                        }
                        if (createLessons) {
                            createLessons.value = '1';
                            createLessons.readOnly = true;
                        }
                        if (createFreezeSection) {
                            createFreezeSection.style.display = 'none';
                        }
                        if (createAutoAttendanceSection) {
                            createAutoAttendanceSection.style.display = 'none';
                        }
                        if (createFreezeEnabled) {
                            createFreezeEnabled.checked = false;
                        }
                        if (createAutoAttendanceEnabled) {
                            createAutoAttendanceEnabled.checked = false;
                        }
                        createToggleFreezeDays();
                    } else {
                        if (createSnapshotBeforeSingle) {
                            if (createDuration) {
                                createDuration.value = createSnapshotBeforeSingle.duration;
                            }
                            if (createLessons) {
                                createLessons.value = createSnapshotBeforeSingle.lessons;
                            }
                            createSnapshotBeforeSingle = null;
                        }
                        if (createDuration) {
                            createDuration.readOnly = false;
                        }
                        if (createLessons) {
                            createLessons.readOnly = false;
                        }
                        if (createFreezeSection) {
                            createFreezeSection.style.display = '';
                        }
                        if (createAutoAttendanceSection) {
                            createAutoAttendanceSection.style.display = '';
                        }
                        createToggleFreezeDays();
                    }
                }

                createFreezeEnabled?.addEventListener('change', createToggleFreezeDays);
                createScheduleType?.addEventListener('change', applyCreateScheduleTypeUi);

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
                    createSnapshotBeforeSingle = null;
                    if (createFormEl) {
                        createFormEl.reset();
                    }
                    if (createScheduleType) {
                        createScheduleType.value = 'fixed';
                    }
                    if (createDuration) {
                        createDuration.value = '30';
                        createDuration.readOnly = false;
                    }
                    if (createLessons) {
                        createLessons.value = '8';
                        createLessons.readOnly = false;
                    }
                    applyCreateScheduleTypeUi();
                });

                // ---------- EDIT MODAL ----------
                const editModalEl = document.getElementById('lessonPackageEditModal');
                const editFormEl = document.getElementById('lessonPackageEditForm');
                const editFreezeEnabled = document.getElementById('edit_freeze_enabled');
                const editFreezeDaysWrap = document.getElementById('edit_freeze_days_wrap');
                const editScheduleType = document.getElementById('edit_schedule_type');
                const editFreezeSection = document.getElementById('edit_freeze_section');
                const editAutoAttendanceSection = document.getElementById('edit_auto_attendance_section');
                const editAutoAttendanceEnabled = document.getElementById('edit_auto_attendance_enabled');
                const editDuration = document.getElementById('edit_duration_days');
                const editLessons = document.getElementById('edit_lessons_count');
                const editIdEl = document.getElementById('edit_id');
                let editSnapshotBeforeSingle = null;

                function editToggleFreezeDays() {
                    if (!editFreezeDaysWrap || !editFreezeEnabled) {
                        return;
                    }
                    editFreezeDaysWrap.style.display = editFreezeEnabled.checked ? '' : 'none';
                }

                function applyEditScheduleTypeUi() {
                    if (!editScheduleType) {
                        return;
                    }
                    const t = editScheduleType.value;
                    if (t === 'no_schedule') {
                        editSnapshotBeforeSingle = {
                            duration: (editDuration && editDuration.value) ? editDuration.value : '30',
                            lessons: (editLessons && editLessons.value) ? editLessons.value : '8',
                        };
                        if (editDuration) {
                            editDuration.value = '1';
                            editDuration.readOnly = true;
                        }
                        if (editLessons) {
                            editLessons.value = '1';
                            editLessons.readOnly = true;
                        }
                        if (editFreezeSection) {
                            editFreezeSection.style.display = 'none';
                        }
                        if (editAutoAttendanceSection) {
                            editAutoAttendanceSection.style.display = 'none';
                        }
                        if (editFreezeEnabled) {
                            editFreezeEnabled.checked = false;
                        }
                        if (editAutoAttendanceEnabled) {
                            editAutoAttendanceEnabled.checked = false;
                        }
                        editToggleFreezeDays();
                    } else {
                        if (editSnapshotBeforeSingle) {
                            if (editDuration) {
                                editDuration.value = editSnapshotBeforeSingle.duration;
                            }
                            if (editLessons) {
                                editLessons.value = editSnapshotBeforeSingle.lessons;
                            }
                            editSnapshotBeforeSingle = null;
                        } else {
                            if (editDuration) {
                                editDuration.value = '30';
                            }
                            if (editLessons) {
                                editLessons.value = '8';
                            }
                        }
                        if (editDuration) {
                            editDuration.readOnly = false;
                        }
                        if (editLessons) {
                            editLessons.readOnly = false;
                        }
                        if (editFreezeSection) {
                            editFreezeSection.style.display = '';
                        }
                        if (editAutoAttendanceSection) {
                            editAutoAttendanceSection.style.display = '';
                        }
                        editToggleFreezeDays();
                    }
                }

                editFreezeEnabled?.addEventListener('change', editToggleFreezeDays);
                editScheduleType?.addEventListener('change', applyEditScheduleTypeUi);

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

                const deleteModalEl = document.getElementById('lessonPackageDeleteModal');
                const deleteNameEl = document.getElementById('lessonPackageDeleteName');
                const deleteConfirmBtn = document.getElementById('lessonPackageDeleteConfirmBtn');
                let deleteTargetId = null;

                document.querySelectorAll('.lesson-package-delete-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        deleteTargetId = btn.getAttribute('data-id');
                        if (deleteNameEl) {
                            deleteNameEl.textContent = btn.getAttribute('data-name') || '';
                        }
                    });
                });

                deleteConfirmBtn?.addEventListener('click', async function () {
                    if (!deleteTargetId) {
                        return;
                    }
                    try {
                        await requestJson('DELETE', '/admin/lesson-packages/' + deleteTargetId);
                        window.location.reload();
                    } catch (err) {
                        const msg = (err.payload && err.payload.message)
                            ? err.payload.message
                            : (err.message || 'Не удалось удалить абонемент.');
                        alert(msg);
                    }
                });

                deleteModalEl?.addEventListener('hidden.bs.modal', function () {
                    deleteTargetId = null;
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
                            if (editAutoAttendanceEnabled) {
                                editAutoAttendanceEnabled.checked = !!lp.auto_attendance_enabled;
                            }

                            editSnapshotBeforeSingle = null;
                            const st = (lp.schedule_type || 'fixed').toString();
                            if (st === 'no_schedule') {
                                if (editDuration) {
                                    editDuration.readOnly = true;
                                }
                                if (editLessons) {
                                    editLessons.readOnly = true;
                                }
                                if (editFreezeSection) {
                                    editFreezeSection.style.display = 'none';
                                }
                                if (editAutoAttendanceSection) {
                                    editAutoAttendanceSection.style.display = 'none';
                                }
                                if (editFreezeEnabled) {
                                    editFreezeEnabled.checked = false;
                                }
                                if (editAutoAttendanceEnabled) {
                                    editAutoAttendanceEnabled.checked = false;
                                }
                                editToggleFreezeDays();
                            } else {
                                if (editDuration) {
                                    editDuration.readOnly = false;
                                }
                                if (editLessons) {
                                    editLessons.readOnly = false;
                                }
                                if (editFreezeSection) {
                                    editFreezeSection.style.display = '';
                                }
                                if (editAutoAttendanceSection) {
                                    editAutoAttendanceSection.style.display = '';
                                }
                                editToggleFreezeDays();
                            }
                        } catch (err) {
                            // silent
                        }
                    });
                });

                editModalEl?.addEventListener('shown.bs.modal', function () {
                    editToggleFreezeDays();
                });
            })();
        </script>
    @endsection
@endcan

