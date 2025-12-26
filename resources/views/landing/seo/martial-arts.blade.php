@extends('layouts.landingPage')
@section('title', 'CRM для школы единоборств — учет учеников, расписание и оплаты | kidscrm.online')

@section('content')

    <!-- Hero -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row align-items-center">

                <h1 class="display-5 fw-bold text-center">
                    CRM для школ единоборств — контроль оплат, договоров и расписания в одном сервисе
                </h1>
                <h2 class="text-center">
                    <b class="alert-color">Экономьте до 30% времени</b> за счёт автоматизации административных задач
                </h2>

                <div class="col-md-6 mb-4 mb-md-0">
                    <p class="lead mt-4 mb-3">
                        <b>kidscrm.online</b> — CRM-сервис для секций единоборств (карате, дзюдо, тхэквондо, бокс),
                        который помогает держать под контролем: учеников и группы, расписание тренировок, оплаты,
                        задолженности, договоры и отчетность.
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
                            <span>Администрирование тренировок: группы, тренеры, расписание.</span>
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
                    <img src="{{ asset('img/landing/seo/martial-arts/hero.png') }}"
                         alt="CRM для школы единоборств"
                         class="img-fluid rounded shadow-sm"
                         onerror="this.src='{{ asset('img/landing/football.png') }}'">
                </div>

            </div>
        </div>
    </section>

    {{-- (1) Сегменты / сценарии для единоборств --}}
    <section id="martial-segments" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">CRM для единоборств: под какие сценарии подходит лучше всего</h2>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Небольшая секция</h3>
                            <p class="text-muted mb-0">
                                1 администратор, несколько групп, оплата помесячно. Важны долги, порядок в расписании и отчеты.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Клуб с несколькими дисциплинами</h3>
                            <p class="text-muted mb-0">
                                Карате + дзюдо + бокс, разные тренеры и группы. Нужны единые правила учета и прозрачные оплаты.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Сеть / несколько залов</h3>
                            <p class="text-muted mb-0">
                                Важно стандартизировать процессы и снизить зависимость от “табличек администратора”.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Сборы и соревнования</h3>
                            <p class="text-muted mb-0">
                                Доп. тренировки, переносы и платежи по разным условиям — всё должно быть фиксировано и прозрачно.
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
            <h2 class="text-center mb-4">CRM vs Excel/тетрадь/чаты: что меняется в школе единоборств</h2>
            <p class="text-center text-muted mb-5 fs-5">
                Если учет живёт в таблицах и переписках — появляются долги, путаница по суммам и ручная рутина.
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
                        <td class="fw-bold">Контроль оплат</td>
                        <td>Ручные отметки, постоянные уточнения “оплатили/не оплатили”.</td>
                        <td>Оплаты фиксируются в системе, статусы видны сразу.</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Сумма “в этом месяце”</td>
                        <td>Разные условия, пересчеты, админ объясняет каждому отдельно.</td>
                        <td>Заданные суммы: общие и индивидуальные — меньше вопросов и ручных пояснений.</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Задолженности</td>
                        <td>Долги “всплывают” поздно, неловкие разговоры с родителями.</td>
                        <td>Прозрачный список должников и периодов, меньше конфликтов.</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Расписание тренировок</td>
                        <td>Переносы по чатам, легко пропустить изменение.</td>
                        <td>Единый источник правды: расписание и изменения в одном месте.</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Договоры</td>
                        <td>Распечатки, подписи на тренировках, потерянные файлы.</td>
                        <td>Онлайн-подписание через СМС и хранение документов в системе.</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Отчет за месяц</td>
                        <td>Сводится вручную, занимает часы и ошибки неизбежны.</td>
                        <td>Автоматические отчеты: доходы, долги, транзакции.</td>
                    </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </section>

    {{-- (3) Боли / сценарии --}}
    <section id="problems" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Что обычно болит у школ единоборств — и как CRM это закрывает</h2>

            <div class="row g-4 align-items-center">
                <div class="col-md-6">
                    <div class="border-0 shadow-sm p-4 rounded bg-white">
                        <h3 class="h5 fw-bold">Оплаты, долги и ручные сверки</h3>
                        <p class="text-muted mb-0">
                            CRM дает прозрачность по оплатам и задолженностям: меньше ручной сверки, меньше спорных ситуаций.
                        </p>
                    </div>
                    <div class="border-0 shadow-sm p-4 rounded bg-white mt-3">
                        <h3 class="h5 fw-bold">Переносы, сборы, доп. тренировки</h3>
                        <p class="text-muted mb-0">
                            Расписание в едином месте: изменения не теряются в чатах и не превращаются в “кто что понял”.
                        </p>
                    </div>
                    <div class="border-0 shadow-sm p-4 rounded bg-white mt-3">
                        <h3 class="h5 fw-bold">Договоры и организационная рутина</h3>
                        <p class="text-muted mb-0">
                            Онлайн-подписание через СМС убирает бумажные круги ада и экономит время и клубу, и родителям.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 text-center">
                    {{-- место под графику --}}
                    <img src="{{ asset('img/landing/seo/martial-arts/problems.png') }}"
                         alt="Проблемы школы единоборств и решение через CRM"
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
                        ['icon' => 'img/landing/icons/functional/user-group.png', 'text' => 'Учёт пользователей и групп', 'desc' => 'Добавляйте учеников и распределяйте по группам, храните данные в одном месте.'],
                        ['icon' => 'img/landing/icons/functional/schedule-management.png', 'text' => 'Управление расписанием', 'desc' => 'Гибко настраивайте расписание с учётом тренеров, залов и групп.'],
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
            <h2 class="text-center mb-4">Коммуникации с родителями: меньше сообщений, больше ясности</h2>
            <p class="text-center text-muted mb-5 fs-5">
                В секциях единоборств больше всего времени “съедают” повторяющиеся вопросы и ручные напоминания.
                CRM снижает хаос в переписке и помогает выстроить прозрачный процесс.
            </p>

            <div class="row g-4 align-items-stretch">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Понятные суммы оплат</h3>
                            <p class="text-muted mb-0">
                                Общие и индивидуальные цены на месяц — меньше уточнений “сколько платить?”.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Прозрачные статусы</h3>
                            <p class="text-muted mb-0">
                                “Оплачено / не оплачено / задолженность” — меньше споров и недопонимания.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Изменения расписания</h3>
                            <p class="text-muted mb-0">
                                Когда тренировка переносится — важна единая точка правды, а не “последнее сообщение в чате”.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Договоры без бумажной рутины</h3>
                            <p class="text-muted mb-0">
                                Онлайн-подпись через СМС — не нужно ловить родителей на тренировках и носить распечатки.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- (5) Что дает CRM — по ролям --}}
    <section id="roles" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">Что дает CRM школе единоборств — владельцу и родителям</h2>
            <p class="text-center text-muted mb-5 fs-5">
                CRM делает процессы прозрачными: владельцу — контроль и управляемость, родителям — ясность и удобство.
            </p>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Владелец секции</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2"><b>Прозрачность денег:</b> видны оплаты и задолженности по всем группам и периодам.</li>
                                <li class="mb-2"><b>Гибкие суммы оплат:</b> общая цена на месяц или индивидуальные цены отдельным ученикам/группам. Раз в месяц подправили — и забыли.</li>
                                <li class="mb-2"><b>Меньше вопросов от родителей:</b> “сколько платить?” — сумма уже задана в системе.</li>
                                <li class="mb-2"><b>Онлайн-подписание договоров:</b> подписывается через СМС прямо в сервисе — без распечаток и встреч на тренировках.</li>
                                <li class="mb-2"><b>Контроль без микроменеджмента:</b> меньше зависимости от таблиц и “памяти администратора”.</li>
                                <li><b>Быстрая отчетность:</b> доходы, долги, транзакции — автоматически.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Родители</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2"><b>Онлайн-подписание договора:</b> через СМС — без бумажек и встреч на занятиях ради подписи.</li>
                                <li class="mb-2"><b>Понятная сумма к оплате:</b> нет вопросов “сколько оплачивать в этом месяце?” — сумма задана в системе, включая индивидуальные условия.</li>
                                <li class="mb-2"><b>Онлайн-оплата:</b> удобно оплачивать занятия без наличных и “переводов на карту”.</li>
                                <li class="mb-2"><b>Меньше неудобных ситуаций:</b> прозрачные статусы оплат снижают спорные моменты.</li>
                                <li><b>Ясность по расписанию:</b> меньше хаоса в переносах и организационных вопросах.</li>
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
                Автоматизируйте управление секцией от регистрации до оплаты и отчётности.
            </p>

            @php
                $steps = [
                    ['icon' => 'img/landing/icons/Register.png',          'title' => 'Моментальная регистрация',         'desc' => 'Подключите секцию в пару кликов — без лишних форм и ожиданий.'],
                    ['icon' => 'img/landing/icons/Import.png',            'title' => 'Импорт данных “под ключ”',       'desc' => 'Мы бесплатно перенесём группы, учеников и расписание в систему, чтобы вы сразу начали работать.'],
                    ['icon' => 'img/landing/icons/Price.png',             'title' => 'Гибкая настройка цен',           'desc' => 'Задавайте общие и индивидуальные цены для ученика или группы — одним действием.'],
                    ['icon' => 'img/landing/icons/Credit-card.png',       'title' => 'Онлайн-платежи за занятия',      'desc' => 'Родители оплачивают через встроенный эквайринг, платежи автоматически фиксируются в системе.'],
                    ['icon' => 'img/landing/icons/Report.png',            'title' => 'Единая панель отчётности',       'desc' => 'Контролируйте платежи, просрочки и ключевые показатели в реальном времени.'],
                    ['icon' => 'img/landing/icons/reminder.png',          'title' => 'Напоминания и пени',             'desc' => 'Система оповестит должников и при необходимости рассчитает штрафы автоматически.'],
                    ['icon' => 'img/landing/icons/saving-time.png',       'title' => 'Экономия времени',              'desc' => 'Меньше ручного учета и переписок — больше времени на развитие секции и качество тренировок.'],
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
                                <li class="mb-2">Список групп и тренеров.</li>
                                <li class="mb-2">Расписание тренировок (как сейчас).</li>
                                <li class="mb-2">Правила оплаты: суммы/периоды (включая индивидуальные цены).</li>
                                <li>База учеников (хоть в тетради/Excel).</li>
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
                                <li class="mb-2">Порядок в расписании тренировок.</li>
                                <li class="mb-2">Прозрачный контроль оплат и задолженностей.</li>
                                <li>Автоматическую отчетность по секции.</li>
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

    <!-- Уникальные преимущества (как на главной) -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row align-items-center">

                <div class="col-md-6 text-end mob-hide">
                    <img src="{{ asset('img/landing/icons/unit/martial-arts.png') }}"
                         alt="Преимущества CRM"
                         class="img-fluid rounded"
                         onerror="this.style.display='none'">
                </div>

                <div class="col-md-6 mb-4 mb-md-0">
                    <h2 class="text-center mb-5" id='advantages'>Наши уникальные преимущества</h2>

                    <div class="container">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card h-100 p-4 shadow-sm border-0 rounded-3">
                                    <h5 class="fw-bold mb-3">Мы сами перенесем данные ваших учеников</h5>
                                    <p class="text-muted fs-6">
                                        Мы бесплатно перенесём вашу базу учеников, групп и расписаний — даже если
                                        сейчас всё в тетради или Excel. Вы ничего не вводите вручную.
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
                                        При старте вы получаете персонального тех. специалиста:
                                        консультации, ответы на вопросы, помощь в адаптации процессов под платформу.
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
                $martialFaq = [
                    'Подходит ли CRM небольшой секции единоборств?' =>
                        'Да. Даже небольшая секция выигрывает за счёт прозрачного учета оплат, задолженностей и порядка в расписании.',
                    'Можно ли задать разные суммы оплат для разных учеников или групп?' =>
                        'Да. Вы можете установить общую цену на месяц или индивидуальные цены отдельным ученикам/группам. Это снижает количество вопросов от родителей.',
                    'Как происходит подписание договора с родителями?' =>
                        'Договор подписывается прямо в сервисе через СМС. Не нужно распечатывать документы и встречаться на тренировках ради подписи.',
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
                @foreach($martialFaq as $question => $answer)
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
                    @foreach($martialFaq as $q => $a)
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
            <h2 class="display-6 fw-bold mb-3">Готовы навести порядок в школе единоборств?</h2>
            <p class="fs-5 mb-4">
                Попробуйте <span class="fw-bold">kidscrm.online</span> бесплатно и получите полную поддержку
                персонального куратора при запуске.
            </p>
            <a href="#registration-form" class="btn btn-success btn-lg me-3"
               data-bs-toggle="modal" data-bs-target="#createOrder">Попробовать бесплатно</a>
        </div>
    </section>

@endsection
