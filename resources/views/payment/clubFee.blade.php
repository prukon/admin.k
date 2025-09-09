@extends('layouts.admin2')
@section('content')
    <style>
        .wrap-club-fee-pay img {

        }
    </style>

    <div class="main-content text-start">

    <h4 class="pt-3 pb-3">Оплата клубного взноса</h4>
        <div class="pay-info mb-3">
            <div>Имя пользователя: <span class="bolt-text">{{ auth()->user()->name }}</span></div>
            <div class="mt-3">Сумма оплаты: <span class="bolt-text"><input id="paymentAmount" type="number" min="1"> руб.</span>
            </div>
        </div>
            <div class="text-center ">
                <div class="wrap-pay mt-3 wrap-club-fee-pay">
                <h5 class="pay-name mt-3 mb-3">Робокасса</h5>
                <img   class="img-fluid d-block mx-auto" src="{{ asset('/img/partners/robokassa.png') }}">

                <form class="mb-3 mt-3" id="paymentForm" action="{{ route('payment.pay') }}" method="POST"
                      onsubmit="return setOutSum();">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                    <input type="hidden" name="userId" value="{{ auth()->user()->id }}">
                    <input type="hidden" name="outSum" id="outSum" value="">

                    <button type="submit" class="btn btn-bd-primary pay-btn">Оплатить</button>
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
    </div>

@endsection