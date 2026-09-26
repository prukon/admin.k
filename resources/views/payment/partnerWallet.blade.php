@extends('layouts.admin2')

@section('title','Кошелёк партнёра')

@php
    $walletFilterDateFrom = (string) request('date_from', '');
    $walletFilterDateTo = (string) request('date_to', '');
    $walletFilterType = (string) request('type', '');
    $walletFilterStatus = (string) request('status', '');
    $walletFilterProvider = (string) request('provider', '');
    $walletHistoryFiltersActive = $walletFilterDateFrom !== ''
        || $walletFilterDateTo !== ''
        || $walletFilterType !== ''
        || $walletFilterStatus !== ''
        || $walletFilterProvider !== '';
@endphp

@push('styles')
    @vite(['resources/css/admin-list-toolbar.css', 'resources/css/admin-reports-tables.css', 'resources/js/admin-reports-tables-sticky.js'])
    <style>
        /* .btn-primary в проекте белая; :disabled из Bootstrap красит текст в белый. */
        #walletTopupForm .btn-primary:disabled,
        #walletTopupForm .btn-primary.disabled {
            color: #212529 !important;
            background-color: #fff !important;
            border-color: #f3a12b !important;
        }

        /* Как блок сведений в модалке «Занятие из абонемента предоплаты» (schedule.css .cell-edit-context). */
        .partner-wallet-balance .cell-edit-context {
            margin-bottom: 1rem;
            padding: 0.85rem 1rem;
            border-radius: 0.65rem;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
        }

        .partner-wallet-balance .cell-edit-context__name {
            font-size: 1.05rem;
            font-weight: 600;
            color: #212529;
            line-height: 1.35;
            word-break: break-word;
        }

        .partner-wallet-balance .cell-edit-context__line {
            margin-top: 0.2rem;
            color: #6c757d;
            font-size: 0.8125rem;
            line-height: 1.3;
            word-break: break-word;
        }

        .partner-wallet-balance .cell-edit-context__line a {
            color: inherit;
            text-decoration: none;
        }

        .partner-wallet-balance .cell-edit-context__line a:hover {
            color: #212529;
            text-decoration: underline;
        }

        .partner-wallet-balance .cell-edit-context__summary {
            margin-top: 0.65rem;
            padding: 0.55rem 0.7rem;
            border-radius: 0.45rem;
            border: 1px solid #e9ecef;
            background: #fff;
            color: #495057;
            font-size: 0.8125rem;
            line-height: 1.4;
            word-break: break-word;
        }

        .partner-wallet-balance .cell-edit-section__label {
            display: block;
            margin-bottom: 0.45rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #495057;
            letter-spacing: 0.01em;
        }

        .partner-wallet-balance .partner-wallet-legal__row + .partner-wallet-legal__row {
            margin-top: 0.35rem;
        }

        .partner-wallet-balance .partner-wallet-legal__label {
            color: #868e96;
            font-size: 0.75rem;
            line-height: 1.2;
        }

        .partner-wallet-balance .js-wallet-preset {
            border: 1px solid #e9ecef;
            border-radius: 0.55rem;
            background: #fff;
            color: #212529;
            font-size: 0.9375rem;
            font-weight: 500;
            padding: 0.55rem 0.85rem;
        }

        .partner-wallet-balance .js-wallet-preset:hover {
            border-color: #ced4da;
            background: #fcfcfd;
            color: #212529;
        }

        .partner-wallet-balance .js-wallet-preset.active {
            border-color: #86b7fe;
            background: rgba(13, 110, 253, 0.06);
            color: #0a58ca;
            box-shadow: 0 0 0 1px rgba(13, 110, 253, 0.18);
        }
    </style>
@endpush

