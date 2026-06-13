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
                    <table id="contract-templates-table" class="table table-striped table-bordered align-middle w-100 dt-columns-managed">
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
    @include('contract-templates.partials.edit-modal-init', ['shouldOpenEditModal' => $shouldOpenEditModal])

    <script>
        $(document).ready(function () {
            const shouldOpenCreateModal = @json($shouldOpenCreateModal);
            const createModalEl = document.getElementById('createContractTemplateModal');

            if (shouldOpenCreateModal && createModalEl) {
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

            const statusBadgeClass = {
                archived: 'bg-secondary',
                active: 'bg-success',
                no_version: 'bg-warning text-dark',
            };

            KidsCrmDataTable.create('#contract-templates-table', {
                columnsSettings: {
                    defaults: {
                        id: true,
                        title: true,
                        version: true,
                        fields_count: true,
                        status_label: true,
                        actions: true,
                    },
                    urls: {
                        get: @json(route('contract-templates.columns-settings.get')),
                        save: @json(route('contract-templates.columns-settings.save')),
                    },
                    csrfToken: $('meta[name="csrf-token"]').attr('content'),
                },
                dataTable: {
                    pageLength: 20,
                    lengthMenu: [10, 20, 50, 100],
                    ajax: {
                        url: @json(route('contract-templates.data')),
                        type: 'GET',
                    },
                    order: [[0, 'desc']],
                    language: @include('partials.datatables.ru'),
                },
                columns: [
                    { key: 'id', type: 'id', data: 'id' },
                    {
                        key: 'title',
                        type: 'link',
                        data: 'title',
                        className: 'dt-col-text',
                        linkClass: 'js-contract-template-edit-link',
                        linkAttrs: function (row) {
                            return 'data-template-id="' + row.id + '"';
                        },
                    },
                    {
                        key: 'version',
                        type: 'count',
                        data: 'version',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            return data != null ? data : '—';
                        },
                    },
                    { key: 'fields_count', type: 'count', data: 'fields_count' },
                    {
                        key: 'status_label',
                        type: 'badge',
                        data: 'status_label',
                        name: 'status_label',
                        className: 'dt-col-badge text-center',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data || '';
                            }

                            const badgeClass = statusBadgeClass[row.status_key] || 'bg-secondary';
                            return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                        },
                    },
                    {
                        key: 'actions',
                        type: 'actions',
                        render: function (data, type, row) {
                            const editBtn = '<a href="#" class="btn btn-sm btn-outline-primary js-contract-template-edit-link" data-template-id="' + row.id + '">Изменить</a>';
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
            });
        });
    </script>

    @include('contract-templates.partials.email-summernote-init')

    @include('includes.logModal')

    <script>
        document.getElementById('historyModal')?.addEventListener('show.bs.modal', function () {
            showLogModal(@json(route('logs.data.contract-template')));
        });
    </script>
@endpush
