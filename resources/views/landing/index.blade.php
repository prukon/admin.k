@extends('layouts.landingPage')
@section('title', 'кружок.online — Управление спортом онлайн')
@section('content')

    <!-- Hero -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row align-items-center">
                <h1 class="display-5 fw-bold text-center">CRM для детских секций и кружков — меньше рутины, больше
                    времени на развитие.</h1>
                <h2 class="text-center"><b class="alert-color">Экономьте до 30% времени</b> за счет автоматизации.</h2>

                <div class="col-md-6 mb-4 mb-md-0">

                    <ul class="list-unstyled lead mt-4 mb-4">
                        <li class="d-flex align-items-center mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Иконка договора"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Подписание договоров с родителями.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Иконка администрирования"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Администрирование занятий, групп, расписаний.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Иконка отчётов"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Учёт оплат и автоматические отчёты.</span>
                        </li>
                        <li class="d-flex align-items-center">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Иконка контроля долгов"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Контроль задолженностей. Всё это в одном сервисе.</span>
                        </li>
                    </ul>

                {{--<a href="#registration" class="btn btn-success btn-lg">Попробовать бесплатно</a>--}}
                <!-- CTA -->
                    <div class="text-center mt-5">
                        <a href="#registration-form" class="btn btn-success btn-lg"
                           data-bs-toggle="modal" data-bs-target="#createOrder">Попробовать бесплатно</a>
                    </div>


                </div>
                <div class="col-md-6 text-end">
                    <img src="{{ asset('img/landing/football.png') }}"
                         alt="main"
                         class="img-fluid rounded">
                </div>
            </div>
        </div>
    </section>

    <!-- Для кого подходит -->
    <section id="audience" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Кому подходит кружок.online</h2>

            @php
                $leftAudience = [
                  [
                    'icon' => 'img/landing/icons/unit/soccer.png',
                    'title' => 'Футбольные секции',
                    'desc'  => 'Удобное администрирование командных тренировок, оплат и расписаний.'
                  ],
                  [
                    'icon' => 'img/landing/icons/unit/dancer.png',
                    'title' => 'Танцевальные студии',
                    'desc'  => 'Гибкое расписание, учёт посещаемости и автоматические напоминания.'
                  ],
                  [
                    'icon' => 'img/landing/icons/unit/martial-arts.png',
                    'title' => 'Боевые искусства',
                    'desc'  => 'Управление группами, оплатами и отчётами для секций единоборств.'
                  ],
                ];

                $rightAudience = [
                  [
                    'icon' => 'img/landing/icons/unit/chess.png',
                    'title' => 'Шахматные кружки',
                    'desc'  => 'Учёт занятий, турнирные таблицы и история прогресса участников.'
                  ],
                  [
                    'icon' => 'img/landing/icons/unit/music.png',
                    'title' => 'Музыкальные школы',
                    'desc'  => 'Удобное ведение уроков, расписание преподавателей и приём оплат.'
                  ],
                  [
                    'icon' => 'img/landing/icons/unit/talk.png',
                    'title' => 'Школы иностранных языков',
                    'desc'  => 'Автоматизация курсов, оплат и отчётов для детских языковых программ.'
                  ],
                ];
            @endphp

            <div class="row align-items-center gy-4">
                {{-- Колонка слева: три пункта --}}
                <div class="col-md-5">
                    @foreach($leftAudience as $item)
                        <div class="d-flex align-items-start mb-4">
                            <img src="{{ asset($item['icon']) }}"
                                 alt="{{ $item['title'] }}"
                                 class="me-3"
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
                    <img src="{{ asset('img/landing/iphone.png') }}"
                         alt="Скриншот сервиса"
                         class="img-fluid rounded mx-auto d-block">
                </div>

                {{-- Колонка справа: три пункта --}}
                <div class="col-md-5">
                    @foreach($rightAudience as $item)
                        <div class="d-flex align-items-start mb-4">
                            <img src="{{ asset($item['icon']) }}"
                                 alt="{{ $item['title'] }}"
                                 class="me-3"
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
    </section>

    <!-- Как это работает -->
    <section id="how-it-works" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Как это работает</h2>
            <p class="text-center text-muted mb-5 fs-4">
                Автоматизируйте управление спортивными кружками от регистрации до оплаты и отчётности.
            </p>

            @php
                $steps = [
                    ['icon' => 'img/landing/icons/Register.png',          'title' => 'Моментальная регистрация',         'desc' => 'Подключите спортивную школу или кружок в пару кликов — без лишних форм и долгих ожиданий.'],
                    ['icon' => 'img/landing/icons/Import.png',            'title' => 'Импорт данных “под ключ”',       'desc' => 'Мы бесплатно перенесём группы, учеников и расписание в систему, чтобы вы сразу приступили к работе.'],
                    ['icon' => 'img/landing/icons/Price.png',             'title' => 'Гибкая настройка цен за занятия', 'desc' => 'Задавайте индивидуальные цены для каждого ученика или группы одним действием — без лишних шагов.'],
                    ['icon' => 'img/landing/icons/Credit-card.png',       'title' => 'Онлайн-платежи за занятия',      'desc' => 'Родители рассчитываются через встроенный эквайринг, а вы мгновенно получаете средства на счёт.'],
                    ['icon' => 'img/landing/icons/Report.png',            'title' => 'Единая панель отчётности',       'desc' => 'Контролируйте все платежи, просрочки и ключевые финансовые показатели в реальном времени.'],
                    ['icon' => 'img/landing/icons/reminder.png',          'title' => 'Автоматические напоминания и пени','desc' => 'Система самостоятельно оповестит должников и при необходимости автоматически рассчитает штрафы.'],
                    ['icon' => 'img/landing/icons/saving-time.png',       'title' => 'Максимальная экономия времени',  'desc' => 'Забудьте о ручном учёте и обзвонах — автоматизация платежей и отчётов позволит сосредоточиться на развитии.'],
                ];
                $half = ceil(count($steps) / 2);
                $leftSteps  = array_slice($steps, 0, $half);
                $rightSteps = array_slice($steps, $half);
            @endphp

            <div class="row g-4">
                {{-- Левая колонка --}}
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
                {{-- Правая колонка --}}
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

    <!-- Ключевой функционал -->
    <section id="features" class="bg-light py-5">
        <div class="container">
            <h2 class="text-center mb-5">Ключевой функционал</h2>
            <div class="row align-items-center">

                {{-- Первый столбец --}}
                <div class="col-md-4">
                    @foreach([
                        ['icon' => 'img/landing/icons/functional/user-group.png', 'text' => 'Учёт пользователей и групп', 'desc' => 'Добавляйте и распределяйте учеников по группам, ведите историю посещений.'],
                        ['icon' => 'img/landing/icons/functional/schedule-management.png', 'text' => 'Управление расписанием', 'desc' => 'Гибко настраивайте расписания с учётом тренеров, залов и групп.'],
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

                {{-- Центральное изображение --}}
                <div class="col-md-4 text-center mb-4 mb-md-0">
                    <img src="{{ asset('img/landing/dashboard.PNG') }}"
                         alt="Функции"
                         class="img-fluid rounded mx-auto d-block">
                </div>

                {{-- Третий столбец --}}
                <div class="col-md-4">
                    @foreach([
                        ['icon' => 'img/landing/icons/functional/payment-acceptance.png', 'text' => 'Приём и учёт оплат', 'desc' => 'Интеграция с платёжными системами и автоматический учёт транзакций.'],
                        ['icon' => 'img/landing/icons/functional/automatic-reporting.png', 'text' => 'Автоматическая отчётность', 'desc' => 'Сводки по доходам, задолженностям и KPI — автоматически.'],
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
        </div>
    </section>

    <!-- Hero 2 -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row align-items-center">

                <div class="col-md-6 text-end mob-hide">
                    <img src="{{ asset('img/landing/dance.png') }}" alt="main" class="img-fluid rounded">
                </div>

                <div class="col-md-6 mb-4 mb-md-0">
                    {{--<h2 class="display-5 fw-bold" id ='advantages'>Наши уникальные преимущества</h2>--}}
                    <h2 class="text-center mb-5" id='advantages'>Наши уникальные преимущества</h2>


                    <div class="container">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card h-100 p-4 shadow-sm border-0 rounded-3">
                                    <h5 class="fw-bold mb-3">Мы сами перенесем данные ваших учеников</h5>
                                    <p class="text-muted fs-6">
                                        Мы бесплатно перенесём всю вашу базу данных учеников, групп, расписаний, если
                                        даже эти данные записаны в обычной тетрадке.
                                        Вы ничего не вводите вручную — мы сделаем за вас полную настройку.
                                    </p>
                                    <div class="mt-auto">
                                        <i class="bi bi-box-arrow-in-right display-5 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 p-4 shadow-sm border-0 rounded-3">
                                    <h5 class="fw-bold mb-3">Личный технических специалист</h5>
                                    <p class="text-muted fs-6">
                                        При старте вы получаете персонального тех. специалиста, готового поддержать вас
                                        в режиме реального времени:
                                        голосовые консультации, ответы на технические и организационные вопросы,
                                        помощь в адаптации вашего бизнес-процесса под возможности платформы.
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

    <!-- Стоимость -->
    <section id="pricing" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Стоимость</h2>
            <p class="text-center fs-5 text-muted mb-5">
                <span class="alert-color">Мы не берём деньги за использование сервиса</span> —
                оплата взимается только с успешных платежей ваших клиентов.
            </p>

            <div class="row g-4 justify-content-center">
                {{-- Абонентская плата --}}
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

                {{-- Миграция данных --}}
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 p-4 text-center">
                        <img src="{{ asset('img/landing/icons/price/transferring-data.png') }}"
                             alt="Иконка миграции данных"
                             class="mx-auto mb-3"
                             style="width:48px; height:48px; object-fit:contain;">
                        <h5 class="fw-bold"><span class="alert-color">0 ₽</span> за перенос данных учеников в нашу
                            систему</h5>
                        <p class="text-muted fs-6">
                            Мы бесплатно перенесём вашу базу учеников, групп и расписаний «под ключ»,
                            чтобы вы могли сразу приступить к работе без ручного ввода.
                        </p>
                    </div>
                </div>

                {{-- Техническая поддержка --}}
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 p-4 text-center">
                        <img src="{{ asset('img/landing/icons/price/technical-support.png') }}"
                             alt="Иконка технической поддержки"
                             class="mx-auto mb-3"
                             style="width:48px; height:48px; object-fit:contain;">
                        <h5 class="fw-bold"><span class="alert-color">0 ₽</span> техническая поддержка</h5>
                        <p class="text-muted fs-6">
                            Оперативная помощь по чату и телефону — персональный специалист решит вашу задачу
                            сразу, без «переадресаций».
                        </p>
                    </div>
                </div>

                {{-- Комиссия --}}
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 p-4 text-center">
                        <img src="{{ asset('img/landing/icons/price/commission.png') }}"
                             alt="Иконка комиссии"
                             class="mx-auto mb-3"
                             style="width:48px; height:48px; object-fit:contain;">
                        <h5 class="fw-bold"><span class="alert-color"> 1,5% комиссия</span> только с транзакций</h5>
                        <p class="text-muted fs-6">
                            Оплата сервиса — небольшой процент от каждой успешной оплаты занятий через
                            онлайн-эквайринг. Никаких скрытых сборов и дополнительных платежей.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">FAQ</h2>
            <div class="accordion" id="faqAccordion">
                @foreach([
                    'Можно ли использовать сервис бесплатно?' => 'Да, вы можете пользоваться сервисом бесплатно. Мы берём комиссию только с успешных онлайн-платежей ваших клиентов.',
                    'Помогаете ли вы с добавлением базы учеников?' => 'Да, мы осуществляем бесплатную миграцию всех данных «под ключ», включая учеников, группы и расписания.',
                    'Есть ли мобильное приложение?' => 'Пока мобильного приложения нет, однако интерфейс полностью адаптирован под смартфоны и планшеты.'
                ] as $question => $answer)
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
        </div>
    </section>

    <!-- Call to Action -->
    <section id="cta" class="py-5  bg-call-to-action ">
        <div class="container text-center">
            <h2 class="display-6 fw-bold mb-3">Готовы вывести ваши секции на новый уровень?</h2>
            <p class="fs-5 mb-4">
                Попробуйте <span class="fw-bold">кружок.online</span> бесплатно и получите полную поддержку
                персонального куратора при запуске.
            </p>
            <a href="#registration-form" class="btn btn-success btn-lg me-3" data-bs-toggle="modal" data-bs-target="#createOrder">Попробовать бесплатно</a>
            {{--            <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#demoModal">--}}
            {{--                Записаться на демо--}}
            {{--            </button>--}}
        </div>
    </section>

    <!-- Контакты -->
    <section id="contacts" class="py-5 bg-light">
        <div class="container">
            <div class="row gy-4 align-items-start">

                {{-- Реквизиты --}}
                <div class="col-md-6">
                    <h3 class="fw-bold mb-4">Реквизиты</h3>
                    <ul class="list-unstyled mb-4">
                        <li class="mb-2"><strong>ИП:</strong> Устьян Евгений Артурович</li>
                        <li class="mb-2"><strong>ИНН:</strong> 110211351590</li>
                        <li><strong>ЕГРНИП:</strong> 324784700017432</li>
                    </ul>

                    <h5 class="fw-bold mb-3">Соцсети и Email</h5>
                    <ul class="list-unstyled d-flex flex-wrap">
                        <li class="me-4 mb-2">
                            <a href="mailto:kidslinkru@yandex.ru"
                               class="d-flex align-items-center text-dark text-decoration-none">
                                <img src="{{ asset('img/landing/icons/social/gmail.png') }}"
                                     alt="Email"
                                     class="me-2"
                                     style="width:24px; height:24px; object-fit:contain;">
                                <span>kruzhok.online@yandex.ru</span>
                            </a>
                        </li>
                        <li class="me-4 mb-2">
                            <a href="https://t.me/prukon" target="_blank"
                               class="d-flex align-items-center text-dark text-decoration-none">
                                <img src="{{ asset('img/landing/icons/social/telegram.png') }}"
                                     alt="Telegram"
                                     class="me-2"
                                     style="width:24px; height:24px; object-fit:contain;">
                                <span>@prukon</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="https://vk.com/prukon" target="_blank"
                               class="d-flex align-items-center text-dark text-decoration-none">
                                <img src="{{ asset('img/landing/icons/social/vk.png') }}"
                                     alt="ВКонтакте"
                                     class="me-2"
                                     style="width:24px; height:24px; object-fit:contain;">
                                <span>@prukon</span>
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- Призыв к действию --}}
                <div class="col-md-6">
                    <h3 class="fw-bold mb-4">Свяжитесь с нами</h3>
                    <p class="text-muted mb-4">
                        Оставьте сообщение – мы оперативно ответим и поможем запустить ваш спорт-кружок без лишних
                        забот.
                    </p>
                    <div class="d-flex flex-wrap">
                        <a href="mailto:kruzhok.online@yandex.ru"
                           class="btn btn-primary me-3 mb-2 d-flex align-items-center">
                            <img src="{{ asset('img/landing/icons/social/gmail.png') }}"
                                 alt="Email"
                                 class="me-2"
                                 style="width:20px; height:20px; object-fit:contain;">
                            Написать на Email
                        </a>
                        <a href="https://t.me/prukon" target="_blank"
                           class="btn btn-outline-primary me-3 mb-2 d-flex align-items-center">
                            <img src="{{ asset('img/landing/icons/social/telegram.png') }}"
                                 alt="Telegram"
                                 class="me-2"
                                 style="width:20px; height:20px; object-fit:contain;">
                            Telegram
                        </a>
                        <a href="https://vk.com/prukon" target="_blank"
                           class="btn btn-outline-primary mb-2 d-flex align-items-center">
                            <img src="{{ asset('img/landing/icons/social/vk.png') }}"
                                 alt="ВКонтакте"
                                 class="me-2"
                                 style="width:20px; height:20px; object-fit:contain;">
                            ВКонтакте
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Кусты -->
    <section id="bushes" class="bg-light py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 text-center">
                    <img src="{{ asset('img/landing/bushes.png') }}"
                         alt="bushes"
                         class="img-fluid rounded mx-auto d-block">
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4">
        <div class="container">
            <p class="mb-1">Все права защищены. 2024 - 2025 кружок.online &copy;</p>
            <div>
                <a href="oferta" class="text-white text-decoration-none mx-2">Оферта</a>
                <a href="{{ route('privacy.policy') }}"  class="text-white text-decoration-none mx-2">Политика конфиденциальности</a>
            </div>
        </div>
    </footer>




 <!-- Модальное окно заявки -->

    @include('includes.modal.order')

@endsection


