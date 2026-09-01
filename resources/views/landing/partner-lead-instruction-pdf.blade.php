<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Как записаться — {{ $partner->landingDisplayName() }}</title>
    <style>
        @page { margin: 14mm 14mm 16mm 14mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1a1a1a;
            font-size: 12pt;
            line-height: 1.45;
        }
        .header {
            background-color: #1d6f42;
            color: #ffffff;
            text-align: center;
            padding: 16px 18px 18px;
        }
        .partner-name {
            font-size: 11pt;
            margin: 0 0 8px;
        }
        h1 {
            font-size: 18pt;
            margin: 0;
        }
        .lead { margin: 16px 0 14px; }
        h2 {
            font-size: 13pt;
            color: #155a35;
            margin: 14px 0 6px;
        }
        p { margin: 0 0 8px; }
        .apply-link {
            font-weight: bold;
            color: #155a35;
            text-decoration: underline;
        }
        .apply-url {
            font-size: 9pt;
            color: #4b5563;
            margin: 4px 0 0;
        }
        .qr {
            width: 140px;
            height: 140px;
        }
        .phone {
            margin-top: 16px;
            padding: 10px 12px;
            background-color: #f4f7f5;
        }
        .closing { color: #4b5563; margin-top: 12px; }
        table.apply { width: 100%; border-collapse: collapse; margin: 8px 0 4px; }
        table.apply td { vertical-align: middle; }
    </style>
</head>
<body>
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
        <td style="width: 150px; text-align: right;">
            <img class="qr" src="{{ $qrPngDataUri }}" alt="QR-код записи" width="140" height="140">
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
</body>
</html>
