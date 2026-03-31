@php
    $canAdditional = auth()->user() && auth()->user()->can('reports.additional.value.view');
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
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
    <h4 class="text-start mb-0">Платежи</h4>

    {{-- Dropdown "Поля списка" --}}
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle"
                type="button"
                id="columnsDropdownPayments"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                title="Поля списка">
            <i class="fas fa-table-columns"></i>
        </button>

        <div class="dropdown-menu p-3"
             aria-labelledby="columnsDropdownPayments"
             style="min-width: 240px;">

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

         @if($tbankEnabled ?? false)

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="bank_commission_total"
                       id="payColBankCommission"
                       checked
                       @if(!$canAdditional) disabled @endif
                       @if(!$canAdditional) title="Доступно по праву reports.additional.value.view" @endif>
                <label class="form-check-label" for="payColBankCommission">Комиссия банка</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="platform_commission"
                       id="payColPlatformCommission"
                       checked
                       @if(!$canAdditional) disabled @endif
                       @if(!$canAdditional) title="Доступно по праву reports.additional.value.view" @endif>
                <label class="form-check-label" for="payColPlatformCommission">Комиссия платформы</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="commission_total"
                       id="payColCommissionTotal"
                       checked
                       @if(!$canAdditional) disabled @endif
                       @if(!$canAdditional) title="Доступно по праву reports.additional.value.view" @endif>
                <label class="form-check-label" for="payColCommissionTotal">Комиссия</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="net_to_partner"
                       id="payColNetToPartner"
                       checked
                       @if(!$canAdditional) disabled @endif
                       @if(!$canAdditional) title="Доступно по праву reports.additional.value.view" @endif>
                <label class="form-check-label" for="payColNetToPartner">К выплате</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="payout_amount"
                       id="payColPayout"
                       checked>
                <label class="form-check-label" for="payColPayout">Выплата</label>
            </div>
@endif


            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="refund_action"
                       id="payColActions"
                       checked>
                <label class="form-check-label" for="payColActions">Действия</label>
            </div>

            <div class="form-check">
                <input class="form-check-input payments-column-toggle"
                       type="checkbox"
                       data-column-key="refund_status"
                       id="payColRefundStatus"
                       checked
                       @if(!$canAdditional) disabled @endif
                       @if(!$canAdditional) title="Доступно по праву reports.additional.value.view" @endif>
                <label class="form-check-label" for="payColRefundStatus">Статус возврата</label>
            </div>

        </div>
    </div>
</div>

<div class="mb-2">
    <button class="payments-report-filters-toggle d-inline-flex align-items-center gap-2"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#paymentsReportFiltersCollapse"
            aria-expanded="{{ $payHasActiveFilters ? 'true' : 'false' }}"
            aria-controls="paymentsReportFiltersCollapse"
            id="paymentsReportFiltersToggle">
        <span class="payments-report-filters-icon-wrap" aria-hidden="true">
            <i class="fas fa-sliders-h payments-report-filters-main-icon"></i>
        </span>
        <span class="payments-report-filters-label">Фильтры</span>
        <i class="fas {{ $payHasActiveFilters ? 'fa-chevron-up' : 'fa-chevron-down' }} payments-report-filters-chevron"
           aria-hidden="true"></i>
    </button>
</div>

<div class="collapse {{ $payHasActiveFilters ? 'show' : '' }} mb-3" id="paymentsReportFiltersCollapse">
    <form id="payments-report-filters" method="GET" action="/admin/reports/payments" class="border rounded p-3 bg-light">
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
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-end gap-2 ms-md-auto">
                <button class="btn btn-primary" type="submit">Применить</button>
                <button class="btn btn-outline-secondary" type="button" id="paymentsReportFiltersResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

<div class="sum-dept-wrap alert alert-warning d-flex justify-content-between align-items-center p-3 mt-3 mb-3 rounded">
    <span class="fw-bold">Общая сумма платежей:</span>

    <span class="fw-bold"> {{$totalPaidPrice}} руб</span>
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

