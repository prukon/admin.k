
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

    // AJAX ПРИМЕНИТЬ СЛЕВА. Установка цен всем группам
    // Начало изменения
    let applyButton = document.getElementById('set-price-all-teams');
    let confirmButton = document.getElementById('confirmApply');

    // Отключаем немедленное выполнение действия при клике на "Применить1"
    applyButton.addEventListener('click', function (event) {
        event.preventDefault();  // Останавливаем стандартное выполнение
        $('#confirmModal').modal('show');  // Показываем модалку
    });

    // Обрабатываем нажатие на кнопку "Да" в модальном окне
    confirmButton.addEventListener('click', function () {
        $('#confirmModal').modal('hide');  // Закрываем модалку

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

        $.ajax({
            url: '/set-price-all-teams',
            method: 'GET',
            data: {
                selectedDate: selectedDate,
                teamsData: JSON.stringify(teamsData) // Конвертируем массив объектов в строку JSON
            },
            success: function (response) {
                let cleanUrl = window.location.href.split('?')[0];
                window.location.href = cleanUrl;
            },
            error: function (xhr, status, error) {
                console.log('Error:', error);
            }
        });
    });
    // Конец изменения


    //AJAX ПРИМЕНИТЬ СПРАВА.Установка цен всем ученикам
    // $('#set-price-all-users').on('click', function () {
    //     var token = '{{ csrf_token() }}';
    //
    //     // Выбранная дата
    //     const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;
    //     //Выключаем кнопку
    //     document.querySelector('#set-price-all-users').setAttribute('disabled', 'disabled');
    //
    //     let updateUsersPrice = function (usersPrice) {
    //         const userRows = document.querySelectorAll('.wrap-users .row.mb-2');
    //         for (i = 0; i < usersPrice.length; i++) {
    //
    //             for (j = 0; j < userRows.length; j++) {
    //                 let userId = userRows[j].querySelector('.user-name').getAttribute('id');
    //                 let price = userRows[j].querySelector('.user-price input').value;
    //                 if (usersPrice[i].user_id == userId) {
    //
    //                     // Обновляем цену пользователя с фронта в usersPrice
    //                     usersPrice[i].price = price;
    //                 }
    //             }
    //         }
    //         return usersPrice;
    //     };
    //
    //     usersPrice = updateUsersPrice(usersPrice);
    //
    //     console.log("usersPrice:");
    //     console.log(usersPrice);
    //     $.ajax({
    //         url: '/set-price-all-users',
    //         method: 'GET',
    //         // method: 'POST',
    //         data: {
    //             selectedDate: selectedDate,
    //             usersPrice: JSON.stringify(usersPrice), // Конвертируем массив объектов в строку JSON
    //         },
    //         success: function (response) {
    //             // usersData = response.usersData;
    //             usersPrice = response.usersPrice;
    //
    //             document.querySelector('#set-price-all-users').removeAttribute('disabled');
    //
    //             // Добавляем юзеров с ценами в колонку справа
    //             let apendUserWithPrice = function () {
    //                 let rightBar = $('.wrap-users');
    //                 rightBar.empty();
    //
    //                 for (let i = 0; i < usersPrice.length; i++) {
    //
    //                     let isPaidClass = usersPrice[i].is_paid == 0 ? 'display-none' : '';
    //                     let inputClass = usersPrice[i].is_paid == 0 ? 'animated-input' : '';
    //                     let inputDisabled = usersPrice[i].is_paid == 1 ? 'disabled' : '';
    //
    //                     let userBlock = `
    //     <div class="row mb-2">
    //         <div id="${usersPrice[i].user_id}" class="user-name col-6">  ${usersPrice[i].name}   </div>
    //         <div class="user-price col-4">
    //             <input class="${inputClass}" type="number" value="${usersPrice[i].price}" ${inputDisabled}>
    //         </div>
    //         <div class="check col-2">
    //             <span class="fa fa-check ${isPaidClass} green-check" aria-hidden="true"></span>
    //         </div>
    //     </div>
    // `;
    //
    //                     rightBar.append(userBlock); // Добавляем каждый блок с пользователем внутрь right_bar
    //                     document.querySelector('#right_bar .btn-setting-prices').removeAttribute('disabled');
    //                 }
    //             }
    //             apendUserWithPrice();
    //
    //         },
    //         error: function (xhr, status, error) {
    //             console.log('Error:', error);
    //         }
    //     });
    //
    // });

    // $('#set-price-all-users').on('click', function () {
    //     var token = '{{ csrf_token() }}';
    //
    //     // Выбранная дата
    //     const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;
    //     // Выключаем кнопку
    //     document.querySelector('#set-price-all-users').setAttribute('disabled', 'disabled');
    //
    //     let updateUsersPrice = function (usersPrice) {
    //         const userRows = document.querySelectorAll('.wrap-users .row.mb-2');
    //         for (i = 0; i < usersPrice.length; i++) {
    //
    //             for (j = 0; j < userRows.length; j++) {
    //                 let userId = userRows[j].querySelector('.user-name').getAttribute('id');
    //                 let price = userRows[j].querySelector('.user-price input').value;
    //                 if (usersPrice[i].user_id == userId) {
    //
    //                     // Обновляем цену пользователя с фронта в usersPrice
    //                     usersPrice[i].price = price;
    //                 }
    //             }
    //         }
    //         return usersPrice;
    //     };
    //
    //     usersPrice = updateUsersPrice(usersPrice);
    //
    //     // console.log("usersPrice:");
    //     // console.log(usersPrice);
    //
    //
    //     console.log("Selected Date:", selectedDate);
    //     console.log("Users Price:", usersPrice);  // Проверьте, что это массив объектов
    //     // $.ajax({
    //     //     url: '/set-price-all-users',
    //     //     method: 'POST',
    //     //
    //     //     data: {
    //     //         selectedDate: selectedDate,
    //     //         usersPrice: JSON.stringify(usersPrice), // Конвертируем массив объектов в строку JSON
    //     //     },
    //
    //         $.ajax({
    //             url: '/set-price-all-users',
    //             method: 'POST',
    //             contentType: 'application/json', // Указываем тип данных JSON
    //             dataType: 'json', // Ожидаемый ответ от сервера в формате JSON
    //             data: JSON.stringify({
    //                 selectedDate: selectedDate,
    //                 usersPrice: usersPrice,
    //             }),
    //
    //
    //
    //         success: function (response) {
    //             usersPrice = response.usersPrice;
    //
    //             document.querySelector('#set-price-all-users').removeAttribute('disabled');
    //
    //             // Добавляем юзеров с ценами в колонку справа
    //             let apendUserWithPrice = function () {
    //                 let rightBar = $('.wrap-users');
    //                 rightBar.empty();
    //
    //                 for (let i = 0; i < usersPrice.length; i++) {
    //
    //                     let isPaidClass = usersPrice[i].is_paid == 0 ? 'display-none' : '';
    //                     let inputClass = usersPrice[i].is_paid == 0 ? 'animated-input' : '';
    //                     let inputDisabled = usersPrice[i].is_paid == 1 ? 'disabled' : '';
    //
    //                     let userBlock = `
    //                     <div class="row mb-2">
    //                         <div id="${usersPrice[i].user_id}" class="user-name col-6">  ${usersPrice[i].name}   </div>
    //                         <div class="user-price col-4">
    //                             <input class="${inputClass}" type="number" value="${usersPrice[i].price}" ${inputDisabled}>
    //                         </div>
    //                         <div class="check col-2">
    //                             <span class="fa fa-check ${isPaidClass} green-check" aria-hidden="true"></span>
    //                         </div>
    //                     </div>
    //                 `;
    //
    //                     rightBar.append(userBlock);
    //                     document.querySelector('#right_bar .btn-setting-prices').removeAttribute('disabled');
    //                 }
    //             }
    //             apendUserWithPrice();
    //
    //         },
    //         error: function (xhr, status, error) {
    //             console.log('Error:', error);
    //         }
    //     });
    //
    // });

    $('#set-price-all-users').on('click', function () {
        var token = '{{ csrf_token() }}';

        // Выбранная дата
        const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;

        // Функция для обновления цен пользователей
        let updateUsersPrice = function (usersPrice) {
            const userRows = document.querySelectorAll('.wrap-users .row.mb-2');
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

        console.log("Selected Date:", selectedDate);
        console.log("Users Price:", usersPrice);

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


});

