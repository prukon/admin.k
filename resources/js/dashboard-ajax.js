// <script src="js/main.js"></script>

document.addEventListener('DOMContentLoaded', function () {

    // AJAX User
    $('#single-select-user').change(function () {
        let userName = $(this).val();
        let teamName = $('#single-select-team').val();
        // let inputDate = document.getElementById("inlineCalendar").value;

        $.ajax({
            url: '/get-user-details',
            type: 'GET',
            data: {
                userName: userName,
                teamName: teamName,
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

                    //Поиск и установка соответствующих установленных цен
                    function apendPrice(userPrice) {
                        if (userPrice) {
                            for (j = 0; j < userPrice.length; j++) {

                                // Получаем все блоки с классом border_price
                                const borderPrices = document.querySelectorAll('.border_price');

                                // Проходим по каждому блоку
                                for (let i = 0; i < borderPrices.length; i++) {
                                    const borderPrice = borderPrices[i];
                                    const button = borderPrice.querySelector('.new-main-button');

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
                                            if (priceValue) {
                                                if (matchedData.price > 0) {
                                                    priceValue.textContent = matchedData.price;
                                                }
                                            }

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
                            $('.group-value').html(userTeam.title);
                        } else
                            $('.group-value').html('-');
                    }

                    //Добавление начала занятий у юзера
                    // function apendUserStartDate() {
                    //     const input = document.getElementById("inlineCalendar");
                    //     input.value = null;
                    //     if (user.start_date) {
                    //         // $('#inlineCalendar').html(user.start_date);
                    //         const startDate = user.start_date // Дата из базы данных
                    //
                    //         // Преобразование формата даты из yyyy-mm-dd в dd.mm.yyyy
                    //         const [year, month, day] = startDate.split('-');
                    //         const formattedDate = `${day}.${month}.${year}`;
                    //
                    //         // Установка даты в поле ввода
                    //         input.value = formattedDate;
                    //     } else $('.personal-data-value .birthday').html("-");
                    //
                    //
                    // }

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
                            // Если кнопка найдена и сезон не открыт, кликнуть на неё
                            if (header && isOpen) {
                                header.click();
                            }
                        }
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
                    // apendUserStartDate();
                    // enableSetupBtn(user, team, inputDate);
                    updateGlobalScheduleData(scheduleUser);
                    setBackgroundToCalendar(globalScheduleData);
                    createCalendar();
                    openFirstSeason();
                    // apendStyleToUserWithoutTeam();

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
        // let inputDate = document.getElementById("inlineCalendar").value;


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
                // inputDate: inputDate,
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


                    // Установка дней недели
                    // function apendWeekdays(weekdays) {
                    //     for (let i = 0; i < weekdays.length; i++) {
                    //         let weekday = weekdays[i];
                    //         let input = weekday.querySelector('input'); // Находим input внутри текущего div
                    //
                    //         if (input) { // Проверяем, существует ли input
                    //             // input.checked = false; // Устанавливаем атрибут checked
                    //             weekdays[i].classList.remove('weekday-enabled');
                    //         }
                    //
                    //         if (teamWeekDayId != null) {
                    //             if (teamWeekDayId.includes(i + 1)) {
                    //                 if (input) { // Проверяем, существует ли input
                    //                     // input.checked = true; // Устанавливаем атрибут checked
                    //                     weekdays[i].classList.add('weekday-enabled');
                    //                 }
                    //             }
                    //         }
                    //     }
                    // }

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
                                .text(counter + '. ' + user.name); // Добавляем нумерацию перед именем

                            // Добавляем опцию в select
                            $('#single-select-user').append(option);

                            // Увеличиваем счетчик
                            counter++;
                        });

                        // Инициализируем Select2 с кастомными шаблонами
                        initializeSelect2();
                    }


                    // enableSetupBtn(user, team, inputDate);
                    // apendWeekdays(weekdays);
                    newUpdateSelectUsers();

                }
            },
            error: function (xhr, status, error) {
            }
        });
    });

    //AJAX клик по УСТАНОВИТЬ
    // $('#setup-btn').click(function () {
    //     let userName = $('#single-select-user').val();
    //     let teamName = $('#single-select-team').val();
    //     // let inputDate = document.getElementById("inlineCalendar").value;
    //
    //     // Выключение кнопки Установить
    //     function disabledBtn() {
    //         $('#setup-btn').attr('disabled', 'disabled');
    //     }
    //
    //     // Функция для получения ID активных дней недели
    //     function getActiveWeekdays() {
    //         // Создаем объект для сопоставления значения чекбокса с ID дня недели
    //         const dayIdMap = {
    //             'Monday': 1,
    //             'Tuesday': 2,
    //             'Wednesday': 3,
    //             'Thursday': 4,
    //             'Friday': 5,
    //             'Saturday': 6,
    //             'Sunday': 7
    //         };
    //
    //         // Найти все чекбоксы внутри div с классом "weekday-checkbox"
    //         const checkboxes = document.querySelectorAll('.weekday-checkbox input[type="checkbox"]');
    //
    //         // Собрать ID активных чекбоксов в массив
    //         const checkedDaysIds = Array.from(checkboxes)
    //             .filter(checkbox => checkbox.checked)
    //             .map(checkbox => dayIdMap[checkbox.value]);
    //
    //         return checkedDaysIds;
    //     }
    //
    //     let activeWeekdays = getActiveWeekdays();
    //     disabledBtn();
    //
    //
    //     $.ajax({
    //         url: '/setup-btn',
    //         type: 'GET',
    //         data: {
    //             userName: userName,
    //             teamName: teamName,
    //             inputDate: inputDate,
    //             activeWeekdays: activeWeekdays,
    //         },
    //
    //         success: function (response) {
    //             if (response.success) {
    //                 let userName = response.userName;
    //                 let inputDate = response.inputDate;
    //                 let teamWeekDays = response.teamWeekDays;
    //                 let teamWeekDaysGet = response.teamWeekDaysGet;
    //                 let scheduleUser = response.scheduleUser; //upd
    //                 let userTeam = response.userTeam;
    //
    //
    //                 // Выключение кнопки Установить
    //                 function enabledBtn() {
    //                     $('#setup-btn').removeAttr('disabled');
    //                 }
    //
    //                 // Добавление название группы юзеру
    //                 function apendTeamNameToUser() {
    //                     if (userTeam) {
    //                         $('.group-value').html(userTeam.title);
    //                     } else
    //                         $('.group-value').html('-');
    //                 }
    //
    //                 enabledBtn();
    //                 updateGlobalScheduleData(scheduleUser);
    //                 setBackgroundToCalendar(globalScheduleData);
    //                 apendTeamNameToUser();
    //
    //                 // Обновление календаря
    //                 // updateCalendar(response.scheduleData); // передаем данные для обновления
    //
    //                 showSuccessModal("Установка расписания", "Расписание успешно обновлено.");
    //             }
    //             // location.reload();
    //
    //         },
    //     })
    // });

});
