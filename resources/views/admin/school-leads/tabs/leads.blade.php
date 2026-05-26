@php
    $canViewLocations = $canViewLocations ?? (auth()->user() && auth()->user()->can('locations.view'));
    $canCreateUserFromLead = $canCreateUserFromLead ?? (auth()->user() && auth()->user()->can('users.view'));
    $canViewContracts = $canViewContracts ?? (auth()->user() && auth()->user()->can('contracts.view'));
    $canShowLeadClientColumn = $canViewContracts || $canCreateUserFromLead;
    $leadStats = $leadStats ?? ['total' => 0, 'new' => 0, 'processing' => 0];
    $leadsHasActiveFilters = false;
@endphp

@vite(['resources/css/admin-list-toolbar.css'])

<div class="main-content text-start">

    <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2" id="schoolLeadsReportToolbar">
        <div class="card-body px-3 py-3">
            <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">
                    Заявки
                </h1>
                <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                    <div class="d-flex flex-wrap align-items-end justify-content-end gap-3 gap-md-4" id="schoolLeadsReportToolbarTotals">
                        <div class="payments-report-total-inline payments-report-total-stat text-end" id="schoolLeadsStatNew">
                            <div class="payments-report-total-label text-muted small mb-0">Новых</div>
                            <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                                <span class="payments-report-total-value-inner">
                                    <span class="payments-report-total-amount school-leads-stat-new">{{ number_format($leadStats['new'], 0, '', ' ') }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="payments-report-total-inline payments-report-total-stat text-end" id="schoolLeadsStatProcessing">
                            <div class="payments-report-total-label text-muted small mb-0">В обработке</div>
                            <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                                <span class="payments-report-total-value-inner">
                                    <span class="payments-report-total-amount school-leads-stat-processing">{{ number_format($leadStats['processing'], 0, '', ' ') }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="payments-report-total-inline payments-report-total-stat text-end" id="schoolLeadsStatTotal">
                            <div class="payments-report-total-label text-muted small mb-0">Всего</div>
                            <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                                <span class="payments-report-total-value-inner">
                                    <span class="payments-report-total-amount school-leads-stat-total">{{ number_format($leadStats['total'], 0, '', ' ') }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                        <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#schoolLeadsFiltersCollapse"
                                aria-expanded="{{ $leadsHasActiveFilters ? 'true' : 'false' }}"
                                aria-controls="schoolLeadsFiltersCollapse"
                                id="schoolLeadsFiltersToggle">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                            <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="dropdown payments-report-toolbar-dropdown">
                            <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                    type="button"
                                    id="columnsDropdownSchoolLeads"
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
                                 aria-labelledby="columnsDropdownSchoolLeads">
                                <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="name" id="slColName" checked>
                                    <label class="form-check-label" for="slColName">Имя</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="phone" id="slColPhone" checked>
                                    <label class="form-check-label" for="slColPhone">Телефон</label>
                                </div>
                                @if ($canViewLocations)
                                    <div class="form-check">
                                        <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="location" id="slColLocation" checked>
                                        <label class="form-check-label" for="slColLocation">Локация</label>
                                    </div>
                                @endif
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="utm" id="slColUtm" checked>
                                    <label class="form-check-label" for="slColUtm">UTM / источник</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="page_url" id="slColPageUrl" checked>
                                    <label class="form-check-label" for="slColPageUrl">Страница</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="status" id="slColStatus" checked>
                                    <label class="form-check-label" for="slColStatus">Статус</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="comment" id="slColComment" checked>
                                    <label class="form-check-label" for="slColComment">Комментарий</label>
                                </div>
                                @if ($canShowLeadClientColumn)
                                    <div class="form-check">
                                        <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="contract" id="slColContract" checked>
                                        <label class="form-check-label" for="slColContract">Договор</label>
                                    </div>
                                @endif
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="actions" id="slColActions" checked>
                                    <label class="form-check-label" for="slColActions">Действия</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="collapse {{ $leadsHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="schoolLeadsFiltersCollapse">
        <form id="school-leads-filters" class="border rounded p-2 p-md-3 bg-light" action="#" method="get">
            <div class="row g-3 align-items-end">
                <div class="col-12">
                    <label class="form-label d-block mb-2">Статусы</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check mb-0">
                            <input class="form-check-input status-filter-checkbox" type="checkbox" value="new" id="slFilterStatusNew" checked>
                            <label class="form-check-label" for="slFilterStatusNew">Новый</label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input status-filter-checkbox" type="checkbox" value="processing" id="slFilterStatusProcessing" checked>
                            <label class="form-check-label" for="slFilterStatusProcessing">Обработка</label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input status-filter-checkbox" type="checkbox" value="sale" id="slFilterStatusSale">
                            <label class="form-check-label" for="slFilterStatusSale">Продажа</label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input status-filter-checkbox" type="checkbox" value="rejected" id="slFilterStatusRejected">
                            <label class="form-check-label" for="slFilterStatusRejected">Отказ</label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input status-filter-checkbox" type="checkbox" value="spam" id="slFilterStatusSpam">
                            <label class="form-check-label" for="slFilterStatusSpam">Спам</label>
                        </div>
                    </div>
                </div>

                @if ($canViewLocations)
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="sl-filter-location">Локация</label>
                        <select class="form-select" id="sl-filter-location" name="location_id">
                            <option value="">Все локации</option>
                            <option value="none">Без локации</option>
                            @foreach ($activeLocations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                    <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                    <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="schoolLeadsFiltersResetBtn">Сброс</button>
                </div>
            </div>
        </form>
    </div>

    <table id="leads-table" class="table table-bordered table-striped align-middle w-100">
        <thead>
            <tr>
                <th>#</th>
                <th>Имя</th>
                <th>Телефон</th>
                @if ($canViewLocations)
                    <th>Локация</th>
                @endif
                <th>UTM / источник</th>
                <th>Страница</th>
                <th>Статус</th>
                <th>Комментарий</th>
                @if ($canShowLeadClientColumn)
                    <th style="min-width: 200px;">Договор</th>
                @endif
                <th style="width: 120px;">Действия</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

</div>

<div class="modal fade" id="editLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактирование заявки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="editLeadForm">
                    <input type="hidden" id="editLeadId">
                    <div class="mb-3">
                        <label for="leadStatus" class="form-label">Статус</label>
                        <select id="leadStatus" class="form-select">
                            <option value="">— не выбран —</option>
                            <option value="new">Новый</option>
                            <option value="processing">Обработка</option>
                            <option value="sale">Продажа</option>
                            <option value="rejected">Отказ</option>
                            <option value="spam">Спам</option>
                        </select>
                    </div>
                    @if ($canViewLocations)
                        <div class="mb-3">
                            <label for="leadLocation" class="form-label">Локация</label>
                            <select id="leadLocation" class="form-select">
                                <option value="">— не выбрана —</option>
                                @foreach ($activeLocations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="leadLocationError"></div>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label for="leadComment" class="form-label">Комментарий</label>
                        <textarea id="leadComment" class="form-control" rows="3"></textarea>
                    </div>
                </form>
                <div class="alert alert-danger d-none" id="editLeadError"></div>
                <div class="alert alert-success d-none" id="editLeadSuccess"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="saveLeadBtn">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Удаление заявки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                Вы действительно хотите удалить эту заявку? Действие можно будет отменить только через БД.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteLeadBtn">Удалить</button>
            </div>
        </div>
    </div>
</div>

@if ($canCreateUserFromLead)
    @include('includes.modal.createUser')
@endif

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="mainToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="mainToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

@section('scripts')
    <script>
        $(document).ready(function() {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            var canViewLocations = @json($canViewLocations);
            var canCreateUserFromLead = @json($canCreateUserFromLead);
            var canViewContracts = @json($canViewContracts);
            var canShowLeadClientColumn = @json($canShowLeadClientColumn);

            var $filtersForm = $('#school-leads-filters');
            var $statNew = $('.school-leads-stat-new');
            var $statProcessing = $('.school-leads-stat-processing');
            var $statTotal = $('.school-leads-stat-total');
            var $toolbarRoot = $('#schoolLeadsReportToolbar');

            var editLeadModal = new bootstrap.Modal(document.getElementById('editLeadModal'));
            var deleteLeadModal = new bootstrap.Modal(document.getElementById('deleteLeadModal'));
            var leadIdToDelete = null;

            var toastEl = document.getElementById('mainToast');
            var toastBodyEl = document.getElementById('mainToastBody');
            var toastInstance = new bootstrap.Toast(toastEl, { delay: 2500 });

            var defaultStatusFilters = ['new', 'processing'];
            var $locationFilter = $('#sl-filter-location');

            function readFiltersFromForm() {
                var statuses = [];
                $filtersForm.find('.status-filter-checkbox:checked').each(function() {
                    statuses.push($(this).val());
                });
                var locationId = '';
                if (canViewLocations && $locationFilter.length) {
                    locationId = $locationFilter.val() || '';
                }
                return { statuses: statuses, location_id: locationId };
            }

            var appliedFilters = readFiltersFromForm();

            function schoolLeadsFormatCount(n) {
                var v = Math.round(Number(n));
                if (isNaN(v)) {
                    return '0';
                }
                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function updateSchoolLeadsStats(stats) {
                if (!stats) {
                    return;
                }
                if ($toolbarRoot.length) {
                    $toolbarRoot.find('.payments-report-total-stat').addClass('payments-report-total-stat--flash');
                    setTimeout(function() {
                        $toolbarRoot.find('.payments-report-total-stat').removeClass('payments-report-total-stat--flash');
                    }, 400);
                }
                if (stats.new !== undefined) {
                    $statNew.text(schoolLeadsFormatCount(stats.new));
                }
                if (stats.processing !== undefined) {
                    $statProcessing.text(schoolLeadsFormatCount(stats.processing));
                }
                if (stats.total !== undefined) {
                    $statTotal.text(schoolLeadsFormatCount(stats.total));
                }
            }

            function resetFiltersFormToDefault() {
                $filtersForm.find('.status-filter-checkbox').each(function() {
                    var val = $(this).val();
                    $(this).prop('checked', defaultStatusFilters.indexOf(val) !== -1);
                });
                if (canViewLocations && $locationFilter.length) {
                    $locationFilter.val('');
                }
            }

            function showToast(message, type) {
                var $toast = $('#mainToast');
                $toast.removeClass('bg-success bg-danger bg-info bg-warning text-dark');
                switch (type) {
                    case 'error':
                        $toast.addClass('bg-danger');
                        break;
                    case 'info':
                        $toast.addClass('bg-info');
                        break;
                    case 'warning':
                        $toast.addClass('bg-warning text-dark');
                        break;
                    default:
                        $toast.addClass('bg-success');
                }
                toastBodyEl.textContent = message;
                toastInstance.show();
            }

            function getStatusBadgeClass(status) {
                switch (status) {
                    case 'new': return 'bg-secondary';
                    case 'processing': return 'bg-warning text-dark';
                    case 'sale': return 'bg-success';
                    case 'rejected': return 'bg-danger';
                    case 'spam': return 'bg-dark';
                    default: return 'bg-secondary';
                }
            }

            function buildStatusOptionsHtml(selectedStatus) {
                var statuses = [
                    { value: '', label: '— не выбран —' },
                    { value: 'new', label: 'Новый' },
                    { value: 'processing', label: 'Обработка' },
                    { value: 'sale', label: 'Продажа' },
                    { value: 'rejected', label: 'Отказ' },
                    { value: 'spam', label: 'Спам' }
                ];
                var html = '';
                statuses.forEach(function(st) {
                    var selected = (st.value === (selectedStatus || '')) ? ' selected' : '';
                    html += '<option value="' + st.value + '"' + selected + '>' + st.label + '</option>';
                });
                return html;
            }

            var defaultColumnsVisibility = {
                name: true,
                phone: true,
                location: canViewLocations,
                utm: true,
                page_url: true,
                status: true,
                comment: true,
                contract: canShowLeadClientColumn,
                actions: true
            };

            var currentColumnsConfig = Object.assign({}, defaultColumnsVisibility);

            var columnsMap = (function buildSchoolLeadsColumnsMap() {
                var map = { name: 1, phone: 2 };
                var idx = 3;
                if (canViewLocations) {
                    map.location = idx++;
                }
                map.utm = idx++;
                map.page_url = idx++;
                map.status = idx++;
                map.comment = idx++;
                if (canShowLeadClientColumn) {
                    map.contract = idx++;
                }
                map.actions = idx;
                return map;
            })();

            function toBool(val, fallback) {
                fallback = fallback !== undefined ? fallback : true;
                if (val === undefined || val === null) return fallback;
                if (typeof val === 'boolean') return val;
                if (typeof val === 'number') return val === 1;
                if (typeof val === 'string') {
                    var v = val.toLowerCase().trim();
                    if (v === 'true' || v === '1') return true;
                    if (v === 'false' || v === '0') return false;
                }
                return fallback;
            }

            function applyVisibleColumns(config) {
                Object.keys(columnsMap).forEach(function(key) {
                    var colIndex = columnsMap[key];
                    var column = table.column(colIndex);
                    if (key === 'location' && !canViewLocations) {
                        column.visible(false);
                        return;
                    }
                    if (key === 'contract' && !canShowLeadClientColumn) {
                        column.visible(false);
                        return;
                    }
                    var isVisible = toBool(config[key], defaultColumnsVisibility[key]);
                    column.visible(isVisible);
                    $('.school-leads-column-toggle[data-column-key="' + key + '"]').prop('checked', isVisible);
                });
            }

            function loadColumnsConfigFromServer() {
                $.ajax({
                    url: '{{ route('admin.school-leads.columns-settings.get') }}',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        var merged = {};
                        Object.keys(defaultColumnsVisibility).forEach(function(key) {
                            if (key === 'location' && !canViewLocations) {
                                merged[key] = false;
                                return;
                            }
                            if (key === 'contract' && !canShowLeadClientColumn) {
                                merged[key] = false;
                                return;
                            }
                            merged[key] = toBool(
                                Object.prototype.hasOwnProperty.call(response, key) ?
                                response[key] : defaultColumnsVisibility[key],
                                defaultColumnsVisibility[key]
                            );
                        });
                        currentColumnsConfig = merged;
                        applyVisibleColumns(currentColumnsConfig);
                    },
                    error: function() {
                        currentColumnsConfig = Object.assign({}, defaultColumnsVisibility);
                        applyVisibleColumns(currentColumnsConfig);
                    }
                });
            }

            var locationColumn = {
                data: 'location_name',
                name: 'location_name',
                render: function(data) {
                    return data ? $('<div/>').text(data).html() : '—';
                }
            };

            var dataTableColumns = [
                { data: 'id', name: 'id' },
                { data: 'name', name: 'name' },
                { data: 'phone', name: 'phone' }
            ];

            if (canViewLocations) {
                dataTableColumns.push(locationColumn);
            }

            dataTableColumns.push(
                {
                    data: 'utm_summary',
                    name: 'utm_source',
                    render: function(data) { return data ? $('<div/>').text(data).html() : '—'; }
                },
                {
                    data: 'page_url',
                    name: 'page_url',
                    render: function(data) {
                        if (!data) return '—';
                        var short = data.length > 40 ? data.substring(0, 37) + '...' : data;
                        return '<a href="' + data + '" target="_blank" rel="noopener">' + short + '</a>';
                    }
                },
                {
                    data: 'status_label',
                    name: 'status',
                    render: function(data, type, row) {
                        var status = row.status;
                        var label = row.status_label || '—';
                        var badgeClass = getStatusBadgeClass(status);
                        var optionsHtml = buildStatusOptionsHtml(status);
                        return '' +
                            '<div class="d-flex align-items-center gap-1">' +
                            '<span class="badge ' + badgeClass + ' lead-status-badge" data-id="' + row.id + '" data-status="' + (status || '') + '">' + label + '</span>' +
                            '<select class="form-select form-select-sm lead-status-select d-none" data-id="' + row.id + '">' + optionsHtml + '</select>' +
                            '</div>';
                    }
                },
                {
                    data: 'comment',
                    name: 'comment',
                    render: function(data) { return data ? $('<div/>').text(data).html() : ''; }
                }
            );

            if (canShowLeadClientColumn) {
                dataTableColumns.push({
                    data: null,
                    name: 'contract',
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        if (!row.user_id) {
                            if (canCreateUserFromLead) {
                                return '<button type="button" class="btn btn-sm btn-primary text-nowrap create-user-from-lead" data-id="' + row.id + '">Создать клиента</button>';
                            }
                            return '—';
                        }
                        if (!canViewContracts) {
                            return '—';
                        }
                        if (row.latest_contract && row.latest_contract.url) {
                            var contractLabel = row.latest_contract.label || ('Договор №' + row.latest_contract.id);
                            var contractLabelEscaped = $('<div/>').text(contractLabel).html();
                            return '<a href="' + row.latest_contract.url + '" class="text-nowrap">' + contractLabelEscaped + '</a>';
                        }
                        if (row.create_contract_url) {
                            return '<a href="' + row.create_contract_url + '" class="btn btn-sm btn-primary text-nowrap">Создать договор</a>';
                        }
                        return '—';
                    }
                });
            }

            dataTableColumns.push({
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    return '' +
                        '<button type="button" class="btn btn-sm btn-primary me-1 edit-lead" data-id="' + row.id + '" title="Редактировать"><i class="fa fa-edit"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-danger delete-lead" data-id="' + row.id + '" title="Удалить"><i class="fa fa-trash"></i></button>';
                }
            });

            var table = $('#leads-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('admin.school-leads.data') }}',
                    type: 'GET',
                    data: function(d) {
                        d.statuses = appliedFilters.statuses;
                        if (canViewLocations) {
                            d.location_id = appliedFilters.location_id;
                        }
                    },
                    dataSrc: function(json) {
                        updateSchoolLeadsStats(json.stats);
                        return json.data;
                    }
                },
                columns: dataTableColumns,
                order: [[0, 'desc']],
                language: {
                    processing: 'Загрузка...',
                    search: 'Поиск:',
                    lengthMenu: 'Показать _MENU_ записей',
                    info: 'Показаны _START_–_END_ из _TOTAL_',
                    infoEmpty: 'Нет записей',
                    infoFiltered: '(отфильтровано из _MAX_ записей)',
                    loadingRecords: 'Загрузка...',
                    zeroRecords: 'Совпадений не найдено',
                    emptyTable: 'Данные отсутствуют',
                    paginate: {
                        first: 'Первая',
                        previous: 'Предыдущая',
                        next: 'Следующая',
                        last: 'Последняя'
                    }
                }
            });

            loadColumnsConfigFromServer();

            $filtersForm.on('submit', function(e) {
                e.preventDefault();
                appliedFilters = readFiltersFromForm();
                table.ajax.reload();
            });

            $('#schoolLeadsFiltersResetBtn').on('click', function() {
                resetFiltersFormToDefault();
                appliedFilters = readFiltersFromForm();
                table.ajax.reload();
            });

            $('.school-leads-column-toggle').on('change', function() {
                var key = $(this).data('column-key');
                currentColumnsConfig[key] = $(this).is(':checked') ? 1 : 0;
                applyVisibleColumns(currentColumnsConfig);
                $.ajax({
                    url: '{{ route('admin.school-leads.columns-settings.save') }}',
                    type: 'POST',
                    data: { _token: csrfToken, columns: currentColumnsConfig },
                    error: function() {
                        console.error('Не удалось сохранить настройки колонок');
                    }
                });
            });

            $('#leads-table').on('click', '.edit-lead', function() {
                var rowData = table.row($(this).closest('tr')).data();
                $('#editLeadId').val(rowData.id);
                $('#leadStatus').val(rowData.status || '');
                $('#leadComment').val(rowData.comment || '');
                if (canViewLocations) {
                    $('#leadLocation').val(rowData.location_id || '').removeClass('is-invalid');
                    $('#leadLocationError').text('');
                }
                $('#editLeadError, #editLeadSuccess').addClass('d-none').text('');
                editLeadModal.show();
            });

            $('#saveLeadBtn').on('click', function() {
                var id = $('#editLeadId').val();
                var payload = { status: $('#leadStatus').val(), comment: $('#leadComment').val() };
                if (canViewLocations) {
                    payload.location_id = $('#leadLocation').val();
                }
                $('#editLeadError, #editLeadSuccess').addClass('d-none').text('');
                if (canViewLocations) {
                    $('#leadLocation').removeClass('is-invalid');
                    $('#leadLocationError').text('');
                }
                $.ajax({
                    url: '/admin/school-leads/' + id,
                    type: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: payload,
                    success: function(response) {
                        $('#editLeadSuccess').removeClass('d-none').text(response.message || 'Сохранено.');
                        table.ajax.reload(null, false);
                        showToast(response.message || 'Изменения сохранены.', 'success');
                        setTimeout(function() { editLeadModal.hide(); }, 600);
                    },
                    error: function(xhr) {
                        var message = 'Ошибка сохранения.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        if (canViewLocations && xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.location_id) {
                            $('#leadLocation').addClass('is-invalid');
                            $('#leadLocationError').text(xhr.responseJSON.errors.location_id[0]);
                        }
                        $('#editLeadError').removeClass('d-none').text(message);
                        showToast(message, 'error');
                    }
                });
            });

            $('#leads-table').on('click', '.lead-status-badge', function() {
                var $badge = $(this);
                var $container = $badge.closest('div');
                $badge.addClass('d-none');
                $container.find('.lead-status-select').removeClass('d-none').focus();
            });

            $('#leads-table').on('change', '.lead-status-select', function() {
                var $select = $(this);
                var id = $select.data('id');
                $.ajax({
                    url: '/admin/school-leads/' + id,
                    type: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: { status: $select.val() },
                    success: function(response) {
                        var $container = $select.closest('div');
                        var $badge = $container.find('.lead-status-badge');
                        $badge.removeClass('bg-secondary bg-warning text-dark bg-success bg-danger bg-dark')
                            .addClass(getStatusBadgeClass(response.status))
                            .attr('data-status', response.status || '')
                            .text(response.status_label || '—');
                        $select.addClass('d-none');
                        $badge.removeClass('d-none');
                        showToast(response.message || 'Статус обновлён.', 'success');
                        table.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Ошибка обновления статуса.', 'error');
                        var $badge = $select.closest('div').find('.lead-status-badge');
                        $select.val($badge.data('status') || '').addClass('d-none');
                        $badge.removeClass('d-none');
                    }
                });
            });

            $('#leads-table').on('blur', '.lead-status-select', function() {
                var $select = $(this);
                setTimeout(function() {
                    if (!$select.is(':focus')) {
                        $select.addClass('d-none');
                        $select.closest('div').find('.lead-status-badge').removeClass('d-none');
                    }
                }, 150);
            });

            $('#leads-table').on('click', '.delete-lead', function() {
                leadIdToDelete = table.row($(this).closest('tr')).data().id;
                deleteLeadModal.show();
            });

            $('#confirmDeleteLeadBtn').on('click', function() {
                if (!leadIdToDelete) return;
                $.ajax({
                    url: '/admin/school-leads/' + leadIdToDelete,
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    success: function(response) {
                        deleteLeadModal.hide();
                        leadIdToDelete = null;
                        table.ajax.reload(null, false);
                        showToast(response.message || 'Заявка удалена.', 'success');
                    },
                    error: function() {
                        deleteLeadModal.hide();
                        leadIdToDelete = null;
                        showToast('Ошибка при удалении заявки.', 'error');
                    }
                });
            });

            if (canCreateUserFromLead) {
                var createUserModalEl = document.getElementById('createUserModal');
                var createUserModal = createUserModalEl ? new bootstrap.Modal(createUserModalEl) : null;
                var $createUserForm = $('#create-user-form');

                function resetCreateUserFormErrors() {
                    $createUserForm.find('.is-invalid').removeClass('is-invalid');
                    $createUserForm.find('.invalid-feedback').remove();
                }

                function resetCreateUserFormFields() {
                    if (!$createUserForm.length) {
                        return;
                    }
                    $createUserForm[0].reset();
                    $('#create-school-lead-id').val('');
                    $createUserForm.removeData('success-handler');
                    resetCreateUserFormErrors();
                }

                $('#leads-table').on('click', '.create-user-from-lead', function() {
                    var rowData = table.row($(this).closest('tr')).data();
                    if (!rowData || rowData.user_id) {
                        return;
                    }

                    resetCreateUserFormFields();

                    $('#create-name').val(rowData.name || '');
                    $('#create-lastname').val('');
                    $('#create-school-lead-id').val(rowData.id);

                    var $phone = $('#create-phone');
                    if ($phone.length && !$phone.prop('disabled')) {
                        $phone.val(rowData.phone || '');
                        if ($phone.inputmask) {
                            $phone.trigger('input');
                        }
                    }

                    if (canViewLocations) {
                        $('#create-location').val(rowData.location_id || '');
                    }

                    $createUserForm.data('success-handler', 'school-leads-table');

                    if (createUserModal) {
                        createUserModal.show();
                    }
                });

                if (createUserModalEl) {
                    createUserModalEl.addEventListener('hidden.bs.modal', function() {
                        resetCreateUserFormFields();
                    });
                }

                window.onSchoolLeadUserCreated = function(response) {
                    resetCreateUserFormFields();
                    table.ajax.reload(null, false);
                    showToast(response.message || 'Клиент создан.', 'success');
                };
            }
        });
    </script>
@endsection

