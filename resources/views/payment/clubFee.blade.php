@extends('layouts.admin2')
@section('content')

    <div class="main-content text-start">
        <h4 class="pt-3 text-start">Оплата клубного взноса</h4>

        <div class="pay-info mb-3 mt-3">
            <div>Имя пользователя: <span class="bolt-text">{{ auth()->user()->name }}</span></div>
            <div class="mt-3">Сумма оплаты: <span class="bolt-text"><input id="paymentAmount" type="number" min="1" step="0.01"> руб.</span></div>
        </div>

        {{-- FLEX контейнер для всех способов оплаты --}}
        <div class="d-flex flex-wrap gap-4 justify-content-start align-items-start">

            {{-- Робокасса --}}
            @if(!empty($robokassaAvailable))
            <div class="wrap-pay" style="max-width: 300px;">
                <h5 class="pay-name mt-3 mb-3">Робокасса</h5>
                <img class="img-fluid d-block mx-auto" src="{{ asset('/img/partners/robokassa.png') }}">
                <form class="mb-3 mt-3 payment-form" action="{{ route('payment.pay') }}" method="POST" onsubmit="return validateAndSetAmount(this);">
                    @csrf
                    <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                    <input type="hidden" name="userId" value="{{ auth()->user()->id }}">
                    <input type="hidden" name="outSum" class="outSum-field" value="">
                    <input type="hidden" name="paymentDate" value="Клубный взнос">
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
                <form class="mb-3 mt-3 payment-form" action="{{ route('payment.tinkoff.pay') }}" method="POST" onsubmit="return validateAndSetAmount(this);">
                    @csrf
                    <input type="hidden" name="partner_id" value="{{ $partnerId }}">
                    <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                    <input type="hidden" name="outSum" class="outSum-field" value="">
                    <input type="hidden" name="paymentDate" value="Клубный взнос">
                    <button type="submit" class="btn btn-bd-primary pay-btn">Оплатить</button>
                </form>
                <div class="pay-fee mb-3">Комиссия: показывается банком</div>
            </div>

            {{-- T‑Bank СБП (QR) --}}
            @if(!empty($tbankSbpAvailable))
                <div class="wrap-pay" style="max-width: 300px;">
                    <h5 class="pay-name mt-3 mb-3">T‑Bank СБП (QR)</h5>
                    <img class="img-fluid d-block mx-auto" src="{{ asset('/img/partners/tbank.png') }}" alt="Tinkoff">
                    <form class="mb-3 mt-3 payment-form" action="{{ route('payment.tinkoff.sbp') }}" method="POST" onsubmit="return validateAndSetAmount(this);">
                        @csrf
                        <input type="hidden" name="partner_id" value="{{ $partnerId }}">
                        <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                        <input type="hidden" name="outSum" class="outSum-field" value="">
                        <input type="hidden" name="paymentDate" value="Клубный взнос">
                        <button type="submit" class="btn btn-bd-primary pay-btn">Оплатить по СБП</button>
                    </form>
                    <div class="pay-fee mb-3">Комиссия: показывается банком</div>
                </div>
            @endif
            @endif

        </div>
    </div>

    <script>
        function validateAndSetAmount(form) {
            var paymentAmount = document.getElementById('paymentAmount').value;
            
            // Проверка на пустое значение или 0
            if (!paymentAmount || parseFloat(paymentAmount) <= 0) {
                alert('Пожалуйста, укажите сумму оплаты');
                return false;
            }
            
            // Устанавливаем сумму в скрытое поле текущей формы
            var outSumField = form.querySelector('.outSum-field');
            outSumField.value = paymentAmount;
            
            return true; // продолжить отправку формы
        }
    </script>

@endsection