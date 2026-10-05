@php
    $filters = $filters ?? [];
    $paymentsFilterUser = $paymentsFilterUser ?? null;
    $paymentsFilterTeam = $paymentsFilterTeam ?? null;
    $paymentsFilterTrainer = $paymentsFilterTrainer ?? null;
    $canViewTrainers = $canViewTrainers ?? (auth()->user() && auth()->user()->can('trainers.view'));
    $canViewLocations = $canViewLocations ?? (auth()->user() && auth()->user()->can('locations.view'));
    $activeLocations = $activeLocations ?? collect();
    $ltvAdminsPeriod = in_array(($ltvAdminsPeriod ?? 'current'), ['current', 'previous', 'all'], true)
        ? ($ltvAdminsPeriod ?? 'current')
        : 'current';
    $ltvAdminsPeriodLabels = $ltvAdminsPeriodLabels ?? [
        'current' => '',
        'previous' => '',
        'all' => 'Все время',
    ];
    $ltvAdminsMode = in_array(($ltvAdminsMode ?? 'operation'), ['operation', 'subscription'], true)
        ? ($ltvAdminsMode ?? 'operation')
        : 'operation';
    $payFilterKeys = ['filter_user_id', 'filter_team_id', 'filter_trainer_profile_id', 'filter_location_id', 'filter_admin_user_id', 'user_name', 'team_title', 'payment_month', 'operation_date_from', 'operation_date_to', 'payment_provider'];
    $payFilterLocation = $filters['filter_location_id'] ?? '';
    $payFilterUserStatus = array_key_exists('status', $filters) ? (string) ($filters['status'] ?? '') : 'active';
    $payHasActiveFilters = false;
    foreach ($payFilterKeys as $k) {
        $v = $filters[$k] ?? null;
        if (is_array($v)) {
            $items = array_filter($v, static fn ($item) => trim((string) $item) !== '');
            if ($items !== []) {
                $payHasActiveFilters = true;
                break;
            }
            continue;
        }
        if ($v !== null && $v !== '') {
            $payHasActiveFilters = true;
            break;
        }
    }
    if (array_key_exists('status', $filters) && ($filters['status'] ?? '') !== 'active') {
        $payHasActiveFilters = true;
    }
@endphp

@push('styles')
    @vite(['resources/css/admin-list-toolbar.css', 'resources/css/admin-reports-tables.css', 'resources/js/admin-reports-tables-sticky.js'])
    <link rel="stylesheet" href="{{ asset('plugins/datatables-fixedheader/css/fixedHeader.bootstrap4.min.css') }}">
@endpush

