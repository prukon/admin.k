// Глобальная переменная для хранения данных расписания юзера из AJAX
var globalScheduleData = [];


// Функция для обновления глобальной переменной после получения данных через AJAX
function updateGlobalScheduleData(scheduleUser) {
    if (scheduleUser) {
        globalScheduleData = scheduleUser;
    }
}

//разблокировка кнопки УСТАНОВИТЬ
// function enableSetupBtn(user, team, inputDate) {
//     if (user && team && inputDate) {
//         $('#setup-btn').removeAttr('disabled');
//     }
// }

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
            // const formatedPaymentDate = paymentDate;

            // console.log("paymentDate: " +  paymentDate);
            // console.log("formatedPaymentDate: " +  formatedPaymentDate);

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

//Создание календаря
function createCalendar() {
    // console.log('createCalendar()');
    let currentYear = new Date().getFullYear();
    let currentMonth = new Date().getMonth();


    // Создаем календарь для текущего месяца
    function createCalendar(year, month) {
        // console.log('createCalendar2');
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
        // Закрашиваем ячейки на текущем месяце в соответствии с данными расписания


        // updateGlobalScheduleData(@json($scheduleUserJson));
        //
        // console.log("@json($scheduleUserJson):");
        // console.log(@json($scheduleUserJson));
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

    // Вызов контекстного меню. Обработчик правого клика на дате.
    function getContextMenu() {
        document.getElementById('days').addEventListener('contextmenu', function (event) {
            event.preventDefault();
            const target = event.target;
            let userName = $('#single-select-user').val();

            if (target.dataset.date && userName) {
                // showContextMenu(event.clientX, event.clientY, target.dataset.date);
                showContextMenu(target);

            }
        });
    }

    //Позиционирование контекстного меню
    function showContextMenu(target) {
        const contextMenu = document.getElementById('context-menu');

        // Получаем отступы от верхнего левого угла календаря
        const x = target.offsetLeft + target.offsetWidth;
        const y = target.offsetTop + target.offsetHeight;

        // Устанавливаем позицию контекстного меню
        contextMenu.style.left = `${x}px`;
        contextMenu.style.top = `${y}px`;
        contextMenu.style.display = 'block';
        contextMenu.dataset.date = target.dataset.date;
    }

    // Скрытие контекстного меню при клике вне его
    function hideContextMenuMissClick() {
        document.addEventListener('click', function (event) {
            const contextMenu = document.getElementById('context-menu');
            if (!contextMenu.contains(event.target)) {
                contextMenu.style.display = 'none';
            }
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

    // Функция отправки AJAX-запроса
    function sendActionRequest(date, action, userName) {

        $.ajax({
            url: '/content-menu-calendar',
            method: 'GET',
            data: {
                date: date,
                action: action,
                userName: userName,
            },
            success: function (response) {
                let scheduleUser = response.scheduleUser;
                updateGlobalScheduleData(scheduleUser);
                createCalendar(currentYear, currentMonth);
            },
            error: function () {
                alert('An error occurred while processing your request.');
            }
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
    getContextMenu();
    hideContextMenuMissClick();
    clickContextmenu();


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
            }
        });

        // Обновляем значение в is_credit_value для текущего сезона
        const creditValueField = season.querySelector('.is_credit_value');
        creditValueField.textContent = totalSum;
    });


}


// Поиск по группам в Пользователя


function clickToSearch() {
    // document.getElementById('search-button').addEventListener('click', function () {
    // searchUserName();
    // searchUserbyTeam();
    // window.location.reload();
    // });

    function searchUserName() {
        document.getElementById('search-button').addEventListener('click', function () {
            var query = document.getElementById('search-input').value;
            // Формируем новый URL
            var newUrl = new URL(window.location.href);
            if (query) {
                // Если в инпуте есть текст, устанавливаем GET-параметр
                newUrl.searchParams.set('name', query);
            } else {
                // Если инпут пустой, удаляем GET-параметр
                newUrl.searchParams.delete('name');
            }
            // Обновляем URL без перезагрузки страницы
            window.history.pushState(null, '', newUrl);
            // Перезагружаем страницу с новым URL


            var selectedOption = document.getElementById('search-select').value;
            // Формируем новый URL
            var newUrl = new URL(window.location.href);
            if (selectedOption) {
                // Если выбрана опция, устанавливаем GET-параметр
                newUrl.searchParams.set('team_id', selectedOption);
            } else {
                // Если не выбрана опция (значение пустое), удаляем GET-параметр
                newUrl.searchParams.delete('team_id');
            }
            // Обновляем URL без перезагрузки страницы
            window.history.pushState(null, '', newUrl);


            window.location.reload();
        });
    }

    // Функция для установки значения инпута при загрузке страницы
    function setInputFromURL() {
        var urlParams = new URLSearchParams(window.location.search);
        var nameQuery = urlParams.get('name');
        if (nameQuery) {
            document.getElementById('search-input').value = nameQuery;
        }
    }

    // Функция для установки значения селекта при загрузке страницы
    function setSelectFromURL() {
        var urlParams = new URLSearchParams(window.location.search);
        var teamId = urlParams.get('team_id');
        if (teamId) {
            document.getElementById('search-select').value = teamId;
        }
    }

    // Вызываем функции после загрузки страницы
    window.onload = function () {
        searchUserName();
        setInputFromURL();
        setSelectFromURL();
        // searchUserbyTeam();
    };

}

document.addEventListener('DOMContentLoaded', function () {

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

    // Добавление datapicker к календарю
    function addDatapicker() {
        try {
            $(function () {
                $('#inlineCalendar').datepicker({
                    firstDay: 1,
                    dateFormat: "dd.mm.yy",
                    defaultDate: new Date(),
                    monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                        'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
                    dayNames: ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'],
                    dayNamesShort: ['вск', 'пнд', 'втр', 'срд', 'чтв', 'птн', 'сбт'],
                    dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                    prevText: '<i class="fa-solid fa-caret-left"></i>', // Добавляем иконку для кнопки назад
                    nextText: '<i class="fa-solid fa-caret-right"></i>'  // Добавляем иконку для кнопки вперед

                });
                $('#inlineCalendar').datepicker('setDate', new Date());
            });
        } catch (e) {
        }
    }

    // -----Вызовы------

    addSelect2ToUser();
    addSelect2ToTeam();
    addDatapicker();
    // showCreditNotice();

    // updateGlobalScheduleData({{$scheduleUser}});
    // setBackgroundToCalendar(@json($scheduleUser));

}, false);


