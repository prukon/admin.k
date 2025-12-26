    @extends('layouts.landingPage')
@section('title', 'CRM для футбольной секции — учет учеников, расписание и оплаты | kidscrm.online')

@section('content')

    <!-- Hero -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row align-items-center">

                <h1 class="display-5 fw-bold text-center">
                    CRM для футбольных секций — контроль оплат, договоров и расписания в одном сервисе
                </h1>
                <h2 class="text-center">
                    <b class="alert-color">Экономьте до 30% времени</b> за счёт автоматизации административных задач
                </h2>

                <div class="col-md-6 mb-4 mb-md-0">
                    <p class="lead mt-4 mb-3">
                        <b>kidscrm.online</b> — CRM-сервис для футбольных секций, который помогает держать под контролем:
                        учеников и команды, расписание тренировок, оплаты, задолженности, договоры и отчетность.
                    </p>

                    <ul class="list-unstyled lead mt-4 mb-4">
                        <li class="d-flex align-items-center mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Чек"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Подписание договоров с родителями игроков.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Чек"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Администрирование тренировок: группы, команды, расписание.</span>
                        </li>
                        <li class="d-flex align-items-center mb-2">
                            <img src="{{ asset('img/landing/icons/check-mark.png') }}"
                                 alt="Чек"
                                 class="me-2"
                                 style="width:24px; height:24px; object-fit:contain;">
                            <span>Учёт оплат и автоматические отчёты по секции.</span>
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
                    <img src="{{ asset('img/landing/football.png') }}"
                         alt="CRM для футбольной секции"
                         class="img-fluid rounded shadow-sm">

                  </div>

            </div>
        </div>
    </section>

    {{-- (1) Сегменты внутри футбола / сценарии --}}
    <section id="football-segments" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">CRM для футбола: под какие сценарии подходит лучше всего</h2>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Небольшая секция</h3>
                            <p class="text-muted mb-0">
                                1 администратор, 3–6 групп, оплата помесячно. Важны долги, список оплат и порядок в расписании.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Футбольная школа / академия</h3>
                            <p class="text-muted mb-0">
                                Много возрастов и тренеров. Нужны единые правила учета, договоры и отчетность по группам.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Несколько площадок / филиалов</h3>
                            <p class="text-muted mb-0">
                                Важно унифицировать администрирование и снять зависимость от “табличек конкретного администратора”.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Сезонность и сборы</h3>
                            <p class="text-muted mb-0">
                                Переходы между возрастами, изменения расписания, сборы и разные условия оплаты — всё должно быть прозрачным.
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
            <h2 class="text-center mb-4">CRM vs Excel/тетрадь/чаты: что меняется в футбольной секции</h2>
            <p class="text-center text-muted mb-5 fs-5">
                Если учет живёт в таблицах и переписках — рано или поздно появляются долги, путаница и ручная рутина.
                CRM делает процессы управляемыми и прозрачными.
            </p>

            <div class="table-responsive shadow-sm rounded">
                <table class="table table-bordered align-middle mb-0 bg-white">
                    <thead class="table-light">
                    <tr>
                        <th style="width:34%">Задача</th>
                        <th style="width:33%">Тетрадь, Excel и т.д.</th>
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
                        <td class="fw-bold">Отчет за месяц</td>
                        <td>Сводится вручную, занимает часы и ошибки неизбежны.</td>
                        <td>Автоматические отчеты: доходы, долги, транзакции.</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Договоры с родителями</td>
                        <td>Файлы “где-то”, сложно контролировать актуальность.</td>
                        <td>Договоры и статусы — внутри сервиса, проще навести порядок.</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Рост секции</td>
                        <td>Таблицы разрастаются, структура ломается, всё зависит от одного человека.</td>
                        <td>Единая система: легче масштабироваться и удерживать порядок.</td>
                    </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </section>

    {{-- Блок “Проблемы/сценарии” --}}
    <section id="problems" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Что обычно болит у футбольных секций — и как CRM это закрывает</h2>

            <div class="row g-4 align-items-center">
                <div class="col-md-6">
                    <div class="border-0 shadow-sm p-4 rounded bg-white">
                        <h3 class="h5 fw-bold">Оплаты и долги</h3>
                        <p class="text-muted mb-0">
                            “Кто оплатил?”, “за какой месяц?”, “почему платеж не отмечен?” — CRM снимает хаос и даёт прозрачность.
                        </p>
                    </div>
                    <div class="border-0 shadow-sm p-4 rounded bg-white mt-3">
                        <h3 class="h5 fw-bold">Расписание тренировок</h3>
                        <p class="text-muted mb-0">
                            Сезонность, переносы, разные поля/залы и тренеры — в CRM расписание становится управляемым.
                        </p>
                    </div>
                    <div class="border-0 shadow-sm p-4 rounded bg-white mt-3">
                        <h3 class="h5 fw-bold">Администрирование и отчеты</h3>
                        <p class="text-muted mb-0">
                            Меньше ручной рутины: данные в одном месте, отчеты формируются автоматически.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 text-center">
                    <img src="{{ asset('img/landing/seo/football/problems.png') }}"
                         alt="Проблемы футбольной секции и решение через CRM"
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

                <div class="col-md-4 text-center mb-4 mb-md-0">
                    <img src="{{ asset('img/landing/dashboard.png') }}"
                         alt="Функции CRM"
                         class="img-fluid rounded mx-auto d-block">
                </div>

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

            {{-- Якорная CTA --}}
            <div class="text-center mt-4">
                <a href="#registration-form" class="btn btn-success btn-lg"
                   data-bs-toggle="modal" data-bs-target="#createOrder">Попробовать бесплатно</a>
            </div>
        </div>
    </section>

    {{-- (5) Коммуникации с родителями --}}
    <section id="communications" class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">Коммуникации с родителями: меньше сообщений, больше ясности</h2>
            <p class="text-center text-muted mb-5 fs-5">
                В футбольных секциях больше всего времени “съедают” повторяющиеся вопросы и ручные напоминания.
                CRM снижает количество хаотичной переписки и помогает выстроить прозрачный процесс.
            </p>

            <div class="row g-4 align-items-stretch">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h6 fw-bold mb-2">Напоминания об оплате</h3>
                            <p class="text-muted mb-0">
                                Родители реже “забывают”, администратору не нужно вручную всем писать одно и то же.
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
                            <h3 class="h6 fw-bold mb-2">Порядок в договоренностях</h3>
                            <p class="text-muted mb-0">
                                Договоры и правила оплаты проще держать в системе, а не в разрозненных файлах.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- (6) Что дает CRM футбольной секции — по ролям (2 группы: владелец + родители) --}}
    <section id="roles" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">Что дает CRM футбольной секции — владельцу и родителям</h2>
            <p class="text-center text-muted mb-5 fs-5">
                CRM делает процессы прозрачными: владельцу — контроль и управляемость, родителям — ясность и меньше организационного стресса.
            </p>

            <div class="row g-4">
                {{-- Владелец секции --}}
                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Владелец секции</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2"><b>Прозрачность денег:</b> видны оплаты и задолженности по всем группам и периодам.</li>
                                <li class="mb-2"><b>Гибкие суммы оплат:</b> задавайте общую цену на месяц или индивидуальные цены отдельным игрокам/группам. Администратору удобно раз в месяц подправить цены — и забыть.</li>
                                <li class="mb-2"><b>Меньше вопросов от родителей:</b> “сколько платить в этом месяце?” — сумма уже задана в системе и понятна всем.</li>
                                <li class="mb-2"><b>Онлайн-подписание договоров:</b> договор подписывается прямо в сервисе через СМС — не нужно бегать с распечатками и ловить родителей на тренировках.</li>
                                <li class="mb-2"><b>Контроль без микроменеджмента:</b> меньше зависимости от “таблиц администратора”.</li>
                                <li class="mb-2"><b>Быстрая отчетность:</b> сводки по доходам, долгам и транзакциям — без ручной сверки.</li>
                                <li><b>Быстрый старт:</b> перенос базы “под ключ” и поддержка специалиста при запуске.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Родители --}}
                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold mb-3">Родители</h3>
                            <ul class="text-muted mb-0 ps-3">
                                <li class="mb-2"><b>Онлайн-подписание договора:</b> не нужно встречаться на тренировках и подписывать бумажки — договор подтверждается через СМС прямо в сервисе. Удобно всем.</li>
                                <li class="mb-2"><b>Понятная сумма к оплате:</b> нет вопросов “а сколько оплачивать в этом месяце?” — сумма заранее задана в системе, в том числе если у вас индивидуальная цена.</li>
                                <li class="mb-2"><b>Онлайн-оплата:</b> удобно оплатить занятия без наличных и “передач через тренера”.</li>
                                <li class="mb-2"><b>Меньше неудобных ситуаций:</b> прозрачные статусы оплат снижают спорные моменты.</li>
                                <li class="mb-2"><b>Ясность по расписанию:</b> меньше хаоса в переносах и организационных вопросах.</li>
                                <li class="mb-2"><b>Быстрее ответы от секции:</b> администратор видит информацию сразу, без “давайте уточню”.</li>
                                <li><b>Больше доверия:</b> процессы секции выглядят организованно и профессионально.</li>
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

    {{-- (8) Чек-лист внедрения за 1 день --}}
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
                                <li class="mb-2">Список групп/команд и тренеров.</li>
                                <li class="mb-2">Расписание тренировок (как есть сейчас).</li>
                                <li class="mb-2">Правила оплаты: суммы/периоды.</li>
                                <li>База учеников (даже в тетради/Excel).</li>
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
                                <li class="mb-2">Помогаем настроить оплату и логику учета.</li>
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

    <!-- Hero 2 (как на главной) -->
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

    <!-- FAQ (9) расширенный -->
    <section id="faq" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">FAQ</h2>

            @php
                $footballFaq = [
                    'Подходит ли CRM небольшой футбольной секции?' =>
                        'Да. Даже небольшая секция выигрывает за счёт прозрачного учета оплат, задолженностей и порядка в расписании.',
                    'Как вести учет оплат в футбольной секции, чтобы не было путаницы?' =>
                        'Лучший вариант — фиксировать оплаты в одной системе и видеть статусы по каждому ученику и периоду. В kidscrm.online оплаты и задолженности отображаются прозрачно.',
                    'Как контролировать задолженность родителей по оплате занятий?' =>
                        'В системе видно, кто не оплатил и за какой период. Это упрощает работу администратора и снижает количество конфликтов.',
                    'Помогаете ли вы перенести базу учеников и расписание?' =>
                        'Да. Мы бесплатно перенесём данные «под ключ»: учеников, группы и расписание — даже если сейчас всё в тетради или Excel.',
                    'Можно ли быстро собрать отчет по оплатам за месяц?' =>
                        'Да. Отчеты по платежам и задолженностям формируются автоматически — без ручной сверки.',
                    'Как родители оплачивают занятия?' =>
                        'Родители оплачивают онлайн через встроенный эквайринг. Платежи автоматически фиксируются в системе, и вы видите статусы оплат.',
                    'Есть ли абонентская плата?' =>
                        'Нет. Абонентская плата — 0 ₽. Оплата сервиса взимается только как комиссия 1,5% с успешных транзакций.',
                    'Можно ли вести в CRM и договоры с родителями?' =>
                        'Да. Вы можете организовать подписание договоров с родителями внутри сервиса и держать документы в одном месте.',
                    'Нужны ли тренерам отдельные приложения?' =>
                        'Нет. Интерфейс адаптирован под смартфоны и планшеты — можно работать из браузера.',
                    'Сколько времени занимает внедрение CRM в футбольной школе?' =>
                        'Чаще всего старт возможен за 1 день: вы даете вводные, мы переносим данные и помогаем настроить процесс.',
                ];
            @endphp

            <div class="accordion" id="faqAccordion">
                @foreach($footballFaq as $question => $answer)
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

            {{-- FAQ JSON-LD --}}
            <script type="application/ld+json">
                {
                  "@context": "https://schema.org",
                  "@type": "FAQPage",
                  "mainEntity": [
                    @foreach($footballFaq as $q => $a)
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
            <h2 class="display-6 fw-bold mb-3">Готовы вывести вашу футбольную секцию на новый уровень?</h2>
            <p class="fs-5 mb-4">
                Попробуйте <span class="fw-bold">kidscrm.online</span> бесплатно и получите полную поддержку
                персонального куратора при запуске.
            </p>
            <a href="#registration-form" class="btn btn-success btn-lg me-3"
               data-bs-toggle="modal" data-bs-target="#createOrder">Попробовать бесплатно</a>
        </div>
    </section>

@endsection