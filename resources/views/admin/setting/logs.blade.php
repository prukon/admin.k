@php
    $logEventLabels = $logEventLabels ?? [];
    $logLevelLabels = $logLevelLabels ?? [];
    $isLogsSuperadmin = $isLogsSuperadmin ?? false;
    $logPartners = $logPartners ?? collect();
    $logsFilterKeys = ['created_from', 'created_to', 'filter_action', 'filter_level', 'filter_author', 'filter_target_label', 'filter_partner_id'];
    $logsHasActiveFilters = false;
    foreach ($logsFilterKeys as $k) {
        $v = request($k);
        if ($k === 'filter_partner_id') {
            if ($v !== null && $v !== '' && $v !== 'all') {
                $logsHasActiveFilters = true;
                break;
            }
            continue;
        }
        if ($v !== null && $v !== '') {
            $logsHasActiveFilters = true;
            break;
        }
    }
    $logsHideSuperadmin = filter_var(request('hide_superadmin', '1'), FILTER_VALIDATE_BOOLEAN);
    $logsHideAuthorizations = filter_var(request('hide_authorizations', '0'), FILTER_VALIDATE_BOOLEAN);
    $logsHideIntegrations = filter_var(request('hide_integrations', '0'), FILTER_VALIDATE_BOOLEAN);
    if (!$logsHasActiveFilters) {
        if (request()->has('hide_superadmin') && !$logsHideSuperadmin) {
            $logsHasActiveFilters = true;
        } elseif ($logsHideAuthorizations || $logsHideIntegrations) {
            $logsHasActiveFilters = true;
        }
    }
    $logsFilterPartner = request('filter_partner_id', 'all');
@endphp

@vite(['resources/css/admin-list-toolbar.css'])

