@extends('layouts.admin2')
@section('content')

    <style>
        .payment-page {
            max-width: 1080px;
            margin: 0 auto;
            padding: 16px 0 28px;
        }
        .payment-title {
            font-weight: 700;
            margin-bottom: 16px;
        }
        .payment-summary {
            background: linear-gradient(135deg, #f3f7ff 0%, #f8fbff 100%);
            border: 1px solid #e4edff;
            border-radius: 18px;
            padding: 18px 20px;
            margin-bottom: 22px;
            box-shadow: 0 8px 20px rgba(27, 80, 180, 0.08);
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        .summary-item-label {
            font-size: 12px;
            color: #60708f;
            margin-bottom: 2px;
        }
        .summary-item-value {
            font-size: 15px;
            font-weight: 600;
            color: #1d2a44;
            word-break: break-word;
        }
        .summary-total {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #dce7ff;
            font-weight: 600;
            color: #1d2a44;
        }
        .summary-total .value {
            font-size: 20px;
            color: #f3a12b;
            margin-left: 6px;
        }
        .payment-trust {
            margin-top: 8px;
            font-size: 12px;
            color: #66738f;
        }
        .sbp-priority-card {
            position: relative;
            border: 1px solid #b8d4ff;
            border-radius: 20px;
            padding: 20px 22px;
            background: linear-gradient(135deg, #ffffff 0%, #f4f9ff 70%);
            box-shadow: 0 12px 28px rgba(28, 84, 196, 0.15);
            max-width: 780px;
        }
        .payment-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(260px, 0.85fr);
            gap: 16px;
            align-items: start;
        }
        .payment-right-column {
            width: 100%;
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
            margin-bottom: 12px;
        }
        .sbp-title {
            font-size: 24px;
            font-weight: 700;
            color: #18253f;
            margin-bottom: 6px;
        }
        .sbp-title-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .sbp-security-icon {
            width: 18px;
            height: 18px;
            color: #f3a12b;
        }
        .sbp-subtitle {
            color: #4e5f80;
            margin-bottom: 16px;
        }
        .sbp-benefits {
            margin: 0 0 16px;
            padding-left: 18px;
            color: #314769;
        }
        .sbp-benefits li {
            margin-bottom: 6px;
        }
        .payment-btn-primary {
            width: auto;
            min-width: 240px;
            border: 0;
            border-radius: 12px;
            padding: 12px 16px;
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
            padding: 16px;
            background: #fff;
            box-shadow: 0 6px 16px rgba(32, 47, 77, 0.07);
        }
        .pay-card-name {
            font-weight: 700;
            margin-bottom: 10px;
            color: #1e2c49;
        }
        .pay-card img {
            max-height: 42px;
            width: auto;
            margin-bottom: 10px;
        }
        .tbank-logo {
            height: 70px;
            width: auto;
            object-fit: contain;
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
            .payment-page {
                padding-top: 8px;
            }
            .summary-grid {
                grid-template-columns: 1fr;
            }
            .sbp-priority-card {
                padding: 18px;
                max-width: none;
            }
            .sbp-title {
                font-size: 21px;
            }
            .payment-layout {
                grid-template-columns: 1fr;
                gap: 14px;
            }
            .payment-btn-primary {
                width: 100%;
                min-width: 0;
            }
        }
    </style>

    <div class="main-content text-start payment-page">
        <h4 class="payment-title">Оплата</h4>

        <div class="payment-summary">
            <div class="summary-grid">
                <div>
                    <div class="summary-item-label">Плательщик</div>
                    <div class="summary-item-value">{{ auth()->user()->name }}</div>
                </div>
                <div>
                    <div class="summary-item-label">Платежный период</div>
                    <div class="summary-item-value">{{ $paymentDate }}</div>
                </div>
                <div>
                    <div class="summary-item-label">Способ оплаты</div>
                    <div class="summary-item-value">Выберите ниже</div>
                </div>
            </div>
            <div class="summary-total">Итого к оплате: <span class="value">{{ number_format((int) round((float) str_replace(',', '.', (string) $outSum)), 0, ',', ' ') }} руб.</span></div>
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
                    <form action="{{ route('payment.tinkoff.sbp') }}" method="POST">
                        @csrf
                        <input type="hidden" name="partner_id" value="{{ $partnerId }}">
                        <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                        <input type="hidden" name="outSum" value="{{ $outSum }}">
                        <input type="hidden" name="paymentDate" value="{{ $paymentDate }}">
                        <input type="hidden" name="formatedPaymentDate" value="{{ $formatedPaymentDate }}">
                        <button type="submit" class="btn payment-btn-primary">Оплатить через СБП</button>
                    </form>
                    <div class="sbp-commission">Без комиссии</div>
                </div>
            @endif

            <div class="payment-right-column">
                <div class="other-methods-title">Другие способы оплаты</div>
                <div class="other-methods-grid">
                    @if(!empty($tbankAvailable))
                        <div class="pay-card">
                            <div class="pay-card-name">T‑Bank (Оплата картой)</div>
                            <img class="img-fluid d-block tbank-logo" src="{{ asset('/img/partners/tbank.png') }}" alt="Tinkoff">
                            <form action="{{ route('payment.tinkoff.pay') }}" method="POST">
                                @csrf
                                <input type="hidden" name="partner_id" value="{{ $partnerId }}">
                                <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                                <input type="hidden" name="outSum" value="{{ $outSum }}">
                                <input type="hidden" name="paymentDate" value="{{ $paymentDate }}">
                                <input type="hidden" name="formatedPaymentDate" value="{{ $formatedPaymentDate }}">
                                <button type="submit" class="btn payment-btn-secondary">Оплатить картой</button>
                            </form>
                            <div class="pay-fee">Комиссия: показывается банком</div>
                        </div>
                    @endif

                    @if(!empty($robokassaAvailable))
                        <div class="pay-card">
                            <div class="pay-card-name">Робокасса</div>
                            <img class="img-fluid d-block" src="{{ asset('/img/partners/robokassa.png') }}" alt="Робокасса">
                            <form id="paymentForm" action="{{ route('payment.pay') }}" method="POST">
                                @csrf
                                <input type="hidden" name="userName" value="{{ auth()->user()->name }}">
                                <input type="hidden" name="userId" value="{{ auth()->user()->id }}">
                                <input type="hidden" name="outSum" value="{{ $outSum }}">
                                <input type="hidden" name="paymentDate" value="{{ $paymentDate }}">
                                <input type="hidden" name="formatedPaymentDate" value="{{ $formatedPaymentDate }}">
                                <button type="submit" class="btn payment-btn-secondary">Оплатить</button>
                            </form>
                            <div class="pay-fee">Комиссия: показывается банком</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection
