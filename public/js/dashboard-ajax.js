document.addEventListener('DOMContentLoaded', function () {

    // AJAX User
    $('#single-select-user').change(function () {
        let userName = $(this).val();
        let teamName = $('#single-select-team').val();
        let inputDate = document.getElementById("inlineCalendar").value;

        $.ajax({
            url: '/get-user-details',
            type: 'GET',
            data: {
                userName: userName,
                teamName: teamName,
                inputDate: inputDate,
            },

            success: function (response) {
                if (response.success) {
                    let user = response.user;
                    let userTeam = response.userTeam;
                    let userPrice = response.userPrice;
                    let scheduleUser = response.scheduleUser;
                    let team = response.team;
                    let inputDate = response.inputDate;

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

                    //Поиск и установка соответствующих установленных цен
                    function apendPrice(userPrice) {
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

// console.log('priceValues[j]:');
// console.log(priceValues[j]);

                                // Accumulate the total sum of price values
                                totalSumm[seasonId] += Number(priceValues[j].textContent);
                                // console.log('totalSumm[seasonId]:');
                                // console.log(totalSumm[seasonId]);

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
                                    // console.log(container.querySelector('.price-value').textContent);
                                    // Добавляем значение к общей сумме для этого сезона
                                    totalSum += priceValue;
                                } else {
                                }
                            });

                            // Обновляем значение в is_credit_value для текущего сезона
                            const creditValueField = season.querySelector('.is_credit_value');
                            creditValueField.textContent = totalSum;
                        });
                    }

                    // Вставка дня рождения
                    function apendBirthdayToUser() {
                        if (user.birthday) {
                            $('.personal-data-value .birthday').html(user.birthday);
                        } else $('.personal-data-value .birthday').html("-");

                    }

                    // Вставка аватарки юзеру
                    function apendImageToUser() {
                        if (user.image_crop) {
                            $('.avatar_wrapper #confirm-img').attr('src', 'storage/avatars/' + user.image_crop).attr('alt', user.name);
                        } else {
                            $('.avatar_wrapper #confirm-img').attr('src', '/img/default.png').attr('alt', 'avatar');
                        }
                    }

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
                            $('.personal-data-value .group').html(userTeam.title);
                        } else
                            $('.personal-data-value .group').html('-');
                    }

                    //Добавление начала занятий у юзера
                    function apendUserStartDate() {
                        const input = document.getElementById("inlineCalendar");
                        input.value = null;
                        if (user.start_date) {
                            // $('#inlineCalendar').html(user.start_date);
                            const startDate = user.start_date // Дата из базы данных

                            // Преобразование формата даты из yyyy-mm-dd в dd.mm.yyyy
                            const [year, month, day] = startDate.split('-');
                            const formattedDate = `${day}.${month}.${year}`;

                            // Установка даты в поле ввода
                            input.value = formattedDate;
                        } else $('.personal-data-value .birthday').html("-");


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
                                console.log(isOpen);
                                // Если кнопка найдена и сезон не открыт, кликнуть на неё
                                if (header && isOpen) {
                                    header.click();
                                }
                            }
                    }
 

                    showHeaderShedule();
                    refreshPrice();
                    apendPrice(userPrice);
                    showSessons();
                    apendCreditTotalSumm();
                    apendTeamNameToUser();
                    apendBirthdayToUser();
                    apendImageToUser();
                    apendTrainingCountToUser();
                    apendUserStartDate();
                    enableSetupBtn(user, team, inputDate);
                    updateGlobalScheduleData(scheduleUser);
                    setBackgroundToCalendar(globalScheduleData);
                    createCalendar();
                    openFirstSeason();

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
        let inputDate = document.getElementById("inlineCalendar").value;


        $.ajax({
            url: '/get-team-details',
            type: 'GET',
            data: {
                teamName: teamName,
                userName: userName,
                inputDate: inputDate,
            },


            success: function (response) {
                if (response.success) {
                    let team = response.team;
                    let teamWeekDayId = response.teamWeekDayId;
                    let usersTeam = response.usersTeam;
                    let inputDate = response.inputDate;
                    let userWithoutTeam = response.userWithoutTeam;
                    let user = response.user;
                    let weekdays = document.querySelectorAll('.weekday-checkbox .form-check');


                    // Установка дней недели
                    let apendWeekdays = function (weekdays) {
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
                    }
                    //Изменение состава юзеров
                    let updateSelectUsers = function () {
                        var users = document.querySelectorAll('#single-select-user option');
                        users.forEach((user, index) => {
                            if (index !== 0) { // оставить только первый элемент
                                user.remove();
                            }
                        });

                        var selectElement = document.querySelector('#single-select-user');

                        // if(userWithoutTeam){
                        // userWithoutTeam.forEach(user => {
                        //     var option = document.createElement('option');
                        //     // option.value = user.id;   // Присвойте значение из свойства id
                        //     option.textContent = user.name; // Отобразите имя пользователя
                        //     selectElement.appendChild(option);
                        //     option.classList.add('user-without-team');
                        // });
                        // }


                        // if (userWithoutTeam) {
                        //     userWithoutTeam.forEach(user => {
                        //         var option = document.createElement('option');
                        //         option.value = user.id; // Присвоение значения из свойства id
                        //         option.textContent = user.name; // Отображение имени пользователя
                        //         option.style.color = 'red'; // Применение стиля прямо к элементу
                        //         selectElement.appendChild(option);
                        //     });
                        // }

                        // var selectElement = document.querySelector('#single-select-user');
                        //
                        // selectElement.select2({
                        //     templateResult: function (data) {
                        //         // We only really care if there is an element to pull classes from
                        //         if (!data.element) {
                        //             return data.text;
                        //         }
                        //
                        //         var $element = $(data.element);
                        //
                        //         var $wrapper = $('<span></span>');
                        //         $wrapper.addClass($element[0].className('user-without-team'));
                        //
                        //         $wrapper.text(data.text);
                        //
                        //         return $wrapper;
                        //     }
                        // });

                        // console.log(userWithoutTeam);

                        // if (userWithoutTeam) {
                        //     console.log('userWithoutTeam');
                        //     userWithoutTeam.forEach(user => {
                        //         var option = new Option(user.name, user.id, false, false);
                        //         $(option).attr('data-user-without-team', true); // Установка атрибута
                        //         $(selectElement).append(option);
                        //     });
                        //
                        //     $(selectElement).select2({
                        //         templateResult: function (data) {
                        //             if ($(data.element).data('user-without-team')) {
                        //                 return $('<span style="color: red;">' + data.text + '</span>');
                        //             }
                        //             return data.text;
                        //         }
                        //     });
                        // }


                        usersTeam.forEach(user => {
                            var option = document.createElement('option');
                            // option.value = user.id;   // Присвойте значение из свойства id
                            option.textContent = user.name; // Отобразите имя пользователя
                            selectElement.appendChild(option);
                        });


                    }

                    enableSetupBtn(user, team, inputDate);
                    apendWeekdays(weekdays);
                    if (user) {
                        if (user.team_id > 0) {

                            updateSelectUsers();
                        }
                    } else {
                        updateSelectUsers();

                    }

                }
            },
            error: function (xhr, status, error) {
            }
        });
    });

    //AJAX клик по УСТАНОВИТЬ
    $('#setup-btn').click(function () {
        let userName = $('#single-select-user').val();
        let teamName = $('#single-select-team').val();
        let inputDate = document.getElementById("inlineCalendar").value;

        // Выключение кнопки Установить
        function disabledBtn() {
            $('#setup-btn').attr('disabled', 'disabled');
        }

        // Функция для получения ID активных дней недели
        function getActiveWeekdays() {
            // Создаем объект для сопоставления значения чекбокса с ID дня недели
            const dayIdMap = {
                'Monday': 1,
                'Tuesday': 2,
                'Wednesday': 3,
                'Thursday': 4,
                'Friday': 5,
                'Saturday': 6,
                'Sunday': 7
            };

            // Найти все чекбоксы внутри div с классом "weekday-checkbox"
            const checkboxes = document.querySelectorAll('.weekday-checkbox input[type="checkbox"]');

            // Собрать ID активных чекбоксов в массив
            const checkedDaysIds = Array.from(checkboxes)
                .filter(checkbox => checkbox.checked)
                .map(checkbox => dayIdMap[checkbox.value]);

            return checkedDaysIds;
        }

        let activeWeekdays = getActiveWeekdays();
        disabledBtn();


        $.ajax({
            url: '/setup-btn',
            type: 'GET',
            data: {
                userName: userName,
                teamName: teamName,
                inputDate: inputDate,
                activeWeekdays: activeWeekdays,
            },

            success: function (response) {
                if (response.success) {
                    let userName = response.userName;
                    let inputDate = response.inputDate;
                    let teamWeekDays = response.teamWeekDays;
                    let teamWeekDaysGet = response.teamWeekDaysGet;

                    // Выключение кнопки Установить
                    function enabledBtn() {
                        $('#setup-btn').removeAttr('disabled');
                    }

                    enabledBtn();

                }
                location.reload();
            },
        })
    });

    // AJAX Вызов модалки
    function showModal() {
        document.getElementById('upload-photo').addEventListener('click', function () {
            $('#uploadPhotoModal').modal('show');
            let apendUserNametoForm = function () {
                if (currentUserRole == "admin") {
                    $('#selectedUserName').val($('#single-select-user').val());
                } else {
                    $('#selectedUserName').val(currentUserName);
                }


            }
            apendUserNametoForm();
        });

        $(document).ready(function () {
            //Добавление имени пользователя в скрытое поле формы для формы отправки аватарки


            // Инициализация Croppie
            var $uploadCrop = $('#upload-demo').croppie({
                viewport: {width: 141, height: 190, type: 'square'},
                boundary: {width: 300, height: 300},
                showZoomer: true
            });

            // При выборе файла изображение загружается в Croppie
            $('#upload').on('change', function () {
                var reader = new FileReader();
                reader.onload = function (e) {
                    $uploadCrop.croppie('bind', {
                        url: e.target.result
                    }).then(function () {
                    });
                }
                reader.readAsDataURL(this.files[0]);

            });

            // Сохранение обрезанного изображения и отправка через AJAX
            $('#saveImageBtn').on('click', function () {
                $uploadCrop.croppie('result', {
                    type: 'base64',
                    size: 'viewport'
                }).then(function (resp) {
                    // Заполняем скрытое поле base64 изображением
                    $('#croppedImage').val(resp);

                    // Устанавливаем имя пользователя в скрытое поле
                    // let userName = $('#single-select-user').val();
                    //
                    // $('#selectedUserName').val(userName);
                    let userName = $('#selectedUserName').val();


                    // Создаем FormData для отправки
                    var formData = new FormData();
                    formData.append('_token', $('input[name="_token"]').val()); // Добавляем CSRF-токен
                    formData.append('croppedImage', $('#croppedImage').val()); // Добавляем обрезанное изображение
                    formData.append('userName', userName); // Добавляем имя пользователя

                    // console.log(userName);
                    // console.log(formData);
                    // Отправка данных через AJAX
                    $.ajax({
                        // url: "{{ route('profile.uploadAvatar') }}", // URL маршрута
                        url: uploadUrl, // URL маршрута
                        type: 'POST', // Метод POST
                        data: formData, // Данные формы
                        contentType: false,
                        processData: false,
                        success: function (response) {
                            if (response.success) {
                                // Обновляем изображение на странице
                                $('#confirm-img').attr('src', response.image_url);
                                console.log('Изображение успешно загружено!');
                            } else {
                                alert('Ошибка загрузки изображения');
                            }
                            location.reload();
                        },
                        error: function (xhr, status, error) {
                            console.error('Ошибка:', error);
                            alert('Ошибка на сервере');
                        }
                    });
                });
            });
        });


    }

    showModal();

});
