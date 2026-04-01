@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        @vite(['resources/css/payments-report.css'])

        <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Выплаты T‑Bank</h1>
                    <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                        <div class="payments-report-total-inline payments-report-total-stat text-end" id="tbankPayoutsTotalStat">
                            <div class="payments-report-total-label text-muted small mb-0">Общая сумма</div>
                            <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                                <span class="payments-report-total-value-inner">
                                    <span class="payments-report-total-amount">{{ $totalPayoutAmountFormatted ?? '0' }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                                </span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                            <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#tbankPayoutsFiltersCollapse"
                                    aria-expanded="false"
                                    aria-controls="tbankPayoutsFiltersCollapse"
                                    id="tbankPayoutsFiltersToggle">
                                <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                                </span>
                                <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                                <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                            </button>

                            <div class="dropdown payments-report-toolbar-dropdown">
                                <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                        type="button"
                                        id="columnsDropdown"
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
                                     aria-labelledby="columnsDropdown">
                                    <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>
                                    @php
                                        $cols = [
                                            'id' => 'ID',
                                            'status' => 'Статус',
                                            'source' => 'Источник',
                                            'partner' => 'Партнёр',
                                            'payer' => 'Плательщик',
                                            'initiator' => 'Инициатор',
                                            'payment' => 'Платёж',
                                            'deal_id' => 'DealId',
                                            'gross' => 'Сумма платежа',
                                            'bank_fee' => 'Комиссия банка',
                                            'platform_fee' => 'Комиссия Платформы',
                                            'net' => 'Сумма выплаты',
                                            'when_to_run' => 'Запланирована',
                                            'created_at' => 'Создана',
                                            'completed_at' => 'Завершена',
                                            'tinkoff_payout_payment_id' => 'T‑Bank payout PaymentId',
                                            'actions' => 'Действия',
                                        ];
                                    @endphp
                                    @foreach($cols as $key => $label)
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="{{ $key }}"
                                                   id="col_{{ $key }}"
                                                   checked>
                                            <label class="form-check-label" for="col_{{ $key }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-light border mb-3 py-2">
            <div class="small">
                <strong>Автовыплаты:</strong>
                @if($partnersWithAuto->isNotEmpty())
                    включены у партнёров
                    @foreach($partnersWithAuto as $pa)
                        <span class="badge text-bg-success">{{ $pa->title }}</span>
                    @endforeach
                @else
                    <span class="text-muted">ни у кого не включены</span>
                @endif
            </div>
            <div class="small text-muted mt-1">
                Обработка отложенных выплат: каждые {{ $scheduledIntervalMinutes }} мин.
                <span class="d-block mt-1">
                    Отложенные выплаты не лежат в таблице <code>jobs</code> до срока: их подбирает джоба по расписанию (cron → <code>schedule:run</code> → очередь).
                    Если срок прошёл, а статус всё ещё <code>INITIATED</code>, проверьте cron и queue worker.
                </span>
            </div>
        </div>

        @if(!empty($overdueScheduledPayoutsCount))
            <div class="alert alert-warning border mb-3">
                <div class="fw-semibold mb-2">
                    Просроченные отложенные выплаты: {{ $overdueScheduledPayoutsCount }}
                </div>
                <div class="small text-muted mb-2">
                    Условие: <code>when_to_run</code> уже наступил, статус <code>INITIATED</code>, нет <code>tinkoff_payout_payment_id</code>.
                    Ожидается запуск <code>TinkoffRunScheduledPayoutsJob</code> (планировщик + worker).
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 bg-white">
                        <thead>
                        <tr>
                            <th>ID</th>
                            @if(!empty($isSuperadmin))
                                <th>Партнёр</th>
                            @endif
                            <th>Запланировано на</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($overdueScheduledPayouts as $op)
                            <tr>
                                <td>{{ $op->id }}</td>
                                @if(!empty($isSuperadmin))
                                    <td>{{ $op->partner?->title ?? ('#' . $op->partner_id) }}</td>
                                @endif
                                <td>{{ $op->when_to_run?->format('Y-m-d H:i') }}</td>
                                <td class="text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ url('/admin/tinkoff/payouts/' . $op->id) }}">Карточка</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if($overdueScheduledPayoutsCount > $overdueScheduledPayouts->count())
                    <div class="small text-muted mt-2">Показаны первые {{ $overdueScheduledPayouts->count() }} записей.</div>
                @endif
            </div>
        @endif

        <div class="collapse mb-2 mb-md-3" id="tbankPayoutsFiltersCollapse">
            <form id="tbank-payouts-filters" class="border rounded p-2 p-md-3 bg-light">
                <div class="row g-2 align-items-end">
                    @if(!empty($isSuperadmin))
                        <div class="col-12 col-md-3 col-lg-2">
                            <label class="form-label" for="filter-partner">Партнёр</label>
                            <select id="filter-partner"
                                    class="form-select payments-report-filter-select2"
                                    data-placeholder="Все партнёры"
                                    data-search-url="/admin/tinkoff/payouts/partners-search">
                                <option value=""></option>
                            </select>
                        </div>
                    @endif

                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-status">Статус</label>
                        <select id="filter-status" class="form-select">
                            <option value="">Все статусы</option>
                            <option value="INITIATED">INITIATED</option>
                            <option value="NEW">NEW</option>
                            <option value="AUTHORIZING">AUTHORIZING</option>
                            <option value="CHECKING">CHECKING</option>
                            <option value="CREDIT_CHECKING">CREDIT_CHECKING</option>
                            <option value="CHECKED">CHECKED</option>
                            <option value="COMPLETING">COMPLETING</option>
                            <option value="COMPLETED">COMPLETED</option>
                            <option value="REJECTED">REJECTED</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-source">Источник</label>
                        <select id="filter-source" class="form-select">
                            <option value="">Любой</option>
                            <option value="auto">auto</option>
                            <option value="manual">manual</option>
                            <option value="delayed">delayed</option>
                            <option value="scheduled">scheduled</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3 col-lg-3">
                        <label class="form-label" for="filter-payer">Плательщик</label>
                        <select id="filter-payer"
                                class="form-select payments-report-filter-select2"
                                data-placeholder="Все плательщики"
                                data-search-url="/admin/tinkoff/payouts/payers-search">
                            <option value=""></option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 col-lg-3">
                        <label class="form-label" for="filter-initiator">Инициатор</label>
                        <input id="filter-initiator" class="form-control" type="text" placeholder="id/ФИО/тел/email">
                    </div>

                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-created-from">Создана: с</label>
                        <input id="filter-created-from" class="form-control" type="date">
                    </div>
                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-created-to">Создана: по</label>
                        <input id="filter-created-to" class="form-control" type="date">
                    </div>
                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-run-from">Запланирована: с</label>
                        <input id="filter-run-from" class="form-control" type="date">
                    </div>
                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-run-to">Запланирована: по</label>
                        <input id="filter-run-to" class="form-control" type="date">
                    </div>
                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-completed-from">Завершена: с</label>
                        <input id="filter-completed-from" class="form-control" type="date">
                    </div>
                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-completed-to">Завершена: по</label>
                        <input id="filter-completed-to" class="form-control" type="date">
                    </div>

                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-gross-min">Сумма платежа от (₽)</label>
                        <input id="filter-gross-min" class="form-control" type="number" step="0.01" min="0">
                    </div>
                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-gross-max">Сумма платежа до (₽)</label>
                        <input id="filter-gross-max" class="form-control" type="number" step="0.01" min="0">
                    </div>
                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-net-min">Сумма выплаты от (₽)</label>
                        <input id="filter-net-min" class="form-control" type="number" step="0.01" min="0">
                    </div>
                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-net-max">Сумма выплаты до (₽)</label>
                        <input id="filter-net-max" class="form-control" type="number" step="0.01" min="0">
                    </div>

                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-deal-id">DealId</label>
                        <input id="filter-deal-id" class="form-control" type="text">
                    </div>
                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-tinkoff-payment-id">ID платежа (в системе)</label>
                        <input id="filter-tinkoff-payment-id" class="form-control" type="number" min="1">
                    </div>
                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label" for="filter-payout-payment-id">T‑Bank payout PaymentId</label>
                        <input id="filter-payout-payment-id" class="form-control" type="text">
                    </div>

                    <div class="col-12 col-md-3 col-lg-3 d-flex align-items-end gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="filter-stuck-only">
                            <label class="form-check-label" for="filter-stuck-only">Застрявшие</label>
                        </div>
                        <input id="filter-stuck-minutes" class="form-control" type="number" min="1" value="60" title="Сколько минут без обновления">
                    </div>

                    <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                        <button class="btn btn-primary payments-report-filters-submit" type="submit" id="filter-apply">Применить</button>
                        <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="filter-reset">Сброс</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table id="payouts-table" class="table table-striped table-bordered align-middle w-100">
                <thead>
                <tr>
                    <th>#</th>
                    <th>ID</th>
                    <th>Статус</th>
                    <th>Источник</th>
                    <th>Партнёр</th>
                    <th>Плательщик</th>
                    <th>Инициатор</th>
                    <th>Платёж</th>
                    <th>DealId</th>
                    <th>Сумма платежа</th>
                    <th>Комиссия банка</th>
                    <th>Комиссия Платформы</th>
                    <th>Сумма выплаты</th>
                    <th>Запланирована</th>
                    <th>Создана</th>
                    <th>Завершена</th>
                    <th>T‑Bank payout PaymentId</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function () {
            var $filtersForm = $('#tbank-payouts-filters');
            var $totalAmount = $('#tbankPayoutsTotalStat .payments-report-total-amount');
            var $totalStat = $('#tbankPayoutsTotalStat');
            var $totalValueInner = $('#tbankPayoutsTotalStat .payments-report-total-value-inner');
            var $filterPartner = $('#filter-partner');
            var $filterPayer = $('#filter-payer');

            function initSelect2($el, extraParamsFn) {
                var url = $el.data('search-url');
                if (!$el.length || !url) return;
                $el.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $el.data('placeholder') || '',
                    allowClear: true,
                    ajax: {
                        url: url,
                        delay: 250,
                        data: function (params) {
                            var payload = {q: params.term || ''};
                            if (typeof extraParamsFn === 'function') {
                                var extra = extraParamsFn() || {};
                                Object.keys(extra).forEach(function (k) { payload[k] = extra[k]; });
                            }
                            return payload;
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 0
                });
            }

            @if(!empty($isSuperadmin))
            initSelect2($filterPartner);
            @endif
            initSelect2($filterPayer, function () {
                var pid = '';
                @if(!empty($isSuperadmin))
                pid = $filterPartner.val() || '';
                @endif
                return {partner_id: pid};
            });

            function parseTotalToInt(str) {
                return parseInt(String(str || '').replace(/\s/g, ''), 10) || 0;
            }

            function formatTotalSpaces(n) {
                var v = Math.round(Number(n));
                if (isNaN(v)) return '0';
                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function animateTotalChange(prevText, nextText, nextRaw) {
                if (!$totalAmount.length) return;

                var nextVal = typeof nextRaw === 'number' && !isNaN(nextRaw) ? Math.round(nextRaw) : parseTotalToInt(nextText);
                var prevVal = parseTotalToInt(prevText);

                var runFlashAndPop = function () {
                    if ($totalStat.length) {
                        $totalStat.removeClass('payments-report-total-stat--flash');
                        void $totalStat[0].offsetWidth;
                        $totalStat.addClass('payments-report-total-stat--flash');
                    }
                    if ($totalValueInner.length) {
                        $totalValueInner.removeClass('payments-report-total-value-inner--pop');
                        void $totalValueInner[0].offsetWidth;
                        $totalValueInner.addClass('payments-report-total-value-inner--pop');
                    }
                };

                var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (prefersReduced || prevText === nextText) {
                    $totalAmount.text(nextText);
                    if (!prefersReduced && prevText !== nextText) runFlashAndPop();
                    return;
                }

                if (prevVal === nextVal) {
                    $totalAmount.text(nextText);
                    runFlashAndPop();
                    return;
                }

                var duration = 480;
                var start = null;

                function easeInOutQuad(t) {
                    return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                }

                function step(ts) {
                    if (start === null) start = ts;
                    var elapsed = ts - start;
                    var t = Math.min(1, elapsed / duration);
                    var eased = easeInOutQuad(t);
                    var cur = Math.round(prevVal + (nextVal - prevVal) * eased);
                    $totalAmount.text(formatTotalSpaces(cur));
                    if (t < 1) window.requestAnimationFrame(step);
                    else $totalAmount.text(nextText);
                }

                runFlashAndPop();
                window.requestAnimationFrame(step);
            }

            function renderPayCellDatetime(data, type) {
                if (!data) {
                    return data;
                }
                if (type !== 'display') {
                    return data;
                }
                var date = null;
                var parsed = new Date(data);
                if (!isNaN(parsed.getTime())) {
                    date = parsed;
                } else {
                    // Бэкенд отдаёт Carbon::format('d.m.Y H:i') — браузер часто не парсит это в Date.
                    var m = String(data).trim().match(/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})(?::(\d{2}))?$/);
                    if (m) {
                        date = new Date(
                            parseInt(m[3], 10),
                            parseInt(m[2], 10) - 1,
                            parseInt(m[1], 10),
                            parseInt(m[4], 10),
                            parseInt(m[5], 10),
                            m[6] !== undefined ? parseInt(m[6], 10) : 0
                        );
                    }
                }
                if (!date || isNaN(date.getTime())) {
                    return data;
                }
                var day = ("0" + date.getDate()).slice(-2);
                var month = ("0" + (date.getMonth() + 1)).slice(-2);
                var year = date.getFullYear();
                var hours = ("0" + date.getHours()).slice(-2);
                var minutes = ("0" + date.getMinutes()).slice(-2);
                var seconds = ("0" + date.getSeconds()).slice(-2);
                var dateLine = day + '.' + month + '.' + year;
                var timeLine = hours + ':' + minutes + ':' + seconds;
                return (
                    '<div class="pay-cell-datetime" role="text" aria-label="' +
                    dateLine + ', ' + timeLine + '">' +
                    '<span class="pay-cell-datetime__date">' + dateLine + '</span>' +
                    '<span class="pay-cell-datetime__time">' + timeLine + '</span>' +
                    '</div>'
                );
            }

            function filterParams() {
                var p = {};
                @if(!empty($isSuperadmin))
                p.partner_id = $filterPartner.val() || '';
                @endif
                p.status = $('#filter-status').val() || '';
                p.source = $('#filter-source').val() || '';
                p.payer_id = $filterPayer.val() || '';
                p.payer_query = '';
                p.initiator_query = $('#filter-initiator').val() || '';
                p.created_from = $('#filter-created-from').val() || '';
                p.created_to = $('#filter-created-to').val() || '';
                p.run_from = $('#filter-run-from').val() || '';
                p.run_to = $('#filter-run-to').val() || '';
                p.completed_from = $('#filter-completed-from').val() || '';
                p.completed_to = $('#filter-completed-to').val() || '';
                p.gross_min = $('#filter-gross-min').val() || '';
                p.gross_max = $('#filter-gross-max').val() || '';
                p.net_min = $('#filter-net-min').val() || '';
                p.net_max = $('#filter-net-max').val() || '';
                p.deal_id = $('#filter-deal-id').val() || '';
                p.tinkoff_payment_id = $('#filter-tinkoff-payment-id').val() || '';
                p.tinkoff_payout_payment_id = $('#filter-payout-payment-id').val() || '';
                p.stuck_only = $('#filter-stuck-only').is(':checked') ? 1 : 0;
                p.stuck_minutes = $('#filter-stuck-minutes').val() || '';
                return p;
            }

            function refreshTotal() {
                var prevText = $totalAmount.length ? $totalAmount.text() : '';
                if ($totalStat.length) $totalStat.addClass('payments-report-total-stat--loading');
                $.get('/admin/tinkoff/payouts/total', filterParams())
                    .done(function (res) {
                        if ($totalStat.length) $totalStat.removeClass('payments-report-total-stat--loading');
                        if (!res || res.total_formatted === undefined || !$totalAmount.length) return;
                        animateTotalChange(prevText, res.total_formatted, res.total_raw);
                    })
                    .fail(function () {
                        if ($totalStat.length) $totalStat.removeClass('payments-report-total-stat--loading');
                    });
            }

            const defaultColumnsVisibility = {
                id: true,
                status: true,
                source: true,
                partner: true,
                payer: true,
                initiator: true,
                payment: true,
                deal_id: true,
                gross: true,
                bank_fee: true,
                platform_fee: true,
                net: true,
                when_to_run: true,
                created_at: true,
                completed_at: true,
                tinkoff_payout_payment_id: true,
                actions: true
            };

            let currentColumnsConfig = {...defaultColumnsVisibility};

            // 0 – нумерация (всегда видна)
            const columnsMap = {
                id: 1,
                status: 2,
                source: 3,
                partner: 4,
                payer: 5,
                initiator: 6,
                payment: 7,
                deal_id: 8,
                gross: 9,
                bank_fee: 10,
                platform_fee: 11,
                net: 12,
                when_to_run: 13,
                created_at: 14,
                completed_at: 15,
                tinkoff_payout_payment_id: 16,
                actions: 17
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

            // --- DataTables ---
            const table = $('#payouts-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 20,
                lengthMenu: [10, 20, 50, 100],
                order: [[1, 'desc']],
                ajax: {
                    url: '/admin/tinkoff/payouts/data',
                    type: 'GET',
                    data: function (d) {
                        var extra = filterParams();
                        Object.keys(extra).forEach(function (k) {
                            d[k] = extra[k];
                        });
                    }
                },
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
                },
                columns: [
                    {
                        data: null,
                        name: 'rownum',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    {data: 'id', name: 'id', className: 'text-center'},
                    {
                        data: 'status',
                        name: 'status',
                        render: function (data) {
                            let cls = 'bg-secondary';
                            if (data === 'COMPLETED') cls = 'bg-success';
                            if (data === 'REJECTED') cls = 'bg-danger';
                            return '<span class="badge ' + cls + '">' + data + '</span>';
                        }
                    },
                    {
                        data: 'source',
                        name: 'source',
                        render: function (data) {
                            const label = data || '—';
                            return '<span class="badge bg-light text-dark border">' + label + '</span>';
                        }
                    },
                    {data: 'partner', name: 'partner', defaultContent: ''},
                    {data: 'payer', name: 'payer', defaultContent: ''},
                    {data: 'initiator', name: 'initiator', defaultContent: ''},
                    {
                        data: 'payment_id',
                        name: 'payment_id',
                        className: 'text-center',
                        render: function (data) {
                            if (!data) return '—';
                            return '<a href="/admin/tinkoff/payments/' + data + '" target="_blank">#' + data + '</a>';
                        }
                    },
                    {data: 'deal_id', name: 'deal_id', defaultContent: ''},
                    {data: 'gross', name: 'gross', className: 'text-end', defaultContent: ''},
                    {data: 'bank_fee', name: 'bank_fee', className: 'text-end', defaultContent: ''},
                    {data: 'platform_fee', name: 'platform_fee', className: 'text-end', defaultContent: ''},
                    {data: 'net', name: 'net', className: 'text-end', defaultContent: ''},
                    {
                        data: 'when_to_run',
                        name: 'when_to_run',
                        className: 'text-nowrap',
                        defaultContent: '',
                        render: renderPayCellDatetime
                    },
                    {
                        data: 'created_at',
                        name: 'created_at',
                        className: 'text-nowrap',
                        defaultContent: '',
                        render: renderPayCellDatetime
                    },
                    {
                        data: 'completed_at',
                        name: 'completed_at',
                        className: 'text-nowrap',
                        defaultContent: '',
                        render: renderPayCellDatetime
                    },
                    {data: 'tinkoff_payout_payment_id', name: 'tinkoff_payout_payment_id', defaultContent: ''},
                    {
                        data: null,
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-end text-nowrap',
                        render: function (data, type, row) {
                            return '<a class="btn btn-sm btn-outline-primary" href="/admin/tinkoff/payouts/' + row.id + '">Карточка</a>';
                        }
                    }
                ]
            });

            function applyVisibleColumns(config) {
                Object.keys(columnsMap).forEach(function (key) {
                    const colIndex = columnsMap[key];
                    const column = table.column(colIndex);
                    const isVisible = toBool(config[key], defaultColumnsVisibility[key]);
                    column.visible(isVisible, false);
                    $('.column-toggle[data-column-key="' + key + '"]').prop('checked', isVisible);
                });
                table.columns.adjust().draw(false);
            }

            function loadColumnsConfigFromServer() {
                $.ajax({
                    url: '/admin/tinkoff/payouts/columns-settings',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        const merged = {};
                        Object.keys(defaultColumnsVisibility).forEach(function (key) {
                            merged[key] = toBool(
                                response.hasOwnProperty(key) ? response[key] : defaultColumnsVisibility[key],
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

            function saveColumnsConfigToServer(config) {
                $.ajax({
                    url: '/admin/tinkoff/payouts/columns-settings',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _token: '{{ csrf_token() }}',
                        columns: config
                    }
                });
            }

            loadColumnsConfigFromServer();

            $('.column-toggle').on('change', function () {
                const key = $(this).data('column-key');
                const val = $(this).is(':checked');
                currentColumnsConfig[key] = val;
                applyVisibleColumns(currentColumnsConfig);
                saveColumnsConfigToServer(currentColumnsConfig);
            });

            $filtersForm.on('submit', function (e) {
                e.preventDefault();
                refreshTotal();
                table.ajax.reload();
            });

            $('#filter-reset').on('click', function () {
                @if(!empty($isSuperadmin))
                $filterPartner.val(null).trigger('change');
                @endif
                $('#filter-status').val('');
                $('#filter-source').val('');
                $filterPayer.val(null).trigger('change');
                $('#filter-initiator').val('');
                $('#filter-created-from').val('');
                $('#filter-created-to').val('');
                $('#filter-run-from').val('');
                $('#filter-run-to').val('');
                $('#filter-completed-from').val('');
                $('#filter-completed-to').val('');
                $('#filter-gross-min').val('');
                $('#filter-gross-max').val('');
                $('#filter-net-min').val('');
                $('#filter-net-max').val('');
                $('#filter-stuck-only').prop('checked', false);
                $('#filter-stuck-minutes').val('60');
                $('#filter-deal-id').val('');
                $('#filter-tinkoff-payment-id').val('');
                $('#filter-payout-payment-id').val('');
                refreshTotal();
                table.ajax.reload();
            });
        });
    </script>
@endsection

