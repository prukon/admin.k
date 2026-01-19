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
            @if(!empty($robokassaAvailable))
            <div class="wrap-pay" style="max-width: 300px;">
                <h5 class="pay-name mt-3 mb-3">Робокасса</h5>
                <img  class="img-fluid d-block mx-auto" src="{{ asset('/img/partners/robokassa.png') }}">
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
            @endif



            {{-- T‑Bank (мультирасчёты) --}}
            @if(!empty($tbankAvailable))
            <div class="wrap-pay" style="max-width: 300px;">
                <h5 class="pay-name mt-3 mb-3">T‑Bank (мультирасчёты)</h5>
                <img class="img-fluid d-block mx-auto" src="{{ asset('/img/partners/tbank.png') }}" alt="Tinkoff">
                <form class="mb-3 mt-3" action="{{ route('payment.tinkoff.pay') }}" method="POST">
                    @csrf
                    {{-- обязательные данные --}}
                    <input type="hidden" name="partner_id" value="{{ $partnerId }}">
                    <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                    <input type="hidden" name="outSum" value="{{ $outSum }}"> {{-- рубли, сконвертим на бэке --}}
                    <input type="hidden" name="paymentDate" value="{{ $paymentDate }}">
                    <input type="hidden" name="formatedPaymentDate" value="{{ $formatedPaymentDate }}">
                    {{-- опционально: метод оплаты (если хочешь разделять card/sbp/tpay). Можно убрать. --}}
                    {{-- <input type="hidden" name="method" value="card"> --}}

                    <button type="submit" class="btn btn-bd-primary pay-btn">Оплатить</button>
                </form>
                <div class="pay-fee mb-3">Комиссия: показывается банком</div>
            </div>

            <div class="wrap-pay" style="max-width: 300px;">
                <h5 class="pay-name mt-3 mb-3">T‑Bank СБП (QR)</h5>
                <img class="img-fluid d-block mx-auto" src="{{ asset('/img/partners/tbank.png') }}" alt="Tinkoff">
                <form class="mb-3 mt-3" action="{{ route('payment.tinkoff.sbp') }}" method="POST">
                    @csrf
                    <input type="hidden" name="partner_id" value="{{ $partnerId }}">
                    <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                    <input type="hidden" name="outSum" value="{{ $outSum }}">
                    <input type="hidden" name="paymentDate" value="{{ $paymentDate }}">
                    <input type="hidden" name="formatedPaymentDate" value="{{ $formatedPaymentDate }}">
                    <button type="submit" class="btn btn-bd-primary pay-btn">Оплатить по СБП</button>
                </form>
                <div class="pay-fee mb-3">Комиссия: показывается банком</div>
            </div>
            @endif

        </div>
    </div>

@endsection