<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
    <div class="card-body px-3 py-3">
        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
            <div class="d-flex flex-column gap-2 min-w-0 flex-shrink-1">
                <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate">По админам</h1>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="btn-group btn-group-sm js-ltv-admins-period" role="group" aria-label="Период" id="ltv-admins-period-switch">
                        <button type="button" class="btn btn-outline-secondary js-ltv-admins-period-btn {{ $ltvAdminsPeriod === 'current' ? 'active' : '' }}"
                                data-period="current" id="ltv-admins-period-btn-current">{{ $ltvAdminsPeriodLabels['current'] }}</button>
                        <button type="button" class="btn btn-outline-secondary js-ltv-admins-period-btn {{ $ltvAdminsPeriod === 'previous' ? 'active' : '' }}"
                                data-period="previous" id="ltv-admins-period-btn-previous">{{ $ltvAdminsPeriodLabels['previous'] }}</button>
                        <button type="button" class="btn btn-outline-secondary js-ltv-admins-period-btn {{ $ltvAdminsPeriod === 'all' ? 'active' : '' }}"
                                data-period="all" id="ltv-admins-period-btn-all">{{ $ltvAdminsPeriodLabels['all'] }}</button>
                    </div>
                    <span class="small text-muted mb-0">Группировка:</span>
                    <div class="btn-group btn-group-sm js-ltv-admins-group-mode" role="group" aria-label="Режим группировки" id="ltv-admins-group-mode-switch">
                        <button type="button" class="btn btn-outline-secondary js-ltv-admins-group-mode-btn {{ $ltvAdminsMode === 'subscription' ? 'active' : '' }}"
                                data-mode="subscription" id="ltv-admins-group-mode-btn-subscription">По месяцу абонемента</button>
                        <button type="button" class="btn btn-outline-secondary js-ltv-admins-group-mode-btn {{ $ltvAdminsMode === 'operation' ? 'active' : '' }}"
                                data-mode="operation" id="ltv-admins-group-mode-btn-operation">По дате платежа</button>
                    </div>
                    <div class="invalid-feedback" data-error-for="period" @error('period') style="display:block" @enderror>@error('period'){{ $message }}@enderror</div>
                    <div class="invalid-feedback" data-error-for="mode" @error('mode') style="display:block" @enderror>@error('mode'){{ $message }}@enderror</div>
                </div>
            </div>
            <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                <div class="payments-report-total-inline payments-report-total-stat text-end" id="ltvAdminsReportTotalStat">
                    <div class="payments-report-total-label text-muted small mb-0">Общая сумма</div>
                    <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                        <span class="payments-report-total-value-inner">
                            <span class="payments-report-total-amount">{{ $totalPaidPrice }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">₽</span>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                    <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#ltvAdminsReportFiltersCollapse"
                            aria-expanded="{{ $payHasActiveFilters ? 'true' : 'false' }}"
                            aria-controls="ltvAdminsReportFiltersCollapse"
                            id="ltvAdminsReportFiltersToggle">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="dropdown payments-report-toolbar-dropdown">
                        <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                id="columnsDropdownLtvAdminsReport"
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
                             aria-labelledby="columnsDropdownLtvAdminsReport">
                            <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>
                            <div class="form-check">
                                <input class="form-check-input ltv-admins-column-toggle" type="checkbox" id="ltvAdminsColAdmin" data-column-key="admin_name" checked>
                                <label class="form-check-label" for="ltvAdminsColAdmin">Админ</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-admins-column-toggle" type="checkbox" id="ltvAdminsColLocations" data-column-key="location_names" checked>
                                <label class="form-check-label" for="ltvAdminsColLocations">Объекты</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-admins-column-toggle" type="checkbox" id="ltvAdminsColName" data-column-key="user_names" checked>
                                <label class="form-check-label" for="ltvAdminsColName">Ученики</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-admins-column-toggle" type="checkbox" id="ltvAdminsColAvgAttendance" data-column-key="avg_attendance" checked>
                                <label class="form-check-label" for="ltvAdminsColAvgAttendance">Ср. посещаемость</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-admins-column-toggle" type="checkbox" id="ltvAdminsColSum" data-column-key="total_price" checked>
                                <label class="form-check-label" for="ltvAdminsColSum">Сумма</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-admins-column-toggle" type="checkbox" id="ltvAdminsColCount" data-column-key="payment_count" checked>
                                <label class="form-check-label" for="ltvAdminsColCount">Кол-во платежей</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-admins-column-toggle" type="checkbox" id="ltvAdminsColAvgCheck" data-column-key="avg_check" checked>
                                <label class="form-check-label" for="ltvAdminsColAvgCheck">Ср. чек</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-admins-column-toggle" type="checkbox" id="ltvAdminsColFirst" data-column-key="first_payment_date" checked>
                                <label class="form-check-label" for="ltvAdminsColFirst">Перв. платёж</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-admins-column-toggle" type="checkbox" id="ltvAdminsColLast" data-column-key="last_payment_date" checked>
                                <label class="form-check-label" for="ltvAdminsColLast">Посл. платёж</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $payHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="ltvAdminsReportFiltersCollapse">
    <form id="ltv-admins-report-filters" method="GET" action="{{ route('reports.ltv.admins') }}" class="border rounded p-2 p-md-3 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-ltv-admins-filter-user">Ученик</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-ltv-admins-filter-user"
                        name="filter_user_id"
                        data-placeholder="Все ученики"
                        data-search-url="{{ route('reports.ltv.admins.users.search') }}">
                    <option value=""></option>
                    @if($paymentsFilterUser)
                        <option value="{{ $paymentsFilterUser['id'] }}" selected>{{ $paymentsFilterUser['text'] }}</option>
                    @endif
                </select>
            </div>
            @include('admin.report.partials.entity-multiselects', [
                'teamFieldId' => 'pay-ltv-admins-filter-team',
                'trainerFieldId' => 'pay-ltv-admins-filter-trainer',
                'locationFieldId' => 'pay-ltv-admins-filter-location',
                'adminFieldId' => 'pay-ltv-admins-filter-admin',
            ])
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-admins-filter-payment-month">Оплаченный месяц</label>
                <input class="form-control" id="pay-ltv-admins-filter-payment-month" type="month" name="payment_month"
                       value="{{ $filters['payment_month'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-admins-filter-op-from">Дата платежа: с</label>
                <input class="form-control" id="pay-ltv-admins-filter-op-from" type="date" name="operation_date_from"
                       value="{{ $filters['operation_date_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-admins-filter-op-to">Дата платежа: по</label>
                <input class="form-control" id="pay-ltv-admins-filter-op-to" type="date" name="operation_date_to"
                       value="{{ $filters['operation_date_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-admins-filter-provider">Провайдер</label>
                @php($fpProvider = $filters['payment_provider'] ?? '')
                <select class="form-select" id="pay-ltv-admins-filter-provider" name="payment_provider">
                    <option value="">—</option>
                    <option value="tbank" {{ $fpProvider === 'tbank' ? 'selected' : '' }}>T-Bank</option>
                    <option value="robokassa" {{ $fpProvider === 'robokassa' ? 'selected' : '' }}>Robokassa</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-admins-filter-user-status">Активность ученика</label>
                <select class="form-select" id="pay-ltv-admins-filter-user-status" name="status">
                    <option value="">Все ученики</option>
                    <option value="active" {{ $payFilterUserStatus === 'active' ? 'selected' : '' }}>Только активные</option>
                    <option value="inactive" {{ $payFilterUserStatus === 'inactive' ? 'selected' : '' }}>Только неактивные</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="ltvAdminsReportFiltersResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

<table class="table table-bordered dt-columns-managed w-100" id="ltv-admins-table">
    <thead>
        <tr>
            <th style="width: 60px;"></th>
            <th>Админ</th>
            <th>Объекты</th>
            <th>Ученики</th>
            <th>Ср. посещаемость</th>
            <th>Сумма</th>
            <th>Кол-во платежей</th>
            <th>Ср. чек</th>
            <th>Перв. платёж</th>
            <th>Посл. платёж</th>
        </tr>
    </thead>
</table>

@section('scripts')
    <script src="{{ asset('plugins/datatables-fixedheader/js/dataTables.fixedHeader.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            @include('admin.report.partials.persist-report-filters-fn')
            var ltvAdminsFiltersSaveUrl = @json(route('reports.ltv.admins.filters.save'));

            var canViewLocations = @json($canViewLocations);
            var defaultFilterUserStatus = 'active';
            var $ltvFiltersForm = $('#ltv-admins-report-filters');
            var $ltvFilterUser = $('#pay-ltv-admins-filter-user');
            var $ltvFilterTeam = $('#pay-ltv-admins-filter-team');
            var $ltvFilterTrainer = $('#pay-ltv-admins-filter-trainer');
            var $ltvAdminsReportTotalAmount = $('.payments-report-total-amount');
            var $ltvAdminsReportTotalStat = $('#ltvAdminsReportTotalStat');
            var $ltvAdminsReportTotalValueInner = $('.payments-report-total-value-inner');
            var currentPeriod = @json($ltvAdminsPeriod);
            var currentMode = @json($ltvAdminsMode);

            function ltvAdminsReportParseTotalToInt(str) {
                return parseInt(String(str || '').replace(/\s/g, ''), 10) || 0;
            }

            function ltvAdminsReportFormatTotalSpaces(n) {
                var v = Math.round(Number(n));
                if (isNaN(v)) {
                    return '0';
                }
                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function ltvAdminsReportAnimateTotalChange(prevText, nextText, nextRaw) {
                var $amount = $ltvAdminsReportTotalAmount;
                if (!$amount.length) {
                    return;
                }

                var nextVal = typeof nextRaw === 'number' && !isNaN(nextRaw)
                    ? Math.round(nextRaw)
                    : ltvAdminsReportParseTotalToInt(nextText);
                var prevVal = ltvAdminsReportParseTotalToInt(prevText);

                var runFlashAndPop = function () {
                    if ($ltvAdminsReportTotalStat.length) {
                        $ltvAdminsReportTotalStat.removeClass('payments-report-total-stat--flash');
                        void $ltvAdminsReportTotalStat[0].offsetWidth;
                        $ltvAdminsReportTotalStat.addClass('payments-report-total-stat--flash');
                    }
                    if ($ltvAdminsReportTotalValueInner.length) {
                        $ltvAdminsReportTotalValueInner.removeClass('payments-report-total-value-inner--pop');
                        void $ltvAdminsReportTotalValueInner[0].offsetWidth;
                        $ltvAdminsReportTotalValueInner.addClass('payments-report-total-value-inner--pop');
                    }
                };

                var prefersReduced = window.matchMedia
                    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                if (prefersReduced || prevText === nextText) {
                    $amount.text(nextText);
                    if (!prefersReduced && prevText !== nextText) {
                        runFlashAndPop();
                    }
                    return;
                }

                if (prevVal === nextVal) {
                    $amount.text(nextText);
                    runFlashAndPop();
                    return;
                }

                var duration = 480;
                var start = null;

                function easeInOutQuad(t) {
                    return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                }

                function step(ts) {
                    if (start === null) {
                        start = ts;
                    }
                    var elapsed = ts - start;
                    var t = Math.min(1, elapsed / duration);
                    var eased = easeInOutQuad(t);
                    var cur = Math.round(prevVal + (nextVal - prevVal) * eased);
                    $amount.text(ltvAdminsReportFormatTotalSpaces(cur));
                    if (t < 1) {
                        window.requestAnimationFrame(step);
                    } else {
                        $amount.text(nextText);
                    }
                }

                runFlashAndPop();
                window.requestAnimationFrame(step);
            }

            function reportMultiValues($el) {
                if (!$el || !$el.length) {
                    return [];
                }
                var val = $el.val();
                if (val === null || val === undefined || val === '') {
                    return [];
                }
                return Array.isArray(val) ? val : [String(val)];
            }

            function ltvAdminsReportFilterParams() {
                var uid = $ltvFiltersForm.find('[name="filter_user_id"]').val() || '';
                var teamIds = reportMultiValues($ltvFilterTeam);
                var trainerIds = $ltvFilterTrainer.length ? reportMultiValues($ltvFilterTrainer) : [];
                return {
                    filter_user_id: uid,
                    filter_team_id: teamIds,
                    filter_trainer_profile_id: trainerIds,
                    filter_location_id: canViewLocations
                        ? reportMultiValues($('#pay-ltv-admins-filter-location'))
                        : [],
                    filter_admin_user_id: canViewLocations
                        ? reportMultiValues($('#pay-ltv-admins-filter-admin'))
                        : [],
                    status: $ltvFiltersForm.find('[name="status"]').val() || '',
                    user_name: '',
                    team_title: '',
                    payment_month: $ltvFiltersForm.find('[name="payment_month"]').val() || '',
                    operation_date_from: $ltvFiltersForm.find('[name="operation_date_from"]').val() || '',
                    operation_date_to: $ltvFiltersForm.find('[name="operation_date_to"]').val() || '',
                    payment_provider: $ltvFiltersForm.find('[name="payment_provider"]').val() || '',
                    period: currentPeriod,
                    mode: currentMode
                };
            }

            function ltvAdminsSyncPeriodInUrl() {
                if (!window.history || !window.history.replaceState) {
                    return;
                }
                var url = new URL(window.location.href);
                if (currentPeriod === 'current') {
                    url.searchParams.delete('period');
                } else {
                    url.searchParams.set('period', currentPeriod);
                }
                window.history.replaceState({}, '', url.toString());
            }

            function refreshLtvAdminsReportTotal() {
                var prevText = $ltvAdminsReportTotalAmount.length ? $ltvAdminsReportTotalAmount.text() : '';
                if ($ltvAdminsReportTotalStat.length) {
                    $ltvAdminsReportTotalStat.addClass('payments-report-total-stat--loading');
                }
                $.get(@json(route('reports.ltv.admins.total')), ltvAdminsReportFilterParams())
                    .done(function (res) {
                        if ($ltvAdminsReportTotalStat.length) {
                            $ltvAdminsReportTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                        if (!res || res.total_formatted === undefined || !$ltvAdminsReportTotalAmount.length) {
                            return;
                        }
                        var nextText = res.total_formatted;
                        ltvAdminsReportAnimateTotalChange(prevText, nextText, res.total_raw);
                    })
                    .fail(function () {
                        if ($ltvAdminsReportTotalStat.length) {
                            $ltvAdminsReportTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                    });
            }

            function initPaymentsReportFilterSelect2($el) {
                var searchUrl = $el.data('search-url');
                if (!$el.length || !searchUrl) {
                    return;
                }
                $el.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $el.data('placeholder') || '',
                    language: @include('partials.select2.ru'),
                    allowClear: true,
                    ajax: {
                        url: searchUrl,
                        delay: 250,
                        data: function (params) {
                            return {q: params.term || ''};
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 0
                });
            }

            initPaymentsReportFilterSelect2($ltvFilterUser);

            function formatLtvDate(data) {
                if (!data) {
                    return '';
                }
                var d = new Date(data);
                if (isNaN(d.getTime())) {
                    return data;
                }
                var day = ('0' + d.getDate()).slice(-2);
                var month = ('0' + (d.getMonth() + 1)).slice(-2);
                var year = d.getFullYear();
                return day + '.' + month + '.' + year;
            }

            var ltvAdminsDetailTables = {};
            var ltvAdminsDetailMeta = {};

            function destroyLtvAdminsDetailTable(locationId) {
                var tableId = 'ltv-admin-payments-' + locationId;
                if (ltvAdminsDetailTables[locationId]) {
                    ltvAdminsDetailTables[locationId].destroy();
                    delete ltvAdminsDetailTables[locationId];
                }
                delete ltvAdminsDetailMeta[locationId];
                $('#' + tableId).remove();
            }

            function formatSubscriptionMonth(raw) {
                if (!raw) return '';

                var re = /^\d{4}-\d{2}-\d{2}$/;
                if (!re.test(raw)) {
                    return raw;
                }

                var parts = raw.split('-');
                var year = parts[0];
                var monthNum = parseInt(parts[1], 10);

                var monthNames = {
                    1: 'Январь', 2: 'Февраль', 3: 'Март', 4: 'Апрель',
                    5: 'Май', 6: 'Июнь', 7: 'Июль', 8: 'Август',
                    9: 'Сентябрь', 10: 'Октябрь', 11: 'Ноябрь', 12: 'Декабрь'
                };

                return (monthNames[monthNum] || parts[1]) + ' ' + year;
            }

            function formatOperationDateTime(raw) {
                if (!raw) {
                    return '';
                }
                var d = new Date(raw);
                if (isNaN(d.getTime())) {
                    return raw;
                }
                var day = ('0' + d.getDate()).slice(-2);
                var month = ('0' + (d.getMonth() + 1)).slice(-2);
                var year = d.getFullYear();
                var hours = ('0' + d.getHours()).slice(-2);
                var minutes = ('0' + d.getMinutes()).slice(-2);
                return day + '.' + month + '.' + year + ' / ' + hours + ':' + minutes;
            }

            function renderPaymentProviderBadge(provider) {
                if (provider === 'tbank') {
                    return '<span class="badge" style="background-color:#ffdd2d !important; color:black !important;">T-Bank</span>';
                }
                if (provider === 'robokassa') {
                    return '<span class="badge bg-secondary">Robokassa</span>';
                }
                return provider || '';
            }

            function updateLtvAdminsDetailSummary(locationId) {
                var meta = ltvAdminsDetailMeta[locationId] || {};
                var $summary = $('#ltv-admins-detail-summary-' + locationId);
                if (!$summary.length) {
                    return;
                }
                var count = meta.payments_count || 0;
                var sum = window.KidsCrmMoney.formatAmount(meta.sum_total || 0);
                $summary.html('Всего платежей: <b>' + count + '</b>, на сумму <b>' + sum + ' ₽</b>');
            }

            function initLtvAdminPaymentsDetailTable(locationId) {
                var tableId = 'ltv-admin-payments-' + locationId;

                return $('#' + tableId).DataTable({
                    processing: true,
                    serverSide: true,
                    autoWidth: false,
                    dom: 'rtip',
                    pageLength: 10,
                    lengthMenu: [10, 20, 50, 100],
                    ajax: {
                        url: '/admin/reports/ltv/admins/' + locationId + '/payments',
                        type: 'GET',
                        data: function (d) {
                            var extra = ltvAdminsReportFilterParams();
                            Object.keys(extra).forEach(function (key) {
                                d[key] = extra[key];
                            });
                        },
                        dataSrc: function (json) {
                            ltvAdminsDetailMeta[locationId] = {
                                payments_count: json.meta_payments_count || 0,
                                sum_total: json.meta_sum_total || 0
                            };
                            updateLtvAdminsDetailSummary(locationId);
                            return json.data || [];
                        }
                    },
                    columns: [
                        {
                            data: 'operation_date',
                            name: 'operation_date',
                            render: function (data, type) {
                                if (type !== 'display') {
                                    return data || '';
                                }
                                return formatOperationDateTime(data);
                            }
                        },
                        {
                            data: 'user_name',
                            name: 'user_name',
                            render: function (data, type, row) {
                                if (type !== 'display' || !window.KidsCrmUserCard) {
                                    return data || '';
                                }
                                return window.KidsCrmUserCard.renderName(data, row.user_id);
                            }
                        },
                        {
                            data: 'team_title',
                            name: 'team_title'
                        },
                        {
                            data: 'summ',
                            name: 'summ',
                            className: 'text-end',
                            render: function (data, type) {
                                if (type !== 'display') {
                                    return data;
                                }
                                return window.KidsCrmMoney.formatAmount(parseFloat(data || 0)) + ' ₽';
                            }
                        },
                        {
                            data: 'payment_month',
                            name: 'payment_month',
                            render: function (data, type) {
                                if (type !== 'display') {
                                    return data || '';
                                }
                                return formatSubscriptionMonth(data);
                            }
                        },
                        {
                            data: 'payment_provider',
                            name: 'payment_provider',
                            orderable: false,
                            searchable: false,
                            render: function (data, type) {
                                if (type !== 'display') {
                                    return data || '';
                                }
                                return renderPaymentProviderBadge(data);
                            }
                        }
                    ],
                    order: [[0, 'desc']],
                    language: @include('partials.datatables.ru')
                });
            }

            function buildLtvAdminsDetailContainerHtml(locationId, locationName) {
                var safeName = locationName || 'Без админа';

                return '' +
                    '<div class="p-3 details-container bg-light border-start border-3 border-secondary" id="ltv-admins-detail-wrap-' + locationId + '">' +
                    '  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">' +
                    '    <div class="fw-bold">Платежи админа: ' + safeName + '</div>' +
                    '    <div class="small text-muted" id="ltv-admins-detail-summary-' + locationId + '"></div>' +
                    '  </div>' +
                    '  <table class="table table-sm table-bordered mb-0 align-middle w-100" id="ltv-admin-payments-' + locationId + '">' +
                    '    <thead class="table-light small">' +
                    '      <tr>' +
                    '        <th>Дата и время платежа</th>' +
                    '        <th>ФИО</th>' +
                    '        <th>Группа</th>' +
                    '        <th class="text-end">Сумма</th>' +
                    '        <th>Месяц абонемента</th>' +
                    '        <th>Провайдер</th>' +
                    '      </tr>' +
                    '    </thead>' +
                    '  </table>' +
                    '</div>';
            }

            var ltvAdminsSumOrderIndex = 5;

            var dtApi = KidsCrmDataTable.create('#ltv-admins-table', {
                columnsSettings: {
                    persistPageLength: true,
                    defaults: {
                        admin_name: true,
                        location_names: true,
                        user_names: true,
                        avg_attendance: true,
                        total_price: true,
                        payment_count: true,
                        avg_check: true,
                        first_payment_date: true,
                        last_payment_date: true
                    },
                    urls: {
                        get: @json(route('reports.ltv.admins.columns-settings.get')),
                        save: @json(route('reports.ltv.admins.columns-settings.save'))
                    },
                    toggleSelector: '.ltv-admins-column-toggle',
                    csrfToken: '{{ csrf_token() }}'
                },
                dataTable: {
                    pageLength: @json((int) ($ltvAdminsPageLength ?? 10)),
                    ajax: {
                        url: @json(route('reports.ltv.admins.data')),
                        type: 'GET',
                        data: function (d) {
                            var extra = ltvAdminsReportFilterParams();
                            Object.keys(extra).forEach(function (key) {
                                d[key] = extra[key];
                            });
                        }
                    },
                    order: [[ltvAdminsSumOrderIndex, 'desc']],
                    language: @include('partials.datatables.ru'),
                    fixedHeader: ($.fn.dataTable && $.fn.dataTable.FixedHeader)
                        ? { header: true, footer: false }
                        : false,
                    drawCallback: function () {
                        if (window.KidsCrmReportTableSticky) {
                            window.KidsCrmReportTableSticky.bind('#ltv-admins-table');
                        }
                    }
                },
                columns: [
                    {
                        type: 'custom',
                        column: {
                            data: null,
                            className: 'details-control text-center',
                            orderable: false,
                            searchable: false,
                            defaultContent: '<button type="button" class="btn btn-sm btn-outline-secondary details-control-btn" aria-label="Развернуть"><i class="fa-solid fa-chevron-down"></i></button>'
                        }
                    },
                    { key: 'admin_name', type: 'text', data: 'admin_name', name: 'admin_name' },
                    {
                        key: 'location_names',
                        type: 'list',
                        data: 'location_names',
                        name: 'location_names',
                        itemsKey: 'location_names_items',
                        listOptions: { customClass: 'kids-hover-list-tooltip--two-col' }
                    },
                    {
                        key: 'user_names',
                        type: 'list',
                        data: 'user_names',
                        name: 'user_names',
                        itemsKey: 'user_names_items',
                        listOptions: { customClass: 'kids-hover-list-tooltip--two-col' },
                        render: function (data, type, row) {
                            if (type !== 'display' || !window.KidsCrmUserCard) {
                                return data || '';
                            }
                            return window.KidsCrmUserCard.renderLinkedList(
                                row.user_name_cards,
                                row.user_names_items,
                                data,
                                { customClass: 'kids-hover-list-tooltip--two-col' }
                            );
                        }
                    },
                    {
                        key: 'avg_attendance',
                        type: 'count',
                        data: 'avg_attendance',
                        name: 'avg_attendance', searchable: false,
                        defaultContent: '<span class="dt-cell-empty text-muted">—</span>',
                        render: function (data, type) {
                            if (data === null || data === undefined || data === '') {
                                if (type === 'sort' || type === 'filter') {
                                    return '';
                                }
                                return '<span class="dt-cell-empty text-muted">—</span>';
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            return String(data);
                        }
                    },
                    { key: 'total_price', type: 'money', data: 'total_price', name: 'total_price', searchable: false, suffix: ' ₽' },
                    { key: 'payment_count', type: 'count', data: 'payment_count', name: 'payment_count', searchable: false },
                    { key: 'avg_check', type: 'money', data: 'avg_check', name: 'avg_check', searchable: false, suffix: ' ₽' },
                    {
                        key: 'first_payment_date',
                        type: 'datetime',
                        data: 'first_payment_date',
                        name: 'first_payment_date',
                        searchable: false,
                        className: 'dt-col-text dt-col-text--wrap',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data || '';
                            }
                            return formatLtvDate(data);
                        }
                    },
                    {
                        key: 'last_payment_date',
                        type: 'datetime',
                        data: 'last_payment_date',
                        name: 'last_payment_date',
                        searchable: false,
                        className: 'dt-col-text dt-col-text--wrap',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data || '';
                            }
                            return formatLtvDate(data);
                        }
                    },
                    {
                        key: 'admin_user_id',
                        type: 'id',
                        data: 'admin_user_id',
                        name: 'admin_user_id',
                        visible: false,
                        searchable: false
                    }
                ]
            });

            var ltvTable = dtApi.table;

            $ltvFiltersForm.on('submit', function (e) {
                e.preventDefault();
                kidsCrmPersistReportFilters($ltvFiltersForm, ltvAdminsFiltersSaveUrl, ltvAdminsReportFilterParams(), function () {
                    refreshLtvAdminsReportTotal();
                    Object.keys(ltvAdminsDetailTables).forEach(function (locationId) {
                        destroyLtvAdminsDetailTable(locationId);
                    });
                    dtApi.reload();
                });
            });

            $('.js-ltv-admins-period-btn').on('click', function () {
                var period = $(this).data('period');
                if (period !== 'current' && period !== 'previous' && period !== 'all') {
                    return;
                }
                if (period === currentPeriod) {
                    return;
                }
                currentPeriod = period;
                $('.js-ltv-admins-period-btn').removeClass('active');
                $(this).addClass('active');
                Object.keys(ltvAdminsDetailTables).forEach(function (locationId) {
                    destroyLtvAdminsDetailTable(locationId);
                });
                ltvAdminsSyncPeriodInUrl();
                refreshLtvAdminsReportTotal();
                dtApi.reload();
            });

            $('.js-ltv-admins-group-mode-btn').on('click', function () {
                var btn = $(this);
                var mode = btn.data('mode');
                if (mode !== 'operation' && mode !== 'subscription') {
                    return;
                }
                if (mode === currentMode) {
                    return;
                }
                var payload = ltvAdminsReportFilterParams();
                payload.mode = mode;
                kidsCrmPersistReportFilters($ltvFiltersForm, ltvAdminsFiltersSaveUrl, payload, function () {
                    currentMode = mode;
                    $('.js-ltv-admins-group-mode-btn').removeClass('active');
                    btn.addClass('active');
                    Object.keys(ltvAdminsDetailTables).forEach(function (locationId) {
                        destroyLtvAdminsDetailTable(locationId);
                    });
                    refreshLtvAdminsReportTotal();
                    dtApi.reload();
                });
            });

            $('#ltvAdminsReportFiltersResetBtn').on('click', function () {
                kidsCrmPersistReportFilters($ltvFiltersForm, ltvAdminsFiltersSaveUrl, { reset: 1 }, function () {
                    $ltvFiltersForm[0].reset();
                    $ltvFilterUser.val(null).trigger('change');
                    if (window.KidsCrmGenericMultiselectSelect2) {
                        KidsCrmGenericMultiselectSelect2.reset($ltvFilterTeam);
                        if ($ltvFilterTrainer.length) {
                            KidsCrmGenericMultiselectSelect2.reset($ltvFilterTrainer);
                        }
                        if (canViewLocations) {
                            KidsCrmGenericMultiselectSelect2.reset($('#pay-ltv-admins-filter-location'));
                            KidsCrmGenericMultiselectSelect2.reset($('#pay-ltv-admins-filter-admin'));
                        }
                    }
                    $('#pay-ltv-admins-filter-user-status').val(defaultFilterUserStatus);
                    currentMode = 'operation';
                    $('.js-ltv-admins-group-mode-btn').removeClass('active');
                    $('.js-ltv-admins-group-mode-btn[data-mode="operation"]').addClass('active');
                    Object.keys(ltvAdminsDetailTables).forEach(function (locationId) {
                        destroyLtvAdminsDetailTable(locationId);
                    });
                    refreshLtvAdminsReportTotal();
                    dtApi.reload();
                });
            });

            $('#ltv-admins-table tbody').on('click', 'td.details-control button', function(e) {
                e.stopPropagation();

                var btn = $(this);
                var tr = btn.closest('tr');
                var row = ltvTable.row(tr);

                if (row.child.isShown()) {
                    var hideData = row.data();
                    if (hideData && hideData.admin_user_id !== undefined && hideData.admin_user_id !== null) {
                        destroyLtvAdminsDetailTable(hideData.admin_user_id);
                    }
                    row.child.hide();
                    tr.removeClass('shown');
                    btn.find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    return;
                }

                var data = row.data();
                var locationId = data.admin_user_id;
                var locationName = data.admin_name;

                row.child(buildLtvAdminsDetailContainerHtml(locationId, locationName)).show();
                tr.addClass('shown');
                btn.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-up');

                ltvAdminsDetailTables[locationId] = initLtvAdminPaymentsDetailTable(locationId);
            });

        });
    </script>
@endsection

@include('partials.ui.user-card-modal', [
    'userCardUrl' => url('/admin/reports/payments/users'),
])

@include('partials.select2.generic-multiselect')

@push('scripts')
    <script>
        $(function () {
            if (!window.KidsCrmGenericMultiselectSelect2) {
                return;
            }
            ['#pay-ltv-admins-filter-team', '#pay-ltv-admins-filter-trainer', '#pay-ltv-admins-filter-location', '#pay-ltv-admins-filter-admin'].forEach(function (selector) {
                var $el = $(selector);
                if (!$el.length) {
                    return;
                }
                KidsCrmGenericMultiselectSelect2.init($el, {
                    placeholder: $el.data('placeholder') || '',
                    allowClear: true,
                    dropdownParent: $('#ltv-admins-report-filters')
                });
            });
        });
    </script>
@endpush
