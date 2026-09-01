<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Как записаться — {{ $partner->landingDisplayName() }}</title>
    <style>
        :root {
            --brand-primary: #1d6f42;
            --brand-primary-dark: #155a35;
            --ink: #1a1a1a;
            --muted: #4b5563;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            color: var(--ink);
            background: #f4f7f5;
            line-height: 1.55;
        }
        .sheet {
            max-width: 720px;
            margin: 24px auto 40px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 28px rgba(21, 90, 53, 0.08);
            overflow: hidden;
        }
        .sheet-header {
            background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-dark) 100%);
            color: #fff;
            padding: 28px 32px 26px;
            text-align: center;
        }
        .brand-bar {
            background: #fff;
            text-align: center;
            padding: 14px 24px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .brand-bar a {
            display: inline-block;
            line-height: 0;
        }
        .brand-bar img {
            height: 52px;
            width: auto;
            max-width: 220px;
        }
        .sheet-header .partner-name {
            display: inline-block;
            margin: 0 0 10px;
            padding: 0.4rem 0.95rem;
            font-size: 0.95rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 10px;
        }
        .sheet-header h1 {
            margin: 0;
            font-size: 1.7rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.25;
        }
        .sheet-body {
            padding: 28px 32px 32px;
        }
        .lead {
            margin: 0 0 22px;
            font-size: 1.05rem;
            color: #233;
        }
        .step {
            margin: 0 0 20px;
            padding-left: 0;
        }
        .step h2 {
            margin: 0 0 8px;
            font-size: 1.12rem;
            color: var(--brand-primary-dark);
        }
        .step p {
            margin: 0;
            color: #222;
        }
        .apply-row {
            display: flex;
            gap: 20px;
            align-items: center;
            margin: 16px 0 4px;
            flex-wrap: wrap;
        }
        .apply-link-wrap {
            flex: 1 1 240px;
            min-width: 0;
        }
        .apply-link {
            display: inline-block;
            color: var(--brand-primary-dark);
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 3px;
            word-break: break-word;
        }
        .apply-url {
            margin: 6px 0 0;
            font-size: 0.85rem;
            color: var(--muted);
            word-break: break-all;
        }
        .qr-code {
            flex: 0 0 168px;
            width: 168px;
            height: 168px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 8px;
            background: #fff;
        }
        .qr-code svg {
            display: block;
            width: 100%;
            height: 100%;
        }
        .phone {
            margin: 24px 0 0;
            padding: 14px 16px;
            background: #f4f7f5;
            border-radius: 12px;
            font-size: 1.02rem;
        }
        .phone a {
            color: var(--brand-primary-dark);
            font-weight: 700;
            text-decoration: none;
        }
        .closing {
            margin: 18px 0 0;
            color: var(--muted);
        }
        .sheet-footer {
            margin: 8px 32px 0;
            padding: 16px 4px 8px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 0.86rem;
            color: var(--muted);
            line-height: 1.45;
        }
        .sheet-footer a {
            color: var(--brand-primary-dark);
            font-weight: 700;
            text-decoration: none;
        }
        .print-bar {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            text-align: center;
            padding: 0 32px 28px;
        }
        .print-bar button,
        .print-bar a {
            appearance: none;
            border: 0;
            background: var(--brand-primary);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            padding: 10px 22px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            line-height: 1.2;
        }
        .print-bar a.pdf-btn {
            background: #fff;
            color: var(--brand-primary-dark);
            border: 2px solid var(--brand-primary);
        }
        .print-bar button:hover,
        .print-bar a:hover {
            background: var(--brand-primary-dark);
            color: #fff;
        }
        @media print {
            body { background: #fff; }
            .sheet {
                margin: 0;
                max-width: none;
                border-radius: 0;
                box-shadow: none;
            }
            .print-bar { display: none; }
            .sheet-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            a { color: inherit; }
            .apply-link { color: var(--brand-primary-dark); }
        }
        @page {
            size: A4;
            margin: 12mm;
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="brand-bar">
        <a href="https://kidscrm.online/" target="_blank" rel="noopener noreferrer">
            <img src="{{ asset('img/logo.png') }}" alt="kidscrm.online" width="180" height="52">
        </a>
    </div>
    <header class="sheet-header">
        @if ($partner->landingDisplayName() !== '')
            <p class="partner-name">{{ $partner->landingDisplayName() }}</p>
        @endif
        <h1>Как записать ребёнка на секцию</h1>
    </header>

    <div class="sheet-body">
        <p class="lead">
            Дорогие родители! Спасибо, что доверили нам своего ребёнка.
            Чтобы начать занятия, оставьте заявку и подпишите договор — всё онлайн, без поездок.
        </p>

        <section class="step">
            <h2>1. Оставьте заявку</h2>
            <p>
                Откройте страницу записи по ссылке ниже или наведите камеру телефона на QR-код.
                В заявке укажите электронную почту — на неё придут все важные письма.
            </p>
            <div class="apply-row">
                <div class="apply-link-wrap">
                    <a class="apply-link" href="{{ $landingUrl }}">Запись — {{ $partner->landingDisplayName() }}</a>
                    <p class="apply-url">{{ $landingUrl }}</p>
                </div>
                <div id="landing-qr"
                     class="qr-code"
                     data-url="{{ $landingUrl }}"
                     role="img"
                     aria-label="QR-код записи"></div>
            </div>
        </section>

        <section class="step">
            <h2>2. Получите доступ в личный кабинет</h2>
            <p>
                Администратор обработает заявку и создаст ваш профиль. После этого на почту придёт письмо с логином и паролем.
                Сохраните его: в кабинете удобно смотреть посещаемость ребёнка и следить за оплатой секций.
            </p>
        </section>

        <section class="step">
            <h2>3. Заполните и подпишите договор</h2>
            <p>
                Когда администратор подготовит договор, вам придёт ещё одно письмо.
                Войдите в личный кабинет, заполните данные и подпишите договор прямо в системе — это просто и удобно.
            </p>
        </section>

        <section class="step">
            <h2>4. Оплата абонемента</h2>
            <p>
                Каждый месяц на ту же почту будет приходить ссылка на оплату абонемента.
            </p>
        </section>

        @if ($contactPhone)
            <p class="phone">
                Если что-то непонятно — просто позвоните нам:
                @if ($contactPhoneHref)
                    <a href="{{ $contactPhoneHref }}">{{ $contactPhone }}</a>
                @else
                    <strong>{{ $contactPhone }}</strong>
                @endif
            </p>
        @endif

        <p class="closing">Мы всегда рядом и с радостью поможем.</p>
    </div>

    <p class="sheet-footer">
        <a href="https://kidscrm.online/" target="_blank" rel="noopener noreferrer">kidscrm.online</a>
        — CRM для учёта детских секций, приёма оплат и онлайн-подписания договоров
    </p>

    <div class="print-bar">
        <button type="button" onclick="window.print()">Распечатать</button>
        <a class="pdf-btn" href="{{ $pdfUrl }}">Скачать PDF</a>
    </div>
</div>
<script src="{{ asset('js/qrcode-generator.min.js') }}"></script>
<script>
    (function () {
        var el = document.getElementById('landing-qr');
        if (!el || typeof qrcode !== 'function') {
            return;
        }
        var url = el.getAttribute('data-url');
        if (!url) {
            return;
        }
        var qr = qrcode(0, 'M');
        qr.addData(url);
        qr.make();
        el.innerHTML = qr.createSvgTag({
            cellSize: 4,
            margin: 1,
            scalable: true,
            alt: 'QR-код записи'
        });
    })();
</script>
</body>
</html>