<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
    <div class="card-body px-3 py-3">
        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Логи</h1>
            <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#settingsLogsFiltersCollapse"
                        aria-expanded="{{ $logsHasActiveFilters ? 'true' : 'false' }}"
                        aria-controls="settingsLogsFiltersCollapse"
                        id="settingsLogsFiltersToggle"
                        title="Фильтры таблицы">
                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                        <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                    </span>
                    <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                    <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $logsHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="settingsLogsFiltersCollapse">
    <form id="settings-logs-filters" class="border rounded p-2 p-md-3 bg-light">
        <div class="row g-2 align-items-end">
            @if($isLogsSuperadmin)
                <div class="col-12 col-md-3">
                    <label class="form-label" for="settings-logs-filter-partner">Партнёр</label>
                    <select class="form-select" id="settings-logs-filter-partner" name="filter_partner_id">
                        <option value="all" @selected((string) $logsFilterPartner === 'all' || $logsFilterPartner === '')>Все партнёры</option>
                        @foreach($logPartners as $p)
                            <option value="{{ $p->id }}" @selected((string) $logsFilterPartner === (string) $p->id)>{{ $p->title }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-2">
                <label class="form-label" for="settings-logs-filter-created-from">Дата: с</label>
                <input class="form-control" id="settings-logs-filter-created-from" type="date" name="created_from"
                       value="{{ request('created_from') }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="settings-logs-filter-created-to">Дата: по</label>
                <input class="form-control" id="settings-logs-filter-created-to" type="date" name="created_to"
                       value="{{ request('created_to') }}">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="settings-logs-filter-action">Действие</label>
                <select class="form-select" id="settings-logs-filter-action" name="filter_action">
                    <option value="">Все действия</option>
                    <option value="unknown" @selected((string) request('filter_action') === 'unknown')>Неизвестный тип</option>
                    @foreach($logEventLabels as $event => $label)
                        <option value="{{ $event }}" @selected((string) request('filter_action') === (string) $event)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="settings-logs-filter-level">Уровень</label>
                <select class="form-select" id="settings-logs-filter-level" name="filter_level">
                    <option value="">Все уровни</option>
                    @foreach($logLevelLabels as $level => $label)
                        <option value="{{ $level }}" @selected((string) request('filter_level') === (string) $level)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="settings-logs-filter-author">Автор</label>
                <input class="form-control" id="settings-logs-filter-author" type="text" name="filter_author"
                       value="{{ request('filter_author') }}" placeholder="ФИО автора">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="settings-logs-filter-target">Что меняли</label>
                <input class="form-control" id="settings-logs-filter-target" type="text" name="filter_target_label"
                       value="{{ request('filter_target_label') }}" placeholder="Название объекта">
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button class="btn btn-primary payments-report-filters-submit" type="button" id="settingsLogsFiltersApply">Применить</button>
                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="settingsLogsFiltersReset">Сброс</button>
            </div>
            <div class="col-12 col-md-auto">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="settings-logs-filter-hide-superadmin"
                           name="hide_superadmin" value="1" @checked($logsHideSuperadmin)>
                    <label class="form-check-label" for="settings-logs-filter-hide-superadmin">Скрыть суперадмина</label>
                </div>
            </div>
            <div class="col-12 col-md-auto">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="settings-logs-filter-hide-authorizations"
                           name="hide_authorizations" value="1" @checked($logsHideAuthorizations)>
                    <label class="form-check-label" for="settings-logs-filter-hide-authorizations">Скрыть авторизации</label>
                </div>
            </div>
            <div class="col-12 col-md-auto">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="settings-logs-filter-hide-integrations"
                           name="hide_integrations" value="1" @checked($logsHideIntegrations)>
                    <label class="form-check-label" for="settings-logs-filter-hide-integrations">Скрыть интеграции</label>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="table-responsive">
    <table id="settingsLogsTable" class="display table table-striped w-100">
        <thead>
        <tr>
            <th>ID</th>
            <th>Дата создания</th>
            @if($isLogsSuperadmin)
                <th>Партнёр</th>
            @endif
            <th>Действие</th>
            <th>Автор</th>
            <th>Что меняли</th>
            <th>Описание</th>
        </tr>
        </thead>
    </table>
</div>

@push('scripts')
    <script type="text/javascript">
        $(function () {
            var isLogsSuperadmin = @json($isLogsSuperadmin);
            var $filtersForm = $('#settings-logs-filters');

            function settingsLogsFilterParams() {
                var params = {
                    created_from: $('#settings-logs-filter-created-from').val() || '',
                    created_to: $('#settings-logs-filter-created-to').val() || '',
                    filter_action: $('#settings-logs-filter-action').val() || '',
                    filter_level: $('#settings-logs-filter-level').val() || '',
                    filter_author: $('#settings-logs-filter-author').val() || '',
                    filter_target_label: $('#settings-logs-filter-target').val() || '',
                    hide_superadmin: $('#settings-logs-filter-hide-superadmin').is(':checked') ? '1' : '0',
                    hide_authorizations: $('#settings-logs-filter-hide-authorizations').is(':checked') ? '1' : '0',
                    hide_integrations: $('#settings-logs-filter-hide-integrations').is(':checked') ? '1' : '0'
                };
                if (isLogsSuperadmin) {
                    params.filter_partner_id = $('#settings-logs-filter-partner').val() || 'all';
                }
                return params;
            }

            function settingsLogsHasActiveFilters() {
                var p = settingsLogsFilterParams();
                if (p.created_from !== '' || p.created_to !== '' || p.filter_action !== ''
                    || p.filter_level !== '' || p.filter_author !== '' || p.filter_target_label !== '') {
                    return true;
                }
                if (p.hide_authorizations === '1' || p.hide_integrations === '1' || p.hide_superadmin === '0') {
                    return true;
                }
                if (isLogsSuperadmin && p.filter_partner_id && p.filter_partner_id !== 'all') {
                    return true;
                }
                return false;
            }

            function syncSettingsLogsFiltersCollapseState() {
                var hasActive = settingsLogsHasActiveFilters();
                var collapseEl = document.getElementById('settingsLogsFiltersCollapse');
                var $toggle = $('#settingsLogsFiltersToggle');

                if (collapseEl && hasActive && !collapseEl.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl, {toggle: false}).show();
                }

                if ($toggle.length && collapseEl) {
                    $toggle.attr('aria-expanded', collapseEl.classList.contains('show') ? 'true' : 'false');
                }
            }

            var columns = [
                {data: 'id', name: 'id'},
                {data: 'created_at', name: 'created_at'}
            ];
            if (isLogsSuperadmin) {
                columns.push({data: 'partner_title', name: 'partner_title'});
            }
            columns = columns.concat([
                {data: 'action', name: 'action'},
                {data: 'author', name: 'author'},
                {data: 'target_label', name: 'target_label'},
                {
                    data: 'description',
                    name: 'description',
                    render: function (data) {
                        if (!data) {
                            return '';
                        }
                        return String(data).replace(/\n/g, '<br>');
                    }
                }
            ]);

            var table = $('#settingsLogsTable').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 10,
                lengthMenu: [10, 20, 50, 100],
                ajax: {
                    url: @json(route('settings.logs.data')),
                    type: 'GET',
                    data: function (d) {
                        var params = settingsLogsFilterParams();
                        d.created_from = params.created_from;
                        d.created_to = params.created_to;
                        d.filter_action = params.filter_action;
                        d.filter_level = params.filter_level;
                        d.filter_author = params.filter_author;
                        d.filter_target_label = params.filter_target_label;
                        d.hide_superadmin = params.hide_superadmin;
                        d.hide_authorizations = params.hide_authorizations;
                        d.hide_integrations = params.hide_integrations;
                        if (isLogsSuperadmin) {
                            d.filter_partner_id = params.filter_partner_id;
                        }
                    }
                },
                columns: columns,
                order: [[1, 'desc']],
                scrollX: true,
                language: @include('partials.datatables.ru')
            });

            $('#settingsLogsFiltersApply').on('click', function () {
                table.ajax.reload();
                syncSettingsLogsFiltersCollapseState();
            });

            $('#settingsLogsFiltersReset').on('click', function () {
                $filtersForm[0].reset();
                if (isLogsSuperadmin) {
                    $('#settings-logs-filter-partner').val('all');
                }
                $('#settings-logs-filter-hide-superadmin').prop('checked', true);
                $('#settings-logs-filter-hide-authorizations').prop('checked', false);
                $('#settings-logs-filter-hide-integrations').prop('checked', false);
                table.ajax.reload();
                syncSettingsLogsFiltersCollapseState();
            });

            $filtersForm.on('keydown', 'input, select', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#settingsLogsFiltersApply').trigger('click');
                }
            });
        });
    </script>
@endpush