@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Кошелёк</h4>

        <ul class="nav nav-tabs" id="partnerWalletTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === 'balance' ? 'active' : '' }}"
                   href="{{ route('partner.wallet') }}"
                   role="tab">Баланс</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === 'history' ? 'active' : '' }}"
                   href="{{ route('partner.wallet.history') }}"
                   role="tab">История платежей</a>
            </li>
        </ul>

        <div class="tab-content">
            @if($activeTab === 'balance')
                @php
                    $walletContactFilled = static function (string $value): bool {
                        $value = trim($value);

                        return $value !== '' && !preg_match('/^[-–—]+$/u', $value);
                    };
                    $walletSchoolTitle = trim((string) ($partner->title ?? ''));
                    $walletSchoolPhone = trim((string) ($partner->phone ?? ''));
                    if (!$walletContactFilled($walletSchoolPhone)) {
                        $walletSchoolPhone = '';
                    }
                    $walletSchoolPhoneHref = preg_replace('/[^\d+]/', '', $walletSchoolPhone) ?? '';
                    $walletSchoolPhoneHref = ($walletSchoolPhoneHref !== '' && $walletSchoolPhoneHref !== '+')
                        ? 'tel:'.$walletSchoolPhoneHref
                        : '';
                    $walletSchoolEmail = trim((string) ($partner->email ?? ''));
                    if (!$walletContactFilled($walletSchoolEmail)) {
                        $walletSchoolEmail = '';
                    }
                    $walletSchoolEmailHref = filter_var($walletSchoolEmail, FILTER_VALIDATE_EMAIL) ? 'mailto:'.$walletSchoolEmail : '';
                    $walletSchoolWebsite = trim((string) ($partner->website ?? ''));
                    if (!$walletContactFilled($walletSchoolWebsite)) {
                        $walletSchoolWebsite = '';
                    }
                    $walletSchoolWebsiteHref = $walletSchoolWebsite;
                    if ($walletSchoolWebsiteHref !== '' && !preg_match('#^[a-z][a-z0-9+.-]*:#i', $walletSchoolWebsiteHref)) {
                        $walletSchoolWebsiteHref = 'https://'.$walletSchoolWebsiteHref;
                    }
                    $walletSchoolWebsiteScheme = strtolower((string) parse_url($walletSchoolWebsiteHref, PHP_URL_SCHEME));
                    if ($walletSchoolWebsiteHref === ''
                        || filter_var($walletSchoolWebsiteHref, FILTER_VALIDATE_URL) === false
                        || !in_array($walletSchoolWebsiteScheme, ['http', 'https'], true)) {
                        $walletSchoolWebsiteHref = '';
                    }
                    $walletLegalName = $walletLegalEntity ? trim($walletLegalEntity->displayTitle()) : '';
                    $walletLegalInn = $walletLegalEntity ? trim((string) ($walletLegalEntity->tax_id ?? '')) : '';
                @endphp
                <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2 partner-wallet-balance">
                    <div class="card-body px-3 py-3">
                        <div class="cell-edit-context" id="walletSchoolCard">
                            @if($walletSchoolTitle !== '')
                                <div class="cell-edit-context__name">{{ $walletSchoolTitle }}</div>
                            @endif
                            @if($walletSchoolPhone !== '')
                                <div class="cell-edit-context__line">
                                    @if($walletSchoolPhoneHref !== '')
                                        <a href="{{ $walletSchoolPhoneHref }}">{{ $walletSchoolPhone }}</a>
                                    @else
                                        {{ $walletSchoolPhone }}
                                    @endif
                                </div>
                            @endif
                            @if($walletSchoolEmail !== '')
                                <div class="cell-edit-context__line">
                                    @if($walletSchoolEmailHref !== '')
                                        <a href="{{ $walletSchoolEmailHref }}">{{ $walletSchoolEmail }}</a>
                                    @else
                                        {{ $walletSchoolEmail }}
                                    @endif
                                </div>
                            @endif
                            @if($walletSchoolWebsite !== '')
                                <div class="cell-edit-context__line">
                                    @if($walletSchoolWebsiteHref !== '')
                                        <a href="{{ $walletSchoolWebsiteHref }}" target="_blank" rel="noopener noreferrer">{{ $walletSchoolWebsite }}</a>
                                    @else
                                        {{ $walletSchoolWebsite }}
                                    @endif
                                </div>
                            @endif
                            <div class="cell-edit-context__summary">
                                <div class="payments-report-total-label text-muted small mb-0">Текущий баланс</div>
                                <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                                    <span class="payments-report-total-value-inner">
                                        <span class="payments-report-total-amount" id="walletBalance">{{ number_format(((int) ($partner->wallet_balance_cents ?? 0)) / 100, 2, ',', ' ') }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                                    </span>
                                </div>
                            </div>
                            @if($walletLegalName !== '' || $walletLegalInn !== '')
                                <div class="cell-edit-context__summary" id="walletLegalEntity">
                                    @if($walletLegalName !== '')
                                        <div class="partner-wallet-legal__row">
                                            <div class="partner-wallet-legal__label">Наименование</div>
                                            <div>{{ $walletLegalName }}</div>
                                        </div>
                                    @endif
                                    @if($walletLegalInn !== '')
                                        <div class="partner-wallet-legal__row">
                                            <div class="partner-wallet-legal__label">ИНН</div>
                                            <div>{{ $walletLegalInn }}</div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <form id="walletTopupForm" method="get" action="{{ route('partner.wallet.checkout') }}">
                            <div class="cell-edit-section__label">Сумма, ₽</div>
                            <div class="d-flex flex-wrap gap-2 mb-3" id="walletPresetAmounts">
                                <button type="button" class="btn btn-outline-secondary js-wallet-preset" data-amount="1000">1 000 ₽</button>
                                <button type="button" class="btn btn-outline-secondary js-wallet-preset" data-amount="5000">5 000 ₽</button>
                                <button type="button" class="btn btn-outline-secondary js-wallet-preset" data-amount="10000">10 000 ₽</button>
                            </div>
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-4">
                                    <label class="visually-hidden" for="walletTopupAmount">Сумма, ₽</label>
                                    <input type="number" step="0.01" min="1" class="form-control @error('amount') is-invalid @enderror" id="walletTopupAmount" name="amount" required>
                                    <div class="invalid-feedback @error('amount') d-block @enderror" data-error-for="amount">@error('amount'){{ $message }}@enderror</div>
                                </div>
                                <div class="col-12 col-md-auto">
                                    @if($canPayAcquiringSbp || $canPayAcquiringCard || $canPayYookassa)
                                        <button type="submit" class="btn btn-primary" id="topupBtn">Пополнить</button>
                                    @else
                                        <button type="submit" class="btn btn-primary" id="topupBtn" disabled>Пополнить</button>
                                    @endif
                                </div>
                            </div>
                            <div class="invalid-feedback d-block" data-error-for="partner_id">@error('partner_id'){{ $message }}@enderror</div>
                            <div class="invalid-feedback d-block" data-error-for="description">@error('description'){{ $message }}@enderror</div>
                            <div class="invalid-feedback d-block" data-error-for="payment_method">@error('payment_method'){{ $message }}@enderror</div>
                            @if(!($canPayAcquiringSbp || $canPayAcquiringCard || $canPayYookassa))
                                <div class="alert alert-warning mt-3 mb-0">Нет доступного способа оплаты.</div>
                            @endif
                        </form>
                    </div>
                </div>
            @else
                <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
                    <div class="card-body px-3 py-3">
                        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                            <h2 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">История платежей</h2>
                            <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                                <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#walletHistoryFiltersCollapse"
                                        aria-expanded="{{ $walletHistoryFiltersActive ? 'true' : 'false' }}"
                                        aria-controls="walletHistoryFiltersCollapse"
                                        id="walletHistoryFiltersToggle">
                                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                        <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                                    </span>
                                    <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                                    <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                                </button>

                                <div class="dropdown payments-report-toolbar-dropdown">
                                    <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                            type="button"
                                            id="walletHistoryColumnsDropdown"
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
                                         aria-labelledby="walletHistoryColumnsDropdown">
                                        <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>
                                        <div class="form-check">
                                            <input class="form-check-input wallet-history-column-toggle" type="checkbox" data-column-key="id" id="walletColId">
                                            <label class="form-check-label" for="walletColId">ID</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input wallet-history-column-toggle" type="checkbox" data-column-key="created_at" id="walletColCreatedAt" checked>
                                            <label class="form-check-label" for="walletColCreatedAt">Дата</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input wallet-history-column-toggle" type="checkbox" data-column-key="type" id="walletColType" checked>
                                            <label class="form-check-label" for="walletColType">Тип</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input wallet-history-column-toggle" type="checkbox" data-column-key="amount" id="walletColAmount" checked>
                                            <label class="form-check-label" for="walletColAmount">Сумма</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input wallet-history-column-toggle" type="checkbox" data-column-key="status" id="walletColStatus" checked>
                                            <label class="form-check-label" for="walletColStatus">Статус</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input wallet-history-column-toggle" type="checkbox" data-column-key="description" id="walletColDescription" checked>
                                            <label class="form-check-label" for="walletColDescription">Описание</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input wallet-history-column-toggle" type="checkbox" data-column-key="provider" id="walletColProvider">
                                            <label class="form-check-label" for="walletColProvider">Провайдер</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input wallet-history-column-toggle" type="checkbox" data-column-key="user_name" id="walletColUser">
                                            <label class="form-check-label" for="walletColUser">Кто провёл</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="collapse {{ $walletHistoryFiltersActive ? 'show' : '' }} mb-2 mb-md-3" id="walletHistoryFiltersCollapse">
                    <form id="wallet-history-filters" method="get" action="{{ route('partner.wallet.history') }}" class="border rounded p-2 p-md-3 bg-light">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="wallet-filter-date-from">Дата: с</label>
                                <input class="form-control @error('date_from') is-invalid @enderror" id="wallet-filter-date-from" type="date" name="date_from" value="{{ $walletFilterDateFrom }}">
                                <div class="invalid-feedback @error('date_from') d-block @enderror" data-error-for="date_from">@error('date_from'){{ $message }}@enderror</div>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="wallet-filter-date-to">Дата: по</label>
                                <input class="form-control @error('date_to') is-invalid @enderror" id="wallet-filter-date-to" type="date" name="date_to" value="{{ $walletFilterDateTo }}">
                                <div class="invalid-feedback @error('date_to') d-block @enderror" data-error-for="date_to">@error('date_to'){{ $message }}@enderror</div>
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="form-label" for="wallet-filter-type">Тип</label>
                                <select class="form-select @error('type') is-invalid @enderror" id="wallet-filter-type" name="type">
                                    <option value="">Все</option>
                                    <option value="credit" @selected($walletFilterType === 'credit')>Пополнение</option>
                                    <option value="debit" @selected($walletFilterType === 'debit')>Списание</option>
                                </select>
                                <div class="invalid-feedback @error('type') d-block @enderror" data-error-for="type">@error('type'){{ $message }}@enderror</div>
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="form-label" for="wallet-filter-status">Статус</label>
                                <select class="form-select @error('status') is-invalid @enderror" id="wallet-filter-status" name="status">
                                    <option value="">Все</option>
                                    <option value="succeeded" @selected($walletFilterStatus === 'succeeded')>Успешно</option>
                                    <option value="pending" @selected($walletFilterStatus === 'pending')>В ожидании</option>
                                    <option value="canceled" @selected($walletFilterStatus === 'canceled')>Отменено</option>
                                    <option value="failed" @selected($walletFilterStatus === 'failed')>Ошибка</option>
                                </select>
                                <div class="invalid-feedback @error('status') d-block @enderror" data-error-for="status">@error('status'){{ $message }}@enderror</div>
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="form-label" for="wallet-filter-provider">Провайдер</label>
                                <select class="form-select @error('provider') is-invalid @enderror" id="wallet-filter-provider" name="provider">
                                    <option value="">Все</option>
                                    <option value="tinkoff" @selected($walletFilterProvider === 'tinkoff')>T‑Bank</option>
                                    <option value="yookassa" @selected($walletFilterProvider === 'yookassa')>ЮKassa</option>
                                    <option value="manual" @selected($walletFilterProvider === 'manual')>Списание за договор</option>
                                    <option value="refund" @selected($walletFilterProvider === 'refund')>Возврат</option>
                                </select>
                                <div class="invalid-feedback @error('provider') d-block @enderror" data-error-for="provider">@error('provider'){{ $message }}@enderror</div>
                            </div>
                            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                                <button class="btn btn-primary payments-report-filters-submit" type="submit" id="walletHistoryFiltersApply">Применить</button>
                                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="walletHistoryFiltersReset">Сброс</button>
                            </div>
                        </div>
                    </form>
                </div>

                <table class="table table-bordered dt-columns-managed w-100" id="walletTxTable">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Дата</th>
                        <th>Тип</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th>Описание</th>
                        <th>Провайдер</th>
                        <th>Кто провёл</th>
                    </tr>
                    </thead>
                </table>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @if($activeTab === 'balance')
    <script>
        $(function() {
            $('.js-wallet-preset').on('click', function() {
                var amount = String($(this).data('amount'));
                $('#walletTopupAmount').val(amount).trigger('input');
            });

            $('#walletTopupAmount').on('input', function() {
                var value = String($(this).val());
                $('.js-wallet-preset').each(function() {
                    $(this).toggleClass('active', String($(this).data('amount')) === value);
                });
            });
        });
    </script>
    @else
    <script>
        $(function() {
            var $historyFilters = $('#wallet-history-filters');

            function clearWalletHistoryFilterErrors() {
                $historyFilters.find('[data-error-for]').text('').removeClass('d-block');
                $historyFilters.find('.is-invalid').removeClass('is-invalid');
            }

            function showWalletHistoryFilterErrors(errors) {
                clearWalletHistoryFilterErrors();
                Object.keys(errors || {}).forEach(function(field) {
                    var messages = errors[field];
                    var text = Array.isArray(messages) ? (messages[0] || '') : (messages || '');
                    var $slot = $historyFilters.find('[data-error-for="' + field + '"]');
                    $slot.text(text).addClass('d-block');
                    $historyFilters.find('[name="' + field + '"]').addClass('is-invalid');
                });
            }

            function walletHistoryFilterParams() {
                return {
                    date_from: $historyFilters.find('[name="date_from"]').val() || '',
                    date_to: $historyFilters.find('[name="date_to"]').val() || '',
                    type: $historyFilters.find('[name="type"]').val() || '',
                    status: $historyFilters.find('[name="status"]').val() || '',
                    provider: $historyFilters.find('[name="provider"]').val() || ''
                };
            }

            var dtApi = KidsCrmDataTable.create('#walletTxTable', {
                columnsSettings: {
                    persistPageLength: true,
                    defaults: {
                        id: false,
                        created_at: true,
                        type: true,
                        amount: true,
                        status: true,
                        description: true,
                        provider: false,
                        user_name: false
                    },
                    urls: {
                        get: @json(route('partner.wallet.transactions.columns-settings.get')),
                        save: @json(route('partner.wallet.transactions.columns-settings.save'))
                    },
                    toggleSelector: '.wallet-history-column-toggle',
                    csrfToken: '{{ csrf_token() }}'
                },
                dataTable: {
                    pageLength: @json((int) ($walletHistoryPageLength ?? 10)),
                    ajax: {
                        url: '/partner-wallet/transactions',
                        type: 'GET',
                        data: function(d) {
                            var filters = walletHistoryFilterParams();
                            Object.keys(filters).forEach(function(key) {
                                d[key] = filters[key];
                            });
                        },
                        error: function(xhr) {
                            if (xhr && xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                                showWalletHistoryFilterErrors(xhr.responseJSON.errors);
                            }
                        }
                    },
                    order: [[1, 'desc']],
                    language: @include('partials.datatables.ru'),
                    drawCallback: function() {
                        if (window.KidsCrmReportTableSticky) {
                            window.KidsCrmReportTableSticky.bind('#walletTxTable');
                        }
                    }
                },
                columns: [
                    { key: 'id', type: 'text', data: 'id', name: 'partner_wallet_transactions.id' },
                    { key: 'created_at', type: 'datetime', data: 'created_at', name: 'partner_wallet_transactions.created_at' },
                    { key: 'type', type: 'text', data: 'type', name: 'partner_wallet_transactions.type' },
                    { key: 'amount', type: 'money', data: 'amount', name: 'amount', searchable: false },
                    {
                        key: 'status',
                        type: 'text',
                        data: 'status',
                        name: 'partner_wallet_transactions.status',
                        render: function(value, type) {
                            if (type === 'display') {
                                return value || '';
                            }
                            return $('<div>').html(value || '').text();
                        }
                    },
                    { key: 'description', type: 'text', data: 'description', name: 'partner_wallet_transactions.description' },
                    { key: 'provider', type: 'text', data: 'provider', name: 'partner_wallet_transactions.provider' },
                    { key: 'user_name', type: 'text', data: 'user_name', name: 'user_name', orderable: false, searchable: false }
                ]
            });

            $('#walletTxTable').on('xhr.dt', function(e, settings, json, xhr) {
                if (xhr && xhr.status >= 200 && xhr.status < 300) {
                    clearWalletHistoryFilterErrors();
                }
            });

            $historyFilters.on('submit', function(e) {
                e.preventDefault();
                dtApi.reload();
            });

            $('#walletHistoryFiltersReset').on('click', function() {
                $historyFilters.find('[name="date_from"],[name="date_to"]').val('');
                $historyFilters.find('[name="type"],[name="status"],[name="provider"]').val('');
                clearWalletHistoryFilterErrors();
                dtApi.reload();
            });
        });
    </script>
    @endif
@endpush
