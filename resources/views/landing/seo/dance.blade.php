@extends('layouts.landingPage')
@section('title', 'CRM для танцевальной студии — учет учеников, расписание и оплаты | kidscrm.online')

@section('content')

    <!-- Hero -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row align-items-center">

                <h1 class="display-5 fw-bold text-center">
                    CRM для детских танцевальных школ — контроль оплат, договоров и расписания в одном сервисе
                </h1>
                <h2 class="text-center">
                    <b class="alert-color">Экономьте до 30% времени</b> за счёт автоматизации административных задач
                </h2>

                <div class="col-md-6 mb-4 mb-md-0">
                    <p class="lead mt-4 mb-3">
                        <b>kidscrm.online</b> — CRM-сервис для танцевальных студий и школ, который помогает держать под контролем:
                        учеников и группы, расписание занятий, оплаты, задолженности, договоры и отчетность.
                    </p>

                    <ul class="list-unstyled lead mt-4 mb-4">
                        <li class="d-flex align-items-center mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Чек"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Онлайн-подписание договоров с родителями через СМС.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Чек"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Управление занятиями: группы, педагоги, расписание.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Чек"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Гибкие суммы оплат: общие и индивидуальные цены на месяц.</span>
                        </li>
                        <li class="d-flex align-items-center">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Чек"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Контроль задолженностей: кто не оплатил и за какой период.</span>
                        </li>
                    </ul>

                    <!-- CTA -->
                    <div class="text-center mt-4">
                        <a href="#registration-form" class="btn btn-success btn-lg"
                           data-bs-toggle="modal" data-bs-target="#createOrder">Попробовать бесплатно</a>
                    </div>

                </div>

                <div class="col-md-6 text-end">
                    <img src="{{ asset('img/landing/dance.png') }}"
                         alt="CRM для танцевальной студии"
                         class="img-fluid rounded shadow-sm">
                </div>

            </div>
        </div>
    </section>

    {{-- (1) Сегменты / сценарии для танцев --}}
    <section id="dance-segments" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">CRM для танцевальной школы: под какие сценарии подходит лучше всего</h2>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Небольшая студия</h3>
                            <p class="text-muted mb-0">
                                1 администратор, несколько групп, оплата помесячно. Важны долги, порядок в расписании и быстрые отчеты.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Школа с несколькими направлениями</h3>
                            <p class="text-muted mb-0">
                                Разные возраста и стили, несколько педагогов. Нужны единые правила учета и прозрачная система оплат.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Несколько залов / филиалов</h3>
                            <p class="text-muted mb-0">
                                Важно унифицировать администрирование и снять зависимость от “табличек конкретного администратора”.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Подготовка к выступлениям</h3>
                            <p class="text-muted mb-0">
                                Репетиции, переносы, дополнительные занятия и сборы — всё должно быть понятно и фиксироваться в системе.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- (2) Сравнение: Excel/тетрадь/мессенджеры vs CRM --}}
    <section id="comparison" class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">CRM vs Excel/тетрадь/чаты: что меняется в танцевальной студии</h2>
            <p class="text-center text-muted mb-5 fs-5">
                Когда учет живёт в таблицах и переписках — появляются долги, путаница по суммам и ручная рутина.
                CRM делает процессы управляемыми и прозрачными.
            </p>

            <div class="table-responsive shadow-sm rounded">
                <table class="table table-bordered align-middle mb-0 bg-white">
                    <thead class="table-light">
                    <tr>
                        <th style="width:34%">Задача</th>
                        <th style="width:33%">Тетрадь, Excel и чаты</th>
                        <th style="width:33%">Как с kidscrm.online</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="fw-bold">Сумма к оплате “в этом месяце”</td>
                        <td>Постоянные уточнения, пересчеты, разные условия у разных детей.</td>
                        <td>Заданная сумма в системе: общая или индивидуальная — вопросов становится меньше.</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Контроль оплат и задолженностей</td>
                        <td>Ручные отметки, легко забыть или перепутать период.</td>
                        <td>Оплаты фиксируются в системе, долги и периоды видны сразу.</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Переносы занятий и репетиции</td>
                        <td>Изменения “тонут” в чате, кто-то обязательно пропускает.</td>
                        <td>Единый источник правды: расписание и изменения в одном месте.</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Договоры с родителями</td>
                        <td>Распечатки, подписи “на коленке”, кто-то не успел, кто-то потерял.</td>
                        <td>Онлайн-подписание договора через СМС прямо в сервисе — удобно всем.</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Отчетность за месяц</td>
                        <td>Сводится вручную, занимает часы, ошибки неизбежны.</td>
                        <td>Автоматические отчеты: доходы, долги, транзакции.</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Рост студии</td>
                        <td>Таблицы разрастаются, структура ломается, всё зависит от одного человека.</td>
                        <td>Единая система: проще масштабироваться и держать порядок.</td>
                    </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </section>

    {{-- (3) Боли/сценарии --}}
    <section id="problems" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Что обычно болит у танцевальных студий — и как CRM это закрывает</h2>

            <div class="row g-4 align-items-center">
                <div class="col-md-6">
                    <div class="border-0 shadow-sm p-4 rounded bg-white">
                        <h3 class="h5 fw-bold">Оплаты и “почему не отмечено?”</h3>
                        <p class="text-muted mb-0">
                            CRM упрощает контроль оплат и снижает количество спорных ситуаций: статусы видны сразу, долги — по периодам.
                        </p>
                    </div>
                    <div class="border-0 shadow-sm p-4 rounded bg-white mt-3">
                        <h3 class="h5 fw-bold">Расписание, переносы, репетиции</h3>
                        <p class="text-muted mb-0">
                            Когда много групп и педагогов, переносы неизбежны. CRM помогает удерживать расписание управляемым и понятным.
                        </p>
                    </div>
                    <div class="border-0 shadow-sm p-4 rounded bg-white mt-3">
                        <h3 class="h5 fw-bold">Организация через чаты</h3>
                        <p class="text-muted mb-0">
                            Снижается хаотичная переписка: меньше повторяющихся вопросов и ручных напоминаний по оплатам и организационным моментам.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 text-center">
                    {{-- Место под графику (можешь добавить свой файл) --}}
                    <img src="{{ asset('img/landing/seo/dance/problems.png') }}"
                         alt="Проблемы танцевальной студии и решение через CRM"
                         class="img-fluid rounded shadow-sm"
                         onerror="this.style.display='none'">
                </div>
            </div>
        </div>
    </section>

    <!-- Ключевой функционал (дубль) -->
    <section id="features" class="bg-light py-5">
        <div class="container">
            <h2 class="text-center mb-5">Ключевой функционал</h2>
            <div class="row align-items-center">

                <div class="col-md-4">
                    @foreach([
                        ['icon' => 'img/landing/icons/functional/user-group.png', 'text' => 'Учёт пользователей и групп', 'desc' => 'Добавляйте учеников и распределяйте по группам, ведите структуру студии.'],
                        ['icon' => 'img/landing/icons/functional/schedule-management.png', 'text' => 'Управление расписанием', 'desc' => 'Гибко настраивайте расписание занятий с учётом групп и педагогов.'],
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
                        ['icon' => 'img/landing/icons/functional/payment-acceptance.png', 'text' => 'Приём и учёт оплат', 'desc' => 'Онлайн-оплата и автоматическая фиксация платежей в системе.'],
                        ['icon' => 'img/landing/icons/functional/automatic-reporting.png', 'text' => 'Автоматическая отчётность', 'desc' => 'Сводки по доходам, задолженностям и транзакциям — автоматически.'],
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
            <h2 class="text-center mb-4">Коммуникации с родителями: меньше хаоса в чатах</h2>
            <p class="text-center text-muted mb-5 fs-5">
                В студиях повторяются одни и те же вопросы: “сколько платить?”, “когда занятие?”, “перенесли или нет?”.
                CRM помогает снять эту рутину и сделать процесс прозрачным.
            </p>

            <div class="row g-4 align-items-stretch">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Понятные суммы оплат</h3>
                            <p class="text-muted mb-0">
                                Общие и индивидуальные цены на месяц — меньше вопросов и ручных пояснений.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Статусы оплат</h3>
                            <p class="text-muted mb-0">
                                “Оплачено / не оплачено / задолженность” — меньше споров, больше ясности.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Переносы занятий</h3>
                            <p class="text-muted mb-0">
                                Единая точка правды по расписанию вместо “последнего сообщения в чате”.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Договоры без бумажной рутины</h3>
                            <p class="text-muted mb-0">
                                Онлайн-подписание через СМС: не нужно ловить родителей на занятиях и носить распечатки.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- (5) Что дает CRM — по ролям (руководитель и родители) --}}
    <section id="roles" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">Что дает CRM танцевальной студии — руководителю и родителям</h2>
            <p class="text-center text-muted mb-5 fs-5">
                CRM делает процессы прозрачными: руководителю — контроль и управляемость, родителям — ясность и удобство.
            </p>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Руководитель / владелец студии</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2"><b>Прозрачность денег:</b> видны оплаты и задолженности по группам и периодам.</li>
                                <li class="mb-2"><b>Гибкие суммы оплат:</b> общая цена на месяц или индивидуальные цены отдельным детям/группам. Раз в месяц подправили — и забыли.</li>
                                <li class="mb-2"><b>Меньше вопросов от родителей:</b> “сколько платить?” — сумма уже задана в системе и понятна всем.</li>
                                <li class="mb-2"><b>Онлайн-подписание договоров:</b> договор подписывается через СМС прямо в сервисе — без распечаток и встреч “на занятиях”.</li>
                                <li class="mb-2"><b>Контроль без микроменеджмента:</b> меньше зависимости от таблиц и “памяти администратора”.</li>
                                <li class="mb-2"><b>Быстрая отчетность:</b> доходы, долги, транзакции — без ручной сверки.</li>
                                <li><b>Быстрый старт:</b> перенос базы “под ключ” и поддержка специалиста при запуске.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Родители</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2"><b>Онлайн-подписание договора:</b> подтверждение через СМС — не нужно бегать с бумажками и подписывать “на коленке”.</li>
                                <li class="mb-2"><b>Понятная сумма к оплате:</b> нет вопросов “сколько оплачивать в этом месяце?” — сумма заранее определена (включая индивидуальные условия).</li>
                                <li class="mb-2"><b>Онлайн-оплата:</b> удобно оплачивать занятия без наличных и “переводов на карту”.</li>
                                <li class="mb-2"><b>Меньше неудобных ситуаций:</b> прозрачные статусы оплат снижают спорные моменты.</li>
                                <li class="mb-2"><b>Ясность по расписанию:</b> меньше хаоса в переносах и организационных вопросах.</li>
                                <li class="mb-2"><b>Быстрее ответы от студии:</b> администратор видит информацию сразу, без “давайте уточню”.</li>
                                <li><b>Больше доверия:</b> студия выглядит организованной и профессиональной.</li>
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
                Автоматизируйте управление кружками и секциями от регистрации до оплаты и отчётности.
            </p>

            @php
                $steps = [
                    ['icon' => 'img/landing/icons/Register.png',          'title' => 'Моментальная регистрация',         'desc' => 'Подключите танцевальную студию в пару кликов — без лишних форм и долгих ожиданий.'],
                    ['icon' => 'img/landing/icons/Import.png',            'title' => 'Импорт данных “под ключ”',       'desc' => 'Мы бесплатно перенесём группы, учеников и расписание в систему, чтобы вы сразу приступили к работе.'],
                    ['icon' => 'img/landing/icons/Price.png',             'title' => 'Гибкая настройка цен',           'desc' => 'Задавайте общие и индивидуальные цены для ученика или группы — одним действием.'],
                    ['icon' => 'img/landing/icons/Credit-card.png',       'title' => 'Онлайн-платежи за занятия',      'desc' => 'Родители рассчитываются через встроенный эквайринг, а вы видите оплаты в системе.'],
                    ['icon' => 'img/landing/icons/Report.png',            'title' => 'Единая панель отчётности',       'desc' => 'Контролируйте платежи, просрочки и ключевые показатели в реальном времени.'],
                    ['icon' => 'img/landing/icons/reminder.png',          'title' => 'Напоминания и пени',             'desc' => 'Система оповестит должников и при необходимости рассчитает штрафы автоматически.'],
                    ['icon' => 'img/landing/icons/saving-time.png',       'title' => 'Экономия времени',              'desc' => 'Меньше ручного учета и переписок — больше времени на развитие студии и качество занятий.'],
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
                Ваша задача — подготовить минимум вводных. Наша задача — перенести данные и помочь запустить процесс.
            </p>

            <div class="row g-4 align-items-stretch">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Что готовите вы</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2">Список групп и педагогов.</li>
                                <li class="mb-2">Расписание занятий (как есть сейчас).</li>
                                <li class="mb-2">Правила оплаты: суммы/периоды (включая индивидуальные условия).</li>
                                <li>База учеников (даже если сейчас в тетради/Excel).</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Что делаем мы</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2">Бесплатно переносим учеников, группы, расписание.</li>
                                <li class="mb-2">Помогаем настроить оплаты и логику учета.</li>
                                <li class="mb-2">Подключаем онлайн-оплату (при необходимости).</li>
                                <li>Даем поддержку технического специалиста.</li>
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
                                <li class="mb-2">Порядок в расписании занятий.</li>
                                <li class="mb-2">Прозрачный контроль оплат и задолженностей.</li>
                                <li>Автоматическую отчетность по студии.</li>
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

    <!-- Уникальные преимущества (как на главной, без изменения структуры) -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row align-items-center">

                <div class="col-md-6 text-end mob-hide">
                    <img src="{{ asset('img/landing/dance.png') }}" alt="Преимущества CRM" class="img-fluid rounded">
                </div>

                <div class="col-md-6 mb-4 mb-md-0">
                    <h2 class="text-center mb-5" id='advantages'>Наши уникальные преимущества</h2>

                    <div class="container">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card h-100 p-4 shadow-sm border-0 rounded-3">
                                    <h5 class="fw-bold mb-3">Мы сами перенесем данные ваших учеников</h5>
                                    <p class="text-muted fs-6">
                                        Мы бесплатно перенесём всю вашу базу данных учеников, групп, расписаний, даже если
                                        эти данные записаны в обычной тетрадке.
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
                        <h5 class="fw-bold"><span class="alert-color">0 ₽</span> за перенос данных учеников в нашу систему</h5>
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
                            сразу, без «переадресаций».
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
                            онлайн-эквайринг. Никаких скрытых сборов и дополнительных платежей.
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
                $danceFaq = [
                    'Подходит ли CRM небольшой танцевальной студии?' =>
                        'Да. Даже небольшая студия выигрывает за счёт прозрачного учета оплат, задолженностей и порядка в расписании.',
                    'Можно ли задать разные суммы оплат для разных групп или учеников?' =>
                        'Да. Вы можете установить общую цену на месяц или индивидуальные цены отдельным ученикам/группам. Это снижает количество вопросов от родителей.',
                    'Как родители подписывают договор?' =>
                        'Договор подписывается прямо в сервисе через СМС. Не нужно распечатывать документы и встречаться на занятиях ради подписи.',
                    'Как вести учет оплат, чтобы не было путаницы по месяцам?' =>
                        'В kidscrm.online оплаты фиксируются в системе, а задолженности отображаются по периодам — вы видите ситуацию сразу.',
                    'Помогаете ли вы перенести базу учеников и расписание?' =>
                        'Да. Мы бесплатно переносим данные “под ключ”: учеников, группы и расписание — даже если сейчас всё в тетради или Excel.',
                    'Есть ли абонентская плата?' =>
                        'Нет. Абонентская плата — 0 ₽. Оплата сервиса — комиссия 1,5% только с успешных транзакций.',
                    'Есть ли мобильное приложение?' =>
                        'Пока приложения нет, но интерфейс адаптирован под смартфоны и планшеты — можно работать из браузера.',
                    'Сколько времени занимает запуск?' =>
                        'Чаще всего старт возможен за 1 день: вы даете вводные, мы переносим данные и помогаем настроить процесс.',
                ];
            @endphp

            <div class="accordion" id="faqAccordion">
                @foreach($danceFaq as $question => $answer)
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
                    @foreach($danceFaq as $q => $a)
                    {
                      "@type": "Question",
                      "name": @json($q),
                      "acceptedAnswer": {
                        "@type": "Answer",
                        "text": @json($a)
                    }
                  }@if(!$loop->last),@endif
                @endforeach
                ]
              }
</script>
        </div>
    </section>

    <!-- Call to Action -->
    <section id="cta" class="py-5 bg-call-to-action">
        <div class="container text-center">
            <h2 class="display-6 fw-bold mb-3">Готовы навести порядок в танцевальной студии?</h2>
            <p class="fs-5 mb-4">
                Попробуйте <span class="fw-bold">kidscrm.online</span> бесплатно и получите полную поддержку
                персонального куратора при запуске.
            </p>
            <a href="#registration-form" class="btn btn-success btn-lg me-3"
               data-bs-toggle="modal" data-bs-target="#createOrder">Попробовать бесплатно</a>
        </div>
    </section>


@endsection
