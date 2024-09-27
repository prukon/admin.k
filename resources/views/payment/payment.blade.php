@extends('layouts.admin2')
@section('content')

    <div class=" col-md-9 main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3">Страница оплаты</h4>
        <div class="pay-info mb-3 mt-3">
            <div>Имя пользователя: <span class="bolt-text">{{ auth()->user()->name }}</span></div>
            <div>Платежный период: <span class="bolt-text">{{ $paymentDate }}</span></div>
            <div>Сумма оплаты: <span class="bolt-text">{{ $outSum }} руб.</span></div>
        </div>
        <div class="wrap-pay ">
            <h5 class="pay-name mt-3 mb-3">Робокасса</h5>
            <img src="{{ asset('storage/robokassa.svg') }}">
            <form class="mb-3 mt-3" id="paymentForm" action="{{ route('payment.pay') }}" method="POST">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                <input type="hidden" name="userId" value="{{ auth()->user()->id }}">
                <input type="hidden" name="outSum" value="{{ $outSum }}">
                <input type="hidden" name="paymentDate" value="{{ $paymentDate }}">
                <button type="submit" class="btn btn-bd-primary pay-btn">Оплатить</button>
            </form>
            <div class="pay-fee  mb-3">Комиссия: 0 руб.</div>
        </div>


        <script>
            document.addEventListener('DOMContentLoaded', function () {

            });
        </script>


    </div>

@endsection