@extends('layouts.admin2')

@php
    $locationsHasActiveFilters = false;
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])

    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Локации</h4>

        <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Локации</h1>
                    <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                        @can('locations.manage')
                            <button id="new-location"
                                    type="button"
                                    class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                    data-bs-toggle="modal"
                                    data-bs-target="#locationCreateModal">
                                <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-plus payments-report-toolbar-icon"></i>
                                </span>
                                <span class="payments-report-toolbar-label d-none d-sm-inline">Добавить</span>
                            </button>
                        @endcan

                        <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#locationsReportFiltersCollapse"
                                aria-expanded="{{ $locationsHasActiveFilters ? 'true' : 'false' }}"
                                aria-controls="locationsReportFiltersCollapse"
                                id="locationsReportFiltersToggle">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                            <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="dropdown payments-report-toolbar-dropdown">
                            <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                    type="button"
                                    id="locationsColumnsDropdown"
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
                                 aria-labelledby="locationsColumnsDropdown">
                                <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="id"
                                           id="colLocationId"
                                           checked>
                                    <label class="form-check-label" for="colLocationId">№</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="name"
                                           id="colLocationName"
                                           checked>
                                    <label class="form-check-label" for="colLocationName">Название</label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="address"
                                           id="colLocationAddress"
                                           checked>
                                    <label class="form-check-label" for="colLocationAddress">Адрес</label>
                                </div>

                                @if($teamOptions->isNotEmpty())
                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="teams_label"
                                           id="colLocationTeams"
                                           checked>
                                    <label class="form-check-label" for="colLocationTeams">Группы</label>
                                </div>
                                @endif

                                <div class="form-check">
                                    <input class="form-check-input column-toggle"
                                           type="checkbox"
                                           data-column-key="is_enabled_label"
                                           id="colLocationStatus"
                                           checked>
                                    <label class="form-check-label" for="colLocationStatus">Активна</label>
                                </div>

                                @can('locations.manage')
                                    <div class="form-check">
                                        <input class="form-check-input column-toggle"
                                               type="checkbox"
                                               data-column-key="actions"
                                               id="colLocationActions"
                                               checked>
                                        <label class="form-check-label" for="colLocationActions">Действия</label>
                                    </div>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="collapse {{ $locationsHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="locationsReportFiltersCollapse">
            <form id="locations-report-filters" class="border rounded p-2 p-md-3 bg-light">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="filter-name">Название</label>
                        <input id="filter-name"
                               class="form-control"
                               type="text"
                               placeholder="Поиск по названию, адресу">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="filter-status">Статус</label>
                        <select id="filter-status" class="form-select">
                            <option value="">Все локации</option>
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
            <table id="locations-table" class="table table-striped table-bordered align-middle w-100">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Название</th>
                    <th>Адрес</th>
                    @if($teamOptions->isNotEmpty())
                    <th>Группы</th>
                    @endif
                    <th>Активна</th>
                    @can('locations.manage')
                        <th>Действия</th>
                    @endcan
                </tr>
                </thead>
                <tbody></tbody>
            </table>
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
                            @if($teamOptions->isNotEmpty())
                            <div class="mb-3 teams-multiselect-field">
                                <label class="form-label" for="locationCreateTeamIds">Группы</label>
                                <select id="locationCreateTeamIds"
                                        name="team_ids[]"
                                        class="form-select js-teams-multiselect-select"
                                        multiple
                                        data-placeholder="Выберите группы">
                                    @foreach($teamOptions as $team)
                                        <option value="{{ $team->id }}">{{ $team->title }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback d-block" data-error-for="team_ids"></div>
                            </div>
                            @endif
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
                            @if($teamOptions->isNotEmpty())
                            <div class="mb-3 teams-multiselect-field">
                                <label class="form-label" for="locationEditTeamIds">Группы</label>
                                <select id="locationEditTeamIds"
                                        name="team_ids[]"
                                        class="form-select js-teams-multiselect-select"
                                        multiple
                                        data-placeholder="Выберите группы">
                                    @foreach($teamOptions as $team)
                                        <option value="{{ $team->id }}">{{ $team->title }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback d-block" data-error-for="team_ids"></div>
                            </div>
                            @endif
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger me-auto confirm-delete-modal" id="locationDeleteBtn">Удалить</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="button" class="btn btn-primary" id="locationEditSubmit">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@if($teamOptions->isNotEmpty())
    @include('partials.ui.hover-list-dropdown')
    @include('partials.select2.teams-multiselect')
@endif

@push('scripts')
    <script>
        $(document).ready(function () {
            const canManageLocations = @json(auth()->user()->can('locations.manage'));
            const hasTeamOptions = @json($teamOptions->isNotEmpty());
            const defaultFilterStatus = 'active';

            const defaultColumnsVisibility = {
                id: true,
                name: true,
                address: true,
                ...(hasTeamOptions ? { teams_label: true } : {}),
                is_enabled_label: true,
                ...(canManageLocations ? { actions: true } : {})
            };

            let currentColumnsConfig = {...defaultColumnsVisibility};

            const columnsMap = (function () {
                const map = { id: 0, name: 1, address: 2 };
                let idx = 3;
                if (hasTeamOptions) {
                    map.teams_label = idx++;
                }
                map.is_enabled_label = idx++;
                if (canManageLocations) {
                    map.actions = idx;
                }
                return map;
            })();

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

            function applyVisibleColumns(config) {
                Object.keys(columnsMap).forEach(function (key) {
                    const colIndex = columnsMap[key];
                    const column = table.column(colIndex);
                    const isVisible = toBool(config[key], defaultColumnsVisibility[key]);

                    column.visible(isVisible);

                    $('.column-toggle[data-column-key="' + key + '"]')
                        .prop('checked', isVisible);
                });
            }

            function loadColumnsConfigFromServer() {
                $.ajax({
                    url: @json(route('admin.locations.columns-settings.get')),
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

            function locationsFilterParams() {
                return {
                    name: $('#filter-name').val() || '',
                    status: $('#filter-status').val() || ''
                };
            }

            function locationsHasNonDefaultFilters() {
                const params = locationsFilterParams();
                return params.name !== '' || params.status !== defaultFilterStatus;
            }

            function syncLocationsFiltersCollapseState() {
                const hasActive = locationsHasNonDefaultFilters();
                const collapseEl = document.getElementById('locationsReportFiltersCollapse');
                const $toggle = $('#locationsReportFiltersToggle');

                if (collapseEl && hasActive && !collapseEl.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                }

                if ($toggle.length && collapseEl) {
                    $toggle.attr('aria-expanded', collapseEl.classList.contains('show') ? 'true' : 'false');
                }
            }

            const dataTableColumns = [
                {
                    data: 'id',
                    name: 'id',
                    className: 'text-center',
                    defaultContent: ''
                },
                {
                    data: 'name',
                    name: 'name',
                    defaultContent: ''
                },
                {
                    data: 'address',
                    name: 'address',
                    defaultContent: '',
                    render: function (data) {
                        return data ? data : '<span class="text-muted">—</span>';
                    }
                },
                ...(hasTeamOptions ? [{
                    data: 'teams_label',
                    name: 'teams_label',
                    defaultContent: '',
                    render: function (data, type, row) {
                        if (type !== 'display') {
                            return data || '';
                        }

                        if (!data) {
                            return '<span class="text-muted">—</span>';
                        }

                        if (window.KidsCrmHoverListDropdown) {
                            return KidsCrmHoverListDropdown.renderCell(data, row.teams_titles || []);
                        }

                        return data;
                    }
                }] : []),
                {
                    data: 'is_enabled_label',
                    name: 'is_enabled_label',
                    className: 'text-center',
                    defaultContent: '',
                    render: function (data, type, row) {
                        const badgeClass = row.is_enabled ? 'bg-success' : 'bg-secondary';
                        return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                    }
                },
                ...(canManageLocations ? [{
                    data: null,
                    name: 'actions',
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render: function (data, type, row) {
                        return '<button type="button" ' +
                            'class="btn btn-sm btn-outline-primary js-location-edit" ' +
                            'data-id="' + row.id + '">' +
                            'Редактировать' +
                            '</button>';
                    }
                }] : [])
            ];

            const table = $('#locations-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 10,
                lengthMenu: [10, 20, 50, 100],
                ajax: {
                    url: @json(route('admin.locations.data')),
                    type: 'GET',
                    data: function (d) {
                        const params = locationsFilterParams();
                        d.name = params.name;
                        d.status = params.status;
                    }
                },
                columns: dataTableColumns,
                order: [[1, 'asc']],
                scrollX: true,
                language: @include('partials.datatables.ru')
            });

            if (hasTeamOptions && window.KidsCrmHoverListDropdown) {
                table.on('draw.dt', function () {
                    KidsCrmHoverListDropdown.init(document.getElementById('locations-table'));
                });
            }

            loadColumnsConfigFromServer();
            table.columns.adjust();

            function reloadLocationsTable() {
                table.ajax.reload(null, false);
                syncLocationsFiltersCollapseState();
            }

            window.__reloadLocationsTable = reloadLocationsTable;

            $('#filter-apply').on('click', function () {
                reloadLocationsTable();
            });

            $('#locations-report-filters').on('submit', function (e) {
                e.preventDefault();
                reloadLocationsTable();
            });

            $('#filter-reset').on('click', function () {
                $('#filter-name').val('');
                $('#filter-status').val(defaultFilterStatus);
                reloadLocationsTable();
            });

            $('#filter-name').on('keyup', function (e) {
                if (e.key === 'Enter') {
                    reloadLocationsTable();
                }
            });

            $('#locationsReportFiltersCollapse').on('shown.bs.collapse hidden.bs.collapse', function () {
                $('#locationsReportFiltersToggle').attr(
                    'aria-expanded',
                    $('#locationsReportFiltersCollapse').hasClass('show') ? 'true' : 'false'
                );
            });

            $('.column-toggle').on('change', function () {
                const key = $(this).data('column-key');
                const isChecked = $(this).is(':checked');

                currentColumnsConfig[key] = isChecked ? 1 : 0;

                applyVisibleColumns(currentColumnsConfig);

                $.ajax({
                    url: @json(route('admin.locations.columns-settings.save')),
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        columns: currentColumnsConfig
                    },
                    error: function () {
                        console.error('Не удалось сохранить настройки колонок');
                    }
                });
            });

            @can('locations.manage')
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            function clearErrors(form) {
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
                form.querySelectorAll('.js-teams-multiselect-select').forEach(function (select) {
                    if (window.KidsCrmTeamsMultiselectSelect2) {
                        KidsCrmTeamsMultiselectSelect2.clearInvalid($(select));
                    }
                });
            }

            function applyErrors(form, errors) {
                Object.entries(errors || {}).forEach(([key, messages]) => {
                    const message = (messages && messages[0]) ? messages[0] : 'Ошибка';
                    let input = form.querySelector(`[name="${key}"]`);

                    if (!input && key === 'team_ids') {
                        input = form.querySelector('[name="team_ids[]"]');
                    }

                    const err = form.querySelector(`[data-error-for="${key}"]`);
                    if (input) {
                        input.classList.add('is-invalid');
                        if (window.KidsCrmTeamsMultiselectSelect2 && input.classList.contains('js-teams-multiselect-select')) {
                            KidsCrmTeamsMultiselectSelect2.markInvalid($(input));
                        }
                    }
                    if (err) {
                        err.textContent = message;
                    }
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
            const $createTeamsSelect = $('#locationCreateTeamIds');
            const $editTeamsSelect = $('#locationEditTeamIds');

            if (hasTeamOptions && window.KidsCrmTeamsMultiselectSelect2) {
                KidsCrmTeamsMultiselectSelect2.init($createTeamsSelect, {
                    dropdownParent: $('#locationCreateModal')
                });
                KidsCrmTeamsMultiselectSelect2.init($editTeamsSelect, {
                    dropdownParent: $('#locationEditModal')
                });
            }

            document.getElementById('locationCreateSubmit')?.addEventListener('click', async () => {
                clearErrors(createForm);
                const { ok, status, data } = await postForm(@json(route('admin.locations.store')), createForm, 'POST');
                if (!ok && status === 422) {
                    applyErrors(createForm, data.errors || {});
                    return;
                }
                if (ok) {
                    createForm.reset();
                    if (window.KidsCrmTeamsMultiselectSelect2) {
                        KidsCrmTeamsMultiselectSelect2.reset($createTeamsSelect);
                    }
                    bootstrap.Modal.getInstance(document.getElementById('locationCreateModal'))?.hide();
                    reloadLocationsTable();
                }
            });

            $('#locations-table').on('click', '.js-location-edit', async function () {
                clearErrors(editForm);
                const id = $(this).data('id');
                const res = await fetch(@json(url('/admin/locations')) + '/' + id, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                editForm.querySelector('[name="id"]').value = data.id;
                editForm.querySelector('[name="name"]').value = data.name || '';
                editForm.querySelector('[name="address"]').value = data.address || '';
                editForm.querySelector('[name="description"]').value = data.description || '';
                editForm.querySelector('[name="is_enabled"]').value = String(data.is_enabled ?? 1);
                if (window.KidsCrmTeamsMultiselectSelect2) {
                    KidsCrmTeamsMultiselectSelect2.setValues($editTeamsSelect, data.team_ids || []);
                }
                const modal = new bootstrap.Modal(document.getElementById('locationEditModal'));
                modal.show();
            });

            document.getElementById('locationEditSubmit')?.addEventListener('click', async () => {
                clearErrors(editForm);
                const id = editForm.querySelector('[name="id"]').value;
                const { ok, status, data } = await postForm(@json(url('/admin/locations')) + '/' + id, editForm, 'PUT');
                if (!ok && status === 422) {
                    applyErrors(editForm, data.errors || {});
                    return;
                }
                if (ok) {
                    bootstrap.Modal.getInstance(document.getElementById('locationEditModal'))?.hide();
                    reloadLocationsTable();
                }
            });

            function deleteLocation() {
                const id = editForm.querySelector('[name="id"]').value;
                if (!id) return;

                const locationName = (editForm.querySelector('[name="name"]').value || '').trim();
                const messageText = locationName !== ''
                    ? 'Вы уверены, что хотите удалить локацию «' + locationName + '»?'
                    : 'Вы уверены, что хотите удалить локацию?';

                showConfirmDeleteModal(
                    'Удаление локации',
                    messageText,
                    function () {
                        const confirmEl = document.getElementById('confirmDeleteModal');
                        const editEl = document.getElementById('locationEditModal');

                        // Не возвращать модалку редактирования после закрытия подтверждения
                        $(confirmEl).off('hidden.bs.modal.return');

                        $.ajax({
                            url: @json(url('/admin/locations')) + '/' + id,
                            type: 'DELETE',
                            data: { _token: token },
                            success: function () {
                                $(editEl).off('hidden.bs.modal.openNext');
                                bootstrap.Modal.getInstance(editEl)?.hide();

                                reloadLocationsTable();

                                if (typeof showSuccessModal === 'function') {
                                    showSuccessModal('Удаление локации', 'Локация успешно удалена.', 0);
                                }
                            },
                            error: function (xhr) {
                                const msg = xhr.responseJSON?.message || 'Произошла ошибка при удалении локации.';
                                if (typeof showErrorModal === 'function') {
                                    showErrorModal('Ошибка', msg, 1);
                                } else if ($('#errorModal').length) {
                                    $('#error-modal-message').text(msg);
                                    $('#errorModal').modal('show');
                                }
                            }
                        });
                    }
                );
            }

            $(document).on('click', '#locationDeleteBtn', function () {
                deleteLocation();
            });
            @endcan
        });
    </script>
@endpush
