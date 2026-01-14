@extends('layouts.admin2')
@section('content')

    <div class="main-content">
        <h4 class="pt-3 pb-3  text-start">Отчеты</h4>
            <div class="">
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $activeTab == 'payment' ? 'active' : '' }}" href="/admin/reports/payments"
                           role="tab">Платежи</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $activeTab == 'debt' ? 'active' : '' }}" href="/admin/reports/debts"
                           role="tab">Задолженности</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $activeTab == 'ltv' ? 'active' : '' }}" href="/admin/reports/ltv"
                           role="tab">LTV</a>
                    </li>
                    @can('reports-payment-intents-view')
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{ $activeTab == 'payment-intents' ? 'active' : '' }}" href="/admin/reports/payment-intents"
                               role="tab">Платежные запросы</a>
                        </li>
                    @endcan
                </ul>


                <div class="tab-content">
                @if($activeTab === 'payment')
                    <!-- Контент вкладки платежи -->
                    @include('admin.report.payment',
                    ['totalPaidPrice' => $totalPaidPrice,
                    ])
                @elseif($activeTab === 'debt')
                    <!-- Контент вкладки задолженности -->
                    @include('admin.report.debt',
                    ['totalUnpaidPrice' => $totalUnpaidPrice,
                    ])
                @elseif($activeTab === 'ltv')
                    <!-- Контент вкладки ltv -->
                        @include('admin.report.ltv',
                        [
                      ]
                        )
                @elseif($activeTab === 'payment-intents')
                    <!-- Контент вкладки платежные запросы -->
                    @include('admin.report.payment_intents', [
                        'filters' => $filters ?? [],
                    ])
                    @endif
                </div>
            </div>
    </div>
    <!-- Модальное окно логов -->
    @include('includes.logModal')

@endsection
