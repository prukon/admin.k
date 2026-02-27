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
.mark-creative {
    position: relative;
    display: inline-block;
    z-index: 1;
}

.mark-creative::before {
    content: "";
    position: absolute;
    left: -8px;
    right: -8px;
    bottom: 0.1em;
    height: 0.7em;
    background: linear-gradient(
        95deg,
        #ffb366 0%,
        #ffc694 40%,
        #ffb366 80%
    );
    border-radius: 12px;
    transform: rotate(-0.5deg);
    z-index: -1;
    box-shadow: 0 4px 12px rgba(255, 182, 120, 0.4);
}

/* Единая типографика лендинга */
.landing-page {
    color: #111827; /* основной текст чуть темнее, чем дефолтный Bootstrap, но не глухой чёрный */
}

/* Базовый текст и списки */
.landing-page p,
.landing-page li {
    font-size: 1rem;
    line-height: 1.6;
    color: #111827;
}

/* Более читабельный muted */
.landing-page .text-muted {
    color: #4b5563 !important;
}

/* Заголовки */
.landing-page h1,
.landing-page h2,
.landing-page h3,
.landing-page h4,
.landing-page h5,
.landing-page h6 {
    color: #111827;
}

/* h2 по всему ленду */
.landing-page h2 {
    font-size: 1.9rem;
    line-height: 1.25;
    font-weight: 700;
}

/* Текст в карточках – немного компактнее */
.landing-page .card p {
    font-size: 0.97rem;
    line-height: 1.55;
}

/* Подзаголовки в карточках */
.landing-page .card h5,
.landing-page .card h6 {
    font-size: 1.05rem;
}

/* Текст в FAQ */
.landing-page .accordion-body {
    font-size: 0.97rem;
    line-height: 1.6;
}
</style>

