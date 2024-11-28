@extends('layouts.admin2')
@section('content')

    <div class=" col-md-9 main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3">Оплата сервиса</h4>




    <!-- Блок с тарифами -->
        <div class="row">



        <!-- Блок с тарифом -->
        <div class="row justify-content-center mt-3">
            <!-- Тариф -->
            <div class="col-md-6">
                <div class="card mb-4 shadow-sm">
                    <div class="card-header text-center">
                        <h4 class="my-0 font-weight-normal">1 месяц</h4>
                    </div>
                    <div class="card-body text-center">

                        <h5>2 500 ₽</h5>
                        <ul class="list-unstyled mt-3 mb-4">
                            <li>Учет до 200 пользователей</li>
                            <li>Расширенные отчеты</li>
                            <li>Приоритетная поддержка</li>
                        </ul>


                        <form action="{{route('createPaymentYookassa')}}" method="post">
                            <!-- Фиксированная сумма -->
                            @csrf
{{--                            <input type="hidden" name="client_id" value="{{ $client->id }}"> <!-- client_id передаётся скрыто -->--}}
                            <input type="hidden" name="client_id" value="1"> <!-- client_id передаётся скрыто -->
                            <input type="hidden" name="amount" value="3.00"> <!-- Укажите здесь фиксированную сумму -->
                            <button type="submit" class="btn btn-lg btn-block btn-primary">Оплатить</button>
                        </form>



                    </div>
                </div>
            </div>

            <!-- Тариф -->
            <div class="col-md-6">
                <div class="card mb-4 shadow-sm">
                    <div class="card-header text-center">
                        <h4 class="my-0 font-weight-normal">1 год</h4>
                    </div>
                    <div class="card-body text-center">

                        <h5>27 000 ₽</h5>
                        <ul class="list-unstyled mt-3 mb-4">
                            <li>Учет до 200 пользователей</li>
                            <li>Расширенные отчеты</li>
                            <li>Приоритетная поддержка</li>
                        </ul>
                        <form action="{{route('createPaymentYookassa')}}" method="post">
                            <!-- Фиксированная сумма -->
                            @csrf
                            <input type="hidden" name="client_id" value="1"> <!-- client_id передаётся скрыто -->
                            <input type="hidden" name="amount" value="2.00"> <!-- Укажите здесь фиксированную сумму -->
                            <button type="submit" class="btn btn-lg btn-block btn-primary">Оплатить</button>
                        </form>
                    </div>
                </div>
            </div>



        </div>






        <!-- Информация о получении услуги -->
        <div class="mt-5">
            <h2>Как получить доступ к сервису после оплаты?</h2>
            <p>После успешной оплаты выбранного тарифа вам будет предоставлен полный доступ к функционалу сервиса. На
                ваш email придет подтверждение оплаты.</p>
        </div>

        <!-- Пользовательское соглашение -->
        <div class="mt-5">
            <h2>Пользовательское соглашение</h2>
            <p>Перед использованием нашего сервиса, пожалуйста, ознакомьтесь с <a href="/terms">Пользовательским
                    соглашением</a>, в котором указаны условия использования и предоставления наших услуг.</p>
        </div>

        <!-- Контактная информация и реквизиты -->
        <div class="mt-5">
            <h2>Контактная информация</h2>
            <p>ИП Устьян Евгений Артурович</p>
            <ul class="list-unstyled">
                <li><strong>ИНН:</strong> 110211351590</li>
                <li><strong>ОГРНИП:</strong> 324784700017432</li>
                <li><strong>Email:</strong>kidslinkru@yandex.ru</li>
                <li><strong>Адрес:</strong> г.Сочи, ул. Урожайная 110/1</li>
            </ul>
        </div>
    </div>











    <div class="wrap-pay  wrap-umoney">
        <h5 class="pay-name mt-3 mb-3">ЮMoney</h5>
        <img class="logo" src="{{ asset('storage/umoney.png') }}">

        <form class="mb-3 mt-3" id="paymentForm" action="{{ route('payment.pay') }}" method="POST"
              onsubmit="return setOutSum();">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
            <input type="hidden" name="userId" value="{{ auth()->user()->id }}">
            <input type="hidden" name="outSum" id="outSum" value="">
            <iframe src="https://yoomoney.ru/quickpay/fundraise/button?billNumber=1YJRbAR2lJs.230810&" width="330"
                    height="50" frameborder="0" allowtransparency="true" scrolling="no"></iframe>

        </form>
        <div class="pay-fee  mb-3">Комиссия: 0 руб.</div>
    </div>

    <script>
        function setOutSum() {
            var paymentAmount = document.getElementById('paymentAmount').value;
            document.getElementById('outSum').value = paymentAmount;
            // console.log('outSum:', paymentAmount); // временная проверка
            // document.getElementById('checkOutSum2').textContent = paymentAmount; // Отображение для проверки

            return true; // продолжить отправку формы
        }
    </script>
    </div>

@endsection