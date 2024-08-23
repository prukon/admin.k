@extends('layouts.admin2')


@section('content')
    <div class="container">
        <h2>Список платежей</h2>
        <table class="table table-bordered" id="payments-table">
            <thead>
            <tr>
                <th>№</th>
                <th>Имя ученика</th>
                <th>Группа</th>
                <th>Сумма платежа</th>
                <th>Оплаченный месяц</th>
                <th>Дата</th>
            </tr>
            </thead>
        </table>
    </div>
test1
    @section('scripts')
        test2
        <script type="text/javascript">
            console.log('test3');
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
                    ]
                });
            });
        </script>
    @endsection
@endsection