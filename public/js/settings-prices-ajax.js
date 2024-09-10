document.addEventListener('DOMContentLoaded', function () {


    // AJAX ПОДРОБНО. Получение списка пользователей
    const detailButtons = document.querySelectorAll('.detail');
    for (let i = 0; i < detailButtons.length; i++) {
        let button = detailButtons[i];
        button.addEventListener('click', function () {
            const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;
            document.querySelector('#right_bar .btn-setting-prices').setAttribute('disabled', 'disabled');
            // Находим родительский div (родителя с классом 'wrap-team')
            const parentDiv = this.closest('.wrap-team');
            // Выводим id родительского div в консоль
            if (parentDiv) {
                $.ajax({
                    url: '/get-team-price',
                    type: 'GET',
                    data: {
                        teamId: parentDiv.id,
                        selectedDate: selectedDate
                    },

                    success: function (response) {
                        if (response.success) {
                         //Обновление списка пользователей справа
                            let updateUserListRightBar = function () {
                             var usersPrice = response.usersPrice;
                             var usersTeam = response.usersTeam;
                             let rightBar = $('.wrap-users');
                             rightBar.empty();
                             for (i = 0; i < usersPrice.length; i++) {
                                 let userTeam = usersTeam.find(team => team.id === usersPrice[i].user_id); // Находим соответствующего пользователя в usersTeam

                                 let checkClass = usersPrice[i].is_paid ? '' : 'display-none';
                                 let inputDisabled = usersPrice[i].is_paid ? 'disabled' : '';

                                 let userBlock = `
                <div class="row mb-2">
                    <div id="${userTeam ? userTeam.id : 'Имя не найдено'}" class="user-name col-6">  ${userTeam ? userTeam.name : 'Имя не найдено'}</div>
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
        console.log(selectedMonth);
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
                    type: 'GET',
                    data: {
                        teamId: parentDiv.id,
                        teamPrice: teamPrice,
                        selectedDate: selectedDate,
                    },

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

    //AJAX ПРИМЕНИТЬ СЛЕВА.Установка цен всем группам
    $('#set-price-all-teams').on('click', function () {
        // Выбранная дата
        const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;

        //Выключаем кнопку
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

        $.ajax({
            url: '/set-price-all-teams',
            method: 'GET',
            data: {
                selectedDate: selectedDate,
                teamsData: JSON.stringify(teamsData) // Конвертируем массив объектов в строку JSON
            },
            success: function (response) {
                // location.reload();
                let cleanUrl = window.location.href.split('?')[0];
                window.location.href = cleanUrl;
            },
            error: function (xhr, status, error) {
                console.log('Error:', error);
            }
        });
    });

    //AJAX ПРИМЕНИТЬ СПРАВА.Установка цен всем ученикам
    $('#set-price-all-users').on('click', function () {
        // Выбранная дата
        const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;
        //Выключаем кнопку
        document.querySelector('#set-price-all-users').setAttribute('disabled', 'disabled');


       // Собираем юзеров в массив для отправки
        var usersData = [];
        let createUserData = function (usersData) {
            // Получаем все элементы с классом 'row mb-2' внутри 'wrap-users'
            const userRows = document.querySelectorAll('.wrap-users .row.mb-2');

            // Создаем массив для хранения данных

            // Проходим по каждому пользователю и собираем его данные
            userRows.forEach(row => {
                let userId = row.querySelector('.user-name').getAttribute('id');
                let price = row.querySelector('.user-price input').value;
                let name = row.querySelector('.user-name').textContent;

                // Добавляем объект с данными пользователя в массив
                usersData.push({
                    id: userId,
                    price: price,
                    name: name,
                });
            });
            return usersData;
        }
            createUserData(usersData);

        $.ajax({
            url: '/set-price-all-users',
            method: 'GET',
            data: {
                selectedDate: selectedDate,
                usersData: JSON.stringify(usersData) // Конвертируем массив объектов в строку JSON
            },
            success: function (response) {
                var usersData = response.usersData;

                document.querySelector('#set-price-all-users').removeAttribute('disabled');

                // Добавляем юзеров с ценами в колонку справа
                let apendUserWithPrice = function () {
                    let rightBar = $('.wrap-users');
                    rightBar.empty();
                    for (i = 0; i < usersData.length; i++) {
                        let userBlock = `
                <div class="row mb-2">
                    <div id="${usersData[i].id}" class="user-name col-6">  ${usersData[i].name}   </div>
                    <div class="user-price col-4">
<!--                    <input class="" type="number" value=${usersData[i].price}>-->
                      <input class="animated-input" type="number" value="${usersData[i].price}">

                    </div>
                    
                    <div class="check col-2"><span class="fa fa-check display-none green-check" aria-hidden="true"></span></div>
                </div>
            `;
                        rightBar.append(userBlock); // Добавляем каждый блок с пользователем внутрь right_bar
                        document.querySelector('#right_bar .btn-setting-prices').removeAttribute('disabled');
                    }
                }
                apendUserWithPrice();

            },
            error: function (xhr, status, error) {
                console.log('Error:', error);
            }
        });
    });

});

