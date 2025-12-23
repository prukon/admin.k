@extends('layouts.landingPage')
@section('title', 'CRM для детской языковой школы — учет учеников, расписание и оплат | kidscrm.online')

@section('content')

    <!-- Hero -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row align-items-center">

                <h1 class="display-5 fw-bold text-center">
                    CRM для детских языковых школ — учет учеников, расписание и оплаты в одном сервисе
                </h1>
                <h2 class="text-center">
                    <b class="alert-color">Экономьте до 30% времени</b> за счёт автоматизации администрирования курсов
                </h2>

                <div class="col-md-6 mb-4 mb-md-0">
                    <p class="lead mt-4 mb-3">
                        <b>kidscrm.online</b> — CRM-сервис для детских языковых школ и курсов английского, немецкого,
                        французского, испанского и других языков. Помогает держать под контролем:
                        учеников и группы, расписание занятий, оплаты, задолженности, договоры и отчётность.
                    </p>

                    <ul class="list-unstyled lead mt-4 mb-4">
                        <li class="d-flex align-items-center mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Чек"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Онлайн-подписание договоров с родителями через СМС — без бумажной рутины.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Чек"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Учет учеников и групп: уровни A1–C1, разговорные клубы, подготовка к экзаменам.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Чек"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Гибкая настройка оплат: абонементы, разовые занятия, интенсивы и индивидуальные цены.</span>
                        </li>
                        <li class="d-flex align-items-center">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Чек"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Прозрачный контроль задолженностей: кто не оплатил и за какой период.</span>
                        </li>
                    </ul>

                    <!-- CTA -->
                    <div class="text-center mt-4">
                        <a href="#registration-form" class="btn btn-success btn-lg"
                           data-bs-toggle="modal" data-bs-target="#createOrder">Попробовать бесплатно</a>
                    </div>

                </div>

                <div class="col-md-6 text-end">
                    <img src="{{ asset('img/landing/seo/language/hero.png') }}"
                         alt="CRM для детской языковой школы"
                         class="img-fluid rounded shadow-sm"
                         onerror="this.src='{{ asset('img/landing/iphone.png') }}'">
                </div>

            </div>
        </div>
    </section>

    {{-- (1) Сегменты / сценарии для языковых школ --}}
    <section id="language-segments" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Для каких языковых школ CRM подходит лучше всего</h2>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Небольшой языковой центр</h3>
                            <p class="text-muted mb-0">
                                1–2 администратора, несколько групп по английскому, оплату ведут в таблицах и
                                мессенджерах —
                                важны порядок в оплатах и расписании.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Языковая школа с уровнями</h3>
                            <p class="text-muted mb-0">
                                Курсы по уровням A1–C1, группы по возрастам, разговорные клубы — нужна единая система
                                учёта и отчётности.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Подготовка к экзаменам</h3>
                            <p class="text-muted mb-0">
                                ЕГЭ, ОГЭ, Cambridge, IELTS, TOEFL — разные тарифы, интенсивы и мини-группы.
                                Важно не путаться в тарифах и оплатах.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Сети языковых школ</h3>
                            <p class="text-muted mb-0">
                                Филиалы в разных районах, много преподавателей и администраторов —
                                нужна единая CRM вместо “отдельных табличек у каждого офиса”.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- (2) Сравнение: Excel/тетради/чаты vs CRM --}}
    <section id="comparison" class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">CRM vs Excel/тетради/чаты: что меняется в языковой школе</h2>
            <p class="text-center text-muted mb-5 fs-5">
                Пока учет живёт в таблицах, переписке и тетрадях — появляются долги, путаница с абонементами и много
                повторяющихся вопросов.
                CRM делает процессы управляемыми и прозрачными.
            </p>

            <div class="table-responsive shadow-sm rounded">
                <table class="table table-bordered align-middle mb-0 bg-white">
                    <thead class="table-light">
                    <tr>
                        <th style="width:34%">Задача</th>
                        <th style="width:33%">Тетради, Excel и чаты</th>
                        <th style="width:33%">Как с kidscrm.online</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="fw-bold">Контроль оплат</td>
                        <td>
                            Ручные отметки, пересчёт переводов, постоянные уточнения
                            “вы оплатили за этот месяц/курс?”.
                        </td>
                        <td>
                            Оплаты фиксируются в системе, статусы видны сразу по каждому ученику и периоду.
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Абонементы и интенсивы</td>
                        <td>
                            Условия в разных файлах и чатах, сложно вспомнить, кто на каком тарифе
                            и что входит в оплату.
                        </td>
                        <td>
                            Гибкие тарифы и индивидуальные цены заданы в CRM — меньше вопросов
                            “а сколько оплачивать в этом месяце?”.
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Задолженности</td>
                        <td>
                            Долги обнаруживаются постфактум, разговоры с родителями часто проходят напряжённо.
                        </td>
                        <td>
                            Прозрачный список должников и периодов, меньше конфликтных ситуаций и неловких диалогов.
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Расписание занятий</td>
                        <td>
                            Переносы, замены преподавателей и залов живут в чатах,
                            кто-то всегда узнаёт последним.
                        </td>
                        <td>
                            Единый источник правды: актуальное расписание и изменения в одном месте.
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Договоры с родителями</td>
                        <td>
                            Распечатки и подписи “на бегу” в классе, “у кого оригинал договора?”.
                        </td>
                        <td>
                            Онлайн-подписание через СМС, договоры и статусы хранятся внутри сервиса.
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Отчёты по школе</td>
                        <td>
                            Сводятся вручную в конце месяца, много ручной сверки и ошибок.
                        </td>
                        <td>
                            Автоматические отчёты: доходы, долги, транзакции и загрузка групп.
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </section>

    {{-- (3) Боли / сценарии языковых школ --}}
    <section id="problems" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Что обычно болит у детских языковых школ — и как CRM это закрывает</h2>

            <div class="row g-4 align-items-center">
                <div class="col-md-6">
                    <div class="border-0 shadow-sm p-4 rounded bg-white">
                        <h3 class="h5 fw-bold">Оплаты и абонементы</h3>
                        <p class="text-muted mb-0">
                            У кого помесячная оплата, у кого курс целиком, кто оплатил интенсив — CRM даёт полную
                            картину
                            по платежам и задолженностям, без ручной сверки.
                        </p>
                    </div>
                    <div class="border-0 shadow-sm p-4 rounded bg-white mt-3">
                        <h3 class="h5 fw-bold">Расписание и наполненность групп</h3>
                        <p class="text-muted mb-0">
                            Группы по уровням и возрастам, разные преподаватели и классы — расписание становится
                            управляемым,
                            а не живёт в нескольких таблицах и чатах.
                        </p>
                    </div>
                    <div class="border-0 shadow-sm p-4 rounded bg-white mt-3">
                        <h3 class="h5 fw-bold">Договоры и коммуникация с родителями</h3>
                        <p class="text-muted mb-0">
                            Онлайн-подписание договоров, понятные суммы к оплате и статусы платежей сокращают
                            количество повторяющихся вопросов и неловких разговоров “про деньги”.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 text-center">
                    {{-- место под графику --}}
                    <img src="{{ asset('img/landing/seo/language/problems.png') }}"
                         alt="Проблемы языковой школы и решение через CRM"
                         class="img-fluid rounded shadow-sm"
                         onerror="this.style.display='none'">
                </div>
            </div>
        </div>
    </section>

    <!-- Ключевой функционал (дубль) -->
    <section id="features" class="bg-light py-5">
        <div class="container">
            <h2 class="text-center mb-5">Ключевой функционал для языковых школ</h2>
            <div class="row align-items-center">

                <div class="col-md-4">
                    @foreach([
                        ['icon' => 'img/landing/icons/functional/user-group.png', 'text' => 'Учёт учеников и групп', 'desc' => 'Добавляйте учеников, распределяйте по возрасту, уровням языка и типам курсов.'],
                        ['icon' => 'img/landing/icons/functional/schedule-management.png', 'text' => 'Управление расписанием', 'desc' => 'Настраивайте расписание по классам, преподавателям, уровням и форматам занятий.'],
                    ] as $item)
                        <div class="d-flex align-items-center mb-4">
                            <img src="{{ asset($item['icon']) }}"
                                 alt="{{ $item['text'] }}"
                                 class="me-3"
                                 style="width:150px; height:150px; object-fit:contain;">
                            <div>
                                <h6 class="fw-bold mb-1">{{ $item['text'] }}</h6>
                                <p class="text-muted fs-6 mb-0">{{ $item['desc'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="col-md-4 text-center mb-4 mb-md-0">
                    <img src="{{ asset('img/landing/dashboard.png') }}"
                         alt="Функции CRM"
                         class="img-fluid rounded mx-auto d-block">
                </div>

                <div class="col-md-4">
                    @foreach([
                        ['icon' => 'img/landing/icons/functional/payment-acceptance.png', 'text' => 'Приём и учёт оплат', 'desc' => 'Онлайн-оплаты и автоматический учёт транзакций по курсам, абонементам и интенсивам.'],
                        ['icon' => 'img/landing/icons/functional/automatic-reporting.png', 'text' => 'Автоматическая отчётность', 'desc' => 'Сводки по доходам, задолженностям и загрузке групп — в пару кликов.'],
                    ] as $item)
                        <div class="d-flex align-items-center mb-4">
                            <img src="{{ asset($item['icon']) }}"
                                 alt="{{ $item['text'] }}"
                                 class="me-3"
                                 style="width:150px; height:150px; object-fit:contain;">
                            <div>
                                <h6 class="fw-bold mb-1">{{ $item['text'] }}</h6>
                                <p class="text-muted fs-6 mb-0">{{ $item['desc'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>

            <div class="text-center mt-4">
                <a href="#registration-form" class="btn btn-success btn-lg"
                   data-bs-toggle="modal" data-bs-target="#createOrder">Попробовать бесплатно</a>
            </div>
        </div>
    </section>

    {{-- (4) Коммуникации с родителями --}}
    <section id="communications" class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">Коммуникации с родителями: меньше переписок, больше ясности</h2>
            <p class="text-center text-muted mb-5 fs-5">
                В языковой школе много деталей: тарифы, пропуски, переносы занятий, пробные уроки.
                CRM помогает снизить хаос в переписке и выстроить понятный процесс для родителей.
            </p>

            <div class="row g-4 align-items-stretch">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Понятные суммы к оплате</h3>
                            <p class="text-muted mb-0">
                                Помесячная оплата, курс целиком, интенсив или разговорный клуб —
                                сумма к оплате заранее зафиксирована в системе.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Прозрачные статусы оплат</h3>
                            <p class="text-muted mb-0">
                                “Оплачено / не оплачено / задолженность” — меньше спорных ситуаций и неудобных
                                разговоров в классе.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Информация по расписанию</h3>
                            <p class="text-muted mb-0">
                                Переносы, замены преподавателей и классов — в одном месте, а не в длинной цепочке
                                сообщений в чате.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Договоры без бумажной рутины</h3>
                            <p class="text-muted mb-0">
                                Онлайн-подписание через СМС — без распечаток, сканов и “поймать родителя после урока”.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- (5) Что даёт CRM — по ролям --}}
    <section id="roles" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">Что даёт CRM детской языковой школе — владельцу и родителям</h2>
            <p class="text-center text-muted mb-5 fs-5">
                CRM делает процессы прозрачными: владельцу — управляемость и цифры, родителям — ясность и удобство.
            </p>

            <div class="row g-4">
                {{-- Владелец / руководитель школы --}}
                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Владелец или руководитель языковой школы</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2">
                                    <b>Прозрачность финансов:</b> видны оплаты и задолженности по каждому курсу, группе,
                                    филиалу.
                                </li>
                                <li class="mb-2">
                                    <b>Гибкие суммы оплат:</b>
                                    общие тарифы и индивидуальные цены по ученику или группе —
                                    однажды настроили, дальше только контролируете.
                                </li>
                                <li class="mb-2">
                                    <b>Меньше вопросов “сколько платить?”:</b>
                                    суммы и периоды оплат заданы в CRM, а не “в голове администратора”.
                                </li>
                                <li class="mb-2">
                                    <b>Онлайн-подписание договоров:</b>
                                    договор подтверждается через СМС прямо в сервисе — не нужно бегать с распечатками и
                                    ловить родителей в коридоре.
                                </li>
                                <li class="mb-2">
                                    <b>Контроль загрузки групп и классов:</b>
                                    видно, где стоит открыть дополнительную группу, а где уже нет мест.
                                </li>
                                <li>
                                    <b>Отчётность без Excel:</b>
                                    доходы, долги, транзакции и загрузка групп — в пару кликов, без ручной сводной
                                    таблицы.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Родители --}}
                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Родители учеников</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2">
                                    <b>Онлайн-подписание договора:</b>
                                    подтверждение через СМС, без поездок в офис и подписи “на коленке” на занятиях.
                                </li>
                                <li class="mb-2">
                                    <b>Понятные суммы к оплате:</b>
                                    видно, сколько и за что нужно оплатить в текущем периоде —
                                    курс, абонемент, интенсив или разговорный клуб.
                                </li>
                                <li class="mb-2">
                                    <b>Удобная онлайн-оплата:</b>
                                    без наличных и переводов “на карту” — платёж фиксируется в системе автоматически.
                                </li>
                                <li class="mb-2">
                                    <b>Прозрачные статусы оплат:</b>
                                    меньше поводов для недопонимания и неловких разговоров “вы нам должны”.
                                </li>
                                <li>
                                    <b>Ясность по расписанию:</b>
                                    родители вовремя узнают о переносах и заменах занятий.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="#registration-form" class="btn btn-success btn-lg"
                   data-bs-toggle="modal" data-bs-target="#createOrder">Попробовать бесплатно</a>
            </div>
        </div>
    </section>

    <!-- Как это работает (дубль) -->
    <section id="how-it-works" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Как это работает</h2>
            <p class="text-center text-muted mb-5 fs-4">
                Автоматизируйте управление детской языковой школой — от первого договора до отчёта за месяц.
            </p>

            @php
                $steps = [
                    ['icon' => 'img/landing/icons/Register.png',          'title' => 'Быстрый старт',                     'desc' => 'Подключите языковую школу в пару кликов — без сложных форм и долгого ожидания.'],
                    ['icon' => 'img/landing/icons/Import.png',            'title' => 'Импорт данных “под ключ”',         'desc' => 'Мы бесплатно перенесём учеников, группы и расписание в систему.'],
                    ['icon' => 'img/landing/icons/Price.png',             'title' => 'Настройка тарифов и курсов',       'desc' => 'Задайте помесячные оплаты, курсы целиком, интенсивы и индивидуальные цены.'],
                    ['icon' => 'img/landing/icons/Credit-card.png',       'title' => 'Онлайн-платежи',                   'desc' => 'Родители оплачивают через встроенный эквайринг, а вы видите статусы оплат.'],
                    ['icon' => 'img/landing/icons/Report.png',            'title' => 'Отчёты и аналитика',               'desc' => 'Отчёты по доходам, долгам и загрузке групп формируются автоматически.'],
                    ['icon' => 'img/landing/icons/reminder.png',          'title' => 'Напоминания о платежах',           'desc' => 'Система помогает аккуратно напомнить о просрочках и избежать “забытых” оплат.'],
                    ['icon' => 'img/landing/icons/saving-time.png',       'title' => 'Экономия времени команды',         'desc' => 'Меньше рутины и ручных сверок — больше времени на развитие школы и качество обучения.'],
                ];
                $half = ceil(count($steps) / 2);
                $leftSteps  = array_slice($steps, 0, $half);
                $rightSteps = array_slice($steps, $half);
            @endphp

            <div class="row g-4">
                <div class="col-md-6">
                    @foreach($leftSteps as $step)
                        <div class="d-flex align-items-start border-bottom pb-3 mb-3">
                            <img src="{{ asset($step['icon']) }}"
                                 alt="{{ $step['title'] }}"
                                 class="me-3"
                                 style="width:48px; height:48px; object-fit:contain;">
                            <div>
                                <h5 class="fw-bold mb-1">{{ $step['title'] }}</h5>
                                <p class="text-muted fs-6 mb-0">{{ $step['desc'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="col-md-6">
                    @foreach($rightSteps as $step)
                        <div class="d-flex align-items-start border-bottom pb-3 mb-3">
                            <img src="{{ asset($step['icon']) }}"
                                 alt="{{ $step['title'] }}"
                                 class="me-3"
                                 style="width:48px; height:48px; object-fit:contain;">
                            <div>
                                <h5 class="fw-bold mb-1">{{ $step['title'] }}</h5>
                                <p class="text-muted fs-6 mb-0">{{ $step['desc'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </section>

    {{-- (6) Чек-лист внедрения --}}
    <section id="checklist" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">Внедрение CRM за 1 день: короткий чек-лист</h2>
            <p class="text-center text-muted mb-5 fs-5">
                Ваша задача — подготовить минимум вводных. Наша задача — аккуратно перенести данные и помочь запустить
                процесс.
            </p>

            <div class="row g-4 align-items-stretch">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Что готовите вы</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2">Список групп, курсов и преподавателей.</li>
                                <li class="mb-2">Текущее расписание по дням, аудиториям и уровням.</li>
                                <li class="mb-2">Правила оплаты: тарифы, длительность курсов, интенсивы.</li>
                                <li>Базу учеников (даже в тетради или Excel).</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Что делаем мы</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2">Бесплатно переносим учеников, группы и расписание.</li>
                                <li class="mb-2">Помогаем настроить тарифы и логику учёта оплат.</li>
                                <li class="mb-2">Подключаем онлайн-оплату (при необходимости).</li>
                                <li>Выделяем технического специалиста на период запуска.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Что вы получаете</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2">Единую базу учеников и групп.</li>
                                <li class="mb-2">Порядок в расписании занятий и кабинетах.</li>
                                <li class="mb-2">Прозрачный контроль оплат и задолженностей.</li>
                                <li>Автоматическую отчётность по школе и филиалам.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="#registration-form" class="btn btn-success btn-lg"
                   data-bs-toggle="modal" data-bs-target="#createOrder">Запустить внедрение</a>
            </div>
        </div>
    </section>

    <!-- Уникальные преимущества -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row align-items-center">

                <div class="col-md-6 text-end mob-hide">
                    <img src="{{ asset('img/landing/dance.png') }}"
                         alt="Преимущества CRM"
                         class="img-fluid rounded">
                </div>

                <div class="col-md-6 mb-4 mb-md-0">
                    <h2 class="text-center mb-5" id='advantages'>Наши уникальные преимущества</h2>

                    <div class="container">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card h-100 p-4 shadow-sm border-0 rounded-3">
                                    <h5 class="fw-bold mb-3">Мы сами перенесём данные ваших учеников и групп</h5>
                                    <p class="text-muted fs-6">
                                        Мы бесплатно перенесём базу учеников, групп и расписаний, даже если сейчас
                                        всё записано в тетради или разрозненных таблицах.
                                    </p>
                                    <div class="mt-auto">
                                        <i class="bi bi-box-arrow-in-right display-5 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 p-4 shadow-sm border-0 rounded-3">
                                    <h5 class="fw-bold mb-3">Личный технический специалист</h5>
                                    <p class="text-muted fs-6">
                                        При старте вы получаете персонального специалиста: помощь с настройкой,
                                        ответы на вопросы, адаптация процессов школы под возможности платформы.
                                    </p>
                                    <div class="mt-auto">
                                        <i class="bi bi-person-raised-hand display-5 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </section>

    <!-- Стоимость (дубль) -->
    <section id="pricing" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Стоимость</h2>
            <p class="text-center fs-5 text-muted mb-5">
                <span class="alert-color">Мы не берём деньги за использование сервиса</span> —
                оплата взимается только с успешных платежей ваших клиентов.
            </p>

            <div class="row g-4 justify-content-center">
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 p-4 text-center">
                        <img src="{{ asset('img/landing/icons/price/money-fee.png') }}"
                             alt="Иконка абонентской платы"
                             class="mx-auto mb-3"
                             style="width:48px; height:48px; object-fit:contain;">
                        <h5 class="fw-bold"><span class="alert-color"> 0 ₽</span> абонентская плата</h5>
                        <p class="text-muted fs-6">
                            Полный доступ ко всем функциям: учёт учеников и групп, управление расписанием,
                            отчёты и прочие инструменты — без ежемесячных взносов.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 p-4 text-center">
                        <img src="{{ asset('img/landing/icons/price/transferring-data.png') }}"
                             alt="Иконка миграции данных"
                             class="mx-auto mb-3"
                             style="width:48px; height:48px; object-fit:contain;">
                        <h5 class="fw-bold"><span class="alert-color">0 ₽</span> за перенос данных в систему</h5>
                        <p class="text-muted fs-6">
                            Мы бесплатно перенесём вашу базу учеников, групп и расписаний «под ключ»,
                            чтобы вы могли сразу приступить к работе без ручного ввода.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 p-4 text-center">
                        <img src="{{ asset('img/landing/icons/price/technical-support.png') }}"
                             alt="Иконка технической поддержки"
                             class="mx-auto mb-3"
                             style="width:48px; height:48px; object-fit:contain;">
                        <h5 class="fw-bold"><span class="alert-color">0 ₽</span> техническая поддержка</h5>
                        <p class="text-muted fs-6">
                            Оперативная помощь по чату и телефону — персональный специалист решит вашу задачу
                            без очередей и “переадресаций”.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 p-4 text-center">
                        <img src="{{ asset('img/landing/icons/price/commission.png') }}"
                             alt="Иконка комиссии"
                             class="mx-auto mb-3"
                             style="width:48px; height:48px; object-fit:contain;">
                        <h5 class="fw-bold"><span class="alert-color"> 1,5% комиссия</span> только с транзакций</h5>
                        <p class="text-muted fs-6">
                            Оплата сервиса — небольшой процент от каждой успешной оплаты занятий через
                            онлайн-эквайринг. Без скрытых сборов и дополнительных платежей.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- FAQ (с JSON-LD) -->
    <section id="faq" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">FAQ</h2>

            @php
                $languageFaq = [
                    'Подходит ли CRM небольшой детской языковой школе?' =>
                        'Да. Даже небольшой центр выигрывает за счёт прозрачного учёта оплат, задолженностей и порядка в расписании.',
                    'Можно ли задать разные тарифы и индивидуальные цены для учеников?' =>
                        'Да. Вы можете настроить помесячные тарифы, оплату курса целиком, интенсивы и индивидуальные цены по ученику или группе. Это снижает количество вопросов от родителей.',
                    'Как происходит подписание договора с родителями?' =>
                        'Договор подписывается онлайн через СМС. Не нужно распечатывать документы и ловить родителей после уроков ради подписи.',
                    'Помогаете ли вы перенести базу учеников и расписание?' =>
                        'Да. Мы бесплатно переносим данные “под ключ”: учеников, группы и расписание — даже если сейчас всё в тетради или Excel.',
                    'Есть ли абонентская плата?' =>
                        'Нет. Абонентская плата — 0 ₽. Оплата сервиса — комиссия 1,5% только с успешных транзакций.',
                    'Есть ли мобильное приложение?' =>
                        'Пока отдельного приложения нет, но интерфейс адаптирован под смартфоны и планшеты — можно работать из браузера.',
                    'Сколько времени занимает запуск CRM в языковой школе?' =>
                        'Чаще всего старт возможен за 1 день: вы даёте вводные, мы переносим данные и помогаем настроить процесс.',
                ];
            @endphp

            <div class="accordion" id="faqAccordion">
                @foreach($languageFaq as $question => $answer)
                    <div class="accordion-item mb-3 border-0 shadow-sm">
                        <h2 class="accordion-header" id="heading{{ $loop->index }}">
                            <button
                                    class="accordion-button collapsed bg-white text-dark d-flex justify-content-between align-items-center"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#collapse{{ $loop->index }}"
                                    aria-expanded="false"
                                    aria-controls="collapse{{ $loop->index }}"
                            >
                                <span class="flex-grow-1 text-start">{{ $question }}</span>
                                <i class="bi bi-chevron-down ms-2"></i>
                            </button>
                        </h2>
                        <div
                                id="collapse{{ $loop->index }}"
                                class="accordion-collapse collapse"
                                aria-labelledby="heading{{ $loop->index }}"
                                data-bs-parent="#faqAccordion"
                        >
                            <div class="accordion-body text-muted">
                                {{ $answer }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <script type="application/ld+json">
                {
                  "@context": "https://schema.org",
                  "@type": "FAQPage",
                  "mainEntity": [
                    @foreach($languageFaq as $q => $a)
                    {
                      "@type": "Question",
                      "name": @json($q),
                      "acceptedAnswer": {
                        "@type": "Answer",
                        "text": @json($a)
                    }
                  }@if(!$loop->last)
                        ,
                    @endif
                @endforeach
                ]
              }

            </script>
        </div>
    </section>

    <!-- Call to Action -->
    <section id="cta" class="py-5 bg-call-to-action">
        <div class="container text-center">
            <h2 class="display-6 fw-bold mb-3">Готовы навести порядок в детской языковой школе?</h2>
            <p class="fs-5 mb-4">
                Попробуйте <span class="fw-bold">kidscrm.online</span> бесплатно и получите поддержку
                персонального куратора при запуске.
            </p>
            <a href="#registration-form" class="btn btn-success btn-lg me-3"
               data-bs-toggle="modal" data-bs-target="#createOrder">Попробовать бесплатно</a>
        </div>
    </section>


@endsection
