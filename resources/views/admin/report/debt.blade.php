<div class="tab-content" id="myTabContent">
    <div class="tab-pane fade {{ $activeTab == 'debt' ? 'show active' : '' }}" id="profile" role="tabpanel">
        <div class="container-fluid">
            <h4 class="pt-3 text-start">Задолженности</h4>
            <div class="sum-dept-wrap alert alert-warning d-flex justify-content-between align-items-center p-3 mt-3 mb-3 rounded">
                <span class="fw-bold">Общая сумма задолженности: </span>
                <span class="fw-bold">{{$totalUnpaidPrice}} руб</span>
            </div>
            <table class="table table-bordered" id="debts-table">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Имя пользователя</th>
                    <th>Месяц</th>
                    <th>Сумма</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

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
                ajax: "{{ route('debts.getDebts') }}",
                columns: [
                    {data: 'DT_RowIndex', name: 'DT_RowIndex'},

                    {
                        data: null,
                        name: 'user_name',
                        render: function (data, type, row) {
                            if (row.user_id) {
                                return row.user_name;
                            } else {
                                return row.user_name ? row.user_name : 'Без имени';
                            }
                        }
                    },

                    {
                        data: 'month',
                        name: 'month',
                        render: function (data, type, row) {
                            // Преобразуем дату с помощью функции formatDateToMonthYear
                            return formatDateToMonthYear(row.month);
                        }
                    },

                    // {
                    //     data: 'price',
                    //     name: 'price',
                    //     render: function (data, type, row) {
                    //         return row.price;
                    //     }
                    // }

                    {
                        data: 'price',
                        name: 'price',
                        render: function (data, type, row) {
                            if (type === 'display') {
                                // Функция для форматирования числа с запятыми
                                function formatNumber(number) {
                                    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                }

                                const formattedPrice = formatNumber(row.price);
                                return `${formattedPrice} руб`; // Отображение значения с символом рубля
                            }
                            return parseFloat(row.price); // Для сортировки возвращаем число
                        }
                    }


                ],
                order: [[2, 'asc']],
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
