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
                        ['ltvPrice' => $ltvPrice,

                        ])
                    @endif
                </div>
            </div>
    </div>
    <!-- Модальное окно логов -->
    @include('includes.logModal')
    <!-- Модальное окно подтверждения удаления -->
    @include('includes.modal.confirmDeleteModal')
    <!-- Модальное окно успешного обновления данных -->
    @include('includes.modal.successModal')
    <!-- Модальное окно ошибки -->
    @include('includes.modal.errorModal')

    <link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>


@endsection
