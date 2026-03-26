@extends('layouts.admin2')
@section('content')
    <div class="main-content">
        <h4 class="pt-3 pb-3  text-start">Платежи</h4>
        <div class="tab-pane fade {{ $activeTab == 'payments' ? 'show active' : '' }}" id="home" role="tabpanel">
            <div class="container-fluid p-0">
                {{--<h4 class="pt-3 text-start">Платежи</h4>--}}
                <div class="sum-dept-wrap alert alert-warning d-flex justify-content-between align-items-center p-3 mb-3 rounded">
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
                        <th>Способ оплаты</th>
                        <th>Чек</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

<style>
    .return-receipt-link {
        text-decoration: none;
    }

    .return-receipt-icon {
        color: #ffc107;
        transition: color .15s ease, transform .15s ease;
    }

    .return-receipt-link:hover .return-receipt-icon {
        color: #ff9800;
        transform: translateY(-1px);
    }
</style>

@section('scripts')
    <script type="text/javascript">
        $(function () {
            var table = $('#payments-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('payments.getUserPayments') }}",
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
                    {data: 'summ', name: 'summ'},

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
                    },
                    {
                        data: 'payment_provider',
                        name: 'payment_provider',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (data === 'tbank') {
                                return '<span class="badge" style="background-color:#ffdd2d !important; color:black !important;">T-Bank</span>';
                            }
                            if (data === 'robokassa') {
                                return '<span class="badge bg-secondary">Robokassa</span>';
                            }
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
                                    '<i class="fa-solid fa-receipt text-primary"></i>' +
                                    '</a>';
                            } else {
                                incomeHtml =
                                    '<span title="' + incomeTitle + '" aria-label="Чек не сформирован">' +
                                    '<i class="fa-solid fa-receipt text-secondary"></i>' +
                                    '</span>';
                            }

                            var returnHtml = '';
                            if (returnReceiptUrl) {
                                returnHtml =
                                    '<a href="' + returnReceiptUrl + '" target="_blank" rel="noopener noreferrer" ' +
                                    'class="return-receipt-link" title="Чек возврата" aria-label="Чек возврата">' +
                                    '<i class="fa-solid fa-receipt return-receipt-icon"></i>' +
                                    '</a>';
                            }

                            return '<span style="display:inline-flex; align-items:center; gap:8px;">' + incomeHtml + returnHtml + '</span>';
                        }
                    }
                ],
                order: [[5, 'desc']], // Колонка «Дата и время платежа»

                scrollX: true,
                fixedColumns: {
                    leftColumns: 2
                },
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
                }
            });
        });
    </script>
@endsection

