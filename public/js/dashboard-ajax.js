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
                    let user = response.userData;
                    let userTeam = response.userTeam;
                    let userPrice = response.userPrice;

                    // Добавляем суммы в месяца
                    let apendPriceToSeasons = function () {
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

                        //Поиск и установка соответствующих установленных цен
                        let apendPrice = function (userPrice) {
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
                        apendPrice(userPrice);
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
                    // Вставка дня рождения
                    let apendBirthdayToUser = function () {
                        if (user.birthday) {
                            $('.personal-data-value .birthday').html(user.birthday);
                        } else $('.personal-data-value .birthday').html("-");

                    }
                    // Вставка аватарки юзеру
                    let apendImageToUser = function () {
                        if (user.image_crop) {
                            $('.avatar_wrapper #confirm-img').attr('src','storage/avatars/' + user.image_crop).attr('alt', user.name);
                        } else {
                            $('.avatar_wrapper #confirm-img').attr('src', '/img/default.png').attr('alt', 'avatar');
                        }
                    }
                    // Вставка счетчика тренировок юзеру
                    let apendTrainingCountToUser = function () {
                        $('.personal-data-value .count-training').html(123);
                    }
                    // Отображение заголовка расписания
                    let showHeaderShedule = function () {
                        let headerShedule = document.querySelector('.header-shedule');
                        headerShedule.classList.remove('display-none');
                    }
                    // Добавление название группы юзеру
                    let apendTeamNameToUser = function () {
                        if (userTeam) {
                            $('.personal-data-value .group').html(userTeam.title);
                        } else
                            $('.personal-data-value .group').html('-');
                    }
                    //Добавление начала занятий у юзера
                    let apendUserStartDate = function () {
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
                    //разблокировка кнопки УСТАНОВИТЬ
                    let enableSetupBtn = function () {
                        $('#setup-btn').removeAttr('disabled');
                    }

                    showHeaderShedule();
                    showSessons();
                    apendPriceToSeasons();
                    apendTeamNameToUser();
                    apendBirthdayToUser();
                    apendImageToUser();
                    apendTrainingCountToUser();
                    apendUserStartDate();
                    enableSetupBtn();

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
                    var userWithoutTeam = response.userWithoutTeam;
                    var weekdays = document.querySelectorAll('.weekday-checkbox .form-check');

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

                        console.log(userWithoutTeam);

                        if (userWithoutTeam) {
                            console.log('userWithoutTeam');
                            userWithoutTeam.forEach(user => {
                                var option = new Option(user.name, user.id, false, false);
                                $(option).attr('data-user-without-team', true); // Установка атрибута
                                $(selectElement).append(option);
                            });

                            $(selectElement).select2({
                                templateResult: function (data) {
                                    if ($(data.element).data('user-without-team')) {
                                        return $('<span style="color: red;">' + data.text + '</span>');
                                    }
                                    return data.text;
                                }
                            });
                        }




                        usersTeam.forEach(user => {
                            var option = document.createElement('option');
                            // option.value = user.id;   // Присвойте значение из свойства id
                            option.textContent = user.name; // Отобразите имя пользователя
                            selectElement.appendChild(option);
                        });


                    }

                    apendWeekdays(weekdays);
                    updateSelectUsers();
                }
            },
            error: function (xhr, status, error) {
            }
        });
    });

    //AJAX клик по УСТАНОВИТЬ
    $('#setup-btn').click(function () {
        let userName = $('#single-select-user').val();
        let inputDate = document.getElementById("inlineCalendar").value;

        $.ajax({
            url: '/setup-btn',
            type: 'GET',
            data: {
                userName: userName,
                inputDate: inputDate,
            },

            success: function (response) {
                if (response.success) {
                    var userName = response.userName;
                    var inputDate = response.inputDate;
                    console.log(userName);
                    console.log(inputDate);
                }
                location.reload();
            },
        })
    });

    // AJAX Вызов модалки
    let showModal = function () {
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
                viewport: { width: 141, height: 190, type: 'square' },
                boundary: { width: 300, height: 300 },
                showZoomer: true
            });

            // При выборе файла изображение загружается в Croppie
            $('#upload').on('change', function () {
                var reader = new FileReader();
                reader.onload = function (e) {
                    $uploadCrop.croppie('bind', {
                        url: e.target.result
                    }).then(function(){
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

                    console.log(userName);
                    console.log(formData);
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
