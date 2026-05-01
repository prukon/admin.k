@extends('layouts.admin2')

@section('content')
    <div class="main-content">
        <div class="d-flex align-items-center justify-content-between pt-3 pb-3">
            <h4 class="text-start mb-0">Расписание школы (слоты)</h4>
            @can('scheduleSlots.manage')
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#slotCreateModal">
                    Добавить слот
                </button>
            @endcan
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                        <tr>
                            <th style="width: 60px">#</th>
                            <th style="width: 80px">День</th>
                            <th style="width: 140px">Время</th>
                            <th>Группа</th>
                            <th style="width: 220px">Локация</th>
                            <th style="width: 230px">Период</th>
                            <th style="width: 100px" class="text-center">Активен</th>
                            @can('scheduleSlots.manage')
                                <th style="width: 140px"></th>
                            @endcan
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($slots as $s)
                            <tr>
                                <td>{{ $s->id }}</td>
                                <td>{{ $weekdays[$s->weekday] ?? $s->weekday }}</td>
                                <td>{{ substr((string) $s->time_start, 0, 5) }}–{{ substr((string) $s->time_end, 0, 5) }}</td>
                                <td>{{ $s->team?->title }}</td>
                                <td>{{ $s->location?->name ?? '—' }}</td>
                                <td>
                                    {{ $s->date_start?->format('d.m.Y') }}
                                    —
                                    {{ ($s->date_end?->format('Y-m-d') === '9999-12-31') ? '…' : $s->date_end?->format('d.m.Y') }}
                                </td>
                                <td class="text-center">{{ $s->is_enabled ? 'Да' : 'Нет' }}</td>
                                @can('scheduleSlots.manage')
                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary js-slot-edit"
                                                data-id="{{ $s->id }}">
                                            Редактировать
                                        </button>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $slots->links() }}
                </div>
            </div>
        </div>
    </div>

    @can('scheduleSlots.manage')
        {{-- Create --}}
        <div class="modal fade" id="slotCreateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Добавить слот</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="slotCreateForm">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Группа*</label>
                                <select class="form-control" name="team_id">
                                    <option value="">—</option>
                                    @foreach($teams as $t)
                                        <option value="{{ $t->id }}">{{ $t->title }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" data-error-for="team_id"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Локация</label>
                                <select class="form-control" name="location_id">
                                    <option value="">—</option>
                                    @foreach($locations as $l)
                                        <option value="{{ $l->id }}">{{ $l->name }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" data-error-for="location_id"></div>
                            </div>

                            <div class="row">
                                <div class="col-5 mb-3">
                                    <label class="form-label">День*</label>
                                    <select class="form-control" name="weekday">
                                        <option value="">—</option>
                                        @foreach($weekdays as $k => $label)
                                            <option value="{{ $k }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback" data-error-for="weekday"></div>
                                </div>
                                <div class="col-3 mb-3">
                                    <label class="form-label">С*</label>
                                    <input class="form-control" name="time_start" placeholder="09:00">
                                    <div class="invalid-feedback" data-error-for="time_start"></div>
                                </div>
                                <div class="col-4 mb-3">
                                    <label class="form-label">По*</label>
                                    <input class="form-control" name="time_end" placeholder="10:00">
                                    <div class="invalid-feedback" data-error-for="time_end"></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Период с*</label>
                                    <input class="form-control" name="date_start" placeholder="2026-05-01">
                                    <div class="invalid-feedback" data-error-for="date_start"></div>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Период по</label>
                                    <input class="form-control" name="date_end" placeholder="(пусто = без конца)">
                                    <div class="invalid-feedback" data-error-for="date_end"></div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Активен</label>
                                <select class="form-control" name="is_enabled">
                                    <option value="1" selected>Да</option>
                                    <option value="0">Нет</option>
                                </select>
                                <div class="invalid-feedback" data-error-for="is_enabled"></div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="button" class="btn btn-primary" id="slotCreateSubmit">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Edit --}}
        <div class="modal fade" id="slotEditModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Редактировать слот</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="slotEditForm">
                            @csrf
                            @method('put')
                            <input type="hidden" name="id" />

                            <div class="mb-3">
                                <label class="form-label">Группа*</label>
                                <select class="form-control" name="team_id">
                                    <option value="">—</option>
                                    @foreach($teams as $t)
                                        <option value="{{ $t->id }}">{{ $t->title }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" data-error-for="team_id"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Локация</label>
                                <select class="form-control" name="location_id">
                                    <option value="">—</option>
                                    @foreach($locations as $l)
                                        <option value="{{ $l->id }}">{{ $l->name }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" data-error-for="location_id"></div>
                            </div>

                            <div class="row">
                                <div class="col-5 mb-3">
                                    <label class="form-label">День*</label>
                                    <select class="form-control" name="weekday">
                                        <option value="">—</option>
                                        @foreach($weekdays as $k => $label)
                                            <option value="{{ $k }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback" data-error-for="weekday"></div>
                                </div>
                                <div class="col-3 mb-3">
                                    <label class="form-label">С*</label>
                                    <input class="form-control" name="time_start" placeholder="09:00">
                                    <div class="invalid-feedback" data-error-for="time_start"></div>
                                </div>
                                <div class="col-4 mb-3">
                                    <label class="form-label">По*</label>
                                    <input class="form-control" name="time_end" placeholder="10:00">
                                    <div class="invalid-feedback" data-error-for="time_end"></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Период с*</label>
                                    <input class="form-control" name="date_start" placeholder="2026-05-01">
                                    <div class="invalid-feedback" data-error-for="date_start"></div>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Период по</label>
                                    <input class="form-control" name="date_end" placeholder="(пусто = без конца)">
                                    <div class="invalid-feedback" data-error-for="date_end"></div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Активен</label>
                                <select class="form-control" name="is_enabled">
                                    <option value="1">Да</option>
                                    <option value="0">Нет</option>
                                </select>
                                <div class="invalid-feedback" data-error-for="is_enabled"></div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-danger me-auto" id="slotDeleteBtn">Удалить</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="button" class="btn btn-primary" id="slotEditSubmit">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@section('scripts')
    @parent
    @can('scheduleSlots.manage')
        <script>
            (function () {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                function clearErrors(form) {
                    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                    form.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
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
                            'X-CSRF-TOKEN': token,
                        }
                    });
                    const data = await res.json().catch(() => ({}));
                    return { ok: res.ok, status: res.status, data };
                }

                const createForm = document.getElementById('slotCreateForm');
                const editForm = document.getElementById('slotEditForm');

                document.getElementById('slotCreateSubmit')?.addEventListener('click', async () => {
                    clearErrors(createForm);
                    const { ok, status, data } = await postForm(`{{ route('admin.team-schedule-slots.store') }}`, createForm, 'POST');
                    if (!ok && status === 422) {
                        applyErrors(createForm, data.errors || {});
                        if (data.message && !Object.keys(data.errors || {}).length) {
                            applyErrors(createForm, { weekday: [data.message] });
                        }
                        return;
                    }
                    window.location.reload();
                });

                document.querySelectorAll('.js-slot-edit').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        clearErrors(editForm);
                        const id = btn.getAttribute('data-id');
                        const res = await fetch(`/admin/team-schedule-slots/${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        editForm.querySelector('[name="id"]').value = data.id;
                        editForm.querySelector('[name="team_id"]').value = String(data.team_id ?? '');
                        editForm.querySelector('[name="location_id"]').value = data.location_id ? String(data.location_id) : '';
                        editForm.querySelector('[name="weekday"]').value = String(data.weekday ?? '');
                        editForm.querySelector('[name="time_start"]').value = data.time_start || '';
                        editForm.querySelector('[name="time_end"]').value = data.time_end || '';
                        editForm.querySelector('[name="date_start"]').value = data.date_start || '';
                        editForm.querySelector('[name="date_end"]').value = data.date_end || '';
                        editForm.querySelector('[name="is_enabled"]').value = String(data.is_enabled ?? 1);
                        const modal = new bootstrap.Modal(document.getElementById('slotEditModal'));
                        modal.show();
                    });
                });

                document.getElementById('slotEditSubmit')?.addEventListener('click', async () => {
                    clearErrors(editForm);
                    const id = editForm.querySelector('[name="id"]').value;
                    const { ok, status, data } = await postForm(`/admin/team-schedule-slots/${id}`, editForm, 'PUT');
                    if (!ok && status === 422) {
                        applyErrors(editForm, data.errors || {});
                        if (data.message && !Object.keys(data.errors || {}).length) {
                            applyErrors(editForm, { weekday: [data.message] });
                        }
                        return;
                    }
                    window.location.reload();
                });

                document.getElementById('slotDeleteBtn')?.addEventListener('click', async () => {
                    const id = editForm.querySelector('[name="id"]').value;
                    if (!id) return;
                    const res = await fetch(`/admin/team-schedule-slots/${id}`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token,
                        },
                        body: new URLSearchParams({ _method: 'DELETE' })
                    });
                    if (res.ok) {
                        window.location.reload();
                    }
                });
            })();
        </script>
    @endcan
@endsection

