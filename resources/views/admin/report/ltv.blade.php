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
{{--@section('scripts')--}}
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
                                // Функция для форматирования числа с запятыми
                                function formatNumber(number) {
                                    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                }

                                const formattedPrice = formatNumber(row.total_price);
                                return `${formattedPrice} руб`; // Отображение значения с символом рубля
                            }
                            return parseFloat(row.total_price); // Для сортировки возвращаем число
                        }
                    }


                ],
                order: [[5, 'desc']],
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
{{--@endsection--}}