<style>
    .return-receipt-link {
        text-decoration: none;
    }

    .return-receipt-icon {
        color: #ffc107; /* bootstrap warning */
        transition: color .15s ease, transform .15s ease;
    }

    .return-receipt-link:hover .return-receipt-icon {
        color: #ff9800;
        transform: translateY(-1px);
    }

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

    .payments-report-filters-toggle {
        cursor: pointer;
        user-select: none;
        padding: 0.45rem 0.9rem 0.45rem 0.45rem;
        border-radius: 0.5rem;
        font-weight: 600;
        line-height: 1.25;
        color: var(--bs-body-color);
        background: linear-gradient(180deg, #fff 0%, var(--bs-light, #f8f9fa) 100%);
        border: 1px solid var(--bs-border-color, #dee2e6);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }

    .payments-report-filters-toggle:hover {
        border-color: #b6d4fe;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        color: var(--bs-body-color);
        background: #fff;
    }

    .payments-report-filters-toggle:focus-visible {
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }

    .payments-report-filters-toggle[aria-expanded="true"] {
        border-color: #86b7fe;
        background: #f0f7ff;
    }

    .payments-report-filters-icon-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.4rem;
        background: var(--bs-primary-bg-subtle, #cfe2ff);
        color: var(--bs-primary, #0d6efd);
    }

    .payments-report-filters-main-icon {
        font-size: 0.95rem;
        line-height: 1;
    }

    .payments-report-filters-label {
        letter-spacing: 0.02em;
    }

    .payments-report-filters-chevron {
        font-size: 0.7rem;
        opacity: 0.65;
        margin-left: 0.15rem;
    }

    /* Select2 в блоке фильтров (как на /cabinet) */
    .payments-report-filter-select2 {
        width: 100% !important;
    }
</style>

@section('scripts')
    <script type="text/javascript">
        $(function () {
            const canAdditional = @json($canAdditional);
            const tbankEnabled = @json($tbankEnabled ?? false);

            /** Совместимость со старыми ссылками ?user_name= / ?team_title= (без filter_*_id) */
            var payReportLegacyFilters = @json([
                'user_name' => (! empty($filters['user_name']) && empty($filters['filter_user_id'] ?? null)) ? (string) $filters['user_name'] : '',
                'team_title' => (! empty($filters['team_title']) && empty($filters['filter_team_id'] ?? null)) ? (string) $filters['team_title'] : '',
            ]);

            var $payFiltersForm = $('#payments-report-filters');
            var $payFiltersCollapse = $('#paymentsReportFiltersCollapse');
            var $payFiltersToggle = $('#paymentsReportFiltersToggle');
            var $payFiltersChevron = $payFiltersToggle.find('.payments-report-filters-chevron');

            $payFiltersCollapse.on('shown.bs.collapse', function () {
                $payFiltersToggle.attr('aria-expanded', 'true');
                $payFiltersChevron.removeClass('fa-chevron-down').addClass('fa-chevron-up');
            });
            $payFiltersCollapse.on('hidden.bs.collapse', function () {
                $payFiltersToggle.attr('aria-expanded', 'false');
                $payFiltersChevron.removeClass('fa-chevron-up').addClass('fa-chevron-down');
            });

            var $payFilterUser = $('#pay-filter-user');
            var $payFilterTeam = $('#pay-filter-team');

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
    payout_amount: tbankEnabled,
    net_to_partner: tbankEnabled && canAdditional,
    commission_total: tbankEnabled && canAdditional,
    bank_commission_total: tbankEnabled && canAdditional,
    platform_commission: tbankEnabled && canAdditional,
    refund_status: canAdditional,
    refund_action: true
            };

            const additionalColumnsKeys = [
                'bank_commission_total',
                'platform_commission',
                'commission_total',
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
    refund_action: 9,
    refund_status: 10
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

// Добавляем T-Bank колонки только если включен T-Bank
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
        },
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
        },
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
        },
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
}

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
            d.filter_user_id = $payFiltersForm.find('[name="filter_user_id"]').val() || '';
            d.filter_team_id = $payFiltersForm.find('[name="filter_team_id"]').val() || '';
            d.user_name = d.filter_user_id ? '' : (payReportLegacyFilters.user_name || '');
            d.team_title = d.filter_team_id ? '' : (payReportLegacyFilters.team_title || '');
            d.payment_month = $payFiltersForm.find('[name="payment_month"]').val();
            d.operation_date_from = $payFiltersForm.find('[name="operation_date_from"]').val();
            d.operation_date_to = $payFiltersForm.find('[name="operation_date_to"]').val();
            d.payment_provider = $payFiltersForm.find('[name="payment_provider"]').val();
            d.payment_method = $payFiltersForm.find('[name="payment_method"]').val();
            d.payment_refund_status = $payFiltersForm.find('[name="payment_refund_status"]').val();
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
                table.ajax.reload();
            });

            $('#paymentsReportFiltersResetBtn').on('click', function () {
                $payFiltersForm[0].reset();
                payReportLegacyFilters.user_name = '';
                payReportLegacyFilters.team_title = '';
                $payFilterUser.val(null).trigger('change');
                $payFilterTeam.val(null).trigger('change');
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



