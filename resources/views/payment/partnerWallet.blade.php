@extends('layouts.admin2')

@section('title','Кошелёк партнёра')

@section('content')
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 m-0">Кошелёк</h1>
            <div>
                Баланс: <span id="walletBalance">{{ number_format(((int) ($partner->wallet_balance_cents ?? 0)) / 100, 2, ',', ' ') }}</span> ₽
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">Пополнить баланс</div>
                    <div class="card-body">
                        <form id="walletTopupForm" method="get" action="{{ route('partner.wallet.checkout') }}">
                            <div class="mb-3">
                                <label class="form-label" for="walletTopupAmount">Сумма, ₽</label>
                                <input type="number" step="0.01" min="1" class="form-control @error('amount') is-invalid @enderror" id="walletTopupAmount" name="amount" value="{{ old('amount') }}" required>
                                <div class="invalid-feedback @error('amount') d-block @enderror" data-error-for="amount">@error('amount'){{ $message }}@enderror</div>
                            </div>
                            <div class="invalid-feedback d-block" data-error-for="partner_id">@error('partner_id'){{ $message }}@enderror</div>
                            <div class="invalid-feedback d-block" data-error-for="description">@error('description'){{ $message }}@enderror</div>
                            <div class="invalid-feedback d-block" data-error-for="payment_method">@error('payment_method'){{ $message }}@enderror</div>
                            @if($canPayAcquiringSbp || $canPayAcquiringCard || $canPayYookassa)
                            <button type="submit" class="btn btn-primary w-100" id="topupBtn">Перейти к оплате</button>
                            @else
                            <div class="alert alert-warning">Нет доступного способа оплаты.</div>
                            <button type="submit" class="btn btn-primary w-100" id="topupBtn" disabled>Перейти к оплате</button>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>История транзакций</span>
                        <button class="btn btn-sm btn-outline-secondary" id="reloadTable">Обновить</button>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped" id="walletTxTable" style="width:100%">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Тип</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th>Дата</th>
                            </tr>
                            </thead>
                        </table>
                        {{--<div class="small text-muted mt-2">Платёж меняет статус после подтверждения вебхуком.</div>--}}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            // DataTable
            var txTable = $('#walletTxTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/partner-wallet/transactions',
                    type: 'GET'
                },
                columns: [
                    { data: 'id', name: 'id', width: '60px' },
                    { data: 'type', name: 'type' },
                    { data: 'amount', name: 'amount' },
                    { data: 'status', name: 'status', orderable: false, searchable: false },
                    { data: 'created_at', name: 'created_at' },
                ],

                language: {
                    // url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ru.json",
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



                order: [[0, 'desc']]
            });

            $('#reloadTable').on('click', function() {
                txTable.ajax.reload(null, false);
            });

        });
    </script>
@endpush
