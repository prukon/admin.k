@php
    $seller = is_array($invoice->seller) ? $invoice->seller : [];
    $buyer = is_array($invoice->buyer) ? $invoice->buyer : [];
    $amountLabel = number_format(((int) $invoice->amount_cents) / 100, 2, ',', ' ');
    $issuedOn = $invoice->issued_on?->format('d.m.Y') ?? '';
    $supplierLine = trim(implode(', ', array_filter([
        (string) ($seller['name'] ?? ''),
        !empty($seller['inn']) ? 'ИНН '.$seller['inn'] : '',
        !empty($seller['ogrnip']) ? 'ОГРНИП '.$seller['ogrnip'] : '',
        (string) ($seller['address'] ?? ''),
    ], static fn (string $part): bool => $part !== '')));
    $buyerLine = trim(implode(', ', array_filter([
        (string) ($buyer['name'] ?? ''),
        !empty($buyer['inn']) ? 'ИНН '.$buyer['inn'] : '',
        !empty($buyer['kpp']) ? 'КПП '.$buyer['kpp'] : '',
        (string) ($buyer['address'] ?? ''),
    ], static fn (string $part): bool => $part !== '')));
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 16px; margin: 16px 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        .bank td, .items td, .items th { border: 1px solid #222; padding: 4px 6px; vertical-align: top; }
        .items th { text-align: center; font-weight: 700; }
        .muted { color: #444; }
        .right { text-align: right; }
        .total { margin-top: 8px; }
        .purpose { margin-top: 12px; }
        .sign { margin-top: 28px; }
    </style>
</head>
<body>
    <table class="bank">
        <tr>
            <td style="width: 55%;">
                {{ $seller['bank_name'] ?? '' }}<br>
                <span class="muted">Банк получателя</span>
            </td>
            <td style="width: 15%;">БИК<br>ИНН банка</td>
            <td>
                {{ $seller['bik'] ?? '' }}<br>
                {{ $seller['bank_inn'] ?? '' }}
            </td>
        </tr>
        <tr>
            <td>
                ИНН {{ $seller['inn'] ?? '' }}<br>
                ОГРНИП {{ $seller['ogrnip'] ?? '' }}<br>
                {{ $seller['name'] ?? '' }}<br>
                <span class="muted">Получатель</span>
            </td>
            <td>Кор. счёт<br>Сч. №</td>
            <td>
                {{ $seller['corr_account'] ?? '' }}<br>
                {{ $seller['account'] ?? '' }}
            </td>
        </tr>
    </table>

    <h1>Счёт на оплату № {{ $invoice->number }} от {{ $issuedOn }}</h1>

    <p><strong>Поставщик:</strong> {{ $supplierLine }}</p>
    <p><strong>Покупатель:</strong> {{ $buyerLine }}</p>

    <table class="items">
        <thead>
        <tr>
            <th style="width: 6%;">№</th>
            <th>Товары (работы, услуги)</th>
            <th style="width: 12%;">Кол-во</th>
            <th style="width: 10%;">Ед.</th>
            <th style="width: 16%;">Цена</th>
            <th style="width: 16%;">Сумма</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td class="right">1</td>
            <td>{{ $invoice->item_name }}</td>
            <td class="right">1</td>
            <td class="right">усл.</td>
            <td class="right">{{ $amountLabel }}</td>
            <td class="right">{{ $amountLabel }}</td>
        </tr>
        </tbody>
    </table>

    <p class="total right"><strong>Итого:</strong> {{ $amountLabel }} ₽</p>
    <p class="right"><strong>{{ $invoice->vat_note }}</strong></p>
    <p class="right"><strong>Всего к оплате:</strong> {{ $amountLabel }} ₽</p>
    <p>Всего наименований 1, на сумму {{ $amountLabel }} ₽.</p>
    <p>{{ $amountInWords }}</p>
    <p class="purpose"><strong>Назначение платежа:</strong> {{ $invoice->payment_purpose }}</p>

    <p class="sign">
        Индивидуальный предприниматель _____________ / {{ $seller['sign_name'] ?? '' }} /
    </p>
</body>
</html>
