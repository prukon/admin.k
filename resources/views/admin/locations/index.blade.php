@extends('layouts.admin2')

@section('content')
    <div class="main-content">
        <div class="d-flex align-items-center justify-content-between pt-3 pb-3">
            <h4 class="text-start mb-0">Локации</h4>
            @can('locations.manage')
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#locationCreateModal">
                    Добавить
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
                            <th>Название</th>
                            <th>Адрес</th>
                            <th style="width: 120px" class="text-center">Активна</th>
                            @can('locations.manage')
                                <th style="width: 140px"></th>
                            @endcan
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($locations as $l)
                            <tr>
                                <td>{{ $l->id }}</td>
                                <td>{{ $l->name }}</td>
                                <td>{{ $l->address }}</td>
                                <td class="text-center">{{ $l->is_enabled ? 'Да' : 'Нет' }}</td>
                                @can('locations.manage')
                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary js-location-edit"
                                                data-id="{{ $l->id }}">
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
                    {{ $locations->links() }}
                </div>
            </div>
        </div>
    </div>

    @can('locations.manage')
        {{-- Create --}}
        <div class="modal fade" id="locationCreateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Добавить локацию</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="locationCreateForm">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Название*</label>
                                <input class="form-control" name="name" />
                                <div class="invalid-feedback" data-error-for="name"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Адрес</label>
                                <input class="form-control" name="address" />
                                <div class="invalid-feedback" data-error-for="address"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Описание</label>
                                <input class="form-control" name="description" />
                                <div class="invalid-feedback" data-error-for="description"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Активна</label>
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
                        <button type="button" class="btn btn-primary" id="locationCreateSubmit">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Edit --}}
        <div class="modal fade" id="locationEditModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Редактировать локацию</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="locationEditForm">
                            @csrf
                            @method('put')
                            <input type="hidden" name="id" />
                            <div class="mb-3">
                                <label class="form-label">Название*</label>
                                <input class="form-control" name="name" />
                                <div class="invalid-feedback" data-error-for="name"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Адрес</label>
                                <input class="form-control" name="address" />
                                <div class="invalid-feedback" data-error-for="address"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Описание</label>
                                <input class="form-control" name="description" />
                                <div class="invalid-feedback" data-error-for="description"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Активна</label>
                                <select class="form-control" name="is_enabled">
                                    <option value="1">Да</option>
                                    <option value="0">Нет</option>
                                </select>
                                <div class="invalid-feedback" data-error-for="is_enabled"></div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-danger me-auto" id="locationDeleteBtn">Удалить</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="button" class="btn btn-primary" id="locationEditSubmit">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@section('scripts')
    @parent
    @can('locations.manage')
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

                const createForm = document.getElementById('locationCreateForm');
                const editForm = document.getElementById('locationEditForm');

                document.getElementById('locationCreateSubmit')?.addEventListener('click', async () => {
                    clearErrors(createForm);
                    const { ok, status, data } = await postForm(`{{ route('admin.locations.store') }}`, createForm, 'POST');
                    if (!ok && status === 422) {
                        applyErrors(createForm, data.errors || {});
                        return;
                    }
                    window.location.reload();
                });

                document.querySelectorAll('.js-location-edit').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        clearErrors(editForm);
                        const id = btn.getAttribute('data-id');
                        const res = await fetch(`/admin/locations/${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        editForm.querySelector('[name="id"]').value = data.id;
                        editForm.querySelector('[name="name"]').value = data.name || '';
                        editForm.querySelector('[name="address"]').value = data.address || '';
                        editForm.querySelector('[name="description"]').value = data.description || '';
                        editForm.querySelector('[name="is_enabled"]').value = String(data.is_enabled ?? 1);
                        const modal = new bootstrap.Modal(document.getElementById('locationEditModal'));
                        modal.show();
                    });
                });

                document.getElementById('locationEditSubmit')?.addEventListener('click', async () => {
                    clearErrors(editForm);
                    const id = editForm.querySelector('[name="id"]').value;
                    const { ok, status, data } = await postForm(`/admin/locations/${id}`, editForm, 'PUT');
                    if (!ok && status === 422) {
                        applyErrors(editForm, data.errors || {});
                        return;
                    }
                    window.location.reload();
                });

                document.getElementById('locationDeleteBtn')?.addEventListener('click', async () => {
                    const id = editForm.querySelector('[name="id"]').value;
                    if (!id) return;
                    const res = await fetch(`/admin/locations/${id}`, {
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

