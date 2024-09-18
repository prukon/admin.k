@extends('layouts.admin2')
@section('content')

    <script src="{{ asset('js/my-croppie.js') }}"></script>
    <script src="{{ asset('js/settings-prices-ajax.js') }}"></script>

    <div class="container-fluid main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3">Отчеты</h4>
        <div class="container-fluid">

            <div class="row justify-content-md-center">
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $activeTab == 'payments' ? 'active' : '' }}" href="/admin/reports/payments" role="tab">Платежи</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $activeTab == 'debts' ? 'active' : '' }}" href="/admin/reports/debts" role="tab">Задолженности</a>
                    </li>
                </ul>

                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade {{ $activeTab == 'debts' ? 'show active' : '' }}" id="profile" role="tabpanel">
                        <div class="container-fluid">
                            <h4 class="pt-3">Задолженности</h4>
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
            </div>
        </div>
    </div>

@endsection

@section('scripts')
    <script type="text/javascript">
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
                            // Проверяем наличие user_id, если есть, делаем ссылку
                            if (row.user_id) {
                                return '<a href="/admin/users/' + row.user_id + '/edit">' + row.user_name + '</a>';
                            } else {
                                // Если user_id нет, просто выводим имя пользователя
                                return row.user_name ? row.user_name : 'Без имени';
                            }
                        }
                    },

                    {data: 'month', name: 'month'},
                    {
                        data: 'price',
                        name: 'price',
                        render: function (data, type, row) {
                            // Используем parseInt для удаления дробной части и добавляем " руб" в конце
                            return parseInt(row.price) + ' руб';
                        }
                    }
                ],
                order: [[3, 'desc']], // Сортировка по столбцу "Сумма" в порядке убывания

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
