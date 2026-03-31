@extends('layouts.admin2')
@section('content')
    <div class="main-content">
        <h4 class="pt-3 pb-3  text-start">Отчеты</h4>
        <div class="">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeTab == 'payment' ? 'active' : '' }}" href="/admin/reports/payments"
                        role="tab">Все платежи</a>
                </li>

                {{-- НОВАЯ ВКЛАДКА --}}
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeTab == 'payment-monthly' ? 'active' : '' }}"
                        href="/admin/reports/payments/monthly" role="tab">Платежи по месяцам</a>
                </li>

                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeTab == 'ltv' ? 'active' : '' }}" href="/admin/reports/ltv"
                        role="tab">Платежи по ученикам</a>
                </li>

                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeTab == 'debt' ? 'active' : '' }}" href="/admin/reports/debts"
                        role="tab">Задолженности</a>
                </li>

                @can('reports.payment.intents.view')
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $activeTab == 'payment-intents' ? 'active' : '' }}"
                            href="/admin/reports/payment-intents" role="tab">Платежные запросы</a>
                    </li>
                @endcan

                @can('reports.fiscal.receipts.view')
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $activeTab == 'fiscal-receipts' ? 'active' : '' }}"
                            href="/admin/reports/fiscal-receipts" role="tab">Чеки</a>
                    </li>
                @endcan
            </ul>


            <div class="tab-content">
                @if ($activeTab === 'payment')
                    <!-- Контент вкладки платежи -->
                    @include('admin.report.payment', [
                        'totalPaidPrice' => $totalPaidPrice,
                        'tbankEnabled' => $tbankEnabled ?? false,
                        'filters' => $filters ?? [],
                        'paymentsFilterUser' => $paymentsFilterUser ?? null,
                        'paymentsFilterTeam' => $paymentsFilterTeam ?? null,
                    ])
                @elseif($activeTab === 'payment-monthly')
                    @include('admin.report.payment_monthly', [
                        'totalPaidPrice' => $totalPaidPrice,
                        'filters' => $filters ?? [],
                        'paymentsFilterUser' => $paymentsFilterUser ?? null,
                        'paymentsFilterTeam' => $paymentsFilterTeam ?? null,
                    ])
                @elseif($activeTab === 'debt')
                    @include('admin.report.debt', [
                        'totalUnpaidPrice' => $totalUnpaidPrice,
                        'filters' => $filters ?? [],
                        'paymentsFilterUser' => $paymentsFilterUser ?? null,
                        'paymentsFilterTeam' => $paymentsFilterTeam ?? null,
                    ])
                @elseif($activeTab === 'ltv')
                    @include('admin.report.ltv', [
                        'totalPaidPrice' => $totalPaidPrice,
                        'filters' => $filters ?? [],
                        'paymentsFilterUser' => $paymentsFilterUser ?? null,
                        'paymentsFilterTeam' => $paymentsFilterTeam ?? null,
                    ])
                @elseif($activeTab === 'payment-intents')
                    <!-- Контент вкладки платежные запросы -->
                    @include('admin.report.payment_intents', [
                        'filters' => $filters ?? [],
                    ])
                @elseif($activeTab === 'fiscal-receipts')
                    <!-- Контент вкладки чеки -->
                    @include('admin.report.fiscal_receipts', [
                        'filters' => $filters ?? [],
                    ])
                @endif
            </div>
        </div>
    </div>
    <!-- Модальное окно логов -->
    @include('includes.logModal')
@endsection
