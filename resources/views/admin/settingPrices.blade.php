{{--@extends('layouts.main2')--}}
@extends('layouts.admin2')
@section('content')


    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="{{ asset('js/my-croppie.js') }}"></script>
    <script src="{{ asset('js/settings-prices-ajax.js') }}"></script>

    <div class="  main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3 text-start">Установка цен</h4>
        <div class="container setting-price-wrap">
            <hr>

            <div class="buttons text-start">
                <button type="button" class="btn btn-primary" id="logs" data-bs-toggle="modal"
                        data-bs-target="#historyModal">История изменений
                </button>
                <hr>
            </div>
            <div class="row justify-content-md-center">
                {{--<div id='selectDate' class="col-10">--}}
                <div id='selectDate' class="selectDate">
                    <select class="form-select" id="single-select-date" data-placeholder="Дата">

                        @if($currentDateString)
                            <option>{{ $currentDateString }}</option>
                        @endif

                    </select>
                    <script>
                        const selectElement = document.getElementById('single-select-date');
                        const startYear = 2023;
                        const startMonth = 8; // Июнь (месяцы в JavaScript считаются с 0: 0 = январь, 1 = февраль и т.д.)
                        let CountMonths = function () { // fix переписать для автоматизации
                            let currentYear = new Date().getFullYear();
                            if (currentYear == 2024) {
                                return 24;
                            } else if (currentYear == 2025) {
                                return 36;
                            }
                        }

                        function capitalizeFirstLetter(string) {
                            return string.charAt(0).toUpperCase() + string.slice(1);
                        }

                        for (let i = 0; i < CountMonths(); i++) {
                            const optionDate = new Date(startYear, startMonth + i, 1);
                            let monthYear = optionDate.toLocaleString('ru-RU', {
                                month: 'long',
                                year: 'numeric'
                            }).replace(' г.', '');
                            monthYear = capitalizeFirstLetter(monthYear);
                            const option = document.createElement('option');
                            option.value = monthYear;
                            option.textContent = monthYear;
                            selectElement.appendChild(option);
                        }

                    </script>

                </div>
            </div>
            <div class="row justify-content-center  mt-3 " id='wrap-bars'>
                {{--<div id='left_bar' class="col col-lg-5 mb-3">--}}
                <div id='left_bar' class="col-12 col-lg-5 mb-3 ">
                    <button id="set-price-all-teams" class="btn btn-primary btn-setting-prices mb-3 mt-3 set-price-all-teams">Применить
                    </button>

                    @if(isset($teamPrices) && count($teamPrices) > 0)
                        @for($i = 0; $i < count($teamPrices); $i++)
                            @if(isset($allTeams[$i])) <!-- Добавляем проверку на существование индекса $i -->
                            <div id="{{ $teamPrices[$i]->team_id }}" class="row mb-2 wrap-team ">

                                {{--<div class=" col-lg-12  d-flex justify-content-between flex-wrap ">--}}

                                <div class="team-name col-3">{{ $allTeams[$i]->title }}</div>
                                <div class="team-price col-4">
                                    <input class="" type="number" value="{{ $teamPrices[$i]->price }}">
                                </div>
                                <div class="team-buttons col-5 d-flex ">
                                    <input class="ok btn btn-primary mr-2" type="button" value="ok" id="">
                                    <input class="detail btn btn-primary" type="button" value="Подробно" id="">
                                </div>
                            </div>
                            @endif
                        @endfor
                    @endif

                </div>
                <div class="col-md-auto"></div>
                <div id='right_bar' class="col-12 col-lg-5">
                    <button disabled id="set-price-all-users" class="btn btn-primary btn-setting-prices mb-3 mt-3 set-price-all-users">
                        Применить
                    </button>
                    <div class="row mb-2 wrap-users"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно логов -->
    @include('includes.logModal')

    <!-- Модальное окно подтверждения удаления -->
    @include('includes.modal.confirmDeleteModal')

    <!-- Модальное окно успешного обновления данных -->
    @include('includes.modal.successModal')

    <!-- Модальное окно ошибки -->
    @include('includes.modal.errorModal')


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Установка CSRF-токена для всех AJAX-запросов
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            let usersPrice = []; // Объявляем переменную вне всех функций, чтобы она была доступна глобально

            // AJAX ПОДРОБНО. Получение списка пользователей
            const detailButtons = document.querySelectorAll('.detail');
            for (let i = 0; i < detailButtons.length; i++) {
                let button = detailButtons[i];
                button.addEventListener('click', function () {

                    // Сначала удаляем класс 'action-button' у всех кнопок
                    detailButtons.forEach(btn => btn.classList.remove('action-button'));

                    // Добавляем класс 'action-button' только к текущей нажатой кнопке
                    button.classList.add('action-button');


                    const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;
                    document.querySelector('#right_bar .btn-setting-prices').setAttribute('disabled', 'disabled');
                    // Находим родительский div (родителя с классом 'wrap-team')
                    const parentDiv = this.closest('.wrap-team');
                    // Выводим id родительского div в консоль
                    if (parentDiv) {
                        $.ajax({
                            url: '/get-team-price',
                            method: 'POST',
                            contentType: 'application/json', // Указываем тип контента JSON
                            data:  JSON.stringify({
                                teamId: parentDiv.id,
                                selectedDate: selectedDate
                            }),
                            success: function (response) {
                                if (response.success) {
                                    //Обновление списка пользователей справа
                                    let updateUserListRightBar = function () {
                                        usersPrice = response.usersPrice;
                                        var usersTeam = response.usersTeam;


                                        let rightBar = $('.wrap-users');
                                        rightBar.empty();
                                        for (i = 0; i < usersPrice.length; i++) {
                                            let userTeam = usersTeam.find(team => team.id === usersPrice[i].user_id); // Находим соответствующего пользователя в usersTeam

                                            let checkClass = usersPrice[i].is_paid ? '' : 'display-none';
                                            let inputDisabled = usersPrice[i].is_paid ? 'disabled' : '';

                                            let userBlock = `
                <div class="row mb-2">
                    <div id="${userTeam ? userTeam.id : 'Имя не найдено'}" class="user-name col-6 text-start">  ${userTeam ? userTeam.name : 'Имя не найдено'}</div>
                    <div class="user-price col-4"><input class="" type="number" value=${usersPrice[i].price} ${inputDisabled}></div>
                    <div class="check col-2"><span class="fa fa-check ${checkClass} green-check" aria-hidden="true"></span></div>
                </div>
            `;
                                            rightBar.append(userBlock); // Добавляем каждый блок с пользователем внутрь right_bar
                                            document.querySelector('#right_bar .btn-setting-prices').removeAttribute('disabled');
                                        }

                                    }
                                    updateUserListRightBar();
                                }
                            },
                            error: function (xhr, status, error) {
                                console.error('Ошибка: ' + error);
                                console.error('Статус: ' + status);
                                console.dir(xhr);
                            }
                        });
                    }
                });
            }

            // AJAX SELECT DATE. Обработчик изменения даты
            $('#single-select-date').on('change', function () {
                document.querySelector('#set-price-all-teams').setAttribute('disabled', 'disabled');
                let selectedMonth = $(this).val();
                $.ajax({
                    url: '/update-date',
                    method: 'GET',
                    data: {
                        month: selectedMonth,
                        // _token: "{{ csrf_token() }}"
                        // _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (response) {

                        document.querySelector('#set-price-all-teams').removeAttribute('disabled');
                        // location.reload();
                        let cleanUrl = window.location.href.split('?')[0];
                        window.location.href = cleanUrl;
                    },
                    error: function (xhr, status, error) {
                        console.log('Error:', error);
                    }
                });
            });

            //AJAX Кнопка ОК. Установка цен группе и юзерам.
            const okButtons = document.querySelectorAll('.ok');
            for (let i = 0; i < okButtons.length; i++) {
                let button = okButtons[i];
                button.addEventListener('click', function () {
                    const parentDiv = this.closest('.wrap-team');
                    const teamPrice = parentDiv.querySelector('.team-price input').value;
                    const teamPriceInput = parentDiv.querySelector('.team-price input');
                    const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;
                    teamPriceInput.classList.remove('animated-input');

                    if (parentDiv) {

                        $.ajax({
                            url: '/set-team-price',
                            method: 'POST',
                            contentType: 'application/json', // Указываем тип контента JSON

                            data: JSON.stringify({
                                teamId: parentDiv.id,
                                teamPrice: teamPrice,
                                selectedDate: selectedDate,
                            }),

                            success: function (response) {
                                if (response.success) {

                                    teamPriceInput.classList.add('animated-input');

                                    var teamPrice = response.teamPrice;
                                    var selectedDate = response.selectedDate;
                                    var teamId = response.teamId;

                                }
                            }
                        });
                    }
                });
            }


            // Вызов модалки установки цен СЛЕВА
            $(document).on('click', '.set-price-all-teams', function () {
                setPriceAllTeams();
            });

            //ПРИМЕНИТЬ СЛЕВА. Установка цен всем группам
            function setPriceAllTeams() {
                showConfirmDeleteModal(
                    "Установка цена всем группам",
                    "Вы уверены, что хотите применить изменения?",
                    function() {
                        // ----
                        // Выполняем действия только после подтверждения
                        const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;

                        // Выключаем кнопку
                        document.querySelector('#set-price-all-teams').setAttribute('disabled', 'disabled');

                        // Получаем массив команд и их цен
                        let teamsData = [];
                        document.querySelectorAll('.wrap-team').forEach(function (teamElement) {
                            let teamName = teamElement.querySelector('.team-name').textContent.trim();
                            let teamPrice = teamElement.querySelector('.team-price input').value;
                            teamsData.push({
                                name: teamName,
                                price: parseFloat(teamPrice)
                            });
                        });


                        if (teamsData.length === 0) {
                            console.error('Teams data is empty');
                            return;
                        }

                        $.ajax({
                            url: '/set-price-all-teams',
                            method: 'POST',  // Меняем метод на POST
                            contentType: 'application/json', // Указываем тип контента JSON
                            data: JSON.stringify({ // Передаём данные в теле запроса в формате JSON
                                selectedDate: selectedDate,
                                teamsData: teamsData
                            }),
                            beforeSend: function() {
                                console.log('Sending data:', {
                                    selectedDate: selectedDate,
                                    teamsData: teamsData
                                });
                            },
                            success: function (response) {
                                let cleanUrl = window.location.href.split('?')[0];
                                window.location.href = cleanUrl;
                            },
                            error: function (xhr, status, error) {
                                console.log('Error:', error);
                            }
                        });
                        // ----
                    }
                );
            }

            // ПРИМЕНИТЬ СПРАВА. Установка цен всем ученикам
            $('#set-price-all-users').on('click', function () {
                // var token = '{{ csrf_token() }}';

                // Выбранная дата
                const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;

                // Функция для обновления цен пользователей
                let updateUsersPrice = function (usersPrice) {
                    const userRows = document.querySelectorAll('.wrap-users .mb-2');
                    for (let i = 0; i < usersPrice.length; i++) {
                        for (let j = 0; j < userRows.length; j++) {
                            let userId = userRows[j].querySelector('.user-name').getAttribute('id');
                            let price = userRows[j].querySelector('.user-price input').value;
                            if (usersPrice[i].user_id == userId) {
                                // Обновляем цену пользователя с фронта в usersPrice
                                usersPrice[i].price = price;
                            }
                        }
                    }
                    return usersPrice;
                };

                // Обновляем данные о ценах пользователей
                usersPrice = updateUsersPrice(usersPrice);

                $.ajax({
                    url: '/set-price-all-users',
                    method: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    data: JSON.stringify({
                        selectedDate: selectedDate,
                        usersPrice: usersPrice,
                    }),
                    success: function (response) {
                        usersPrice = response.usersPrice;

                        document.querySelector('#set-price-all-users').removeAttribute('disabled');

                        // Добавляем юзеров с ценами в колонку справа
                        let apendUserWithPrice = function () {
                            let rightBar = $('.wrap-users');
                            rightBar.empty();
                            for (let i = 0; i < usersPrice.length; i++) {
                                let isPaidClass = usersPrice[i].is_paid == 0 ? 'display-none' : '';
                                let inputClass = usersPrice[i].is_paid == 0 ? 'animated-input' : '';
                                let inputDisabled = usersPrice[i].is_paid == 1 ? 'disabled' : '';

                                let userBlock = `
                        <div class="row mb-2">
                            <div id="${usersPrice[i].user_id}" class="user-name col-6">  ${usersPrice[i].name}   </div>
                            <div class="user-price col-4">
                                <input class="${inputClass}" type="number" value="${usersPrice[i].price}" ${inputDisabled}>
                            </div>
                            <div class="check col-2">
                                <span class="fa fa-check ${isPaidClass} green-check" aria-hidden="true"></span>
                            </div>
                        </div>
                    `;
                                rightBar.append(userBlock);
                                document.querySelector('#right_bar .btn-setting-prices').removeAttribute('disabled');
                            }
                        };
                        apendUserWithPrice();
                    },
                    error: function (xhr, status, error) {
                        console.log('Error:', error);
                    }
                });
            });

            <!-- Модальное окно логов -->
            $(document).ready(function () {
                showLogModal("{{ route('logs.data.settingPrice') }}"); // Здесь можно динамически передать route
            })
        });
    </script>
@endsection