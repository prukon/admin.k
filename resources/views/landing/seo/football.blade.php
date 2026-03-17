@extends('layouts.landingPage')

@section('title', 'CRM для футбольных школ и секций — онлайн-оплаты без абонплаты | kidscrm.online')

@section('meta_description',
    'CRM для футбольных школ и секций: автоматизация оплат, долгов, абонементов, договоров и сборов за турниры. Без абонентской платы, без своей онлайн-кассы и без отдельного эквайринга.')

@section('content')

    <div class="landing-page football-landing">

        <!-- HERO -->
        <section class="bg-light py-4 py-md-5">
            <div class="container">
                <div class="row align-items-center">

                    <div class="col-12 mb-3 mb-md-4">
                        <div class="text-center mb-2">
                            <span class="section-label">CRM для футбольных школ</span>
                        </div>

                        <h1 class="fw-bold text-center mb-3">
                            <span class="mark-creative">Онлайн-оплаты и учёт для футбольных секций без абонплаты</span>
                        </h1>

                        <p class="text-center mb-2" style="font-size: 1.1rem;">
                            Помогаем футбольным школам собирать оплаты вовремя, видеть долги по каждому игроку
                            и перестать вести учёт в чатах, Excel и заметках.
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
                                     alt="Приём оплат за футбол">
                                <span>Приём онлайн-оплат за тренировки, абонементы и сборы</span>
                            </li>

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                     class="me-2 mt-1"
                                     style="width:20px; height:20px; object-fit:contain;"
                                     alt="Контроль долгов">
                                <span>Контроль долгов по каждому игроку, группе и месяцу</span>
                            </li>

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                     class="me-2 mt-1"
                                     style="width:20px; height:20px; object-fit:contain;"
                                     alt="Не нужна касса">
                                <span>Не нужна своя онлайн-касса и отдельный эквайринг</span>
                            </li>

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                     class="me-2 mt-1"
                                     style="width:20px; height:20px; object-fit:contain;"
                                     alt="Онлайн договоры">
                                <span>Онлайн-подписание договоров с родителями</span>
                            </li>

                            <li class="d-flex align-items-start mb-2">
                                <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                     class="me-2 mt-1"
                                     style="width:20px; height:20px; object-fit:contain;"
                                     alt="Перенос базы">
                                <span>Бесплатный перенос базы игроков, групп и расписания</span>
                            </li>

                        </ul>

                        <div class="small text-muted mb-3 mb-md-4">
                            Сервис вырос из действующей футбольной школы в Санкт-Петербурге и уже более 6 лет
                            используется в реальной ежедневной работе.
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
                                Покажем за 15 минут, как это будет работать именно в вашей футбольной школе
                            </div>
                        </div>
                    </div>

                    <!-- Правый столбец -->
                    <div class="col-md-6 d-flex justify-content-center align-items-center mt-4 mt-md-0">
                        <img src="{{ asset('img/landing/football.png') }}"
                             alt="CRM для футбольной школы"
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
                            <span class="mark-creative">Почему в футбольной школе постоянно возникает хаос с оплатами</span>
                        </h2>

                        <p class="text-center text-muted fs-5 mb-4">
                            Когда в школе 30, 60 или 100+ игроков, ручной учёт перестаёт работать:
                            кто-то оплатил не полностью, кто-то забыл, кто-то перевёл «потом», а администратор
                            всё это собирает вручную из чатов и таблиц.
                        </p>

                        <div class="card border-0 shadow-lg rounded-4">
                            <div class="card-body py-4 px-3 px-md-4">

                                <ul class="list-unstyled mb-3">

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">Часть оплат теряется или приходит с опозданием</h5>
                                            <div class="text-muted">
                                                У родителей нет понятного сценария оплаты, а у школы — единого прозрачного учёта.
                                                В итоге недосборы, кассовые разрывы и постоянные догоняющие сообщения.
                                            </div>
                                        </div>
                                    </li>

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">Непонятно, кто и за что должен</h5>
                                            <div class="text-muted">
                                                Абонементы, разовые тренировки, индивидуалки, форма, турниры, сборы, лагерь —
                                                через пару месяцев без системы уже сложно быстро ответить, у кого какая задолженность.
                                            </div>
                                        </div>
                                    </li>

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">Владелец или администратор тратит часы на ручную рутину</h5>
                                            <div class="text-muted">
                                                Напоминания родителям, сверки переводов, ответы на вопросы по долгам и поиски
                                                оплат отнимают время, которое должно идти на развитие школы и тренировочный процесс.
                                            </div>
                                        </div>
                                    </li>

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">Тренеры и администраторы работают вслепую</h5>
                                            <div class="text-muted">
                                                Нет одной панели, где видно группы, игроков, платежи, просрочки и историю оплат.
                                                Информация живёт в головах сотрудников и теряется при любой замене администратора.
                                            </div>
                                        </div>
                                    </li>

                                    <li class="d-flex mb-3">
                                        <div class="me-3 mt-1">
                                            <i class="fas fa-exclamation-circle my-alert-color"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-semibold mb-1">Приём денег “на карту” создаёт риски</h5>
                                            <div class="text-muted">
                                                Такая схема плохо масштабируется, создаёт юридические и налоговые риски,
                                                а также мешает выстроить нормальный управленческий учёт в школе.
                                            </div>
                                        </div>
                                    </li>

                                </ul>

                                <p class="text-muted mt-3 mb-0" style="text-decoration: underline;">
                                    Для футбольной школы даже небольшой недосбор по ежемесячным оплатам за год превращается
                                    в ощутимую потерю выручки.
                                </p>

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
                    <span class="mark-creative">Что получает футбольная школа после подключения kidscrm.online</span>
                </h2>

                <p class="text-center text-muted fs-5 mb-4">
                    Вы перестаёте вручную собирать деньги и сводить долги.
                    <br>Система берёт на себя оплату, фиксацию поступлений, чеки, напоминания и прозрачный учёт.
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
                                                выше и стабильнее
                                            </div>
                                            <div class="text-muted small">
                                                Родители получают понятные ссылки на оплату, а школа видит долги и просрочки
                                                без ручных сверок.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 p-md-4 h-100 rounded-4 bg-light">
                                            <div class="text-uppercase small fw-semibold text-muted mb-1">
                                                Админская нагрузка
                                            </div>
                                            <div class="h3 fw-bold mb-1 my-alert-color">
                                                меньше рутины
                                            </div>
                                            <div class="text-muted small">
                                                Снижается объём звонков, сообщений и ручной проверки переводов.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 p-md-4 h-100 rounded-4 bg-light">
                                            <div class="text-uppercase small fw-semibold text-muted mb-1">
                                                Финмодель
                                            </div>
                                            <div class="h3 fw-bold mb-1 my-alert-color">
                                                0 ₽ / месяц
                                            </div>
                                            <div class="text-muted small">
                                                Нет абонентской платы и платы за внедрение — только комиссия с успешных оплат.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3 смысловых блока -->
                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <div class="h-100 p-3 p-md-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Онлайн-оплаты для родителей</h5>
                                            <ul class="text-muted small mb-0 ps-3">
                                                <li>оплата по ссылке без лишних действий;</li>
                                                <li>деньги поступают на счёт школы;</li>
                                                <li>чеки уходят автоматически;</li>
                                                <li>не нужно подключать свою кассу и отдельный эквайринг.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="h-100 p-3 p-md-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Учёт игроков, абонементов и долгов</h5>
                                            <ul class="text-muted small mb-0 ps-3">
                                                <li>единая база игроков, родителей, тренеров и групп;</li>
                                                <li>видно, кто оплатил, кто просрочил, кто должен частично;</li>
                                                <li>отдельно можно учитывать турниры, форму, сборы и лагерь;</li>
                                                <li>отчёты по школе — в пару кликов.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="h-100 p-3 p-md-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Понятная и безопасная схема</h5>
                                            <ul class="text-muted small mb-0 ps-3">
                                                <li>мультирасчёты через Т-Банк;</li>
                                                <li>онлайн-касса уже на нашей стороне;</li>
                                                <li>чеки формируются автоматически;</li>
                                                <li>у школы меньше юридических и операционных рисков.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center mt-4">
                                    <a href="#registration-form"
                                       class="btn btn-success btn-lg cta-inline"
                                       data-bs-toggle="modal"
                                       data-bs-target="#createOrder">
                                        Посмотреть демо для футбольной школы
                                    </a>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ДЛЯ ФУТБОЛЬНЫХ ШКОЛ -->
        <section class="py-5 bg-light">
            <div class="container">

                <div class="text-center mb-2">
                    <span class="section-label">Заточено под футбол</span>
                </div>

                <h2 class="text-center mb-3">
                    <span class="mark-creative">Что особенно важно именно для футбольных секций</span>
                </h2>

                <p class="text-center text-muted fs-5 mb-5">
                    У футбольных школ обычно не один тип платежей.
                    Есть ежемесячные абонементы, индивидуальные тренировки, форма, турниры, сборы, поездки и лагеря.
                    Поэтому вам нужна не “просто CRM”, а система, которая понимает эту логику.
                </p>

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">
                                <div class="row g-4">

                                    <div class="col-md-6 col-lg-3">
                                        <div class="h-100 p-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Абонементы по месяцам</h5>
                                            <p class="text-muted small mb-0">
                                                Система показывает, кто оплатил текущий месяц, у кого просрочка,
                                                а у кого есть частичная задолженность.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3">
                                        <div class="h-100 p-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Сборы за турниры и форму</h5>
                                            <p class="text-muted small mb-0">
                                                Можно учитывать отдельные платежи, не смешивая их с базовой абонентской платой.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3">
                                        <div class="h-100 p-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Группы, тренеры, расписание</h5>
                                            <p class="text-muted small mb-0">
                                                Вся структура школы находится в одной системе, а не разложена по разным таблицам.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3">
                                        <div class="h-100 p-4 rounded-4 bg-light">
                                            <h5 class="fw-bold mb-2">Родители и договоры</h5>
                                            <p class="text-muted small mb-0">
                                                Договоры можно подписывать онлайн, а история взаимодействия по оплатам сохраняется.
                                            </p>
                                        </div>
                                    </div>

                                </div>

                                <div class="text-center mt-4">
                                    <a href="#registration-form"
                                       class="btn btn-outline-success cta-inline"
                                       data-bs-toggle="modal"
                                       data-bs-target="#createOrder">
                                        Обсудить задачи моей футбольной школы
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
                    <span class="mark-creative">Подключаем футбольную школу под ключ без сложных интеграций</span>
                </h2>
                <p class="text-center text-muted mb-5 fs-5">
                    Вы оставляете заявку. Дальше мы сами помогаем с подключением, переносом базы и запуском.
                </p>

                @php
                    $steps = [
                        [
                            'icon' => 'img/landing/icons/Register.png',
                            'title' => 'Оставляете заявку',
                            'desc' => 'Показываете, как сейчас в школе устроен учёт игроков, групп и оплат.',
                        ],
                        [
                            'icon' => 'img/landing/icons/Import.png',
                            'title' => 'Переносим базу игроков и групп',
                            'desc' => 'Бесплатно переносим игроков, родителей, группы и расписание — даже если всё пока живёт в Excel.',
                        ],
                        [
                            'icon' => 'img/landing/icons/Price.png',
                            'title' => 'Настраиваем тарифы и платежи',
                            'desc' => 'Помогаем задать стоимость абонементов, доплат, сборов и других платежей именно под вашу школу.',
                        ],
                        [
                            'icon' => 'img/landing/icons/Credit-card.png',
                            'title' => 'Родители начинают оплачивать онлайн',
                            'desc' => 'Школа получает деньги, система автоматически фиксирует оплаты, статусы и чеки.',
                        ],
                        [
                            'icon' => 'img/landing/icons/reminder.png',
                            'title' => 'Вы видите долги и отчёты',
                            'desc' => 'В личном кабинете видно, кто оплатил, кто должен и какая выручка по школе, группам и периодам.',
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
                                        Получить демо и расчёт под мою школу
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
                            <span class="mark-creative">Что есть в CRM для футбольной школы</span>
                        </h2>

                        <p class="text-muted fs-5 mb-0">
                            Всё, что нужно для ежедневной работы с игроками, родителями, оплатами и задолженностями.
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
                                                'text' => 'Приём оплат от родителей',
                                                'desc' => 'Онлайн-оплаты по ссылке с автоматической фиксацией в системе.',
                                            ],
                                            [
                                                'icon' => 'img/landing/icons/functional/automatic-reporting.png',
                                                'text' => 'Контроль долгов и отчёты',
                                                'desc' => 'Кто оплатил, кто должен, за какой период и на какую сумму.',
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
                                                 alt="Панель управления футбольной школой"
                                                 class="img-fluid rounded-3">
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        @foreach ([
                                            [
                                                'icon' => 'img/landing/icons/functional/user-group.png',
                                                'text' => 'Игроки, группы и тренеры',
                                                'desc' => 'Единая база школы: состав групп, тренеры, родители, история платежей.',
                                            ],
                                            [
                                                'icon' => 'img/landing/icons/functional/schedule-management.png',
                                                'text' => 'Онлайн-договоры с родителями',
                                                'desc' => 'Можно подписывать договоры дистанционно без бумажной волокиты.',
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
                                        <img src="{{ asset('img/landing/dance.png') }}"
                                             alt="Преимущества kidscrm.online для футбольной школы"
                                             class="img-fluid rounded">
                                    </div>

                                    <div class="col-md-8 mb-4 mb-md-0">
                                        <div class="text-center mb-2">
                                            <span class="section-label">Почему мы</span>
                                        </div>

                                        <h2 class="text-center mb-5" id="advantages">
                                            <span class="mark-creative">Почему владельцы футбольных школ выбирают kidscrm.online</span>
                                        </h2>

                                        <div class="container">
                                            <div class="row g-4">

                                                <div class="col-md-6">
                                                    <div class="card h-100 p-4 shadow-sm border-0 rounded-3">
                                                        <h5 class="fw-bold mb-3">Сервис вырос внутри футбольной школы</h5>
                                                        <p class="text-muted fs-6">
                                                            Это не абстрактная CRM “для всех ниш”.
                                                            Логика сервиса рождалась на реальных задачах футбольной школы:
                                                            абонементы, долги, сборы, родители, группы, тренеры.
                                                        </p>
                                                        <div class="mt-auto">
                                                            <i class="bi bi-trophy display-5 text-primary"></i>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="card h-100 p-4 shadow-sm border-0 rounded-3">
                                                        <h5 class="fw-bold mb-3">Запуск без боли для команды</h5>
                                                        <p class="text-muted fs-6">
                                                            Мы не бросаем вас на этапе внедрения: переносим базу,
                                                            настраиваем систему, объясняем, как работать,
                                                            и помогаем запуститься без долгой подготовки.
                                                        </p>
                                                        <div class="mt-auto">
                                                            <i class="bi bi-person-raised-hand display-5 text-primary"></i>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-12">
                                                    <div class="card h-100 p-4 shadow-sm border-0 rounded-3 mt-3">
                                                        <h5 class="fw-bold mb-3">Нет абонплаты и лишних подключений</h5>
                                                        <p class="text-muted fs-6 mb-0">
                                                            Вам не нужно отдельно покупать онлайн-кассу, искать эквайринг,
                                                            настраивать сложные интеграции и платить за внедрение.
                                                            Мы уже собрали эту инфраструктуру — футбольная школа просто начинает работать.
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

        <!-- КЕЙС -->
        <section class="py-5 bg-light position-relative overflow-hidden">
            <div class="container">

                <div class="row justify-content-center text-center mb-5">
                    <div class="col-lg-12 col-xl-11">
                        <div class="mb-2">
                            <span class="section-label">Кейс</span>
                        </div>

                        <h2 class="text-center mb-4">
                            <span class="mark-creative">Сначала это была внутренняя CRM для футбольной школы</span>
                        </h2>

                        <p class="text-muted fs-5 mb-0">
                            Сервис не создавался “в вакууме”.
                            Он появился из реальной боли футбольной школы и уже больше 6 лет используется
                            в ежедневной работе с родителями, игроками, группами и оплатами.
                        </p>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">

                        <div class="card border-0 shadow-lg rounded-4 card-soft">
                            <div class="card-body p-4 p-md-5">

                                <div class="text-center mb-5">
                                    <div class="display-4 fw-bold mb-2 my-alert-color">
                                        80+ игроков
                                    </div>
                                    <div class="fs-5 fw-semibold">
                                        и более 6 лет использования в живой футбольной школе Санкт-Петербурга
                                    </div>
                                    <div class="text-muted mt-2">
                                        Система продолжает работать в реальных условиях, а не только на демо-данных.
                                    </div>
                                </div>

                                <div class="row g-4">

                                    <div class="col-md-6">
                                        <div class="p-4 bg-light rounded-4 h-100">
                                            <div class="fw-semibold mb-2">До внедрения</div>
                                            <ul class="text-muted mb-3 ps-3">
                                                <li>владелец и администратор вручную контролировали оплаты;</li>
                                                <li>родителям постоянно приходилось напоминать про оплату;</li>
                                                <li>долги и переводы приходилось сверять вручную;</li>
                                                <li>данные жили в чатах, таблицах и голове сотрудников.</li>
                                            </ul>

                                            <div class="fw-semibold mb-2">После внедрения</div>
                                            <ul class="text-muted mb-0 ps-3">
                                                <li>цены и абонементы задаются в системе;</li>
                                                <li>долги считаются автоматически;</li>
                                                <li>родители получают понятный сценарий оплаты;</li>
                                                <li>руководитель видит состояние оплат по школе в одном месте.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="p-4 bg-primary bg-opacity-10 rounded-4 h-100">
                                            <div class="fw-semibold mb-2">Что это даёт владельцу школы</div>
                                            <div class="text-muted mb-3">
                                                Меньше операционного хаоса, меньше ручных действий, лучше контроль денег
                                                и больше времени на развитие школы, набор игроков и качество тренировочного процесса.
                                            </div>
                                            <ul class="text-muted mb-0 ps-3">
                                                <li>снижается нагрузка на администратора;</li>
                                                <li>деньги собираются более системно;</li>
                                                <li>видна полная картина по выручке и задолженностям.</li>
                                            </ul>
                                        </div>
                                    </div>

                                </div>

                                <div class="text-center mt-5">
                                    <div class="fw-bold fs-5">
                                        Это решение уже прошло проверку реальной футбольной школой.
                                    </div>
                                    <div class="text-muted mb-3">
                                        Поэтому на демо мы показываем не “красивую презентацию”, а рабочую логику,
                                        которую можно перенести в вашу школу.
                                    </div>

                                    <a href="#registration-form"
                                       class="btn btn-success btn-lg"
                                       data-bs-toggle="modal"
                                       data-bs-target="#createOrder">
                                        Хочу посмотреть демо для моей школы
                                    </a>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </section>

        <!-- ТАРИФЫ -->
        <section id="pricing" class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-2">
                    <span class="section-label">Стоимость</span>
                </div>

                <h2 class="text-center mb-3">
                    <span class="mark-creative">Сколько стоит CRM для футбольной школы</span>
                </h2>

                <p class="text-center fs-5 text-muted mb-5">
                    <span class="my-alert-color">Без абонентской платы и без оплаты внедрения</span> —
                    вы платите только тогда, когда родители реально оплачивают занятия.
                </p>

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">

                                <div class="row g-4 justify-content-center">

                                    <div class="col-md-6 col-lg-3">
                                        <div class="card border-0 shadow-sm h-100 p-4 text-center">
                                            <img src="{{ asset('img/landing/icons/price/transferring-data.png') }}"
                                                 alt="Перенос данных"
                                                 class="mx-auto mb-3"
                                                 style="width:48px; height:48px; object-fit:contain;">
                                            <h5 class="fw-bold">
                                                <span class="my-alert-color">0 ₽</span> перенос базы
                                            </h5>
                                            <p class="text-muted fs-6">
                                                Переносим игроков, родителей, группы и расписание без дополнительной оплаты.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3">
                                        <div class="card border-0 shadow-sm h-100 p-4 text-center">
                                            <img src="{{ asset('img/landing/icons/price/money-fee.png') }}"
                                                 alt="Абонентская плата"
                                                 class="mx-auto mb-3"
                                                 style="width:48px; height:48px; object-fit:contain;">
                                            <h5 class="fw-bold">
                                                <span class="my-alert-color">0 ₽</span> абонентская плата
                                            </h5>
                                            <p class="text-muted fs-6">
                                                Пользование CRM, отчёты, учёт игроков, групп и оплат — без ежемесячного платежа.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3">
                                        <div class="card border-0 shadow-sm h-100 p-4 text-center">
                                            <img src="{{ asset('img/landing/icons/price/technical-support.png') }}"
                                                 alt="Поддержка"
                                                 class="mx-auto mb-3"
                                                 style="width:48px; height:48px; object-fit:contain;">
                                            <h5 class="fw-bold">
                                                <span class="my-alert-color">0 ₽</span> запуск и поддержка
                                            </h5>
                                            <p class="text-muted fs-6">
                                                Помогаем на старте и отвечаем на вопросы команды в процессе работы.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3">
                                        <div class="card border-0 shadow-sm h-100 p-4 text-center">
                                            <img src="{{ asset('img/landing/icons/price/commission.png') }}"
                                                 alt="Комиссия"
                                                 class="mx-auto mb-3"
                                                 style="width:48px; height:48px; object-fit:contain;">
                                            <h5 class="fw-bold">
                                                <span class="my-alert-color">1% комиссия сервиса</span>
                                            </h5>
                                            <p class="text-muted fs-6">
                                                Только с успешных онлайн-платежей. Мы зарабатываем, когда деньги получает школа.
                                            </p>
                                        </div>
                                    </div>

                                </div>

                                <div class="pricing-divider"></div>

                                <div class="row g-4 align-items-stretch">
                                    <div class="col-md-6">
                                        <div class="h-100 p-4 rounded-4 bg-light">
                                            <div class="text-uppercase small fw-semibold text-muted mb-2">
                                                Если делать всё самостоятельно
                                            </div>
                                            <h5 class="fw-semibold mb-3">Эквайринг + касса + CRM + внедрение</h5>
                                            <ul class="text-muted small mb-0 ps-3">
                                                <li>отдельный договор с банком или агрегатором;</li>
                                                <li>своя онлайн-касса и её обслуживание;</li>
                                                <li>допрасходы на фискальный накопитель;</li>
                                                <li>ежемесячная оплата CRM;</li>
                                                <li>ручной перенос базы и обучение сотрудников.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="h-100 p-4 rounded-4 bg-white border">
                                            <div class="text-uppercase small fw-semibold text-success mb-2">
                                                Если работать с kidscrm.online
                                            </div>
                                            <h5 class="fw-semibold mb-3">Готовая инфраструктура для футбольной школы</h5>
                                            <ul class="text-muted small mb-3 ps-3">
                                                <li>не нужна своя онлайн-касса;</li>
                                                <li>не нужно отдельно подключать эквайринг;</li>
                                                <li>нет абонплаты за CRM;</li>
                                                <li>мы переносим базу и помогаем с запуском;</li>
                                                <li>вы платите только комиссию с успешных оплат.</li>
                                            </ul>

                                            <div class="text-md-end text-start">
                                                <a href="#registration-form"
                                                   class="btn btn-outline-success cta-inline"
                                                   data-bs-toggle="modal"
                                                   data-bs-target="#createOrder">
                                                    Узнать условия для моей школы
                                                </a>
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

        <!-- FAQ -->
        <section id="faq" class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-2">
                    <span class="section-label">Частые вопросы</span>
                </div>

                <h2 class="text-center mb-4">FAQ для футбольных школ</h2>

                <div class="row justify-content-center">
                    <div class="col-lg-12 col-xl-11">
                        <div class="card border-0 shadow-lg rounded-4 bg-white card-soft">
                            <div class="card-body p-4 p-md-5">
                                <div class="accordion" id="faqAccordion">
                                    @foreach ([
                                        'Подходит ли сервис именно футбольной школе?' => 'Да. Более того, kidscrm.online вырос из реальной футбольной школы. Логика сервиса изначально строилась вокруг задач владельца секции: абонементы, игроки, группы, тренеры, долги, сборы и родительские оплаты.',
                                        'Можно ли учитывать не только ежемесячные абонементы, но и отдельные сборы?' => 'Да. Помимо регулярной оплаты занятий вы можете учитывать отдельные платежи: форму, турниры, сборы, поездки, лагерь и другие доплаты.',
                                        'Нужно ли футбольной школе покупать онлайн-кассу?' => 'Нет. Наша платёжная инфраструктура уже включает онлайн-кассу на стороне сервиса. Родителям автоматически отправляются чеки.',
                                        'Нужно ли подключать свой эквайринг?' => 'Нет. Мы уже работаем через мультирасчёты Т-Банка. Это избавляет школу от отдельного подключения и настройки платёжной части.',
                                        'Поможете ли вы перенести текущую базу игроков и родителей?' => 'Да. Мы бесплатно переносим базу игроков, родителей, групп и расписание, даже если сейчас всё ведётся в Excel или вручную.',
                                        'Как быстро можно запустить школу?' => 'Обычно старт занимает минимальное время после согласования данных и подключения. Мы сопровождаем запуск и помогаем пройти путь от заявки до первых оплат.',
                                        'Есть ли мобильное приложение?' => 'Отдельного приложения пока нет, но интерфейс адаптирован под смартфоны и планшеты. Администратор и тренеры могут заходить с телефона.',
                                        'Можно ли сделать возврат родителю?' => 'Да. Администратор может оформить возврат по нужному платежу в системе, если это предусмотрено настройками школы.',
                                    ] as $question => $answer)
                                        <div class="accordion-item mb-3 border-0 shadow-sm">
                                            <h2 class="accordion-header" id="heading{{ $loop->index }}">
                                                <button class="accordion-button collapsed bg-white text-dark d-flex justify-content-between align-items-center"
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
                    Хотите навести порядок в оплатах футбольной школы?
                </h2>

                <p class="fs-5 mb-3">
                    Покажем, как <span class="fw-bold">kidscrm.online</span> поможет вашей школе
                    собирать деньги вовремя, видеть долги по каждому игроку и убрать ручной хаос из оплат.
                </p>

                <p class="fs-5 fw-semibold mb-4">
                    На демо разберём вашу текущую схему оплат и покажем, как это можно автоматизировать.
                </p>

                <a href="#registration-form"
                   class="btn btn-success btn-lg btn-order btn-order-transform"
                   data-bs-toggle="modal"
                   data-bs-target="#createOrder"
                   data-analytics="cta_bottom_demo_football">
                    Записаться на демо 15 минут
                </a>
            </div>
        </section>

    </div>

@endsection