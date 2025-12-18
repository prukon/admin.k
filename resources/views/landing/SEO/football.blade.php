@extends('layouts.landingPage')

@section('title', 'CRM для футбольной секции — учет учеников, расписание, оплаты | kidscrm.online')

@section('meta')
    {{-- Если в layouts.landingPage есть @yield('meta') — отлично. Если нет, можно вставить это в <head> шаблона. --}}
    <meta name="description" content="kidscrm.online — CRM для футбольных секций и школ: учет учеников и команд, расписание тренировок, онлайн-оплата, контроль задолженностей, договоры с родителями и отчеты. Без абонплаты, комиссия только с платежей.">
    <link rel="canonical" href="{{ url('/crm-dlya-futbolnoy-sekcii') }}">
@endsection

@section('content')

    {{-- Хлебные крошки --}}
    <section class="bg-light py-3 border-bottom">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ url('/') }}" class="text-decoration-none">Главная</a></li>
                    <li class="breadcrumb-item active" aria-current="page">CRM для футбольной секции</li>
                </ol>
            </nav>
        </div>
    </section>

    {{-- HERO --}}
    <section class="py-5">
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-lg-6">
                    <h1 class="display-6 fw-bold">
                        CRM для футбольной секции и футбольной школы — расписание, оплаты и учет учеников в одном месте
                    </h1>

                    <p class="lead text-muted mt-3">
                        kidscrm.online помогает администратору и тренерам держать под контролем
                        <b>команды, группы, расписание тренировок, задолженности и оплаты</b> — без таблиц и тетрадок.
                    </p>

                    <ul class="list-unstyled mt-4 mb-4">
                        <li class="d-flex align-items-start mb-2">
                            <span class="me-2">✅</span>
                            <span><b>Учет учеников и групп</b>: команды, возрастные группы, история оплат.</span>
                        </li>
                        <li class="d-flex align-items-start mb-2">
                            <span class="me-2">✅</span>
                            <span><b>Расписание тренировок</b>: тренеры, поля/залы, время, изменения.</span>
                        </li>
                        <li class="d-flex align-items-start mb-2">
                            <span class="me-2">✅</span>
                            <span><b>Онлайн-оплата</b> и контроль задолженностей: меньше ручных напоминаний.</span>
                        </li>
                        <li class="d-flex align-items-start">
                            <span class="me-2">✅</span>
                            <span><b>Договоры с родителями</b> и отчетность по оплатам — в пару кликов.</span>
                        </li>
                    </ul>

                    <div class="d-flex flex-wrap gap-2">
                        <a href="#registration-form" class="btn btn-success btn-lg"
                           data-bs-toggle="modal" data-bs-target="#createOrder">
                            Попробовать бесплатно
                        </a>

                        <a href="{{ url('/') }}#features" class="btn btn-outline-secondary btn-lg">
                            Посмотреть функционал
                        </a>
                    </div>

                    <p class="text-muted small mt-3 mb-0">
                        Без абонентской платы: оплата сервиса — только комиссия с успешных платежей.
                    </p>
                </div>

                <div class="col-lg-6 text-center text-lg-end">
                    {{-- Место под графику (положи файл в public/img/landing/seo/football/hero.png) --}}
                    <img
                            src="{{ asset('img/landing/seo/football/hero.png') }}"
                            alt="CRM для футбольной секции kidscrm.online — экран расписания и оплат"
                            class="img-fluid rounded shadow-sm"
                            style="max-height: 420px; object-fit: contain;"
                    >
                    <div class="text-muted small mt-2">
                        {{-- Подпись к картинке (опционально) --}}
                        Скрин: расписание тренировок, оплата и долги
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- БЛОК ДЛЯ КОГО (football ICP) --}}
    <section class="bg-light py-5" id="for-whom">
        <div class="container">
            <h2 class="text-center fw-bold mb-4">Кому подходит CRM для футбольной секции</h2>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-2">Футбольные секции</h3>
                            <p class="text-muted mb-0">
                                Когда много групп и регулярные оплаты, легко “потерять” долги и запутаться в расписании.
                                CRM помогает держать порядок в учете и финансах.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-2">Футбольные школы и академии</h3>
                            <p class="text-muted mb-0">
                                Подходит для системной работы: перенос базы учеников, единые правила оплаты,
                                отчеты по направлениям и группам.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-2">Сетевые проекты</h3>
                            <p class="text-muted mb-0">
                                Если несколько филиалов/площадок — важно унифицировать учет и снять зависимость от
                                “табличек конкретного администратора”.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="{{ url('/') }}#pricing" class="btn btn-outline-primary">
                    Посмотреть стоимость и условия
                </a>
            </div>
        </div>
    </section>

    {{-- БОЛИ / ЗАДАЧИ ФУТБОЛЬНОЙ СЕКЦИИ --}}
    <section class="py-5" id="problems">
        <div class="container">
            <h2 class="text-center fw-bold mb-4">Типовые проблемы футбольных секций, которые решает CRM</h2>

            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-3">
                            <div class="fw-bold">1) Долги и “забытые оплаты”</div>
                            <div class="text-muted">Кто оплатил? Кто должен? За какой месяц? В CRM это прозрачно.</div>
                        </li>
                        <li class="mb-3">
                            <div class="fw-bold">2) Расписание постоянно меняется</div>
                            <div class="text-muted">Переносы тренировок, разные поля, тренеры, возрастные группы.</div>
                        </li>
                        <li class="mb-3">
                            <div class="fw-bold">3) Администратор тратит время на ручную рутину</div>
                            <div class="text-muted">Таблицы, переписки, напоминания, отчеты — все это можно автоматизировать.</div>
                        </li>
                        <li class="mb-3">
                            <div class="fw-bold">4) Нет единого места для данных</div>
                            <div class="text-muted">Контакты родителей, договоры, статусы оплат, группы и расписания разнесены по чатам и файлам.</div>
                        </li>
                        <li>
                            <div class="fw-bold">5) Сложно быстро собрать отчетность</div>
                            <div class="text-muted">Доход, задолженность, динамика оплат — должны считаться автоматически.</div>
                        </li>
                    </ul>
                </div>

                <div class="col-lg-6 text-center">
                    {{-- Место под инфографику “до/после” (public/img/landing/seo/football/problems.png) --}}
                    <img
                            src="{{ asset('img/landing/seo/football/problems.png') }}"
                            alt="Проблемы футбольной секции без CRM и после внедрения kidscrm.online"
                            class="img-fluid rounded shadow-sm"
                            style="max-height: 380px; object-fit: contain;"
                    >
                </div>
            </div>
        </div>
    </section>

    {{-- РЕШЕНИЕ: ФУНКЦИИ ПОД ФУТБОЛ --}}
    <section class="bg-light py-5" id="football-features">
        <div class="container">
            <h2 class="text-center fw-bold mb-4">Функции kidscrm.online для футбольной секции</h2>
            <p class="text-center text-muted mb-5">
                Это не “просто CRM”. Это практичный набор инструментов под реальную рутину футбольной школы:
                учет, расписание, оплаты, долги, договоры, отчеты.
            </p>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold">Учет учеников и команд</h3>
                            <p class="text-muted mb-0">
                                Разделяйте по возрастным группам, фиксируйте статусы и историю оплат.
                                Быстро находите нужного ученика по базе.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold">Расписание тренировок</h3>
                            <p class="text-muted mb-0">
                                Настраивайте график занятий с учетом тренеров и площадок.
                                Удобно для регулярных тренировок и изменений по сезону.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold">Онлайн-оплата и учет платежей</h3>
                            <p class="text-muted mb-0">
                                Родители оплачивают занятия онлайн, а система автоматически фиксирует оплату и
                                помогает собрать отчетность без ручной сверки.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold">Контроль задолженностей</h3>
                            <p class="text-muted mb-0">
                                Видно, кто не оплатил и за какой период. Меньше конфликтов и “непонятных ситуаций”
                                с родителями — больше прозрачности.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold">Договоры с родителями</h3>
                            <p class="text-muted mb-0">
                                Подписание договоров в одном сервисе. Удобно, когда много учеников и
                                нужно наводить порядок в документах.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold">Автоматические отчеты</h3>
                            <p class="text-muted mb-0">
                                Доходы, долги, платежи — сводки формируются автоматически.
                                Владелец видит цифры, администратор экономит часы.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5">
                <a href="{{ url('/') }}#how-it-works" class="btn btn-outline-secondary btn-lg">
                    Как это работает (по шагам)
                </a>
            </div>
        </div>
    </section>

    {{-- ВНЕДРЕНИЕ (под SEO и продажи) --}}
    <section class="py-5" id="implementation">
        <div class="container">
            <h2 class="text-center fw-bold mb-4">Внедрение CRM в футбольной секции — без боли</h2>

            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <div class="border rounded p-4">
                        <div class="d-flex align-items-start mb-3">
                            <div class="me-3 fs-4">1️⃣</div>
                            <div>
                                <div class="fw-bold">Регистрируетесь и оставляете заявку</div>
                                <div class="text-muted">Дальше мы связываемся и уточняем вашу структуру: группы, тренеры, формат оплат.</div>
                            </div>
                        </div>

                        <div class="d-flex align-items-start mb-3">
                            <div class="me-3 fs-4">2️⃣</div>
                            <div>
                                <div class="fw-bold">Бесплатно переносим базу “под ключ”</div>
                                <div class="text-muted">Переносим учеников, группы и расписание — даже если сейчас это в тетради или таблицах.</div>
                            </div>
                        </div>

                        <div class="d-flex align-items-start mb-3">
                            <div class="me-3 fs-4">3️⃣</div>
                            <div>
                                <div class="fw-bold">Настраиваем оплату и правила учета</div>
                                <div class="text-muted">Вы задаете цены по группам/ученикам, получаете прозрачный контроль оплат и долгов.</div>
                            </div>
                        </div>

                        <div class="d-flex align-items-start">
                            <div class="me-3 fs-4">4️⃣</div>
                            <div>
                                <div class="fw-bold">Работаете в системе с поддержкой специалиста</div>
                                <div class="text-muted">Поможем адаптировать процессы секции под возможности платформы.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 text-muted small">
                        Важно: вы не тратите недели на внедрение. Цель — быстро перевести секцию на нормальный учет.
                    </div>
                </div>

                <div class="col-lg-6 text-center">
                    {{-- Место под графику “шаги внедрения” (public/img/landing/seo/football/implementation.png) --}}
                    <img
                            src="{{ asset('img/landing/seo/football/implementation.png') }}"
                            alt="Этапы внедрения CRM в футбольной школе"
                            class="img-fluid rounded shadow-sm"
                            style="max-height: 380px; object-fit: contain;"
                    >
                </div>
            </div>
        </div>
    </section>

    {{-- ЦЕНООБРАЗОВАНИЕ (микро-блок) --}}
    <section class="bg-light py-5" id="pricing-short">
        <div class="container">
            <h2 class="text-center fw-bold mb-4">Стоимость CRM для футбольной школы</h2>

            <div class="row g-4 justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="display-6 fw-bold mb-2"><span class="alert-color">0 ₽</span></div>
                            <div class="fw-bold mb-2">Абонентская плата</div>
                            <p class="text-muted mb-0">
                                Полный доступ к функционалу без ежемесячных платежей.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="display-6 fw-bold mb-2"><span class="alert-color">0 ₽</span></div>
                            <div class="fw-bold mb-2">Перенос данных</div>
                            <p class="text-muted mb-0">
                                Бесплатно переносим учеников, группы и расписание “под ключ”.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="display-6 fw-bold mb-2"><span class="alert-color">1,5%</span></div>
                            <div class="fw-bold mb-2">Комиссия с оплат</div>
                            <p class="text-muted mb-0">
                                Только с успешных онлайн-платежей родителей через эквайринг.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="{{ url('/') }}#pricing" class="btn btn-primary btn-lg">
                    Открыть полный блок стоимости
                </a>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-5" id="cta-football">
        <div class="container">
            <div class="p-4 p-md-5 rounded bg-call-to-action text-center">
                <h2 class="fw-bold mb-3">Хотите навести порядок в оплатах и расписании футбольной секции?</h2>
                <p class="fs-5 mb-4">
                    Попробуйте <b>kidscrm.online</b> бесплатно: мы перенесем вашу базу и поможем запустить систему.
                </p>

                <a href="#registration-form" class="btn btn-success btn-lg"
                   data-bs-toggle="modal" data-bs-target="#createOrder">
                    Попробовать бесплатно
                </a>

                <div class="text-muted small mt-3">
                    Или посмотрите: <a href="{{ url('/') }}#faq" class="text-decoration-none">FAQ</a> ·
                    <a href="{{ url('/') }}#contacts" class="text-decoration-none">контакты</a>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ (футбол-специфичный) --}}
    <section class="py-5" id="faq-football">
        <div class="container">
            <h2 class="text-center fw-bold mb-4">FAQ: CRM для футбольной секции</h2>

            @php
                $faq = [
                    [
                        'q' => 'Подойдет ли CRM небольшой футбольной секции на 30–60 детей?',
                        'a' => 'Да. kidscrm.online полезен и небольшим секциям: вы получаете прозрачный учет оплат и долгов, единое расписание и базу учеников без таблиц.'
                    ],
                    [
                        'q' => 'Можно ли перенести учеников, если сейчас учет в тетради или Excel?',
                        'a' => 'Да. Мы бесплатно переносим базу “под ключ”: учеников, группы и расписание. Вам не нужно вручную все заносить в систему.'
                    ],
                    [
                        'q' => 'Как родители оплачивают занятия?',
                        'a' => 'Родители оплачивают онлайн через встроенный эквайринг. Платежи автоматически фиксируются в системе, а вы видите статус оплат и задолженностей.'
                    ],
                    [
                        'q' => 'Есть ли абонентская плата?',
                        'a' => 'Нет. Абонентской платы нет — оплата сервиса взимается только как комиссия с успешных онлайн-платежей.'
                    ],
                    [
                        'q' => 'Нужны ли тренерам отдельные сложные приложения?',
                        'a' => 'Нет. Интерфейс адаптирован под смартфоны и планшеты, можно работать из браузера. Фокус — сделать администрирование простым.'
                    ],
                ];
            @endphp

            <div class="accordion" id="faqFootballAccordion">
                @foreach($faq as $item)
                    <div class="accordion-item mb-3 border-0 shadow-sm">
                        <h3 class="accordion-header" id="headingFootball{{ $loop->index }}">
                            <button
                                    class="accordion-button collapsed bg-white text-dark"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#collapseFootball{{ $loop->index }}"
                                    aria-expanded="false"
                                    aria-controls="collapseFootball{{ $loop->index }}"
                            >
                                {{ $item['q'] }}
                            </button>
                        </h3>
                        <div
                                id="collapseFootball{{ $loop->index }}"
                                class="accordion-collapse collapse"
                                aria-labelledby="headingFootball{{ $loop->index }}"
                                data-bs-parent="#faqFootballAccordion"
                        >
                            <div class="accordion-body text-muted">
                                {{ $item['a'] }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- JSON-LD разметка FAQPage (для SEO) --}}
        <script type="application/ld+json">
            {
              "@context": "https://schema.org",
              "@type": "FAQPage",
              "mainEntity": [
                @foreach($faq as $item)
                {
                  "@type": "Question",
                  "name": @json($item['q']),
                  "acceptedAnswer": {
                    "@type": "Answer",
                    "text": @json($item['a'])
                }
              }@if(!$loop->last),@endif
            @endforeach
            ]
          }
