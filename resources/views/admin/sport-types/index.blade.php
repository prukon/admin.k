@extends('layouts.admin2')

@php
    $activeTab = 'sport-types';
    $sportTypesHasActiveFilters = false;
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])

    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Справочники</h4>

        @include('admin.directories._section_tabs')

        <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Виды спорта</h1>
                    <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                        @can('sport_types.manage')
                            <button id="new-sport-type"
                                    type="button"
                                    class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                    data-bs-toggle="modal"
                                    data-bs-target="#sportTypeCreateModal">
                                <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-plus payments-report-toolbar-icon"></i>
                                </span>
                                <span class="payments-report-toolbar-label d-none d-sm-inline">Добавить</span>
                            </button>
                        @endcan

                        <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#sportTypesReportFiltersCollapse"
                                aria-expanded="{{ $sportTypesHasActiveFilters ? 'true' : 'false' }}"
                                aria-controls="sportTypesReportFiltersCollapse"
                                id="sportTypesReportFiltersToggle">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                            <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="dropdown payments-report-toolbar-dropdown">
                            <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                    type="button"
                                    id="sportTypesColumnsDropdown"
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
                                 aria-labelledby="sportTypesColumnsDropdown">
                                <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column-key="sort" id="colSportTypeSort" checked>
                                    <label class="form-check-label" for="colSportTypeSort">Сортировка</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column-key="name" id="colSportTypeName" checked>
                                    <label class="form-check-label" for="colSportTypeName">Название</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column-key="teams_count" id="colSportTypeTeams" checked>
                                    <label class="form-check-label" for="colSportTypeTeams">Группы</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column-key="is_enabled_label" id="colSportTypeStatus" checked>
                                    <label class="form-check-label" for="colSportTypeStatus">Активен</label>
                                </div>

                                @can('sport_types.manage')
                                    <div class="form-check">
                                        <input class="form-check-input column-toggle" type="checkbox" data-column-key="actions" id="colSportTypeActions" checked>
                                        <label class="form-check-label" for="colSportTypeActions">Действия</label>
                                    </div>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="collapse {{ $sportTypesHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="sportTypesReportFiltersCollapse">
            <form id="sport-types-report-filters" class="border rounded p-2 p-md-3 bg-light">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="filter-name">Название</label>
                        <input id="filter-name" class="form-control" type="text" placeholder="Поиск по названию">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="filter-status">Статус</label>
                        <select id="filter-status" class="form-select">
                            <option value="">Все виды спорта</option>
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
            <table id="sport-types-table" class="table table-striped table-bordered align-middle w-100 dt-columns-managed">
                <thead>
                <tr>
                    <th>Сортировка</th>
                    <th>Название</th>
                    <th>Группы</th>
                    <th>Активен</th>
                    @can('sport_types.manage')
                        <th>Действия</th>
                    @endcan
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    @can('sport_types.manage')
        <div class="modal fade" id="sportTypeCreateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Добавить вид спорта</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="sportTypeCreateForm">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Название*</label>
                                <input class="form-control" name="name" />
                                <div class="invalid-feedback" data-error-for="name"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Описание</label>
                                <input class="form-control" name="description" />
                                <div class="invalid-feedback" data-error-for="description"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Сортировка</label>
                                <input class="form-control" name="sort" type="number" min="0" value="0" />
                                <div class="invalid-feedback" data-error-for="sort"></div>
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
                        <button type="button" class="btn btn-primary" id="sportTypeCreateSubmit">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="sportTypeEditModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Редактировать вид спорта</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="sportTypeEditForm">
                            @csrf
                            @method('put')
                            <input type="hidden" name="id" />
                            <div class="mb-3">
                                <label class="form-label">Название*</label>
                                <input class="form-control" name="name" />
                                <div class="invalid-feedback" data-error-for="name"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Описание</label>
                                <input class="form-control" name="description" />
                                <div class="invalid-feedback" data-error-for="description"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Сортировка</label>
                                <input class="form-control" name="sort" type="number" min="0" />
                                <div class="invalid-feedback" data-error-for="sort"></div>
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
                        <button type="button" class="btn btn-danger me-auto confirm-delete-modal" id="sportTypeDeleteBtn">Удалить</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="button" class="btn btn-primary" id="sportTypeEditSubmit">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            const canManageSportTypes = @json(auth()->user()->can('sport_types.manage'));
            const defaultFilterStatus = 'active';

            function sportTypesFilterParams() {
                return {
                    name: $('#filter-name').val() || '',
                    status: $('#filter-status').val() || ''
                };
            }

            const dtApi = KidsCrmDataTable.create('#sport-types-table', {
                columnsSettings: {
                    defaults: {
                        sort: true,
                        name: true,
                        teams_count: true,
                        is_enabled_label: true,
                        ...(canManageSportTypes ? { actions: true } : {}),
                    },
                    urls: {
                        get: @json(route('admin.sport-types.columns-settings.get')),
                        save: @json(route('admin.sport-types.columns-settings.save')),
                    },
                    csrfToken: '{{ csrf_token() }}',
                },
                dataTable: {
                    ajax: {
                        url: @json(route('admin.sport-types.data')),
                        type: 'GET',
                        data: function (d) {
                            const params = sportTypesFilterParams();
                            d.name = params.name;
                            d.status = params.status;
                        }
                    },
                    order: [[1, 'asc']],
                    language: @include('partials.datatables.ru')
                },
                columns: [
                    { key: 'sort', type: 'sort', data: 'sort' },
                    { key: 'name', type: 'text-long', data: 'name' },
                    { key: 'teams_count', type: 'count', data: 'teams_count' },
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
                        when: canManageSportTypes,
                        render: function (data, type, row) {
                            return '<button type="button" class="btn btn-sm btn-outline-primary js-sport-type-edit" data-id="' + row.id + '">Редактировать</button>';
                        }
                    },
                ],
            });

            const table = dtApi.table;

            function reloadSportTypesTable() {
                dtApi.reload({ keepPage: true });
            }

            $('#filter-apply').on('click', reloadSportTypesTable);
            $('#sport-types-report-filters').on('submit', function (e) {
                e.preventDefault();
                reloadSportTypesTable();
            });
            $('#filter-reset').on('click', function () {
                $('#filter-name').val('');
                $('#filter-status').val(defaultFilterStatus);
                reloadSportTypesTable();
            });
            $('#filter-name').on('keyup', function (e) {
                if (e.key === 'Enter') reloadSportTypesTable();
            });

            @can('sport_types.manage')
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

            const createForm = document.getElementById('sportTypeCreateForm');
            const editForm = document.getElementById('sportTypeEditForm');

            document.getElementById('sportTypeCreateSubmit')?.addEventListener('click', async () => {
                clearErrors(createForm);
                const { ok, status, data } = await postForm(@json(route('admin.sport-types.store')), createForm, 'POST');
                if (!ok && status === 422) {
                    applyErrors(createForm, data.errors || {});
                    return;
                }
                if (ok) {
                    createForm.reset();
                    bootstrap.Modal.getInstance(document.getElementById('sportTypeCreateModal'))?.hide();
                    reloadSportTypesTable();
                }
            });

            $('#sport-types-table').on('click', '.js-sport-type-edit', async function () {
                clearErrors(editForm);
                const id = $(this).data('id');
                const res = await fetch(@json(url('/admin/sport-types')) + '/' + id, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                editForm.querySelector('[name="id"]').value = data.id;
                editForm.querySelector('[name="name"]').value = data.name || '';
                editForm.querySelector('[name="description"]').value = data.description || '';
                editForm.querySelector('[name="sort"]').value = data.sort ?? 0;
                editForm.querySelector('[name="is_enabled"]').value = String(data.is_enabled ?? 1);
                new bootstrap.Modal(document.getElementById('sportTypeEditModal')).show();
            });

            document.getElementById('sportTypeEditSubmit')?.addEventListener('click', async () => {
                clearErrors(editForm);
                const id = editForm.querySelector('[name="id"]').value;
                const { ok, status, data } = await postForm(@json(url('/admin/sport-types')) + '/' + id, editForm, 'PUT');
                if (!ok && status === 422) {
                    applyErrors(editForm, data.errors || {});
                    return;
                }
                if (ok) {
                    bootstrap.Modal.getInstance(document.getElementById('sportTypeEditModal'))?.hide();
                    reloadSportTypesTable();
                }
            });

            $(document).on('click', '#sportTypeDeleteBtn', function () {
                const id = editForm.querySelector('[name="id"]').value;
                if (!id) return;
                const sportTypeName = (editForm.querySelector('[name="name"]').value || '').trim();
                const messageText = sportTypeName !== ''
                    ? 'Вы уверены, что хотите удалить вид спорта «' + sportTypeName + '»?'
                    : 'Вы уверены, что хотите удалить вид спорта?';

                showConfirmDeleteModal('Удаление вида спорта', messageText, function () {
                    $.ajax({
                        url: @json(url('/admin/sport-types')) + '/' + id,
                        type: 'DELETE',
                        data: { _token: token },
                        success: function () {
                            bootstrap.Modal.getInstance(document.getElementById('sportTypeEditModal'))?.hide();
                            reloadSportTypesTable();
                        }
                    });
                });
            });
            @endcan
        });
    </script>
@endpush
