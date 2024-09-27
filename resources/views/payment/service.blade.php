@extends('layouts.admin2')
@section('content')

    <div class=" col-md-9 main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3">Оплата сервиса</h4>

        <div class="wrap-pay  wrap-umoney">
            <h5 class="pay-name mt-3 mb-3">ЮMoney</h5>
            <img class="logo" src="{{ asset('storage/umoney.png') }}">

            <form class="mb-3 mt-3" id="paymentForm" action="{{ route('payment.pay') }}" method="POST"  onsubmit="return setOutSum();">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                <input type="hidden" name="userId" value="{{ auth()->user()->id }}">
                <input type="hidden" name="outSum" id="outSum" value="">


                <iframe src="https://yoomoney.ru/quickpay/fundraise/button?billNumber=1YJRbAR2lJs.230810&" width="330" height="50" frameborder="0" allowtransparency="true" scrolling="no"></iframe>

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

            <script>
                document.addEventListener('DOMContentLoaded', function () {

                });
            </script>


    </div>

@endsection