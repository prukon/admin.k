@extends('layouts.admin2')

@php
    $activeTab = 'legal-entities';
    $legalEntitiesHasActiveFilters = false;
    $businessTypes = \App\Enums\PartnerLegalEntityBusinessType::cases();
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])

    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Справочники</h4>

        @include('admin.directories._section_tabs')

        <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Юр. лица</h1>
                    <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                        @can('legal_entities.manage')
                            <button id="new-legal-entity"
                                    type="button"
                                    class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                    data-bs-toggle="modal"
                                    data-bs-target="#legalEntityCreateModal">
                                <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-plus payments-report-toolbar-icon"></i>
                                </span>
                                <span class="payments-report-toolbar-label d-none d-sm-inline">Добавить</span>
                            </button>
                        @endcan

                        <button type="button"
                                class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                data-bs-toggle="modal"
                                data-bs-target="#historyModal"
                                title="История изменений">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-clock-rotate-left payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">История</span>
                        </button>

                        <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#legalEntitiesReportFiltersCollapse"
                                aria-expanded="{{ $legalEntitiesHasActiveFilters ? 'true' : 'false' }}"
                                aria-controls="legalEntitiesReportFiltersCollapse"
                                id="legalEntitiesReportFiltersToggle">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                            <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="dropdown payments-report-toolbar-dropdown">
                            <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                    type="button"
                                    id="legalEntitiesColumnsDropdown"
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
                                 aria-labelledby="legalEntitiesColumnsDropdown">
                                <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                                @foreach ([
                                    'title' => 'Наименование',
                                    'business_type_label' => 'Форма',
                                    'tax_id' => 'ИНН',
                                    'tinkoff_shop_code' => 'ShopCode',
                                    'is_registered_label' => 'sm-register',
                                    'is_default_label' => 'Основное',
                                    'teams_count' => 'Группы',
                                    'is_enabled_label' => 'Активен',
                                    'actions' => 'Действия',
                                ] as $key => $label)
                                    <div class="form-check">
                                        <input class="form-check-input column-toggle" type="checkbox" data-column-key="{{ $key }}" id="colLegalEntity{{ ucfirst(str_replace('_', '', $key)) }}" checked>
                                        <label class="form-check-label" for="colLegalEntity{{ ucfirst(str_replace('_', '', $key)) }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="collapse {{ $legalEntitiesHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="legalEntitiesReportFiltersCollapse">
            <form id="legal-entities-report-filters" class="border rounded p-2 p-md-3 bg-light">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="filter-search">Поиск</label>
                        <input id="filter-search" class="form-control" type="text" placeholder="Название, ИНН, ShopCode">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="filter-status">Статус</label>
                        <select id="filter-status" class="form-select">
                            <option value="">Все</option>
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
            <table id="legal-entities-table" class="table table-striped table-bordered align-middle w-100 dt-columns-managed">
                <thead>
                <tr>
                    <th>Наименование</th>
                    <th>Форма</th>
                    <th>ИНН</th>
                    <th>ShopCode</th>
                    <th>sm-register</th>
                    <th>Основное</th>
                    <th>Группы</th>
                    <th>Активен</th>
                    @can('legal_entities.manage')
                        <th>Действия</th>
                    @endcan
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    @can('legal_entities.manage')
        <div class="modal fade" id="legalEntityCreateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl directories-form-modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Добавить юр. лицо</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="legalEntityCreateForm">
                            @csrf
                            @include('admin.legal-entities.partials.crud-fields', ['prefix' => 'create'])
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="button" class="btn btn-primary" id="legalEntityCreateSubmit">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="legalEntityEditModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl directories-form-modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Редактировать юр. лицо</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="legalEntityEditForm">
                            @csrf
                            @method('put')
                            <input type="hidden" name="id" />
                            @include('admin.legal-entities.partials.crud-fields', ['prefix' => 'edit'])
                        </form>
                        <div class="mt-3">
                            <a href="#" id="legalEntityOpenShowLink" class="btn btn-sm btn-outline-secondary">Открыть карточку (T‑Bank / sm-register)</a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger me-auto confirm-delete-modal" id="legalEntityDeleteBtn">Удалить</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="button" class="btn btn-primary" id="legalEntityEditSubmit">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
    @endcan

    @include('includes.logModal')
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            const canManage = @json(auth()->user()->can('legal_entities.manage'));
            const defaultFilterStatus = 'active';

            function filterParams() {
                return {
                    search: $('#filter-search').val() || '',
                    status: $('#filter-status').val() || ''
                };
            }

            const dtApi = KidsCrmDataTable.create('#legal-entities-table', {
                columnsSettings: {
                    defaults: {
                        title: true,
                        business_type_label: true,
                        tax_id: true,
                        tinkoff_shop_code: true,
                        is_registered_label: true,
                        is_default_label: true,
                        teams_count: true,
                        is_enabled_label: true,
                        ...(canManage ? { actions: true } : {}),
                    },
                    urls: {
                        get: @json(route('admin.legal-entities.columns-settings.get')),
                        save: @json(route('admin.legal-entities.columns-settings.save')),
                    },
                    csrfToken: '{{ csrf_token() }}',
                },
                dataTable: {
                    ajax: {
                        url: @json(route('admin.legal-entities.data')),
                        type: 'GET',
                        data: function (d) {
                            const params = filterParams();
                            d.search = params.search;
                            d.status = params.status;
                        }
                    },
                    order: [[0, 'asc']],
                    language: @include('partials.datatables.ru')
                },
                columns: [
                    {
                        key: 'title',
                        type: 'link',
                        data: 'title',
                        className: 'dt-col-text',
                        linkClass: 'js-legal-entity-open',
                        linkAttrs: function (row) {
                            return 'href="' + row.show_url + '"';
                        },
                    },
                    { key: 'business_type_label', type: 'text', data: 'business_type_label' },
                    { key: 'tax_id', type: 'text', data: 'tax_id' },
                    { key: 'tinkoff_shop_code', type: 'text', data: 'tinkoff_shop_code' },
                    {
                        key: 'is_registered_label',
                        type: 'badge',
                        data: 'is_registered_label',
                        badgeKey: 'is_registered',
                        className: 'dt-col-badge text-center',
                    },
                    {
                        key: 'is_default_label',
                        type: 'badge',
                        data: 'is_default_label',
                        badgeKey: 'is_default',
                        className: 'dt-col-badge text-center',
                    },
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
                        when: canManage,
                        render: function (data, type, row) {
                            return '<a class="btn btn-sm btn-outline-primary" href="' + row.show_url + '">Карточка</a> ' +
                                '<button type="button" class="btn btn-sm btn-outline-secondary js-legal-entity-edit" data-id="' + row.id + '">Изменить</button>';
                        }
                    },
                ],
            });

            function reloadTable() {
                dtApi.reload({ keepPage: true });
            }

            $('#filter-apply').on('click', reloadTable);
            $('#legal-entities-report-filters').on('submit', function (e) {
                e.preventDefault();
                reloadTable();
            });
            $('#filter-reset').on('click', function () {
                $('#filter-search').val('');
                $('#filter-status').val(defaultFilterStatus);
                reloadTable();
            });
            $('#filter-search').on('keyup', function (e) {
                if (e.key === 'Enter') reloadTable();
            });

            @can('legal_entities.manage')
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            function clearErrors(form) {
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
            }

            function resolveFormInput(form, key) {
                let input = form.querySelector('[name="' + key + '"]');
                if (input) {
                    return input;
                }
                const parts = key.split('.');
                if (parts.length === 2) {
                    return form.querySelector('[name="' + parts[0] + '[' + parts[1] + ']"]');
                }

                return null;
            }

            function applyErrors(form, errors) {
                Object.entries(errors || {}).forEach(([key, messages]) => {
                    const message = (messages && messages[0]) ? messages[0] : 'Ошибка';
                    const input = resolveFormInput(form, key);
                    const err = form.querySelector('[data-error-for="' + key + '"]');
                    if (input) input.classList.add('is-invalid');
                    if (err) err.textContent = message;
                });
            }

            function setRegisteredFieldsLocked(form, locked) {
                form.querySelectorAll('.js-legal-entity-sm-locked-mirror').forEach(el => el.remove());
                form.querySelectorAll('.js-legal-entity-sm-locked').forEach(el => {
                    if (el.tagName === 'SELECT') {
                        el.disabled = locked;
                        if (locked) {
                            const hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = el.name;
                            hidden.value = el.value;
                            hidden.className = 'js-legal-entity-sm-locked-mirror';
                            el.parentNode.insertBefore(hidden, el.nextSibling);
                        }
                    } else {
                        el.readOnly = locked;
                    }
                    el.classList.toggle('bg-light', locked);
                });

                const hint = form.querySelector('.js-legal-entity-registered-hint');
                if (hint) {
                    hint.classList.toggle('d-none', !locked);
                }
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

            function fillForm(form, data) {
                const map = {
                    business_type: data.business_type,
                    title: data.title,
                    organization_name: data.organization_name,
                    tax_id: data.tax_id,
                    kpp: data.kpp,
                    registration_number: data.registration_number,
                    city: data.city,
                    zip: data.zip,
                    address: data.address,
                    bank_name: data.bank_name,
                    bank_bik: data.bank_bik,
                    bank_account: data.bank_account,
                    sms_name: data.sms_name,
                    sm_details_template: data.sm_details_template,
                    taxation_system: data.taxation_system,
                    vat: data.vat,
                    is_default: String(data.is_default ?? 0),
                    is_enabled: String(data.is_enabled ?? 1),
                };
                Object.entries(map).forEach(([key, value]) => {
                    const input = resolveFormInput(form, key);
                    if (input) input.value = value ?? '';
                });

                const ceo = data.ceo || {};
                ['lastName', 'firstName', 'middleName'].forEach((part) => {
                    const input = form.querySelector('[name="ceo[' + part + ']"]');
                    if (input) input.value = ceo[part] ?? '';
                });
                const ceoPhoneInput = form.querySelector('[name="ceo[phone]"]');
                if (ceoPhoneInput) {
                    if (window.PhoneInputMask?.setValue) {
                        window.PhoneInputMask.setValue('#' + ceoPhoneInput.id, ceo.phone || '');
                    } else {
                        ceoPhoneInput.value = ceo.phone ?? '';
                    }
                }

                setRegisteredFieldsLocked(form, !!data.is_registered);
            }

            const createForm = document.getElementById('legalEntityCreateForm');
            const editForm = document.getElementById('legalEntityEditForm');

            document.getElementById('legalEntityCreateModal')?.addEventListener('show.bs.modal', () => {
                setRegisteredFieldsLocked(createForm, false);
            });

            document.getElementById('legalEntityCreateSubmit')?.addEventListener('click', async () => {
                clearErrors(createForm);
                const { ok, status, data } = await postForm(@json(route('admin.legal-entities.store')), createForm, 'POST');
                if (!ok && status === 422) {
                    applyErrors(createForm, data.errors || {});
                    return;
                }
                if (ok) {
                    createForm.reset();
                    setRegisteredFieldsLocked(createForm, false);
                    bootstrap.Modal.getInstance(document.getElementById('legalEntityCreateModal'))?.hide();
                    reloadTable();
                }
            });

            $('#legal-entities-table').on('click', '.js-legal-entity-edit', async function (e) {
                e.preventDefault();
                clearErrors(editForm);
                const id = $(this).data('id');
                const res = await fetch(@json(url('/admin/legal-entities')) + '/' + id, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json();
                editForm.querySelector('[name="id"]').value = data.id;
                fillForm(editForm, data);
                document.getElementById('legalEntityOpenShowLink').href = @json(url('/admin/legal-entities')) + '/' + id;
                new bootstrap.Modal(document.getElementById('legalEntityEditModal')).show();
            });

            document.getElementById('legalEntityEditSubmit')?.addEventListener('click', async () => {
                clearErrors(editForm);
                const id = editForm.querySelector('[name="id"]').value;
                const { ok, status, data } = await postForm(@json(url('/admin/legal-entities')) + '/' + id, editForm, 'PUT');
                if (!ok && status === 422) {
                    applyErrors(editForm, data.errors || {});
                    return;
                }
                if (ok) {
                    bootstrap.Modal.getInstance(document.getElementById('legalEntityEditModal'))?.hide();
                    reloadTable();
                }
            });

            $(document).on('click', '#legalEntityDeleteBtn', function () {
                const id = editForm.querySelector('[name="id"]').value;
                if (!id) return;
                const name = (editForm.querySelector('[name="title"]').value || '').trim();
                const messageText = name !== ''
                    ? 'Вы уверены, что хотите удалить юр. лицо «' + name + '»?'
                    : 'Вы уверены, что хотите удалить юр. лицо?';

                showConfirmDeleteModal('Удаление юр. лица', messageText, function () {
                    $.ajax({
                        url: @json(url('/admin/legal-entities')) + '/' + id,
                        type: 'DELETE',
                        data: { _token: token },
                        success: function () {
                            bootstrap.Modal.getInstance(document.getElementById('legalEntityEditModal'))?.hide();
                            reloadTable();
                        },
                        error: function (xhr) {
                            const msg = xhr.responseJSON?.errors?.legal_entity?.[0]
                                || xhr.responseJSON?.message
                                || 'Не удалось удалить';
                            alert(msg);
                        }
                    });
                });
            });
            @endcan

            showLogModal(@json(route('logs.data.legal-entity')));
        });
    </script>
@endpush
