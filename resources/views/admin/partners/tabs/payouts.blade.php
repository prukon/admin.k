        @vite(['resources/css/admin-list-toolbar.css'])

        <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Выплаты T‑Bank</h1>
                    <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                        @php
                            $tt = $toolbarTotals ?? [];
                            $ttPay = $tt['payments']['formatted'] ?? '0';
                            $ttPayout = $tt['payouts']['formatted'] ?? '0';
                            $ttPlat = $tt['platform_fee']['formatted'] ?? '0';
                        @endphp
                        <div class="d-flex flex-wrap align-items-end justify-content-end gap-3 gap-md-4" id="tbankPayoutsToolbarTotals">
                            <div class="payments-report-total-inline payments-report-total-stat text-end" id="tbankPayoutsPaymentsStat">
                                <div class="payments-report-total-label text-muted small mb-0">Сумма платежей</div>
                                <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                                    <span class="payments-report-total-value-inner">
                                        <span class="payments-report-total-amount">{{ $ttPay }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                                    </span>
                                </div>
                            </div>
                            <div class="payments-report-total-inline payments-report-total-stat text-end" id="tbankPayoutsPayoutsStat">
                                <div class="payments-report-total-label text-muted small mb-0">Сумма выплат</div>
                                <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                                    <span class="payments-report-total-value-inner">
                                        <span class="payments-report-total-amount">{{ $ttPayout }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                                    </span>
                                </div>
                            </div>
                            <div class="payments-report-total-inline payments-report-total-stat text-end" id="tbankPayoutsPlatformStat">
                                <div class="payments-report-total-label text-muted small mb-0">Комиссия платформы</div>
                                <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                                    <span class="payments-report-total-value-inner">
                                        <span class="payments-report-total-amount">{{ $ttPlat }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                                    </span>
                                </div>
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
                                            'payment' => 'Платёж',
                                            'id' => 'ID',
                                            'status' => 'Статус',
                                            'source' => 'Источник',
                                            'partner' => 'Партнёр',
                                            'legal_entity_organization' => 'Организация',
                                            'payer' => 'Плательщик',
                                            'initiator' => 'Инициатор',
                                            'provider_inv_id' => 'ID провайдера',
                                            'deal_id' => 'DealId',
                                            'gross' => 'Сумма платежа',
                                            'bank_accept_fee' => 'Комиссия оплаты',
                                            'bank_payout_fee' => 'Комиссия выплаты',
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

        <style>
            #payouts-table .pay-cell-datetime--payouts {
                flex-direction: row;
                align-items: baseline;
                flex-wrap: wrap;
                gap: 0.35rem;
            }
        </style>

        <div class="table-responsive">
            <table id="payouts-table" class="table table-striped table-bordered align-middle w-100 dt-columns-managed">
                <thead>
                <tr>
                    <th>Платёж</th>
                    <th>ID</th>
                    <th>Статус</th>
                    <th>Источник</th>
                    <th>Партнёр</th>
                    <th>Организация</th>
                    <th>Плательщик</th>
                    <th>Инициатор</th>
                    <th>ID провайдера</th>
                    <th>DealId</th>
                    <th>Сумма платежа</th>
                    <th>Комиссия оплаты</th>
                    <th>Комиссия выплаты</th>
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

@push('scripts')
    <script>
        $(document).ready(function () {
            var $filtersForm = $('#tbank-payouts-filters');
            var $toolbarTotalsRoot = $('#tbankPayoutsToolbarTotals');
            var $statPayments = $('#tbankPayoutsPaymentsStat');
            var $statPayouts = $('#tbankPayoutsPayoutsStat');
            var $statPlatform = $('#tbankPayoutsPlatformStat');
            var $filterPartner = $('#filter-partner');
            var $filterPayer = $('#filter-payer');

            function initSelect2($el, extraParamsFn) {
                var url = $el.data('search-url');
                if (!$el.length || !url) return;
                $el.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $el.data('placeholder') || '',
                    language: @include('partials.select2.ru'),
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

            function animateToolbarStat($statRoot, prevText, nextText, nextRaw) {
                var $amount = $statRoot.find('.payments-report-total-amount');
                if (!$amount.length) return;

                var nextVal = typeof nextRaw === 'number' && !isNaN(nextRaw) ? Math.round(nextRaw) : parseTotalToInt(nextText);
                var prevVal = parseTotalToInt(prevText);

                var runFlashAndPop = function () {
                    if ($statRoot.length) {
                        $statRoot.removeClass('payments-report-total-stat--flash');
                        void $statRoot[0].offsetWidth;
                        $statRoot.addClass('payments-report-total-stat--flash');
                    }
                    var $valueInner = $statRoot.find('.payments-report-total-value-inner');
                    if ($valueInner.length) {
                        $valueInner.removeClass('payments-report-total-value-inner--pop');
                        void $valueInner[0].offsetWidth;
                        $valueInner.addClass('payments-report-total-value-inner--pop');
                    }
                };

                var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (prefersReduced || prevText === nextText) {
                    $amount.text(nextText);
                    if (!prefersReduced && prevText !== nextText) runFlashAndPop();
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
                    if (start === null) start = ts;
                    var elapsed = ts - start;
                    var t = Math.min(1, elapsed / duration);
                    var eased = easeInOutQuad(t);
                    var cur = Math.round(prevVal + (nextVal - prevVal) * eased);
                    $amount.text(formatTotalSpaces(cur));
                    if (t < 1) window.requestAnimationFrame(step);
                    else $amount.text(nextText);
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

            /** Дата ДД.ММ.ГГГГ; время — как в ответе API (без добавления секунд). */
            function renderPayoutListDatetime(data, type) {
                if (!data) {
                    return data;
                }
                if (type !== 'display') {
                    return data;
                }
                var s = String(data).trim();
                var pm = s.match(/^(\d{2}\.\d{2}\.\d{4})\s+(.+)$/);
                if (pm) {
                    var dateLine = pm[1];
                    var timeLine = pm[2];
                    return (
                        '<div class="pay-cell-datetime pay-cell-datetime--payouts" role="text" aria-label="' +
                        dateLine + ', ' + timeLine + '">' +
                        '<span class="pay-cell-datetime__date">' + dateLine + '</span>' +
                        '<span class="pay-cell-datetime__time">' + timeLine + '</span>' +
                        '</div>'
                    );
                }
                return renderPayCellDatetime(data, type);
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
                var prevPay = $statPayments.find('.payments-report-total-amount').text();
                var prevPayout = $statPayouts.find('.payments-report-total-amount').text();
                var prevPlat = $statPlatform.find('.payments-report-total-amount').text();
                if ($toolbarTotalsRoot.length) {
                    $toolbarTotalsRoot.find('.payments-report-total-stat').addClass('payments-report-total-stat--loading');
                }
                $.get('/admin/tinkoff/payouts/total', filterParams())
                    .done(function (res) {
                        if ($toolbarTotalsRoot.length) {
                            $toolbarTotalsRoot.find('.payments-report-total-stat').removeClass('payments-report-total-stat--loading');
                        }
                        if (!res || res.payments_total_formatted === undefined) return;
                        animateToolbarStat($statPayments, prevPay, res.payments_total_formatted, res.payments_total_raw);
                        animateToolbarStat($statPayouts, prevPayout, res.payouts_total_formatted, res.payouts_total_raw);
                        animateToolbarStat($statPlatform, prevPlat, res.platform_fee_total_formatted, res.platform_fee_total_raw);
                    })
                    .fail(function () {
                        if ($toolbarTotalsRoot.length) {
                            $toolbarTotalsRoot.find('.payments-report-total-stat').removeClass('payments-report-total-stat--loading');
                        }
                    });
            }

            const dtApi = KidsCrmDataTable.create('#payouts-table', {
                columnsSettings: {
                    defaults: {
                        payment: true,
                        id: true,
                        status: true,
                        source: true,
                        partner: true,
                        legal_entity_organization: true,
                        payer: true,
                        initiator: true,
                        provider_inv_id: true,
                        deal_id: true,
                        gross: true,
                        bank_accept_fee: true,
                        bank_payout_fee: true,
                        platform_fee: true,
                        net: true,
                        when_to_run: true,
                        created_at: true,
                        completed_at: true,
                        tinkoff_payout_payment_id: true,
                        actions: true,
                    },
                    urls: {
                        get: '/admin/tinkoff/payouts/columns-settings',
                        save: '/admin/tinkoff/payouts/columns-settings',
                    },
                    csrfToken: '{{ csrf_token() }}',
                },
                dataTable: {
                    order: [[1, 'desc']],
                    ajax: {
                        url: '/admin/tinkoff/payouts/data',
                        type: 'GET',
                        data: function (d) {
                            var extra = filterParams();
                            Object.keys(extra).forEach(function (k) {
                                d[k] = extra[k];
                            });
                        },
                    },
                    language: @include('partials.datatables.ru'),
                },
                columns: [
                    {
                        key: 'payment',
                        type: 'link',
                        data: 'payment_id',
                        name: 'payment_id',
                        className: 'dt-col-id text-center',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return row.payment_id || '';
                            }

                            if (!row.payment_id) {
                                return '<span class="dt-cell-empty text-muted">—</span>';
                            }

                            return '<a href="/admin/tinkoff/payments/' + row.payment_id + '">#' + row.payment_id + '</a>';
                        },
                    },
                    { key: 'id', type: 'id', data: 'id' },
                    {
                        key: 'status',
                        type: 'badge',
                        data: 'status',
                        name: 'status',
                        className: 'dt-col-badge text-center',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data || '';
                            }

                            var cls = 'bg-secondary';
                            if (data === 'COMPLETED') cls = 'bg-success';
                            if (data === 'REJECTED') cls = 'bg-danger';
                            return '<span class="badge ' + cls + '">' + data + '</span>';
                        },
                    },
                    {
                        key: 'source',
                        type: 'badge',
                        data: 'source',
                        name: 'source',
                        className: 'dt-col-badge text-center',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data || '';
                            }

                            var label = data || '—';
                            return '<span class="badge bg-light text-dark border">' + label + '</span>';
                        },
                    },
                    { key: 'partner', type: 'text', data: 'partner', name: 'partner' },
                    {
                        key: 'legal_entity_organization',
                        type: 'text',
                        data: 'legal_entity_organization',
                        name: 'legal_entity_organization',
                        className: 'dt-col-text dt-col-text--wrap',
                    },
                    { key: 'payer', type: 'text', data: 'payer', name: 'payer' },
                    { key: 'initiator', type: 'text', data: 'initiator', name: 'initiator' },
                    {
                        key: 'provider_inv_id',
                        type: 'text',
                        data: 'provider_inv_id',
                        name: 'provider_inv_id',
                        className: 'dt-col-count text-center tabular-nums',
                    },
                    { key: 'deal_id', type: 'text', data: 'deal_id', name: 'deal_id' },
                    { key: 'gross', type: 'text', data: 'gross', name: 'gross', className: 'dt-col-count text-end' },
                    { key: 'bank_accept_fee', type: 'text', data: 'bank_accept_fee', name: 'bank_accept_fee', className: 'dt-col-count text-end' },
                    { key: 'bank_payout_fee', type: 'text', data: 'bank_payout_fee', name: 'bank_payout_fee', className: 'dt-col-count text-end' },
                    { key: 'platform_fee', type: 'text', data: 'platform_fee', name: 'platform_fee', className: 'dt-col-count text-end' },
                    { key: 'net', type: 'text', data: 'net', name: 'net', className: 'dt-col-count text-end' },
                    {
                        key: 'when_to_run',
                        type: 'datetime',
                        data: 'when_to_run',
                        name: 'when_to_run',
                        className: 'dt-col-text dt-col-text--wrap',
                        render: renderPayoutListDatetime,
                    },
                    {
                        key: 'created_at',
                        type: 'datetime',
                        data: 'created_at',
                        name: 'created_at',
                        className: 'dt-col-text dt-col-text--wrap',
                        render: renderPayoutListDatetime,
                    },
                    {
                        key: 'completed_at',
                        type: 'datetime',
                        data: 'completed_at',
                        name: 'completed_at',
                        className: 'dt-col-text dt-col-text--wrap',
                        render: renderPayoutListDatetime,
                    },
                    { key: 'tinkoff_payout_payment_id', type: 'text', data: 'tinkoff_payout_payment_id', name: 'tinkoff_payout_payment_id' },
                    {
                        key: 'actions',
                        type: 'actions',
                        className: 'text-end text-nowrap',
                        render: function (data, type, row) {
                            return '<a class="btn btn-sm btn-outline-primary" href="/admin/tinkoff/payouts/' + row.id + '">Карточка</a>';
                        },
                    },
                ],
            });

            const table = dtApi.table;

            $filtersForm.on('submit', function (e) {
                e.preventDefault();
                refreshTotal();
                dtApi.reload({ keepPage: true });
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
                dtApi.reload({ keepPage: true });
            });
        });
    </script>
@endpush
