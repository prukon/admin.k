@extends('layouts.admin2')
@section('content')

    {{--    <meta name="csrf-token" content="{{ csrf_token() }}">--}}
    <script>
        window.Laravel = {
            csrfToken: '{{ csrf_token() }}',
            paymentUrl: '{{ route('payment') }}'
        };
    </script>
    <script src="{{ asset('js/dashboard-ajax.js') }}"></script>


    <script>
        // Передача данных текущего пользователя из Blade в JavaScript
                {{--let currentUserName = "{{ auth()->user()->name }}";--}}
                {{--let currentUserRole = "{{ auth()->user()->role }}";--}}
        let currentUserName = "{{$curUser->name}}";
        let currentUserRole = "{{$curUser->role}}";
    </script>


    <div class=" col-md-12 main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3 text-start">Консоль</h4>
        <div>


            @can('view', auth()->user())
                <h5 class="choose-user-header text-start">Выбор ученика:</h5>

                {{--Выбор ученика, группы, кнопка установить--}}
                <div class="row choose-user">


                    <div class="col-md-3 col-12 mb-3 team-select text-start">
                        <select class="form-select text-start" id="single-select-team" data-placeholder="Группа">
                            <option value="all">Все группы</option>
                            <option value="withoutTeam">Без группы</option>

                            <option></option>
                            @foreach($allTeams as $index => $team)
                                <option value="{{ $team->title }}" label="{{ $team->label }}">{{ $index + 1 }}
                                    . {{ $team->title }}</option>
                            @endforeach
                        </select>
                        <i class="fa-thin fa-calendar-lines"></i>
                    </div>

                    <div class="col-md-3 col-12 mb-3 user-select">
                        <select class="form-select" id="single-select-user" data-placeholder="ФИО">
                            <option value="">Выберите пользователя</option>
                            @foreach($allUsersSelect as $index => $user)
                                <option value="{{ $user->name }}" label="{{ $user->label }}">{{ $index + 1 }}
                                    . {{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 col-12 mb-3  date-input">
                        <div class="input-group flex-nowrap">
                            <input type="text" id="inlineCalendar" class="form-control" placeholder="01.02.2024"
                                   aria-label="Имя пользователя" aria-describedby="addon-wrapping">
                            <span class="input-group-text" id="addon-wrapping"><i class="fa-solid fa-calendar-days"></i></span>
                        </div>
                    </div>

                    <div class="col-md-3 col-12 mb-3  btn-choose-user">
                        <button type="button" disabled id="setup-btn" class="btn btn-primary">Установить</button>
                    </div>
                </div>

                {{--            Чекбоксы дней недели--}}

                <div class="form-group text-start">
                    <label for="weekdays"> Расписание</label>
                    <div class="row weekday-checkbox">
                        {{--<div class=" col-12 d-flex justify-content-between flex-wrap ">--}}
                        <div class=" col-lg-12  d-flex justify-content-between flex-wrap ">


                            @foreach($weekdays as $weekday)
                                <div class="form-check form-check-inline weekday-disabled">
                                    <input
                                            @if($curTeam)
                                            @foreach($curTeam->weekdays as $teamWeekday)
                                            {{$weekday->id === $teamWeekday->id ? 'checked' : ''}}
                                            @endforeach
                                            @endif
                                            {{--                                            {{$weekday->id === $teamWeekday->id ? 'checked' : ''}}--}}
                                            class="form-check-input " type="checkbox" id="{{$weekday->titleEn}}"
                                            value="{{$weekday->titleEn}}">
                                    <label class="form-check-label label-day"
                                           for="{{$weekday->titleEn}}">{{$weekday->title}}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="row weekday-checkbox">
                    <div class="col-12" id="weekdayContainer"></div>
                </div>
            @endcan

            {{--Аватарка и личные данные--}}
            <div class="row personal-data">
                <div class="col-5 col-lg-3 avatar-wrap">
                    <div class="avatar_wrapper d-flex align-items-center justify-content-center">
                        <img id='confirm-img'
                             @if ($curUser->image_crop)
                             src="{{ asset('storage/avatars/' . $curUser->image_crop) }}"
                             alt="{{ $curUser->image_crop }}"
                             @else  src="/img/default.png" alt=""
                                @endif
                        >
                    </div>
                    {{--                    <div class='container-form'>--}}
                    {{--                        <input id='selectedFile' class="display-none" type='file' accept=".png, .jpg, .jpeg, .svg">--}}
                    {{--                        <button id="upload-photo" class="btn-primary btn">Выбрать фото...</button>--}}
                    {{--                    </div>--}}
                </div>
                <div class="col-7 col-lg-3 header-wrap">
                    <div class="personal-data-header">
                        <div class="name">Имя: <span class="name-value"> @if($curUser)
                                    {{$curUser->name}}
                                @else
                                    -
                                @endif </span></div>

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
                        <div class="group">Группа: <span class="group-value"> @if($curTeam)
                                    {{$curTeam->title}}
                                @else
                                    -
                                @endif </span></div>



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
                    <div class="mt-3">
                        <a href="/payment/club-fee">
                            <button type="button" id="club-fee" class="btn btn-primary">Клубный взнос</button>
                        </a>
                    </div>
                </div>
                <div class="col-12 col-lg-4 mt-3 mb-1 credit-notice  align-items-center justify-content-center text-center">
                    <i class="close fa-solid fa-circle-xmark"></i>
                    У вас образовалась задолженность в размере <span class="summ"></span> руб.
                </div>
            </div>
            {{--            <div class="notification-wrap">--}}
            {{--                <div class="notification">{{$textForUsers}}</div>--}}
            {{--            </div>--}}
            @if(!empty($textForUsers))
                <div class="notification-wrap">
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
                    {{--                    <div class="context-menu-item" data-action="add-payment">Добавление оплаты</div>--}}
                    {{--                    <div class="context-menu-item" data-action="remove-payment">Удаление оплаты</div>--}}
                </div>
            </div>


            {{--Сезоны--}}
            {{--            fix автоматизировать--}}
            <div class="row seasons">
                <div class="col-12">

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


            <script>
                document.addEventListener('DOMContentLoaded', function () {

                    // передача расписания юзера для календаря
                    var scheduleUser = {!! json_encode($scheduleUserArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!};
                    updateGlobalScheduleData(scheduleUser);
                    var userPrice = {!! json_encode($userPriceArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!};

                    // закрытие плашки с задолженностью у юзера
                    function closeNotice() {
                        document.querySelector('.credit-notice .close').addEventListener('click', function () {
                            document.querySelector('.credit-notice').style.display = 'none';
                        });

                        function showCreditNotice() {
                            let creditNotice = document.querySelector(".credit-notice");
                            const creditNoticeSum = document.querySelector(".credit-notice .summ").textContent;
                            if (creditNoticeSum > 0) {
                                creditNotice.style.display = 'block';
                            }
                        }

                    }

                    // Показывать плашку с задолженностью юзеру
                    function showCreditNotice() {
                        let creditNotice = document.querySelector(".credit-notice");
                        const creditNoticeSum = document.querySelector(".credit-notice .summ").textContent;
                        if (creditNoticeSum > 0) {
                            creditNotice.style.display = 'block';
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
                            // if (totalSum == 0) {
                            //     creditValueWrap.classList.add('display-none');
                            // } else {
                            //     creditValueWrap.classList.remove('display-none');
                            // }

                            if (totalSum == 0) {
                                creditValueWrap.classList.add('visibility-hidden');
                            } else {
                                creditValueWrap.classList.remove('visibility-hidden');
                            }

                            totalSumAllSeasons += totalSum;
                        });

                        // Обновляем notice с суммой долга
                        const creditNoticeSumm = document.querySelector('.credit-notice .summ');
                        creditNoticeSumm.textContent = totalSumAllSeasons;
                    }


                    function disabledPaymentForm(role) {
                        if (role == "admin" || role == "superadmin") {
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
                        }
                    }


                    createSeasons()     //Создание сезонов
                    clickSeason()       //Измерение иконок при клике
                    hideAllSeason()     //Скрытие всех сезонов при загрузке страницы
                    createCalendar();
                    apendPrice(userPrice);
                    showSessons();
                    apendCreditTotalSumm();
                    apendCreditTotalSummtoNotice();
                    openFirstSeason();
                    closeNotice();
                    showCreditNotice();
                    disabledPaymentForm(currentUserRole);


                });
            </script>

        </div>
    </div>



    {{--    Модалка загрузка аватарки--}}
    <div class="modal fade" id="uploadPhotoModal" tabindex="-1" role="dialog" aria-labelledby="uploadPhotoModalLabel"
         aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadPhotoModalLabel">Загрузка аватарки</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form id="uploadImageForm" enctype="multipart/form-data">
                    @csrf
                    <!-- Выбор файла -->
                        <input class="mb-3" type="file" id="upload" accept="image/*">

                        <!-- Контейнер для Croppie -->
                        <div id="upload-demo" style="width:300px;"></div>

                        <!-- Скрытое поле для сохранения имени пользователя -->
                        <input type="hidden" id="selectedUserName" name="userName" value="">

                        <!-- Скрытое поле для обрезанного изображения -->
                        <input type="hidden" id="croppedImage" name="croppedImage">

                        <!-- Кнопка для сохранения изображения -->
                        <button type="button" id="saveImageBtn" class="btn btn-primary">Загрузить</button>
                    </form>


                </div>
                {{--                <div class="modal-footer">--}}
                {{--                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>--}}
                {{--                    <button type="button" class="btn btn-primary" id="saveImageBtn">Загрузить</button>--}}
                {{--                </div>--}}
            </div>
        </div>
    </div>



    <!-- Модальное окно успешного обновления данных -->
    @include('includes.modal.successModal')

    <!-- Модальное окно ошибки -->
    @include('includes.modal.errorModal')
@endsection