@extends('layouts.admin2')
@section('content')

    <script src="{{ asset('js/my-croppie.js') }}"></script>
    {{--    <script src="{{ asset('js/settings-prices-ajax.js') }}"></script>--}}

    <div class="container-fluid main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3 pb-3 text-start">Отчеты</h4>
        <div class="container-fluid">

            <div class="row justify-content-md-center">
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $activeTab == 'payments' ? 'active' : '' }}"
                           href="/admin/reports/payments" role="tab">Платежи</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $activeTab == 'debts' ? 'active' : '' }}" href="/admin/reports/debts"
                           role="tab">Задолженности</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $activeTab == 'ltv' ? 'active' : '' }}" href="/admin/reports/ltv"
                           role="tab">LTV</a>
                    </li>

                </ul>

                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade {{ $activeTab == 'ltv' ? 'show active' : '' }}" id="profile"
                         role="tabpanel">
                        <div class="container-fluid">
                            <h4 class="pt-3 text-start">LTV</h4>


                            <table class="table table-bordered" id="debts-table">
                                <thead>
                                <tr>
                                    <th>№</th>
                                    <th>Имя пользователя</th>
                                    <th>Дата первого платежа</th>
                                    <th>Дата последнего платежа</th>
                                    <th>Кол-во платежей</th>
                                    <th>Общая сумма</th>
                                </tr>
                                </thead>
                            </table>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
    <script type="text/javascript">
        // Функция для форматирования даты
        function formatDateToMonthYear(dateStr) {
            // Преобразуем строку в объект Date
            var date = new Date(dateStr);
            // Определяем массив названий месяцев
            var months = [
                'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
            ];
            // Форматируем дату в нужный вид "Месяц Год"
            return months[date.getMonth()] + ' ' + date.getFullYear();
        }

        $(function () {


            var table = $('#debts-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('ltv.getLtv') }}",
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                    {
                        data: null,
                        name: 'user_name',
                        render: function (data, type, row) {
                            var name = row.user_name ? row.user_name : 'Без имени';
                            if (row.is_enabled == 0) {
                                return '<span style="color: red;">' + name + '</span>';
                            }
                            return name;
                        }
                    },
                    {
                        data: 'first_payment_date',
                        name: 'first_payment_date',
                        render: function (data, type, row) {
                            return row.first_payment_date ? new Date(row.first_payment_date).toLocaleDateString() : 'Нет данных';
                        }
                    },
                    {
                        data: 'last_payment_date',
                        name: 'last_payment_date',
                        render: function (data, type, row) {
                            return row.last_payment_date ? new Date(row.last_payment_date).toLocaleDateString() : 'Нет данных';
                        }
                    },
                    {
                        data: 'payment_count',
                        name: 'payment_count',
                        render: function (data, type, row) {
                            return row.payment_count || 0;
                        }
                    },

                    {
                        data: 'total_price',
                        name: 'total_price',
                        render: function (data, type, row) {
                            if (type === 'display') {
                                return row.total_price + ' руб'; // Отображение значения с символом рубля
                            }
                            return parseFloat(row.total_price); // Для сортировки возвращаем число
                        }
                    }


                ],
                order: [[5, 'desc']],
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
