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
                            var usersPrice = response.usersPrice;
                            var usersTeam = response.usersTeam;
                            let rightBar = $('.wrap-users');
                            rightBar.empty();

                            for (i = 0; i < usersPrice.length; i++) {
                                let userTeam = usersTeam.find(team => team.id === usersPrice[i].user_id); // Находим соответствующего пользователя в usersTeam
                                let userBlock = `
                <div class="row mb-2">
                    <div class="user-name col-6">  ${userTeam ? userTeam.name : 'Имя не найдено'}</div>
                    <div class="user-price col-4"><input class="" type="number" value=${usersPrice[i].price}></div>
                    <div class="check col-2"><span class="fa fa-check display-none green-check" aria-hidden="true"></span></div>
                </div>
            `;
                                rightBar.append(userBlock); // Добавляем каждый блок с пользователем внутрь right_bar
                                document.querySelector('#right_bar .btn-setting-prices').removeAttribute('disabled');


                            }

            //                 usersTeam.forEach(function (user) {
            //                     let userBlock = `
            //     <div class="row mb-2">
            //         <div class="user-name col-6">${user.name}</div>
            //         <div class="user-price col-4"><input class="" type="number" value="7050"></div>
            //         <div class="check col-2"><span class="fa fa-check display-none green-check" aria-hidden="true"></span></div>
            //     </div>
            // `;
            //                     rightBar.append(userBlock); // Добавляем каждый блок с пользователем внутрь right_bar
            //                     document.querySelector('#right_bar .btn-setting-prices').removeAttribute('disabled');
            //                 });
                            console.log(usersTeam);
                            console.log(usersPrice);
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
                // _token: '{{ csrf_token() }}'
            },
            success: function (response) {
                document.querySelector('#set-price-all-teams').removeAttribute('disabled');
                location.reload();
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
            const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;

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
                            var teamPrice = response.teamPrice;
                            var selectedDate = response.selectedDate;
                            var teamId = response.teamId;
                            // console.log(teamId);
                            // console.log(selectedDate);
                            // console.log(teamPrice);
                        }
                    }
                });
            }
        });
    }

    //AJAX ПРИМЕНИТЬ слева.Установка цен всем группам
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
                location.reload();
            },
            error: function (xhr, status, error) {
                console.log('Error:', error);
            }
        });
    });

    //AJAX ПРИМЕНИТЬ справа.Установка цен всем ученикам
    $('#set-price-all-users').on('click', function () {
        // Выбранная дата
        const selectedDate = document.getElementById('single-select-date').options[selectElement.selectedIndex].textContent;

        //Выключаем кнопку
        document.querySelector('#set-price-all-users').setAttribute('disabled', 'disabled');

        $.ajax({
            url: '/set-price-all-users',
            method: 'GET',
            data: {
                selectedDate: selectedDate,
                // teamsData: JSON.stringify(teamsData) // Конвертируем массив объектов в строку JSON
            },
            success: function (response) {
                document.querySelector('#set-price-all-users').removeAttribute('disabled');
                location.reload();
            },
            error: function (xhr, status, error) {
                console.log('Error:', error);
            }
        });
    });
 
});

