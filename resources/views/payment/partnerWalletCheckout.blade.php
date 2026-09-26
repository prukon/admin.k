@extends('layouts.admin2')

@section('title', 'Пополнение кошелька')

@section('content')
    @php
        $amountValue = number_format((float) $amount, 2, '.', '');
        $amountLabel = number_format((float) $amount, 2, ',', ' ');
        $showSbp = !empty($canPayAcquiringSbp);
        $showCard = !empty($canPayAcquiringCard);
        $showYookassa = !empty($canPayYookassa);
        $showOther = $showCard || $showYookassa;
        $singleColumn = !($showSbp && $showOther);
    @endphp

    <style>
        .payment-page { max-width: 1080px; margin: 0 auto; padding: 16px 0 28px; }
        .payment-title { font-weight: 700; margin-bottom: 16px; }
        .payment-summary {
            background: linear-gradient(135deg, #f3f7ff 0%, #f8fbff 100%);
            border: 1px solid #e4edff;
            border-radius: 18px;
            padding: 18px 20px;
            margin-bottom: 22px;
            box-shadow: 0 8px 20px rgba(27, 80, 180, 0.08);
        }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .summary-item-label { font-size: 12px; color: #60708f; margin-bottom: 2px; }
        .summary-item-value { font-size: 15px; font-weight: 600; color: #1d2a44; word-break: break-word; }
        .summary-total { margin-top: 12px; padding-top: 12px; border-top: 1px solid #dce7ff; font-weight: 600; color: #1d2a44; }
        .summary-total .value { font-size: 20px; color: #f3a12b; margin-left: 6px; }
        .payment-trust { margin-top: 8px; font-size: 12px; color: #66738f; }
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
        .payment-layout--sbp-only { grid-template-columns: minmax(0, 1fr); }
        .recommend-badge {
            display: inline-flex; align-items: center; font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em; color: #4a2a00;
            background: linear-gradient(135deg, #f3a12b 0%, #ffd089 100%);
            border-radius: 999px; padding: 6px 12px; margin-bottom: 12px;
        }
        .sbp-title { font-size: 24px; font-weight: 700; color: #18253f; margin-bottom: 6px; }
        .sbp-benefits { margin: 0 0 16px; padding-left: 18px; color: #314769; }
        .sbp-benefits li { margin-bottom: 6px; }
        .payment-btn-primary {
            width: auto; min-width: 240px; border: 0; border-radius: 12px; padding: 12px 16px;
            font-weight: 700; color: #3f2300;
            background: linear-gradient(135deg, #f3a12b 0%, #ffc76e 100%);
            box-shadow: 0 8px 18px rgba(243, 161, 43, 0.38);
        }
        .payment-btn-primary:hover { color: #3f2300; }
        .sbp-commission {
            display: inline-flex; margin-top: 8px; padding: 5px 10px; border-radius: 999px;
            background: #e9f8ee; color: #1f8a4d; font-size: 13px; font-weight: 700;
        }
        .other-methods-title { font-size: 16px; font-weight: 700; color: #253352; margin-bottom: 12px; }
        .other-methods-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
        .pay-card {
            border: 1px solid #e5eaf4; border-radius: 16px; padding: 16px; background: #fff;
            box-shadow: 0 6px 16px rgba(32, 47, 77, 0.07);
        }
        .pay-card-name { font-weight: 700; margin-bottom: 10px; color: #1e2c49; }
        .pay-card img, .tbank-logo { max-height: 42px; width: auto; margin-bottom: 10px; }
        .payment-btn-secondary {
            width: 100%; border-radius: 11px; padding: 10px 14px; font-weight: 600;
            border: 1px solid #c6d4eb; background: #f8fbff; color: #1f365c;
        }
        .pay-fee { margin-top: 10px; margin-bottom: 0; color: #68748f; font-size: 13px; }
        @media (max-width: 767.98px) {
            .payment-layout, .summary-grid { grid-template-columns: 1fr; }
            .payment-btn-primary, .sbp-priority-card { width: 100%; max-width: none; }
        }
    </style>

    <div class="container payment-page">
        <h1 class="h3 payment-title">Пополнение кошелька</h1>

        <div class="payment-summary">
            <div class="summary-grid">
                <div>
                    <div class="summary-item-label">Школа</div>
                    <div class="summary-item-value">{{ $partner->name }}</div>
                </div>
                <div>
                    <div class="summary-item-label">Назначение</div>
                    <div class="summary-item-value">Пополнение баланса KidsCRM</div>
                </div>
            </div>
            <div class="summary-total">Итого к оплате: <span class="value">{{ $amountLabel }} ₽</span></div>
            <div class="payment-trust">Оплата защищена банковскими протоколами безопасности. Обычный эквайринг, не мультирасчёты.</div>
        </div>

        <div class="text-danger small mb-2" data-error-for="amount">@error('amount'){{ $message }}@enderror</div>
        <div class="text-danger small mb-2" data-error-for="payment_method">@error('payment_method'){{ $message }}@enderror</div>
        <div class="text-danger small mb-2" data-error-for="partner_id">@error('partner_id'){{ $message }}@enderror</div>
        <div class="text-danger small mb-3" data-error-for="description">@error('description'){{ $message }}@enderror</div>

        @if(!$showSbp && !$showOther)
            <div class="alert alert-warning">Нет доступного способа оплаты.</div>
        @else
            <div class="payment-layout{{ $singleColumn ? ' payment-layout--sbp-only' : '' }}">
                @if($showSbp)
                    <div class="sbp-priority-card">
                        <div class="recommend-badge">Способ оплаты</div>
                        <div class="sbp-title">СБП · эквайринг</div>
                        <ul class="sbp-benefits">
                            <li>Быстрое подтверждение платежа</li>
                            <li>Не нужно вводить данные карты</li>
                        </ul>
                        <img class="img-fluid d-block mb-3 tbank-logo" src="{{ asset('/img/partners/tbank.png') }}" alt="T‑Bank">
                        <form action="{{ route('partner.wallet.topup') }}" method="POST">
                            @csrf
                            <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                            <input type="hidden" name="amount" value="{{ $amountValue }}">
                            <input type="hidden" name="payment_method" value="acquiring_sbp">
                            <button type="submit" class="btn payment-btn-primary" id="walletCheckoutSbp">Оплатить через СБП</button>
                        </form>
                        <div class="sbp-commission">От 10 ₽</div>
                    </div>
                @endif

                @if($showOther)
                    <div class="payment-right-column">
                        <div class="other-methods-title">Другие способы оплаты</div>
                        <div class="other-methods-grid">
                            @if($showCard)
                                <div class="pay-card">
                                    <div class="pay-card-name">Карта · эквайринг</div>
                                    <img class="img-fluid d-block tbank-logo" src="{{ asset('/img/partners/tbank.png') }}" alt="T‑Bank">
                                    <form action="{{ route('partner.wallet.topup') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                                        <input type="hidden" name="amount" value="{{ $amountValue }}">
                                        <input type="hidden" name="payment_method" value="acquiring_card">
                                        <button type="submit" class="btn payment-btn-secondary" id="walletCheckoutCard">Оплатить картой</button>
                                    </form>
                                    <div class="pay-fee">Форма T‑Bank</div>
                                </div>
                            @endif
                            @if($showYookassa)
                                <div class="pay-card">
                                    <div class="pay-card-name">ЮKassa</div>
                                    <img class="img-fluid d-block" src="{{ asset('/img/partners/yoomoney.png') }}" alt="ЮKassa">
                                    <form action="{{ route('partner.wallet.topup') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                                        <input type="hidden" name="amount" value="{{ $amountValue }}">
                                        <input type="hidden" name="payment_method" value="yookassa">
                                        <button type="submit" class="btn payment-btn-secondary" id="walletCheckoutYookassa">Оплатить</button>
                                    </form>
                                    <div class="pay-fee">От 1 ₽</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="mt-4">
            <a href="{{ route('partner.wallet') }}">Вернуться в кошелёк</a>
        </div>
    </div>
@endsection
