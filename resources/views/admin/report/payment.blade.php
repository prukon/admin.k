@php
    $canAdditional = auth()->user() && auth()->user()->can('reports.additional.value.view');
    $pt = $paymentsToolbar ?? [];
    $ptSum = $pt['sum_payments_formatted'] ?? ($totalPaidPrice ?? '0');
    $canPaymentsToolbarNetToPartner = $canPaymentsToolbarNetToPartner ?? false;
    $canPaymentsToolbarPayoutAmount = $canPaymentsToolbarPayoutAmount ?? false;
    $canPaymentsToolbarPlatformCommission = $canPaymentsToolbarPlatformCommission ?? false;
    $paymentsFilterUser = $paymentsFilterUser ?? null;
    $paymentsFilterTeam = $paymentsFilterTeam ?? null;
    $payFilterKeys = ['filter_user_id', 'filter_team_id', 'user_name', 'team_title', 'payment_month', 'operation_date_from', 'operation_date_to', 'payment_provider', 'payment_method', 'payment_refund_status'];
    $payHasActiveFilters = false;
    foreach ($payFilterKeys as $k) {
        $v = $filters[$k] ?? null;
        if ($v !== null && $v !== '') {
            $payHasActiveFilters = true;
            break;
        }
    }
@endphp
<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
    <div class="card-body px-3 py-3">
        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Платежи</h1>
            <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                <div class="d-flex flex-wrap align-items-end justify-content-end gap-3 gap-md-4" id="paymentsReportToolbarTotals">
                    <div class="payments-report-total-inline payments-report-total-stat text-end" id="paymentsReportSumPaymentsStat">
                        <div class="payments-report-total-label text-muted small mb-0">Сумма платежей</div>
                        <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                            <span class="payments-report-total-value-inner">
                                <span class="payments-report-total-amount">{{ $ptSum }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                            </span>
                        </div>
                    </div>
                    @if($canPaymentsToolbarNetToPartner)
                        <div class="payments-report-total-inline payments-report-total-stat text-end" id="paymentsReportNetToPartnerStat">
                            <div class="payments-report-total-label text-muted small mb-0">К выплате</div>
                            <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                                <span class="payments-report-total-value-inner">
                                    <span class="payments-report-total-amount">{{ $pt['net_to_partner_formatted'] ?? '0' }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                                </span>
                            </div>
                        </div>
                    @endif
                    @if($canPaymentsToolbarPayoutAmount)
                        <div class="payments-report-total-inline payments-report-total-stat text-end" id="paymentsReportPayoutAmountStat">
                            <div class="payments-report-total-label text-muted small mb-0">Выплата</div>
                            <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                                <span class="payments-report-total-value-inner">
                                    <span class="payments-report-total-amount">{{ $pt['payout_amount_formatted'] ?? '0' }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                                </span>
                            </div>
                        </div>
                    @endif
                    @if($canPaymentsToolbarPlatformCommission)
                        <div class="payments-report-total-inline payments-report-total-stat text-end" id="paymentsReportPlatformCommissionStat">
                            <div class="payments-report-total-label text-muted small mb-0">Комиссия платформы</div>
                            <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                                <span class="payments-report-total-value-inner">
                                    <span class="payments-report-total-amount">{{ $pt['platform_commission_formatted'] ?? '0' }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#paymentsReportFiltersCollapse"
                        aria-expanded="{{ $payHasActiveFilters ? 'true' : 'false' }}"
                        aria-controls="paymentsReportFiltersCollapse"
                        id="paymentsReportFiltersToggle">
                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                        <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                    </span>
                    <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                    <i class="fas fa-chevron-down payments-report-toolbar-chevron"
                       aria-hidden="true"></i>
                </button>

                <div class="dropdown payments-report-toolbar-dropdown">
                    <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                            type="button"
                            id="columnsDropdownPayments"
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
                         aria-labelledby="columnsDropdownPayments">
                        <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="user_name"
                       id="payColUserName"
                       checked>
                <label class="form-check-label" for="payColUserName">ФИО</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="team_title"
                       id="payColTeam"
                       checked>
                <label class="form-check-label" for="payColTeam">Группа</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="summ"
                       id="payColSumm"
                       checked>
                <label class="form-check-label" for="payColSumm">Сумма платежа</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="payment_month"
                       id="payColMonth"
                       checked>
                <label class="form-check-label" for="payColMonth">Оплаченный месяц</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="operation_date"
                       id="payColDate"
                       checked>
                <label class="form-check-label" for="payColDate">Дата платежа</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="payment_provider"
                       id="payColProvider"
                       checked>
                <label class="form-check-label" for="payColProvider">Провайдер</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="payment_method_label"
                       id="payColPaymentMethod"
                       checked>
                <label class="form-check-label" for="payColPaymentMethod">Способ оплаты</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="receipt"
                       id="payColReceipt"
                       checked>
                <label class="form-check-label" for="payColReceipt">Чек</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="commission_total"
                       id="payColCommissionTotal"
                       checked>
                <label class="form-check-label" for="payColCommissionTotal">Комиссия</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="payout_amount"
                       id="payColPayout"
                       checked>
                <label class="form-check-label" for="payColPayout">Выплата</label>
            </div>

         @if($tbankEnabled ?? false)

            @if($canAdditional)
                <div class="form-check">
                    <input class="form-check-input payments-column-toggle"
                           type="checkbox"
                           data-column-key="bank_commission_total"
                           id="payColBankCommission"
                           checked>
                    <label class="form-check-label" for="payColBankCommission">Комиссия банка</label>
                </div>

                <div class="form-check">
                    <input class="form-check-input payments-column-toggle"
                           type="checkbox"
                           data-column-key="platform_commission"
                           id="payColPlatformCommission"
                           checked>
                    <label class="form-check-label" for="payColPlatformCommission">Комиссия платформы</label>
                </div>

                <div class="form-check">
                    <input class="form-check-input payments-column-toggle"
                           type="checkbox"
                           data-column-key="net_to_partner"
                           id="payColNetToPartner"
                           checked>
                    <label class="form-check-label" for="payColNetToPartner">К выплате</label>
                </div>
            @endif
@endif


            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="refund_action"
                       id="payColActions"
                       checked>
                <label class="form-check-label" for="payColActions">Действия</label>
            </div>

            @if($canAdditional)
                <div class="form-check">
                    <input class="form-check-input payments-column-toggle"
                           type="checkbox"
                           data-column-key="refund_status"
                           id="payColRefundStatus"
                           checked>
                    <label class="form-check-label" for="payColRefundStatus">Статус возврата</label>
                </div>
            @endif

                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $payHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="paymentsReportFiltersCollapse">
    <form id="payments-report-filters" method="GET" action="/admin/reports/payments" class="border rounded p-2 p-md-3 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-filter-user">Ученик</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-filter-user"
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
                <label class="form-label" for="pay-filter-team">Группа</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-filter-team"
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
                <label class="form-label" for="pay-filter-payment-month">Оплаченный месяц</label>
                <input class="form-control" id="pay-filter-payment-month" type="month" name="payment_month"
                       value="{{ $filters['payment_month'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-filter-op-from">Дата платежа: с</label>
                <input class="form-control" id="pay-filter-op-from" type="date" name="operation_date_from"
                       value="{{ $filters['operation_date_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-filter-op-to">Дата платежа: по</label>
                <input class="form-control" id="pay-filter-op-to" type="date" name="operation_date_to"
                       value="{{ $filters['operation_date_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-filter-provider">Провайдер</label>
                @php($fpProvider = $filters['payment_provider'] ?? '')
                <select class="form-select" id="pay-filter-provider" name="payment_provider">
                    <option value="">—</option>
                    <option value="tbank" {{ $fpProvider === 'tbank' ? 'selected' : '' }}>T-Bank</option>
                    <option value="robokassa" {{ $fpProvider === 'robokassa' ? 'selected' : '' }}>Robokassa</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pay-filter-method">Способ оплаты</label>
                @php($fpMethod = $filters['payment_method'] ?? '')
                <select class="form-select" id="pay-filter-method" name="payment_method">
                    <option value="">—</option>
                    <option value="card" {{ $fpMethod === 'card' ? 'selected' : '' }}>Карта</option>
                    <option value="sbp_qr" {{ $fpMethod === 'sbp_qr' ? 'selected' : '' }}>QR (СБП)</option>
                    <option value="tpay" {{ $fpMethod === 'tpay' ? 'selected' : '' }}>T‑Pay</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-filter-refund-status">Статус платежа</label>
                @php($fpRefund = $filters['payment_refund_status'] ?? '')
                <select class="form-select" id="pay-filter-refund-status" name="payment_refund_status">
                    <option value="">—</option>
                    <option value="no_refund" {{ $fpRefund === 'no_refund' ? 'selected' : '' }}>Оплата</option>
                    <option value="refunded" {{ $fpRefund === 'refunded' ? 'selected' : '' }}>Возврат</option>
                    <option value="refund_pending" {{ $fpRefund === 'refund_pending' ? 'selected' : '' }}>Возврат (в процессе)</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="paymentsReportFiltersResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

<table class="table table-bordered" id="payments-table">
    <thead>
    <tr>
        <th>№</th>
        <th>ФИО</th>
        <th>Группа</th>
        <th>Сумма платежа</th>
        <th>Оплаченный месяц</th>
        <th>Дата платежа</th>
        <th>Провайдер</th>
        <th>Способ оплаты</th>
        <th>Чек</th>
        @if($tbankEnabled ?? false)
            <th>Комиссия банка</th>
            <th>Комиссия платформы</th>
            <th>Комиссия</th>
            <th>К выплате</th>
            <th>Выплата</th>
        @else
            <th>Комиссия</th>
            <th>Выплата</th>
        @endif

        <th>Действия</th>
        <th>Статус возврата</th>
    </tr>
    </thead>
</table>

<!-- Модальное окно возврата -->
<div class="modal fade" id="refundModal" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="refundModalLabel">Возврат платежа</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    После успешного возврата оплата месяца будет отменена (снимется <b>is_paid</b>).
                </div>
                <div class="alert alert-info py-2 d-none" id="refundProviderNote"></div>
                <input type="hidden" id="refundPaymentId" value="">

                <div class="mb-2">
                    <div><b>Провайдер:</b> <span id="refundProvider"></span></div>
                    <div><b>Ученик:</b> <span id="refundUser"></span></div>
                    <div><b>Период:</b> <span id="refundMonth"></span></div>
                    <div><b>Сумма:</b> <span id="refundAmount"></span> руб</div>
                </div>

                <div class="mb-3">
                    <label for="refundComment" class="form-label">Комментарий (необязательно)</label>
                    <textarea class="form-control" id="refundComment" rows="3" maxlength="1000"></textarea>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="refundConfirm">
                    <label class="form-check-label" for="refundConfirm">
                        Подтверждаю возврат и отмену оплаты месяца
                    </label>
                </div>

                <div class="text-danger mt-2 d-none" id="refundError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" id="refundSubmitBtn" disabled>Сделать возврат</button>
            </div>
        </div>
    </div>
</div>

@can('viewing.all.logs')
<!-- Модальное окно "История" (T‑Bank) -->
<div class="modal fade" id="tbankHistoryModal" tabindex="-1" aria-labelledby="tbankHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tbankHistoryModalLabel">История платежа (T‑Bank)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-3 mb-2">
                    <div><b>Платёж:</b> <span id="tbankHistoryPaymentId"></span></div>
                    <div><b>Deal:</b> <span id="tbankHistoryDealId"></span></div>
                    <div><b>BankPaymentId:</b> <span id="tbankHistoryBankPaymentId"></span></div>
                </div>

                <div class="alert alert-secondary py-2 d-none" id="tbankHistoryEmpty">
                    История для этого платежа пока отсутствует.
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="tbankHistoryTable">
                        <thead>
                        <tr>
                            <th style="width: 170px;">Время</th>
                            <th style="width: 90px;">Тип</th>
                            <th style="width: 110px;">Источник</th>
                            <th>Статус</th>
                            <th style="width: 160px;">Детали</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div class="d-none" id="tbankHistoryError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>
@endcan

@vite(['resources/css/payments-report.css'])

@section('scripts')
    <script type="text/javascript">
        $(function () {
            const canAdditional = @json($canAdditional);
            const tbankEnabled = @json($tbankEnabled ?? false);
            const paymentsToolbarFlags = {
                net: @json($canPaymentsToolbarNetToPartner),
                payout: @json($canPaymentsToolbarPayoutAmount),
                platform: @json($canPaymentsToolbarPlatformCommission)
            };

            /** Совместимость со старыми ссылками ?user_name= / ?team_title= (без filter_*_id) */
            var payReportLegacyFilters = @json([
                'user_name' => (! empty($filters['user_name']) && empty($filters['filter_user_id'] ?? null)) ? (string) $filters['user_name'] : '',
                'team_title' => (! empty($filters['team_title']) && empty($filters['filter_team_id'] ?? null)) ? (string) $filters['team_title'] : '',
            ]);

            var $payFiltersForm = $('#payments-report-filters');

            var $payFilterUser = $('#pay-filter-user');
            var $payFilterTeam = $('#pay-filter-team');
            var $paymentsToolbarRoot = $('#paymentsReportToolbarTotals');
            var $statSumPayments = $('#paymentsReportSumPaymentsStat');
            var $statNetToPartner = $('#paymentsReportNetToPartnerStat');
            var $statPayoutAmount = $('#paymentsReportPayoutAmountStat');
            var $statPlatformCommission = $('#paymentsReportPlatformCommissionStat');

            function paymentsReportParseTotalToInt(str) {
                return parseInt(String(str || '').replace(/\s/g, ''), 10) || 0;
            }

            function paymentsReportFormatTotalSpaces(n) {
                var v = Math.round(Number(n));
                if (isNaN(v)) {
                    return '0';
                }
                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function paymentsReportAnimateStatChange($statRoot, prevText, nextText, nextRaw) {
                var $amount = $statRoot.find('.payments-report-total-amount');
                if (!$amount.length) {
                    return;
                }
                var nextVal = typeof nextRaw === 'number' && !isNaN(nextRaw)
                    ? Math.round(nextRaw)
                    : paymentsReportParseTotalToInt(nextText);
                var prevVal = paymentsReportParseTotalToInt(prevText);

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
                    $amount.text(paymentsReportFormatTotalSpaces(cur));
                    if (t < 1) {
                        window.requestAnimationFrame(step);
                    } else {
                        $amount.text(nextText);
                    }
                }

                runFlashAndPop();
                window.requestAnimationFrame(step);
            }

            function paymentsReportFilterParams() {
                var uid = $payFiltersForm.find('[name="filter_user_id"]').val() || '';
                var tid = $payFiltersForm.find('[name="filter_team_id"]').val() || '';
                return {
                    filter_user_id: uid,
                    filter_team_id: tid,
                    user_name: uid ? '' : (payReportLegacyFilters.user_name || ''),
                    team_title: tid ? '' : (payReportLegacyFilters.team_title || ''),
                    payment_month: $payFiltersForm.find('[name="payment_month"]').val(),
                    operation_date_from: $payFiltersForm.find('[name="operation_date_from"]').val(),
                    operation_date_to: $payFiltersForm.find('[name="operation_date_to"]').val(),
                    payment_provider: $payFiltersForm.find('[name="payment_provider"]').val(),
                    payment_method: $payFiltersForm.find('[name="payment_method"]').val(),
                    payment_refund_status: $payFiltersForm.find('[name="payment_refund_status"]').val()
                };
            }

            function refreshPaymentsReportTotal() {
                var prevSum = $statSumPayments.find('.payments-report-total-amount').text();
                var prevNet = $statNetToPartner.length ? $statNetToPartner.find('.payments-report-total-amount').text() : '';
                var prevPayout = $statPayoutAmount.length ? $statPayoutAmount.find('.payments-report-total-amount').text() : '';
                var prevPlat = $statPlatformCommission.length ? $statPlatformCommission.find('.payments-report-total-amount').text() : '';
                if ($paymentsToolbarRoot.length) {
                    $paymentsToolbarRoot.find('.payments-report-total-stat').addClass('payments-report-total-stat--loading');
                }
                $.get(@json(route('reports.payments.total')), paymentsReportFilterParams())
                    .done(function (res) {
                        if ($paymentsToolbarRoot.length) {
                            $paymentsToolbarRoot.find('.payments-report-total-stat').removeClass('payments-report-total-stat--loading');
                        }
                        if (!res || res.sum_payments_formatted === undefined || !$statSumPayments.length) {
                            return;
                        }
                        paymentsReportAnimateStatChange($statSumPayments, prevSum, res.sum_payments_formatted, res.sum_payments_raw);
                        if (paymentsToolbarFlags.net && $statNetToPartner.length && res.net_to_partner_formatted !== undefined) {
                            paymentsReportAnimateStatChange($statNetToPartner, prevNet, res.net_to_partner_formatted, res.net_to_partner_raw);
                        }
                        if (paymentsToolbarFlags.payout && $statPayoutAmount.length && res.payout_amount_formatted !== undefined) {
                            paymentsReportAnimateStatChange($statPayoutAmount, prevPayout, res.payout_amount_formatted, res.payout_amount_raw);
                        }
                        if (paymentsToolbarFlags.platform && $statPlatformCommission.length && res.platform_commission_formatted !== undefined) {
                            paymentsReportAnimateStatChange($statPlatformCommission, prevPlat, res.platform_commission_formatted, res.platform_commission_raw);
                        }
                    })
                    .fail(function () {
                        if ($paymentsToolbarRoot.length) {
                            $paymentsToolbarRoot.find('.payments-report-total-stat').removeClass('payments-report-total-stat--loading');
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

            initPaymentsReportFilterSelect2($payFilterUser);
            initPaymentsReportFilterSelect2($payFilterTeam);

            const defaultColumnsVisibility = {
    user_name: true,
    team_title: true,
    summ: true,
    payment_month: true,
    operation_date: true,
    payment_provider: true,
    payment_method_label: true,
    receipt: true,
    commission_total: true,
    payout_amount: true,
    net_to_partner: tbankEnabled && canAdditional,
    bank_commission_total: tbankEnabled && canAdditional,
    platform_commission: tbankEnabled && canAdditional,
    refund_status: canAdditional,
    refund_action: true
            };

            const additionalColumnsKeys = [
                'bank_commission_total',
                'platform_commission',
                'net_to_partner',
                'refund_status',
            ];

            let currentColumnsConfig = {...defaultColumnsVisibility};

            // Маппинг ключей на индексы колонок DataTables
            // 0 – № (DT_RowIndex) всегда видна, не настраиваем
     const columnsMap = tbankEnabled ? {
    user_name: 1,
    team_title: 2,
    summ: 3,
    payment_month: 4,
    operation_date: 5,
    payment_provider: 6,
    payment_method_label: 7,
    receipt: 8,
    bank_commission_total: 9,
    platform_commission: 10,
    commission_total: 11,
    net_to_partner: 12,
    payout_amount: 13,
    refund_action: 14,
    refund_status: 15
} : {
    user_name: 1,
    team_title: 2,
    summ: 3,
    payment_month: 4,
    operation_date: 5,
    payment_provider: 6,
    payment_method_label: 7,
    receipt: 8,
    commission_total: 9,
    payout_amount: 10,
    refund_action: 11,
    refund_status: 12
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

            function updateFixedColumns() {
                // Если скрыли "Имя ученика" (колонка 1), лучше фиксировать только № (колонка 0),
                // иначе FixedColumns может оставить "пустой" фиксированный столбец.
                try {
                    const keepLeft = toBool(currentColumnsConfig.user_name, true) ? 2 : 1;
                    if (table && typeof table.fixedColumns === 'function') {
                        const fc = table.fixedColumns();
                        if (fc && typeof fc.leftColumns === 'function') {
                            fc.leftColumns(keepLeft);
                        }
                    }
                } catch (e) {
                    // no-op
                }
            }

            function applyVisibleColumns(config) {
                Object.keys(columnsMap).forEach(function (key) {
                    const colIndex = columnsMap[key];
                    const column = table.column(colIndex);

                    let isVisible = toBool(config[key], defaultColumnsVisibility[key]);

                    // Доступ к комиссиям, «к выплате», статусу возврата — только по праву reports.additional.value.view.
                    if (!canAdditional && additionalColumnsKeys.indexOf(key) !== -1) {
                        isVisible = false;
                    }

                    column.visible(isVisible);

                    $('.payments-column-toggle[data-column-key="' + key + '"]')
                        .prop('checked', isVisible);

                    if (!canAdditional && additionalColumnsKeys.indexOf(key) !== -1) {
                        $('.payments-column-toggle[data-column-key="' + key + '"]').prop('disabled', true);
                    }
                });

                updateFixedColumns();

                try {
                    table.columns.adjust();
                } catch (e) {
                    // no-op
                }
            }

            function loadColumnsConfigFromServer() {
                $.ajax({
                    url: '/admin/reports/payments/columns-settings',
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

            
// Формируем массив колонок в зависимости от tbankEnabled
const columns = [
    {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
    {
        data: null,
        name: 'user_name',
        render: function (data, type, row) {
            return row.user_name ? row.user_name : 'Без имени';
        }
    },
    {data: 'team_title', name: 'team_title'},
    {
        data: 'summ',
        name: 'summ',
        render: function (data, type, row) {
            if (type === 'display') {
                function formatNumber(number) {
                    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
                const formattedPrice = formatNumber(row.summ);
                return `${formattedPrice} руб`;
            }
            return parseFloat(row.summ);
        }
    },
    {
        data: 'payment_month',
        name: 'payment_month',
        render: function (data, type, row) {
            if (data) {
                if (/\d{4}-\d{2}-\d{2}/.test(data)) {
                    const date = new Date(data);
                    const monthNames = [
                        "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь",
                        "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"
                    ];
                    const month = monthNames[date.getMonth()];
                    const year = date.getFullYear();
                    return `${month} ${year}`;
                } else {
                    return data;
                }
            }
            return data;
        }
    },
    {
        data: 'operation_date',
        name: 'operation_date',
        render: function (data, type, row) {
            if (!data) {
                return data;
            }
            if (type !== 'display') {
                return data;
            }
            var date = new Date(data);
            if (isNaN(date.getTime())) {
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
    },
    {
        data: 'payment_provider',
        name: 'payment_provider',
        orderable: false,
        searchable: false,
        render: function (data, type, row) {
            if (data === 'tbank') return '<span class="badge" style="background-color:#ffdd2d !important; color:black !important;">T-Bank</span>';
            if (data === 'robokassa') return '<span class="badge bg-secondary">Robokassa</span>';
            return data ? data : '';
        }
    },
    {
        data: 'payment_method_label',
        name: 'payment_method_label',
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
        data: null,
        name: 'receipt',
        orderable: false,
        searchable: false,
        render: function (data, type, row) {
            if (type !== 'display') return row.has_receipt ? 1 : 0;

            if (row.payment_provider !== 'tbank') {
                return '<span title="Чек формируется у партнера в его онлайн-кассе"></span>';
            }

                var refundStatus = row.refund_status || '';
                var returnReceiptUrl = row.return_receipt_url || '';
                var returnReceiptStatus = row.return_receipt_status || '';

                var incomeTitle = (row.has_receipt && row.receipt_url)
                    ? 'Чек сформирован'
                    : 'Чек не сформирован';

                // Сообщение/подсказка, если возврат уже есть, но return-check ещё не сформирован.
                if (refundStatus !== '' && !returnReceiptUrl) {
                    if (returnReceiptStatus === 'error') {
                        incomeTitle += '. Чек возврата: не сформирован (ошибка).';
                    } else {
                        incomeTitle += '. Чек возврата ожидается (CloudKassir).';
                    }
                }

                var incomeHtml = '';
                if (row.has_receipt && row.receipt_url) {
                    incomeHtml =
                        '<a href="' + row.receipt_url + '" target="_blank" rel="noopener noreferrer" title="' + incomeTitle + '" aria-label="Чек сформирован">' +
                        '<i class="fas fa-receipt text-primary"></i>' +
                        '</a>';
                } else {
                    incomeHtml =
                        '<span title="' + incomeTitle + '" aria-label="Чек не сформирован">' +
                        '<i class="fas fa-receipt text-secondary"></i>' +
                        '</span>';
                }

                // Вторая иконка показывается только при наличии return receipt URL.
                var returnHtml = '';
                if (returnReceiptUrl) {
                    returnHtml =
                        '<a href="' + returnReceiptUrl + '" target="_blank" rel="noopener noreferrer" ' +
                        'class="return-receipt-link" title="Чек возврата" aria-label="Чек возврата">' +
                        '<i class="fas fa-receipt return-receipt-icon"></i>' +
                        '</a>';
                }

                return '<span style="display:inline-flex; align-items:center; gap:8px;">' + incomeHtml + returnHtml + '</span>';
        }
    }
];

if (tbankEnabled) {
    columns.push(
        {
            data: 'bank_commission_total',
            name: 'bank_commission_total',
            render: function (data, type, row) {
                if (data === null || data === undefined || data === '') return '';
                if (type !== 'display') return parseFloat(data);

                function formatNumber(number) {
                    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }

                const acq = row.bank_commission_acquiring;
                const payout = row.bank_commission_payout;
                const title = `Эквайринг: ${acq !== null && acq !== undefined ? formatNumber(Math.round(parseFloat(acq))) : ''} руб&#10;Выплата: ${payout !== null && payout !== undefined ? formatNumber(Math.round(parseFloat(payout))) : ''} руб`;
                const total = formatNumber(Math.round(parseFloat(data)));
                return `<span title="${title}">${total} руб</span>`;
            }
        },
        {
            data: 'platform_commission',
            name: 'platform_commission',
            render: function (data, type, row) {
                if (data === null || data === undefined || data === '') return '';
                if (type !== 'display') return parseFloat(data);
                function formatNumber(number) {
                    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
                return `${formatNumber(Math.round(parseFloat(data)))} руб`;
            }
        }
    );
}

columns.push(
    {
        data: 'commission_total',
        name: 'commission_total',
        render: function (data, type, row) {
            if (data === null || data === undefined || data === '') return '';
            if (type !== 'display') return parseFloat(data);
            function formatNumber(number) {
                return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }
            const title = 'Суммарные удержания по тарифу и комиссии банка';
            const total = formatNumber(Math.round(parseFloat(data)));
            return `<span title="${title}">${total} руб</span>`;
        }
    }
);

if (tbankEnabled) {
    columns.push(
        {
            data: 'net_to_partner',
            name: 'net_to_partner',
            render: function (data, type, row) {
                if (data === null || data === undefined || data === '') return '';
                if (type !== 'display') return parseFloat(data);
                function formatNumber(number) {
                    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
                return `${formatNumber(Math.round(parseFloat(data)))} руб`;
            }
        }
    );
}

columns.push(
    {
        data: 'payout_amount',
        name: 'payout_amount',
        render: function (data, type, row) {
            if (data === null || data === undefined || data === '') return '';
            if (type !== 'display') return parseFloat(data);
            function formatNumber(number) {
                return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }
            return `${formatNumber(Math.round(parseFloat(data)))} руб`;
        }
    }
);

columns.push(
    {data: 'refund_action', name: 'refund_action', orderable: false, searchable: false},
    {
        data: 'refund_status',
        name: 'refund_status',
        render: function (data, type, row) {
            if (!data) return '';
            if (data === 'pending') return '<span class="badge bg-warning text-dark">в обработке</span>';
            if (data === 'succeeded') return '<span class="badge bg-success">возвращён</span>';
            if (data === 'failed') return '<span class="badge bg-danger">ошибка</span>';
            return data;
        }
    }
);


            var table = $('#payments-table').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
        url: "{{ route('payments.getPayments') }}",
        data: function (d) {
            var f = paymentsReportFilterParams();
            Object.keys(f).forEach(function (k) {
                d[k] = f[k];
            });
        }
    },
    columns: columns,
    order: [[5, 'desc']], // По умолчанию: ближайшие платежи сверху (по дате)

    scrollX: true,

    fixedColumns: {
        leftColumns: 2
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
    }
});

            // после инициализации — подгружаем конфиг из БД
            loadColumnsConfigFromServer();

            $payFiltersForm.on('submit', function (e) {
                e.preventDefault();
                refreshPaymentsReportTotal();
                table.ajax.reload();
            });

            $('#paymentsReportFiltersResetBtn').on('click', function () {
                $payFiltersForm[0].reset();
                payReportLegacyFilters.user_name = '';
                payReportLegacyFilters.team_title = '';
                $payFilterUser.val(null).trigger('change');
                $payFilterTeam.val(null).trigger('change');
                refreshPaymentsReportTotal();
                table.ajax.reload();
            });

            // --- Обработчик чекбоксов "Поля списка" ---
            $('.payments-column-toggle').on('change', function () {
                const key = $(this).data('column-key');
                const isChecked = $(this).is(':checked');

                // как в users: отправляем 1/0
                currentColumnsConfig[key] = isChecked ? 1 : 0;

                applyVisibleColumns(currentColumnsConfig);

                $.ajax({
                    url: '/admin/reports/payments/columns-settings',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        columns: currentColumnsConfig
                    },
                    success: function () {
                    },
                    error: function () {
                        console.error('Не удалось сохранить настройки колонок');
                    }
                });
            });

            // handlers: refund modal
            var refundModal = new bootstrap.Modal(document.getElementById('refundModal'));
            @can('viewing.all.logs')
            var tbankHistoryModal = new bootstrap.Modal(document.getElementById('tbankHistoryModal'));
            @endcan

            function applyRefundProviderUi(provider) {
                var title = 'Возврат платежа';
                var label = '';
                var note = '';

                if (provider === 'robokassa') {
                    title = 'Возврат платежа (Robokassa)';
                    label = 'Robokassa';
                    note = 'Ограничение: возврат доступен в течение <b>7 дней</b> после оплаты.';
                } else if (provider === 'tbank') {
                    title = 'Возврат платежа (T-Bank мультирасчёты)';
                    label = 'T-Bank';
                    note = 'Важно: если выплата уже <b>ушла в банк</b> (есть PaymentId выплаты), возврат запрещён. Пока выплата только в очереди — возврат отменит её.<br>После успешного возврата месяц будет отмечен как <b>неоплаченный</b>.';
                }

                $('#refundModalLabel').text(title);
                $('#refundProvider').text(label);

                if (note) {
                    $('#refundProviderNote').removeClass('d-none').html(note);
                } else {
                    $('#refundProviderNote').addClass('d-none').html('');
                }
            }

            $(document).on('click', '.js-refund-btn', function () {
                if ($(this).prop('disabled')) return;
                var paymentId = $(this).data('payment-id');
                var amount = $(this).data('amount');
                var user = $(this).data('user');
                var month = $(this).data('month');
                var provider = $(this).data('provider') || '';

                $('#refundPaymentId').val(paymentId);
                $('#refundAmount').text(amount);
                $('#refundUser').text(user || '');
                $('#refundMonth').text(month || '');
                $('#refundComment').val('');
                $('#refundConfirm').prop('checked', false);
                $('#refundSubmitBtn').prop('disabled', true).text('Сделать возврат');
                $('#refundError').addClass('d-none').text('');

                applyRefundProviderUi(provider);

                refundModal.show();
            });

            $('#refundConfirm').on('change', function () {
                $('#refundSubmitBtn').prop('disabled', !$(this).is(':checked'));
            });

            $('#refundSubmitBtn').on('click', function () {
                var btn = $(this);
                var paymentId = $('#refundPaymentId').val();
                var comment = $('#refundComment').val();

                btn.prop('disabled', true).text('Отправляем...');
                $('#refundError').addClass('d-none').text('');

                $.ajax({
                    url: '/admin/reports/payments/' + paymentId + '/refund',
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    data: {comment: comment},
                    success: function (resp) {
                        refundModal.hide();
                        table.ajax.reload(null, false);
                    },
                    error: function (xhr) {
                        var msg = 'Ошибка при создании возврата';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        $('#refundError').removeClass('d-none').text(msg);
                        btn.prop('disabled', false).text('Сделать возврат');
                    }
                });
            });

            @can('viewing.all.logs')
            // handlers: tbank history modal
            function escapeHtml(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function renderHistoryRow(ev) {
                var at = escapeHtml(ev.at || '');
                var kind = escapeHtml(ev.kind || '');
                var source = escapeHtml(ev.source || '');
                var status = escapeHtml(ev.to_status || ev.bank_status || '');
                var from = ev.from_status ? (' ← ' + escapeHtml(ev.from_status)) : '';
                var details = '';

                if (ev.kind === 'payment') {
                    if (ev.bank_payment_id) details += 'PaymentId: ' + escapeHtml(ev.bank_payment_id) + '<br>';
                    if (ev.order_id) details += 'OrderId: ' + escapeHtml(ev.order_id) + '<br>';
                    if (ev.bank_status && ev.to_status && ev.bank_status !== ev.to_status) {
                        details += 'BankStatus: ' + escapeHtml(ev.bank_status) + '<br>';
                    }
                } else if (ev.kind === 'payout') {
                    if (ev.payout_id) details += 'PayoutId: ' + escapeHtml(ev.payout_id) + '<br>';
                }

                var payloadBtn = '';
                if (ev.payload) {
                    var payloadId = 'payload-' + Math.random().toString(36).slice(2);
                    payloadBtn =
                        '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#' + payloadId + '">payload</button>' +
                        '<div class="collapse mt-2" id="' + payloadId + '"><pre class="mb-0 small bg-light p-2 rounded" style="max-height:240px; overflow:auto;">' +
                        escapeHtml(JSON.stringify(ev.payload, null, 2)) +
                        '</pre></div>';
                }

                return '<tr>' +
                    '<td class="text-nowrap">' + at + '</td>' +
                    '<td class="text-nowrap">' + kind + '</td>' +
                    '<td class="text-nowrap">' + source + '</td>' +
                    '<td>' + status + from + '</td>' +
                    '<td>' + (details || '') + payloadBtn + '</td>' +
                    '</tr>';
            }

            $(document).on('click', '.js-tbank-history-btn', function () {
                var paymentId = $(this).data('payment-id');
                var dealId = $(this).data('deal-id') || '';
                var bankPaymentId = $(this).data('bank-payment-id') || '';

                $('#tbankHistoryPaymentId').text(paymentId);
                $('#tbankHistoryDealId').text(dealId);
                $('#tbankHistoryBankPaymentId').text(bankPaymentId);
                $('#tbankHistoryError').addClass('d-none').removeClass('alert alert-danger').text('');
                $('#tbankHistoryEmpty').addClass('d-none');
                $('#tbankHistoryTable tbody').html('<tr><td colspan="5">Загрузка...</td></tr>');

                tbankHistoryModal.show();

                $.ajax({
                    url: '/admin/reports/payments/' + paymentId + '/tbank-history',
                    method: 'GET',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    success: function (resp) {
                        var events = Array.isArray(resp && resp.events) ? resp.events : [];
                        if (events.length === 0) {
                            $('#tbankHistoryTable tbody').html('');
                            $('#tbankHistoryEmpty').removeClass('d-none');
                            return;
                        }

                        var html = '';
                        events.forEach(function (ev) {
                            html += renderHistoryRow(ev);
                        });
                        $('#tbankHistoryTable tbody').html(html);
                    },
                    error: function (xhr) {
                        $('#tbankHistoryTable tbody').html('');
                        var msg = 'Не удалось загрузить историю';
                        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        $('#tbankHistoryError').removeClass('d-none').addClass('alert alert-danger').text(msg);
                    }
                });
            });
            @endcan
        });
    </script>
@endsection



