@extends('layouts.admin2')
@section('content')

    <div class=" main-content">
        <h4 class="pt-3 text-start">Консоль</h4>
        @can('users.view')
            <h5 class="choose-user-header text-start">Выбор ученика:</h5>

            {{--Выбор ученика, группы, кнопка установить--}}
            <div class="row choose-user">
                <div class="col-md-3 col-12 mb-3 team-select text-start">

                    <select class="form-select text-start" id="single-select-team" data-placeholder="Группа">
                        <option value="all">Все группы</option>
                        <option value="withoutTeam">Без группы</option>
                        <option></option>
                        @foreach($allTeams as $index => $team)
                            <option value="{{ $team->title }}" label="{{ $team->label }}"
                                    data-team-id="{{ $team->id }}">
                                {{ $index + 1 }}. {{ $team->title }}
                            </option>
                        @endforeach
                    </select>

                    <i class="fa-thin fa-calendar-lines"></i>
                </div>

                <div class="col-md-3 col-12 mb-3 user-select">


                    <select class="form-select" id="single-select-user" data-placeholder="ФИО">
                        <option value="">Выберите пользователя</option>

                        @foreach ($allUsersSelect as $index => $user)
                            @php
                                $fullName = $user->full_name ?? trim(($user->lastname ?? '').' '.($user->name ?? ''));
                            @endphp
                            <option
                                    value="{{ $user->id }}"
                                    label="{{ $fullName }}"
                                    data-user-id="{{ $user->id }}"
                            >
                                {{ $index + 1 }}. {{ $fullName }}
                            </option>
                        @endforeach


                    </select>

                </div>
            </div>
        @endcan

        {{--Аватарка и личные данные--}}
        <div class="row personal-data align-items-center">
            <div class="col-5 col-lg-3 avatar-wrap align-items-center">

                {{--Аватар--}}
                <div class="avatar">                         <!-- ВНЕШНИЙ контейнер (hover + меню) -->
                    <div class="avatar-clip">                  <!-- ВНУТРЕННИЙ круг (обрезка фото + бордер) -->
                        <img
                                src="{{ auth()->user()->image_crop ? asset('storage/avatars/'.auth()->user()->image_crop) : asset('/img/default-avatar.png') }}"
                                alt="Avatar">
                    </div>

                    <div class="avatar-actions">
                        <button class="dropdown-item js-open-photo" type="button">
                            <i class="fa-solid fa-image"></i> Открыть фото
                        </button>
                        <button class="dropdown-item js-change-photo" type="button"
                                data-bs-toggle="modal" data-bs-target="#avatarEditModal">
                            <i class="fa-solid fa-pen-to-square"></i> Изменить фото
                        </button>
                        <button class="dropdown-item text-danger js-delete-photo" type="button">
                            <i class="fa-solid fa-trash"></i> Удалить фото
                        </button>
                    </div>
                </div>

                <!-- CRUD аватарки -->
                @include('includes.modal.editAvatar')

            </div>
            <div class="col-7 col-lg-3 header-wrap">
                <div class="personal-data-header">

                    <div class="name">
                        Имя:<span class="name-value">{{ $curUser?->full_name ?: '-' }}</span>
                    </div>

                    <div class="birthday">Дата рождения: <span class="birthday-value"> @if($curUser->birthday)
                                {{ \Carbon\Carbon::parse($curUser->birthday)->format('d.m.Y') }}
                            @else
                                -
                            @endif </span></div>


                    <div class="email">Почта: <span class="email-value"> @if($curUser)
                                {{$curUser->email}}
                            @else
                                -
                            @endif </span></div>
                    <div class="group">Группа: <span class="group-value"@if($curUser->teams->count() >= 2) data-multi-team="1"@endif>
                            @if($curUser->teams->isEmpty())
                                -
                            @elseif($curUser->teams->count() === 1)
                                {{ $curUser->teams->first()->title }}
                            @else
                                @foreach($curUser->teams as $team)
                                    <span class="dashboard-group-name" data-team-id="{{ $team->id }}">{{ $team->title }}</span>@if(!$loop->last), @endif
                                @endforeach
                            @endif
                        </span></div>


                    <div class="fields-wrap">
                        @foreach($allFields as $field)
                            <div class="fields-title" data-id="{{$field->id}}">
                                {{ $field->name }}:
                                <span class="fields-value">{{ $userFieldValues[$field->id] ?? '-' }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{--<div class="display-none count-training">Количество тренировок: <span--}}
                    {{--class="count-training-value">223</span></div>--}}
                </div>
                @can('payment.clubfee')

                    <div class="mt-3">
                        <a href="/payment/club-fee">
                            <button type="button" id="club-fee" class="btn btn-primary">Клубный взнос</button>
                        </a>
                    </div>
                @endcan
            </div>

            @can('paying.classes')
                <div class="col-12 col-lg-4 mt-3 mb-3 credit-notice  align-items-center justify-content-center text-center">
                    <i class="close fa-solid fa-circle-xmark"></i>
                    У вас образовалась задолженность в размере <span class="summ"></span> руб.
                </div>
            @endcan

        </div>

        @if(!empty($textForUsers))
            <div class="notification-wrap mt-3 mb-3">
                <div class="notification">{{ $textForUsers }}</div>
            </div>
        @endif

        <h5 class="header-shedule display-none mt-3 mb-2">Расписание:</h5>

        <div class="mt-3 mb-3 calendar">
            <div class="calendar-header">
                <div id="prev-month">←</div>
                <div id="calendar-title"></div>
                <div id="next-month">→</div>
            </div>
            <div class="days-header">
                <div>Пн</div>
                <div>Вт</div>
                <div>Ср</div>
                <div>Чт</div>
                <div>Пт</div>
                <div>Сб</div>
                <div>Вс</div>
            </div>
            <div class="days" id="days"></div>

            <!-- Контекстное меню -->
            <div id="context-menu" class="context-menu">
                <div class="context-menu-item" data-action="add-training">Добавление тренировки</div>
                <div class="context-menu-item" data-action="remove-training">Удаление тренировки</div>
                <div class="context-menu-item" data-action="add-freeze">Добавление заморозки</div>
                <div class="context-menu-item" data-action="remove-freeze">Удаление заморозки</div>
            </div>
        </div>

        {{-- Дополнительные платежи (кастомные периоды) --}}
        @can('setPrices.customPayments.view')
        @if(isset($userAbonements) && $userAbonements->count() > 0)
            <div class="row custom-payments custom-payments-block mt-3 mb-3">
                <div class="col-12">
                    <div class="custom-payments-season">
                        <div class="custom-payments-header">Дополнительные платежи</div>
                        <div class="row justify-content-center align-items-center custom-payments-items">
                            @foreach($userAbonements as $a)
                                @php
                                    $startIso = $a->date_start ? \Carbon\Carbon::parse($a->date_start)->format('Y-m-d') : '';
                                    $endIso = $a->date_end ? \Carbon\Carbon::parse($a->date_end)->format('Y-m-d') : '';

                                    $startRu = $a->date_start
                                        ? \Carbon\Carbon::parse($a->date_start)->format('d.m.Y')
                                        : '';
                                    $endRu = $a->date_end
                                        ? \Carbon\Carbon::parse($a->date_end)->format('d.m.Y')
                                        : '';

                                    $note = (string) ($a->note ?? '');
                                    $amountNormalized = number_format((float) $a->amount, 2, '.', '');
                                    $amountDisplay = number_format((float) $a->amount, 0, ',', ' ');
                                    $paid = (bool) ($a->effective_is_paid ?? false);
                                    $periodRu = trim($startRu . ' - ' . $endRu);
                                    $paymentDateLabel = trim("Дополнительный платеж: {$periodRu}" . ($note !== '' ? " ({$note})" : ''));
                                @endphp

                                <div class="custom-payment-price col-3">
                                    <div class="row align-items-center justify-content-center">
                                        <span class="price-value">{{ $amountDisplay }}</span>
                                        <span class="hide-currency">₽</span>
                                    </div>
                                    <div class="row justify-content-center align-items-center">
                                        <div class="new-price-description">
                                            <div>{{ $periodRu }}</div>
                                            @if($note !== '')
                                                <div class="custom-payment-note">{{ $note }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="row new-main-button-wrap">
                                        <div class="justify-content-center align-items-center">
                                            @can('paying.classes')
                                                <form action="{{ route('payment') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="payment_kind" value="custom_payment">
                                                    <input type="hidden" name="custom_payment_id" value="{{ (int) $a->id }}">
                                                    <input type="hidden" name="user_lesson_package_id" value="">
                                                    <input type="hidden" name="custom_payment_date_start" value="{{ $startIso }}">
                                                    <input type="hidden" name="custom_payment_date_end" value="{{ $endIso }}">
                                                    <input type="hidden" name="custom_payment_note" value="{{ $note }}">

                                                    <input type="hidden" name="paymentDate" value="{{ $paymentDateLabel }}">
                                                    <input class="outSum" type="hidden" name="outSum" value="{{ $amountNormalized }}">

                                                    <button type="submit"
                                                            {{ $paid ? 'disabled' : '' }}
                                                            class="btn btn-lg btn-bd-primary new-main-button {{ $paid ? 'buttonPaided' : '' }}">
                                                        {{ $paid ? 'Оплачено' : 'Оплатить' }}
                                                    </button>
                                                </form>
                                            @else
                                                <button type="button" disabled class="btn btn-lg btn-bd-primary new-main-button {{ $paid ? 'buttonPaided' : '' }}">
                                                    {{ $paid ? 'Оплачено' : 'Оплатить' }}
                                                </button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif
        @endcan

        {{-- Назначенные абонементы (оплата из user_lesson_packages.fee_amount) --}}
        @if(isset($userLessonPackages) && $userLessonPackages->count() > 0)
            <div class="row custom-payments custom-payments-block mt-3 mb-3">
                <div class="col-12">
                    <div class="custom-payments-season">
                        <div class="custom-payments-header">Назначенные абонементы</div>
                        <div class="row justify-content-center align-items-center custom-payments-items">
                            @foreach($userLessonPackages as $ulp)
                                @php
                                    $pkgName = $ulp->lessonPackage->name ?? 'Абонемент';
                                    $amountNormalized = number_format((float) $ulp->fee_amount, 2, '.', '');
                                    $amountDisplay = number_format((float) $ulp->fee_amount, 0, ',', ' ');
                                    $paid = (bool) ($ulp->effective_is_paid ?? false);
                                    $periodRu = trim(
                                        ($ulp->starts_at ? $ulp->starts_at->locale('ru')->isoFormat('D.MM.YYYY') : '')
                                        . ' — '
                                        . ($ulp->ends_at ? $ulp->ends_at->locale('ru')->isoFormat('D.MM.YYYY') : '')
                                    );
                                    $paymentDateLabel = 'Абонемент: '.$pkgName.' №'.(int) $ulp->id;
                                @endphp
                                <div class="custom-payment-price col-3">
                                    <div class="row align-items-center justify-content-center">
                                        <span class="price-value">{{ $amountDisplay }}</span>
                                        <span class="hide-currency">₽</span>
                                    </div>
                                    <div class="row justify-content-center align-items-center">
                                        <div class="new-price-description">
                                            <div>{{ $pkgName }}</div>
                                            <div class="small text-muted">{{ $periodRu }}</div>
                                        </div>
                                    </div>
                                    <div class="row new-main-button-wrap">
                                        <div class="justify-content-center align-items-center">
                                            @can('paying.classes')
                                                <form action="{{ route('payment') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="payment_kind" value="lesson_package">
                                                    <input type="hidden" name="user_lesson_package_id" value="{{ (int) $ulp->id }}">
                                                    <input type="hidden" name="custom_payment_id" value="">
                                                    <input type="hidden" name="paymentDate" value="{{ $paymentDateLabel }}">
                                                    <input class="outSum" type="hidden" name="outSum" value="{{ $amountNormalized }}">

                                                    <button type="submit"
                                                            {{ $paid ? 'disabled' : '' }}
                                                            class="btn btn-lg btn-bd-primary new-main-button {{ $paid ? 'buttonPaided' : '' }}">
                                                        {{ $paid ? 'Оплачено' : 'Оплатить' }}
                                                    </button>
                                                </form>
                                            @else
                                                <button type="button" disabled class="btn btn-lg btn-bd-primary new-main-button {{ $paid ? 'buttonPaided' : '' }}">
                                                    {{ $paid ? 'Оплачено' : 'Оплатить' }}
                                                </button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @include('includes.dashboard_team_switcher')

        {{--Сезоны--}}
        <div class="row seasons">
            <div class="col-12">

                <div class="season season-2026" id="season-2026">
                    <div class="header-season">Сезон 2025 - 2026 <i class="fa fa-chevron-up"></i><span
                                class="display-none from">2025</span><span class="display-none to">2026</span></div>
                    <span class="is_credit">Имеется просроченная задолженность в размере <span
                                class="is_credit_value">0</span> руб.</span>
                    <span class="display-none1 total-summ"></span>
                    <div class="row justify-content-center align-items-center container" data-season="2026"></div>
                </div>

                <div class="season season-2025" id="season-2025">
                    <div class="header-season">Сезон 2024 - 2025 <i class="fa fa-chevron-up"></i><span
                                class="display-none from">2024</span><span class="display-none to">2025</span></div>
                    <span class="is_credit">Имеется просроченная задолженность в размере <span
                                class="is_credit_value">0</span> руб.</span>
                    <span class="display-none1 total-summ"></span>
                    <div class="row justify-content-center align-items-center container" data-season="2025"></div>
                </div>

                <div class="season season-2024" id="season-2024">
                    <div class="header-season">Сезон 2023 - 2024 <i class="fa fa-chevron-up"></i><span
                                class="display-none from">2023</span><span class="display-none to">2024</span></div>
                    <span class="is_credit">Имеется просроченная задолженность в размере <span
                                class="is_credit_value">0</span> руб.</span>
                    <span class="display-none1 total-summ"></span>
                    <div class="row justify-content-center align-items-center container" data-season="2024"></div>
                </div>

                <div class="season season-2023" id="season-2023">
                    <div class="header-season">Сезон 2022 - 2023 <i class="fa fa-chevron-up"></i><span
                                class="display-none from">2022</span><span class="display-none to">2023</span></div>
                    <span class="is_credit">Имеется просроченная задолженность в размере <span
                                class="is_credit_value">0</span> руб.</span>
                    <div class="row justify-content-center align-items-center container" data-season="2023"></div>
                </div>

                <div class="season season-2022" id="season-2022">
                    <div class="header-season">Сезон 2021 - 2022 <i class="fa fa-chevron-up"></i></div>
                    <span class="is_credit">Имеется просроченная задолженность в размере <span
                                class="is_credit_value">0</span> руб.</span>
                    <div class="row justify-content-center align-items-center container" data-season="2022"></div>
                </div>
            </div>
        </div>

    </div>

@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            window.Laravel = {
                csrfToken: '{{ csrf_token() }}',
                paymentUrl: '{{ route('payment') }}'
            };

            let currentUserName = "{{$curUser->name}}";
            let currentUserRole = "{{$curUser->role}}";
            // Глобальная переменная для хранения данных расписания юзера из AJAX
            var globalScheduleData = [];
            // передача расписания юзера для календаря
            var scheduleUser = {!! json_encode($scheduleUserArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!};
            updateGlobalScheduleData(scheduleUser);
            var userPriceAll = {!! json_encode($userPriceArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!};
            var userPrice = userPriceAll;
            @cannot('users.view')
            var dashboardTeams = {!! json_encode(
                $curUser->teams->count() >= 2
                    ? $curUser->teams->map(fn ($team) => ['id' => (int) $team->id, 'title' => (string) $team->title])->values()
                    : [],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) !!};
            @else
            var dashboardTeams = [];
            @endcannot
            var dashboardStudentId = {{ (int) $curUser->id }};
            var dashboardTeamStorageKey = 'dashboard_active_team_id_' + dashboardStudentId;

            function refreshPrice() {
                document.querySelectorAll('.price-value').forEach(function (element) {
                    element.textContent = '0';
                });
                document.querySelectorAll('.new-main-button-wrap button').forEach(function (button) {
                    button.classList.remove('buttonPaided');
                });
            }

            function escapeHtml(text) {
                return String(text)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function getDashboardActiveTeamId() {
                if (!dashboardTeams.length) {
                    return null;
                }

                const stored = sessionStorage.getItem(dashboardTeamStorageKey);
                if (stored && dashboardTeams.some(function (team) {
                    return String(team.id) === String(stored);
                })) {
                    return Number(stored);
                }

                return Number(dashboardTeams[0].id);
            }

            function filterUserPriceByTeam(teamId) {
                if (!teamId) {
                    return userPriceAll;
                }

                return userPriceAll.filter(function (item) {
                    return Number(item.team_id) === Number(teamId);
                });
            }

            function updateDashboardGroupLabel(teamId) {
                const label = document.querySelector('.group-value[data-multi-team="1"]');
                if (!label || !dashboardTeams.length) {
                    return;
                }

                label.innerHTML = dashboardTeams.map(function (team, index) {
                    const title = escapeHtml(team.title);
                    const part = Number(team.id) === Number(teamId)
                        ? '<strong>' + title + '</strong>'
                        : title;
                    return index === 0 ? part : ', ' + part;
                }).join('');
            }

            function applyDashboardTeamContext(teamId, persist) {
                if (!dashboardTeams.length) {
                    return;
                }

                if (persist) {
                    sessionStorage.setItem(dashboardTeamStorageKey, String(teamId));
                }

                const select = document.getElementById('dashboard-active-team');
                if (select) {
                    select.value = String(teamId);
                }

                updateDashboardGroupLabel(teamId);
                refreshPrice();
                userPrice = filterUserPriceByTeam(teamId);
                apendPrice(userPrice);
                showSessons();
                apendCreditTotalSumm();
                apendCreditTotalSummtoNotice();
                openFirstSeason();
            }

            function initDashboardTeamSwitcher() {
                if (!dashboardTeams.length) {
                    return;
                }

                const teamId = getDashboardActiveTeamId();
                applyDashboardTeamContext(teamId, false);

                $('#dashboard-active-team').on('change', function () {
                    applyDashboardTeamContext(Number(this.value), true);
                    disabledPaymentForm(currentUserRole);
                });
            }

            // закрытие плашки с задолженностью у юзера
            function closeNotice() {
                var $closeButton = $('.credit-notice .close');
                if ($closeButton.length > 0) { // Проверяем, что элемент существует
                    $closeButton.on('click', function () {
                        $('.credit-notice').hide();
                    });
                }
            }

            // Показывать плашку с задолженностью юзеру
            function showCreditNotice() {
                let creditNotice = document.querySelector(".credit-notice");
                let creditNoticeSumElement = document.querySelector(".credit-notice .summ");

                // Проверяем, что элемент уведомления и элемент суммы существуют
                if (creditNotice && creditNoticeSumElement) {
                    const creditNoticeSum = creditNoticeSumElement.textContent;
                    // При необходимости можно привести к числовому типу
                    if (parseFloat(creditNoticeSum) > 0) {
                        creditNotice.style.display = 'block';
                    }
                }
            }

            function convertStringToDate(dateStr) {
                const months = {
                    "Январь": 0,
                    "Февраль": 1,
                    "Март": 2,
                    "Апрель": 3,
                    "Май": 4,
                    "Июнь": 5,
                    "Июль": 6,
                    "Август": 7,
                    "Сентябрь": 8,
                    "Октябрь": 9,
                    "Ноябрь": 10,
                    "Декабрь": 11
                };

                const [monthName, year] = dateStr.split(' ');
                const month = months[monthName];

                if (month === undefined || isNaN(year)) {
                    throw new Error('Некорректный формат даты. Ожидается формат "Месяц Год".');
                }

                return new Date(year, month);
            }

            // Добавление сумм с задолженностями в плашки над сезонами и в общую плашку
            function apendCreditTotalSummtoNotice() {
                const seasons = document.querySelectorAll('.season');
                let totalSumAllSeasons = 0;
                const monthsInRussian = ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];

                const currentDate = new Date();
                const currentMonth = monthsInRussian[currentDate.getMonth()];
                const currentYear = currentDate.getFullYear();
                const currentFormatedDate = `${currentMonth} ${currentYear}`;

                // Перебираем каждый сезон
                seasons.forEach(function (season) {
                    let seasonOnlyYear = season.id.match(/\d+/)[0];

                    let totalSum = 0;

                    // Ищем все контейнеры с классом border_price внутри текущего сезона
                    const priceContainers = season.querySelectorAll('.border_price');

                    // Перебираем все контейнеры с ценами
                    priceContainers.forEach(function (container) {

                        // Находим кнопку внутри контейнера
                        const button = container.querySelector('button.new-main-button');
                        const date = container.querySelector('.new-price-description').textContent;

                        // const month = parseFloat(container.querySelector('.new-price-description').textContent);
                        const parts = date.split(' ');
                        const seasonOnlyMonth = parts[0]; // "Апрель"
                        const seasonOnlyYear = parts[1];  // "2022"

                        currentFormatedDatetoDate = convertStringToDate(currentFormatedDate)
                        FormatedToDate = convertStringToDate(date);
                        if (FormatedToDate >= currentFormatedDatetoDate) {
                            return
                        }
                        // Проверяем, если кнопка называется "Оплатить" и не отключена
                        if (button && button.textContent.trim() === 'Оплатить' && !button.disabled) {
                            // Получаем значение из price-value
                            const priceValue = parseFloat(container.querySelector('.price-value').textContent.trim());

                            // Добавляем значение к общей сумме для этого сезона

                            totalSum += priceValue;
                        }
                    });

                    // Обновляем значение в is_credit_value для текущего сезона
                    const creditValueField = season.querySelector('.is_credit_value');
                    const creditValueWrap = season.querySelector('.is_credit')

                    creditValueField.textContent = totalSum;

                    if (totalSum == 0) {
                        creditValueWrap.classList.add('visibility-hidden');
                    } else {
                        creditValueWrap.classList.remove('visibility-hidden');
                    }

                    totalSumAllSeasons += totalSum;
                });

                // Обновляем notice с суммой долга
                const creditNoticeSumm = document.querySelector('.credit-notice .summ');
                // if (totalSumAllSeasons) {
                if (creditNoticeSumm && totalSumAllSeasons) {

                    creditNoticeSumm.textContent = totalSumAllSeasons;
                }

            }

            function disabledPaymentForm(role) {
                @cannot('paying.classes')

                // Получаем все формы на странице
                const forms = document.querySelectorAll('.seasons form');

// Перебираем каждую форму и отключаем её
                forms.forEach((form) => {
                    form.addEventListener('submit', (event) => {
                        event.preventDefault(); // Отменяем отправку формы
                    });

                    // Отключаем кнопку отправки, если она есть
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true; // Делаем кнопку неактивной
                    }

                    // Добавляем визуальные эффекты, чтобы показать, что форма отключена
                    form.style.opacity = '0.5';
                    form.style.pointerEvents = 'none';
                });
                @endcan
            }

            // AJAX User
            $('#single-select-user').change(function () {
                let userName = $(this).val();


                const selectedOption = this.options[this.selectedIndex];
                const userId = selectedOption.getAttribute('data-user-id');

                if (!userId) {
                    console.log('Ошибка: идентификатор пользователя не найден.');
                    // return;
                }

                $.ajax({
                    url: '/get-user-details',
                    type: 'GET',
                    data: {
                        // userName: userName,
                        userId: userId,
                        // inputDate: inputDate,
                    },

                    success: function (response) {
                        if (response.success) {
                            let user = response.user;
                            let userTeam = response.userTeam;
                            let userTeamsLabel = response.userTeamsLabel;
                            let userPrice = response.userPrice;
                            let scheduleUser = response.scheduleUser;
                            // let inputDate = response.inputDate;
                            let team = response.team;
                            let formattedBirthday = response.formattedBirthday;

                            let userFieldValues = response.userFieldValues;
                            let userFields = response.userFields;

                            //Сброс всех значений цен до нуля
                            refreshPrice();
                            function apendNameToUser2() {
                                if (user.name) {
                                    $('.name-value').html(user.name);
                                } else $('.name-value').html("-");
                            }

                            function apendNameToUser() {
                                const display =
                                    (user?.full_name || '').trim() ||                                   // из аксессора "Фамилия Имя"
                                    [user?.lastname, user?.name].filter(Boolean).join(' ').trim() ||    // фолбэк
                                    '-';

                                $('.name-value').text(display); // безопаснее, чем .html()
                            }


                            // Вставка почты
                            function apendEmailToUser() {
                                if (user.email) {
                                    $('.email-value').html(user.email);
                                } else $('.email-value').html("-");
                            }

                            // Вставка дня рождения
                            function apendBirthdayToUser() {
                                if (formattedBirthday) {
                                    $('.birthday-value').html(formattedBirthday);
                                } else $('.birthday-value').html("-");

                            }

                            // Вставка кастомных полей
                            function apendUserFieldValues(userFieldValues) {

                                // Очищаем значения перед заполнением
                                const fields = document.querySelectorAll('.fields-title');
                                fields.forEach(field => {
                                    const valueElement = field.querySelector('.fields-value');
                                    if (valueElement) {
                                        valueElement.textContent = '-';
                                    }
                                });

                                if (userFieldValues) {
                                    const fields = document.querySelectorAll('.fields-title');
                                    fields.forEach(field => {
                                        const id = field.getAttribute('data-id');
                                        if (userFieldValues[id]) {
                                            const valueElement = field.querySelector('.fields-value');
                                            valueElement.textContent = userFieldValues[id];
                                        }
                                    });
                                }
                            }

                            // Вставка аватарки юзеру
                            const baseUrl = "{{ asset('storage/avatars') }}"; // даст полный путь к /storage/avatars
                            const defaultAvatar = "{{ asset('img/default-avatar.png') }}";

                            function apendImageToUser() {
                                const $img = $('.avatar .avatar-clip img');

                                if (user.image_crop) {
                                    $img.attr('src', baseUrl + '/' + user.image_crop)
                                        .attr('alt', user.name ?? 'avatar');
                                } else {
                                    $img.attr('src', defaultAvatar)
                                        .attr('alt', 'avatar');
                                }
                            }


                            // Вставка большой  аватарки юзеру
                            setZoomImageFromUser(response.user);
                            //Вставка data-id в кнопку добавления аватарки (для добавления чужих аватаров)
                            setSelectedUserContext(response.user); // <- это ключевое, сюда кладём data-id
                            // Вставим data-id в кнопку удаления аватарки (для удаления чужих аватаров)
                            $('.js-delete-photo').attr('data-id', response.user.id);
                            //отключение кнопки "открыть фото" где нет фото
                            setOpenPhotoVisibilityByUser(response.user);


                            // Вставка счетчика тренировок юзеру
                            function apendTrainingCountToUser() {
                                $('.personal-data-value .count-training').html(123);
                            }

                            // Отображение заголовка расписания
                            function showHeaderShedule() {
                                let headerShedule = document.querySelector('.header-shedule');
                                headerShedule.classList.remove('display-none');
                            }

                            // Добавление название группы юзеру
                            function apendTeamNameToUser() {
                                const label = userTeamsLabel || (userTeam ? userTeam.title : null);
                                $('.group-value').html(label || '-');
                            }

                            //отключение форм для юзеров и суперюзеров
                            function disabledPaymentForm(role) {
                                if (role == "admin" || role == "superadmin") {
                                    // Получаем все формы на странице
                                    const forms = document.querySelectorAll('form');

                                    // Перебираем каждую форму и отключаем её
                                    forms.forEach((form) => {
                                        form.addEventListener('submit', (event) => {
                                            event.preventDefault(); // Отменяем отправку формы
                                        });

                                        // Отключаем кнопку отправки, если она есть
                                        const submitButton = form.querySelector('button[type="submit"]');
                                        if (submitButton) {
                                            submitButton.disabled = true; // Делаем кнопку неактивной
                                        }

                                        // Добавляем визуальные эффекты, чтобы показать, что форма отключена
                                        form.style.opacity = '0.5';
                                        form.style.pointerEvents = 'none';
                                    });
                                }
                            }

                            showHeaderShedule();
                            refreshPrice();
                            apendPrice(userPrice);
                            showSessons();
                            apendCreditTotalSumm();
                            apendTeamNameToUser();
                            apendBirthdayToUser();
                            apendNameToUser();
                            apendEmailToUser();
                            apendImageToUser();
                            apendTrainingCountToUser();
                            updateGlobalScheduleData(scheduleUser);
                            setBackgroundToCalendar(globalScheduleData);
                            createCalendar();
                            openFirstSeason();
                            disabledPaymentForm(currentUserRole);
                            apendUserFieldValues(userFieldValues);

                        } else {
                            $('#user-details').html('<p>' + response.message + '</p>');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.log(error);
                    }
                });
            });

            // AJAX Team
            $('#single-select-team').change(function () {
                let teamName = $(this).val();
                let userName = $('#single-select-user').val();
                // Получаем выбранный option и извлекаем teamId из data-атрибута
                const selectedOption = this.options[this.selectedIndex];
                const teamId = selectedOption.getAttribute('data-team-id');

                function initializeSelect2() {
                    $('#single-select-user').select2({
                        theme: "bootstrap-5",
                        width: '100%',
                        placeholder: $('#single-select-user').data('placeholder'),
                        language: @include('partials.select2.ru'),
                        templateResult: formatUserOption,
                        templateSelection: formatUserOption // Применяем кастомный шаблон для отображения выбранного элемента
                    });
                }

                function formatUserOption(user) {
                    if (!user.id) {
                        return user.text; // Возвращаем текст для пустой опции (например, placeholder)
                    }

                    // Проверяем наличие команды у пользователя
                    let hasTeam = $(user.element).data('team');

                    let $userOption = $('<span></span>').text(user.text);

                    // Если у пользователя нет команды, применяем красный цвет
                    if (!hasTeam) {
                        $userOption.css('color', '#f3a12b');
                    }

                    return $userOption;
                }

                $.ajax({
                    url: '/get-team-details',
                    type: 'GET',
                    data: {
                        teamName: teamName,
                        userName: userName,
                        teamId: teamId,
                    },

                    success: function (response) {
                        if (response.success) {
                            let team = response.team;
                            let teamWeekDayId = response.teamWeekDayId;
                            let usersTeam = response.usersTeam;
                            let userWithoutTeam = response.userWithoutTeam;
                            // let inputDate = response.inputDate;
                            let user = response.user;
                            // let weekdays = document.querySelectorAll('.weekday-checkbox .form-check');
                            let usersTeamWithUnteamUsers = userWithoutTeam.concat(usersTeam);

                            // Новое изменение состава
                            function newUpdateSelectUsers() {

                                // Очищаем текущий список
                                $('#single-select-user').empty();

                                // Добавляем пустой элемент
                                $('#single-select-user').append('<option></option>');

                                // Счетчик для нумерации пользователей
                                let counter = 1;

                                // Проходим по каждому пользователю и добавляем опцию в select

                                let userList;
                                if (team == "Без групппы") {
                                    userList = userWithoutTeam;

                                } else if (team != null) {
                                    userList = usersTeamWithUnteamUsers;
                                } else {
                                    userList = usersTeam;
                                }


                                userList.forEach(function (user) {
                                    const fullName =
                                        (user?.full_name && String(user.full_name).trim()) ||               // "Фамилия Имя" из бэка
                                        [ (user?.lastname || '').trim(), (user?.name || '').trim() ]        // фолбэк
                                            .filter(Boolean)
                                            .join(' ')
                                            .trim();

                                    const option = $('<option></option>')
                                    // .val(user.id) // предпочтительно хранить id
                                        .val(user.name)
                                        .attr('label', fullName || '-')
                                        .attr('data-team', (user.teams && user.teams.length > 0) ? 'true' : 'false')
                                        .attr('data-user-id', user.id)
                                        .text(counter + '. ' + (fullName || '-'));

                                    $('#single-select-user').append(option);
                                    counter++;
                                });



                                // Инициализируем Select2 с кастомными шаблонами
                                initializeSelect2();
                            }

                            newUpdateSelectUsers();

                        }
                    },
                    error: function (xhr, status, error) {
                    }
                });
            });

            // Создание сезонов
            function createSeasons() {

                const csrfToken = window.Laravel.csrfToken;
                const paymentUrl = window.Laravel.paymentUrl;

// Данные для каждого месяца
                const months = [
                    'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june',
                    'july', 'august'
                ];
                const monthsRu = [
                    'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                    'Июль', 'Август'
                ];
                const calendarMonthByKey = [9, 10, 11, 12, 1, 2, 3, 4, 5, 6, 7, 8];
                var season2024;

                document.querySelectorAll('.season .container').forEach(container => {
                    var season = container.dataset.season;
                    // console.log('Season:', season); // Отладка: Выводим текущий сезон
                    // Цикл по месяцам
                    for (const [key, month] of months.entries()) {
                        // console.log('Processing month:', month); // Отладка: Выводим текущий месяц
                        const div = document.createElement('div');
                        div.className = `border_price col-3 ${month}`;

                        var displaySeason;
                        if (monthsRu[key] == "Сентябрь" ||
                            monthsRu[key] == "Октябрь" ||
                            monthsRu[key] == "Ноябрь" ||
                            monthsRu[key] == "Декабрь"
                        ) {
                            displaySeason = season - 1;
                        } else {
                            displaySeason = season;
                        }

                        const paymentDate = `${monthsRu[key]} ${displaySeason}`;
                        const year = Number(displaySeason);
                        const calMonth = calendarMonthByKey[key];
                        const formatedPaymentDate = `${year}-${String(calMonth).padStart(2, '0')}-01`;

                        var outSum = 22;
                        div.innerHTML = `
            <div class="row align-items-center justify-content-center">
                <span class="price-value">0</span>
                <span class="hide-currency">₽</span>
            </div>
            <div class="row justify-content-center align-items-center">
                <div class="new-price-description">${monthsRu[key]} ${displaySeason}</div>
            </div>
            <div class="row new-main-button-wrap">
                <div class="justify-content-center align-items-center">

                    <form action="${paymentUrl}" method="POST">
                        <input type="hidden" name="_token" value="${csrfToken}">
                        <input type="hidden" name="paymentDate" value="${paymentDate}">
                        <input type="hidden" name="formatedPaymentDate" value="${formatedPaymentDate}">
                        <input class="team-id" type="hidden" name="team_id" value="">
                        <input class="outSum" type="hidden" name="outSum" value="">
                        <button type="submit" disabled class="btn btn-lg btn-bd-primary new-main-button">Оплатить</button>
                    </form>

                </div>
            </div>
        `;

                        // Добавляем созданный div в контейнер
                        container.appendChild(div);
                    }
                });


            }

// Открытие, закрытие сезонов при клике
            function clickSeason() {

                var chevronDownIcons = document.querySelectorAll('.header-season');
                // Добавляем обработчик события клика для каждого элемента
                chevronDownIcons.forEach(function (icon) {
                    icon.addEventListener('click', function () {
                        // Изменяем класс элемента в зависимости от текущего класса
                        if (icon.children[0].classList.contains('fa-chevron-down')) {
                            icon.children[0].classList.remove('fa-chevron-down');
                            icon.children[0].classList.add('fa-chevron-up');
                        } else {
                            icon.children[0].classList.remove('fa-chevron-up');
                            icon.children[0].classList.add('fa-chevron-down');
                        }

                        // Находим соответствующий элемент "season"
                        var seasonElement = icon.children[0].closest('.season');

                        // Находим все элементы с классом "border_price col-3 february" внутри "season"
                        var borderPriceElements = seasonElement.querySelectorAll('.border_price');

                        // Скрываем/показываем все элементы в зависимости от текущего класса "fa-chevron-down/fa-chevron-up"
                        borderPriceElements.forEach(function (borderPrice) {
                            if (icon.children[0].classList.contains('fa-chevron-up')) {
                                borderPrice.style.display = 'none';
                            } else {
                                borderPrice.style.display = 'block   ';
                            }
                        });
                    });
                });
            }

//Скрытие всех сезонов при загрузке страницы
            function hideAllSeason() {
                var seasons = document.querySelectorAll('.season');
                for (var i = 0; i < seasons.length; i++) {
                    seasons[i].classList.add('display-none');
                }
            }

            // Добавление Select2 к Юзерам
            function addSelect2ToUser() {
                $('#single-select-user').select2({
                    theme: "bootstrap-5",
                    width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
                    placeholder: $(this).data('placeholder'),
                    language: @include('partials.select2.ru'),
                });
            }

            // Добавление Select2 к Группам
            function addSelect2ToTeam() {
                $('#single-select-team').select2({
                    theme: "bootstrap-5",
                    width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
                    placeholder: $(this).data('placeholder'),
                    language: @include('partials.select2.ru'),
                });
            }

            // Скрипт открытия верхнего сезона
            function openFirstSeason() {
                // Найти все элементы с классом 'season'
                const seasons = document.querySelectorAll(".season");

                // Если найден хотя бы один сезон
                if (seasons.length > 0) {
                    // Открыть верхний сезон (первый в списке)
                    const topSeason = seasons[0];

                    // Найти кнопку для открытия сезона
                    const header = topSeason.querySelector(".header-season");

                    // Проверить, не открыт ли сезон уже
                    const isOpen = topSeason.querySelector(".fa-chevron-up") !== null;
                    // console.log(isOpen);
                    // Если кнопка найдена и сезон не открыт, кликнуть на неё
                    if (header && isOpen) {
                        header.click();
                    }
                }
            }

// Скрываем/отображаем сезоны, в которых не установленны/установлены суммы.
            function showSessons() {
                var seasons = document.querySelectorAll('.season');
                var borderPrice = {};
                var totalSumm = {};

                for (var i = 0; i < seasons.length; i++) {
                    var seasonId = seasons[i].id;

                    // Initialize the arrays for each season
                    borderPrice[seasonId] = [];
                    totalSumm[seasonId] = 0;

                    var borderPrices = seasons[i].querySelectorAll('.border_price');
                    var priceValues = seasons[i].querySelectorAll('.price-value');

                    for (var j = 0; j < borderPrices.length; j++) {
                        // Store the border price (if needed)
                        borderPrice[seasonId].push(borderPrices[j]);
                        totalSumm[seasonId] += Number(priceValues[j].textContent);
                    }

                    seasons[i].classList.remove('display-none');
                    if (totalSumm[seasonId] === 0) {
                        seasons[i].classList.add('display-none');
                    }
                    // отобразить последний сезон
                    seasons[0].classList.remove('display-none')
                }
            }

            //Поиск и установка соответствующих установленных цен на странице
            function apendPrice(userPrice) {
                if (!userPrice) {
                    return;
                }

                const paymentUrl = window.Laravel.paymentUrl;
                const csrfToken = window.Laravel.csrfToken;

                const formatMonth = (dateString) => {
                    const date = new Date(dateString);
                    const monthNames = [
                        "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь",
                        "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"
                    ];
                    const month = monthNames[date.getMonth()];
                    const year = date.getFullYear();
                    return `${month} ${year}`;
                };

                const teamTitle = (item) => {
                    if (item.team && item.team.title) {
                        return String(item.team.title).trim();
                    }
                    return '';
                };

                const borderPrices = document.querySelectorAll('.border_price');

                for (let i = 0; i < borderPrices.length; i++) {
                    const borderPrice = borderPrices[i];
                    const newPriceDescription = borderPrice.querySelector('.new-price-description');
                    const buttonWrap = borderPrice.querySelector('.new-main-button-wrap .justify-content-center');

                    if (!newPriceDescription || !buttonWrap) {
                        continue;
                    }

                    const monthText = newPriceDescription.textContent.trim();
                    const matchedAll = userPrice.filter(item => formatMonth(item.new_month) === monthText);

                    if (matchedAll.length === 0) {
                        const priceValue = borderPrice.querySelector('.price-value');
                        const button = borderPrice.querySelector('.new-main-button');
                        if (priceValue) {
                            priceValue.textContent = '0';
                        }
                        if (button) {
                            button.textContent = 'Оплатить';
                            button.setAttribute('disabled', 'disabled');
                            button.classList.remove('buttonPaided');
                        }
                        continue;
                    }

                    if (matchedAll.length === 1) {
                        const matchedData = matchedAll[0];
                        const priceValue = borderPrice.querySelector('.price-value');
                        const outSum = borderPrice.querySelector('.outSum');
                        const teamIdInput = borderPrice.querySelector('.team-id');
                        const button = borderPrice.querySelector('.new-main-button');

                        if (priceValue) {
                            priceValue.textContent = matchedData.price > 0 ? matchedData.price : '0';
                        }
                        if (outSum) {
                            outSum.value = matchedData.price > 0 ? matchedData.price : '';
                        }
                        if (teamIdInput && matchedData.team_id) {
                            teamIdInput.value = matchedData.team_id;
                        }

                        buttonWrap.innerHTML = `
                    <form action="${paymentUrl}" method="POST">
                        <input type="hidden" name="_token" value="${csrfToken}">
                        <input type="hidden" name="paymentDate" value="${monthText}">
                        <input type="hidden" name="formatedPaymentDate" value="${matchedData.new_month}">
                        <input type="hidden" name="team_id" value="${matchedData.team_id || ''}">
                        <input class="outSum" type="hidden" name="outSum" value="${matchedData.price > 0 ? matchedData.price : ''}">
                        <button type="submit" class="btn btn-lg btn-bd-primary new-main-button">Оплатить</button>
                    </form>`;

                        const newButton = borderPrice.querySelector('.new-main-button');
                        if (newButton) {
                            newButton.textContent = 'Оплатить';
                            if (matchedData.is_paid) {
                                newButton.textContent = 'Оплачено';
                                newButton.setAttribute('disabled', 'disabled');
                                newButton.classList.add('buttonPaided');
                            } else if (matchedData.price == 0) {
                                newButton.setAttribute('disabled', 'disabled');
                            } else {
                                newButton.removeAttribute('disabled');
                                newButton.classList.remove('buttonPaided');
                            }
                        }
                        continue;
                    }

                    let total = 0;
                    let formsHtml = '';
                    matchedAll.forEach(function (matchedData) {
                        total += Number(matchedData.price) || 0;
                        const title = teamTitle(matchedData);
                        const label = title !== '' ? ('Оплатить (' + title + ')') : 'Оплатить';
                        const paid = !!matchedData.is_paid;
                        const disabled = paid || Number(matchedData.price) <= 0;
                        formsHtml += `
                    <form action="${paymentUrl}" method="POST" class="mb-1">
                        <input type="hidden" name="_token" value="${csrfToken}">
                        <input type="hidden" name="paymentDate" value="${monthText}">
                        <input type="hidden" name="formatedPaymentDate" value="${matchedData.new_month}">
                        <input type="hidden" name="team_id" value="${matchedData.team_id || ''}">
                        <input type="hidden" name="outSum" value="${matchedData.price > 0 ? matchedData.price : ''}">
                        <button type="submit" class="btn btn-sm btn-bd-primary new-main-button w-100"
                            ${disabled ? 'disabled' : ''} ${paid ? 'class="btn btn-sm btn-bd-primary new-main-button w-100 buttonPaided"' : ''}>
                            ${paid ? 'Оплачено' + (title ? ' (' + title + ')' : '') : label}
                        </button>
                    </form>`;
                    });

                    const priceValue = borderPrice.querySelector('.price-value');
                    if (priceValue) {
                        priceValue.textContent = total > 0 ? total : '0';
                    }

                    buttonWrap.innerHTML = formsHtml;
                }
            }

            // Закрашивание ячеек в календаре
            function setBackgroundToCalendar(scheduleUser) {
                if (scheduleUser) {
                    scheduleUser.forEach(entry => {
                        // Формат даты в dataset.date в элементе календаря совпадает с форматом в объекте scheduleUser
                        const dayElement = document.querySelector(`[data-date="${entry.date}"]`);

                        if (dayElement) {
                            // dayElement.classList.add('scheduled-day');  // Добавляем общий класс для всех дней с расписанием

                            // Закрашиваем в зависимости от состояния оплаты
                            if (entry.is_enabled) {
                                dayElement.classList.add('is_enabled');
                            }
                            if (entry.is_hospital) {
                                dayElement.classList.add('is_hospital');
                            }
                        }
                    });
                }
            }

            // Функция для обновления глобальной переменной после получения данных через AJAX
            function updateGlobalScheduleData(scheduleUser) {
                if (scheduleUser) {
                    globalScheduleData = scheduleUser;
                }
            }

            //Создание календаря
            function createCalendar() {
                let currentYear = new Date().getFullYear();
                let currentMonth = new Date().getMonth();

                // Создаем календарь для текущего месяца
                function createCalendar(year, month) {
                    const firstDayOfMonth = new Date(year, month, 1).getDay();
                    const lastDateOfMonth = new Date(year, month + 1, 0).getDate();
                    const calendarTitle = document.getElementById('calendar-title');
                    const daysContainer = document.getElementById('days');
                    const monthNames = [
                        'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                        'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
                    ];

                    // Заполняем заголовок календаря
                    calendarTitle.textContent = `${monthNames[month]} ${year}`;

                    // Очищаем предыдущие дни
                    daysContainer.innerHTML = '';

                    // Определяем, с какого дня недели начинается месяц (с учётом того, что воскресенье в JS это 0)
                    const adjustedFirstDay = (firstDayOfMonth === 0) ? 6 : firstDayOfMonth - 1;

                    // Заполняем дни до первого числа месяца пустыми блоками
                    for (let i = 0; i < adjustedFirstDay; i++) {
                        const emptyDiv = document.createElement('div');
                        daysContainer.appendChild(emptyDiv);
                    }

                    // Заполняем календарь числами текущего месяца
                    for (let i = 1; i <= lastDateOfMonth; i++) {
                        const dayDiv = document.createElement('div');
                        dayDiv.textContent = i;
                        dayDiv.dataset.date = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                        daysContainer.appendChild(dayDiv);
                    }
                    // Закрашивание сегодняшней даты
                    highlightToday();

                    setBackgroundToCalendar(globalScheduleData);

                }

                //Предыдущие месяц
                function preMonth() {
                    document.getElementById('prev-month').addEventListener('click', () => {
                        currentMonth--;
                        if (currentMonth < 0) {
                            currentMonth = 11;
                            currentYear--;
                        }
                        createCalendar(currentYear, currentMonth);
                    });
                }

                // Следующий месяц
                function nextMonth() {
                    document.getElementById('next-month').addEventListener('click', () => {
                        currentMonth++;
                        if (currentMonth > 11) {
                            currentMonth = 0;
                            currentYear++;
                        }
                        createCalendar(currentYear, currentMonth);
                    });
                }

                // Обработчик кликов по пунктам контекстного меню
                function clickContextmenu() {
                    document.getElementById('context-menu').addEventListener('click', function (event) {
                        const action = event.target.dataset.action;
                        const date = this.dataset.date;
                        let userName = $('#single-select-user').val();

                        if (action && date && userName) {
                            sendActionRequest(date, action, userName);
                        }
                        this.style.display = 'none';
                    });
                }

                // Вызов функции для закрашивания сегодняшней даты
                function highlightToday() {
                    // Получаем сегодняшнюю дату
                    const today = new Date();
                    const formattedToday = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

                    // Ищем элемент календаря, соответствующий сегодняшней дате
                    const todayElement = document.querySelector(`[data-date="${formattedToday}"]`);

                    if (todayElement) {
                        // Добавляем класс для закрашивания сегодняшней даты
                        todayElement.classList.add('today');
                    }
                }

                preMonth();
                nextMonth();
                createCalendar(currentYear, currentMonth);
            }

            //Расчет сумм долга за сезон и добавление долга в шапку сезона
            function apendCreditTotalSumm() {
                // Ищем все контейнеры с классом season
                const seasons = document.querySelectorAll('.season');

                // Перебираем каждый сезон
                seasons.forEach(function (season) {
                    let totalSum = 0;

                    // Ищем все контейнеры с классом border_price внутри текущего сезона
                    const priceContainers = season.querySelectorAll('.border_price');

                    // Перебираем все контейнеры с ценами
                    priceContainers.forEach(function (container) {
                        // Находим кнопку внутри контейнера
                        const button = container.querySelector('button.new-main-button');

                        // Проверяем, если кнопка называется "Оплатить" и не отключена
                        if (button && button.textContent.trim() === 'Оплатить' && !button.disabled) {
                            // Получаем значение из price-value
                            const priceValue = parseFloat(container.querySelector('.price-value').textContent.trim());
                            // Добавляем значение к общей сумме для этого сезона
                            totalSum += priceValue;
                        } else {
                        }
                    });

                    // Обновляем значение в is_credit_value для текущего сезона
                    const creditValueField = season.querySelector('.is_credit_value');
                    const creditValueWrap = season.querySelector('.is_credit')

                    creditValueField.textContent = totalSum;

                    if (totalSum == 0) {
                        creditValueWrap.classList.add('visibility-hidden');
                    } else {
                        creditValueWrap.classList.remove('visibility-hidden');
                    }
                });
            }

            createSeasons();    //Создание сезонов
            clickSeason();       //Измерение иконок при клике
            hideAllSeason();     //Скрытие всех сезонов при загрузке страницы
            createCalendar();
            if (dashboardTeams.length) {
                initDashboardTeamSwitcher();
            } else {
                apendPrice(userPrice);
                showSessons();
                apendCreditTotalSumm();
                apendCreditTotalSummtoNotice();
                openFirstSeason();
            }
            closeNotice();
            showCreditNotice();
            disabledPaymentForm(currentUserRole);
            addSelect2ToUser();
            addSelect2ToTeam();

        });
    </script>
@endsection