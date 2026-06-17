@extends('layouts.admin2')

@php
    $endpoints = $roleStaffEndpoints ?? [];
    $tableKey = $tableKey ?? ('role_staff_' . ($role->name ?? 'unknown'));
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])

    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Пользователи</h4>
        <div class="">
            @include('admin.users._users_section_tabs')

            <div class="tab-content">
                <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
                    <div class="card-body px-3 py-3">
                        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">{{ $pageTitle ?? $role->label }}</h1>
                            <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                                <button type="button"
                                        class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#roleStaffCreateModal">
                                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                        <i class="fas fa-user-plus payments-report-toolbar-icon"></i>
                                    </span>
                                    <span class="payments-report-toolbar-label d-none d-sm-inline">Добавить</span>
                                </button>

                                <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#roleStaffFiltersCollapse"
                                        aria-expanded="{{ ($roleStaffHasActiveFilters ?? false) ? 'true' : 'false' }}"
                                        aria-controls="roleStaffFiltersCollapse"
                                        id="roleStaffFiltersToggle">
                                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                        <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                                    </span>
                                    <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                                    <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                                </button>

                                <div class="dropdown payments-report-toolbar-dropdown">
                                    <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                            type="button"
                                            id="roleStaffColumnsDropdown"
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
                                         aria-labelledby="roleStaffColumnsDropdown">
                                        <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="avatar" id="colRoleStaffAvatar" checked>
                                            <label class="form-check-label" for="colRoleStaffAvatar">Аватар</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="full_name" id="colRoleStaffFullName" checked>
                                            <label class="form-check-label" for="colRoleStaffFullName">ФИО</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="email" id="colRoleStaffEmail" checked>
                                            <label class="form-check-label" for="colRoleStaffEmail">Email</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="phone" id="colRoleStaffPhone" checked>
                                            <label class="form-check-label" for="colRoleStaffPhone">Телефон</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="is_enabled" id="colRoleStaffStatus" checked>
                                            <label class="form-check-label" for="colRoleStaffStatus">Активен</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="actions" id="colRoleStaffActions" checked>
                                            <label class="form-check-label" for="colRoleStaffActions">Действия</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="collapse {{ ($roleStaffHasActiveFilters ?? false) ? 'show' : '' }} mb-2 mb-md-3" id="roleStaffFiltersCollapse">
                    <form id="role-staff-filters" class="border rounded p-2 p-md-3 bg-light">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="role-staff-filter-name">Имя</label>
                                <input id="role-staff-filter-name" class="form-control" type="text" placeholder="Поиск по ФИО, email, телефону">
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="role-staff-filter-status">Статус</label>
                                <select id="role-staff-filter-status" class="form-select">
                                    <option value="">Все</option>
                                    <option value="active" selected>Только активные</option>
                                    <option value="inactive">Только неактивные</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                                <button id="role-staff-filter-apply" class="btn btn-primary payments-report-filters-submit" type="button">Применить</button>
                                <button id="role-staff-filter-reset" class="btn btn-outline-secondary payments-report-filters-reset" type="button">Сброс</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table id="role-staff-table" class="table table-striped table-bordered align-middle w-100 dt-columns-managed">
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>Аватар</th>
                            <th>ФИО</th>
                            <th>Email</th>
                            <th>Телефон</th>
                            <th class="text-center">Активен</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="roleStaffCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Создание: {{ $role->label }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="roleStaffCreateForm" class="text-start">
                        @csrf
                        <div class="alert alert-danger d-none js-form-error mb-3" role="alert"></div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role-staff-create-name">Имя*</label>
                                <input type="text" class="form-control" name="name" id="role-staff-create-name">
                                <div class="invalid-feedback d-block" data-error-for="name"></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role-staff-create-lastname">Фамилия*</label>
                                <input type="text" class="form-control" name="lastname" id="role-staff-create-lastname">
                                <div class="invalid-feedback d-block" data-error-for="lastname"></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role-staff-create-email">Email</label>
                                <input type="email" class="form-control" name="email" id="role-staff-create-email" autocomplete="off">
                                <div class="invalid-feedback d-block" data-error-for="email"></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role-staff-create-phone">Телефон</label>
                                @include('includes.fields.phone-input', ['name' => 'phone', 'id' => 'role-staff-create-phone'])
                                <div class="invalid-feedback d-block" data-error-for="phone"></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role-staff-create-password">Пароль</label>
                                <input type="password" class="form-control" name="password" id="role-staff-create-password" autocomplete="new-password">
                                <div class="invalid-feedback d-block" data-error-for="password"></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role-staff-create-enabled">Активен</label>
                                <select class="form-select" name="is_enabled" id="role-staff-create-enabled">
                                    <option value="1" selected>Да</option>
                                    <option value="0">Нет</option>
                                </select>
                                <div class="invalid-feedback d-block" data-error-for="is_enabled"></div>
                            </div>
                        </div>
                        <div class="modal-footer-modal-user pt-3 mt-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                            <button type="button" class="btn btn-primary" id="roleStaffCreateSubmit">Создать</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="roleStaffEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content background-color-grey">
                <div class="modal-header">
                    <h5 class="modal-title">Редактирование: {{ $role->label }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="roleStaffEditForm" class="text-start">
                        @csrf
                        @method('put')
                        <div class="alert alert-danger d-none js-form-error mb-3" role="alert"></div>
                        <input type="hidden" name="id" id="role-staff-edit-user-id">

                        <div class="mb-3 d-flex flex-column align-items-center">
                            <div class="avatar_wrapper">
                                <div class="avatar">
                                    <div class="avatar-clip">
                                        <img src="{{ asset('img/default-avatar.png') }}" alt="Avatar">
                                    </div>
                                    <div class="avatar-actions">
                                        <button class="dropdown-item js-open-photo" type="button">
                                            <i class="fa-solid fa-image"></i> Открыть фото
                                        </button>
                                        <button class="dropdown-item js-change-photo" type="button"
                                                data-bs-toggle="modal" data-bs-target="#avatarEditModal">
                                            <i class="fa-solid fa-pen-to-square"></i> Изменить фото
                                        </button>
                                        <button class="dropdown-item text-danger js-delete-photo" type="button">
                                            <i class="fa-solid fa-trash"></i> Удалить фото
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role-staff-edit-lastname">Фамилия*</label>
                                <input type="text" class="form-control" name="lastname" id="role-staff-edit-lastname">
                                <div class="invalid-feedback d-block" data-error-for="lastname"></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role-staff-edit-name">Имя*</label>
                                <input type="text" class="form-control" name="name" id="role-staff-edit-name">
                                <div class="invalid-feedback d-block" data-error-for="name"></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role-staff-edit-email">Email</label>
                                <input type="email" class="form-control" name="email" id="role-staff-edit-email" autocomplete="off">
                                <div class="invalid-feedback d-block" data-error-for="email"></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role-staff-edit-phone">Телефон</label>
                                @include('includes.fields.phone-input', ['name' => 'phone', 'id' => 'role-staff-edit-phone'])
                                <div class="invalid-feedback d-block" data-error-for="phone"></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role-staff-edit-enabled">Активен</label>
                                <select class="form-select" name="is_enabled" id="role-staff-edit-enabled">
                                    <option value="1">Да</option>
                                    <option value="0">Нет</option>
                                </select>
                                <div class="invalid-feedback d-block" data-error-for="is_enabled"></div>
                            </div>
                        </div>

                        @if($canChangePassword ?? false)
                            <div class="border-top pt-3 mt-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="role-staff-change-password-btn">Сменить пароль</button>
                                <div id="role-staff-change-pass-wrap" class="wrap-change-password mt-2" style="display:none;">
                                    <label class="form-label" for="role-staff-new-password">Новый пароль</label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="role-staff-new-password" minlength="8" autocomplete="new-password">
                                        <span class="fa fa-fw fa-eye field-icon toggle-password"></span>
                                    </div>
                                    <div class="text-danger small mt-1" id="role-staff-password-error-message" style="display:none;">Пароль не короче 8 символов</div>
                                    <div class="d-flex gap-2 mt-2">
                                        <button type="button" class="btn btn-primary btn-sm" id="role-staff-apply-password-btn">Сохранить пароль</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="role-staff-cancel-change-password-btn">Отмена</button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="modal-footer-modal-user pt-3 mt-2 d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-danger" id="roleStaffDeleteBtn">Удалить</button>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                                <button type="button" class="btn btn-primary" id="roleStaffEditSubmit">Сохранить</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @include('includes.modal.editAvatar')
@endsection

@push('scripts')
    <script>
        window.__roleStaffPageConfig = {
            dataUrl: @json($endpoints['data'] ?? ''),
            storeUrl: @json($endpoints['store'] ?? ''),
            showUrlTemplate: @json($endpoints['show'] ?? ''),
            updateUrlTemplate: @json($endpoints['update'] ?? ''),
            destroyUrlTemplate: @json($endpoints['destroy'] ?? ''),
            columnsSettingsUrl: @json($endpoints['columns_settings'] ?? ''),
            passwordUpdateUrlTemplate: @json($endpoints['password_update'] ?? ''),
            tableKey: @json($tableKey),
            defaultAvatar: @json(asset('img/default-avatar.png')),
            canChangePassword: @json($canChangePassword ?? false),
        };
    </script>
    <script>
        $(document).ready(function () {
            const cfg = window.__roleStaffPageConfig;
            const defaultAvatar = cfg.defaultAvatar;
            const defaultFilterStatus = 'active';
            const tableKeyQuery = '?table_key=' + encodeURIComponent(cfg.tableKey);

            function urlFromTemplate(template, id) {
                return (template || '').replace('__ID__', String(id));
            }

            function filterParams() {
                return {
                    name: $('#role-staff-filter-name').val() || '',
                    status: $('#role-staff-filter-status').val() || '',
                };
            }

            function hasNonDefaultFilters() {
                const params = filterParams();
                return params.name !== '' || params.status !== defaultFilterStatus;
            }

            function syncFiltersCollapseState() {
                const hasActive = hasNonDefaultFilters();
                const collapseEl = document.getElementById('roleStaffFiltersCollapse');
                const $toggle = $('#roleStaffFiltersToggle');
                if (collapseEl && hasActive && !collapseEl.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                }
                if ($toggle.length && collapseEl) {
                    $toggle.attr('aria-expanded', collapseEl.classList.contains('show') ? 'true' : 'false');
                }
            }

            const dtApi = KidsCrmDataTable.create('#role-staff-table', {
                columnsSettings: {
                    defaults: {
                        avatar: true,
                        full_name: true,
                        email: true,
                        phone: true,
                        is_enabled: true,
                        actions: true,
                    },
                    urls: {
                        get: cfg.columnsSettingsUrl + tableKeyQuery,
                        save: cfg.columnsSettingsUrl + tableKeyQuery,
                    },
                    csrfToken: $('meta[name="csrf-token"]').attr('content'),
                },
                dataTable: {
                    ajax: {
                        url: cfg.dataUrl,
                        type: 'GET',
                        data: function (d) {
                            const params = filterParams();
                            d.name = params.name;
                            d.status = params.status;
                        },
                    },
                    order: [[2, 'asc']],
                    language: @include('partials.datatables.ru'),
                },
                columns: [
                    { type: 'rownum' },
                    {
                        key: 'avatar',
                        type: 'image',
                        data: 'avatar',
                        name: 'avatar',
                        orderable: false,
                        searchable: false,
                        fallbackUrl: defaultAvatar,
                    },
                    {
                        key: 'full_name',
                        type: 'link',
                        data: 'full_name',
                        name: 'full_name',
                        className: 'dt-col-text',
                        linkClass: 'js-role-staff-edit',
                        linkAttrs: function (row) {
                            return 'data-id="' + row.id + '"';
                        },
                    },
                    { key: 'email', type: 'text', data: 'email', name: 'email' },
                    { key: 'phone', type: 'text', data: 'phone', name: 'phone' },
                    {
                        key: 'is_enabled',
                        type: 'text',
                        data: 'status_label',
                        name: 'is_enabled',
                        className: 'dt-col-badge text-center',
                    },
                    {
                        key: 'actions',
                        type: 'actions',
                        render: function (data, type, row) {
                            return '<button type="button" class="btn btn-sm btn-outline-primary js-role-staff-edit" data-id="' + row.id + '">Редактировать</button>';
                        },
                    },
                ],
            });

            function reloadTable() {
                dtApi.reload({ keepPage: true });
                syncFiltersCollapseState();
            }

            window.__reloadRoleStaffTable = reloadTable;

            $('#role-staff-filter-apply').on('click', reloadTable);
            $('#role-staff-filters').on('submit', function (e) {
                e.preventDefault();
                reloadTable();
            });
            $('#role-staff-filter-reset').on('click', function () {
                $('#role-staff-filter-name').val('');
                $('#role-staff-filter-status').val(defaultFilterStatus);
                reloadTable();
            });
            $('#role-staff-filter-name').on('keyup', function (e) {
                if (e.key === 'Enter') reloadTable();
            });

            const meta = document.querySelector('meta[name="csrf-token"]');
            const token = meta ? meta.getAttribute('content') : '';

            function clearErrors(form) {
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
                const box = form.querySelector('.js-form-error');
                if (box) {
                    box.textContent = '';
                    box.classList.add('d-none');
                }
            }

            function showFormError(form, message) {
                const box = form.querySelector('.js-form-error');
                if (!box || !message) return;
                box.textContent = message;
                box.classList.remove('d-none');
            }

            function applyErrors(form, errors) {
                Object.entries(errors || {}).forEach(([key, messages]) => {
                    const baseKey = key.split('.')[0];
                    const input = form.querySelector('[name="' + key + '"]') || form.querySelector('[name="' + baseKey + '"]');
                    const err = form.querySelector('[data-error-for="' + key + '"]') || form.querySelector('[data-error-for="' + baseKey + '"]');
                    if (input) input.classList.add('is-invalid');
                    if (err) err.textContent = (messages && messages[0]) ? messages[0] : 'Ошибка';
                });
            }

            async function postForm(url, form, method) {
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
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json().catch(() => ({}));
                return { ok: res.ok, status: res.status, data };
            }

            const createForm = document.getElementById('roleStaffCreateForm');
            const editForm = document.getElementById('roleStaffEditForm');

            document.getElementById('roleStaffCreateSubmit')?.addEventListener('click', async function () {
                clearErrors(createForm);
                const result = await postForm(cfg.storeUrl, createForm, 'POST');
                if (result.ok) {
                    reloadTable();
                    bootstrap.Modal.getInstance(document.getElementById('roleStaffCreateModal'))?.hide();
                    createForm.reset();
                    return;
                }
                if (result.status === 422) {
                    applyErrors(createForm, result.data.errors || {});
                    if (result.data.message) showFormError(createForm, result.data.message);
                    return;
                }
                showFormError(createForm, result.data.message || 'Не удалось сохранить');
            });

            $(document).on('click', '.js-role-staff-edit', async function (e) {
                e.preventDefault();
                const userId = $(this).data('id');
                clearErrors(editForm);
                const res = await fetch(urlFromTemplate(cfg.showUrlTemplate, userId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) return;
                const data = await res.json();
                $('#role-staff-edit-user-id').val(data.id);
                $('#role-staff-edit-name').val(data.name || '');
                $('#role-staff-edit-lastname').val(data.lastname || '');
                $('#role-staff-edit-email').val(data.email || '');
                $('#role-staff-edit-phone').val(data.phone || '');
                $('#role-staff-edit-enabled').val(String(data.is_enabled ?? 1));
                const clipImg = document.querySelector('#roleStaffEditModal .avatar-clip img');
                if (clipImg) clipImg.src = data.avatar_url || defaultAvatar;
                if (typeof window.setSelectedUserContext === 'function') {
                    window.setSelectedUserContext({ id: data.id, image: data.image, image_crop: data.image_crop });
                }
                new bootstrap.Modal(document.getElementById('roleStaffEditModal')).show();
            });

            document.getElementById('roleStaffEditSubmit')?.addEventListener('click', async function () {
                const userId = $('#role-staff-edit-user-id').val();
                clearErrors(editForm);
                const result = await postForm(urlFromTemplate(cfg.updateUrlTemplate, userId), editForm, 'PUT');
                if (result.ok) {
                    reloadTable();
                    bootstrap.Modal.getInstance(document.getElementById('roleStaffEditModal'))?.hide();
                    return;
                }
                if (result.status === 422) {
                    applyErrors(editForm, result.data.errors || {});
                    if (result.data.message) showFormError(editForm, result.data.message);
                    return;
                }
                showFormError(editForm, result.data.message || 'Не удалось сохранить');
            });

            document.getElementById('roleStaffDeleteBtn')?.addEventListener('click', async function () {
                const userId = $('#role-staff-edit-user-id').val();
                if (!userId || !confirm('Удалить пользователя?')) return;
                const res = await fetch(urlFromTemplate(cfg.destroyUrlTemplate, userId), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (res.ok) {
                    bootstrap.Modal.getInstance(document.getElementById('roleStaffEditModal'))?.hide();
                    reloadTable();
                }
            });

            if (cfg.canChangePassword) {
                document.getElementById('role-staff-change-password-btn')?.addEventListener('click', function () {
                    this.style.display = 'none';
                    document.getElementById('role-staff-change-pass-wrap').style.display = '';
                });
                document.getElementById('role-staff-cancel-change-password-btn')?.addEventListener('click', function () {
                    document.getElementById('role-staff-change-password-btn').style.display = '';
                    document.getElementById('role-staff-change-pass-wrap').style.display = 'none';
                    document.getElementById('role-staff-new-password').value = '';
                });
                document.getElementById('role-staff-apply-password-btn')?.addEventListener('click', function () {
                    const userId = $('#role-staff-edit-user-id').val();
                    const newPassword = document.getElementById('role-staff-new-password').value || '';
                    const err = document.getElementById('role-staff-password-error-message');
                    if (newPassword.length < 8) {
                        if (err) err.style.display = '';
                        return;
                    }
                    if (err) err.style.display = 'none';
                    $.ajax({
                        url: urlFromTemplate(cfg.passwordUpdateUrlTemplate, userId),
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': token },
                        data: { password: newPassword },
                    });
                });
            }
        });
    </script>
@endpush