<div class="landing-page">

    <!-- Hero: конверсионный хедер -->
    <section class="bg-light py-4 py-md-4">
        <div class="container">

            <!-- Основной ряд -->
            <div class="row align-items-center">

                <!-- Заголовочная часть -->
                <div class="col-12 mb-3 mb-md-4">
                    <h1 class="fw-bold text-center mb-3" style="font-size: 1.9rem; line-height: 1.2;">
                        <span class="mark-creative">CRM для детских секций и кружков</span>
                    </h1>

                    <p class="text-center mb-1" style="font-size: 1.05rem;">
                        Принимайте онлайн-оплату за занятия без онлайн-кассы
                    </p>

                    <p class="fw-semibold text-center mt-3 mb-0" style="font-size: 1rem;">
                        <span class="alert-color">0 ₽ в месяц </span> — только комиссия эквайринга
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
                                style="width:20px; height:20px; object-fit:contain;" alt="Не нужно подключать эквайринг">
                            <span>Не нужно подключать эквайринг</span>
                        </li>

                        <li class="d-flex align-items-start mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}" class="me-2 mt-1"
                                style="width:20px; height:20px; object-fit:contain;" alt="Не нужно считать долги вручную">
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
                            <a href="#registration-form" class="btn btn-success btn-lg px-4" style="min-width:230px;"
                                data-bs-toggle="modal" data-bs-target="#createOrder">
                                Записаться на демо
                            </a>

                            <!-- YouTube + текст -->
                            {{-- <div class="d-flex align-items-center gap-2">

                                <a href="https://youtube.com" target="_blank"
                                    class="d-flex align-items-center justify-content-center"
                                    style="width:56px; height:56px; text-decoration:none;">
                                    <i class="fab fa-youtube" style="font-size:40px; color:#ff0000;"></i>
                                </a>

                                <span class="fw-medium" style="font-size: 1rem; width: max-content;">
                                    Ознакомительное видео
                                </span>

                            </div> --}}

                        </div>

                    </div>

                </div>

                <!-- Правый столбец -->
                <div class="col-md-6 d-flex justify-content-center align-items-center mt-3 mt-md-0">
                    <img src="{{ asset('img/landing/football.png') }}" alt="CRM для детских секций и футбольных школ"
                        class="img-fluid" style="max-height:340px; width:auto;">
                </div>

            </div>

            <!-- Ряд с оператором персональных данных -->
            <div class="row mt-4">

            </div>

        </div>
    </section>

    <!-- Цена хаоса -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-12 col-xl-11">

                    <h2 class="text-center mb-3">
                        <span class="mark-creative">
                            Хаос в оплатах стоит школе дороже, чем кажется
                        </span>
                    </h2>

                    <p class="text-center text-muted fs-5 mb-4">
                        Когда платежи живут в чатах, таблицах и тетрадях, вы теряете не только удобство — вы теряете деньги
                        и контроль.
                    </p>

                    <div class="card border-0 shadow-lg rounded-4">
                        <div class="card-body py-4 px-3 px-md-4">

                            <ul class="list-unstyled mb-3">

                                <li class="d-flex mb-3">
                                    <div class="me-3 mt-1">
                                        <i class="fas fa-exclamation-circle alert-color"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-semibold mb-1">
                                            Недосбор 10–20% оплат и кассовые разрывы
                                        </h5>
                                        <div class="text-muted">
                                            Группы заполнены, а выручка не добирается — деньги «растворяются» в хаосе учёта
                                            и поздних платежей.
                                        </div>
                                    </div>
                                </li>

                                <li class="d-flex mb-3">
                                    <div class="me-3 mt-1">
                                        <i class="fas fa-exclamation-circle alert-color"></i>
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
                                        <i class="fas fa-exclamation-circle alert-color"></i>
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
                                        <i class="fas fa-exclamation-circle alert-color"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-semibold mb-1">
                                            Постоянные напоминания и личные сообщения
                                        </h5>
                                        <div class="text-muted">
                                            Чтобы деньги пришли вовремя, приходится самому писать родителям о просрочках и
                                            буквально «выбивать» оплату.
                                        </div>
                                    </div>
                                </li>

                                <li class="d-flex mb-3">
                                    <div class="me-3 mt-1">
                                        <i class="fas fa-exclamation-circle alert-color"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-semibold mb-1">
                                            Бесконечные вопросы «А сколько мы должны в этом месяце?»
                                        </h5>
                                        <div class="text-muted">
                                            Вместо того чтобы развивать школу, вы тратите время на ответы по суммам, скидкам
                                            и датам оплат.
                                        </div>
                                    </div>
                                </li>

                                <li class="d-flex mb-3">
                                    <div class="me-3 mt-1">
                                        <i class="fas fa-exclamation-circle alert-color"></i>
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
                                Даже для небольшой секции это легко превращается в сотни тысяч рублей упущенной выручки и
                                постоянный стресс за год.
                            </p>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- Как это работает: акцент на платежи + CRM -->
    <section id="how-it-works" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">
                <span class="mark-creative">Как мы можем помочь вам в управлении детскими секциями</span>
            </h2>
            <p class="text-center text-muted mb-5 fs-5">
                Вы не подключаете эквайринг, не покупаете онлайн-кассу и не настраиваете интеграции.
                <br>Вы регистрируетесь. Мы все настраиваем.
            </p>

            @php
                $steps = [
                    [
                        'icon' => 'img/landing/icons/Register.png',
                        'title' => 'Подключение за 1 день',
                        'desc' =>
                            'Оставляете заявку, передаёте реквизиты школы или ИП — мы подключаем вас к платёжной инфраструктуре.',
                    ],
                    [
                        'icon' => 'img/landing/icons/Import.png',
                        'title' => 'Перенос базы учениников и настройка “под ключ”',
                        'desc' =>
                            'Мы бесплатно переносим учеников, группы и расписания даже из тетрадки или Excel, чтобы вы сразу начали работать в системе.',
                    ],
                    [
                        'icon' => 'img/landing/icons/Price.png',
                        'title' => 'Настройка абонплат и тарифов',
                        'desc' =>
                            'Вы задаёте стоимость занятий и абонементов по группам или индивидуально для учеников — система сама посчитает, кто сколько должен.',
                    ],
                    [
                        'icon' => 'img/landing/icons/Credit-card.png',
                        'title' => 'Родители оплачивают — школа получает деньги',
                        'desc' =>
                            'Родители оплачивают занятия онлайн. Деньги поступают напрямую на реквизиты вашей школы, а kidscrm.online автоматически фиксирует оплату.',
                    ],
                    [
                        'icon' => 'img/landing/icons/reminder.png',
                        'title' => 'Автоматические напоминания и работа с долгами',
                        'desc' =>
                            'Система аккуратно напоминает родителям об оплате и показывает вам актуальный список должников по группам и периодам.',
                    ],
                    [
                        'icon' => 'img/landing/icons/Report.png',
                        'title' => 'Отчётность и аналитика в один клик',
                        'desc' =>
                            'Вы видите, кто оплатил, кто должен, доход по секциям, тренерам и месяцам — без ручных сверок и таблиц.',
                    ],
                    [
                        'icon' => 'img/landing/icons/saving-time.png',
                        'title' => 'Экономия времени и нервов',
                        'desc' =>
                            'Администратор и тренеры тратят меньше времени на деньги и переписки, а больше — на работу с детьми и развитие школы.',
                    ],
                ];
                $half = ceil(count($steps) / 2);
                $leftSteps = array_slice($steps, 0, $half);
                $rightSteps = array_slice($steps, $half);
            @endphp

            <div class="row justify-content-center">
                <div class="col-lg-12 col-xl-11">
                    <div class="card border-0 shadow-lg rounded-4 bg-white">
                        <div class="card-body p-4 p-md-5">
                            <div class="row g-4">
                                {{-- Левая колонка --}}
                                <div class="col-md-6">
                                    @foreach ($leftSteps as $step)
                                        <div class="d-flex align-items-start border-bottom pb-3 mb-3">
                                            <img src="{{ asset($step['icon']) }}" alt="{{ $step['title'] }}" class="me-3"
                                                style="width:48px; height:48px; object-fit:contain;">
                                            <div>
                                                <h5 class="fw-bold mb-1">{{ $step['title'] }}</h5>
                                                <p class="text-muted fs-6 mb-0">{{ $step['desc'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                {{-- Правая колонка --}}
                                <div class="col-md-6">
                                    @foreach ($rightSteps as $step)
                                        <div class="d-flex align-items-start border-bottom pb-3 mb-3">
                                            <img src="{{ asset($step['icon']) }}" alt="{{ $step['title'] }}" class="me-3"
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

                    <h2 class="mb-3">
                        <span class="mark-creative">Система для автоматизации управления детскими секциями</span>
                    </h2>
                    <p class="text-muted fs-5 mb-0">
                        Контроль оплат, единый учёт учеников и групп, онлайн-договоры — всё в одной панели,
                        чтобы меньше думать о сборах денег и больше о тренировках.
                    </p>
                </div>
            </div>

            <!-- Белая карточка внутри серого фона -->
            <div class="row justify-content-center">
                <div class="col-lg-12 col-xl-11">
                    <div class="card border-0 shadow-lg rounded-4 bg-white">
                        <div class="card-body py-4 py-md-5 px-3 px-md-4">

                            <div class="row align-items-center">

                                {{-- Первый столбец --}}
                                <div class="col-md-4 mb-4 mb-md-0">
                                    @foreach ([
                                        [
                                            'icon' => 'img/landing/icons/functional/payment-acceptance.png',
                                            'text' => 'Прием платежей от родителей',
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
                                            'text' => 'Единый учет учеников и групп',
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
                    <div class="card border-0 shadow-lg rounded-4 bg-white">
                        <div class="card-body p-4 p-md-5">
                            <div class="row align-items-center">

                                <div class="col-md-4 text-end mob-hide">
                                    <img src="{{ asset('img/landing/dance.png') }}" alt="Преимущества kidscrm.online"
                                        class="img-fluid rounded">
                                </div>

                                <div class="col-md-8 mb-4 mb-md-0">
                                    <h2 class="text-center mb-5" id="advantages">
                                        <span class="mark-creative">Наши уникальные преимущества</span>
                                    </h2>

                                    <div class="container">
                                        <div class="row g-4">
                                            <div class="col-md-6">
                                                <div class="card h-100 p-4 shadow-sm border-0 rounded-3">
                                                    <h5 class="fw-bold mb-3">Перенос данных “с нуля” под ключ</h5>
                                                    <p class="text-muted fs-6">
                                                        Мы бесплатно перенесём вашу базу учеников, групп и расписаний — даже если
                                                        сейчас всё записано в тетрадке или разбросано по файлам. Вы не тратите время
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
                                                        На запуске с вами работает персональный специалист: помогает настроить систему
                                                        под ваши процессы, отвечает на вопросы, обучает администраторов и тренеров
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
                                                        Вам не нужно отдельно подключать эквайринг, покупать онлайн-кассу или
                                                        разбираться с фискальными накопителями. Мы уже интегрированы с банком через
                                                        мультирасчёты: вы просто даёте реквизиты, а деньги поступают напрямую вашей
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

    <!-- Стоимость -->
    <section id="pricing" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">
                <span class="mark-creative">Сколько это стоит</span>
            </h2>
            <p class="text-center fs-5 text-muted mb-5">
                <span class="alert-color">Никакой абонентской платы и плат за внедрение</span> —
                мы зарабатываем только тогда, когда вы получаете оплату от родителей.
            </p>

            <div class="row justify-content-center">
                <div class="col-lg-12 col-xl-11">
                    <div class="card border-0 shadow-lg rounded-4 bg-white">
                        <div class="card-body p-4 p-md-5">

                            <div class="row g-4 justify-content-center">
                          
                                {{-- Миграция данных --}}
                                <div class="col-md-6 col-lg-3">
                                    <div class="card border-0 shadow-sm h-100 p-4 text-center">
                                        <img src="{{ asset('img/landing/icons/price/transferring-data.png') }}" alt="Перенос данных"
                                            class="mx-auto mb-3" style="width:48px; height:48px; object-fit:contain;">
                                        <h5 class="fw-bold">
                                            <span class="alert-color">0 ₽</span> за перенос данных учеников
                                        </h5>
                                        <p class="text-muted fs-6">
                                            Перенесём вашу базу учеников, групп и расписаний “под ключ”, чтобы не тратить
                                            время команды на ручной ввод и стартовать быстро.
                                        </p>
                                    </div>
                                </div>

                                      {{-- Абонентская плата --}}
                                      <div class="col-md-6 col-lg-3">
                                        <div class="card border-0 shadow-sm h-100 p-4 text-center">
                                            <img src="{{ asset('img/landing/icons/price/money-fee.png') }}" alt="Абонентская плата"
                                                class="mx-auto mb-3" style="width:48px; height:48px; object-fit:contain;">
                                            <h5 class="fw-bold">
                                                <span class="alert-color">0 ₽</span> абонентская плата
                                            </h5>
                                            <p class="text-muted fs-6">
                                                Полный доступ ко всем функциям сервиса: учёт учеников, групп, расписаний, оплат и долгов
                                                — без ежемесячных платежей за использование платформы.
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
                                            <span class="alert-color">0 ₽</span> техническая поддержка
                                        </h5>
                                        <p class="text-muted fs-6">
                                            Персональное сопровождение: помогаем с настройкой, отвечаем на вопросы администраторов
                                            и тренеров, подсказываем, как выжать максимум из сервиса.
                                        </p>
                                    </div>
                                </div>

                                {{-- Комиссия --}}
                                <div class="col-md-6 col-lg-3">
                                    <div class="card border-0 shadow-sm h-100 p-4 text-center">
                                        <img src="{{ asset('img/landing/icons/price/commission.png') }}" alt="Комиссия сервиса"
                                            class="mx-auto mb-3" style="width:48px; height:48px; object-fit:contain;">
                                        <h5 class="fw-bold">
                                            <span class="alert-color">1% комиссия сервиса</span> только с успешных платежей
                                        </h5>
                                        <p class="text-muted fs-6">
                                            Оплата сервиса — небольшой процент от каждой успешной онлайн-оплаты. Никаких
                                            скрытых сборов: мы зарабатываем только когда платят вам.
                                        </p>
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
            <h2 class="text-center mb-5">
                <span class="mark-creative">Для каких секций и школ подходит kidscrm.online</span>
            </h2>

            @php
                $leftAudience = [
                    [
                        'icon' => 'img/landing/icons/unit/soccer.png',
                        'title' => 'Футбольные школы и секции',
                        'desc' =>
                            'Автоматизация оплаты абонементов, учёт долгов и прозрачные отчёты по группам и тренерам.',
                    ],
                    [
                        'icon' => 'img/landing/icons/unit/dancer.png',
                        'title' => 'Танцевальные студии',
                        'desc' =>
                            'Удобный учёт оплат по абонементам и занятиям, напоминания родителям и контроль задолженностей.',
                    ],
                    [
                        'icon' => 'img/landing/icons/unit/martial-arts.png',
                        'title' => 'Секции боевых искусств',
                        'desc' => 'Единая база учеников, групп и платежей: кто занимается, кто оплатил, кто должен.',
                    ],
                ];

                $rightAudience = [
                    [
                        'icon' => 'img/landing/icons/unit/chess.png',
                        'title' => 'Шахматные и интеллектуальные клубы',
                        'desc' =>
                            'Учёт занятий, абонементов и оплат, чтобы тренер занимался развитием детей, а не таблицами.',
                    ],
                    [
                        'icon' => 'img/landing/icons/unit/music.png',
                        'title' => 'Музыкальные школы и студии',
                        'desc' => 'Расписания, преподаватели, оплата занятий и контроль долгов в одном месте.',
                    ],
                    [
                        'icon' => 'img/landing/icons/unit/talk.png',
                        'title' => 'Школы иностранных языков',
                        'desc' =>
                            'Автоматизация оплаты курсов и абонементов, напоминания и отчёты по группам и потокам.',
                    ],
                ];
            @endphp

            <div class="row justify-content-center">
                <div class="col-lg-12 col-xl-11">
                    <div class="card border-0 shadow-lg rounded-4 bg-white">
                        <div class="card-body p-4 p-md-5">
                            <div class="row align-items-center gy-4">
                                {{-- Колонка слева: три пункта --}}
                                <div class="col-md-5">
                                    @foreach ($leftAudience as $item)
                                        <div class="d-flex align-items-start mb-4">
                                            <img src="{{ asset($item['icon']) }}" alt="{{ $item['title'] }}" class="me-3"
                                                style="width:64px; height:auto; object-fit:contain;">
                                            <div>
                                                <h5 class="fw-bold mb-1">{{ $item['title'] }}</h5>
                                                <p class="text-muted mb-0">{{ $item['desc'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Центральная колонка: скрин сервиса --}}
                                <div class="col-md-2 text-center">
                                    <img src="{{ asset('img/landing/iphone.png') }}" alt="Скриншот сервиса kidscrm.online"
                                        class="img-fluid rounded mx-auto d-block">
                                </div>

                                {{-- Колонка справа: три пункта --}}
                                <div class="col-md-5">
                                    @foreach ($rightAudience as $item)
                                        <div class="d-flex align-items-start mb-4">
                                            <img src="{{ asset($item['icon']) }}" alt="{{ $item['title'] }}" class="me-3"
                                                style="width:64px; height:auto; object-fit:contain;">
                                            <div>
                                                <h5 class="fw-bold mb-1">{{ $item['title'] }}</h5>
                                                <p class="text-muted mb-0">{{ $item['desc'] }}</p>
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

    <!-- Кейс футбольной школы -->
    <section class="py-5 bg-light position-relative overflow-hidden">
        <div class="container">

            <!-- Заголовок -->
            <div class="row justify-content-center text-center mb-5">

                <div class="col-lg-12 col-xl-11">
                    <h2 class="text-center mb-4">
                        <span class="mark-creative">Сервис, выросший из реальной футбольной школы в Санкт-Петербурге</span>
                    </h2>

                    <p class="text-muted fs-5 mb-0">
                        Сначала это была внутренняя разработка для футбольной школы в Санкт-Петербурге.
                        Со временем система выросла в самостоятельный проект, который теперь доступен другим секциям и кружкам.
                    </p>
                </div>
            </div>

            <!-- Карточка -->
            <div class="row justify-content-center">
                <div class="col-lg-12 col-xl-11">

                    <div class="card border-0 shadow-lg rounded-4">
                        <div class="card-body p-4 p-md-5">

                            <!-- Блок с цифрой -->
                            <div class="text-center mb-5">
                                <div class="display-4 fw-bold mb-2 alert-color">
                                    4 года
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
                                            Что появилось в процессе реальной работы
                                        </div>
                                        <ul class="text-muted mb-0 ps-3">
                                            <li>учёт задолженностей</li>
                                            <li>автоматические напоминания родителям</li>
                                            <li>отчётность по оплатам</li>
                                            <li>гибкая настройка абонементов</li>
                                            <li>онлайн подписание договоров с родителями</li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Правая колонка -->
                                <div class="col-md-6">
                                    <div class="p-4 bg-primary bg-opacity-10 rounded-4 h-100">
                                        <div class="fw-semibold mb-2">
                                            Что это даёт руководителю секции
                                        </div>
                                        <div class="text-muted">
                                            Деньги собираются вовремя, сотрудники не тратят часы на
                                            напоминания, а хаос в оплатах превращается
                                            в прозрачную и управляемую систему.
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- Финальный акцент -->
                            <div class="text-center mt-5">
                                <div class="fw-bold fs-5">
                                    Это не абстрактная CRM “для бизнеса”.
                                </div>
                                <div class="text-muted">
                                    Это система, которая <span class="alert-color"> родилась внутри футбольной школы в
                                        СПб</span>
                                    и продолжает работать там <span class="alert-color"> каждый день</span>.
                                </div>
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
            <h2 class="text-center mb-4">FAQ</h2>

            <div class="row justify-content-center">
                <div class="col-lg-12 col-xl-11">
                    <div class="card border-0 shadow-lg rounded-4 bg-white">
                        <div class="card-body p-4 p-md-5">
                            <div class="accordion" id="faqAccordion">
                                @foreach ([
                                    'Можно ли использовать сервис бесплатно?' => 'Да. У нас нет абонентской платы и плат за внедрение. Вы платите только небольшую комиссию от успешных онлайн-платежей родителей.',
                                    'Нужно ли подключать онлайн-кассу, эквайринг или покупать фискальный накопитель?' => 'Нет. Мы уже подключены к банку через мультирасчёты и берём платёжную инфраструктуру на себя. Вы просто даёте реквизиты, а деньги поступают непосредственно вашей школе.',
                                    'Помогаете ли вы с добавлением базы учеников?' => 'Да. Мы бесплатно переносим текущую базу учеников, групп и расписаний “под ключ” — даже если сейчас всё хранится в тетрадке или Excel.',
                                    'Как быстро деньги поступают на счёт школы?' => 'Сроки зависят от настроек банковских расчётов, но обычно зачисление проходит в стандартные для эквайринга сроки. В системе вы видите статусы оплат и можете сверять поступления.',
                                    'Насколько безопасны мои данные?' => 'Доступ к системе настраивается по ролям, все ключевые изменения фиксируются. Данные хранятся на защищённых серверах, при необходимости поможем с выгрузкой и переносом.',
                                    'Есть ли мобильное приложение?' => 'Отдельного приложения пока нет, но веб-интерфейс адаптирован под смартфоны и планшеты — администратор и тренеры могут работать с телефонов.',
                                ] as $question => $answer)
                                    <div class="accordion-item mb-3 border-0 shadow-sm">
                                        <h2 class="accordion-header" id="heading{{ $loop->index }}">
                                            <button
                                                class="accordion-button collapsed bg-white text-dark d-flex justify-content-between align-items-center"
                                                type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $loop->index }}"
                                                aria-expanded="false" aria-controls="collapse{{ $loop->index }}">
                                                <span class="flex-grow-1 text-start">{{ $question }}</span>
                                                <i class="bi bi-chevron-down ms-2"></i>
                                            </button>
                                        </h2>
                                        <div id="collapse{{ $loop->index }}" class="accordion-collapse collapse"
                                            aria-labelledby="heading{{ $loop->index }}" data-bs-parent="#faqAccordion">
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
                При запуске вас сопровождает персональный технический специалист — он помогает с настройкой и остаётся на
                связи.
            </p>

            <a href="#registration-form" class="btn btn-success btn-lg" data-bs-toggle="modal"
                data-bs-target="#createOrder">
                Записаться на демо 15 минут
            </a>
        </div>
    </section>

</div>

@endsection