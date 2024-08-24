@extends('layouts.admin2')
@section('content')
    <div class="container">
        <h4 class="pt-3">Платежи</h4>
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
                        {data: 'user_name', name: 'user_name'},
                        {data: 'team_title', name: 'team_title'},
                        {data: 'summ', name: 'summ'},
                        {data: 'payment_month', name: 'payment_month'},
                        {data: 'operation_date', name: 'operation_date'},
                    ],
                    order: [[5, 'desc']], // Сортировка по столбцу "Дата" в порядке убывания

                    language: {
                        "processing": "Обработка...",
                        "search": "Поиск:",
                        "lengthMenu": "Показать _MENU_ записей",
                        "info": "Записи с _START_ до _END_ из _TOTAL_ записей",
                        "infoEmpty": "Записи с 0 до 0 из 0 записей",
                        "infoFiltered": "(отфильтровано из _MAX_ записей)",
                        "infoPostFix": "",
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
@endsection
