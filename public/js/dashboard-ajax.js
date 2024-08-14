document.addEventListener('DOMContentLoaded', function () {

    // AJAX User
    $('#single-select-user').change(function () {
        var userName = $(this).val();
        $.ajax({
            url: '/get-user-details',
            type: 'GET',
            data: {name: userName},

            success: function (response) {
                if (response.success) {
                    var user = response.data;
                    var userTeam = response.userTeam;
                    var userPrice = response.userPrice;

                    // Добавляем суммы в месяца
                    let addPriceToSeasons = function () {

                        let refreshPrice = function () {
                            // Получаем все элементы с классом 'price-value' и устанавливаем значение '0'
                            document.querySelectorAll('.price-value').forEach(function (element) {
                                element.textContent = '0';
                            });
                            // Получаем все кнопки внутри 'new-main-button-wrap' и удаляем все классы
                            document.querySelectorAll('.new-main-button-wrap button').forEach(function (button) {
                                button.classList.remove('buttonPaided');
                            });
                        }
                        refreshPrice();



                        if (userPrice) {
                            for (j = 0; j < userPrice.length; j++) {

                                // Получаем все блоки с классом border_price
                                const borderPrices = document.querySelectorAll('.border_price');

// Проходим по каждому блоку
                                for (let i = 0; i < borderPrices.length; i++) {
                                    const borderPrice = borderPrices[i];

                                    // Находим элемент с классом new-price-description внутри текущего блока
                                    const newPriceDescription = borderPrice.querySelector('.new-price-description');

                                    // Проверяем, есть ли такой элемент
                                    if (newPriceDescription) {
                                        // Получаем текст месяца из блока и убираем пробелы
                                        const monthText = newPriceDescription.textContent.trim();

                                        // Ищем объект в массиве, у которого month совпадает с текстом месяца
                                        const matchedData = userPrice.find(item => item.month === monthText);

                                        // Если найдено совпадение, обновляем цену
                                        if (matchedData) {
                                            // console.log("matchedData:");
                                            // console.log(matchedData);
                                            //
                                            // console.log("matchedData.price > 0:");
                                            // console.log(matchedData.price > 0);

                                            const priceValue = borderPrice.querySelector('.price-value');
                                            if (priceValue) {
                                                if (matchedData.price > 0) {
                                                    priceValue.textContent = matchedData.price;
                                                }
                                            }
                                            // borderPrice.querySelector('.new-main-button').removeAttribute('disabled');
                                            // Получаем кнопку
                                            const button = borderPrice.querySelector('.new-main-button');

                                            // Проверяем, если is_paid == true, меняем текст и делаем кнопку неактивной
                                            button.textContent = "Оплатить";
                                            // button.style.backgroundColor = 'white';
                                            // button.removeAttribute('disabled');

                                            console.log("matchedData:");
                                            console.log(matchedData);

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
                    // Скрываем месяца, которых нет
                    let showSessons = function () {
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

                                // Accumulate the total sum of price values
                                totalSumm[seasonId] += Number(priceValues[j].textContent);
                            }

                            // Check if totalSumm is 0 and add class 'display-none' if true
                            seasons[i].classList.remove('display-none');
                            if (totalSumm[seasonId] === 0) {
                                seasons[i].classList.add('display-none');
                            }
                            // отобразить последний сезон
                            seasons[0].classList.remove('display-none')

                        }
                    }
                    let showHeaderShedule = function () {
                        let headerShedule = document.querySelector('.header-shedule');
                        headerShedule.classList.remove('display-none');
                    }
                    let addTeamNameToUser = function () {
                        if (userTeam) {
                            $('.personal-data-value .group').html(userTeam.title);
                        } else
                            $('.personal-data-value .group').html('-');
                    }
                    let addBirthdayToUser = function () {
                        if (user.birthday) {
                            $('.personal-data-value .birthday').html(user.birthday);
                        } else $('.personal-data-value .birthday').html("-");

                    }
                    let addImageToUser = function () {
                        if (user.image_crop) {
                            $('.avatar_wrapper #confirm-img').attr('src', user.image_crop).attr('alt', user.name);
                        } else {
                            $('.avatar_wrapper #confirm-img').attr('src', '/img/default.png').attr('alt', 'avatar');
                        }
                    }
                    let addTrainingCountToUser = function () {
                        $('.personal-data-value .count-training').html(123);

                    }

                    addPriceToSeasons();
                    showSessons();
                    showHeaderShedule();
                    addTeamNameToUser();
                    addBirthdayToUser();
                    addImageToUser();
                    addTrainingCountToUser();

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
        var teamName = $(this).val();
        $.ajax({
            url: '/get-team-details',
            type: 'GET',
            data: {name: teamName},

            success: function (response) {
                if (response.success) {
                    var team = response.data;
                    var teamWeekDayId = response.teamWeekDayId;
                    var usersTeam = response.usersTeam;
                    var weekdays = document.querySelectorAll('.weekday-checkbox .form-check');

                    // Установка дней недели
                    for (let i = 0; i < weekdays.length; i++) {
                        let weekday = weekdays[i];
                        let input = weekday.querySelector('input'); // Находим input внутри текущего div

                        if (input) { // Проверяем, существует ли input
                            // input.checked = false; // Устанавливаем атрибут checked
                            weekdays[i].classList.remove('weekday-enabled');
                        }

                        if (teamWeekDayId.includes(i + 1)) {
                            if (input) { // Проверяем, существует ли input
                                // input.checked = true; // Устанавливаем атрибут checked
                                weekdays[i].classList.add('weekday-enabled');
                            }
                        }
                    }

                    //Изменение состава юзеров

                    var users = document.querySelectorAll('#single-select-user option');
                    users.forEach((user, index) => {
                        if (index !== 0) { // оставить только первый элемент
                            user.remove();
                        }
                    });

                    var selectElement = document.querySelector('#single-select-user');
                    usersTeam.forEach(user => {
                        var option = document.createElement('option');
                        // option.value = user.id;   // Присвойте значение из свойства id
                        option.textContent = user.name; // Отобразите имя пользователя
                        selectElement.appendChild(option);
                    });

                }
            },
            error: function (xhr, status, error) {
            }
        });
    });
});
