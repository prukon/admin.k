@extends('layouts.admin2')

@section('title', 'Шаблоны договоров')

@php
    $shouldOpenEditModal = !empty($editTemplate);
    $shouldOpenCreateModal = !$shouldOpenEditModal && (request()->boolean('create') || ($errors->any() && !request()->filled('edit')));
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])

    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Документы</h4>

        <div class="">
            @include('contracts._contracts_section_tabs', ['activeTab' => $activeTab ?? 'templates'])

            <div class="tab-content">
                <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
                    <div class="card-body px-3 py-3">
                        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Шаблоны договоров</h1>
                            <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                                <button type="button"
                                        class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#createContractTemplateModal">
                                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                        <i class="fas fa-plus payments-report-toolbar-icon"></i>
                                    </span>
                                    <span class="payments-report-toolbar-label d-none d-sm-inline">Добавить шаблон</span>
                                </button>

                                <div class="dropdown payments-report-toolbar-dropdown">
                                    <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                            type="button"
                                            id="contractTemplatesColumnsDropdown"
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
                                         aria-labelledby="contractTemplatesColumnsDropdown">
                                        <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="id"
                                                   id="colTemplateId"
                                                   checked>
                                            <label class="form-check-label" for="colTemplateId">№</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="title"
                                                   id="colTemplateTitle"
                                                   checked>
                                            <label class="form-check-label" for="colTemplateTitle">Название</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="version"
                                                   id="colTemplateVersion"
                                                   checked>
                                            <label class="form-check-label" for="colTemplateVersion">Версия</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="fields_count"
                                                   id="colTemplateFieldsCount"
                                                   checked>
                                            <label class="form-check-label" for="colTemplateFieldsCount">Полей</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="status_label"
                                                   id="colTemplateStatus"
                                                   checked>
                                            <label class="form-check-label" for="colTemplateStatus">Статус</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="actions"
                                                   id="colTemplateActions"
                                                   checked>
                                            <label class="form-check-label" for="colTemplateActions">Действия</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <div class="table-responsive">
                    <table id="contract-templates-table" class="table table-striped table-bordered table-sm align-middle w-100">
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>Название</th>
                            <th>Версия</th>
                            <th>Полей</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('contract-templates.partials.create-modal')
    @include('contract-templates.partials.edit-modal')
    @include('contract-templates.partials.email-edit-modal')
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            const columnsSettingsGetUrl = @json(route('contract-templates.columns-settings.get'));
            const columnsSettingsSaveUrl = @json(route('contract-templates.columns-settings.save'));

            const shouldOpenCreateModal = @json($shouldOpenCreateModal);
            const shouldOpenEditModal = @json($shouldOpenEditModal);
            const createModalEl = document.getElementById('createContractTemplateModal');
            const editModalEl = document.getElementById('editContractTemplateModal');

            if (shouldOpenEditModal && editModalEl) {
                bootstrap.Modal.getOrCreateInstance(editModalEl).show();
            } else if (shouldOpenCreateModal && createModalEl) {
                bootstrap.Modal.getOrCreateInstance(createModalEl).show();
            }

            createModalEl?.addEventListener('hidden.bs.modal', function () {
                if (@json($errors->any() && !request()->filled('edit') && !request()->filled('email'))) {
                    return;
                }

                const form = document.getElementById('contractTemplateCreateForm');
                if (!form) {
                    return;
                }

                form.reset();
                form.querySelectorAll('.is-invalid').forEach(function (el) {
                    el.classList.remove('is-invalid');
                });
            });

            editModalEl?.addEventListener('hidden.bs.modal', function () {
                if (window.location.search.includes('edit=')) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('edit');
                    window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
                }
            });

            const defaultColumnsVisibility = {
                id: true,
                title: true,
                version: true,
                fields_count: true,
                status_label: true,
                actions: true,
            };

            let currentColumnsConfig = {...defaultColumnsVisibility};

            const columnsMap = {
                id: 0,
                title: 1,
                version: 2,
                fields_count: 3,
                status_label: 4,
                actions: 5,
            };

            const statusBadgeClass = {
                archived: 'bg-secondary',
                active: 'bg-success',
                no_version: 'bg-warning text-dark',
            };

            function toBool(val, fallback = true) {
                if (val === undefined || val === null) return fallback;
                if (typeof val === 'boolean') return val;
                if (typeof val === 'number') return val === 1;
                if (typeof val === 'string') {
                    const v = val.toLowerCase().trim();
                    if (v === 'true' || v === '1') return true;
                    if (v === 'false' || v === '0') return false;
                }
                return fallback;
            }

            const table = $('#contract-templates-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 20,
                lengthMenu: [10, 20, 50, 100],
                ajax: {
                    url: @json(route('contract-templates.data')),
                    type: 'GET',
                },
                columns: [
                    {
                        data: 'id',
                        name: 'id',
                        className: 'text-center',
                        defaultContent: '',
                    },
                    {
                        data: 'title',
                        name: 'title',
                        defaultContent: '',
                    },
                    {
                        data: 'version',
                        name: 'version',
                        className: 'text-center',
                        defaultContent: '',
                        render: function (data) {
                            return data != null ? data : '—';
                        },
                    },
                    {
                        data: 'fields_count',
                        name: 'fields_count',
                        className: 'text-center',
                        defaultContent: '0',
                    },
                    {
                        data: 'status_label',
                        name: 'status_label',
                        className: 'text-center',
                        defaultContent: '',
                        render: function (data, type, row) {
                            const badgeClass = statusBadgeClass[row.status_key] || 'bg-secondary';
                            return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                        },
                    },
                    {
                        data: 'edit_url',
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        defaultContent: '',
                        render: function (data, type, row) {
                            const editBtn = '<a href="' + data + '" class="btn btn-sm btn-outline-primary">Изменить</a>';
                            const emailBtn = '<button type="button"'
                                + ' class="btn btn-sm btn-outline-secondary js-contract-template-edit-email"'
                                + ' data-template-id="' + row.id + '"'
                                + ' title="Письмо клиенту"'
                                + ' aria-label="Письмо клиенту">'
                                + 'Письмо'
                                + '</button>';

                            return '<div class="d-flex flex-wrap gap-1 justify-content-end">' + editBtn + emailBtn + '</div>';
                        },
                    },
                ],
                order: [[0, 'desc']],
                language: @include('partials.datatables.ru')
            });

            function applyVisibleColumns(config) {
                Object.keys(columnsMap).forEach(function (key) {
                    const colIndex = columnsMap[key];
                    const isVisible = toBool(config[key], defaultColumnsVisibility[key]);

                    table.column(colIndex).visible(isVisible);

                    $('.column-toggle[data-column-key="' + key + '"]')
                        .prop('checked', isVisible);
                });
            }

            function loadColumnsConfigFromServer() {
                $.ajax({
                    url: columnsSettingsGetUrl,
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        const merged = {};
                        Object.keys(defaultColumnsVisibility).forEach(function (key) {
                            merged[key] = toBool(
                                Object.prototype.hasOwnProperty.call(response, key) ? response[key] : defaultColumnsVisibility[key],
                                defaultColumnsVisibility[key]
                            );
                        });
                        currentColumnsConfig = merged;
                        applyVisibleColumns(currentColumnsConfig);
                    },
                    error: function () {
                        currentColumnsConfig = {...defaultColumnsVisibility};
                        applyVisibleColumns(currentColumnsConfig);
                    }
                });
            }

            loadColumnsConfigFromServer();
            table.columns.adjust();

            $('.column-toggle').on('change', function () {
                const key = $(this).data('column-key');
                const isChecked = $(this).is(':checked');

                currentColumnsConfig[key] = isChecked ? 1 : 0;
                applyVisibleColumns(currentColumnsConfig);

                $.ajax({
                    url: columnsSettingsSaveUrl,
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        columns: currentColumnsConfig,
                    },
                    error: function () {
                        console.error('Не удалось сохранить настройки колонок');
                    },
                });
            });
        });
    </script>

    @include('contract-templates.partials.email-summernote-init')
@endpush
