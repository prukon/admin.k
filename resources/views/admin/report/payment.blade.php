<h4 class="pt-3 text-start">Платежи</h4>
<div class="sum-dept-wrap alert alert-warning d-flex justify-content-between align-items-center p-3 mt-3 mb-3 rounded">
    <span class="fw-bold">Общая сумма платежей:</span>

    <span class="fw-bold"> {{$totalPaidPrice}} руб</span>
</div>
<table class="table table-bordered" id="payments-table">
    <thead>
    <tr>
        <th>№</th>
        <th>Имя ученика</th>
        <th>Группа</th>
        <th>Сумма платежа</th>
        <th>Оплаченный месяц</th>
        <th>Дата и время платежа</th>
        <th>Провайдер</th>
        <th>Статус возврата</th>
        <th>Действия</th>
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

@section('scripts')
    <script type="text/javascript">
        $(function () {
            var table = $('#payments-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('payments.getPayments') }}",
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex'},

                    {
                        data: null,
                        name: 'user_name',
                        render: function (data, type, row) {

                            // Если user_id существует, делаем ссылку
                            // if (row.user_id) {
                            //     return '<a href="/admin/users/' + row.user_id + '/edit">' + row.user_name + '</a>';
                            // } else {
                            //     // Если user_id нет, просто выводим имя пользователя без ссылки
                            //     return row.user_name ? row.user_name : 'Без имени';
                            // }
                            return row.user_name ? row.user_name : 'Без имени';
                        }
                    },

                    {data: 'team_title', name: 'team_title'},
                    // {data: 'summ', name: 'summ'},

                    {
                        data: 'summ',
                        name: 'summ',
                        render: function (data, type, row) {
                            if (type === 'display') {
                                // Функция для форматирования числа с запятыми
                                function formatNumber(number) {
                                    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                }

                                const formattedPrice = formatNumber(row.summ);
                                return `${formattedPrice} руб`; // Отображение значения с символом рубля
                            }
                            return parseFloat(row.summ); // Для сортировки возвращаем число
                        }
                    },


                    {
                        data: 'payment_month',
                        name: 'payment_month',
                        render: function (data, type, row) {
                            if (data) {
                                // Проверяем, находится ли дата в формате "2024-11-01"
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
                                    // Если строка уже в нужном формате, возвращаем её как есть
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
                            if (data) {
                                var date = new Date(data);
                                var day = ("0" + date.getDate()).slice(-2);
                                var month = ("0" + (date.getMonth() + 1)).slice(-2);
                                var year = date.getFullYear();
                                var hours = ("0" + date.getHours()).slice(-2);
                                var minutes = ("0" + date.getMinutes()).slice(-2);
                                var seconds = ("0" + date.getSeconds()).slice(-2);
                                return day + '.' + month + '.' + year + ' / ' + hours + ':' + minutes + ':' + seconds;
                            }
                            return data;
                        }
                    }
                    ,
                    {
                        data: 'payment_provider',
                        name: 'payment_provider',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (data === 'tbank') return '<span class="badge bg-primary">T-Bank</span>';
                            if (data === 'robokassa') return '<span class="badge bg-secondary">Robokassa</span>';
                            return data ? data : '';
                        }
                    },
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
                    },
                    {data: 'refund_action', name: 'refund_action', orderable: false, searchable: false}
                ],
                order: [[5, 'desc']], // Сортировка по столбцу "Дата" в порядке убывания

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

            // handlers: refund modal
            var refundModal = new bootstrap.Modal(document.getElementById('refundModal'));

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
                    note = 'Важно: если по платежу уже была <b>выплата партнёру</b>, возврат запрещён.<br>После успешного возврата месяц будет отмечен как <b>неоплаченный</b>.';
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
        });
    </script>
@endsection



