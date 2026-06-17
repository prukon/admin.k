@php
    $canViewLocations = $canViewLocations ?? (auth()->user() && auth()->user()->can('locations.view'));
    $canViewDistricts = $canViewDistricts ?? (auth()->user() && auth()->user()->can('districts.view'));
    $canCreateUserFromLead = $canCreateUserFromLead ?? (auth()->user() && auth()->user()->can('users.view'));
    $canViewContracts = $canViewContracts ?? (auth()->user() && auth()->user()->can('contracts.view'));
    $canShowLeadClientColumn = $canViewContracts || $canCreateUserFromLead;
    $leadStats = $leadStats ?? ['total' => 0, 'new' => 0];
    $leadsHasActiveFilters = false;
    $schoolLeadStatuses = $schoolLeadStatuses ?? collect();
    $defaultStatusFilterIds = $defaultStatusFilterIds ?? [];
    $filterTeams = $filterTeams ?? collect();
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
                        <button type="button"
                                class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                data-bs-toggle="modal"
                                data-bs-target="#schoolLeadStatusesModal"
                                title="Настройки статусов">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-cog payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Настройки</span>
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
                                    <label class="form-check-label" for="slColName">ФИО родителя</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="status" id="slColStatus" checked>
                                    <label class="form-check-label" for="slColStatus">Статус</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="phone" id="slColPhone" checked>
                                    <label class="form-check-label" for="slColPhone">Телефон родителя</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="parent_email" id="slColParentEmail" checked>
                                    <label class="form-check-label" for="slColParentEmail">Email родителя</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="child_full_name" id="slColChildName" checked>
                                    <label class="form-check-label" for="slColChildName">ФИО ребенка</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="child_birthday" id="slColChildBirthday" checked>
                                    <label class="form-check-label" for="slColChildBirthday">Дата рождения</label>
                                </div>
                                @if ($canViewDistricts)
                                    <div class="form-check">
                                        <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="district" id="slColDistrict" checked>
                                        <label class="form-check-label" for="slColDistrict">Район</label>
                                    </div>
                                @endif
                                @if ($canViewLocations)
                                    <div class="form-check">
                                        <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="location" id="slColLocation" checked>
                                        <label class="form-check-label" for="slColLocation">Объект</label>
                                    </div>
                                @endif
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="team_title" id="slColTeam" checked>
                                    <label class="form-check-label" for="slColTeam">Секция</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="child_flags" id="slColChildFlags" checked>
                                    <label class="form-check-label" for="slColChildFlags">Особые условия</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="utm" id="slColUtm" checked>
                                    <label class="form-check-label" for="slColUtm">UTM / источник</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input school-leads-column-toggle" type="checkbox" data-column-key="page_url" id="slColPageUrl" checked>
                                    <label class="form-check-label" for="slColPageUrl">Страница</label>
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
                <div class="col-12 col-md-3">
                    <label class="form-label" for="sl-filter-status">Статус</label>
                    <select class="form-select js-filter-multiselect-select"
                            id="sl-filter-status"
                            name="status_ids[]"
                            multiple
                            data-placeholder="Выберите статусы">
                        @foreach ($schoolLeadStatuses as $status)
                            <option value="{{ $status->id }}" @selected(in_array((string) $status->id, $defaultStatusFilterIds, true))>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($canViewDistricts)
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="sl-filter-district">Район</label>
                        <select class="form-select" id="sl-filter-district" name="district_id">
                            <option value="">Все районы</option>
                            <option value="none">Без района</option>
                            @foreach ($activeDistricts as $district)
                                <option value="{{ $district->id }}">{{ $district->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($canViewLocations)
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="sl-filter-location">Объект</label>
                        <select class="form-select" id="sl-filter-location" name="location_id">
                            <option value="">Все объекты</option>
                            <option value="none">Без объекта</option>
                            @foreach ($activeLocations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-12 col-md-3">
                    <label class="form-label" for="sl-filter-team">Секция</label>
                    <select class="form-select" id="sl-filter-team" name="team_id">
                        <option value="">Все секции</option>
                        <option value="none">Без секции</option>
                        @foreach ($filterTeams as $team)
                            <option value="{{ $team->id }}">{{ $team->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-auto">
                    <div class="form-check mb-0 pb-md-1">
                        <input class="form-check-input" type="checkbox" value="1" id="sl-filter-special-conditions" name="has_special_conditions">
                        <label class="form-check-label text-nowrap" for="sl-filter-special-conditions">Есть особые условия</label>
                    </div>
                </div>

                <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                    <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                    <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="schoolLeadsFiltersResetBtn">Сброс</button>
                </div>
            </div>
        </form>
    </div>

    <table id="leads-table" class="table table-bordered table-striped align-middle w-100 dt-columns-managed">
        <thead>
            <tr>
                <th>№</th>
                <th>ФИО родителя</th>
                <th class="lead-status-col-header" title="Статус можно изменить прямо в таблице — нажмите на бейдж в строке">Статус</th>
                <th>Телефон родителя</th>
                <th>Email родителя</th>
                <th>ФИО ребенка</th>
                <th>Дата рождения</th>
                @if ($canViewDistricts)
                    <th>Район</th>
                @endif
                @if ($canViewLocations)
                    <th>Объект</th>
                @endif
                <th>Секция</th>
                <th>Особые условия</th>
                <th>UTM / источник</th>
                <th>Страница</th>
                <th>Комментарий</th>
                @if ($canShowLeadClientColumn)
                    <th>Договор</th>
                @endif
                <th>Действия</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

</div>

@include('admin.school-leads.partials.status-settings')

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
                            @foreach ($schoolLeadStatuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="leadStatusError"></div>
                    </div>
                    @if ($canViewDistricts)
                        <div class="mb-3">
                            <label for="leadDistrict" class="form-label">Район</label>
                            <select id="leadDistrict" class="form-select">
                                <option value="">— не выбран —</option>
                                @foreach ($activeDistricts as $district)
                                    <option value="{{ $district->id }}">{{ $district->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="leadDistrictError"></div>
                        </div>
                    @endif
                    @if ($canViewLocations)
                        <div class="mb-3">
                            <label for="leadLocation" class="form-label">Объект</label>
                            <select id="leadLocation" class="form-select">
                                <option value="">— не выбран —</option>
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

@include('includes.logModal')

@push('scripts')
    <script>
        $(document).ready(function() {
            showLogModal(@json(route('logs.data.school-lead')));

            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            var canViewLocations = @json($canViewLocations);
            var canViewDistricts = @json($canViewDistricts);
            var canCreateUserFromLead = @json($canCreateUserFromLead);
            var canViewContracts = @json($canViewContracts);
            var canShowLeadClientColumn = @json($canShowLeadClientColumn);
            var schoolLeadStatuses = @json($schoolLeadStatuses->map(fn ($status) => $status->toFrontendArray())->values());
            var defaultStatusFilterIds = @json($defaultStatusFilterIds);
            var schoolLeadStatusRoutes = {
                index: @json(route('admin.school-leads.statuses.index')),
                store: @json(route('admin.school-leads.statuses.store')),
                update: @json(route('admin.school-leads.statuses.update', ['schoolLeadStatus' => '__ID__'])),
                destroy: @json(route('admin.school-leads.statuses.destroy', ['schoolLeadStatus' => '__ID__'])),
            };

            var $filtersForm = $('#school-leads-filters');
            var $statNew = $('.school-leads-stat-new');
            var $statTotal = $('.school-leads-stat-total');
            var $toolbarRoot = $('#schoolLeadsReportToolbar');

            var editLeadModal = new bootstrap.Modal(document.getElementById('editLeadModal'));
            var deleteLeadModal = new bootstrap.Modal(document.getElementById('deleteLeadModal'));
            var schoolLeadStatusesModal = new bootstrap.Modal(document.getElementById('schoolLeadStatusesModal'));
            var schoolLeadStatusFormModal = new bootstrap.Modal(document.getElementById('schoolLeadStatusFormModal'));

            document.getElementById('schoolLeadStatusFormModal').addEventListener('shown.bs.modal', function () {
                var backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops.length) {
                    backdrops[backdrops.length - 1].style.zIndex = '1060';
                }
            });
            var leadIdToDelete = null;

            var toastEl = document.getElementById('mainToast');
            var toastBodyEl = document.getElementById('mainToastBody');
            var toastInstance = new bootstrap.Toast(toastEl, { delay: 2500 });

            var defaultStatusFilters = defaultStatusFilterIds.slice();
            var $statusFilter = $('#sl-filter-status');
            var $districtFilter = $('#sl-filter-district');
            var $locationFilter = $('#sl-filter-location');
            var $teamFilter = $('#sl-filter-team');
            var $specialConditionsFilter = $('#sl-filter-special-conditions');

            if ($statusFilter.length && window.KidsCrmFilterMultiselectSelect2) {
                KidsCrmFilterMultiselectSelect2.init($statusFilter, {
                    placeholder: $statusFilter.data('placeholder') || 'Выберите статусы',
                    allowClear: true,
                    dropdownParent: $('#school-leads-filters')
                });
            }

            function readFiltersFromForm() {
                var statusIds = $statusFilter.length ? ($statusFilter.val() || []) : [];
                var districtId = '';
                if (canViewDistricts && $districtFilter.length) {
                    districtId = $districtFilter.val() || '';
                }
                var locationId = '';
                if (canViewLocations && $locationFilter.length) {
                    locationId = $locationFilter.val() || '';
                }
                var teamId = $teamFilter.length ? ($teamFilter.val() || '') : '';
                var hasSpecialConditions = $specialConditionsFilter.length && $specialConditionsFilter.is(':checked');

                return {
                    status_ids: statusIds,
                    district_id: districtId,
                    location_id: locationId,
                    team_id: teamId,
                    has_special_conditions: hasSpecialConditions ? 1 : 0
                };
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
                if (stats.total !== undefined) {
                    $statTotal.text(schoolLeadsFormatCount(stats.total));
                }
            }

            function resetFiltersFormToDefault() {
                if ($statusFilter.length && window.KidsCrmFilterMultiselectSelect2) {
                    KidsCrmFilterMultiselectSelect2.setValues($statusFilter, defaultStatusFilters);
                } else if ($statusFilter.length) {
                    $statusFilter.val(defaultStatusFilters).trigger('change');
                }
                if (canViewDistricts && $districtFilter.length) {
                    $districtFilter.val('');
                }
                if (canViewLocations && $locationFilter.length) {
                    $locationFilter.val('');
                }
                if ($teamFilter.length) {
                    $teamFilter.val('');
                }
                if ($specialConditionsFilter.length) {
                    $specialConditionsFilter.prop('checked', false);
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

            function getStatusBadgeStyle(statusId, row) {
                if (row && row.status_badge_style) {
                    return row.status_badge_style;
                }

                if (row && row.status_color) {
                    var textColor = row.status_text_color || '#ffffff';
                    return 'background-color:' + row.status_color + ';color:' + textColor + ';';
                }

                var statusIdToMatch = statusId || (row && row.school_lead_status_id);
                if (statusIdToMatch) {
                    var matchedStatus = schoolLeadStatuses.find(function (status) {
                        return String(status.id) === String(statusIdToMatch);
                    });
                    if (matchedStatus) {
                        if (matchedStatus.badge_style) {
                            return matchedStatus.badge_style;
                        }
                        if (matchedStatus.color) {
                            var matchedTextColor = matchedStatus.text_color || '#ffffff';
                            return 'background-color:' + matchedStatus.color + ';color:' + matchedTextColor + ';';
                        }
                    }
                }

                return '';
            }

            var leadStatusEditHint = 'Нажмите, чтобы изменить статус';
            var $leadStatusOpenMenu = null;

            function getLeadStatusOptionBadgeStyle(option) {
                if (option && option.badge_style) {
                    return option.badge_style;
                }

                if (option && option.color) {
                    var textColor = option.text_color || '#ffffff';
                    return 'background-color:' + option.color + ';color:' + textColor + ';';
                }

                return '';
            }

            function closeAllLeadStatusMenus() {
                $('.lead-status-inline-picker.is-open .lead-status-inline-trigger').attr('aria-expanded', 'false');
                $('.lead-status-inline-picker.is-open').removeClass('is-open');

                if ($leadStatusOpenMenu && $leadStatusOpenMenu.length) {
                    var $ownerPicker = $leadStatusOpenMenu.data('owner-picker');
                    if ($ownerPicker && $ownerPicker.length) {
                        $leadStatusOpenMenu.addClass('d-none').appendTo($ownerPicker);
                    } else {
                        $leadStatusOpenMenu.addClass('d-none');
                    }
                    $leadStatusOpenMenu = null;
                }
            }

            function positionLeadStatusMenu($picker, $menu) {
                var trigger = $picker.find('.lead-status-inline-trigger').get(0);
                if (!trigger) {
                    return;
                }

                var rect = trigger.getBoundingClientRect();
                $menu.css({
                    top: Math.round(rect.bottom + 4) + 'px',
                    left: Math.round(rect.left) + 'px',
                    minWidth: Math.max(Math.round(rect.width), 112) + 'px',
                });
            }

            function buildLeadStatusMenuHtml(currentStatusId) {
                var esc = window.KidsCrmTooltip.escapeHtml;
                var html = '';

                leadStatusInlineSelectOptions.forEach(function (option) {
                    var isActive = String(option.value) === String(currentStatusId || '');
                    var badgeStyle = getLeadStatusOptionBadgeStyle(option);
                    var badgeClass = badgeStyle ? 'badge' : 'badge bg-secondary';
                    var styleAttr = badgeStyle ? (' style="' + esc(badgeStyle) + '"') : '';
                    var activeClass = isActive ? ' is-active' : '';

                    html += '<button type="button"'
                        + ' class="lead-status-inline-option ' + esc(badgeClass) + activeClass + '"'
                        + ' data-value="' + esc(option.value) + '"' + styleAttr
                        + ' role="option"'
                        + (isActive ? ' aria-selected="true"' : ' aria-selected="false"')
                        + '>'
                        + esc(option.label)
                        + '</button>';
                });

                return html;
            }

            function openLeadStatusInlineSelect($badge) {
                var $picker = $badge.closest('.lead-status-inline-picker');
                var $menu = $picker.find('.lead-status-inline-menu');
                var isSamePickerOpen = $picker.hasClass('is-open');

                closeAllLeadStatusMenus();

                if (isSamePickerOpen) {
                    return;
                }

                $menu.html(buildLeadStatusMenuHtml($badge.data('status')));
                $menu.data('owner-picker', $picker);
                $menu.appendTo(document.body);
                $leadStatusOpenMenu = $menu;
                $picker.addClass('is-open');
                $badge.attr('aria-expanded', 'true');
                $menu.removeClass('d-none');
                positionLeadStatusMenu($picker, $menu);
                $menu.find('.lead-status-inline-option.is-active').first().focus();
            }

            function saveLeadStatusInline(leadId, statusId, $picker) {
                closeAllLeadStatusMenus();

                $.ajax({
                    url: '/admin/school-leads/' + leadId,
                    type: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: { school_lead_status_id: statusId || '' },
                    success: function(response) {
                        var $badge = $picker.find('.lead-status-badge');
                        var badgeStyle = getStatusBadgeStyle(response.school_lead_status_id, response);
                        $badge.removeClass('bg-secondary bg-warning text-dark bg-success bg-danger bg-dark');
                        if (badgeStyle) {
                            $badge.attr('style', badgeStyle);
                        } else {
                            $badge.removeAttr('style').addClass('bg-secondary');
                        }
                        $badge
                            .attr('data-status', response.school_lead_status_id || '')
                            .attr('aria-label', leadStatusEditHint + ': ' + (response.status_label || '—'));
                        showToast(response.message || 'Статус обновлён.', 'success');
                        dtApi.reload({ keepPage: true });
                    },
                    error: function(xhr) {
                        showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Ошибка обновления статуса.', 'error');
                    }
                });
            }

            function renderLeadStatusInlineSelect(value, type, row) {
                row = row || {};
                var esc = window.KidsCrmTooltip.escapeHtml;
                var status = row.school_lead_status_id;
                var label = row.status_label || '—';
                var rowId = row.id;

                if (type !== 'display') {
                    return value || label || '';
                }

                var badgeStyle = getStatusBadgeStyle(status, row);
                var badgeClassPart = badgeStyle ? 'badge' : 'badge bg-secondary';
                var badgeStyleAttr = badgeStyle ? (' style="' + esc(badgeStyle) + '"') : '';
                var ariaLabel = leadStatusEditHint + ': ' + label;

                return ''
                    + '<div class="d-flex align-items-center gap-1 lead-status-inline-picker" data-lead-id="' + esc(rowId) + '">'
                    + '<span class="' + esc(badgeClassPart) + ' lead-status-badge lead-status-inline-trigger"'
                    + ' role="button" tabindex="0"'
                    + ' title="' + esc(leadStatusEditHint) + '"'
                    + ' aria-haspopup="listbox"'
                    + ' aria-expanded="false"'
                    + ' aria-label="' + esc(ariaLabel) + '"'
                    + ' data-id="' + esc(rowId) + '" data-status="' + esc(status || '') + '"' + badgeStyleAttr + '>'
                    + esc(label)
                    + '<i class="fas fa-caret-down lead-status-inline-caret" aria-hidden="true"></i>'
                    + '</span>'
                    + '<div class="lead-status-inline-menu d-none" role="listbox"'
                    + ' aria-label="' + esc(leadStatusEditHint) + '"></div>'
                    + '</div>';
            }

            function buildLeadStatusInlineSelectOptions() {
                var options = [{ value: '', label: '— не выбран —', badge_style: '', color: null, text_color: '#ffffff' }];
                schoolLeadStatuses.forEach(function (status) {
                    options.push({
                        value: String(status.id),
                        label: status.name,
                        badge_style: status.badge_style || '',
                        color: status.color || null,
                        text_color: status.text_color || '#ffffff',
                    });
                });
                return options;
            }

            var leadStatusInlineSelectOptions = buildLeadStatusInlineSelectOptions();

            function renderChildFlags(row) {
                var badges = [];
                if (row.is_individual_traits) {
                    badges.push('<span class="badge bg-info text-dark me-1 mb-1">Особенности</span>');
                }
                if (row.is_on_medical_register) {
                    badges.push('<span class="badge bg-warning text-dark me-1 mb-1">Мед. учёт</span>');
                }
                if (row.is_with_disability) {
                    badges.push('<span class="badge bg-secondary me-1 mb-1">Инвалидность</span>');
                }
                if (!badges.length) {
                    return '—';
                }
                return '<div class="d-flex flex-wrap gap-1">' + badges.join('') + '</div>';
            }

            function renderOptionalText(data) {
                return data ? $('<div/>').text(data).html() : '—';
            }

            var dtApi = KidsCrmDataTable.create('#leads-table', {
                columnsSettings: {
                    defaults: {
                        name: true,
                        phone: true,
                        parent_email: true,
                        child_full_name: true,
                        child_birthday: true,
                        district: canViewDistricts,
                        team_title: true,
                        child_flags: true,
                        location: canViewLocations,
                        utm: true,
                        page_url: true,
                        status: true,
                        comment: true,
                        contract: canShowLeadClientColumn,
                        actions: true,
                    },
                    toggleSelector: '.school-leads-column-toggle',
                    urls: {
                        get: @json(route('admin.school-leads.columns-settings.get')),
                        save: @json(route('admin.school-leads.columns-settings.save')),
                    },
                    csrfToken: csrfToken,
                },
                dataTable: {
                    ajax: {
                        url: @json(route('admin.school-leads.data')),
                        type: 'GET',
                        data: function (d) {
                            d.status_ids = appliedFilters.status_ids;
                            if (canViewDistricts) {
                                d.district_id = appliedFilters.district_id;
                            }
                            if (canViewLocations) {
                                d.location_id = appliedFilters.location_id;
                            }
                            d.team_id = appliedFilters.team_id;
                            if (appliedFilters.has_special_conditions) {
                                d.has_special_conditions = appliedFilters.has_special_conditions;
                            }
                        },
                        dataSrc: function (json) {
                            updateSchoolLeadsStats(json.stats);
                            return json.data;
                        },
                    },
                    order: [[0, 'desc']],
                    language: @include('partials.datatables.ru'),
                },
                columns: [
                    { type: 'id', data: 'id', name: 'id' },
                    {
                        key: 'name',
                        type: 'link',
                        data: 'parent_full_name',
                        name: 'name',
                        className: 'dt-col-text',
                        linkClass: 'edit-lead',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            if (!data) {
                                return '—';
                            }

                            return window.KidsCrmTooltip.renderLink(data, {
                                linkClass: 'edit-lead',
                                extraAttrs: 'data-id="' + row.id + '"',
                            });
                        },
                    },
                    {
                        key: 'status',
                        type: 'inline-select',
                        data: 'status_label',
                        name: 'status',
                        className: 'dt-col-inline-select',
                        render: renderLeadStatusInlineSelect,
                        inlineSelect: {
                            statusKey: 'school_lead_status_id',
                            labelKey: 'status_label',
                            rowIdKey: 'id',
                            badgeSelector: 'lead-status-badge',
                            selectSelector: 'lead-status-select',
                            badgeExtraClass: '',
                            selectExtraClass: 'form-select form-select-sm d-none',
                            badgeStyleFn: getStatusBadgeStyle,
                            options: leadStatusInlineSelectOptions,
                        },
                    },
                    {
                        key: 'phone',
                        type: 'text',
                        data: 'parent_phone',
                        name: 'phone',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            return renderOptionalText(data);
                        },
                    },
                    {
                        key: 'parent_email',
                        type: 'text',
                        data: 'parent_email',
                        name: 'parent_email',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            return renderOptionalText(data);
                        },
                    },
                    {
                        key: 'child_full_name',
                        type: 'text',
                        data: 'child_full_name',
                        name: 'child_full_name',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            return renderOptionalText(data);
                        },
                    },
                    {
                        key: 'child_birthday',
                        type: 'text',
                        data: 'child_birthday',
                        name: 'child_birthday',
                        className: 'dt-col-text text-nowrap',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            return renderOptionalText(data);
                        },
                    },
                    {
                        key: 'district',
                        type: 'text',
                        data: 'district_name',
                        name: 'district_name',
                        when: canViewDistricts,
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            return renderOptionalText(data);
                        },
                    },
                    {
                        key: 'location',
                        type: 'text',
                        data: 'location_name',
                        name: 'location_name',
                        when: canViewLocations,
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            return renderOptionalText(data);
                        },
                    },
                    {
                        key: 'team_title',
                        type: 'text',
                        data: 'team_title',
                        name: 'team_title',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            return renderOptionalText(data);
                        },
                    },
                    {
                        key: 'child_flags',
                        type: 'text',
                        data: null,
                        name: 'child_flags',
                        orderable: false,
                        searchable: false,
                        className: 'dt-col-list',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return '';
                            }

                            return renderChildFlags(row);
                        },
                    },
                    {
                        key: 'utm',
                        type: 'text',
                        data: 'utm_summary',
                        name: 'utm_source',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            return data ? $('<div/>').text(data).html() : '—';
                        },
                    },
                    {
                        key: 'page_url',
                        type: 'link',
                        data: 'page_url',
                        name: 'page_url',
                        className: 'dt-col-text',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            if (!data) {
                                return '<span class="dt-cell-empty text-muted">—</span>';
                            }

                            var short = data.length > 40 ? data.substring(0, 37) + '...' : data;
                            return '<a href="' + $('<div/>').text(data).html() + '" target="_blank" rel="noopener">'
                                + $('<div/>').text(short).html()
                                + '</a>';
                        },
                    },
                    {
                        key: 'comment',
                        type: 'text',
                        data: 'comment',
                        name: 'comment',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            if (!data) {
                                return '';
                            }

                            return window.KidsCrmTooltip.renderText(data);
                        },
                    },
                    {
                        key: 'contract',
                        type: 'actions',
                        when: canShowLeadClientColumn,
                        className: 'dt-col-text text-start text-nowrap',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return '';
                            }

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
                        },
                    },
                    {
                        key: 'actions',
                        type: 'actions',
                        className: 'dt-col-actions',
                        render: function (data, type, row) {
                            return '' +
                                '<button type="button" class="btn btn-sm btn-primary me-1 edit-lead" data-id="' + row.id + '" title="Редактировать"><i class="fa fa-edit"></i></button>' +
                                '<button type="button" class="btn btn-sm btn-danger delete-lead" data-id="' + row.id + '" title="Удалить"><i class="fa fa-trash"></i></button>';
                        },
                    },
                ],
            });

            var table = dtApi.table;

            $filtersForm.on('submit', function(e) {
                e.preventDefault();
                appliedFilters = readFiltersFromForm();
                dtApi.reload({ keepPage: true });
            });

            $('#schoolLeadsFiltersResetBtn').on('click', function() {
                resetFiltersFormToDefault();
                appliedFilters = readFiltersFromForm();
                dtApi.reload({ keepPage: true });
            });

            $('#leads-table').on('click', '.edit-lead', function() {
                var rowData = table.row($(this).closest('tr')).data();
                $('#editLeadId').val(rowData.id);
                $('#leadStatus').val(rowData.school_lead_status_id ? String(rowData.school_lead_status_id) : '').removeClass('is-invalid');
                $('#leadStatusError').text('');
                $('#leadComment').val(rowData.comment || '');
                if (canViewDistricts) {
                    $('#leadDistrict').val(rowData.district_id || '').removeClass('is-invalid');
                    $('#leadDistrictError').text('');
                }
                if (canViewLocations) {
                    $('#leadLocation').val(rowData.location_id || '').removeClass('is-invalid');
                    $('#leadLocationError').text('');
                }
                $('#editLeadError, #editLeadSuccess').addClass('d-none').text('');
                editLeadModal.show();
            });

            $('#saveLeadBtn').on('click', function() {
                var id = $('#editLeadId').val();
                var payload = {
                    school_lead_status_id: $('#leadStatus').val(),
                    comment: $('#leadComment').val(),
                };
                if (canViewDistricts) {
                    payload.district_id = $('#leadDistrict').val();
                }
                if (canViewLocations) {
                    payload.location_id = $('#leadLocation').val();
                }
                $('#editLeadError, #editLeadSuccess').addClass('d-none').text('');
                if (canViewDistricts) {
                    $('#leadDistrict').removeClass('is-invalid');
                    $('#leadDistrictError').text('');
                }
                if (canViewLocations) {
                    $('#leadLocation').removeClass('is-invalid');
                    $('#leadLocationError').text('');
                }
                $('#leadStatus').removeClass('is-invalid');
                $('#leadStatusError').text('');
                $.ajax({
                    url: '/admin/school-leads/' + id,
                    type: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: payload,
                    success: function(response) {
                        $('#editLeadSuccess').removeClass('d-none').text(response.message || 'Сохранено.');
                        dtApi.reload({ keepPage: true });
                        showToast(response.message || 'Изменения сохранены.', 'success');
                        setTimeout(function() { editLeadModal.hide(); }, 600);
                    },
                    error: function(xhr) {
                        var message = 'Ошибка сохранения.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.school_lead_status_id) {
                            $('#leadStatus').addClass('is-invalid');
                            $('#leadStatusError').text(xhr.responseJSON.errors.school_lead_status_id[0]);
                        }
                        if (canViewDistricts && xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.district_id) {
                            $('#leadDistrict').addClass('is-invalid');
                            $('#leadDistrictError').text(xhr.responseJSON.errors.district_id[0]);
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

            $('#leads-table').on('click', '.lead-status-badge', function(event) {
                event.stopPropagation();
                openLeadStatusInlineSelect($(this));
            });

            $('#leads-table').on('keydown', '.lead-status-badge', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    event.stopPropagation();
                    openLeadStatusInlineSelect($(this));
                }
            });

            $(document).on('click', '.lead-status-inline-menu', function(event) {
                event.stopPropagation();
            });

            $(document).on('click', '.lead-status-inline-option', function(event) {
                event.stopPropagation();
                var $option = $(this);
                var $menu = $option.closest('.lead-status-inline-menu');
                var $picker = $menu.data('owner-picker');

                if (!$picker || !$picker.length) {
                    closeAllLeadStatusMenus();
                    return;
                }

                var leadId = $picker.find('.lead-status-badge').data('id');
                var newStatusId = String($option.data('value') || '');
                var currentStatusId = String($picker.find('.lead-status-badge').data('status') || '');

                if (newStatusId === currentStatusId) {
                    closeAllLeadStatusMenus();
                    return;
                }

                saveLeadStatusInline(leadId, newStatusId, $picker);
            });

            $(document).on('click.leadStatusMenu', function() {
                closeAllLeadStatusMenus();
            });

            $(document).on('keydown.leadStatusMenu', function(event) {
                if (event.key === 'Escape') {
                    closeAllLeadStatusMenus();
                }
            });

            $(window).on('resize scroll.leadStatusMenu', function() {
                if (!$leadStatusOpenMenu || !$leadStatusOpenMenu.length) {
                    return;
                }

                var $picker = $leadStatusOpenMenu.data('owner-picker');
                if ($picker && $picker.length) {
                    positionLeadStatusMenu($picker, $leadStatusOpenMenu);
                }
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
                        dtApi.reload({ keepPage: true });
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
                    $createUserForm.removeData('school-lead-prefill');
                    resetCreateUserFormErrors();
                    if (typeof window.resetStudentParentForm === 'function') {
                        window.resetStudentParentForm('create');
                    }
                    if (typeof window.resetCreateUserHealthFields === 'function') {
                        window.resetCreateUserHealthFields();
                    }
                    if (typeof window.syncCreateUserHealthFields === 'function') {
                        window.syncCreateUserHealthFields($('#create_role_id').val());
                    }
                }

                function prefillCreateUserFromLead(rowData) {
                    resetCreateUserFormFields();

                    var studentRoleId = $('.js-student-parent-fields[data-parent-prefix="create"]').data('student-role-id');
                    if (studentRoleId) {
                        $('#create_role_id').val(String(studentRoleId)).trigger('change');
                    }

                    $('#create-name').val(rowData.child_firstname || '');
                    $('#create-lastname').val(rowData.child_lastname || '');
                    $('#create-birthday').val(rowData.child_birthday_iso || '');

                    if (rowData.team_id) {
                        $('#create-team').val(String(rowData.team_id));
                    } else {
                        $('#create-team').val('');
                    }

                    $('#create-email').val(rowData.parent_email || '');
                    $('#create-school-lead-id').val(rowData.id);

                    var $phone = $('#create-phone');
                    if ($phone.length && !$phone.prop('disabled')) {
                        var phoneValue = rowData.parent_phone || rowData.phone || '';
                        window.PhoneInputMask?.setValue($phone, phoneValue);
                    }

                    $createUserForm.data('school-lead-prefill', {
                        parent_lastname: rowData.parent_lastname || '',
                        parent_firstname: rowData.parent_firstname || '',
                        parent_middlename: rowData.parent_middlename || '',
                    });

                    if (typeof window.setCreateUserHealthFieldsFromLead === 'function') {
                        window.setCreateUserHealthFieldsFromLead({
                            is_individual_traits: rowData.is_individual_traits,
                            is_on_medical_register: rowData.is_on_medical_register,
                            is_with_disability: rowData.is_with_disability,
                        });
                    }
                    if (typeof window.syncCreateUserHealthFields === 'function') {
                        window.syncCreateUserHealthFields($('#create_role_id').val());
                    }

                    $createUserForm.data('success-handler', 'school-leads-table');
                }

                $('#leads-table').on('click', '.create-user-from-lead', function() {
                    var rowData = table.row($(this).closest('tr')).data();
                    if (!rowData || rowData.user_id) {
                        return;
                    }

                    prefillCreateUserFromLead(rowData);

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
                    dtApi.reload({ keepPage: true });
                    showToast(response.message || 'Клиент создан.', 'success');
                };
            }

            function schoolLeadStatusUrl(template, id) {
                return String(template).replace('__ID__', String(id));
            }

            function escapeHtml(value) {
                return $('<div/>').text(value == null ? '' : value).html();
            }

            function normalizeSchoolLeadStatusHex(hex) {
                if (!hex || typeof hex !== 'string') {
                    return '#0d6efd';
                }
                var value = hex.trim();
                if (/^#[0-9A-Fa-f]{6}$/.test(value) || /^#[0-9A-Fa-f]{3}$/.test(value)) {
                    return value;
                }
                return '#0d6efd';
            }

            function setSchoolLeadStatusFormColor(hex) {
                var normalized = normalizeSchoolLeadStatusHex(hex);
                $('#schoolLeadStatusFormColor').val(normalized);
                $('#schoolLeadStatusFormColorPicker').val(normalized);
                $('#schoolLeadStatusFormColorHex').text(normalized);
            }

            $('#schoolLeadStatusFormColorSwatches .sls-color-swatch').on('click', function () {
                setSchoolLeadStatusFormColor($(this).data('hex'));
            });
            $('#schoolLeadStatusFormColorPicker').on('input', function () {
                setSchoolLeadStatusFormColor(this.value);
            });

            function clearSchoolLeadStatusFormErrors() {
                $('#schoolLeadStatusForm .is-invalid').removeClass('is-invalid');
                $('#schoolLeadStatusForm [data-error-for]').text('');
            }

            function showSchoolLeadStatusFormErrors(errors) {
                clearSchoolLeadStatusFormErrors();
                if (!errors) {
                    return;
                }
                Object.keys(errors).forEach(function (field) {
                    var message = errors[field] && errors[field][0] ? errors[field][0] : '';
                    if (!message) {
                        return;
                    }
                    var $input = $('#schoolLeadStatusForm [name="' + field + '"]');
                    if ($input.length) {
                        $input.addClass('is-invalid');
                    }
                    $('#schoolLeadStatusForm [data-error-for="' + field + '"]').text(message);
                });
            }

            function applySchoolLeadStatusesToUi(statuses) {
                schoolLeadStatuses = Array.isArray(statuses) ? statuses : [];
                leadStatusInlineSelectOptions = buildLeadStatusInlineSelectOptions();

                defaultStatusFilters = schoolLeadStatuses
                    .filter(function (status) { return !!status.is_default_in_filter; })
                    .map(function (status) { return String(status.id); });

                var $leadStatus = $('#leadStatus');
                var currentLeadStatus = $leadStatus.val();
                $leadStatus.find('option:not(:first)').remove();
                schoolLeadStatuses.forEach(function (status) {
                    $leadStatus.append(
                        $('<option/>', { value: String(status.id), text: status.name })
                    );
                });
                if (currentLeadStatus) {
                    $leadStatus.val(currentLeadStatus);
                }

                if ($statusFilter.length) {
                    var currentFilter = $statusFilter.val() || [];
                    $statusFilter.empty();
                    schoolLeadStatuses.forEach(function (status) {
                        $statusFilter.append(
                            $('<option/>', { value: String(status.id), text: status.name })
                        );
                    });
                    if (window.KidsCrmFilterMultiselectSelect2) {
                        KidsCrmFilterMultiselectSelect2.rebuild($statusFilter);
                        KidsCrmFilterMultiselectSelect2.setValues($statusFilter, currentFilter);
                    } else {
                        $statusFilter.val(currentFilter).trigger('change');
                    }
                }
            }

            function renderSchoolLeadStatusesTable(statuses) {
                var $tbody = $('#school-lead-statuses-table-body');
                $tbody.empty();

                statuses.forEach(function (status) {
                    var nameHtml = escapeHtml(status.name);
                    if (status.is_system) {
                        var systemStatusHint = 'Системный статус. Его нельзя изменять или удалять.';
                        nameHtml += ' <span class="kids-tooltip-hint d-inline-block ms-1"'
                            + ' tabindex="0"'
                            + ' data-kids-tooltip-hint'
                            + ' data-bs-toggle="tooltip"'
                            + ' data-bs-placement="top"'
                            + ' data-bs-custom-class="ulp-assignment-paid-tooltip"'
                            + ' title="' + escapeHtml(systemStatusHint) + '"'
                            + ' aria-label="' + escapeHtml(systemStatusHint) + '">'
                            + '<i class="fa fa-info-circle" aria-hidden="true"></i>'
                            + '</span>';
                    }

                    var colorHtml = status.color
                        ? '<span class="badge" style="' + escapeHtml(status.badge_style || ('background-color:' + status.color + ';')) + '">' + escapeHtml(status.name) + '</span>'
                        : '—';

                    var actionsHtml = '';
                    if (!status.is_system) {
                        actionsHtml =
                            '<button type="button" class="btn btn-sm btn-primary me-1 js-school-lead-status-edit"'
                            + ' data-id="' + escapeHtml(status.id) + '"'
                            + ' data-name="' + escapeHtml(status.name) + '"'
                            + ' data-color="' + escapeHtml(status.color || '#0d6efd') + '"'
                            + ' data-sort-order="' + escapeHtml(status.sort_order || 0) + '"'
                            + ' data-default-filter="' + (status.is_default_in_filter ? '1' : '0') + '"'
                            + ' title="Редактировать" aria-label="Редактировать">'
                            + '<i class="fa fa-edit" aria-hidden="true"></i></button>'
                            + '<button type="button" class="btn btn-sm btn-danger js-school-lead-status-delete"'
                            + ' data-id="' + escapeHtml(status.id) + '"'
                            + ' data-name="' + escapeHtml(status.name) + '"'
                            + ' title="Удалить" aria-label="Удалить">'
                            + '<i class="fa fa-trash" aria-hidden="true"></i></button>';
                    }

                    $tbody.append(
                        '<tr>'
                        + '<td>' + nameHtml + '</td>'
                        + '<td class="text-center">' + escapeHtml(status.sort_order || 0) + '</td>'
                        + '<td>' + colorHtml + '</td>'
                        + '<td class="text-center">' + (status.is_default_in_filter ? 'Да' : 'Нет') + '</td>'
                        + '<td>' + actionsHtml + '</td>'
                        + '</tr>'
                    );
                });

                if (window.KidsCrmTooltip) {
                    var statusesModalEl = document.getElementById('schoolLeadStatusesModal');
                    KidsCrmTooltip.dispose(statusesModalEl, { scopes: ['hint'] });
                    KidsCrmTooltip.init(statusesModalEl, { scopes: ['hint'] });
                }
            }

            function loadSchoolLeadStatusesTable() {
                return $.getJSON(schoolLeadStatusRoutes.index).then(function (response) {
                    var statuses = response.statuses || [];
                    renderSchoolLeadStatusesTable(statuses);
                    applySchoolLeadStatusesToUi(statuses);
                    return statuses;
                });
            }

            document.getElementById('schoolLeadStatusesModal').addEventListener('show.bs.modal', function () {
                loadSchoolLeadStatusesTable().catch(function () {
                    showToast('Не удалось загрузить статусы.', 'error');
                });
            });

            $('#schoolLeadStatusCreateBtn').on('click', function () {
                clearSchoolLeadStatusFormErrors();
                $('#schoolLeadStatusFormId').val('');
                $('#schoolLeadStatusFormName').val('');
                setSchoolLeadStatusFormColor('#0d6efd');
                $('#schoolLeadStatusFormSortOrder').val('');
                $('#schoolLeadStatusFormDefaultFilter').prop('checked', false);
                $('#schoolLeadStatusFormModalLabel').text('Создать статус');
                schoolLeadStatusFormModal.show();
            });

            $('#school-lead-statuses-table-body').on('click', '.js-school-lead-status-edit', function () {
                var $btn = $(this);
                clearSchoolLeadStatusFormErrors();
                $('#schoolLeadStatusFormId').val($btn.data('id'));
                $('#schoolLeadStatusFormName').val($btn.data('name') || '');
                setSchoolLeadStatusFormColor($btn.data('color') || '#0d6efd');
                $('#schoolLeadStatusFormSortOrder').val($btn.data('sort-order') || '');
                $('#schoolLeadStatusFormDefaultFilter').prop('checked', String($btn.data('default-filter')) === '1');
                $('#schoolLeadStatusFormModalLabel').text('Редактировать статус');
                schoolLeadStatusFormModal.show();
            });

            $('#school-lead-statuses-table-body').on('click', '.js-school-lead-status-delete', function () {
                var statusId = $(this).data('id');
                if (!statusId) {
                    return;
                }

                var statusName = String($(this).data('name') || '').trim();
                var messageText = statusName !== ''
                    ? 'Вы уверены, что хотите удалить статус «' + statusName + '»?'
                    : 'Вы уверены, что хотите удалить этот статус?';

                showConfirmDeleteModal('Удаление статуса', messageText, function () {
                    $.ajax({
                        url: schoolLeadStatusUrl(schoolLeadStatusRoutes.destroy, statusId),
                        type: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        success: function () {
                            showToast('Статус удалён.', 'success');
                            loadSchoolLeadStatusesTable().then(function () {
                                appliedFilters = readFiltersFromForm();
                                dtApi.reload({ keepPage: true });
                            });
                        },
                        error: function (xhr) {
                            var message = (xhr.responseJSON && xhr.responseJSON.message)
                                || 'Не удалось удалить статус.';
                            showToast(message, 'error');
                        }
                    });
                });
            });

            $('#schoolLeadStatusForm').on('submit', function (e) {
                e.preventDefault();
                clearSchoolLeadStatusFormErrors();

                var statusId = $('#schoolLeadStatusFormId').val();
                var payload = {
                    name: $('#schoolLeadStatusFormName').val(),
                    color: $('#schoolLeadStatusFormColor').val(),
                    sort_order: $('#schoolLeadStatusFormSortOrder').val(),
                    is_default_in_filter: $('#schoolLeadStatusFormDefaultFilter').is(':checked') ? 1 : 0,
                };

                var requestOptions = {
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: payload,
                };

                if (statusId) {
                    requestOptions.url = schoolLeadStatusUrl(schoolLeadStatusRoutes.update, statusId);
                    requestOptions.type = 'PUT';
                } else {
                    requestOptions.url = schoolLeadStatusRoutes.store;
                    requestOptions.type = 'POST';
                }

                $.ajax(Object.assign(requestOptions, {
                    success: function () {
                        schoolLeadStatusFormModal.hide();
                        showToast('Статус сохранён.', 'success');
                        loadSchoolLeadStatusesTable().then(function () {
                            appliedFilters = readFiltersFromForm();
                            dtApi.reload({ keepPage: true });
                        });
                    },
                    error: function (xhr) {
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            showSchoolLeadStatusFormErrors(xhr.responseJSON.errors);
                        }
                        var message = (xhr.responseJSON && xhr.responseJSON.message)
                            || 'Не удалось сохранить статус.';
                        showToast(message, 'error');
                    }
                }));
            });
        });
    </script>
@endpush

