    @extends('layouts.admin2')
    @section('content')

        <script src="{{ asset('js/my-croppie.js') }}"></script>
{{--        <script src="{{ asset('js/settings-prices-ajax.js') }}"></script>--}}

        <div class="container-fluid main-content" xmlns="http://www.w3.org/1999/html">
            <h4 class="pt-3 pb-3  text-start">Отчеты</h4>
            <div class="container-fluid">

                <div class="row justify-content-md-center">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{ $activeTab == 'payments' ? 'active' : '' }}" href="/admin/reports/payments" role="tab">Платежи</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{ $activeTab == 'debts' ? 'active' : '' }}" href="/admin/reports/debts" role="tab">Задолженности</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{ $activeTab == 'ltv' ? 'active' : '' }}" href="/admin/reports/ltv" role="tab">LTV</a>
                        </li>
                    </ul>

                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade {{ $activeTab == 'payments' ? 'show active' : '' }}" id="home" role="tabpanel">
                            <div class="container-fluid">
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
                                    </tr>
                                    </thead>
                                </table>
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
                                                        if (row.user_id) {
                                                            return '<a href="/admin/users/' + row.user_id + '/edit">' + row.user_name + '</a>';
                                                        } else {
                                                            // Если user_id нет, просто выводим имя пользователя без ссылки
                                                            return row.user_name ? row.user_name : 'Без имени';
                                                        }
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
                                            ],
                                            order: [[5, 'desc']], // Сортировка по столбцу "Дата" в порядке убывания

                                            language: {
                                                "processing": "Обработка...",
                                                "search": "Поиск:",
                                                "lengthMenu": "Показать _MENU_ записей",
                                                "info": "Записи с _START_ до _END_ из _TOTAL_ записей",
                                                "infoEmpty": "Записи с 0 до 0 из 0 записей",
                                                "infoFiltered": "(отфильтровано из _MAX_ записей)",
                                                "loadingRecords": "Загрузка записей...",
                                                "zeroRecords": "Записи отсутствуют.",
                                                "emptyTable": "В таблице отсутствуют данные",
                                                "paginate": {
                                                    "first": "Первая",
                                                    "previous": "Предыдущая",
                                                    "next": "Следующая",
                                                    "last": "Последняя"
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
                        </div>
                    </div>
                </div>

            </div>
        </div>

    @endsection
