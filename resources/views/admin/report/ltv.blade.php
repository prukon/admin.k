@php
    $filters = $filters ?? [];
    $paymentsFilterUser = $paymentsFilterUser ?? null;
    $paymentsFilterTeam = $paymentsFilterTeam ?? null;
    $paymentsFilterTrainer = $paymentsFilterTrainer ?? null;
    $canViewTrainers = $canViewTrainers ?? (auth()->user() && auth()->user()->can('trainers.view'));
    $canViewLocations = $canViewLocations ?? (auth()->user() && auth()->user()->can('locations.view'));
    $activeLocations = $activeLocations ?? collect();
    $payFilterKeys = ['filter_user_id', 'filter_team_id', 'filter_trainer_profile_id', 'filter_location_id', 'user_name', 'team_title', 'payment_month', 'operation_date_from', 'operation_date_to', 'payment_provider'];
    $payFilterLocation = $filters['filter_location_id'] ?? '';
    $payHasActiveFilters = false;
    foreach ($payFilterKeys as $k) {
        $v = $filters[$k] ?? null;
        if ($v !== null && $v !== '') {
            $payHasActiveFilters = true;
            break;
        }
    }
@endphp

@vite(['resources/css/admin-list-toolbar.css'])

<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
    <div class="card-body px-3 py-3">
        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Платежи по ученикам</h1>
            <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                <div class="payments-report-total-inline payments-report-total-stat text-end" id="ltvReportTotalStat">
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
                            data-bs-target="#ltvReportFiltersCollapse"
                            aria-expanded="{{ $payHasActiveFilters ? 'true' : 'false' }}"
                            aria-controls="ltvReportFiltersCollapse"
                            id="ltvReportFiltersToggle">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="dropdown payments-report-toolbar-dropdown">
                        <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                id="columnsDropdownLtvReport"
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
                             aria-labelledby="columnsDropdownLtvReport">
                            <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>
                            <div class="form-check">
                                <input class="form-check-input ltv-column-toggle" type="checkbox" id="ltvColName" data-column-key="user_name" checked>
                                <label class="form-check-label" for="ltvColName">ФИО</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-column-toggle" type="checkbox" id="ltvColTeam" data-column-key="team_title" checked>
                                <label class="form-check-label" for="ltvColTeam">Группа</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-column-toggle" type="checkbox" id="ltvColSum" data-column-key="total_price" checked>
                                <label class="form-check-label" for="ltvColSum">Сумма</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-column-toggle" type="checkbox" id="ltvColCount" data-column-key="payment_count" checked>
                                <label class="form-check-label" for="ltvColCount">Кол-во платежей</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-column-toggle" type="checkbox" id="ltvColFirst" data-column-key="first_payment_date" checked>
                                <label class="form-check-label" for="ltvColFirst">Перв. платёж</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-column-toggle" type="checkbox" id="ltvColLast" data-column-key="last_payment_date" checked>
                                <label class="form-check-label" for="ltvColLast">Посл. платёж</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input ltv-column-toggle" type="checkbox" id="ltvColStatus" data-column-key="is_enabled" checked>
                                <label class="form-check-label" for="ltvColStatus">Статус</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $payHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="ltvReportFiltersCollapse">
    <form id="ltv-report-filters" method="GET" action="{{ route('reports.ltv') }}" class="border rounded p-2 p-md-3 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-ltv-filter-user">Ученик</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-ltv-filter-user"
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
                <label class="form-label" for="pay-ltv-filter-team">Группа</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-ltv-filter-team"
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
                <label class="form-label" for="pay-ltv-filter-trainer">Тренер</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-ltv-filter-trainer"
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
                <label class="form-label" for="pay-ltv-filter-location">Локация</label>
                <select class="form-select" id="pay-ltv-filter-location" name="filter_location_id">
                    <option value="">Все локации</option>
                    <option value="none" {{ (string) $payFilterLocation === 'none' ? 'selected' : '' }}>Без локации</option>
                    @foreach($activeLocations as $location)
                        <option value="{{ $location->id }}" {{ (string) $payFilterLocation === (string) $location->id ? 'selected' : '' }}>
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-filter-payment-month">Оплаченный месяц</label>
                <input class="form-control" id="pay-ltv-filter-payment-month" type="month" name="payment_month"
                       value="{{ $filters['payment_month'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-filter-op-from">Дата платежа: с</label>
                <input class="form-control" id="pay-ltv-filter-op-from" type="date" name="operation_date_from"
                       value="{{ $filters['operation_date_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-filter-op-to">Дата платежа: по</label>
                <input class="form-control" id="pay-ltv-filter-op-to" type="date" name="operation_date_to"
                       value="{{ $filters['operation_date_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-ltv-filter-provider">Провайдер</label>
                @php($fpProvider = $filters['payment_provider'] ?? '')
                <select class="form-select" id="pay-ltv-filter-provider" name="payment_provider">
                    <option value="">—</option>
                    <option value="tbank" {{ $fpProvider === 'tbank' ? 'selected' : '' }}>T-Bank</option>
                    <option value="robokassa" {{ $fpProvider === 'robokassa' ? 'selected' : '' }}>Robokassa</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="ltvReportFiltersResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

