@extends('layouts.admin2')
@section('content')

    <div class="main-content text-start">
        <h4 class="pt-3 text-start">Страница оплаты</h4>

        <div class="pay-info mb-3 mt-3">
            <div>Имя пользователя: <span class="bolt-text">{{ auth()->user()->name }}</span></div>
            <div>Платежный период: <span class="bolt-text">{{ $paymentDate }}</span></div>
            <div>Сумма оплаты: <span class="bolt-text">{{ $outSum }} руб.</span></div>
        </div>

        {{-- FLEX контейнер для обоих способов оплаты --}}
        <div class="d-flex flex-wrap gap-4 justify-content-start align-items-start">

            {{-- Робокасса --}}
            <div class="wrap-pay" style="max-width: 300px;">
                <h5 class="pay-name mt-3 mb-3">Робокасса</h5>
                <img src="{{ asset('storage/robokassa.svg') }}" style="max-width: 100%;">
                <form class="mb-3 mt-3" id="paymentForm" action="{{ route('payment.pay') }}" method="POST">
                    @csrf
                    <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                    <input type="hidden" name="userId" value="{{ auth()->user()->id }}">
                    <input type="hidden" name="outSum" value="{{ $outSum }}">
                    <input type="hidden" name="paymentDate" value="{{ $paymentDate }}">
                    <input type="hidden" name="formatedPaymentDate" value="{{ $formatedPaymentDate }}">
                    <button type="submit" class="btn btn-bd-primary pay-btn">Оплатить</button>
                </form>
                <div class="pay-fee mb-3">Комиссия: 0 руб.</div>
            </div>

            {{-- Тинькофф --}}
            <div class="wrap-pay" style="max-width: 300px;">
                <h5 class="pay-name mt-3 mb-3">Тинькофф</h5>
                <img src="{{ asset('storage/tinkoff.svg') }}" style="max-width: 100%;">
                <form class="mb-3 mt-3" id="tinkoffForm" action="{{ route('tinkoff.pay') }}" method="POST">
                    @csrf
                    <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                    <input type="hidden" name="userId" value="{{ auth()->user()->id }}">
                    <input type="hidden" name="outSum" value="{{ $outSum }}">
                    <input type="hidden" name="paymentDate" value="{{ $paymentDate }}">
                    <input type="hidden" name="formatedPaymentDate" value="{{ $formatedPaymentDate }}">
                    <input type="hidden" name="partnerId" value="{{ $partnerId }}">
                    <button type="submit" class="btn btn-bd-primary pay-btn">Оплатить</button>
                </form>
                <div class="pay-fee mb-3">Комиссия: 0 руб.</div>
            </div>

        </div>
    </div>

@endsection
