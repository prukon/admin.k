@php
    $tpCanFilterPartner = $tpCanFilterPartner ?? false;
    $tpStatuses = \App\Http\Requests\Admin\Report\TbankPaymentsReportFilterRequest::STATUSES;
    $tpMethods = \App\Models\TinkoffPayment::METHOD_LABELS;
    $tpStatus = (string) ($filters['status'] ?? '');
    $tpMethod = (string) ($filters['method'] ?? '');
@endphp
@vite(['resources/css/admin-list-toolbar.css'])

<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
    <div class="card-body px-3 py-3">
        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Платежи T‑Bank</h1>
            <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                <div class="payments-report-total-inline payments-report-total-stat text-end" id="tbankPaymentsReportTotalStat">
                    <div class="payments-report-total-label text-muted small mb-0">Общая сумма</div>
                    <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                        <span class="payments-report-total-value-inner">
                            <span class="payments-report-total-amount">{{ $totalPaidPrice ?? '0' }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions @can('settings.commission') payments-report-toolbar-actions--many @endcan flex-shrink-0">
                    @can('settings.commission')
                    <a href="{{ route('admin.setting.tbankCommissions') }}"
                       class="payments-report-toolbar-action d-inline-flex align-items-center gap-2 text-decoration-none"
                       id="tp-toolbar-commissions"
                       title="Правила комиссий T‑Bank">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-percent payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Комиссии</span>
                    </a>
                    @endcan
                    <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#tbankPaymentsFiltersCollapse"
                            aria-expanded="{{ $tpHasActiveFilters ? 'true' : 'false' }}"
                            aria-controls="tbankPaymentsFiltersCollapse"
                            id="tbankPaymentsFiltersToggle">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="dropdown payments-report-toolbar-dropdown">
                        <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                id="columnsDropdownTbankPayments"
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
                             aria-labelledby="columnsDropdownTbankPayments">
                            <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>
                            <div class="form-check">
                                <input class="form-check-input tbank-payments-column-toggle" type="checkbox" data-column-key="created_at" id="tpColCreated" checked>
                                <label class="form-check-label" for="tpColCreated">Создан</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tbank-payments-column-toggle" type="checkbox" data-column-key="partner" id="tpColPartner" checked>
                                <label class="form-check-label" for="tpColPartner">Партнер</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tbank-payments-column-toggle" type="checkbox" data-column-key="order_id" id="tpColOrder" checked>
                                <label class="form-check-label" for="tpColOrder">Order</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tbank-payments-column-toggle" type="checkbox" data-column-key="amount" id="tpColAmount" checked>
                                <label class="form-check-label" for="tpColAmount">Сумма</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tbank-payments-column-toggle" type="checkbox" data-column-key="platform_commission" id="tpColPlatformCommission" checked>
                                <label class="form-check-label" for="tpColPlatformCommission">Комиссия платформы</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tbank-payments-column-toggle" type="checkbox" data-column-key="payout_amount" id="tpColPayoutAmount" checked>
                                <label class="form-check-label" for="tpColPayoutAmount">Выплата</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tbank-payments-column-toggle" type="checkbox" data-column-key="method" id="tpColMethod" checked>
                                <label class="form-check-label" for="tpColMethod">Способ</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tbank-payments-column-toggle" type="checkbox" data-column-key="status" id="tpColStatus" checked>
                                <label class="form-check-label" for="tpColStatus">Статус</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tbank-payments-column-toggle" type="checkbox" data-column-key="deal_id" id="tpColDeal" checked>
                                <label class="form-check-label" for="tpColDeal">Deal</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tbank-payments-column-toggle" type="checkbox" data-column-key="receipt" id="tpColReceipt" checked>
                                <label class="form-check-label" for="tpColReceipt">Чек</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tbank-payments-column-toggle" type="checkbox" data-column-key="actions" id="tpColActions" checked>
                                <label class="form-check-label" for="tpColActions">Действия</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $tpHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="tbankPaymentsFiltersCollapse">
    <form id="tbank-payments-filters" method="GET" action="{{ route('reports.tbank-payments.index') }}" class="border rounded p-2 p-md-3 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label" for="tp-filter-status">Статус</label>
                <select class="form-select @error('status') is-invalid @enderror" name="status" id="tp-filter-status">
                    <option value="" {{ $tpStatus === '' ? 'selected' : '' }}>Все статусы</option>
                    @foreach($tpStatuses as $st)
                        <option value="{{ $st }}" {{ $tpStatus === $st ? 'selected' : '' }}>{{ $st }}</option>
                    @endforeach
                </select>
                <div class="invalid-feedback" data-error-for="status" @error('status') style="display:block" @enderror>@error('status'){{ $message }}@enderror</div>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="tp-filter-method">Способ</label>
                <select class="form-select @error('method') is-invalid @enderror" name="method" id="tp-filter-method">
                    <option value="" {{ $tpMethod === '' ? 'selected' : '' }}>Все способы</option>
                    @foreach($tpMethods as $code => $label)
                        <option value="{{ $code }}" {{ $tpMethod === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="invalid-feedback" data-error-for="method" @error('method') style="display:block" @enderror>@error('method'){{ $message }}@enderror</div>
            </div>
            @if(!empty($tpCanFilterPartner))
            <div class="col-12 col-md-3">
                <label class="form-label" for="tp-filter-partner">Партнер</label>
                <select class="form-select payments-report-filter-select2 @error('partner_id') is-invalid @enderror"
                        id="tp-filter-partner"
                        name="partner_id"
                        data-placeholder="Все партнеры"
                        data-search-url="{{ route('reports.tbank-payments.partners.search') }}">
                    <option value=""></option>
                    @if(!empty($tpFilterPartner))
                        <option value="{{ $tpFilterPartner['id'] }}" selected>{{ $tpFilterPartner['text'] }}</option>
                    @endif
                </select>
                <div class="invalid-feedback" data-error-for="partner_id" @error('partner_id') style="display:block" @enderror>@error('partner_id'){{ $message }}@enderror</div>
            </div>
            @endif
            <div class="col-12 col-md-2">
                <label class="form-label" for="tp-filter-created-from">Создано: с</label>
                <input class="form-control @error('created_from') is-invalid @enderror" type="date" name="created_from" id="tp-filter-created-from" value="{{ $filters['created_from'] ?? '' }}">
                <div class="invalid-feedback" data-error-for="created_from" @error('created_from') style="display:block" @enderror>@error('created_from'){{ $message }}@enderror</div>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="tp-filter-created-to">Создано: по</label>
                <input class="form-control @error('created_to') is-invalid @enderror" type="date" name="created_to" id="tp-filter-created-to" value="{{ $filters['created_to'] ?? '' }}">
                <div class="invalid-feedback" data-error-for="created_to" @error('created_to') style="display:block" @enderror>@error('created_to'){{ $message }}@enderror</div>
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="tbankPaymentsResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-bordered dt-columns-managed w-100" id="tbank-payments-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Создан</th>
            <th>Партнер</th>
            <th>Order</th>
            <th>Сумма</th>
            <th>Комиссия платформы</th>
            <th>Выплата</th>
            <th>Способ</th>
            <th>Статус</th>
            <th>Deal</th>
            <th>Чек</th>
            <th></th>
        </tr>
        </thead>
    </table>
</div>

@push('scripts')
    <script type="text/javascript">
        $(function () {
            var $form = $('#tbank-payments-filters');
            var $tpTotalAmount = $('.payments-report-total-amount');
            var $tpTotalStat = $('#tbankPaymentsReportTotalStat');
            var $tpTotalValueInner = $('.payments-report-total-value-inner');
            @if($tpCanFilterPartner)
            var $tpFilterPartner = $('#tp-filter-partner');
            @endif

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

            @if($tpCanFilterPartner)
            initPaymentsReportFilterSelect2($tpFilterPartner);
            @endif

            function tpParseTotalToInt(str) {
                return parseInt(String(str || '').replace(/\s/g, ''), 10) || 0;
            }

            function tpFormatTotalSpaces(n) {
                var v = Math.round(Number(n));
                if (isNaN(v)) {
                    return '0';
                }
                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function tpAnimateTotalChange(prevText, nextText, nextRaw) {
                var $amount = $tpTotalAmount;
                if (!$amount.length) {
                    return;
                }

                var nextVal = typeof nextRaw === 'number' && !isNaN(nextRaw)
                    ? Math.round(nextRaw)
                    : tpParseTotalToInt(nextText);
                var prevVal = tpParseTotalToInt(prevText);

                var runFlashAndPop = function () {
                    if ($tpTotalStat.length) {
                        $tpTotalStat.removeClass('payments-report-total-stat--flash');
                        void $tpTotalStat[0].offsetWidth;
                        $tpTotalStat.addClass('payments-report-total-stat--flash');
                    }
                    if ($tpTotalValueInner.length) {
                        $tpTotalValueInner.removeClass('payments-report-total-value-inner--pop');
                        void $tpTotalValueInner[0].offsetWidth;
                        $tpTotalValueInner.addClass('payments-report-total-value-inner--pop');
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
                    $amount.text(tpFormatTotalSpaces(cur));
                    if (t < 1) {
                        window.requestAnimationFrame(step);
                    } else {
                        $amount.text(nextText);
                    }
                }

                runFlashAndPop();
                window.requestAnimationFrame(step);
            }

            function tpFilterParams() {
                return {
                    status: $form.find('[name="status"]').val() || '',
                    method: $form.find('[name="method"]').val() || '',
                    partner_id: $form.find('[name="partner_id"]').val() || '',
                    created_from: $form.find('[name="created_from"]').val() || '',
                    created_to: $form.find('[name="created_to"]').val() || ''
                };
            }

            function tpClearFieldErrors() {
                $form.find('[data-error-for]').text('').hide();
                $form.find('.is-invalid').removeClass('is-invalid');
            }

            function tpShowFieldErrors(xhr) {
                tpClearFieldErrors();
                var errors = xhr && xhr.responseJSON && xhr.responseJSON.errors;
                if (!errors) {
                    return;
                }
                Object.keys(errors).forEach(function (field) {
                    var msg = errors[field] && errors[field][0];
                    if (!msg) {
                        return;
                    }
                    $form.find('[name="' + field + '"]').addClass('is-invalid');
                    $form.find('[data-error-for="' + field + '"]').text(msg).show();
                });
            }

            function refreshTbankPaymentsTotal() {
                var prevText = $tpTotalAmount.length ? $tpTotalAmount.text() : '';
                if ($tpTotalStat.length) {
                    $tpTotalStat.addClass('payments-report-total-stat--loading');
                }
                $.get(@json(route('reports.tbank-payments.total')), tpFilterParams())
                    .done(function (res) {
                        tpClearFieldErrors();
                        if ($tpTotalStat.length) {
                            $tpTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                        if (!res || res.total_formatted === undefined || !$tpTotalAmount.length) {
                            return;
                        }
                        tpAnimateTotalChange(prevText, res.total_formatted, res.total_raw);
                    })
                    .fail(function (xhr) {
                        if ($tpTotalStat.length) {
                            $tpTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                        tpShowFieldErrors(xhr);
                    });
            }

            function renderStatusBadge(status) {
                if (!status) return '';
                var s = String(status);
                var map = {
                    NEW: 'bg-secondary',
                    FORM: 'bg-info text-dark',
                    CONFIRMED: 'bg-success',
                    REJECTED: 'bg-danger',
                    CANCELED: 'bg-warning text-dark'
                };
                var cls = map[s] || 'bg-light text-dark';
                return '<span class="badge ' + cls + '">' + $('<div/>').text(s).html() + '</span>';
            }

            function renderTbankReceiptCell(data, type, row) {
                if (type !== 'display') {
                    return row.has_receipt ? 1 : 0;
                }

                var incomeTitle = row.receipt_hint || (row.has_receipt && row.receipt_url
                    ? 'Чек сформирован'
                    : 'Чек не сформирован');
                var icons = [];
                if (row.has_receipt && row.receipt_url) {
                    icons.push({
                        href: row.receipt_url,
                        iconClass: 'fas fa-receipt text-primary',
                        title: incomeTitle,
                        ariaLabel: 'Чек сформирован'
                    });
                } else {
                    icons.push({
                        iconClass: 'fas fa-receipt text-secondary',
                        title: incomeTitle,
                        ariaLabel: 'Чек не сформирован'
                    });
                }

                if (row.return_receipt_url) {
                    icons.push({
                        href: row.return_receipt_url,
                        iconClass: 'fas fa-receipt return-receipt-icon',
                        linkClass: 'return-receipt-link',
                        title: row.return_receipt_hint || 'Чек возврата',
                        ariaLabel: 'Чек возврата'
                    });
                }

                return window.KidsCrmDataTable.renderIcon(icons, type, { sortKey: 'has_receipt' }, row);
            }

            var dtApi = KidsCrmDataTable.create('#tbank-payments-table', {
                columnsSettings: {
                    persistPageLength: true,
                    defaults: {
                        created_at: true,
                        partner: true,
                        order_id: true,
                        amount: true,
                        platform_commission: true,
                        payout_amount: true,
                        method: true,
                        status: true,
                        deal_id: true,
                        receipt: true,
                        actions: true
                    },
                    urls: {
                        get: '/admin/reports/tbank-payments/columns-settings',
                        save: '/admin/reports/tbank-payments/columns-settings'
                    },
                    toggleSelector: '.tbank-payments-column-toggle',
                    csrfToken: '{{ csrf_token() }}'
                },
                dataTable: {
                    pageLength: @json((int) ($tbankPaymentsPageLength ?? 10)),
                    ajax: {
                        url: "{{ route('reports.tbank-payments.data') }}",
                        data: function (d) {
                            var extra = tpFilterParams();
                            Object.keys(extra).forEach(function (key) {
                                d[key] = extra[key];
                            });
                        }
                    },
                    order: [[0, 'desc']],
                    language: @include('partials.datatables.ru')
                },
                columns: [
                    { key: 'id', type: 'id', data: 'id', name: 'id' },
                    { key: 'created_at', type: 'datetime', data: 'created_at', name: 'created_at', searchable: false },
                    {
                        key: 'partner',
                        type: 'text',
                        data: 'partner_title',
                        name: 'partner_id',
                        className: 'dt-col-text'
                    },
                    { key: 'order_id', type: 'text', data: 'order_id', name: 'order_id' },
                    { key: 'amount', type: 'money', data: 'amount', name: 'amount', searchable: false },
                    {
                        key: 'platform_commission',
                        type: 'money',
                        data: 'platform_commission',
                        name: 'platform_commission',
                        orderable: false,
                        searchable: false
                    },
                    { key: 'payout_amount', type: 'money', data: 'payout_amount', name: 'payout_amount', searchable: false },
                    {
                        key: 'method',
                        type: 'text',
                        data: 'method_label',
                        name: 'method',
                        searchable: false,
                        className: 'dt-col-text'
                    },
                    {
                        key: 'status',
                        type: 'badge',
                        data: 'status',
                        name: 'status',
                        searchable: false,
                        className: 'dt-col-badge text-center',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data || '';
                            }
                            return renderStatusBadge(data);
                        }
                    },
                    { key: 'deal_id', type: 'text', data: 'deal_id', name: 'deal_id' },
                    {
                        key: 'receipt',
                        type: 'icon',
                        data: null,
                        name: 'receipt',
                        orderable: false,
                        searchable: false,
                        className: 'dt-col-icon text-center',
                        render: renderTbankReceiptCell
                    },
                    {
                        key: 'actions',
                        type: 'actions',
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-nowrap',
                        render: function (data, type, row) {
                            var url = row.show_url || '';
                            return '<a class="btn btn-sm btn-outline-primary" href="'
                                + KidsCrmTooltip.escapeHtml(url)
                                + '">Открыть</a>';
                        }
                    }
                ]
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                refreshTbankPaymentsTotal();
                dtApi.reload();
            });

            $('#tbankPaymentsResetBtn').on('click', function () {
                $form[0].reset();
                @if($tpCanFilterPartner)
                $tpFilterPartner.val(null).trigger('change');
                @endif
                tpClearFieldErrors();
                refreshTbankPaymentsTotal();
                dtApi.reload();
            });
        });
    </script>
@endpush
