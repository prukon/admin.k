@extends('layouts.admin2')
@section('content')

    <div class="col-md-12 main-content text-start">
        <h4 class="pt-3 pb-3">Управление платежами</h4>

    {{--<div class="sum-dept-wrap alert alert-warning d-flex justify-content-between align-items-center p-3 mt-3 mb-3 rounded">--}}
    {{--<span class="fw-bold">Баланс:</span>--}}

    {{--<span class="fw-bold"> 123 руб</span>--}}
    {{--</div>--}}

    <!-- Вкладки -->
        <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ $activeTab == 'recharge' ? 'active' : '' }}"
                   href="{{ route('partner.payment.recharge') }}"
                   id="recharge-tab"
                   role="tab"
                   aria-controls="recharge"
                   aria-selected="{{ $activeTab == 'recharge' ? 'true' : 'false' }}">
                    Пополнить счет
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab == 'history' ? 'active' : '' }}"
                   href="{{ route('partner.payment.history') }}"
                   id="history-tab"
                   role="tab"
                   aria-controls="history"
                   aria-selected="{{ $activeTab == 'history' ? 'true' : 'false' }}">
                    История платежей
                </a>
            </li>
        </ul>

        <div class="tab-content mt-3" id="paymentTabsContent">
        @if($activeTab == 'recharge')
            {{------------------------------------}}
            <!-- Вкладка "Пополнить счет" -->

                <div class="tab-pane fade show active" id="recharge" role="tabpanel" aria-labelledby="recharge-tab">
                    <h4>Оплата сервиса</h4>
                    <!-- Ваш код для отображения тарифов -->
                    <!-- Блок с тарифами -->
                    <div class="row">
                        <!-- Блок с тарифом -->
                        <div class="row justify-content-center mt-3">
                            <!-- Тариф 30 дней -->
                            <div class="col-md-6">
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-header text-center">
                                        <h4 class="my-0 font-weight-normal">30 дней</h4>
                                    </div>
                                    <div class="card-body text-center">
                                        <h5>2 500 ₽</h5>
                                        <ul class="list-unstyled mt-3 mb-4">
                                            <li>Приоритетная поддержка</li>
                                            <li>Учет до 200 пользователей</li>
                                            <li>Отчеты</li>
                                        </ul>
                                        <form action="{{route('createPaymentYookassa')}}" method="post">
                                            <!-- Фиксированная сумма -->
                                            @csrf
                                            {{--                            <input type="hidden" name="client_id" value="{{ $client->id }}"> <!-- client_id передаётся скрыто -->--}}
{{--                                            <input type="hidden" name="partner_id" value="{{ $partner->id }}">--}}
                                            <input type="hidden" name="partner_id" value="1">
                                            <input type="hidden" name="amount" value="2500.00">
                                            <input type="hidden" name="days" value="29">
                                            <input type="hidden" name="description" value="Учет до 200 пользователей">

                                            <!-- Укажите здесь фиксированную сумму -->
                                            <button type="submit" class="btn btn-lg btn-block btn-primary">Оплатить
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Тариф 1 год -->
                            <div class="col-md-6">
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-header text-center">
                                        <h4 class="my-0 font-weight-normal">1 год</h4>
                                    </div>
                                    <div class="card-body text-center">

                                        <h5>27 000 ₽</h5>
                                        <ul class="list-unstyled mt-3 mb-4">
                                            <li>Приоритетная поддержка</li>
                                            <li>Учет до 200 пользователей</li>
                                            <li>Отчеты</li>
                                        </ul>
                                        <form action="{{route('createPaymentYookassa')}}" method="post">
                                            @csrf
                                            <input type="hidden" name="partner_id" value="1">
                                            <input type="hidden" name="amount" value="27000.00">
                                            <input type="hidden" name="days" value="365">
                                            <input type="hidden" name="description" value="Учет до 200 пользователей">

                                            <button type="submit" class="btn btn-lg btn-block btn-primary">Оплатить
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Информация о получении услуги -->
                        <div class="mt-5">
                            <h2>Как получить доступ к сервису после оплаты?</h2>
                            <p>После успешной оплаты выбранного тарифа вам будет предоставлен полный доступ к
                                функционалу сервиса. На
                                ваш email придет подтверждение оплаты.</p>
                        </div>

                        <!-- Пользовательское соглашение -->
                        <div class="mt-5">
                            <h2>Пользовательское соглашение</h2>
                            <p>Перед использованием сервиса, пожалуйста, ознакомьтесь с <a href="/terms">Пользовательским
                                    соглашением</a>, в котором указаны условия использования и предоставления наших
                                услуг.</p>
                        </div>

                        {{--<!-- Контактная информация и реквизиты -->--}}
                        {{--<div class="mt-5">--}}
                        {{--<h2>Контактная информация</h2>--}}
                        {{--<p>ИП Устьян Евгений Артурович</p>--}}
                        {{--<ul class="list-unstyled">--}}
                        {{--<li><strong>ИНН:</strong> 110211351590</li>--}}
                        {{--<li><strong>ОГРНИП:</strong> 324784700017432</li>--}}
                        {{--<li><strong>Email:</strong>kidslinkru@yandex.ru</li>--}}
                        {{--<li><strong>Адрес:</strong> г.Сочи, ул. Урожайная 110/1</li>--}}
                        {{--</ul>--}}
                        {{--</div>--}}
                        {{--</div>--}}

                    </div>


                {{------------------------------------}}
                @elseif($activeTab == 'history')
                    {{------------------------------------}}
                    <!-- Вкладка "История платежей" -->


                        <div class="tab-pane fade show active" id="history" role="tabpanel"
                             aria-labelledby="history-tab">
                            <h4>История платежей</h4>

                            <table id="paymentsTable" class="table table-bordered table-hover mt-3">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Партнер</th>
                                    <th>Пользователь</th>
                                    <th>Сумма</th>
                                    <th>Дата платежа</th>
                                    <th>Период оплаты</th>
                                    <th>Метод оплаты</th>
                                    <th>Статус</th>
                                </tr>
                                </thead>
                            </table>
                        </div>
                        <!-- Подключение DataTables -->
                        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css">
                        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                        <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
                        <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>


                        <script>
                            $(document).ready(function () {
                                $('#paymentsTable').DataTable({
                                    processing: true,
                                    serverSide: true,
                                    ajax: '{{ route('partner.payment.data') }}',
                                    columns: [
                                        {data: 'id', name: 'id'},
                                        {data: 'partner_name', name: 'partner_name'},
                                        {data: 'user_name', name: 'user_name'},
                                        {data: 'amount', name: 'amount'},
                                        {data: 'payment_date', name: 'payment_date'},

                                        // {data: 'payment_date', name: 'payment_date'},

                                        {data: 'payment_period', name: 'payment_period'},



                                        {data: 'payment_method', name: 'payment_method'},
                                        {
                                            data: 'payment_status',
                                            name: 'payment_status',
                                            orderable: false,
                                            searchable: false
                                        },
                                    ],
                                    order: [[4, 'desc']], // Сортировка по столбцу "Дата" в порядке убывания

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

                        {{------------------------------------}}

                    @endif
                </div>
        </div>

@endsection