<table class="table table-bordered dt-columns-managed w-100" id="ltv-table">
    <thead>
        <tr>
            <th style="width: 60px;"></th>
            <th>ФИО</th>
            <th>Группа</th>
            <th>Сумма</th>
            <th>Кол-во платежей</th>
            <th>Перв. платёж</th>
            <th>Посл. платёж</th>
            <th>Статус</th>
        </tr>
    </thead>
</table>

@section('scripts')
    <script type="text/javascript">
        $(function() {

            var canViewLocations = @json($canViewLocations);
            var $ltvFiltersForm = $('#ltv-report-filters');
            var $ltvFilterUser = $('#pay-ltv-filter-user');
            var $ltvFilterTeam = $('#pay-ltv-filter-team');
            var $ltvFilterTrainer = $('#pay-ltv-filter-trainer');
            var $ltvReportTotalAmount = $('.payments-report-total-amount');
            var $ltvReportTotalStat = $('#ltvReportTotalStat');
            var $ltvReportTotalValueInner = $('.payments-report-total-value-inner');

            function ltvReportParseTotalToInt(str) {
                return parseInt(String(str || '').replace(/\s/g, ''), 10) || 0;
            }

            function ltvReportFormatTotalSpaces(n) {
                var v = Math.round(Number(n));
                if (isNaN(v)) {
                    return '0';
                }
                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function ltvReportAnimateTotalChange(prevText, nextText, nextRaw) {
                var $amount = $ltvReportTotalAmount;
                if (!$amount.length) {
                    return;
                }

                var nextVal = typeof nextRaw === 'number' && !isNaN(nextRaw)
                    ? Math.round(nextRaw)
                    : ltvReportParseTotalToInt(nextText);
                var prevVal = ltvReportParseTotalToInt(prevText);

                var runFlashAndPop = function () {
                    if ($ltvReportTotalStat.length) {
                        $ltvReportTotalStat.removeClass('payments-report-total-stat--flash');
                        void $ltvReportTotalStat[0].offsetWidth;
                        $ltvReportTotalStat.addClass('payments-report-total-stat--flash');
                    }
                    if ($ltvReportTotalValueInner.length) {
                        $ltvReportTotalValueInner.removeClass('payments-report-total-value-inner--pop');
                        void $ltvReportTotalValueInner[0].offsetWidth;
                        $ltvReportTotalValueInner.addClass('payments-report-total-value-inner--pop');
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
                    $amount.text(ltvReportFormatTotalSpaces(cur));
                    if (t < 1) {
                        window.requestAnimationFrame(step);
                    } else {
                        $amount.text(nextText);
                    }
                }

                runFlashAndPop();
                window.requestAnimationFrame(step);
            }

            function ltvReportFilterParams() {
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
                    user_name: '',
                    team_title: '',
                    payment_month: $ltvFiltersForm.find('[name="payment_month"]').val() || '',
                    operation_date_from: $ltvFiltersForm.find('[name="operation_date_from"]').val() || '',
                    operation_date_to: $ltvFiltersForm.find('[name="operation_date_to"]').val() || '',
                    payment_provider: $ltvFiltersForm.find('[name="payment_provider"]').val() || ''
                };
            }

            function refreshLtvReportTotal() {
                var prevText = $ltvReportTotalAmount.length ? $ltvReportTotalAmount.text() : '';
                if ($ltvReportTotalStat.length) {
                    $ltvReportTotalStat.addClass('payments-report-total-stat--loading');
                }
                $.get(@json(route('reports.ltv.total')), ltvReportFilterParams())
                    .done(function (res) {
                        if ($ltvReportTotalStat.length) {
                            $ltvReportTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                        if (!res || res.total_formatted === undefined || !$ltvReportTotalAmount.length) {
                            return;
                        }
                        var nextText = res.total_formatted;
                        ltvReportAnimateTotalChange(prevText, nextText, res.total_raw);
                    })
                    .fail(function () {
                        if ($ltvReportTotalStat.length) {
                            $ltvReportTotalStat.removeClass('payments-report-total-stat--loading');
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

            var ltvDetailTables = {};
            var ltvDetailMeta = {};

            function destroyLtvDetailTable(userId) {
                var tableId = 'ltv-user-payments-' + userId;
                if (ltvDetailTables[userId]) {
                    ltvDetailTables[userId].destroy();
                    delete ltvDetailTables[userId];
                }
                delete ltvDetailMeta[userId];
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

            function updateLtvDetailSummary(userId) {
                var meta = ltvDetailMeta[userId] || {};
                var $summary = $('#ltv-detail-summary-' + userId);
                if (!$summary.length) {
                    return;
                }
                var count = meta.payments_count || 0;
                var sum = (meta.sum_total || 0).toLocaleString('ru-RU');
                $summary.html('Всего платежей: <b>' + count + '</b>, на сумму <b>' + sum + ' руб</b>');
            }

            function initLtvUserPaymentsDetailTable(userId) {
                var tableId = 'ltv-user-payments-' + userId;

                return $('#' + tableId).DataTable({
                    processing: true,
                    serverSide: true,
                    autoWidth: false,
                    dom: 'rtip',
                    pageLength: 10,
                    lengthMenu: [10, 20, 50, 100],
                    ajax: {
                        url: '/admin/reports/ltv/' + userId + '/payments',
                        type: 'GET',
                        data: function (d) {
                            var extra = ltvReportFilterParams();
                            Object.keys(extra).forEach(function (key) {
                                d[key] = extra[key];
                            });
                        },
                        dataSrc: function (json) {
                            ltvDetailMeta[userId] = {
                                payments_count: json.meta_payments_count || 0,
                                sum_total: json.meta_sum_total || 0
                            };
                            updateLtvDetailSummary(userId);
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

            function buildLtvDetailContainerHtml(userId, userName, teamTitle) {
                var safeUserName = userName || 'Без имени';
                var safeTeam = teamTitle || 'Без команды';

                return '' +
                    '<div class="p-3 details-container bg-light border-start border-3 border-secondary" id="ltv-detail-wrap-' + userId + '">' +
                    '  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">' +
                    '    <div class="fw-bold">Платежи ученика: ' + safeUserName + ' (' + safeTeam + ')</div>' +
                    '    <div class="small text-muted" id="ltv-detail-summary-' + userId + '"></div>' +
                    '  </div>' +
                    '  <table class="table table-sm table-bordered mb-0 align-middle w-100" id="ltv-user-payments-' + userId + '">' +
                    '    <thead class="table-light small">' +
                    '      <tr>' +
                    '        <th>Дата и время платежа</th>' +
                    '        <th class="text-end">Сумма</th>' +
                    '        <th>Месяц абонемента</th>' +
                    '        <th>Провайдер</th>' +
                    '      </tr>' +
                    '    </thead>' +
                    '  </table>' +
                    '</div>';
            }

            var dtApi = KidsCrmDataTable.create('#ltv-table', {
                columnsSettings: {
                    defaults: {
                        user_name: true,
                        team_title: true,
                        total_price: true,
                        payment_count: true,
                        first_payment_date: true,
                        last_payment_date: true,
                        is_enabled: true
                    },
                    urls: {
                        get: @json(route('reports.ltv.columns-settings.get')),
                        save: @json(route('reports.ltv.columns-settings.save'))
                    },
                    toggleSelector: '.ltv-column-toggle',
                    csrfToken: '{{ csrf_token() }}'
                },
                dataTable: {
                    ajax: {
                        url: @json(route('reports.ltv.data')),
                        type: 'GET',
                        data: function (d) {
                            var extra = ltvReportFilterParams();
                            Object.keys(extra).forEach(function (key) {
                                d[key] = extra[key];
                            });
                        }
                    },
                    order: [[3, 'desc']],
                    language: @include('partials.datatables.ru')
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
                    { key: 'user_name', type: 'text', data: 'user_name', name: 'user_name' },
                    { key: 'team_title', type: 'text', data: 'team_title', name: 'team_title' },
                    { key: 'total_price', type: 'money', data: 'total_price', name: 'total_price' },
                    { key: 'payment_count', type: 'count', data: 'payment_count', name: 'payment_count' },
                    {
                        key: 'first_payment_date',
                        type: 'datetime',
                        data: 'first_payment_date',
                        name: 'first_payment_date',
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
                        className: 'dt-col-badge text-center',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data;
                            }
                            if (data) {
                                return '<span class="badge bg-success">Активен</span>';
                            }
                            return '<span class="badge bg-secondary">Отключен</span>';
                        }
                    },
                    {
                        key: 'user_id',
                        type: 'id',
                        data: 'user_id',
                        name: 'user_id',
                        visible: false,
                        searchable: false
                    }
                ]
            });

            var ltvTable = dtApi.table;

            $ltvFiltersForm.on('submit', function (e) {
                e.preventDefault();
                refreshLtvReportTotal();
                Object.keys(ltvDetailTables).forEach(function (userId) {
                    destroyLtvDetailTable(userId);
                });
                dtApi.reload({ keepPage: true });
            });

            $('#ltvReportFiltersResetBtn').on('click', function () {
                $ltvFiltersForm[0].reset();
                $ltvFilterUser.val(null).trigger('change');
                $ltvFilterTeam.val(null).trigger('change');
                $ltvFilterTrainer.val(null).trigger('change');
                if (canViewLocations) {
                    $('#pay-ltv-filter-location').val('');
                }
                Object.keys(ltvDetailTables).forEach(function (userId) {
                    destroyLtvDetailTable(userId);
                });
                refreshLtvReportTotal();
                dtApi.reload();
            });

            $('#ltv-table tbody').on('click', 'td.details-control button', function(e) {
                e.stopPropagation();

                var btn = $(this);
                var tr = btn.closest('tr');
                var row = ltvTable.row(tr);

                if (row.child.isShown()) {
                    var hideData = row.data();
                    if (hideData && hideData.user_id) {
                        destroyLtvDetailTable(hideData.user_id);
                    }
                    row.child.hide();
                    tr.removeClass('shown');
                    btn.find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    return;
                }

                var data = row.data();
                var userId = data.user_id;
                var userName = data.user_name;
                var teamTitle = data.team_title;

                row.child(buildLtvDetailContainerHtml(userId, userName, teamTitle)).show();
                tr.addClass('shown');
                btn.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-up');

                ltvDetailTables[userId] = initLtvUserPaymentsDetailTable(userId);
            });

        });
    </script>
@endsection
