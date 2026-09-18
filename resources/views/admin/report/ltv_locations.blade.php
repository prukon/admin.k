@php
    $filters = $filters ?? [];
    $paymentsFilterUser = $paymentsFilterUser ?? null;
    $paymentsFilterTeam = $paymentsFilterTeam ?? null;
    $paymentsFilterTrainer = $paymentsFilterTrainer ?? null;
    $canViewTrainers = $canViewTrainers ?? (auth()->user() && auth()->user()->can('trainers.view'));
    $canViewLocations = $canViewLocations ?? (auth()->user() && auth()->user()->can('locations.view'));
    $activeLocations = $activeLocations ?? collect();
    $ltvLocationsPeriod = in_array(($ltvLocationsPeriod ?? 'current'), ['current', 'previous', 'all'], true)
        ? ($ltvLocationsPeriod ?? 'current')
        : 'current';
    $ltvLocationsPeriodLabels = $ltvLocationsPeriodLabels ?? [
        'current' => '',
        'previous' => '',
        'all' => 'Все время',
    ];
    $payFilterKeys = ['filter_user_id', 'filter_team_id', 'filter_trainer_profile_id', 'filter_location_id', 'user_name', 'team_title', 'payment_month', 'operation_date_from', 'operation_date_to', 'payment_provider'];
    $payFilterLocation = $filters['filter_location_id'] ?? '';
    $payFilterUserStatus = array_key_exists('status', $filters) ? (string) ($filters['status'] ?? '') : 'active';
    $payHasActiveFilters = false;
    foreach ($payFilterKeys as $k) {
        $v = $filters[$k] ?? null;
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
                <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate">Платежи по объектам</h1>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="btn-group btn-group-sm js-ltv-locations-period" role="group" aria-label="Период" id="ltv-locations-period-switch">
                        <button type="button" class="btn btn-outline-secondary js-ltv-locations-period-btn {{ $ltvLocationsPeriod === 'current' ? 'active' : '' }}"
                                data-period="current" id="ltv-locations-period-btn-current">{{ $ltvLocationsPeriodLabels['current'] }}</button>
                        <button type="button" class="btn btn-outline-secondary js-ltv-locations-period-btn {{ $ltvLocationsPeriod === 'previous' ? 'active' : '' }}"
                                data-period="previous" id="ltv-locations-period-btn-previous">{{ $ltvLocationsPeriodLabels['previous'] }}</button>
                        <button type="button" class="btn btn-outline-secondary js-ltv-locations-period-btn {{ $ltvLocationsPeriod === 'all' ? 'active' : '' }}"
                                data-period="all" id="ltv-locations-period-btn-all">{{ $ltvLocationsPeriodLabels['all'] }}</button>
                    </div>
                    <div class="invalid-feedback" data-error-for="period" @error('period') style="display:block" @enderror>@error('period'){{ $message }}@enderror</div>
                </div>
            </div>
            <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                <div class="payments-report-total-inline payments-report-total-stat text-end" id="ltvLocationsReportTotalStat">
                    <div class="payments-report-total-label text-muted small mb-0">Общая сумма</div>
                    <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                        <span class="payments-report-total-value-inner">
                            <span class="payments-report-total-amount">{{ $totalPaidPrice }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                    <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#ltvLocationsReportFiltersCollapse"
                            aria-expanded="{{ $payHasActiveFilters ? 'true' : 'false' }}"
                            aria-controls="ltvLocationsReportFiltersCollapse"
                            id="ltvLocationsReportFiltersToggle">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="dropdown payments-report-toolbar-dropdown">
                        <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                id="columnsDropdownLtvLocationsReport"
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
                             aria-labelledby="columnsDropdownLtvLocationsReport">
                            <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>
                            <div class="form-check">
                                <input class="form-check-input ltv-locations-column-toggle" type="checkbox" id="ltvLocationsColLocation" data-column-key="location_name" checked>
                                <label class="form-check-label" for="ltvLocationsColLocation">Объект</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-locations-column-toggle" type="checkbox" id="ltvLocationsColName" data-column-key="user_names" checked>
                                <label class="form-check-label" for="ltvLocationsColName">Ученики</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-locations-column-toggle" type="checkbox" id="ltvLocationsColAvgAttendance" data-column-key="avg_attendance" checked>
                                <label class="form-check-label" for="ltvLocationsColAvgAttendance">Ср. посещаемость</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-locations-column-toggle" type="checkbox" id="ltvLocationsColSum" data-column-key="total_price" checked>
                                <label class="form-check-label" for="ltvLocationsColSum">Сумма</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-locations-column-toggle" type="checkbox" id="ltvLocationsColCount" data-column-key="payment_count" checked>
                                <label class="form-check-label" for="ltvLocationsColCount">Кол-во платежей</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-locations-column-toggle" type="checkbox" id="ltvLocationsColFirst" data-column-key="first_payment_date" checked>
                                <label class="form-check-label" for="ltvLocationsColFirst">Перв. платёж</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-locations-column-toggle" type="checkbox" id="ltvLocationsColLast" data-column-key="last_payment_date" checked>
                                <label class="form-check-label" for="ltvLocationsColLast">Посл. платёж</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-locations-column-toggle" type="checkbox" id="ltvLocationsColStatus" data-column-key="is_enabled" checked>
                                <label class="form-check-label" for="ltvLocationsColStatus">Статус</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $payHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="ltvLocationsReportFiltersCollapse">
    <form id="ltv-locations-report-filters" method="GET" action="{{ route('reports.ltv.locations') }}" class="border rounded p-2 p-md-3 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-ltv-locations-filter-user">Ученик</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-ltv-locations-filter-user"
                        name="filter_user_id"
                        data-placeholder="Все ученики"
                        data-search-url="{{ route('reports.payments.users.search') }}">
                    <option value=""></option>
                    @if($paymentsFilterUser)
                        <option value="{{ $paymentsFilterUser['id'] }}" selected>{{ $paymentsFilterUser['text'] }}</option>
                    @endif
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-ltv-locations-filter-team">Группа</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-ltv-locations-filter-team"
                        name="filter_team_id"
                        data-placeholder="Все группы"
                        data-search-url="{{ route('reports.payments.teams.search') }}">
                    <option value=""></option>
                    @if($paymentsFilterTeam)
                        <option value="{{ $paymentsFilterTeam['id'] }}" selected>{{ $paymentsFilterTeam['text'] }}</option>
                    @endif
                </select>
            </div>
            @if($canViewTrainers)
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-ltv-locations-filter-trainer">Тренер</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-ltv-locations-filter-trainer"
                        name="filter_trainer_profile_id"
                        data-placeholder="Все тренеры"
                        data-search-url="{{ route('reports.payments.trainers.search') }}">
                    <option value=""></option>
                    @if($paymentsFilterTrainer)
                        <option value="{{ $paymentsFilterTrainer['id'] }}" selected>{{ $paymentsFilterTrainer['text'] }}</option>
                    @endif
                </select>
            </div>
            @endif
            @if($canViewLocations)
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-ltv-locations-filter-location">Объект</label>
                <select class="form-select" id="pay-ltv-locations-filter-location" name="filter_location_id">
                    <option value="">Все объекты</option>
                    <option value="none" {{ (string) $payFilterLocation === 'none' ? 'selected' : '' }}>Без объекта</option>
                    @foreach($activeLocations as $location)
                        <option value="{{ $location->id }}" {{ (string) $payFilterLocation === (string) $location->id ? 'selected' : '' }}>
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-locations-filter-payment-month">Оплаченный месяц</label>
                <input class="form-control" id="pay-ltv-locations-filter-payment-month" type="month" name="payment_month"
                       value="{{ $filters['payment_month'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-locations-filter-op-from">Дата платежа: с</label>
                <input class="form-control" id="pay-ltv-locations-filter-op-from" type="date" name="operation_date_from"
                       value="{{ $filters['operation_date_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-locations-filter-op-to">Дата платежа: по</label>
                <input class="form-control" id="pay-ltv-locations-filter-op-to" type="date" name="operation_date_to"
                       value="{{ $filters['operation_date_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-locations-filter-provider">Провайдер</label>
                @php($fpProvider = $filters['payment_provider'] ?? '')
                <select class="form-select" id="pay-ltv-locations-filter-provider" name="payment_provider">
                    <option value="">—</option>
                    <option value="tbank" {{ $fpProvider === 'tbank' ? 'selected' : '' }}>T-Bank</option>
                    <option value="robokassa" {{ $fpProvider === 'robokassa' ? 'selected' : '' }}>Robokassa</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-locations-filter-user-status">Активность ученика</label>
                <select class="form-select" id="pay-ltv-locations-filter-user-status" name="status">
                    <option value="">Все ученики</option>
                    <option value="active" {{ $payFilterUserStatus === 'active' ? 'selected' : '' }}>Только активные</option>
                    <option value="inactive" {{ $payFilterUserStatus === 'inactive' ? 'selected' : '' }}>Только неактивные</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="ltvLocationsReportFiltersResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

<table class="table table-bordered dt-columns-managed w-100" id="ltv-locations-table">
    <thead>
        <tr>
            <th style="width: 60px;"></th>
            <th>Объект</th>
            <th>Ученики</th>
            <th>Ср. посещаемость</th>
            <th>Сумма</th>
            <th>Кол-во платежей</th>
            <th>Перв. платёж</th>
            <th>Посл. платёж</th>
            <th>Статус</th>
        </tr>
    </thead>
</table>

@section('scripts')
    <script src="{{ asset('plugins/datatables-fixedheader/js/dataTables.fixedHeader.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {

            var canViewLocations = @json($canViewLocations);
            var defaultFilterUserStatus = 'active';
            var $ltvFiltersForm = $('#ltv-locations-report-filters');
            var $ltvFilterUser = $('#pay-ltv-locations-filter-user');
            var $ltvFilterTeam = $('#pay-ltv-locations-filter-team');
            var $ltvFilterTrainer = $('#pay-ltv-locations-filter-trainer');
            var $ltvLocationsReportTotalAmount = $('.payments-report-total-amount');
            var $ltvLocationsReportTotalStat = $('#ltvLocationsReportTotalStat');
            var $ltvLocationsReportTotalValueInner = $('.payments-report-total-value-inner');
            var currentPeriod = @json($ltvLocationsPeriod);

            function ltvLocationsReportParseTotalToInt(str) {
                return parseInt(String(str || '').replace(/\s/g, ''), 10) || 0;
            }

            function ltvLocationsReportFormatTotalSpaces(n) {
                var v = Math.round(Number(n));
                if (isNaN(v)) {
                    return '0';
                }
                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function ltvLocationsReportAnimateTotalChange(prevText, nextText, nextRaw) {
                var $amount = $ltvLocationsReportTotalAmount;
                if (!$amount.length) {
                    return;
                }

                var nextVal = typeof nextRaw === 'number' && !isNaN(nextRaw)
                    ? Math.round(nextRaw)
                    : ltvLocationsReportParseTotalToInt(nextText);
                var prevVal = ltvLocationsReportParseTotalToInt(prevText);

                var runFlashAndPop = function () {
                    if ($ltvLocationsReportTotalStat.length) {
                        $ltvLocationsReportTotalStat.removeClass('payments-report-total-stat--flash');
                        void $ltvLocationsReportTotalStat[0].offsetWidth;
                        $ltvLocationsReportTotalStat.addClass('payments-report-total-stat--flash');
                    }
                    if ($ltvLocationsReportTotalValueInner.length) {
                        $ltvLocationsReportTotalValueInner.removeClass('payments-report-total-value-inner--pop');
                        void $ltvLocationsReportTotalValueInner[0].offsetWidth;
                        $ltvLocationsReportTotalValueInner.addClass('payments-report-total-value-inner--pop');
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
                    $amount.text(ltvLocationsReportFormatTotalSpaces(cur));
                    if (t < 1) {
                        window.requestAnimationFrame(step);
                    } else {
                        $amount.text(nextText);
                    }
                }

                runFlashAndPop();
                window.requestAnimationFrame(step);
            }

            function ltvLocationsReportFilterParams() {
                var uid = $ltvFiltersForm.find('[name="filter_user_id"]').val() || '';
                var tid = $ltvFiltersForm.find('[name="filter_team_id"]').val() || '';
                var tpid = $ltvFilterTrainer.length
                    ? ($ltvFiltersForm.find('[name="filter_trainer_profile_id"]').val() || '')
                    : '';
                return {
                    filter_user_id: uid,
                    filter_team_id: tid,
                    filter_trainer_profile_id: tpid,
                    filter_location_id: canViewLocations
                        ? ($ltvFiltersForm.find('[name="filter_location_id"]').val() || '')
                        : '',
                    status: $ltvFiltersForm.find('[name="status"]').val() || '',
                    user_name: '',
                    team_title: '',
                    payment_month: $ltvFiltersForm.find('[name="payment_month"]').val() || '',
                    operation_date_from: $ltvFiltersForm.find('[name="operation_date_from"]').val() || '',
                    operation_date_to: $ltvFiltersForm.find('[name="operation_date_to"]').val() || '',
                    payment_provider: $ltvFiltersForm.find('[name="payment_provider"]').val() || '',
                    period: currentPeriod
                };
            }

            function ltvLocationsSyncPeriodInUrl() {
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

            function refreshLtvLocationsReportTotal() {
                var prevText = $ltvLocationsReportTotalAmount.length ? $ltvLocationsReportTotalAmount.text() : '';
                if ($ltvLocationsReportTotalStat.length) {
                    $ltvLocationsReportTotalStat.addClass('payments-report-total-stat--loading');
                }
                $.get(@json(route('reports.ltv.locations.total')), ltvLocationsReportFilterParams())
                    .done(function (res) {
                        if ($ltvLocationsReportTotalStat.length) {
                            $ltvLocationsReportTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                        if (!res || res.total_formatted === undefined || !$ltvLocationsReportTotalAmount.length) {
                            return;
                        }
                        var nextText = res.total_formatted;
                        ltvLocationsReportAnimateTotalChange(prevText, nextText, res.total_raw);
                    })
                    .fail(function () {
                        if ($ltvLocationsReportTotalStat.length) {
                            $ltvLocationsReportTotalStat.removeClass('payments-report-total-stat--loading');
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
            initPaymentsReportFilterSelect2($ltvFilterTeam);
            initPaymentsReportFilterSelect2($ltvFilterTrainer);

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

            var ltvLocationsDetailTables = {};
            var ltvLocationsDetailMeta = {};

            function destroyLtvLocationsDetailTable(locationId) {
                var tableId = 'ltv-location-payments-' + locationId;
                if (ltvLocationsDetailTables[locationId]) {
                    ltvLocationsDetailTables[locationId].destroy();
                    delete ltvLocationsDetailTables[locationId];
                }
                delete ltvLocationsDetailMeta[locationId];
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

            function updateLtvLocationsDetailSummary(locationId) {
                var meta = ltvLocationsDetailMeta[locationId] || {};
                var $summary = $('#ltv-locations-detail-summary-' + locationId);
                if (!$summary.length) {
                    return;
                }
                var count = meta.payments_count || 0;
                var sum = (meta.sum_total || 0).toLocaleString('ru-RU');
                $summary.html('Всего платежей: <b>' + count + '</b>, на сумму <b>' + sum + ' руб</b>');
            }

            function initLtvLocationPaymentsDetailTable(locationId) {
                var tableId = 'ltv-location-payments-' + locationId;

                return $('#' + tableId).DataTable({
                    processing: true,
                    serverSide: true,
                    autoWidth: false,
                    dom: 'rtip',
                    pageLength: 10,
                    lengthMenu: [10, 20, 50, 100],
                    ajax: {
                        url: '/admin/reports/ltv/locations/' + locationId + '/payments',
                        type: 'GET',
                        data: function (d) {
                            var extra = ltvLocationsReportFilterParams();
                            Object.keys(extra).forEach(function (key) {
                                d[key] = extra[key];
                            });
                        },
                        dataSrc: function (json) {
                            ltvLocationsDetailMeta[locationId] = {
                                payments_count: json.meta_payments_count || 0,
                                sum_total: json.meta_sum_total || 0
                            };
                            updateLtvLocationsDetailSummary(locationId);
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
                            name: 'user_name'
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
                                return (parseFloat(data || 0)).toLocaleString('ru-RU') + ' руб';
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

            function buildLtvLocationsDetailContainerHtml(locationId, locationName) {
                var safeName = locationName || 'Без объекта';

                return '' +
                    '<div class="p-3 details-container bg-light border-start border-3 border-secondary" id="ltv-locations-detail-wrap-' + locationId + '">' +
                    '  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">' +
                    '    <div class="fw-bold">Платежи объекта: ' + safeName + '</div>' +
                    '    <div class="small text-muted" id="ltv-locations-detail-summary-' + locationId + '"></div>' +
                    '  </div>' +
                    '  <table class="table table-sm table-bordered mb-0 align-middle w-100" id="ltv-location-payments-' + locationId + '">' +
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

            var dtApi = KidsCrmDataTable.create('#ltv-locations-table', {
                columnsSettings: {
                    persistPageLength: true,
                    defaults: {
                        location_name: true,
                        user_names: true,
                        avg_attendance: true,
                        total_price: true,
                        payment_count: true,
                        first_payment_date: true,
                        last_payment_date: true,
                        is_enabled: true
                    },
                    urls: {
                        get: @json(route('reports.ltv.locations.columns-settings.get')),
                        save: @json(route('reports.ltv.locations.columns-settings.save'))
                    },
                    toggleSelector: '.ltv-locations-column-toggle',
                    csrfToken: '{{ csrf_token() }}'
                },
                dataTable: {
                    pageLength: @json((int) ($ltvLocationsPageLength ?? 10)),
                    ajax: {
                        url: @json(route('reports.ltv.locations.data')),
                        type: 'GET',
                        data: function (d) {
                            var extra = ltvLocationsReportFilterParams();
                            Object.keys(extra).forEach(function (key) {
                                d[key] = extra[key];
                            });
                        }
                    },
                    order: [[4, 'desc']],
                    language: @include('partials.datatables.ru'),
                    fixedHeader: ($.fn.dataTable && $.fn.dataTable.FixedHeader)
                        ? { header: true, footer: false }
                        : false,
                    drawCallback: function () {
                        if (window.KidsCrmReportTableSticky) {
                            window.KidsCrmReportTableSticky.bind('#ltv-locations-table');
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
                    { key: 'location_name', type: 'text', data: 'location_name', name: 'location_name' },
                    { key: 'user_names', type: 'list', data: 'user_names', name: 'user_names', itemsKey: 'user_names_items', listOptions: { customClass: 'kids-hover-list-tooltip--two-col' } },
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
                    { key: 'total_price', type: 'money', data: 'total_price', name: 'total_price', searchable: false },
                    { key: 'payment_count', type: 'count', data: 'payment_count', name: 'payment_count', searchable: false },
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
                        key: 'is_enabled',
                        type: 'badge',
                        data: 'is_enabled',
                        name: 'is_enabled',
                        searchable: false,
                        className: 'dt-col-badge text-center',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data;
                            }
                            if (!row || !row.location_id) {
                                return '—';
                            }
                            if (data) {
                                return '<span class="badge bg-success">Активен</span>';
                            }
                            return '<span class="badge bg-secondary">Отключен</span>';
                        }
                    },
                    {
                        key: 'location_id',
                        type: 'id',
                        data: 'location_id',
                        name: 'location_id',
                        visible: false,
                        searchable: false
                    }
                ]
            });

            var ltvTable = dtApi.table;

            $ltvFiltersForm.on('submit', function (e) {
                e.preventDefault();
                refreshLtvLocationsReportTotal();
                Object.keys(ltvLocationsDetailTables).forEach(function (locationId) {
                    destroyLtvLocationsDetailTable(locationId);
                });
                dtApi.reload({ keepPage: true });
            });

            $('.js-ltv-locations-period-btn').on('click', function () {
                var period = $(this).data('period');
                if (period !== 'current' && period !== 'previous' && period !== 'all') {
                    return;
                }
                if (period === currentPeriod) {
                    return;
                }
                currentPeriod = period;
                $('.js-ltv-locations-period-btn').removeClass('active');
                $(this).addClass('active');
                Object.keys(ltvLocationsDetailTables).forEach(function (locationId) {
                    destroyLtvLocationsDetailTable(locationId);
                });
                ltvLocationsSyncPeriodInUrl();
                refreshLtvLocationsReportTotal();
                dtApi.reload();
            });

            $('#ltvLocationsReportFiltersResetBtn').on('click', function () {
                $ltvFiltersForm[0].reset();
                $ltvFilterUser.val(null).trigger('change');
                $ltvFilterTeam.val(null).trigger('change');
                $ltvFilterTrainer.val(null).trigger('change');
                $('#pay-ltv-locations-filter-user-status').val(defaultFilterUserStatus);
                if (canViewLocations) {
                    $('#pay-ltv-locations-filter-location').val('');
                }
                Object.keys(ltvLocationsDetailTables).forEach(function (locationId) {
                    destroyLtvLocationsDetailTable(locationId);
                });
                refreshLtvLocationsReportTotal();
                dtApi.reload();
            });

            $('#ltv-locations-table tbody').on('click', 'td.details-control button', function(e) {
                e.stopPropagation();

                var btn = $(this);
                var tr = btn.closest('tr');
                var row = ltvTable.row(tr);

                if (row.child.isShown()) {
                    var hideData = row.data();
                    if (hideData && hideData.location_id !== undefined && hideData.location_id !== null) {
                        destroyLtvLocationsDetailTable(hideData.location_id);
                    }
                    row.child.hide();
                    tr.removeClass('shown');
                    btn.find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    return;
                }

                var data = row.data();
                var locationId = data.location_id;
                var locationName = data.location_name;

                row.child(buildLtvLocationsDetailContainerHtml(locationId, locationName)).show();
                tr.addClass('shown');
                btn.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-up');

                ltvLocationsDetailTables[locationId] = initLtvLocationPaymentsDetailTable(locationId);
            });

        });
    </script>
@endsection
