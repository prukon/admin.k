@extends('layouts.landingPage')
@section('title',
    'Автоматизация оплат для детских секций и футбольных школ — без абонплаты и без эквайринга |
    kidscrm.online')
@section('meta_description',
    'kidscrm.online — сервис для детских секций и футбольных школ: автоматический учёт оплат и
    долгов без абонентской платы и без подключения эквайринга и онлайн-кассы. Деньги поступают прямо на счёт школы, комиссия
    только с успешных платежей.')
@section('content')

    <style>
    
    </style>

    <div class="landing-page">

        <!-- Hero: конверсионный хедер -->
        <section class="bg-light py-4 py-md-4">
            <div class="container">

                <!-- Основной ряд -->
                <div class="row align-items-center">

                    <!-- Заголовочная часть -->
                    <div class="col-12 mb-3 mb-md-4">
                        <div class="text-center mb-2">
                            <span class="section-label">Онлайн-оплаты для детских секций</span>
                        </div> 

                        <h1 class="fw-bold text-center mb-3">
                            <span class="mark-creative">CRM для детских секций и кружков</span>
                        </h1>

                        <p class="text-center mb-1" style="font-size: 1.08rem;">
                            Принимайте онлайн-оплату за занятия без онлайн-кассы и сложных интеграций
                        </p>

                        <p class="fw-semibold text-center mt-3 mb-0" style="font-size: 1.02rem;">
                            <span class="my-alert-color">0 ₽ в месяц </span> — только комиссия сервиса с успешных платежей
                        </p>
                    </div>

                    <!-- Левый столбец -->
                    <div class="col-md-6 mb-4 mb-md-0 d-flex flex-column">

                        <!-- Блок выгод -->
                        <ul class="list-unstyled mt-3 mb-3 mb-md-4">

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}" class="me-2 mt-1"
                                    style="width:20px; height:20px; object-fit:contain;" alt="Не нужна онлайн-касса">
                                <span>Не нужна онлайн-касса</span>
                            </li>

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}" class="me-2 mt-1"
                                    style="width:20px; height:20px; object-fit:contain;"
                                    alt="Не нужно подключать эквайринг">
                                <span>Не нужно подключать эквайринг</span>
                            </li>

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}" class="me-2 mt-1"
                                    style="width:20px; height:20px; object-fit:contain;"
                                    alt="Не нужно считать долги вручную">
                                <span>Не нужно считать долги вручную</span>
                            </li>

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}" class="me-2 mt-1"
                                    style="width:20px; height:20px; object-fit:contain;" alt="Онлайн подписание договоров">
                                <span>Онлайн подписание договоров с родителями</span>
                            </li>

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}" class="me-2 mt-1"
                                    style="width:20px; height:20px; object-fit:contain;" alt="Бесплатный перенос данных">
                                <span>Бесплатный перенос данных в CRM даже из тетради или Excel</span>
                            </li>

                        </ul>

                        <div class="small text-muted mb-3 mb-md-4">
                            Разработано на базе действующей футбольной школы из Санкт-Петербурга
                        </div>

                        <!-- CTA -->
                        <div class="col-12 col-md-6 mx-auto d-flex justify-content-center mt-3">

                            <div
                                class="d-flex flex-column flex-md-row align-items-center justify-content-center gap-3 text-center">

                                <!-- Кнопка демо -->
                                <a href="#registration-form" class="btn btn-success btn-lg px-4 btn-order btn-order-transform"
                                    style="min-width:230px;" data-bs-toggle="modal" data-bs-target="#createOrder">
                                    Записаться на демо
                                </a>

                            </div>

                        </div>

                    </div>

                    <!-- Правый столбец -->
                    <div class="col-md-6 d-flex justify-content-center align-items-center mt-3 mt-md-0">
                        <img src="{{ asset('img/landing/football.png') }}" alt="CRM для детских секций и футбольных школ"
                            class="img-fluid" style="max-height:340px; width:auto;">
                    </div>

                </div>

            </div>
        </section>

        <!-- Цена хаоса -->
        <section class="py-5 bg-light">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">

                        <div class="text-center mb-3">
                            <span class="section-label">Проблема</span>
                        </div>

                        <h2 class="text-center mb-3">
                            <span class="mark-creative">
                                Хаос в оплатах стоит школе дороже, чем кажется
                            </span>
                        </h2>

                        <p class="text-center text-muted fs-5 mb-4">
                            Когда платежи живут в чатах, таблицах и тетрадях, вы теряете не только удобство — вы теряете
                            деньги
                            и контроль.
                        </p>

                        <div class="card border-0 shadow-lg rounded-4">
                            <div class="card-body py-4 px-3 px-md-4">

                                <ul class="list-unstyled mb-3">

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">
                                                Недосбор 10–20% оплат и кассовые разрывы
                                            </h5>
                                            <div class="text-muted">
                                                Группы заполнены, а выручка не добирается — деньги «растворяются» в хаосе
                                                учёта
                                                и поздних платежей.
                                            </div>
                                        </div>
                                    </li>

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">
                                                Чаты, таблицы и тетрадки не синхронизированы
                                            </h5>
                                            <div class="text-muted">
                                                Родители не видят, за какой месяц оплатили, школа тоже — данные теряются,
                                                забываются и живут в разных местах.
                                            </div>
                                        </div>
                                    </li>

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">
                                                Нет прозрачности по долгам
                                            </h5>
                                            <div class="text-muted">
                                                Со временем становится сложно ответить на простой вопрос: кто, сколько и за
                                                какой месяц должен школе.
                                            </div>
                                        </div>
                                    </li>

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">
                                                Постоянные напоминания и личные сообщения
                                            </h5>
                                            <div class="text-muted">
                                                Чтобы деньги пришли вовремя, приходится самому писать родителям о просрочках
                                                и
                                                буквально «выбивать» оплату.
                                            </div>
                                        </div>
                                    </li>

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">
                                                Бесконечные вопросы «А сколько мы должны в этом месяце?»
                                            </h5>
                                            <div class="text-muted">
                                                Вместо того чтобы развивать школу, вы тратите время на ответы по суммам,
                                                скидкам
                                                и датам оплат.
                                            </div>
                                        </div>
                                    </li>

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">
                                                Приём денег «с карты на карту» — риск для бизнеса
                                            </h5>
                                            <div class="text-muted">
                                                В большинстве случаев это противоречит закону, вызывает лишние вопросы у
                                                налоговой и риск блокировок счетов.
                                            </div>
                                        </div>
                                    </li>

                                </ul>

                                <p class="text-muted mt-3 mb-0" style="text-decoration: underline;">
                                    Даже для небольшой секции это легко превращается в сотни тысяч рублей упущенной выручки
                                    и
                                    постоянный стресс за год.
                                </p>

                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- Решение -->
        <section id="solution" class="py-5 bg-light">
            <div class="container">

                <div class="text-center mb-2">
                    <span class="section-label">Решение</span>
                </div>

                <h2 class="text-center mb-3">
                    <span class="mark-creative">
                        Как мы решаем проблему хаоса в оплатах
                    </span>
                </h2>

                <p class="text-center text-muted fs-5 mb-4">
                    Мы берём на себя приём онлайн-оплат, юридическую часть и учёт долгов.
                    <br>Вы управляете секцией и тренировками — система отвечает за деньги и порядок.
                </p>

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">

                                <!-- Витрина результатов -->
                                <div class="row g-4 mb-4 text-center text-md-start">
                                    <div class="col-md-4">
                                        <div class="p-3 p-md-4 h-100 rounded-4 bg-light">
                                            <div class="text-uppercase small fw-semibold text-muted mb-1">
                                                Сбор оплат
                                            </div>
                                            <div class="h3 fw-bold mb-1 my-alert-color">
                                                +10–20%
                                            </div>
                                            <div class="text-muted small">
                                                за счёт автоматических напоминаний, понятных ссылок на оплату
                                                и прозрачного учёта долгов.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 p-md-4 h-100 rounded-4 bg-light">
                                            <div class="text-uppercase small fw-semibold text-muted mb-1">
                                                Админская рутина
                                            </div>
                                            <div class="h3 fw-bold mb-1 my-alert-color">
                                                до −10 ч/нед
                                            </div>
                                            <div class="text-muted small">
                                                меньше звонков, переписок и ручных сверок по оплатам и задолженностям.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 p-md-4 h-100 rounded-4 bg-light">
                                            <div class="text-uppercase small fw-semibold text-muted mb-1">
                                                Стоимость сервиса
                                            </div>
                                            <div class="h3 fw-bold mb-1 my-alert-color">
                                                0 ₽ в месяц
                                            </div>
                                            <div class="text-muted small">
                                                вы не платите абонентку — только небольшую комиссию с успешных
                                                онлайн-платежей.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Суть решения в трёх блоках -->
                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <div class="h-100 p-3 p-md-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Онлайн-оплаты «под ключ»</h5>
                                            <ul class="text-muted small mb-0 ps-3">
                                                <li>родители получают понятную ссылку на оплату;</li>
                                                <li>деньги поступают напрямую на счёт школы;</li>
                                                <li>чеки автоматически уходят на почту родителям;</li>
                                                <li>вам не нужно самому подключать эквайринг и кассу.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="h-100 p-3 p-md-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Прозрачный учёт и долги в одном месте</h5>
                                            <ul class="text-muted small mb-0 ps-3">
                                                <li>единая база учеников, групп, тренеров и оплат;</li>
                                                <li>актуальный список должников по месяцам и секциям;</li>
                                                <li>отчёты по доходу и просрочкам в пару кликов;</li>
                                                <li>больше никаких «а сколько мы должны в этом месяце?».</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="h-100 p-3 p-md-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Юридически чистая и безопасная схема</h5>
                                            <ul class="text-muted small mb-0 ps-3">
                                                <li>мультирасчёты Т-Банка, прозрачная схема для школы;</li>
                                                <li>онлайн-касса сервиса зарегистрирована в налоговой;</li>
                                                <li>чеки формируются и отправляются автоматически;</li>
                                                <li>вы снижаете риски блокировок и вопросов от налоговой.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center mt-4">
                                    <a href="#registration-form" class="btn btn-success btn-lg cta-inline"
                                        data-bs-toggle="modal" data-bs-target="#createOrder">
                                        Хочу такое же решение для своей школы
                                    </a>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Как это работает: акцент на платежи + CRM -->
        <section id="how-it-works" class="py-5 bg-light">
            <div class="container">

                <div class="text-center mb-2">
                    <span class="section-label">Как это работает</span>
                </div>

                <h2 class="text-center mb-4">
                    <span class="mark-creative">Всю настройку кабинета мы берем на себя. Это очень удобно!</span>
                </h2>
                <p class="text-center text-muted mb-5 fs-5">
                    Вы не подключаете эквайринг, не покупаете онлайн-кассу и не настраиваете интеграции.
                    <br>Вы регистрируетесь. Мы настраиваем всё «под ключ».
                </p>

                @php
                    $steps = [
                        [
                            'icon' => 'img/landing/icons/Register.png',
                            'title' => 'Подключение школы за 1 день',
                            'desc' =>
                                'Оставляете заявку и передаёте реквизиты школы или ИП. Мы подключаем вас к платёжной инфраструктуре через Т-Банк.',
                        ],
                        [
                            'icon' => 'img/landing/icons/Import.png',
                            'title' => 'Перенос базы учеников и старт в системе',
                            'desc' =>
                                'Бесплатно переносим учеников, группы и расписания — даже если всё сейчас живёт в тетрадках и Excel. Вы сразу видите живую базу.',
                        ],
                        [
                            'icon' => 'img/landing/icons/Price.png',
                            'title' => 'Настройка абонплат и тарифов',
                            'desc' =>
                                'Вы задаёте стоимость занятий и абонементов по группам или ученикам — система сама понимает, кто и сколько должен за месяц.',
                        ],
                        [
                            'icon' => 'img/landing/icons/Credit-card.png',
                            'title' => 'Родители оплачивают онлайн — школа получает деньги',
                            'desc' =>
                                'Родители оплачивают занятия по ссылке. Деньги поступают напрямую на реквизиты школы, а kidscrm.online автоматически фиксирует оплату.',
                        ],
                        [
                            'icon' => 'img/landing/icons/reminder.png',
                            'title' => 'Напоминания, долги и отчёты в одном месте',
                            'desc' =>
                                'Сервис аккуратно напоминает об оплате, показывает актуальный список должников и формирует отчёты по группам, тренерам и месяцам.',
                        ],
                    ];
                    $half = ceil(count($steps) / 2);
                    $leftSteps = array_slice($steps, 0, $half);
                    $rightSteps = array_slice($steps, $half);
                @endphp

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">
                                <div class="row g-4">
                                    {{-- Левая колонка --}}
                                    <div class="col-md-6">
                                        @foreach ($leftSteps as $step)
                                            <div class="step-item mb-4">
                                                <div class="step-circle">
                                                    {{ $loop->iteration }}
                                                </div>
                                                <div class="step-line"></div>
                                                <div class="d-flex align-items-start">
                                                    <img src="{{ asset($step['icon']) }}" alt="{{ $step['title'] }}"
                                                        class="me-3 flex-shrink-0"
                                                        style="width:48px; height:48px; object-fit:contain;">
                                                    <div>
                                                        <h5 class="fw-bold mb-1">{{ $step['title'] }}</h5>
                                                        <p class="text-muted fs-6 mb-0">{{ $step['desc'] }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    {{-- Правая колонка --}}
                                    <div class="col-md-6">
                                        @foreach ($rightSteps as $step)
                                            <div class="step-item mb-4">
                                                <div class="step-circle">
                                                    {{ $loop->iteration + $half }}
                                                </div>
                                                @if (!$loop->last)
                                                    <div class="step-line"></div>
                                                @endif
                                                <div class="d-flex align-items-start">
                                                    <img src="{{ asset($step['icon']) }}" alt="{{ $step['title'] }}"
                                                        class="me-3 flex-shrink-0"
                                                        style="width:48px; height:48px; object-fit:contain;">
                                                    <div>
                                                        <h5 class="fw-bold mb-1">{{ $step['title'] }}</h5>
                                                        <p class="text-muted fs-6 mb-0">{{ $step['desc'] }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="text-center mt-4">
                                    <a href="#registration-form" class="btn btn-outline-success cta-inline"
                                        data-bs-toggle="modal" data-bs-target="#createOrder">
                                        Посмотреть, как это будет работать в вашей школе
                                    </a>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Ключевой функционал: акцент на деньгах и управлении -->
        <section id="features" class="py-5 bg-light">
            <div class="container">

                <!-- Заголовок блока -->
                <div class="row justify-content-center text-center mb-5">
                    <div class="col-lg-9">

                        <div class="mb-2">
                            <span class="section-label">Функционал</span>
                        </div>

                        <h2 class="mb-3">
                            <span class="mark-creative">Система для автоматизации управления детскими секциями</span>
                        </h2>
                        <p class="text-muted fs-5 mb-0">
                            Контроль оплат, единый учёт учеников и групп, онлайн-договоры — всё в одной панели,
                            чтобы меньше думать о сборах денег и больше о тренировках.
                        </p>
                        <div class="section-divider"></div>
                    </div>
                </div>

                <!-- Белая карточка внутри серого фона -->
                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body py-4 py-md-5 px-3 px-md-4">

                                <div class="row align-items-center">

                                    {{-- Первый столбец --}}
                                    <div class="col-md-4 mb-4 mb-md-0">
                                        @foreach ([
            [
                'icon' => 'img/landing/icons/functional/payment-acceptance.png',
                'text' => 'Приём платежей от родителей',
                'desc' => 'Система автоматически фиксирует поступления и долги — вы всегда знаете, кто оплатил и за какой период.',
            ],
            [
                'icon' => 'img/landing/icons/functional/automatic-reporting.png',
                'text' => 'Контроль оплат и задолженностей',
                'desc' => 'Доход по ученикам и группам, просрочки, динамика оплат — готовые отчёты в пару кликов.',
            ],
        ] as $item)
                                            <div class="d-flex align-items-start mb-4 p-3 rounded-4 bg-light">
                                                <div class="me-3 flex-shrink-0">
                                                    <div class="rounded-circle bg-white shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width:72px; height:72px;">
                                                        <img src="{{ asset($item['icon']) }}" alt="{{ $item['text'] }}"
                                                            style="max-width:48px; max-height:48px; object-fit:contain;">
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-1">{{ $item['text'] }}</h6>
                                                    <p class="text-muted fs-6 mb-0">{{ $item['desc'] }}</p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Центральное изображение --}}
                                    <div class="col-md-4 text-center mb-4 mb-md-0">
                                        <div class="border rounded-4 shadow-sm p-2 bg-light">
                                            <img src="{{ asset('img/landing/dashboard.png') }}"
                                                alt="Панель управления kidscrm.online" class="img-fluid rounded-3">
                                        </div>
                                    </div>

                                    {{-- Третий столбец --}}
                                    <div class="col-md-4">
                                        @foreach ([
            [
                'icon' => 'img/landing/icons/functional/user-group.png',
                'text' => 'Единый учёт учеников и групп',
                'desc' => 'Вся информация о детях, родителях, группах, тренерах и расписании в одном месте — с привязкой оплат и задолженностей.',
            ],
            [
                'icon' => 'img/landing/icons/functional/schedule-management.png',
                'text' => 'Онлайн подписание договоров с родителями',
                'desc' => 'Вы можете подписывать договоры с родителями онлайн — без бумажной волокиты и обязательных личных встреч.',
            ],
        ] as $item)
                                            <div class="d-flex align-items-start mb-4 p-3 rounded-4 bg-light">
                                                <div class="me-3 flex-shrink-0">
                                                    <div class="rounded-circle bg-white shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width:72px; height:72px;">
                                                        <img src="{{ asset($item['icon']) }}" alt="{{ $item['text'] }}"
                                                            style="max-width:48px; max-height:48px; object-fit:contain;">
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-1">{{ $item['text'] }}</h6>
                                                    <p class="text-muted fs-6 mb-0">{{ $item['desc'] }}</p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Наши уникальные преимущества -->
        <section class="bg-light py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">
                                <div class="row align-items-center">

                                    <div class="col-md-4 text-end mob-hide">
                                        <img src="{{ asset('img/landing/dance.png') }}" alt="Преимущества kidscrm.online"
                                            class="img-fluid rounded">
                                    </div>

                                    <div class="col-md-8 mb-4 mb-md-0">
                                        <div class="text-center mb-2">
                                            <span class="section-label">Почему мы</span>
                                        </div>

                                        <h2 class="text-center mb-5" id="advantages">
                                            <span class="mark-creative">Наши уникальные преимущества</span>
                                        </h2>

                                        <div class="container">
                                            <div class="row g-4">
                                                <div class="col-md-6">
                                                    <div class="card h-100 p-4 shadow-sm border-0 rounded-3">
                                                        <h5 class="fw-bold mb-3">Перенос данных “с нуля” под ключ</h5>
                                                        <p class="text-muted fs-6">
                                                            Мы бесплатно перенесём вашу базу учеников, групп и расписаний —
                                                            даже если
                                                            сейчас всё записано в тетрадке или разбросано по файлам. Вы не
                                                            тратите время
                                                            на ручной ввод — сразу начинаете работать.
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
                                                            На запуске с вами работает персональный специалист: помогает
                                                            настроить систему
                                                            под ваши процессы, отвечает на вопросы, обучает администраторов
                                                            и тренеров
                                                            в удобном формате.
                                                        </p>
                                                        <div class="mt-auto">
                                                            <i class="bi bi-person-raised-hand display-5 text-primary"></i>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-12">
                                                    <div class="card h-100 p-4 shadow-sm border-0 rounded-3 mt-3">
                                                        <h5 class="fw-bold mb-3">Готовая платёжная инфраструктура </h5>
                                                        <p class="text-muted fs-6 mb-0">
                                                            Вам не нужно отдельно подключать эквайринг, покупать
                                                            онлайн-кассу или
                                                            разбираться с фискальными накопителями. Мы уже интегрированы с
                                                            банком через
                                                            мультирасчёты: вы просто даёте реквизиты, а деньги поступают
                                                            напрямую вашей
                                                            школе, пока система автоматически фиксирует оплату.
                                                        </p>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </section>


        {{-- Для кого подходит --}}
        <section id="audience" class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-2">
                    <span class="section-label">Кому подходит</span>
                </div>

                <h2 class="text-center mb-3">
                    <span class="mark-creative">Для каких секций и школ подходит kidscrm.online</span>
                </h2>

                <p class="text-center text-muted mb-5 fs-5">
                    Сервис особенно полезен школам с десятками и сотнями учеников, когда Excel и тетрадки
                    уже не справляются, а руководителю нужен управляемый и прозрачный учёт денег.
                </p>

                @php
                    $leftAudience = [
                        [
                            'icon' => 'img/landing/icons/unit/soccer.png',
                            'title' => 'Футбольные школы и секции',
                            'desc' =>
                                'Автоматизация оплаты абонементов, учёт долгов и прозрачные отчёты по группам и тренерам. Можно отдельно учитывать разовые сборы: форма, турниры, сборы, летние лагеря.',
                        ],
                        [
                            'icon' => 'img/landing/icons/unit/dancer.png',
                            'title' => 'Танцевальные студии',
                            'desc' =>
                                'Удобный учёт оплат по абонементам и занятиям, напоминания родителям и контроль задолженностей. Дополнительно — фиксация участия в концертах и подготовка к выступлениям по группам.',
                        ],
                        [
                            'icon' => 'img/landing/icons/unit/martial-arts.png',
                            'title' => 'Секции боевых искусств',
                            'desc' =>
                                'Единая база учеников, групп и платежей: кто занимается, кто оплатил, кто должен. Отдельный учёт сборов за аттестации, пояса, соревнования и выезды.',
                        ],
                    ];

                    $rightAudience = [
                        [
                            'icon' => 'img/landing/icons/unit/chess.png',
                            'title' => 'Шахматные и интеллектуальные клубы',
                            'desc' =>
                                'Учёт занятий, абонементов и оплат, чтобы тренер занимался развитием детей, а не таблицами. Можно отдельно вести сборы за турниры и фиксировать участие в них.',
                        ],
                        [
                            'icon' => 'img/landing/icons/unit/music.png',
                            'title' => 'Музыкальные школы и студии',
                            'desc' =>
                                'Расписания, преподаватели, оплата занятий и контроль долгов в одном месте. Раздельный учёт индивидуальных и групповых занятий, сборов за зачёты и концерты.',
                        ],
                        [
                            'icon' => 'img/landing/icons/unit/talk.png',
                            'title' => 'Школы иностранных языков',
                            'desc' =>
                                'Автоматизация оплаты курсов и абонементов, напоминания и отчёты по группам и потокам. Можно вести уровни (A1–C2), потоки и заморозки абонементов по болезни или отъезду.',
                        ],
                    ];
                @endphp

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">
                                <div class="row align-items-center gy-4">
                                    {{-- Колонка слева: три пункта --}}
                                    <div class="col-md-5">
                                        @foreach ($leftAudience as $item)
                                            <div class="d-flex align-items-start mb-4">
                                                <img src="{{ asset($item['icon']) }}" alt="{{ $item['title'] }}"
                                                    class="me-3" style="width:64px; height:auto; object-fit:contain;">
                                                <div>
                                                    <h5 class="fw-bold mb-1">{{ $item['title'] }}</h5>
                                                    <p class="text-muted mb-0">{{ $item['desc'] }}</p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Центральная колонка: скрин сервиса --}}
                                    <div class="col-md-2 text-center">
                                        <img src="{{ asset('img/landing/iphone.png') }}"
                                            alt="Скриншот сервиса kidscrm.online"
                                            class="img-fluid rounded mx-auto d-block">
                                    </div>

                                    {{-- Колонка справа: три пункта --}}
                                    <div class="col-md-5">
                                        @foreach ($rightAudience as $item)
                                            <div class="d-flex align-items-start mb-4">
                                                <img src="{{ asset($item['icon']) }}" alt="{{ $item['title'] }}"
                                                    class="me-3" style="width:64px; height:auto; object-fit:contain;">
                                                <div>
                                                    <h5 class="fw-bold mb-1">{{ $item['title'] }}</h5>
                                                    <p class="text-muted mb-0">{{ $item['desc'] }}</p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="text-center mt-4">
                                    <a href="#registration-form" class="btn btn-outline-success cta-inline btn-order btn-order-transform"
                                        data-bs-toggle="modal" data-bs-target="#createOrder">
                                        Обсудить ваш формат секции и нужный функционал
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Стоимость -->
        <section id="pricing" class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-2">
                    <span class="section-label">Тарифы</span>
                </div>

                <h2 class="text-center mb-3">
                    <span class="mark-creative">Сколько это стоит</span>
                </h2>
                <p class="text-center fs-5 text-muted mb-5">
                    <span class="my-alert-color">Никакой абонентской платы и плат за внедрение</span> —
                    мы зарабатываем только тогда, когда вы получаете оплату от родителей.
                </p>

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">

                                <!-- Витрина стоимости -->
                                <div class="row g-4 justify-content-center">

                                    {{-- Миграция данных --}}
                                    <div class="col-md-6 col-lg-3">
                                        <div class="card border-0 shadow-sm h-100 p-4 text-center">
                                            <img src="{{ asset('img/landing/icons/price/transferring-data.png') }}"
                                                alt="Перенос данных" class="mx-auto mb-3"
                                                style="width:48px; height:48px; object-fit:contain;">
                                            <h5 class="fw-bold">
                                                <span class="my-alert-color">0 ₽</span> за перенос данных учеников
                                            </h5>
                                            <p class="text-muted fs-6">
                                                Перенесём вашу базу учеников, групп и расписаний “под ключ”, чтобы не
                                                тратить время команды на ручной ввод и стартовать быстро.
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Абонентская плата --}}
                                    <div class="col-md-6 col-lg-3">
                                        <div class="card border-0 shadow-sm h-100 p-4 text-center">
                                            <img src="{{ asset('img/landing/icons/price/money-fee.png') }}"
                                                alt="Абонентская плата" class="mx-auto mb-3"
                                                style="width:48px; height:48px; object-fit:contain;">
                                            <h5 class="fw-bold">
                                                <span class="my-alert-color">0 ₽</span> абонентская плата
                                            </h5>
                                            <p class="text-muted fs-6">
                                                Полный доступ ко всем функциям сервиса: учёт учеников, групп, расписаний,
                                                оплат и долгов — без ежемесячных платежей за использование платформы.
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Техническая поддержка --}}
                                    <div class="col-md-6 col-lg-3">
                                        <div class="card border-0 shadow-sm h-100 p-4 text-center">
                                            <img src="{{ asset('img/landing/icons/price/technical-support.png') }}"
                                                alt="Техническая поддержка" class="mx-auto mb-3"
                                                style="width:48px; height:48px; object-fit:contain;">
                                            <h5 class="fw-bold">
                                                <span class="my-alert-color">0 ₽</span> техническая поддержка
                                            </h5>
                                            <p class="text-muted fs-6">
                                                Персональное сопровождение: помогаем с настройкой, отвечаем на вопросы
                                                администраторов и тренеров, подсказываем, как выжать максимум из сервиса.
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Комиссия --}}
                                    <div class="col-md-6 col-lg-3">
                                        <div class="card border-0 shadow-sm h-100 p-4 text-center">
                                            <img src="{{ asset('img/landing/icons/price/commission.png') }}"
                                                alt="Комиссия сервиса" class="mx-auto mb-3"
                                                style="width:48px; height:48px; object-fit:contain;">
                                            <h5 class="fw-bold">
                                                <span class="my-alert-color">1% комиссия сервиса</span> только с успешных
                                                платежей
                                            </h5>
                                            <p class="text-muted fs-6">
                                                Оплата сервиса — небольшой процент от каждой успешной онлайн-оплаты. Никаких
                                                скрытых сборов: мы зарабатываем только когда платят вам.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="pricing-divider"></div>

                                <!-- Сравнение с классической схемой -->
                                <div class="row g-4 align-items-stretch">
                                    <div class="col-md-6">
                                        <div class="h-100 p-4 p-md-4 rounded-4 bg-light">
                                            <div class="text-uppercase small fw-semibold text-muted mb-2">
                                                Если подключать всё самостоятельно
                                            </div>
                                            <h5 class="fw-semibold mb-3">«По-старинке» через банк и свою кассу</h5>
                                            <ul class="text-muted small mb-0 ps-3">
                                                <li>Заключать договор эквайринга с банком или агрегатором.</li>
                                                <li>Покупать или арендовать онлайн-кассу (≈ 3 000 ₽ в месяц).</li>
                                                <li>Покупать фискальный накопитель (≈ 18 000 ₽ на 36 месяцев).</li>
                                                <li>Платить комиссию за эквайринг (обычно от 2% до 5%).</li>
                                                <li>Платить за пользование CRM, где ещё нужно настроить интеграции.</li>
                                                <li>Самостоятельно переносить данные учеников и расписание.</li>
                                                <li>Самим разбираться в настройке CRM и обучать сотрудников.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="h-100 p-4 p-md-4 rounded-4 bg-white border">
                                            <div class="text-uppercase small fw-semibold text-success mb-2">
                                                Если работать с kidscrm.online
                                            </div>
                                            <h5 class="fw-semibold mb-3">Готовая платёжная инфраструктура «под ключ»</h5>
                                            <ul class="text-muted small mb-3 ps-3">
                                                <li>Не нужен отдельный договор с банком по эквайрингу.</li>
                                                <li>Не нужно покупать и обслуживать онлайн-кассу — мы оплачиваем её сами.
                                                </li>
                                                <li>Не нужен фискальный накопитель — это тоже на нашей стороне.</li>
                                                <li>Вы не платите абонентскую плату за CRM.</li>
                                                <li>Мы сами переносим данные учеников и расписание в систему.</li>
                                                <li>Помогаем работать в сервисе, в том числе по телефону и в мессенджерах.
                                                </li>
                                                <li>Вы оплачиваете только % эквайринга, в который уже включена комиссия
                                                    сервиса.</li>
                                            </ul>

                                            <div class="text-md-end text-start">
                                                <a href="#registration-form" class="btn btn-outline-success cta-inline"
                                                    data-bs-toggle="modal" data-bs-target="#createOrder">
                                                    Посчитать выгоду для вашей школы
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div> <!-- /card-body -->
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Кейс футбольной школы -->
        <section class="py-5 bg-light position-relative overflow-hidden">
            <div class="container">

                <!-- Заголовок -->
                <div class="row justify-content-center text-center mb-5">

                    <div class="col-lg-12 col-xl-11">
                        <div class="mb-2">
                            <span class="section-label">Кейс</span>
                        </div>

                        <h2 class="text-center mb-4">
                            <span class="mark-creative">Сервис, выросший из реальной футбольной школы в
                                Санкт-Петербурге</span>
                        </h2>

                        <p class="text-muted fs-5 mb-0">
                            Сначала это была внутренняя разработка для футбольной школы на 80+ футболистов в
                            Санкт-Петербурге.
                            Более 6 лет система помогает школе собирать оплату, вести учёт и не терять деньги из-за хаоса.
                        </p>
                    </div>
                </div>

                <!-- Карточка -->
                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">

                        <div class="card border-0 shadow-lg rounded-4 card-soft">
                            <div class="card-body p-4 p-md-5">

                                <!-- Блок с цифрой -->
                                <div class="text-center mb-5">
                                    <div class="display-4 fw-bold mb-2 my-alert-color">
                                        6+ лет
                                    </div>
                                    <div class="fs-5 fw-semibold">
                                        ежедневной работы в живой футбольной школе Санкт-Петербурга
                                    </div>
                                    <div class="text-muted mt-2">
                                        Через систему проходят реальные дети, родители, группы и регулярные платежи.
                                        Школа продолжает работать в kidscrm.online и сегодня.
                                    </div>
                                </div>

                                <div class="row g-4">

                                    <!-- Левая колонка -->
                                    <div class="col-md-6">
                                        <div class="p-4 bg-light rounded-4 h-100">
                                            <div class="fw-semibold mb-2">
                                                Как это выглядело раньше
                                            </div>
                                            <ul class="text-muted mb-3 ps-3">
                                                <li>владелец сам обзванивал родителей и отвечал на звонки по оплатам;</li>
                                                <li>каждый месяц вручную напоминал о ценах и сроках оплат;</li>
                                                <li>тратил время на расчёты, сверки и разбор вопросов «сколько мы должны?»;
                                                </li>
                                                <li>учёт жил в чатах, тетрадках и таблицах.</li>
                                            </ul>

                                            <div class="fw-semibold mb-2">
                                                Что изменилось с kidscrm.online
                                            </div>
                                            <ul class="text-muted mb-0 ps-3">
                                                <li>установка цен и абонплат занимает ~30 минут 1 раз в месяц;</li>
                                                <li>система сама считает долги и напоминает родителям об оплате;</li>
                                                <li>звонки и сообщения «сколько мы должны в этом месяце?» почти исчезли;
                                                </li>
                                                <li>руководитель видит деньги и долги по группам и тренерам в несколько
                                                    кликов.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Правая колонка -->
                                    <div class="col-md-6">
                                        <div class="p-4 bg-primary bg-opacity-10 rounded-4 h-100">
                                            <div class="fw-semibold mb-2">
                                                Что это даёт руководителю секции
                                            </div>
                                            <div class="text-muted mb-3">
                                                Деньги собираются вовремя, сотрудники не тратят часы на
                                                напоминания, а хаос в оплатах превращается
                                                в прозрачную и управляемую систему.
                                            </div>
                                            <ul class="text-muted mb-0 ps-3">
                                                <li>меньше ручной рутины и переписок с родителями;</li>
                                                <li>понятная картина по доходу и долгам;</li>
                                                <li>меньше стресса — больше времени на развитие школы и тренировочный
                                                    процесс.</li>
                                            </ul>
                                        </div>
                                    </div>

                                </div>

                                <!-- Финальный акцент -->
                                <div class="text-center mt-5">
                                    <div class="fw-bold fs-5">
                                        Это не абстрактная CRM “для бизнеса”.
                                    </div>
                                    <div class="text-muted mb-3">
                                        Это система, которая <span class=" my-alert-color"> родилась внутри футбольной школы в
                                            СПб</span>
                                        и продолжает работать там <span class="my-alert-color"> каждый день</span>.
                                    </div>

                                    <a href="#registration-form" class="btn btn-success btn-lg" data-bs-toggle="modal"
                                        data-bs-target="#createOrder">
                                        Обсудить, как перенести опыт этой школы к вам
                                    </a>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-2">
                    <span class="section-label">Частые вопросы</span>
                </div>

                <h2 class="text-center mb-4">FAQ</h2>

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">
                                <div class="accordion" id="faqAccordion">
                                    @foreach ([
            'Можно ли использовать сервис бесплатно?' => 'Да. У нас нет абонентской платы и плат за внедрение. Вы платите только небольшую комиссию от успешных онлайн-платежей родителей.',
            'Нужно ли подключать онлайн-кассу, эквайринг или покупать фискальный накопитель?' => 'Нет. Мы уже подключены к банку через мультирасчёты Т-Банка и берём платёжную инфраструктуру на себя. Онлайн-касса сервиса зарегистрирована в налоговой, а чеки по оплатам автоматически отправляются родителям на электронную почту.',
            'Насколько законна схема оплат через kidscrm.online?' => 'Схема построена на мультирасчётах с крупным российским банком — Т-Банком. Деньги поступают на реквизиты школы, формируются фискальные чеки через зарегистрированную онлайн-кассу, и данные передаются в ФНС. Вы работаете в понятной и прозрачной модели.',
            'Насколько безопасны мои данные и платежи родителей?' => 'Мы работаем через инфраструктуру крупного банка — Т-Банка. Платежи обрабатываются на стороне банка, а kidscrm.online фиксирует статусы оплат и ведёт учёт. Доступ к системе настраивается по ролям, все ключевые изменения фиксируются. Данные хранятся на защищённых серверах, при необходимости поможем с выгрузкой и переносом.',
            'Помогаете ли вы с добавлением базы учеников?' => 'Да. Мы бесплатно переносим текущую базу учеников, групп и расписаний “под ключ” — даже если сейчас всё хранится в тетрадке или Excel.',
            'Что будет, если родитель не оплатил вовремя?' => 'Сервис аккуратно напоминает родителю об оплате по электронной почте и/или другим каналам, если они подключены. Пени, штрафы и другие условия вы определяете сами в правилах школы и договоре — сервис только фиксирует долги и отправляет напоминания.',
            'Как работает возврат оплаты?' => 'Администратор школы может оформить возврат в личном кабинете: нажимает кнопку возврата по нужному платежу, и деньги возвращаются на карту плательщика (родителя). Максимальный срок, в течение которого можно сделать возврат, настраивается администратором школы в системе.',
            'Как быстро деньги поступают на счёт школы?' => 'Сроки зависят от настроек банковских расчётов, но обычно зачисление проходит в стандартные для эквайринга сроки. В системе вы видите статусы оплат и можете сверять поступления.',
            'Есть ли мобильное приложение?' => 'Отдельного приложения пока нет, но веб-интерфейс адаптирован под смартфоны и планшеты — администратор и тренеры могут работать с телефонов.',
        ] as $question => $answer)
                                        <div class="accordion-item mb-3 border-0 shadow-sm">
                                            <h2 class="accordion-header" id="heading{{ $loop->index }}">
                                                <button
                                                    class="accordion-button collapsed bg-white text-dark d-flex justify-content-between align-items-center"
                                                    type="button" data-bs-toggle="collapse"
                                                    data-bs-target="#collapse{{ $loop->index }}" aria-expanded="false"
                                                    aria-controls="collapse{{ $loop->index }}">
                                                    <span class="flex-grow-1 text-start">{{ $question }}</span>
                                                    <i class="bi bi-chevron-down ms-2"></i>
                                                </button>
                                            </h2>
                                            <div id="collapse{{ $loop->index }}" class="accordion-collapse collapse"
                                                aria-labelledby="heading{{ $loop->index }}"
                                                data-bs-parent="#faqAccordion">
                                                <div class="accordion-body text-muted">
                                                    {{ $answer }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Call to Action -->
        <section id="cta" class="py-5 bg-call-to-action">
            <div class="container text-center">
                <h2 class="display-6 fw-bold mb-3">
                    Хотите навести порядок в оплатах и забыть про хаос с долгами?
                </h2>

                <p class="fs-5 mb-3">
                    Попробуйте <span class="fw-bold">kidscrm.online</span> —
                    автоматизацию сборов платежей без абонентской платы,
                    без подключения эквайринга и лишних расходов.
                </p>

                <!-- Акцент на поддержке -->
                <p class="fs-5 fw-semibold mb-4">
                    При запуске вас сопровождает персональный технический специалист — он помогает с настройкой и остаётся
                    на связи.
                </p>

                <a href="#registration-form" class="btn btn-success btn-lg btn-order btn-order-transform" data-bs-toggle="modal"
                    data-bs-target="#createOrder" data-analytics="cta_bottom_demo">
                    Записаться на демо 15 минут
                </a>
            </div>
        </section>

    </div>

@endsection
