


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
                    data: JSON.stringify({
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

                                for (let i = 0; i < usersPrice.length; i++) {
                                    let userTeam = usersTeam.find(team => team.id === usersPrice[i].user_id);

                                    let checkClass = usersPrice[i].is_paid ? '' : 'display-none';
                                    let inputDisabled = usersPrice[i].is_paid ? 'disabled' : '';

                                    // Добавляем нумерацию: (i + 1) + '. ' + имя
                                    let userNameFormatted = (i + 1) + '. ' + (userTeam ? userTeam.name : 'Имя не найдено');

                                    let userBlock = `
                        <div class="row mb-2">
                            <div id="${userTeam ? userTeam.id : 'Имя не найдено'}" class="user-name col-6 text-start">${userNameFormatted}</div>
                            <div class="user-price col-4">
                                <input type="number" value="${usersPrice[i].price}" ${inputDisabled}>
                            </div>
                            <div class="check col-2">
                                <span class="fa fa-check ${checkClass} green-check" aria-hidden="true"></span>
                            </div>
                        </div>
                    `;
                                    rightBar.append(userBlock);
                                }

                                // Активируем кнопку после загрузки пользователей
                                document.querySelector('#right_bar .btn-setting-prices').removeAttribute('disabled');
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

    // Вешаем обработчики на кнопки с классом .ok
    const okButtons = document.querySelectorAll('.ok');
    for (let i = 0; i < okButtons.length; i++) {
        let button = okButtons[i];
        button.addEventListener('click', function () {
            const parentDiv = this.closest('.wrap-team');
            const teamPriceInput = parentDiv.querySelector('.team-price input');
            const teamPrice = teamPriceInput.value;
            // Ваш уже полученный select
            const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;

            // Убираем старый класс
            teamPriceInput.classList.remove('animated-input');

            // Если элемент есть, вызываем модалку и передаём логику в confirmCallback
            if (parentDiv) {
                showConfirmDeleteModal(
                    'Подтвердите действие',
                    'Вы действительно хотите установить цену для этой команды?',
                    function () {
                        // Весь AJAX-запрос исполняется внутри этого колбэка
                        $.ajax({
                            url: '/set-team-price',
                            method: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({
                                teamId: parentDiv.id,
                                teamPrice: teamPrice,
                                selectedDate: selectedDate,
                            }),
                            success: function (response) {
                                if (response.success) {
                                    // Возвращаем класс при успешном ответе
                                    teamPriceInput.classList.add('animated-input');

                                    // Можно использовать полученные данные из ответа
                                    var teamPrice = response.teamPrice;
                                    var selectedDate = response.selectedDate;
                                    var teamId = response.teamId;
                                    // Если нужно что-то сделать с этими данными — делайте тут
                                }
                            }
                        });
                    }
                );
            }
        });
    }



    //ПРИМЕНИТЬ СЛЕВА. Установка цен всем группам
    $('.set-price-all-teams').on('click', function () {
        showConfirmDeleteModal(
            "Установка цена всем группам",
            "Вы уверены, что хотите применить изменения?", function() {
                // ----
                // Выполняем действия только после подтверждения
                const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;

                // Выключаем кнопку
                document.querySelector('#set-price-all-teams').setAttribute('disabled', 'disabled');

                // Получаем массив команд и их цен
                let teamsData = [];
                document.querySelectorAll('.wrap-team').forEach(function (teamElement) {
                    let teamName = teamElement.querySelector('.team-name').textContent.trim();
                    let teamId = teamElement.id;
                    let teamPrice = teamElement.querySelector('.team-price input').value;
                    teamsData.push({
                        name: teamName,
                        price: parseFloat(teamPrice),
                        teamId: teamId
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
                    success: function (response) {
                        showSuccessModal("Установка цен всем группам", "Цены  всем группам успешно обновлены.", 1);
                    },
                    error: function (xhr, status, error) {
                        console.log('Error:', error);
                    }
                });

            }
        );
    });


    // ПРИМЕНИТЬ СПРАВА. Установка цен всем ученикам
    $('#set-price-all-users').on('click', function () {
        showConfirmDeleteModal(
            "Установка цен в одной группе",
            "Вы уверены, что хотите применить изменения?", function () {

        // Выбранная дата
        const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;

                console.log("до фунцкции");
                console.log(usersPrice);
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
            console.log("функция");
            console.log(usersPrice);
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

                showSuccessModal("Установка цен в одной группе", "Цены ученикам в выбранной группе успешно обновлены.");

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
            }
        );
    });
});
