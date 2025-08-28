@extends('layouts.admin2')
@section('content')

    <div class=" main-content">
        <h4 class="pt-3 text-start">Консоль</h4>
        @can('users-view')
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
                        @foreach($allUsersSelect as $index => $user)
                            <option value="{{ $user->name }}" label="{{ $user->label }}" data-user-id="{{ $user->id }}">
                                {{ $index + 1 }}. {{ $user->name }}
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
                @can('payment-clubfee')

                    <div class="mt-3">
                        <a href="/payment/club-fee">
                            <button type="button" id="club-fee" class="btn btn-primary">Клубный взнос</button>
                        </a>
                    </div>
                @endcan
            </div>

            @can('paying-classes')
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
            var userPrice = {!! json_encode($userPriceArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!};

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
                // if (totalSumAllSeasons) {
                if (creditNoticeSumm && totalSumAllSeasons) {

                    creditNoticeSumm.textContent = totalSumAllSeasons;
                }


            }

            function disabledPaymentForm(role) {
                @cannot('paying-classes')

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
                            let userPrice = response.userPrice;
                            let scheduleUser = response.scheduleUser;
                            // let inputDate = response.inputDate;
                            let team = response.team;
                            let formattedBirthday = response.formattedBirthday;

                            let userFieldValues = response.userFieldValues;
                            let userFields = response.userFields;

                            //Сброс всех значений цен до нуля
                            function refreshPrice() {
                                // Получаем все элементы с классом 'price-value' и устанавливаем значение '0'
                                document.querySelectorAll('.price-value').forEach(function (element) {
                                    element.textContent = '0';
                                });
                                // Получаем все кнопки внутри 'new-main-button-wrap' и удаляем все классы
                                document.querySelectorAll('.new-main-button-wrap button').forEach(function (button) {
                                    button.classList.remove('buttonPaided');
                                });
                            }

                            // Вставка имени
                            function apendNameToUser() {
                                if (user.name) {
                                    $('.name-value').html(user.name);
                                } else $('.name-value').html("-");
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
                                if (userTeam) {
                                    $('.group-value').html(userTeam.title);
                                } else
                                    $('.group-value').html('-');
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
                                    let option = $('<option></option>')
                                        .attr('value', user.name)
                                        .attr('label', user.label)
                                        .attr('data-team', user.team_id ? 'true' : 'false') // Проверяем наличие команды и добавляем data-атрибут
                                        .attr('data-user-id', user.id) // Добавляем id пользователя в DOM
                                        .text(counter + '. ' + user.name); // Добавляем нумерацию перед именем

                                    // Добавляем опцию в select
                                    $('#single-select-user').append(option);

                                    // Увеличиваем счетчик
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
                });
            }

            // Добавление Select2 к Группам
            function addSelect2ToTeam() {
                $('#single-select-team').select2({
                    theme: "bootstrap-5",
                    width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
                    placeholder: $(this).data('placeholder'),
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
                if (userPrice) {
                    for (j = 0; j < userPrice.length; j++) {

                        // Получаем все блоки с классом border_price
                        const borderPrices = document.querySelectorAll('.border_price');

                        // Проходим по каждому блоку
                        for (let i = 0; i < borderPrices.length; i++) {
                            const borderPrice = borderPrices[i];
                            const button = borderPrice.querySelector('.new-main-button');
                            button.setAttribute('disabled', 'disabled');

                            // Находим элемент с классом new-price-description внутри текущего блока
                            const newPriceDescription = borderPrice.querySelector('.new-price-description');

                            // Проверяем, есть ли такой элемент
                            if (newPriceDescription) {
                                // Получаем текст месяца из блока и убираем пробелы
                                const monthText = newPriceDescription.textContent.trim();

                                // Преобразуем дату из БД (new_month) в строку вида "Месяц ГГГГ" для сравнения
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

                                // Ищем объект в массиве, у которого преобразованная new_month совпадает с текстом месяца
                                const matchedData = userPrice.find(item => formatMonth(item.new_month) === monthText);

                                // Если найдено совпадение, обновляем цену
                                if (matchedData) {

                                    const priceValue = borderPrice.querySelector('.price-value');
                                    const outSum = borderPrice.querySelector('.outSum');

                                    if (priceValue) {
                                        if (matchedData.price > 0) {
                                            priceValue.textContent = matchedData.price;
                                            outSum.value = matchedData.price;
                                        }
                                    }

                                    // Получаем кнопку

                                    // Проверяем, если is_paid == true, меняем текст и делаем кнопку неактивной
                                    button.textContent = "Оплатить";

                                    if (matchedData.is_paid) {
                                        button.textContent = "Оплачено";
                                        button.setAttribute('disabled', 'disabled');
                                        button.classList.add('buttonPaided');
                                    } else {
                                        button.removeAttribute('disabled');
                                    }
                                    if (matchedData.price == 0) {
                                        button.setAttribute('disabled', 'disabled');
                                    }
                                }
                            }
                        }
                    }
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
            apendPrice(userPrice);
            showSessons();
            apendCreditTotalSumm();
            apendCreditTotalSummtoNotice();
            openFirstSeason();
            closeNotice();
            showCreditNotice();
            disabledPaymentForm(currentUserRole);
            addSelect2ToUser();
            addSelect2ToTeam();

        });
    </script>
@endsection