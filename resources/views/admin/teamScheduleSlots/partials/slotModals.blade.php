{{-- Модалки слотов: компактные, стиль как у остальной админки (Bootstrap / AdminLTE). --}}
@php
    $tssDateEndMax = now()->addDays(365)->format('Y-m-d');
    $tssDateStartDefault = now()->format('Y-m-d');
    $tssDateEndDefault = $tssDateEndMax;
    $tssWeekdayDefault = (int) now()->format('N');
@endphp
<style>
    .slot-modal-narrow .modal-dialog {
        max-width: 26rem;
    }
    .slot-modal-narrow .modal-body {
        padding-top: 0.65rem;
        padding-bottom: 0.65rem;
    }
    .slot-modal-weekdays {
        display: flex;
        flex-wrap: wrap;
        gap: 0.3rem;
    }
    .slot-modal-weekdays .btn {
        font-size: 0.8125rem;
        padding: 0.2rem 0.45rem;
        line-height: 1.2;
    }
</style>

<div class="modal fade slot-modal-narrow" id="slotCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 px-3">
                <h5 class="modal-title mb-0">Добавить слот</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-3">
                <form id="slotCreateForm" data-date-end-max="{{ $tssDateEndMax }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label mb-1 small">Группа*</label>
                        <select class="form-control form-control-sm" name="team_id" required>
                            <option value="">—</option>
                            @foreach($teams as $t)
                                <option value="{{ $t->id }}">{{ $t->title }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback d-block" data-error-for="team_id"></div>
                    </div>

                    @can('locations.view')
                        <div class="mb-2">
                            <label class="form-label mb-1 small">Локация</label>
                            <select class="form-control form-control-sm" name="location_id">
                                @forelse($locations as $l)
                                    <option value="{{ $l->id }}" @if($loop->first) selected @endif>{{ $l->name }}</option>
                                @empty
                                    <option value="">Нет локаций</option>
                                @endforelse
                            </select>
                            <div class="invalid-feedback d-block" data-error-for="location_id"></div>
                        </div>
                    @endcan

                    <div class="mb-2">
                        <label class="form-label mb-1 small">День недели*</label>
                        <div class="slot-modal-weekdays" role="group" aria-label="День недели">
                            @foreach($weekdays as $k => $label)
                                <button type="button" class="btn btn-sm js-slot-weekday-create {{ (int)$k === $tssWeekdayDefault ? 'btn-primary' : 'btn-outline-secondary' }}" data-weekday="{{ $k }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        <input type="hidden" name="weekday" value="{{ $tssWeekdayDefault }}">
                        <div class="invalid-feedback d-block" data-error-for="weekday"></div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label mb-1 small">С*</label>
                            <input class="form-control form-control-sm" type="time" name="time_start" value="09:00" step="300" required>
                            <div class="invalid-feedback d-block" data-error-for="time_start"></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-1 small">По*</label>
                            <input class="form-control form-control-sm" type="time" name="time_end" value="10:00" step="300" required>
                            <div class="invalid-feedback d-block" data-error-for="time_end"></div>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label mb-1 small">Период с*</label>
                            <input class="form-control form-control-sm" type="date" name="date_start" value="{{ $tssDateStartDefault }}" required>
                            <div class="invalid-feedback d-block" data-error-for="date_start"></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-1 small">Период по*</label>
                            <input class="form-control form-control-sm" type="date" name="date_end" id="slotCreateDateEnd" value="{{ $tssDateEndDefault }}" min="{{ $tssDateStartDefault }}" max="{{ $tssDateEndMax }}" required>
                            <div class="invalid-feedback d-block" data-error-for="date_end"></div>
                        </div>
                    </div>
                    <p class="small text-muted mb-2 mb-0">Дата окончания не позднее {{ \Illuminate\Support\Carbon::parse($tssDateEndMax)->format('d.m.Y') }}.</p>

                    <input type="hidden" name="is_enabled" value="1">
                </form>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary btn-sm" id="slotCreateSubmit">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade slot-modal-narrow" id="slotEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 px-3">
                <h5 class="modal-title mb-0">Редактировать слот</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-3">
                <form id="slotEditForm" data-date-end-max="{{ $tssDateEndMax }}">
                    @csrf
                    @method('put')
                    <input type="hidden" name="id" />

                    <div class="mb-2">
                        <label class="form-label mb-1 small">Группа*</label>
                        <select class="form-control form-control-sm" name="team_id" required>
                            <option value="">—</option>
                            @foreach($teams as $t)
                                <option value="{{ $t->id }}">{{ $t->title }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback d-block" data-error-for="team_id"></div>
                    </div>

                    @can('locations.view')
                        <div class="mb-2">
                            <label class="form-label mb-1 small">Локация</label>
                            <select class="form-control form-control-sm" name="location_id">
                                <option value="">—</option>
                                @foreach($locations as $l)
                                    <option value="{{ $l->id }}">{{ $l->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block" data-error-for="location_id"></div>
                        </div>
                    @endcan

                    <div class="mb-2">
                        <label class="form-label mb-1 small">День недели*</label>
                        <div class="slot-modal-weekdays" role="group" aria-label="День недели">
                            @foreach($weekdays as $k => $label)
                                <button type="button" class="btn btn-sm js-slot-weekday-edit btn-outline-secondary" data-weekday="{{ $k }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        <input type="hidden" name="weekday" value="">
                        <div class="invalid-feedback d-block" data-error-for="weekday"></div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label mb-1 small">С*</label>
                            <input class="form-control form-control-sm" type="time" name="time_start" step="300" required>
                            <div class="invalid-feedback d-block" data-error-for="time_start"></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-1 small">По*</label>
                            <input class="form-control form-control-sm" type="time" name="time_end" step="300" required>
                            <div class="invalid-feedback d-block" data-error-for="time_end"></div>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label mb-1 small">Период с*</label>
                            <input class="form-control form-control-sm" type="date" name="date_start" max="{{ $tssDateEndMax }}" required>
                            <div class="invalid-feedback d-block" data-error-for="date_start"></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-1 small">Период по</label>
                            <input class="form-control form-control-sm" type="date" name="date_end" id="slotEditDateEnd" min="" max="{{ $tssDateEndMax }}">
                            <div class="invalid-feedback d-block" data-error-for="date_end"></div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1 small">Применить изменения с*</label>
                        <input class="form-control form-control-sm" type="date" name="apply_changes_from" max="{{ $tssDateEndMax }}" required>
                        <div class="invalid-feedback d-block" data-error-for="apply_changes_from"></div>
                        <p class="small text-muted mb-0 mt-1">Совпадает с началом периода — правится одна запись. Позже начала — старый период усечён, справа создаётся новое правило (день и время могут не совпадать с этой датой).</p>
                    </div>
                    <p class="small text-muted js-slot-edit-split-hint d-none mb-2">Начало периода слева фиксировано; группа, время и конец периода ниже относятся к новому отрезку.</p>
                    <p class="small text-muted mb-2 mb-0">Не позднее {{ \Illuminate\Support\Carbon::parse($tssDateEndMax)->format('d.m.Y') }}.</p>

                    <div class="mb-0">
                        <label class="form-label mb-1 small">Активен</label>
                        <select class="form-control form-control-sm" name="is_enabled">
                            <option value="1">Да</option>
                            <option value="0">Нет</option>
                        </select>
                        <div class="invalid-feedback d-block" data-error-for="is_enabled"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-outline-danger btn-sm me-auto" id="slotDeleteBtn">Удалить</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary btn-sm" id="slotEditSubmit">Сохранить</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    @can('scheduleSlots.manage')
        <script>
            (function () {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const tssDateEndMax = '{{ \Illuminate\Support\Carbon::now()->addDays(365)->format('Y-m-d') }}';
                const storeUrl = `{{ route('admin.team-schedule-slots.store') }}`;

                function getCreateForm() {
                    return document.getElementById('slotCreateForm');
                }

                function getEditForm() {
                    return document.getElementById('slotEditForm');
                }

                function clearErrors(form) {
                    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                    form.querySelectorAll('[data-error-for]').forEach(el => { el.textContent = ''; });
                }

                function applyErrors(form, errors) {
                    Object.entries(errors || {}).forEach(([key, messages]) => {
                        const input = form.querySelector(`[name="${key}"]`);
                        const err = form.querySelector(`[data-error-for="${key}"]`);
                        if (input) input.classList.add('is-invalid');
                        if (err) err.textContent = (messages && messages[0]) ? messages[0] : 'Ошибка';
                    });
                }

                async function postForm(url, form, method = 'POST') {
                    const fd = new FormData(form);
                    if (method !== 'POST') {
                        fd.set('_method', method);
                    }
                    const res = await fetch(url, {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                        }
                    });
                    const data = await res.json().catch(() => ({}));
                    return { ok: res.ok, status: res.status, data };
                }

                function syncCreateWeekday(val) {
                    document.querySelectorAll('.js-slot-weekday-create').forEach(btn => {
                        const on = String(btn.getAttribute('data-weekday')) === String(val);
                        btn.classList.toggle('btn-primary', on);
                        btn.classList.toggle('btn-outline-secondary', !on);
                    });
                }

                function syncEditWeekday(val) {
                    document.querySelectorAll('.js-slot-weekday-edit').forEach(btn => {
                        const on = String(btn.getAttribute('data-weekday')) === String(val);
                        btn.classList.toggle('btn-primary', on);
                        btn.classList.toggle('btn-outline-secondary', !on);
                    });
                }

                function wireSlotDatePickers(form) {
                    const maxEnd = form.getAttribute('data-date-end-max');
                    const ds = form.querySelector('[name="date_start"]');
                    const de = form.querySelector('[name="date_end"]');
                    if (!ds || !de || !maxEnd) return;
                    de.setAttribute('max', maxEnd);
                    const clamp = () => {
                        if (ds.value) {
                            de.setAttribute('min', ds.value);
                        } else {
                            de.removeAttribute('min');
                        }
                        if (de.value && ds.value && de.value < ds.value) {
                            de.value = ds.value;
                        }
                        if (de.value && de.value > maxEnd) {
                            de.value = maxEnd;
                        }
                    };
                    ds.addEventListener('change', clamp);
                    de.addEventListener('change', () => {
                        if (de.value && de.value > maxEnd) de.value = maxEnd;
                        if (de.value && ds.value && de.value < ds.value) de.value = ds.value;
                    });
                    clamp();
                }

                function wireEditSlotFormDates(form) {
                    if (!form || form.dataset.editSlotDatesWired === '1') return;
                    form.dataset.editSlotDatesWired = '1';
                    const maxEnd = form.getAttribute('data-date-end-max');
                    const ds = form.querySelector('[name="date_start"]');
                    const de = form.querySelector('[name="date_end"]');
                    const apply = form.querySelector('[name="apply_changes_from"]');
                    const hint = form.querySelector('.js-slot-edit-split-hint');
                    if (!ds || !de || !maxEnd || !apply) return;
                    de.setAttribute('max', maxEnd);
                    apply.setAttribute('max', maxEnd);

                    function syncSplitUi() {
                        const orig = form.dataset.originalDateStart || '';
                        if (apply.value && orig && apply.value > orig) {
                            ds.readOnly = true;
                            ds.value = orig;
                            if (hint) hint.classList.remove('d-none');
                        } else {
                            ds.readOnly = false;
                            if (hint) hint.classList.add('d-none');
                        }
                    }

                    function clamp() {
                        syncSplitUi();
                        const orig = form.dataset.originalDateStart || '';
                        const applyVal = apply.value || '';
                        let minEnd = ds.value || '';
                        if (applyVal && orig && applyVal > orig) {
                            minEnd = applyVal;
                        }
                        if (minEnd) {
                            de.setAttribute('min', minEnd);
                        } else {
                            de.removeAttribute('min');
                        }
                        if (!ds.readOnly && de.value && ds.value && de.value < ds.value) {
                            de.value = ds.value;
                        }
                        if (applyVal && orig && applyVal > orig && de.value && de.value < applyVal) {
                            de.value = applyVal;
                        }
                        if (de.value && de.value > maxEnd) {
                            de.value = maxEnd;
                        }
                    }

                    apply.addEventListener('change', clamp);
                    ds.addEventListener('change', clamp);
                    de.addEventListener('change', () => {
                        if (de.value && de.value > maxEnd) de.value = maxEnd;
                        const applyVal = apply.value || '';
                        const orig = form.dataset.originalDateStart || '';
                        let minV = ds.readOnly ? (orig || ds.value) : (ds.value || '');
                        if (applyVal && orig && applyVal > orig) minV = applyVal;
                        if (de.value && minV && de.value < minV) de.value = minV;
                    });
                    clamp();
                }

                function bindTeamScheduleSlotModalsOnce() {
                    if (document.body.dataset.teamScheduleSlotModalsBound === '1') return;
                    document.body.dataset.teamScheduleSlotModalsBound = '1';

                    const createFormEl = getCreateForm();
                    const editFormEl = getEditForm();
                    if (createFormEl) wireSlotDatePickers(createFormEl);
                    if (editFormEl) wireEditSlotFormDates(editFormEl);

                    document.querySelectorAll('.js-slot-weekday-create').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const form = getCreateForm();
                            if (!form) return;
                            const v = btn.getAttribute('data-weekday');
                            form.querySelector('[name="weekday"]').value = v;
                            syncCreateWeekday(v);
                        });
                    });

                    document.querySelectorAll('.js-slot-weekday-edit').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const form = getEditForm();
                            if (!form) return;
                            const v = btn.getAttribute('data-weekday');
                            form.querySelector('[name="weekday"]').value = v;
                            syncEditWeekday(v);
                        });
                    });

                    document.getElementById('slotCreateSubmit')?.addEventListener('click', async () => {
                        const form = getCreateForm();
                        if (!form) return;
                        clearErrors(form);
                        try {
                            const { ok, status, data } = await postForm(storeUrl, form, 'POST');
                            if (!ok && status === 422) {
                                applyErrors(form, data.errors || {});
                                if (data.message && !Object.keys(data.errors || {}).length) {
                                    applyErrors(form, { weekday: [data.message] });
                                }
                                return;
                            }
                            if (!ok) {
                                window.alert(data.message || ('Не удалось сохранить слот (HTTP ' + status + ').'));
                                return;
                            }
                            window.location.reload();
                        } catch (e) {
                            window.alert('Не удалось сохранить слот: ' + (e && e.message ? e.message : 'ошибка сети'));
                        }
                    });

                    async function openTeamScheduleSlotEdit(slotId, opts) {
                        const editForm = getEditForm();
                        if (!editForm || slotId == null || slotId === '') return;
                        clearErrors(editForm);
                        const o = opts || {};
                        const preferredApply = typeof o.applyChangesFrom === 'string' ? o.applyChangesFrom.trim() : '';
                        const res = await fetch(`/admin/team-schedule-slots/${slotId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                        const data = await res.json().catch(() => ({}));
                        editForm.querySelector('[name="id"]').value = data.id;
                        editForm.querySelector('[name="team_id"]').value = String(data.team_id ?? '');
                        const locSel = editForm.querySelector('[name="location_id"]');
                        if (locSel) locSel.value = data.location_id ? String(data.location_id) : '';
                        editForm.querySelector('[name="weekday"]').value = String(data.weekday ?? '');
                        syncEditWeekday(data.weekday);
                        editForm.querySelector('[name="time_start"]').value = data.time_start || '';
                        editForm.querySelector('[name="time_end"]').value = data.time_end || '';
                        const dsEl = editForm.querySelector('[name="date_start"]');
                        dsEl.value = data.date_start || '';
                        dsEl.readOnly = false;
                        editForm.dataset.originalDateStart = data.date_start || '';
                        let endVal = data.date_end || '';
                        if (!endVal) {
                            endVal = tssDateEndMax;
                        }
                        if (endVal > tssDateEndMax) {
                            endVal = tssDateEndMax;
                        }
                        editForm.dataset.originalDateEnd = endVal;
                        editForm.querySelector('[name="date_end"]').value = endVal;
                        editForm.querySelector('[name="is_enabled"]').value = String(data.is_enabled ?? 1);
                        const slotStart = data.date_start || '';
                        let applyVal = slotStart;
                        if (preferredApply !== '' && slotStart !== '' && preferredApply >= slotStart && preferredApply <= endVal) {
                            applyVal = preferredApply;
                        }
                        const applyEl = editForm.querySelector('[name="apply_changes_from"]');
                        if (applyEl) {
                            applyEl.min = slotStart;
                            applyEl.max = endVal;
                            applyEl.value = applyVal;
                            applyEl.dispatchEvent(new Event('change'));
                        } else {
                            dsEl.dispatchEvent(new Event('change'));
                        }
                        const modal = new bootstrap.Modal(document.getElementById('slotEditModal'));
                        modal.show();
                    }

                    window.openTeamScheduleSlotEdit = openTeamScheduleSlotEdit;

                    function openSlotCreateModalWithDefaults(opts) {
                        const form = getCreateForm();
                        if (!form) return;
                        clearErrors(form);
                        const o = opts || {};
                        if (o.weekday != null && o.weekday !== '') {
                            const wd = String(o.weekday);
                            form.querySelector('[name="weekday"]').value = wd;
                            syncCreateWeekday(wd);
                        }
                        if (o.dateStart) {
                            form.querySelector('[name="date_start"]').value = o.dateStart;
                        }
                        if (o.timeStart) {
                            form.querySelector('[name="time_start"]').value = o.timeStart;
                        }
                        if (o.timeEnd) {
                            form.querySelector('[name="time_end"]').value = o.timeEnd;
                        }
                        if (o.locationId != null && o.locationId !== '') {
                            const sel = form.querySelector('[name="location_id"]');
                            if (sel) {
                                const v = String(o.locationId);
                                if ([...sel.options].some(opt => opt.value === v)) {
                                    sel.value = v;
                                }
                            }
                        }
                        form.querySelector('[name="team_id"]').value = '';
                        form.querySelector('[name="date_start"]')?.dispatchEvent(new Event('change'));
                        const modalEl = document.getElementById('slotCreateModal');
                        if (!modalEl) return;
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }

                    window.openSlotCreateModalWithDefaults = openSlotCreateModalWithDefaults;

                    document.querySelectorAll('.js-slot-edit').forEach(btn => {
                        btn.addEventListener('click', async () => {
                            const id = btn.getAttribute('data-id');
                            await openTeamScheduleSlotEdit(id);
                        });
                    });

                    document.getElementById('slotEditSubmit')?.addEventListener('click', async () => {
                        const editForm = getEditForm();
                        if (!editForm) return;
                        clearErrors(editForm);
                        const id = editForm.querySelector('[name="id"]').value;
                        try {
                            const { ok, status, data } = await postForm(`/admin/team-schedule-slots/${id}`, editForm, 'PUT');
                            if (!ok && status === 422) {
                                const errs = { ...(data.errors || {}) };
                                if (data.conflicts && data.conflicts.length && data.message) {
                                    errs.apply_changes_from = [data.message];
                                }
                                applyErrors(editForm, errs);
                                if (data.message && !Object.keys(errs).length) {
                                    applyErrors(editForm, { weekday: [data.message] });
                                }
                                return;
                            }
                            if (!ok) {
                                window.alert(data.message || ('Не удалось сохранить слот (HTTP ' + status + ').'));
                                return;
                            }
                            window.location.reload();
                        } catch (e) {
                            window.alert('Не удалось сохранить слот: ' + (e && e.message ? e.message : 'ошибка сети'));
                        }
                    });

                    document.getElementById('slotDeleteBtn')?.addEventListener('click', async () => {
                        const editForm = getEditForm();
                        if (!editForm) return;
                        const id = editForm.querySelector('[name="id"]').value;
                        if (!id) return;
                        const res = await fetch(`/admin/team-schedule-slots/${id}`, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token || '',
                            },
                            body: new URLSearchParams({ _method: 'DELETE' })
                        });
                        if (res.ok) {
                            window.location.reload();
                        } else {
                            const data = await res.json().catch(() => ({}));
                            let msg = data.message || ('Не удалось удалить (HTTP ' + res.status + ').');
                            if (data.conflicts && data.conflicts.length) {
                                msg += '\n\n' + data.conflicts.map(function (c) {
                                    return (c.user_label || '') + ' — ' + (c.occurrence_date || '');
                                }).join('\n');
                            }
                            window.alert(msg);
                        }
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bindTeamScheduleSlotModalsOnce);
                } else {
                    bindTeamScheduleSlotModalsOnce();
                }
            })();
            </script>
        @endcan
    @endpush
