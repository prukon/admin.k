@extends('layouts.landingPage')

@section('title', 'CRM для детского развивающего центра — онлайн-оплаты без абонплаты | kidscrm.online')

@section('meta_description',
    'CRM для детского развивающего центра и центра раннего развития: онлайн-оплаты, учёт абонементов, долгов, учеников, групп, расписания и договоров. Без абонентской платы, без своей онлайн-кассы и без отдельного эквайринга.')

@section('content')

    <div class="landing-page development-center-landing">

        <!-- HERO -->
        <section class="bg-light py-4 py-md-5">
            <div class="container">
                <div class="row align-items-center">

                    <div class="col-12 mb-3 mb-md-4">
                        <div class="text-center mb-2">
                            <span class="section-label">CRM для развивающих центров</span>
                        </div>

                        <h1 class="fw-bold text-center mb-3">
                            <span class="mark-creative">CRM для детского развивающего центра с онлайн-оплатами без абонплаты</span>
                        </h1>

                        <p class="text-center mb-2" style="font-size: 1.08rem;">
                            Помогаем развивающим центрам собирать оплаты вовремя, видеть долги по абонементам
                            и держать детей, группы и платежи в одной системе.
                        </p>

                        <p class="fw-semibold text-center mt-3 mb-0" style="font-size: 1.02rem;">
                            <span class="my-alert-color">0 ₽ в месяц</span> — только комиссия с успешных платежей
                        </p>
                    </div>

                    <!-- Левый столбец -->
                    <div class="col-md-6 mb-4 mb-md-0 d-flex flex-column">

                        <ul class="list-unstyled mt-3 mb-3 mb-md-4">

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                     class="me-2 mt-1"
                                     style="width:20px; height:20px; object-fit:contain;"
                                     alt="Онлайн-оплаты для детского развивающего центра">
                                <span>Онлайн-оплаты за абонементы, занятия и курсы</span>
                            </li>

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                     class="me-2 mt-1"
                                     style="width:20px; height:20px; object-fit:contain;"
                                     alt="Учёт долгов">
                                <span>Учёт долгов и просрочек по каждому ребёнку</span>
                            </li>

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                     class="me-2 mt-1"
                                     style="width:20px; height:20px; object-fit:contain;"
                                     alt="Без онлайн-кассы">
                                <span>Без своей онлайн-кассы и отдельного эквайринга</span>
                            </li>

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                     class="me-2 mt-1"
                                     style="width:20px; height:20px; object-fit:contain;"
                                     alt="Онлайн-договоры">
                                <span>Онлайн-договоры с родителями</span>
                            </li>

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                     class="me-2 mt-1"
                                     style="width:20px; height:20px; object-fit:contain;"
                                     alt="Перенос данных">
                                <span>Бесплатный перенос базы детей и расписания</span>
                            </li>

                        </ul>

                        <div class="small text-muted mb-3 mb-md-4">
                            Подходит для детских развивающих центров, центров раннего развития, подготовки к школе и детских клубов.
                        </div>

                        <div class="d-flex flex-column flex-md-row align-items-center gap-3">
                            <a href="#registration-form"
                               class="btn btn-success btn-lg px-4 btn-order btn-order-transform"
                               style="min-width:260px;"
                               data-bs-toggle="modal"
                               data-bs-target="#createOrder">
                                Записаться на демо
                            </a>

                            <div class="small text-muted text-center text-md-start">
                                Покажем за 15 минут, как это будет работать именно в вашем центре
                            </div>
                        </div>

                    </div>

                    <!-- Правый столбец -->
                    <div class="col-md-6 d-flex justify-content-center align-items-center mt-4 mt-md-0">
                        <img src="{{ asset('img/landing/development-center.png') }}"
                             alt="CRM для детского развивающего центра"
                             class="img-fluid"
                             style="max-height:360px; width:auto;">
                    </div>

                </div>
            </div>
        </section>

        <!-- ПРОБЛЕМА -->
        <section class="py-5 bg-light">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">

                        <div class="text-center mb-3">
                            <span class="section-label">Проблема</span>
                        </div>

                        <h2 class="text-center mb-3">
                            <span class="mark-creative">Почему в развивающем центре быстро начинается хаос с оплатами</span>
                        </h2>

                        <p class="text-center text-muted fs-5 mb-4">
                            Когда детей, групп и направлений становится больше, ручной учёт начинает мешать нормальной работе центра.
                        </p>

                        <div class="card border-0 shadow-lg rounded-4">
                            <div class="card-body py-4 px-3 px-md-4">

                                <ul class="list-unstyled mb-0">

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">Оплаты приходят несистемно</h5>
                                            <div class="text-muted">
                                                Кто-то оплатил абонемент, кто-то внёс часть суммы, кто-то обещал позже —
                                                и всё это приходится проверять вручную.
                                            </div>
                                        </div>
                                    </li>

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">Не видно полной картины по долгам</h5>
                                            <div class="text-muted">
                                                Сложно быстро понять, кто должен, за какой месяц, курс или группу.
                                            </div>
                                        </div>
                                    </li>

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">Много ручной рутины</h5>
                                            <div class="text-muted">
                                                Напоминания родителям, сверки переводов и ответы на вопросы по оплатам
                                                забирают часы каждую неделю.
                                            </div>
                                        </div>
                                    </li>

                                    <li class="d-flex mb-0">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">Приём денег “на карту” мешает росту</h5>
                                            <div class="text-muted">
                                                Такая схема неудобна, непрозрачна и плохо подходит для центра, который хочет расти системно.
                                            </div>
                                        </div>
                                    </li>

                                </ul>

                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- РЕШЕНИЕ -->
        <section id="solution" class="py-5 bg-light">
            <div class="container">

                <div class="text-center mb-2">
                    <span class="section-label">Решение</span>
                </div>

                <h2 class="text-center mb-3">
                    <span class="mark-creative">Как kidscrm.online помогает развивающему центру</span>
                </h2>

                <p class="text-center text-muted fs-5 mb-4">
                    Вы занимаетесь детьми и развитием центра. Система берёт на себя оплату, учёт долгов и порядок в базе.
                </p>

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">

                                <div class="row g-4 mb-4 text-center text-md-start">
                                    <div class="col-md-4">
                                        <div class="p-3 p-md-4 h-100 rounded-4 bg-light">
                                            <div class="text-uppercase small fw-semibold text-muted mb-1">
                                                Оплаты
                                            </div>
                                            <div class="h3 fw-bold mb-1 my-alert-color">
                                                вовремя
                                            </div>
                                            <div class="text-muted small">
                                                Родители получают ссылку на оплату, а центр видит статусы и долги.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 p-md-4 h-100 rounded-4 bg-light">
                                            <div class="text-uppercase small fw-semibold text-muted mb-1">
                                                Рутины
                                            </div>
                                            <div class="h3 fw-bold mb-1 my-alert-color">
                                                меньше
                                            </div>
                                            <div class="text-muted small">
                                                Меньше сообщений, сверок и ручной проверки переводов.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 p-md-4 h-100 rounded-4 bg-light">
                                            <div class="text-uppercase small fw-semibold text-muted mb-1">
                                                Стоимость
                                            </div>
                                            <div class="h3 fw-bold mb-1 my-alert-color">
                                                0 ₽ / мес
                                            </div>
                                            <div class="text-muted small">
                                                Нет абонплаты и платы за внедрение — только комиссия с успешных оплат.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <div class="h-100 p-3 p-md-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Онлайн-оплаты</h5>
                                            <ul class="text-muted small mb-0 ps-3">
                                                <li>оплата по ссылке;</li>
                                                <li>автофиксация платежей;</li>
                                                <li>чеки отправляются автоматически;</li>
                                                <li>не нужна своя касса.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="h-100 p-3 p-md-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Учёт центра</h5>
                                            <ul class="text-muted small mb-0 ps-3">
                                                <li>дети, группы, расписание;</li>
                                                <li>курсы, абонементы и долги;</li>
                                                <li>история оплат;</li>
                                                <li>отчёты в одном месте.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="h-100 p-3 p-md-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Спокойный запуск</h5>
                                            <ul class="text-muted small mb-0 ps-3">
                                                <li>переносим базу;</li>
                                                <li>помогаем настроить систему;</li>
                                                <li>сопровождаем на старте;</li>
                                                <li>не нужно разбираться одному.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center mt-4">
                                    <a href="#registration-form"
                                       class="btn btn-success btn-lg cta-inline"
                                       data-bs-toggle="modal"
                                       data-bs-target="#createOrder">
                                        Посмотреть демо для развивающего центра
                                    </a>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ЗАТОЧЕНО ПОД РАЗВИВАЮЩИЕ ЦЕНТРЫ -->
        <section class="py-5 bg-light">
            <div class="container">

                <div class="text-center mb-2">
                    <span class="section-label">Подходит для развивающих центров</span>
                </div>

                <h2 class="text-center mb-3">
                    <span class="mark-creative">Что особенно важно для детского развивающего центра</span>
                </h2>

                <p class="text-center text-muted fs-5 mb-5">
                    В таких центрах обычно есть группы по возрастам, абонементы, расписание, педагоги,
                    курсы, интенсивы и дополнительные оплаты. Всё это удобнее вести в одной системе.
                </p>

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">
                                <div class="row g-4">

                                    <div class="col-md-6 col-lg-3">
                                        <div class="h-100 p-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Абонементы и курсы</h5>
                                            <p class="text-muted small mb-0">
                                                Видно, кто оплатил месяц или курс, а у кого просрочка или частичный долг.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3">
                                        <div class="h-100 p-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Группы и педагоги</h5>
                                            <p class="text-muted small mb-0">
                                                Дети, педагоги, расписание и группы — в одном месте.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3">
                                        <div class="h-100 p-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Интенсивы и доплаты</h5>
                                            <p class="text-muted small mb-0">
                                                Можно учитывать материалы, интенсивы, занятия и другие платежи отдельно.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3">
                                        <div class="h-100 p-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Родители и договоры</h5>
                                            <p class="text-muted small mb-0">
                                                Онлайн-договоры и понятная история взаимодействия по оплатам.
                                            </p>
                                        </div>
                                    </div>

                                </div>

                                <div class="text-center mt-4">
                                    <a href="#registration-form"
                                       class="btn btn-outline-success cta-inline"
                                       data-bs-toggle="modal"
                                       data-bs-target="#createOrder">
                                        Обсудить задачи моего центра
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- КАК ЭТО РАБОТАЕТ -->
        <section id="how-it-works" class="py-5 bg-light">
            <div class="container">

                <div class="text-center mb-2">
                    <span class="section-label">Как это работает</span>
                </div>

                <h2 class="text-center mb-4">
                    <span class="mark-creative">Подключаем центр под ключ</span>
                </h2>

                <p class="text-center text-muted mb-5 fs-5">
                    Вы оставляете заявку. Мы помогаем с запуском, переносом базы и настройкой оплат.
                </p>

                @php
                    $steps = [
                        [
                            'icon' => 'img/landing/icons/Register.png',
                            'title' => 'Заявка',
                            'desc' => 'Вы показываете, как сейчас устроены оплаты и учёт в вашем центре.',
                        ],
                        [
                            'icon' => 'img/landing/icons/Import.png',
                            'title' => 'Перенос базы',
                            'desc' => 'Переносим детей, группы и расписание в систему.',
                        ],
                        [
                            'icon' => 'img/landing/icons/Price.png',
                            'title' => 'Настройка тарифов',
                            'desc' => 'Помогаем задать курсы, абонементы, цены и нужные типы платежей.',
                        ],
                        [
                            'icon' => 'img/landing/icons/Credit-card.png',
                            'title' => 'Запуск оплат',
                            'desc' => 'Родители оплачивают онлайн, а система фиксирует статусы и чеки.',
                        ],
                        [
                            'icon' => 'img/landing/icons/reminder.png',
                            'title' => 'Контроль долгов',
                            'desc' => 'Вы видите, кто оплатил, кто должен и какая картина по центру.',
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

                                    <div class="col-md-6">
                                        @foreach ($leftSteps as $step)
                                            <div class="step-item mb-4">
                                                <div class="step-circle">
                                                    {{ $loop->iteration }}
                                                </div>
                                                <div class="step-line"></div>
                                                <div class="d-flex align-items-start">
                                                    <img src="{{ asset($step['icon']) }}"
                                                         alt="{{ $step['title'] }}"
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
                                                    <img src="{{ asset($step['icon']) }}"
                                                         alt="{{ $step['title'] }}"
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
                                    <a href="#registration-form"
                                       class="btn btn-outline-success cta-inline"
                                       data-bs-toggle="modal"
                                       data-bs-target="#createOrder">
                                        Получить демо и условия подключения
                                    </a>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ФУНКЦИОНАЛ -->
        <section id="features" class="py-5 bg-light">
            <div class="container">

                <div class="row justify-content-center text-center mb-5">
                    <div class="col-lg-9">

                        <div class="mb-2">
                            <span class="section-label">Функционал</span>
                        </div>

                        <h2 class="mb-3">
                            <span class="mark-creative">Что есть в CRM для развивающего центра</span>
                        </h2>

                        <p class="text-muted fs-5 mb-0">
                            Всё основное для оплаты, учёта детей и управления центром в одном кабинете.
                        </p>
                        <div class="section-divider"></div>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body py-4 py-md-5 px-3 px-md-4">

                                <div class="row align-items-center">

                                    <div class="col-md-4 mb-4 mb-md-0">
                                        @foreach ([
                                            [
                                                'icon' => 'img/landing/icons/functional/payment-acceptance.png',
                                                'text' => 'Приём оплат',
                                                'desc' => 'Онлайн-оплаты от родителей с автоматической фиксацией.',
                                            ],
                                            [
                                                'icon' => 'img/landing/icons/functional/automatic-reporting.png',
                                                'text' => 'Долги и отчёты',
                                                'desc' => 'Понятно, кто оплатил, кто должен и за какой период.',
                                            ],
                                        ] as $item)
                                            <div class="d-flex align-items-start mb-4 p-3 rounded-4 bg-light">
                                                <div class="me-3 flex-shrink-0">
                                                    <div class="rounded-circle bg-white shadow-sm d-flex align-items-center justify-content-center"
                                                         style="width:72px; height:72px;">
                                                        <img src="{{ asset($item['icon']) }}"
                                                             alt="{{ $item['text'] }}"
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

                                    <div class="col-md-4 text-center mb-4 mb-md-0">
                                        <div class="border rounded-4 shadow-sm p-2 bg-light">
                                            <img src="{{ asset('img/landing/dashboard.png') }}"
                                                 alt="Панель управления развивающим центром"
                                                 class="img-fluid rounded-3">
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        @foreach ([
                                            [
                                                'icon' => 'img/landing/icons/functional/user-group.png',
                                                'text' => 'Дети и группы',
                                                'desc' => 'Единая база детей, родителей, групп и педагогов.',
                                            ],
                                            [
                                                'icon' => 'img/landing/icons/functional/schedule-management.png',
                                                'text' => 'Онлайн-договоры',
                                                'desc' => 'Подписание договоров с родителями без бумажной волокиты.',
                                            ],
                                        ] as $item)
                                            <div class="d-flex align-items-start mb-4 p-3 rounded-4 bg-light">
                                                <div class="me-3 flex-shrink-0">
                                                    <div class="rounded-circle bg-white shadow-sm d-flex align-items-center justify-content-center"
                                                         style="width:72px; height:72px;">
                                                        <img src="{{ asset($item['icon']) }}"
                                                             alt="{{ $item['text'] }}"
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

                                <div class="text-center mt-3">
                                    <a href="#registration-form"
                                       class="btn btn-success cta-inline"
                                       data-bs-toggle="modal"
                                       data-bs-target="#createOrder">
                                        Запросить демо системы
                                    </a>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ПРЕИМУЩЕСТВА -->
        <section class="bg-light py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">
                                <div class="row align-items-center">

                                    <div class="col-md-4 text-end mob-hide">
                                        <img src="{{ asset('img/landing/development-center.png') }}"
                                             alt="Преимущества CRM для развивающего центра"
                                             class="img-fluid rounded">
                                    </div>

                                    <div class="col-md-8 mb-4 mb-md-0">
                                        <div class="text-center mb-2">
                                            <span class="section-label">Почему мы</span>
                                        </div>

                                        <h2 class="text-center mb-5" id="advantages">
                                            <span class="mark-creative">Почему развивающие центры выбирают kidscrm.online</span>
                                        </h2>

                                        <div class="container">
                                            <div class="row g-4">

                                                <div class="col-md-6">
                                                    <div class="card h-100 p-4 shadow-sm border-0 rounded-3">
                                                        <h5 class="fw-bold mb-3">Быстрый старт</h5>
                                                        <p class="text-muted fs-6 mb-0">
                                                            Помогаем запуститься без долгого внедрения и ручного переноса данных.
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="card h-100 p-4 shadow-sm border-0 rounded-3">
                                                        <h5 class="fw-bold mb-3">Меньше рутины</h5>
                                                        <p class="text-muted fs-6 mb-0">
                                                            Снижается нагрузка на администратора и руководителя по оплатам и сверкам.
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="col-md-12">
                                                    <div class="card h-100 p-4 shadow-sm border-0 rounded-3 mt-3">
                                                        <h5 class="fw-bold mb-3">Без лишних расходов на старте</h5>
                                                        <p class="text-muted fs-6 mb-0">
                                                            Нет абонентской платы, нет платы за внедрение, не нужно отдельно покупать онлайн-кассу и подключать эквайринг.
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

        <!-- КОМУ ПОДХОДИТ -->
        <section id="audience" class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-2">
                    <span class="section-label">Кому подходит</span>
                </div>

                <h2 class="text-center mb-3">
                    <span class="mark-creative">Для каких развивающих центров подходит CRM</span>
                </h2>

                <p class="text-center text-muted mb-5 fs-5">
                    Решение подходит центрам, где есть группы, абонементы, регулярные оплаты и работа с родителями.
                </p>

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">
                                <div class="row g-4 text-center text-md-start">

                                    <div class="col-md-6 col-lg-3">
                                        <div class="p-4 bg-light rounded-4 h-100">
                                            <h5 class="fw-bold mb-2">Центры раннего развития</h5>
                                            <p class="text-muted small mb-0">
                                                Регулярные занятия, абонементы и понятный учёт оплат.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3">
                                        <div class="p-4 bg-light rounded-4 h-100">
                                            <h5 class="fw-bold mb-2">Подготовка к школе</h5>
                                            <p class="text-muted small mb-0">
                                                Группы, педагоги, расписание и прозрачный контроль задолженностей.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3">
                                        <div class="p-4 bg-light rounded-4 h-100">
                                            <h5 class="fw-bold mb-2">Детские клубы</h5>
                                            <p class="text-muted small mb-0">
                                                Удобно вести абонементы, долги и дополнительные оплаты.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3">
                                        <div class="p-4 bg-light rounded-4 h-100">
                                            <h5 class="fw-bold mb-2">Центры с несколькими группами</h5>
                                            <p class="text-muted small mb-0">
                                                Когда таблиц уже много и нужен единый порядок в центре.
                                            </p>
                                        </div>
                                    </div>

                                </div>

                                <div class="text-center mt-4">
                                    <a href="#registration-form"
                                       class="btn btn-outline-success cta-inline btn-order btn-order-transform"
                                       data-bs-toggle="modal"
                                       data-bs-target="#createOrder">
                                        Узнать, подойдёт ли это моему центру
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

                <h2 class="text-center mb-4">FAQ для развивающих центров</h2>

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">
                                <div class="accordion" id="faqAccordion">
                                    @foreach ([
                                        'Подходит ли сервис для детского развивающего центра?' => 'Да. Сервис подходит развивающим центрам, где есть группы, абонементы, расписание, педагоги и регулярные оплаты.',
                                        'Можно ли учитывать доплаты, кроме абонемента?' => 'Да. Можно учитывать отдельные платежи: материалы, интенсивы, дополнительные занятия и другие доплаты.',
                                        'Нужно ли покупать свою онлайн-кассу?' => 'Нет. Платёжная инфраструктура и онлайн-касса уже на нашей стороне.',
                                        'Нужно ли подключать свой эквайринг?' => 'Нет. Мы уже работаем через мультирасчёты Т-Банка.',
                                        'Поможете перенести базу детей?' => 'Да. Мы бесплатно переносим текущую базу детей, групп и расписание.',
                                        'Есть ли мобильное приложение?' => 'Отдельного приложения пока нет, но интерфейс адаптирован под смартфоны и планшеты.',
                                        'Можно ли подписывать договоры онлайн?' => 'Да. В системе предусмотрено онлайн-подписание договоров с родителями.',
                                        'Сколько стоит сервис?' => 'Нет абонентской платы и платы за внедрение. Вы платите только комиссию с успешных онлайн-платежей.',
                                    ] as $question => $answer)
                                        <div class="accordion-item mb-3 border-0 shadow-sm">
                                            <h2 class="accordion-header" id="heading{{ $loop->index }}">
                                                <button
                                                    class="accordion-button collapsed bg-white text-dark d-flex justify-content-between align-items-center"
                                                    type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#collapse{{ $loop->index }}"
                                                    aria-expanded="false"
                                                    aria-controls="collapse{{ $loop->index }}">
                                                    <span class="flex-grow-1 text-start">{{ $question }}</span>
                                                    <i class="bi bi-chevron-down ms-2"></i>
                                                </button>
                                            </h2>
                                            <div id="collapse{{ $loop->index }}"
                                                 class="accordion-collapse collapse"
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

        <!-- CTA -->
        <section id="cta" class="py-5 bg-call-to-action">
            <div class="container text-center">
                <h2 class="display-6 fw-bold mb-3">
                    Хотите навести порядок в оплатах развивающего центра?
                </h2>

                <p class="fs-5 mb-3">
                    Покажем, как <span class="fw-bold">kidscrm.online</span> поможет вашему центру
                    собирать оплаты вовремя и убрать ручной хаос из учёта.
                </p>

                <p class="fs-5 fw-semibold mb-4">
                    На демо покажем, как это будет работать именно под вашу структуру групп, курсов и абонементов.
                </p>

                <a href="#registration-form"
                   class="btn btn-success btn-lg btn-order btn-order-transform"
                   data-bs-toggle="modal"
                   data-bs-target="#createOrder"
                   data-analytics="cta_bottom_demo_development_center">
                    Записаться на демо 15 минут
                </a>
            </div>
        </section> 

    </div>

@endsection