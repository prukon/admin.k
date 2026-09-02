<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Как записаться — {{ $partner->landingDisplayName() }}</title>
    <style>
        @page { margin: 10mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1a1a1a;
            font-size: 11pt;
            line-height: 1.35;
        }
        .header {
            background-color: #1d6f42;
            color: #ffffff;
            text-align: center;
            padding: 10px 14px 12px;
        }
        .brand-bar {
            background-color: #ffffff;
            text-align: center;
            padding: 6px 10px 5px;
            border-bottom: 1px solid #e5e7eb;
        }
        .brand-bar a {
            text-decoration: none;
            border: none;
        }
        .brand-bar img {
            height: 36px;
            width: auto;
            border: 0;
            display: block;
            margin: 0 auto;
        }
        .sheet-footer {
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 8.5pt;
            color: #4b5563;
        }
        .sheet-footer a {
            font-weight: bold;
            color: #155a35;
            text-decoration: none;
        }
        .partner-name {
            font-size: 10pt;
            margin: 0 0 4px;
        }
        h1 {
            font-size: 16pt;
            margin: 0;
        }
        .lead { margin: 10px 0 8px; }
        h2 {
            font-size: 12pt;
            color: #155a35;
            margin: 10px 0 4px;
        }
        p { margin: 0 0 5px; }
        .apply-link {
            font-weight: bold;
            color: #155a35;
            text-decoration: underline;
        }
        .apply-url {
            font-size: 8.5pt;
            color: #4b5563;
            margin: 3px 0 0;
        }
        .qr {
            width: 112px;
            height: 112px;
        }
        .phone {
            margin-top: 10px;
            padding: 8px 10px;
            background-color: #f4f7f5;
        }
        .closing { color: #4b5563; margin-top: 8px; }
        table.apply { width: 100%; border-collapse: collapse; margin: 6px 0 2px; }
        table.apply td { vertical-align: middle; }
    </style>
</head>
<body>
@if (!empty($logoPngDataUri))
    <div class="brand-bar">
        <a href="https://kidscrm.online/"><img src="{{ $logoPngDataUri }}" alt="kidscrm.online" width="131" height="36"></a>
    </div>
@endif
<div class="header">
    @if ($partner->landingDisplayName() !== '')
        <p class="partner-name">{{ $partner->landingDisplayName() }}</p>
    @endif
    <h1>Как записать ребёнка на секцию</h1>
</div>

<p class="lead">
    Дорогие родители! Спасибо, что доверили нам своего ребёнка.
    Чтобы начать занятия, оставьте заявку и подпишите договор — всё онлайн, без поездок.
</p>

<h2>1. Оставьте заявку</h2>
<p>
    Откройте страницу записи по ссылке ниже или наведите камеру телефона на QR-код.
    В заявке укажите электронную почту — на неё придут все важные письма.
</p>
<table class="apply">
    <tr>
        <td>
            <a class="apply-link" href="{{ $landingUrl }}">Запись — {{ $partner->landingDisplayName() }}</a>
            <p class="apply-url">{{ $landingUrl }}</p>
        </td>
        <td style="width: 122px; text-align: right;">
            <img class="qr" src="{{ $qrPngDataUri }}" alt="QR-код записи" width="112" height="112">
        </td>
    </tr>
</table>

<h2>2. Получите доступ в личный кабинет</h2>
<p>
    Администратор обработает заявку и создаст ваш профиль. После этого на почту придёт письмо с логином и паролем.
    Сохраните его: в кабинете удобно смотреть посещаемость ребёнка и следить за оплатой секций.
</p>

<h2>3. Заполните и подпишите договор</h2>
<p>
    Когда администратор подготовит договор, вам придёт ещё одно письмо.
    Войдите в личный кабинет, заполните данные и подпишите договор прямо в системе — это просто и удобно.
</p>

<h2>4. Оплата абонемента</h2>
<p>
    Каждый месяц на ту же почту будет приходить ссылка на оплату абонемента.
</p>

@if ($contactPhone)
    <p class="phone">
        Если что-то непонятно — просто позвоните нам:
        <strong>{{ $contactPhone }}</strong>
    </p>
@endif

<p class="closing">Мы всегда рядом и с радостью поможем.</p>

<p class="sheet-footer">
    <a href="https://kidscrm.online/">kidscrm.online</a>
    — CRM для учёта детских секций, приёма оплат и онлайн-подписания договоров
</p>
</body>
</html>
