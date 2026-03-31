@php
    $filters = $filters ?? [];
    $paymentsFilterUser = $paymentsFilterUser ?? null;
    $paymentsFilterTeam = $paymentsFilterTeam ?? null;
    $payFilterKeys = ['filter_user_id', 'filter_team_id', 'user_name', 'team_title', 'payment_month', 'operation_date_from', 'operation_date_to', 'payment_provider'];
    $payHasActiveFilters = false;
    foreach ($payFilterKeys as $k) {
        $v = $filters[$k] ?? null;
        if ($v !== null && $v !== '') {
            $payHasActiveFilters = true;
            break;
        }
    }
    $groupMode = request('mode', 'subscription');
    if (! in_array($groupMode, ['operation', 'subscription'], true)) {
        $groupMode = 'subscription';
    }
@endphp

@vite(['resources/css/payments-report.css'])

<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
    <div class="card-body px-3 py-3">
        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Платежи по месяцам</h1>
            <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                <div class="payments-report-total-inline payments-report-total-stat text-end" id="paymentsMonthlyReportTotalStat">
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
                            data-bs-target="#paymentsMonthlyFiltersCollapse"
                            aria-expanded="{{ $payHasActiveFilters ? 'true' : 'false' }}"
                            aria-controls="paymentsMonthlyFiltersCollapse"
                            id="paymentsMonthlyFiltersToggle">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="dropdown payments-report-toolbar-dropdown">
                        <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                id="columnsDropdownMonthlyPayments"
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
                             aria-labelledby="columnsDropdownMonthlyPayments">
                            <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>
                            <div class="form-check">
                                <input class="form-check-input payments-monthly-column-toggle" type="checkbox" id="monthlyColMonth" data-column-index="1" checked>
                                <label class="form-check-label" for="monthlyColMonth">Месяц</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payments-monthly-column-toggle" type="checkbox" id="monthlyColCount" data-column-index="2" checked>
                                <label class="form-check-label" for="monthlyColCount">Количество платежей</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payments-monthly-column-toggle" type="checkbox" id="monthlyColSum" data-column-index="3" checked>
                                <label class="form-check-label" for="monthlyColSum">Сумма платежей</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
            <span class="small text-muted mb-0">Группировка:</span>
            <div class="btn-group btn-group-sm js-monthly-group-mode" role="group" aria-label="Режим группировки">
                <button type="button" class="btn btn-outline-secondary js-group-mode-btn {{ $groupMode === 'subscription' ? 'active' : '' }}"
                        data-mode="subscription">
                    По месяцу абонемента
                </button>
                <button type="button" class="btn btn-outline-secondary js-group-mode-btn {{ $groupMode === 'operation' ? 'active' : '' }}"
                        data-mode="operation">
                    По дате платежа
                </button>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $payHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="paymentsMonthlyFiltersCollapse">
    <form id="payments-monthly-filters" method="GET" action="{{ route('reports.payments.monthly') }}" class="border rounded p-2 p-md-3 bg-light">
        <input type="hidden" name="mode" id="payments-monthly-mode-hidden" value="{{ $groupMode }}">

        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-monthly-filter-user">Ученик</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-monthly-filter-user"
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
                <label class="form-label" for="pay-monthly-filter-team">Группа</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-monthly-filter-team"
                        name="filter_team_id"
                        data-placeholder="Все группы"
                        data-search-url="{{ route('reports.payments.teams.search') }}">
                    <option value=""></option>
                    @if($paymentsFilterTeam)
                        <option value="{{ $paymentsFilterTeam['id'] }}" selected>{{ $paymentsFilterTeam['text'] }}</option>
                    @endif
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-monthly-filter-payment-month">Оплаченный месяц</label>
                <input class="form-control" id="pay-monthly-filter-payment-month" type="month" name="payment_month"
                       value="{{ $filters['payment_month'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-monthly-filter-op-from">Дата платежа: с</label>
                <input class="form-control" id="pay-monthly-filter-op-from" type="date" name="operation_date_from"
                       value="{{ $filters['operation_date_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-monthly-filter-op-to">Дата платежа: по</label>
                <input class="form-control" id="pay-monthly-filter-op-to" type="date" name="operation_date_to"
                       value="{{ $filters['operation_date_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-monthly-filter-provider">Провайдер</label>
                @php($fpProvider = $filters['payment_provider'] ?? '')
                <select class="form-select" id="pay-monthly-filter-provider" name="payment_provider">
                    <option value="">—</option>
                    <option value="tbank" {{ $fpProvider === 'tbank' ? 'selected' : '' }}>T-Bank</option>
                    <option value="robokassa" {{ $fpProvider === 'robokassa' ? 'selected' : '' }}>Robokassa</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="paymentsMonthlyFiltersResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

