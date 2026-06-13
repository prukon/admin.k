@extends('layouts.admin2')

@php
    $activeTab = 'districts';
    $districtsHasActiveFilters = false;
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])

    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Справочники</h4>

        @include('admin.directories._section_tabs')

        <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Районы</h1>
                    <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                        @can('districts.view')
                            <button id="new-district"
                                    type="button"
                                    class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                    data-bs-toggle="modal"
                                    data-bs-target="#districtCreateModal">
                                <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-plus payments-report-toolbar-icon"></i>
                                </span>
                                <span class="payments-report-toolbar-label d-none d-sm-inline">Добавить</span>
                            </button>
                        @endcan

                        <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#districtsReportFiltersCollapse"
                                aria-expanded="{{ $districtsHasActiveFilters ? 'true' : 'false' }}"
                                aria-controls="districtsReportFiltersCollapse"
                                id="districtsReportFiltersToggle">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                            <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="dropdown payments-report-toolbar-dropdown">
                            <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                    type="button"
                                    id="districtsColumnsDropdown"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    aria-expanded="false"
                                    aria-haspopup="true"
                                    title="Какие колонки показывать в таблице">
                                <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-table-columns payments-report-toolbar-icon"></i>
                                </span>
                                <span class="payments-report-toolbar-label d-none d-sm-inline">Колонки</span>
                                <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                            </button>

                            <div class="dropdown-menu dropdown-menu-end payments-report-toolbar-dropdown-panel payments-report-columns-menu"
                                 aria-labelledby="districtsColumnsDropdown">
                                <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column-key="sort_order" id="colDistrictSort" checked>
                                    <label class="form-check-label" for="colDistrictSort">Сортировка</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column-key="name" id="colDistrictName" checked>
                                    <label class="form-check-label" for="colDistrictName">Название</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column-key="locations_count" id="colDistrictLocations" checked>
                                    <label class="form-check-label" for="colDistrictLocations">Объекты</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column-key="is_enabled_label" id="colDistrictStatus" checked>
                                    <label class="form-check-label" for="colDistrictStatus">Активен</label>
                                </div>

                                @can('districts.view')
                                    <div class="form-check">
                                        <input class="form-check-input column-toggle" type="checkbox" data-column-key="actions" id="colDistrictActions" checked>
                                        <label class="form-check-label" for="colDistrictActions">Действия</label>
                                    </div>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="collapse {{ $districtsHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="districtsReportFiltersCollapse">
            <form id="districts-report-filters" class="border rounded p-2 p-md-3 bg-light">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="filter-name">Название</label>
                        <input id="filter-name" class="form-control" type="text" placeholder="Поиск по названию">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="filter-status">Статус</label>
                        <select id="filter-status" class="form-select">
                            <option value="">Все районы</option>
                            <option value="active" selected>Только активные</option>
                            <option value="inactive">Только неактивные</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                        <button id="filter-apply" class="btn btn-primary payments-report-filters-submit" type="button">Применить</button>
                        <button id="filter-reset" class="btn btn-outline-secondary payments-report-filters-reset" type="button">Сброс</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table id="districts-table" class="table table-striped table-bordered align-middle w-100 dt-columns-managed">
                <thead>
                <tr>
                    <th>Сортировка</th>
                    <th>Название</th>
                    <th>Объекты</th>
                    <th>Активен</th>
                    @can('districts.view')
                        <th>Действия</th>
                    @endcan
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    @can('districts.view')
        <div class="modal fade" id="districtCreateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Добавить район</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="districtCreateForm">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Название*</label>
                                <input class="form-control" name="name" />
                                <div class="invalid-feedback" data-error-for="name"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Сортировка</label>
                                <input class="form-control" name="sort_order" type="number" min="0" value="0" />
                                <div class="invalid-feedback" data-error-for="sort_order"></div>
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
                        <button type="button" class="btn btn-primary" id="districtCreateSubmit">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="districtEditModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Редактировать район</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="districtEditForm">
                            @csrf
                            @method('put')
                            <input type="hidden" name="id" />
                            <div class="mb-3">
                                <label class="form-label">Название*</label>
                                <input class="form-control" name="name" />
                                <div class="invalid-feedback" data-error-for="name"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Сортировка</label>
                                <input class="form-control" name="sort_order" type="number" min="0" />
                                <div class="invalid-feedback" data-error-for="sort_order"></div>
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
                        <button type="button" class="btn btn-danger me-auto confirm-delete-modal" id="districtDeleteBtn">Удалить</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="button" class="btn btn-primary" id="districtEditSubmit">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            const canManageDistricts = @json(auth()->user()->can('districts.view'));
            const defaultFilterStatus = 'active';

            function districtsFilterParams() {
                return {
                    name: $('#filter-name').val() || '',
                    status: $('#filter-status').val() || ''
                };
            }

            const dtApi = KidsCrmDataTable.create('#districts-table', {
                columnsSettings: {
                    defaults: {
                        sort_order: true,
                        name: true,
                        locations_count: true,
                        is_enabled_label: true,
                        ...(canManageDistricts ? { actions: true } : {}),
                    },
                    urls: {
                        get: @json(route('admin.districts.columns-settings.get')),
                        save: @json(route('admin.districts.columns-settings.save')),
                    },
                    csrfToken: '{{ csrf_token() }}',
                },
                dataTable: {
                    ajax: {
                        url: @json(route('admin.districts.data')),
                        type: 'GET',
                        data: function (d) {
                            const params = districtsFilterParams();
                            d.name = params.name;
                            d.status = params.status;
                        }
                    },
                    order: [[0, 'asc'], [1, 'asc']],
                    language: @include('partials.datatables.ru')
                },
                columns: [
                    { key: 'sort_order', type: 'sort', data: 'sort_order' },
                    {
                        key: 'name',
                        type: 'link',
                        data: 'name',
                        className: 'dt-col-text',
                        linkClass: 'js-district-edit',
                        linkAttrs: function (row) {
                            return 'data-id="' + row.id + '"';
                        },
                    },
                    { key: 'locations_count', type: 'count', data: 'locations_count' },
                    {
                        key: 'is_enabled_label',
                        type: 'badge',
                        data: 'is_enabled_label',
                        badgeKey: 'is_enabled',
                        className: 'dt-col-badge text-center',
                    },
                    {
                        key: 'actions',
                        type: 'actions',
                        when: canManageDistricts,
                        render: function (data, type, row) {
                            return '<button type="button" class="btn btn-sm btn-outline-primary js-district-edit" data-id="' + row.id + '">Редактировать</button>';
                        }
                    },
                ],
            });

            function reloadDistrictsTable() {
                dtApi.reload({ keepPage: true });
            }

            $('#filter-apply').on('click', reloadDistrictsTable);
            $('#districts-report-filters').on('submit', function (e) {
                e.preventDefault();
                reloadDistrictsTable();
            });
            $('#filter-reset').on('click', function () {
                $('#filter-name').val('');
                $('#filter-status').val(defaultFilterStatus);
                reloadDistrictsTable();
            });
            $('#filter-name').on('keyup', function (e) {
                if (e.key === 'Enter') reloadDistrictsTable();
            });

            @can('districts.view')
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            function clearErrors(form) {
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
            }

            function applyErrors(form, errors) {
                Object.entries(errors || {}).forEach(([key, messages]) => {
                    const message = (messages && messages[0]) ? messages[0] : 'Ошибка';
                    const input = form.querySelector('[name="' + key + '"]');
                    const err = form.querySelector('[data-error-for="' + key + '"]');
                    if (input) input.classList.add('is-invalid');
                    if (err) err.textContent = message;
                });
            }

            async function postForm(url, form, method = 'POST') {
                const fd = new FormData(form);
                if (method !== 'POST') fd.set('_method', method);
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

            const createForm = document.getElementById('districtCreateForm');
            const editForm = document.getElementById('districtEditForm');

            document.getElementById('districtCreateSubmit')?.addEventListener('click', async () => {
                clearErrors(createForm);
                const { ok, status, data } = await postForm(@json(route('admin.districts.store')), createForm, 'POST');
                if (!ok && status === 422) {
                    applyErrors(createForm, data.errors || {});
                    return;
                }
                if (ok) {
                    createForm.reset();
                    bootstrap.Modal.getInstance(document.getElementById('districtCreateModal'))?.hide();
                    reloadDistrictsTable();
                }
            });

            $('#districts-table').on('click', '.js-district-edit', async function () {
                clearErrors(editForm);
                const id = $(this).data('id');
                const res = await fetch(@json(url('/admin/districts')) + '/' + id, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                editForm.querySelector('[name="id"]').value = data.id;
                editForm.querySelector('[name="name"]').value = data.name || '';
                editForm.querySelector('[name="sort_order"]').value = data.sort_order ?? 0;
                editForm.querySelector('[name="is_enabled"]').value = String(data.is_enabled ?? 1);
                new bootstrap.Modal(document.getElementById('districtEditModal')).show();
            });

            document.getElementById('districtEditSubmit')?.addEventListener('click', async () => {
                clearErrors(editForm);
                const id = editForm.querySelector('[name="id"]').value;
                const { ok, status, data } = await postForm(@json(url('/admin/districts')) + '/' + id, editForm, 'PUT');
                if (!ok && status === 422) {
                    applyErrors(editForm, data.errors || {});
                    return;
                }
                if (ok) {
                    bootstrap.Modal.getInstance(document.getElementById('districtEditModal'))?.hide();
                    reloadDistrictsTable();
                }
            });

            $(document).on('click', '#districtDeleteBtn', function () {
                const id = editForm.querySelector('[name="id"]').value;
                if (!id) return;
                const districtName = (editForm.querySelector('[name="name"]').value || '').trim();
                const messageText = districtName !== ''
                    ? 'Вы уверены, что хотите удалить район «' + districtName + '»?'
                    : 'Вы уверены, что хотите удалить район?';

                showConfirmDeleteModal('Удаление района', messageText, function () {
                    $.ajax({
                        url: @json(url('/admin/districts')) + '/' + id,
                        type: 'DELETE',
                        data: { _token: token },
                        success: function () {
                            bootstrap.Modal.getInstance(document.getElementById('districtEditModal'))?.hide();
                            reloadDistrictsTable();
                        },
                        error: function (xhr) {
                            const msg = xhr.responseJSON?.message
                                || xhr.responseJSON?.errors?.district?.[0]
                                || 'Произошла ошибка при удалении района.';
                            if (typeof showErrorModal === 'function') {
                                showErrorModal('Ошибка', msg, 1);
                            } else if ($('#errorModal').length) {
                                $('#error-modal-message').text(msg);
                                $('#errorModal').modal('show');
                            }
                        }
                    });
                });
            });
            @endcan
        });
    </script>
@endpush
