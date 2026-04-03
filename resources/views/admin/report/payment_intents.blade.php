@php
    $piFilterKeys = ['inv_id', 'partner_id', 'user_id', 'partner_title', 'user_name', 'provider', 'status', 'created_from', 'created_to', 'paid_from', 'paid_to'];
    $piHasActiveFilters = false;
    foreach ($piFilterKeys as $k) {
        $v = $filters[$k] ?? null;
        if ($v !== null && $v !== '') {
            $piHasActiveFilters = true;
            break;
        }
    }
@endphp
@vite(['resources/css/payments-report.css'])

<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
    <div class="card-body px-3 py-3">
        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Платежные запросы</h1>
            <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                <div class="payments-report-total-inline payments-report-total-stat text-end" id="paymentIntentsReportTotalStat">
                    <div class="payments-report-total-label text-muted small mb-0">Общая сумма</div>
                    <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                        <span class="payments-report-total-value-inner">
                            <span class="payments-report-total-amount">{{ $totalPaidPrice ?? '0' }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                    <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#paymentIntentsFiltersCollapse"
                            aria-expanded="{{ $piHasActiveFilters ? 'true' : 'false' }}"
                            aria-controls="paymentIntentsFiltersCollapse"
                            id="paymentIntentsFiltersToggle">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="dropdown payments-report-toolbar-dropdown">
                        <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                id="columnsDropdownPaymentIntents"
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
                             aria-labelledby="columnsDropdownPaymentIntents">
                            <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColId" data-column-key="id" data-column-index="0" checked>
                                <label class="form-check-label" for="piColId">№</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColProviderInvId" data-column-key="provider_inv_id" data-column-index="1" checked>
                                <label class="form-check-label" for="piColProviderInvId">ID провайдера</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColPartner" data-column-key="partner" data-column-index="2" checked>
                                <label class="form-check-label" for="piColPartner">Партнер</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColUser" data-column-key="user" data-column-index="3" checked>
                                <label class="form-check-label" for="piColUser">Пользователь</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColProvider" data-column-key="provider" data-column-index="4" checked>
                                <label class="form-check-label" for="piColProvider">Провайдер</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColMethod" data-column-key="payment_method_webhook_label" data-column-index="5" checked>
                                <label class="form-check-label" for="piColMethod">Способ оплаты</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColStatus" data-column-key="status" data-column-index="6" checked>
                                <label class="form-check-label" for="piColStatus">Статус</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColOutSum" data-column-key="out_sum" data-column-index="7" checked>
                                <label class="form-check-label" for="piColOutSum">Сумма платежа</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColPaymentDate" data-column-key="payment_date" data-column-index="8" checked>
                                <label class="form-check-label" for="piColPaymentDate">Оплаченный месяц</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColCreatedAt" data-column-key="created_at" data-column-index="9" checked>
                                <label class="form-check-label" for="piColCreatedAt">Создано</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColPaidAt" data-column-key="paid_at" data-column-index="10" checked>
                                <label class="form-check-label" for="piColPaidAt">Дата оплаты</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColDeviceType" data-column-key="client_device_type" data-column-index="11" checked>
                                <label class="form-check-label" for="piColDeviceType">Тип устройства</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColClientOs" data-column-key="client_os" data-column-index="12" checked>
                                <label class="form-check-label" for="piColClientOs">ОС</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColClientBrowser" data-column-key="client_browser" data-column-index="13" checked>
                                <label class="form-check-label" for="piColClientBrowser">Браузер</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColClientUa" data-column-key="client_user_agent" data-column-index="14" checked>
                                <label class="form-check-label" for="piColClientUa">User-Agent</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColClientIp" data-column-key="client_ip" data-column-index="15" checked>
                                <label class="form-check-label" for="piColClientIp">IP</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColClientRef" data-column-key="client_referrer" data-column-index="16" checked>
                                <label class="form-check-label" for="piColClientRef">Referrer</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input payment-intents-column-toggle" type="checkbox" id="piColMeta" data-column-key="meta" data-column-index="17" checked>
                                <label class="form-check-label" for="piColMeta">Мета</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $piHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="paymentIntentsFiltersCollapse">
    <form id="payment-intents-filters" method="GET" action="{{ route('reports.payment-intents.index') }}" class="border rounded p-2 p-md-3 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-inv-id">№</label>
                <input class="form-control" id="pi-filter-inv-id" name="inv_id" value="{{ $filters['inv_id'] ?? '' }}"
                       placeholder="123">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-partner">Партнер</label>
                <select class="form-select payments-report-filter-select2"
                        id="pi-filter-partner"
                        name="partner_id"
                        data-placeholder="Все партнеры"
                        data-search-url="{{ route('reports.payment-intents.partners.search') }}">
                    <option value=""></option>
                    @if(!empty($piFilterPartner))
                        <option value="{{ $piFilterPartner['id'] }}" selected>{{ $piFilterPartner['text'] }}</option>
                    @endif
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-user">Пользователь</label>
                <select class="form-select payments-report-filter-select2"
                        id="pi-filter-user"
                        name="user_id"
                        data-placeholder="Все пользователи"
                        data-search-url="{{ route('reports.payment-intents.users.search') }}">
                    <option value=""></option>
                    @if(!empty($piFilterUser))
                        <option value="{{ $piFilterUser['id'] }}" selected>{{ $piFilterUser['text'] }}</option>
                    @endif
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-provider">Провайдер</label>
                <select class="form-select" id="pi-filter-provider" name="provider">
                    @php($provider = $filters['provider'] ?? '')
                    <option value="">—</option>
                    <option value="robokassa" {{ $provider === 'robokassa' ? 'selected' : '' }}>Robokassa</option>
                    <option value="tbank" {{ $provider === 'tbank' ? 'selected' : '' }}>T-Bank</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-status">Статус</label>
                @php($status = $filters['status'] ?? '')
                <select class="form-select" id="pi-filter-status" name="status">
                    <option value="">—</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>в ожидании</option>
                    <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>оплачен</option>
                    <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>ошибка</option>
                    <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>отменён</option>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-created-from">Создано: с</label>
                <input class="form-control" id="pi-filter-created-from" type="date" name="created_from"
                       value="{{ $filters['created_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-created-to">Создано: по</label>
                <input class="form-control" id="pi-filter-created-to" type="date" name="created_to"
                       value="{{ $filters['created_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-paid-from">Дата оплаты: с</label>
                <input class="form-control" id="pi-filter-paid-from" type="date" name="paid_from"
                       value="{{ $filters['paid_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-paid-to">Дата оплаты: по</label>
                <input class="form-control" id="pi-filter-paid-to" type="date" name="paid_to"
                       value="{{ $filters['paid_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="paymentIntentsResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

<style>
    .pay-cell-datetime {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.2rem;
        padding: 0.15rem 0;
        line-height: 1.25;
        min-width: 5.5rem;
    }

    .pay-cell-datetime__date {
        white-space: nowrap;
    }

    .pay-cell-datetime__time {
        font-size: 0.8125rem;
        font-variant-numeric: tabular-nums;
        color: var(--bs-secondary-color, #6c757d);
        white-space: nowrap;
    }

    #payment-intents-table th.payment-intent-meta-th,
    #payment-intents-table td.payment-intent-meta-td {
        width: 1%;
        max-width: 5.5rem;
        white-space: nowrap;
        vertical-align: middle;
    }

    .payment-intent-meta-actions {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .payment-intent-meta-modal-pre {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.8125rem;
        white-space: pre-wrap;
        word-break: break-word;
    }

    #payment-intents-table th.payment-intent-client-ua-th,
    #payment-intents-table td.payment-intent-client-ua-td,
    #payment-intents-table th.payment-intent-client-ref-th,
    #payment-intents-table td.payment-intent-client-ref-td {
        max-width: 14rem;
        white-space: normal;
        word-break: break-word;
        vertical-align: middle;
        font-size: 0.8125rem;
    }
</style>

<div class="modal fade" id="paymentIntentMetaModal" tabindex="-1" aria-labelledby="paymentIntentMetaModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentIntentMetaModalLabel">Мета</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <pre class="payment-intent-meta-modal-pre mb-0"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary js-copy-payment-intent-meta-modal">
                    <i class="fas fa-copy me-1" aria-hidden="true"></i>Копировать
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered" id="payment-intents-table">
        <thead>
        <tr>
            <th>№</th>
            <th>ID провайдера</th>
            <th>Партнер</th>
            <th>Пользователь</th>
            <th>Провайдер</th>
            <th>Способ оплаты</th>
            <th>Статус</th>
            <th>Сумма платежа</th>
            <th>Оплаченный месяц</th>
            <th>Создано</th>
            <th>Дата оплаты</th>
            <th>Тип устройства</th>
            <th>ОС</th>
            <th>Браузер</th>
            <th class="payment-intent-client-ua-th">User-Agent</th>
            <th>IP</th>
            <th class="payment-intent-client-ref-th">Referrer</th>
            <th class="payment-intent-meta-th">Мета</th>
        </tr>
        </thead>
    </table>
</div>

@push('scripts')
    <script type="text/javascript">
        $(function () {
            var $form = $('#payment-intents-filters');
            var $piFilterPartner = $('#pi-filter-partner');
            var $piFilterUser = $('#pi-filter-user');
            var $piTotalAmount = $('.payments-report-total-amount');
            var $piTotalStat = $('#paymentIntentsReportTotalStat');
            var $piTotalValueInner = $('.payments-report-total-value-inner');

            function initPaymentsReportFilterSelect2($el, extraParamsFn) {
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
                            var payload = {q: params.term || ''};
                            if (typeof extraParamsFn === 'function') {
                                var extra = extraParamsFn() || {};
                                Object.keys(extra).forEach(function (k) {
                                    payload[k] = extra[k];
                                });
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

            initPaymentsReportFilterSelect2($piFilterPartner);
            initPaymentsReportFilterSelect2($piFilterUser, function () {
                return {
                    partner_id: $piFilterPartner.val() || ''
                };
            });

            function piParseTotalToInt(str) {
                return parseInt(String(str || '').replace(/\s/g, ''), 10) || 0;
            }

            function piFormatTotalSpaces(n) {
                var v = Math.round(Number(n));
                if (isNaN(v)) {
                    return '0';
                }
                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function piAnimateTotalChange(prevText, nextText, nextRaw) {
                var $amount = $piTotalAmount;
                if (!$amount.length) {
                    return;
                }

                var nextVal = typeof nextRaw === 'number' && !isNaN(nextRaw)
                    ? Math.round(nextRaw)
                    : piParseTotalToInt(nextText);
                var prevVal = piParseTotalToInt(prevText);

                var runFlashAndPop = function () {
                    if ($piTotalStat.length) {
                        $piTotalStat.removeClass('payments-report-total-stat--flash');
                        void $piTotalStat[0].offsetWidth;
                        $piTotalStat.addClass('payments-report-total-stat--flash');
                    }
                    if ($piTotalValueInner.length) {
                        $piTotalValueInner.removeClass('payments-report-total-value-inner--pop');
                        void $piTotalValueInner[0].offsetWidth;
                        $piTotalValueInner.addClass('payments-report-total-value-inner--pop');
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
                    $amount.text(piFormatTotalSpaces(cur));
                    if (t < 1) {
                        window.requestAnimationFrame(step);
                    } else {
                        $amount.text(nextText);
                    }
                }

                runFlashAndPop();
                window.requestAnimationFrame(step);
            }

            function piFilterParams() {
                return {
                    inv_id: $form.find('[name="inv_id"]').val() || '',
                    partner_id: $form.find('[name="partner_id"]').val() || '',
                    user_id: $form.find('[name="user_id"]').val() || '',
                    partner_title: '',
                    user_name: '',
                    provider: $form.find('[name="provider"]').val() || '',
                    status: $form.find('[name="status"]').val() || '',
                    created_from: $form.find('[name="created_from"]').val() || '',
                    created_to: $form.find('[name="created_to"]').val() || '',
                    paid_from: $form.find('[name="paid_from"]').val() || '',
                    paid_to: $form.find('[name="paid_to"]').val() || ''
                };
            }

            function refreshPiTotal() {
                var prevText = $piTotalAmount.length ? $piTotalAmount.text() : '';
                if ($piTotalStat.length) {
                    $piTotalStat.addClass('payments-report-total-stat--loading');
                }
                $.get(@json(route('reports.payment-intents.total')), piFilterParams())
                    .done(function (res) {
                        if ($piTotalStat.length) {
                            $piTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                        if (!res || res.total_formatted === undefined || !$piTotalAmount.length) {
                            return;
                        }
                        var nextText = res.total_formatted;
                        piAnimateTotalChange(prevText, nextText, res.total_raw);
                    })
                    .fail(function () {
                        if ($piTotalStat.length) {
                            $piTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                    });
            }

            function dtLanguageRu() {
                return {
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
                };
            }

            function formatNumber(number) {
                if (number === null || number === undefined) return '';
                return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            /**
             * Снимает HTML-сущности (&quot; и т.д.), если meta пришла экранированной.
             */
            function decodeHtmlEntitiesSafe(input) {
                var s = String(input);
                if (s.indexOf('&') === -1) {
                    return s;
                }
                var prev;
                var guard = 0;
                do {
                    prev = s;
                    s = s
                        .replace(/&amp;/g, '&')
                        .replace(/&quot;/g, '"')
                        .replace(/&#0*39;/g, "'")
                        .replace(/&lt;/g, '<')
                        .replace(/&gt;/g, '>');
                    guard++;
                } while (s !== prev && guard < 5);
                return s;
            }

            function formatMetaPretty(text) {
                var t = decodeHtmlEntitiesSafe(String(text)).trim();
                if (!t) return '';
                try {
                    return JSON.stringify(JSON.parse(t), null, 2);
                } catch (e) {
                    return t;
                }
            }

            function renderPaymentMonth(data, type) {
                if (!data) return data;
                if (type !== 'display') return data;
                if (/\d{4}-\d{2}-\d{2}/.test(data)) {
                    var date = new Date(data);
                    var monthNames = [
                        "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь",
                        "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"
                    ];
                    var month = monthNames[date.getMonth()];
                    var year = date.getFullYear();
                    return month + ' ' + year;
                }
                return data;
            }

            function renderDateTimeTwoLines(data, type) {
                if (!data) return data;
                if (type !== 'display') return data;
                var date = new Date(data);
                if (isNaN(date.getTime())) return data;
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

            function renderStatusBadge(status) {
                if (!status) return '';
                var s = String(status);
                if (s === 'pending') return '<span class="badge bg-warning text-dark">в ожидании</span>';
                if (s === 'paid') return '<span class="badge bg-success">оплачен</span>';
                if (s === 'failed') return '<span class="badge bg-danger">ошибка</span>';
                if (s === 'cancelled') return '<span class="badge bg-secondary">отменён</span>';
                return '<span class="badge bg-light text-dark">' + $('<div/>').text(s).html() + '</span>';
            }

            function formatClientDeviceTypeLabel(code) {
                if (!code) return '';
                var c = String(code);
                var map = {
                    mobile: 'мобильное',
                    tablet: 'планшет',
                    desktop: 'ПК',
                    bot: 'бот',
                    unknown: 'неизвестно'
                };
                return map[c] || c;
            }

            function joinClientOsBrowserParts(family, version) {
                var a = family ? String(family).trim() : '';
                var b = version ? String(version).trim() : '';
                if (a && b) {
                    return a + ' ' + b;
                }
                return a || b || '';
            }

            function escapeHtmlAttr(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function renderClientLongText(data, type, maxPreview) {
                if (data === null || data === undefined) {
                    data = '';
                }
                var text = String(data);
                if (type !== 'display') {
                    return text;
                }
                if (!text) {
                    return '<span class="text-muted">—</span>';
                }
                var preview = text;
                if (maxPreview && preview.length > maxPreview) {
                    preview = preview.slice(0, maxPreview) + '…';
                }
                var safePreview = $('<div/>').text(preview).html();
                return '<span title="' + escapeHtmlAttr(text) + '">' + safePreview + '</span>';
            }

            function flashCopyButton($btn) {
                var origHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-check text-success" aria-hidden="true"></i>');
                setTimeout(function () {
                    $btn.prop('disabled', false).html(origHtml);
                }, 1500);
            }

            function copyTextToClipboard(text, $btnForFlash) {
                function copyViaTextarea() {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        if (document.execCommand('copy') && $btnForFlash) {
                            flashCopyButton($btnForFlash);
                        }
                    } catch (err) {
                        alert('Не удалось скопировать');
                    }
                    document.body.removeChild(ta);
                }

                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    navigator.clipboard.writeText(text).then(function () {
                        if ($btnForFlash) flashCopyButton($btnForFlash);
                    }).catch(function () {
                        copyViaTextarea();
                    });
                } else {
                    copyViaTextarea();
                }
            }

            var table = $('#payment-intents-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('reports.payment-intents.data') }}",
                    data: function (d) {
                        var extra = piFilterParams();
                        Object.keys(extra).forEach(function (key) {
                            d[key] = extra[key];
                        });
                    }
                },
                columnDefs: [
                    {targets: -1, className: 'payment-intent-meta-td'}
                ],
                columns: [
                    {data: 'id', name: 'id'},
                    {data: 'provider_inv_id', name: 'provider_inv_id'},
                    {
                        data: null,
                        name: 'partner_id',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            var title = row.partner_title ? ('<div class="small text-muted">' + row.partner_title + '</div>') : '';
                            return (row.partner_id ? row.partner_id : '') + title;
                        }
                    },
                    {
                        data: null,
                        name: 'user_id',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            var name = row.user_name ? ('<div class="small text-muted">' + row.user_name + '</div>') : '';
                            return (row.user_id ? row.user_id : '') + name;
                        }
                    },
                    {
                        data: 'provider',
                        name: 'provider',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data || '';
                            }
                            if (data === 'tbank') {
                                return '<span class="badge" style="background-color:#ffdd2d !important; color:black !important;">T-Bank</span>';
                            }
                            if (data === 'robokassa') {
                                return '<span class="badge bg-secondary">Robokassa</span>';
                            }
                            return data ? $('<div/>').text(data).html() : '';
                        }
                    },
                    {
                        data: 'payment_method_webhook_label',
                        name: 'payment_method_webhook_label',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data || '';
                            }
                            if (!data) {
                                return '<span class="text-muted">—</span>';
                            }
                            return $('<div/>').text(data).html();
                        }
                    },
                    {
                        data: 'status',
                        name: 'status',
                        render: function (data, type, row) {
                            if (type === 'display') return renderStatusBadge(data);
                            return data;
                        }
                    },
                    {
                        data: 'out_sum',
                        name: 'out_sum',
                        render: function (data, type, row) {
                            if (type === 'display') {
                                if (data === null || data === undefined || data === '') return '';
                                var n = Math.round(parseFloat(data));
                                if (isNaN(n)) return '';
                                return formatNumber(n) + ' руб';
                            }
                            return data;
                        }
                    },
                    {
                        data: 'payment_date',
                        name: 'payment_date',
                        render: function (data, type, row) {
                            return renderPaymentMonth(data, type);
                        }
                    },
                    {
                        data: 'created_at',
                        name: 'created_at',
                        render: function (data, type, row) {
                            return renderDateTimeTwoLines(data, type);
                        }
                    },
                    {
                        data: 'paid_at',
                        name: 'paid_at',
                        render: function (data, type, row) {
                            return renderDateTimeTwoLines(data, type);
                        }
                    },
                    {
                        data: 'client_device_type',
                        name: 'client_device_type',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data || '';
                            }
                            if (!data) {
                                return '<span class="text-muted">—</span>';
                            }
                            return $('<div/>').text(formatClientDeviceTypeLabel(data)).html();
                        }
                    },
                    {
                        data: null,
                        name: 'client_os_family',
                        render: function (data, type, row) {
                            var joined = joinClientOsBrowserParts(row.client_os_family, row.client_os_version);
                            if (type !== 'display') {
                                return joined;
                            }
                            if (!joined) {
                                return '<span class="text-muted">—</span>';
                            }
                            return $('<div/>').text(joined).html();
                        }
                    },
                    {
                        data: null,
                        name: 'client_browser_family',
                        render: function (data, type, row) {
                            var joined = joinClientOsBrowserParts(row.client_browser_family, row.client_browser_version);
                            if (type !== 'display') {
                                return joined;
                            }
                            if (!joined) {
                                return '<span class="text-muted">—</span>';
                            }
                            return $('<div/>').text(joined).html();
                        }
                    },
                    {
                        data: 'client_user_agent',
                        name: 'client_user_agent',
                        className: 'payment-intent-client-ua-td',
                        render: function (data, type, row) {
                            return renderClientLongText(data, type, 120);
                        }
                    },
                    {
                        data: 'client_ip',
                        name: 'client_ip',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data || '';
                            }
                            if (!data) {
                                return '<span class="text-muted">—</span>';
                            }
                            return $('<div/>').text(String(data)).html();
                        }
                    },
                    {
                        data: 'client_referrer',
                        name: 'client_referrer',
                        className: 'payment-intent-client-ref-td',
                        render: function (data, type, row) {
                            return renderClientLongText(data, type, 80);
                        }
                    },
                    {
                        data: 'meta',
                        name: 'meta',
                        orderable: false,
                        render: function (data, type, row) {
                            if (!data) return '';
                            var text = String(data);
                            if (type !== 'display') {
                                return text;
                            }
                            var pretty = formatMetaPretty(text);
                            var safe = $('<div/>').text(pretty).html();
                            return (
                                '<div class="payment-intent-meta-actions payment-intent-meta-cell">' +
                                '<button type="button" class="btn btn-sm btn-outline-secondary js-show-payment-intent-meta" title="Показать">' +
                                '<i class="fas fa-eye" aria-hidden="true"></i>' +
                                '<span class="visually-hidden">Показать</span>' +
                                '</button>' +
                                '<button type="button" class="btn btn-sm btn-outline-secondary js-copy-payment-intent-meta" title="Копировать">' +
                                '<i class="fas fa-copy" aria-hidden="true"></i>' +
                                '<span class="visually-hidden">Копировать</span>' +
                                '</button>' +
                                '<span class="payment-intent-meta-full d-none">' + safe + '</span>' +
                                '</div>'
                            );
                        }
                    }
                ],
                order: [[0, 'desc']],
                scrollX: true,
                fixedColumns: {leftColumns: 2},
                language: dtLanguageRu()
            });

            var defaultColumnsVisibility = {
                id: true,
                provider_inv_id: true,
                partner: true,
                user: true,
                provider: true,
                payment_method_webhook_label: true,
                status: true,
                out_sum: true,
                payment_date: true,
                created_at: true,
                paid_at: true,
                client_device_type: true,
                client_os: true,
                client_browser: true,
                client_user_agent: true,
                client_ip: true,
                client_referrer: true,
                meta: true
            };

            var columnsMap = {
                id: 0,
                provider_inv_id: 1,
                partner: 2,
                user: 3,
                provider: 4,
                payment_method_webhook_label: 5,
                status: 6,
                out_sum: 7,
                payment_date: 8,
                created_at: 9,
                paid_at: 10,
                client_device_type: 11,
                client_os: 12,
                client_browser: 13,
                client_user_agent: 14,
                client_ip: 15,
                client_referrer: 16,
                meta: 17
            };

            function toBool(val, fallback) {
                if (fallback === undefined) fallback = true;
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

            function applyVisibleColumns(cfg) {
                Object.keys(columnsMap).forEach(function (key) {
                    var idx = columnsMap[key];
                    var visible = toBool(cfg[key], defaultColumnsVisibility[key]);
                    table.column(idx).visible(visible, false);
                    $('.payment-intents-column-toggle[data-column-key="' + key + '"]').prop('checked', visible);
                });
                table.columns.adjust().draw(false);
            }

            var currentColumnsConfig = $.extend({}, defaultColumnsVisibility);

            $.get('/admin/reports/payment-intents/columns-settings')
                .done(function (saved) {
                    if (saved && typeof saved === 'object') {
                        currentColumnsConfig = $.extend({}, defaultColumnsVisibility, saved);
                    }
                    applyVisibleColumns(currentColumnsConfig);
                })
                .fail(function () {
                    applyVisibleColumns(currentColumnsConfig);
                });

            $('.payment-intents-column-toggle').on('change', function () {
                var key = $(this).data('column-key');
                var isChecked = $(this).is(':checked');
                if (!key) return;

                currentColumnsConfig[key] = isChecked ? 1 : 0;
                applyVisibleColumns(currentColumnsConfig);

                $.ajax({
                    url: '/admin/reports/payment-intents/columns-settings',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        columns: currentColumnsConfig
                    },
                    success: function () {},
                    error: function () {
                        console.error('Не удалось сохранить настройки колонок');
                    }
                });
            });

            var metaModalEl = document.getElementById('paymentIntentMetaModal');
            var metaModal = metaModalEl && typeof bootstrap !== 'undefined'
                ? bootstrap.Modal.getOrCreateInstance(metaModalEl)
                : null;

            $('#payment-intents-table').on('click', '.js-show-payment-intent-meta', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var full = $(this).closest('.payment-intent-meta-cell').find('.payment-intent-meta-full').text();
                var $pre = $('#paymentIntentMetaModal .payment-intent-meta-modal-pre');
                $pre.text(formatMetaPretty(full));
                if (metaModal) {
                    metaModal.show();
                }
            });

            $('#payment-intents-table').on('click', '.js-copy-payment-intent-meta', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var full = $(this).closest('.payment-intent-meta-cell').find('.payment-intent-meta-full').text();
                copyTextToClipboard(full, $(this));
            });

            $(document).on('click', '.js-copy-payment-intent-meta-modal', function (e) {
                e.preventDefault();
                var t = $('#paymentIntentMetaModal .payment-intent-meta-modal-pre').text();
                copyTextToClipboard(t, $(this));
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                refreshPiTotal();
                table.ajax.reload();
            });

            $('#paymentIntentsResetBtn').on('click', function () {
                $form[0].reset();
                $piFilterPartner.val(null).trigger('change');
                $piFilterUser.val(null).trigger('change');
                refreshPiTotal();
                table.ajax.reload();
            });
        });
    </script>
@endpush