<table class="table table-bordered" id="payments-monthly-table">
    <thead>
        <tr>
            <th style="width: 60px;"></th>
            <th>Месяц</th>
            <th>Количество платежей</th>
            <th>Сумма платежей</th>
        </tr>
    </thead>
</table>

@section('scripts')
    <script type="text/javascript">
        $(function() {

            var currentMode = @json($groupMode);

            var $payMonthlyFiltersForm = $('#payments-monthly-filters');
            var $payMonthlyFilterUser = $('#pay-monthly-filter-user');
            var $payMonthlyFilterTeam = $('#pay-monthly-filter-team');
            var $paymentsMonthlyReportTotalAmount = $('.payments-report-total-amount');
            var $paymentsMonthlyReportTotalStat = $('#paymentsMonthlyReportTotalStat');
            var $paymentsMonthlyReportTotalValueInner = $('.payments-report-total-value-inner');

            function paymentsMonthlyReportParseTotalToInt(str) {
                return parseInt(String(str || '').replace(/\s/g, ''), 10) || 0;
            }

            function paymentsMonthlyReportFormatTotalSpaces(n) {
                var v = Math.round(Number(n));
                if (isNaN(v)) {
                    return '0';
                }
                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function paymentsMonthlyReportAnimateTotalChange(prevText, nextText, nextRaw) {
                var $amount = $paymentsMonthlyReportTotalAmount;
                if (!$amount.length) {
                    return;
                }

                var nextVal = typeof nextRaw === 'number' && !isNaN(nextRaw)
                    ? Math.round(nextRaw)
                    : paymentsMonthlyReportParseTotalToInt(nextText);
                var prevVal = paymentsMonthlyReportParseTotalToInt(prevText);

                var runFlashAndPop = function () {
                    if ($paymentsMonthlyReportTotalStat.length) {
                        $paymentsMonthlyReportTotalStat.removeClass('payments-report-total-stat--flash');
                        void $paymentsMonthlyReportTotalStat[0].offsetWidth;
                        $paymentsMonthlyReportTotalStat.addClass('payments-report-total-stat--flash');
                    }
                    if ($paymentsMonthlyReportTotalValueInner.length) {
                        $paymentsMonthlyReportTotalValueInner.removeClass('payments-report-total-value-inner--pop');
                        void $paymentsMonthlyReportTotalValueInner[0].offsetWidth;
                        $paymentsMonthlyReportTotalValueInner.addClass('payments-report-total-value-inner--pop');
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
                    $amount.text(paymentsMonthlyReportFormatTotalSpaces(cur));
                    if (t < 1) {
                        window.requestAnimationFrame(step);
                    } else {
                        $amount.text(nextText);
                    }
                }

                runFlashAndPop();
                window.requestAnimationFrame(step);
            }

            function paymentsMonthlyFilterParams() {
                var uid = $payMonthlyFiltersForm.find('[name=\"filter_user_id\"]').val() || '';
                var tid = $payMonthlyFiltersForm.find('[name=\"filter_team_id\"]').val() || '';
                return {
                    mode: currentMode,
                    filter_user_id: uid,
                    filter_team_id: tid,
                    user_name: '',
                    team_title: '',
                    payment_month: $payMonthlyFiltersForm.find('[name=\"payment_month\"]').val() || '',
                    operation_date_from: $payMonthlyFiltersForm.find('[name=\"operation_date_from\"]').val() || '',
                    operation_date_to: $payMonthlyFiltersForm.find('[name=\"operation_date_to\"]').val() || '',
                    payment_provider: $payMonthlyFiltersForm.find('[name=\"payment_provider\"]').val() || ''
                };
            }

            function refreshPaymentsMonthlyReportTotal() {
                var prevText = $paymentsMonthlyReportTotalAmount.length ? $paymentsMonthlyReportTotalAmount.text() : '';
                if ($paymentsMonthlyReportTotalStat.length) {
                    $paymentsMonthlyReportTotalStat.addClass('payments-report-total-stat--loading');
                }
                $.get(@json(route('reports.payments.monthly.total')), paymentsMonthlyFilterParams())
                    .done(function (res) {
                        if ($paymentsMonthlyReportTotalStat.length) {
                            $paymentsMonthlyReportTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                        if (!res || res.total_formatted === undefined || !$paymentsMonthlyReportTotalAmount.length) {
                            return;
                        }
                        var nextText = res.total_formatted;
                        paymentsMonthlyReportAnimateTotalChange(prevText, nextText, res.total_raw);
                    })
                    .fail(function () {
                        if ($paymentsMonthlyReportTotalStat.length) {
                            $paymentsMonthlyReportTotalStat.removeClass('payments-report-total-stat--loading');
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

            initPaymentsReportFilterSelect2($payMonthlyFilterUser);
            initPaymentsReportFilterSelect2($payMonthlyFilterTeam);

            $payMonthlyFiltersForm.on('submit', function (e) {
                e.preventDefault();
                refreshPaymentsMonthlyReportTotal();
                monthlyTable.ajax.reload();
            });

            $('#paymentsMonthlyFiltersResetBtn').on('click', function () {
                $payMonthlyFiltersForm[0].reset();
                $payMonthlyFilterUser.val(null).trigger('change');
                $payMonthlyFilterTeam.val(null).trigger('change');
                currentMode = 'subscription';
                $('#payments-monthly-mode-hidden').val(currentMode);
                $('.js-group-mode-btn').removeClass('active');
                $('.js-group-mode-btn[data-mode=\"subscription\"]').addClass('active');
                refreshPaymentsMonthlyReportTotal();
                monthlyTable.ajax.reload();
            });

            var monthlyTable = $('#payments-monthly-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/admin/reports/payments/monthly/data',
                    type: 'GET',
                    data: function (d) {
                        var extra = paymentsMonthlyFilterParams();
                        Object.keys(extra).forEach(function (key) {
                            d[key] = extra[key];
                        });
                    }
                },
                columns: [{
                        data: null,
                        className: 'details-control text-center',
                        orderable: false,
                        searchable: false,
                        defaultContent: '<button type="button" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-chevron-down"></i></button>'
                    },
                    {
                        data: 'month_title',
                        name: 'month_title'
                    },
                    {
                        data: 'payments_count',
                        name: 'payments_count'
                    },
                    {
                        data: 'total_sum',
                        name: 'total_sum',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                var num = parseFloat(data || 0);
                                return num.toLocaleString('ru-RU') + ' руб';
                            }
                            return data;
                        }
                    },
                    {
                        data: 'month_key',
                        name: 'month_key',
                        visible: false,
                        searchable: false
                    }
                ],
                order: [
                    [1, 'desc']
                ],
                scrollX: true,
                language: {
                    "processing": "Обработка...",
                    "search": "",
                    "searchPlaceholder": "Поиск...",
                    "lengthMenu": "Показать _MENU_",
                    "info": "С _START_ до _END_ из _TOTAL_ записей",
                    "infoEmpty": "С 0 до 0 из 0 записей",
                    "infoFiltered": "(отфильтровано из _MAX_ записей)",
                    "loadingRecords": "Загрузка записей...",
                    "zeroRecords": "Записи отсутствуют.",
                    "emptyTable": "В таблице отсутствуют данные",
                    "paginate": {
                        "first": "",
                        "previous": "",
                        "next": "",
                        "last": ""
                    },
                    "aria": {
                        "sortAscending": ": активировать для сортировки столбца по возрастанию",
                        "sortDescending": ": активировать для сортировки столбца по убыванию"
                    }
                }
            });

            $('.payments-monthly-column-toggle').on('change', function () {
                var idx = parseInt($(this).data('column-index'), 10);
                if (isNaN(idx)) {
                    return;
                }
                var col = monthlyTable.column(idx);
                col.visible($(this).is(':checked'));
            });

            $('.js-group-mode-btn').on('click', function() {
                var btn = $(this);
                var mode = btn.data('mode');

                if (mode === currentMode) {
                    return;
                }

                currentMode = mode;
                $('#payments-monthly-mode-hidden').val(currentMode);

                $('.js-group-mode-btn').removeClass('active');
                btn.addClass('active');

                refreshPaymentsMonthlyReportTotal();
                monthlyTable.ajax.reload(null, true);
            });

            $('#payments-monthly-table tbody').on('click', 'td.details-control button', function(e) {
                e.stopPropagation();

                var btn = $(this);
                var tr = btn.closest('tr');
                var row = monthlyTable.row(tr);

                if (row.child.isShown()) {
                    row.child.hide();
                    tr.removeClass('shown');
                    btn.find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    return;
                }

                row.child('<div class="p-3 details-container">Загрузка...</div>').show();
                tr.addClass('shown');
                btn.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-up');

                var data = row.data();
                var monthKey = data.month_key;
                var monthText = data.month_title;

                var detailData = paymentsMonthlyFilterParams();

                $.ajax({
                    url: '/admin/reports/payments/monthly/' + monthKey + '/payments',
                    type: 'GET',
                    dataType: 'json',
                    data: detailData,
                    success: function(resp) {
                        var html = buildDetailsHtml(resp.payments || [], monthText,
                        currentMode);
                        tr.next('tr').find('div.details-container').replaceWith(html);
                    },
                    error: function() {
                        var html = '' +
                            '<div class="p-3 details-container bg-light border-start border-3 border-secondary">' +
                            '  <div class="fw-bold mb-2">' +
                            '    Платежи за месяц: ' + (monthText || '') +
                            '  </div>' +
                            '  <div class="text-danger">Ошибка загрузки данных.</div>' +
                            '</div>';
                        tr.next('tr').find('div.details-container').replaceWith(html);
                    }
                });
            });

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
                    1: 'Январь',
                    2: 'Февраль',
                    3: 'Март',
                    4: 'Апрель',
                    5: 'Май',
                    6: 'Июнь',
                    7: 'Июль',
                    8: 'Август',
                    9: 'Сентябрь',
                    10: 'Октябрь',
                    11: 'Ноябрь',
                    12: 'Декабрь'
                };

                var monthName = monthNames[monthNum] || parts[1];

                return monthName + ' ' + year;
            }

            function buildDetailsHtml(payments, monthTitle, mode) {
                var captionMode = (mode === 'operation') ?
                    'по дате платежа' :
                    'по месяцу абонемента';

                if (!payments || !payments.length) {
                    return '' +
                        '<div class="p-3 details-container bg-light border-start border-3 border-secondary">' +
                        '  <div class="fw-bold mb-2">' +
                        '    Платежи за месяц: ' + (monthTitle || '') +
                        '    <span class="text-muted small">(' + captionMode + ')</span>' +
                        '  </div>' +
                        '  <div class="text-muted small">Нет платежей за этот месяц.</div>' +
                        '</div>';
                }

                var totalSum = payments.reduce(function(acc, p) {
                    return acc + (parseFloat(p.summ || 0) || 0);
                }, 0);
                var totalSumFormatted = totalSum.toLocaleString('ru-RU');

                var html = '' +
                    '<div class="p-3 details-container bg-light border-start border-3 border-secondary">' +
                    '  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">' +
                    '    <div class="fw-bold">' +
                    '      Платежи за месяц: ' + (monthTitle || '') +
                    '      <span class="text-muted small">(' + captionMode + ')</span>' +
                    '    </div>' +
                    '    <div class="small text-muted">' +
                    '      Всего платежей: <b>' + payments.length + '</b>, ' +
                    '      на сумму <b>' + totalSumFormatted + ' руб</b>' +
                    '    </div>' +
                    '  </div>' +
                    '  <div class="table-responsive">' +
                    '    <table class="table table-sm table-bordered mb-0 align-middle">' +
                    '      <thead class="table-light small">' +
                    '        <tr>' +
                    '          <th style="width: 220px;">Дата и время платежа</th>' +
                    '          <th style="width: 180px;">ФИО</th>' +
                    '          <th style="width: 180px;">Группа</th>' +
                    '          <th style="width: 130px;" class="text-end">Сумма</th>' +
                    '          <th style="width: 160px;">Месяц абонемента</th>' +
                    '          <th style="width: 120px;">Провайдер</th>' +
                    '        </tr>' +
                    '      </thead>' +
                    '      <tbody>';

                payments.forEach(function(p) {
                    var amount = (parseFloat(p.summ || 0)).toLocaleString('ru-RU') + ' руб';

                    var providerLabel = '';
                    if (p.payment_provider === 'tbank') {
                        providerLabel = '<span class="badge" style="background-color:#ffdd2d !important; color:black !important;">T-Bank</span>';
                    } else if (p.payment_provider === 'robokassa') {
                        providerLabel = '<span class="badge bg-secondary">Robokassa</span>';
                    } else {
                        providerLabel = p.payment_provider || '';
                    }

                    var opDate = '';
                    if (p.operation_date) {
                        var d = new Date(p.operation_date);
                        if (!isNaN(d.getTime())) {
                            var day = ("0" + d.getDate()).slice(-2);
                            var month = ("0" + (d.getMonth() + 1)).slice(-2);
                            var year = d.getFullYear();
                            var hours = ("0" + d.getHours()).slice(-2);
                            var minutes = ("0" + d.getMinutes()).slice(-2);
                            opDate = day + '.' + month + '.' + year + ' / ' + hours + ':' + minutes;
                        } else {
                            opDate = p.operation_date;
                        }
                    }

                    var monthLabel = formatSubscriptionMonth(p.payment_month);

                    html += '' +
                        '<tr>' +
                        '  <td>' + opDate + '</td>' +
                        '  <td>' + (p.user_name || 'Без имени') + '</td>' +
                        '  <td>' + (p.team_title || 'Без команды') + '</td>' +
                        '  <td class="text-end">' + amount + '</td>' +
                        '  <td>' + monthLabel + '</td>' +
                        '  <td>' + providerLabel + '</td>' +
                        '</tr>';
                });

                html += '' +
                    '      </tbody>' +
                    '    </table>' +
                    '  </div>' +
                    '</div>';

                return html;
            }

        });
    </script>
@endsection
