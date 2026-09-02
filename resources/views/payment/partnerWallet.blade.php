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
                        <form id="walletTopupForm">
                            @csrf
                            <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                            <div class="mb-3">
                                <label class="form-label" for="walletTopupAmount">Сумма, ₽</label>
                                <input type="number" step="0.01" min="1" class="form-control" id="walletTopupAmount" name="amount" required>
                                <div class="invalid-feedback" data-error-for="amount"></div>
                            </div>
                            <div class="invalid-feedback d-block" data-error-for="partner_id"></div>
                            <div class="text-danger small mb-2 d-none" id="walletTopupFormError"></div>
                            {{--<div class="mb-3">--}}
                                {{--<label class="form-label">Описание (необязательно)</label>--}}
                                {{--<input type="text" class="form-control" name="description" placeholder="Пополнение баланса">--}}
                            {{--</div>--}}
                            <button type="submit" class="btn btn-primary w-100" id="topupBtn">Оплатить</button>
                            {{--<div class="small text-muted mt-2">Оплата через YooKassa. После оплаты вы вернётесь на сайт.</div>--}}
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

            function clearWalletTopupErrors() {
                var $form = $('#walletTopupForm');
                $form.find('.is-invalid').removeClass('is-invalid');
                $form.find('[data-error-for]').text('');
                $('#walletTopupFormError').addClass('d-none').text('');
            }

            function showWalletTopupErrors(errors, fallbackMessage) {
                var $form = $('#walletTopupForm');
                var shown = false;

                if (errors && typeof errors === 'object') {
                    Object.keys(errors).forEach(function (field) {
                        var messages = errors[field];
                        var text = Array.isArray(messages) ? messages[0] : messages;
                        if (!text) {
                            return;
                        }
                        var $input = $form.find('[name="' + field + '"]');
                        var $slot = $form.find('[data-error-for="' + field + '"]');
                        if ($input.length) {
                            $input.addClass('is-invalid');
                        }
                        if ($slot.length) {
                            $slot.text(text);
                            shown = true;
                        }
                    });
                }

                if (!shown) {
                    $('#walletTopupFormError').removeClass('d-none').text(fallbackMessage || 'Ошибка сервера');
                }
            }

            // Ajax пополнение
            $('#walletTopupForm').on('submit', function(e) {
                e.preventDefault();

                clearWalletTopupErrors();
                $('#topupBtn').prop('disabled', true).text('Создаём платёж...');

                $.ajax({
                    url: '/partner-wallet/topup',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(res) {
                        if (res && res.ok && res.redirect) {
                            window.location = res.redirect;
                        } else {
                            showWalletTopupErrors(null, 'Не удалось создать платёж');
                            $('#topupBtn').prop('disabled', false).text('Оплатить');
                        }
                    },
                    error: function(xhr) {
                        var json = xhr.responseJSON || {};
                        var msg = json.message ? json.message : 'Ошибка сервера';
                        showWalletTopupErrors(json.errors, msg);
                        $('#topupBtn').prop('disabled', false).text('Оплатить');
                    }
                });
            });
        });
    </script>
@endpush