</script>
    </section>

    {{-- Внутренние ссылки на другие “вертикали” (пока заглушки) --}}
    <section class="bg-light py-5" id="more-verticals">
        <div class="container">
            <h2 class="text-center fw-bold mb-4">CRM для других направлений</h2>
            <p class="text-center text-muted mb-4">
                Если у вас не только футбол — мы постепенно делаем отдельные страницы под разные секции.
            </p>

            <div class="row g-3 justify-content-center">
                <div class="col-md-4 col-lg-3">
                    <a href="{{ url('/crm-dlya-tancevalnoy-studii') }}" class="btn btn-outline-secondary w-100">
                        CRM для танцевальной студии
                    </a>
                </div>
                <div class="col-md-4 col-lg-3">
                    <a href="{{ url('/crm-dlya-shahmatnogo-kluba') }}" class="btn btn-outline-secondary w-100">
                        CRM для шахматного клуба
                    </a>
                </div>
                <div class="col-md-4 col-lg-3">
                    <a href="{{ url('/crm-dlya-edinoborstv') }}" class="btn btn-outline-secondary w-100">
                        CRM для единоборств
                    </a>
                </div>
            </div>

            <div class="text-muted small text-center mt-3">
                (Если страниц пока нет — оставь ссылки, но сделай 404/редирект позже или временно скрой блок.)
            </div>
        </div>
    </section>
 
    {{-- Контакты / Доверие — ведем на главную, чтобы не дублировать --}}
    <section class="py-4 border-top">
        <div class="container text-center">
            <div class="text-muted">
                Хотите задать вопрос? Перейдите в
                <a href="{{ url('/') }}#contacts" class="text-decoration-none">контакты</a>
                или посмотрите
                <a href="{{ url('/') }}#faq" class="text-decoration-none">общий FAQ</a>.
            </div>
        </div>
    </section>

    {{-- Модалка заявки (как на главной) --}}
    @include('includes.modal.order')

@endsection
