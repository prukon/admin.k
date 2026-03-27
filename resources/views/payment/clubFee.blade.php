@extends('layouts.admin2')
@section('content')

    <style>
        .payment-page {
            max-width: 1080px;
            margin: 0 auto;
            padding: 8px 0 20px;
        }
        .payment-title {
            font-weight: 700;
            margin-bottom: 10px;
        }
        .payment-summary {
            background: linear-gradient(135deg, #f3f7ff 0%, #f8fbff 100%);
            border: 1px solid #e4edff;
            border-radius: 18px;
            padding: 14px 16px;
            margin-bottom: 14px;
            box-shadow: 0 8px 20px rgba(27, 80, 180, 0.08);
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .summary-item-label {
            font-size: 12px;
            color: #60708f;
            margin-bottom: 4px;
        }
        .summary-item-value {
            font-size: 15px;
            font-weight: 600;
            color: #1d2a44;
        }
        .amount-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .amount-input-wrap {
            display: inline-flex;
            align-items: center;
            border: 1px solid #d2dff5;
            border-radius: 10px;
            background: #fff;
            padding: 0 10px;
            min-width: 190px;
        }
        .amount-input {
            border: 0;
            outline: none;
            padding: 8px 0;
            width: 120px;
            font-weight: 700;
            color: #1d2a44;
            background: transparent;
        }
        .amount-input-currency {
            color: #60708f;
            font-size: 13px;
            margin-left: 6px;
        }
        .amount-error {
            margin-top: 6px;
            color: #bf2f2f;
            font-size: 12px;
            display: none;
        }
        .payment-trust {
            margin-top: 8px;
            font-size: 12px;
            color: #66738f;
        }
        .payment-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(260px, 0.85fr);
            gap: 16px;
            align-items: start;
        }
        .sbp-priority-card {
            border: 1px solid #f4c67f;
            border-radius: 20px;
            padding: 14px 16px;
            background: #ffffff;
            box-shadow: 0 10px 22px rgba(243, 161, 43, 0.2);
            max-width: 780px;
        }
        .recommend-badge {
            display: inline-flex;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #4a2a00;
            background: linear-gradient(135deg, #f3a12b 0%, #ffd089 100%);
            border-radius: 999px;
            padding: 6px 12px;
            margin-bottom: 10px;
        }
        .sbp-title-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .sbp-title {
            font-size: 21px;
            font-weight: 700;
            color: #18253f;
            margin-bottom: 0;
        }
        .sbp-security-icon {
            width: 18px;
            height: 18px;
            color: #f3a12b;
        }
        .sbp-benefits {
            margin: 0 0 10px;
            padding-left: 18px;
            color: #314769;
            font-size: 14px;
        }
        .sbp-benefits li {
            margin-bottom: 4px;
        }
        .tbank-logo {
            height: 70px;
            width: auto;
            object-fit: contain;
        }
        .payment-btn-primary {
            width: auto;
            min-width: 210px;
            border: 0;
            border-radius: 12px;
            padding: 10px 14px;
            font-weight: 700;
            color: #3f2300;
            background: linear-gradient(135deg, #f3a12b 0%, #ffc76e 100%);
            transition: transform .15s ease, box-shadow .15s ease;
            box-shadow: 0 8px 18px rgba(243, 161, 43, 0.38);
        }
        .payment-btn-primary:hover {
            color: #3f2300;
            transform: translateY(-1px);
            box-shadow: 0 12px 22px rgba(243, 161, 43, 0.44);
        }
        .sbp-commission {
            display: inline-flex;
            margin-top: 8px;
            padding: 5px 10px;
            border-radius: 999px;
            background: #e9f8ee;
            color: #1f8a4d;
            font-size: 13px;
            font-weight: 700;
        }
        .other-methods-title {
            font-size: 16px;
            font-weight: 700;
            color: #253352;
            margin-bottom: 12px;
        }
        .other-methods-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .pay-card {
            border: 1px solid #e5eaf4;
            border-radius: 16px;
            padding: 14px;
            background: #fff;
            box-shadow: 0 6px 16px rgba(32, 47, 77, 0.07);
        }
        .pay-card-name {
            font-weight: 700;
            margin-bottom: 8px;
            color: #1e2c49;
        }
        .pay-card img {
            max-height: 42px;
            width: auto;
            margin-bottom: 10px;
        }
        .payment-btn-secondary {
            width: 100%;
            border-radius: 11px;
            padding: 10px 14px;
            font-weight: 600;
            border: 1px solid #c6d4eb;
            background: #f8fbff;
            color: #1f365c;
        }
        .payment-btn-secondary:hover {
            background: #edf4ff;
        }
        .pay-fee {
            margin-top: 10px;
            margin-bottom: 0;
            color: #68748f;
            font-size: 13px;
        }
        @media (max-width: 767.98px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
            .payment-layout {
                grid-template-columns: 1fr;
                gap: 14px;
            }
            .payment-btn-primary {
                width: 100%;
                min-width: 0;
            }
            .sbp-priority-card {
                max-width: none;
            }
        }
    </style>

    <div class="main-content text-start payment-page">
        <h4 class="payment-title">Оплата клубного взноса</h4>

        <div class="payment-summary">
            <div class="summary-grid">
                <div>
                    <div class="summary-item-label">Плательщик</div>
                    <div class="summary-item-value">{{ auth()->user()->name }}</div>
                </div>
                <div>
                    <div class="summary-item-label">Сумма оплаты</div>
                    <div class="amount-row">
                        <div class="amount-input-wrap">
                            <input
                                id="paymentAmount"
                                class="amount-input"
                                type="number"
                                min="1"
                                step="0.01"
                                inputmode="decimal"
                                placeholder="Введите сумму"
                                aria-describedby="paymentAmountError"
                            >
                            <span class="amount-input-currency">руб.</span>
                        </div>
                    </div>
                    <div id="paymentAmountError" class="amount-error"></div>
                </div>
            </div>
            <div class="payment-trust">Оплата защищена банковскими протоколами безопасности.</div>
        </div>

        <div class="payment-layout">
            @if(!empty($tbankSbpAvailable))
                <div class="sbp-priority-card">
                    <div class="recommend-badge">Рекомендуемый способ</div>
                    <div class="sbp-title-row">
                        <div class="sbp-title">Оплата через СБП</div>
                        <svg class="sbp-security-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 10V7a5 5 0 0 1 10 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <rect x="5" y="10" width="14" height="10" rx="2.5" stroke="currentColor" stroke-width="1.8"/>
                            <circle cx="12" cy="15" r="1.5" fill="currentColor"/>
                        </svg>
                    </div>
                    <ul class="sbp-benefits">
                        <li>Быстрое подтверждение платежа</li>
                        <li>Не нужно вводить данные карты</li>
                    </ul>
                    <img class="img-fluid d-block mb-3 tbank-logo" src="{{ asset('/img/partners/tbank.png') }}" alt="Tinkoff">
                    <form class="payment-form" action="{{ route('payment.tinkoff.sbp') }}" method="POST" onsubmit="return validateAndSetAmount(this);">
                        @csrf
                        <input type="hidden" name="partner_id" value="{{ $partnerId }}">
                        <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                        <input type="hidden" name="outSum" class="outSum-field" value="">
                        <input type="hidden" name="paymentDate" value="Клубный взнос">
                        <button type="submit" class="btn payment-btn-primary">Оплатить через СБП</button>
                    </form>
                    <div class="sbp-commission">Без комиссии</div>
                </div>
            @endif

            <div>
                <div class="other-methods-title">Другие способы оплаты</div>
                <div class="other-methods-grid">
                    @if(!empty($tbankAvailable))
                        <div class="pay-card">
                            <div class="pay-card-name">T‑Bank (Оплата картой)</div>
                            <img class="img-fluid d-block tbank-logo" src="{{ asset('/img/partners/tbank.png') }}" alt="Tinkoff">
                            <form class="payment-form" action="{{ route('payment.tinkoff.pay') }}" method="POST" onsubmit="return validateAndSetAmount(this);">
                                @csrf
                                <input type="hidden" name="partner_id" value="{{ $partnerId }}">
                                <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                                <input type="hidden" name="outSum" class="outSum-field" value="">
                                <input type="hidden" name="paymentDate" value="Клубный взнос">
                                <button type="submit" class="btn payment-btn-secondary">Оплатить картой</button>
                            </form>
                            <div class="pay-fee">Комиссия: показывается банком</div>
                        </div>
                    @endif

                    @if(!empty($robokassaAvailable))
                        <div class="pay-card">
                            <div class="pay-card-name">Робокасса</div>
                            <img class="img-fluid d-block" src="{{ asset('/img/partners/robokassa.png') }}" alt="Робокасса">
                            <form class="payment-form" action="{{ route('payment.pay') }}" method="POST" onsubmit="return validateAndSetAmount(this);">
                                @csrf
                                <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                                <input type="hidden" name="userId" value="{{ auth()->user()->id }}">
                                <input type="hidden" name="outSum" class="outSum-field" value="">
                                <input type="hidden" name="paymentDate" value="Клубный взнос">
                                <button type="submit" class="btn payment-btn-secondary">Оплатить</button>
                            </form>
                            <div class="pay-fee">Комиссия: 0 руб.</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        function validateAndSetAmount(form) {
            var paymentAmountInput = document.getElementById('paymentAmount');
            var paymentAmount = paymentAmountInput.value;
            var paymentAmountError = document.getElementById('paymentAmountError');

            paymentAmountError.style.display = 'none';
            paymentAmountError.textContent = '';

            if (!paymentAmount || parseFloat(paymentAmount) <= 0) {
                paymentAmountError.textContent = 'Пожалуйста, укажите сумму оплаты больше 0.';
                paymentAmountError.style.display = 'block';
                paymentAmountInput.focus();
                return false;
            }

            if (!/^\d+(\.\d{1,2})?$/.test(paymentAmount)) {
                paymentAmountError.textContent = 'Сумма должна быть в формате 100 или 100.50.';
                paymentAmountError.style.display = 'block';
                paymentAmountInput.focus();
                return false;
            }

            var outSumField = form.querySelector('.outSum-field');
            outSumField.value = paymentAmount;

            return true;
        }
    </script>

@endsection