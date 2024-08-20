// Глобальная переменная для хранения данных расписания юзера из AJAX
let globalScheduleData = [];

//разблокировка кнопки УСТАНОВИТЬ
function enableSetupBtn (user, team, inputDate) {
    if (user && team && inputDate) {
        $('#setup-btn').removeAttr('disabled');
    }
}
// Создание сезонов
function createSeasons () {
    // console.log('createSeasons');

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
// Цикл по сезонам
    document.querySelectorAll('.season .container').forEach(container => {
        var season = container.dataset.season;
        // Цикл по месяцам
        for (const [key, month] of months.entries()) {
            const div = document.createElement('div');
            div.className = `border_price col-3 ${month}`;

            if (month == 'september') {
                // season = season - 1;
                // console.log(month)
            }

            var displaySeason;
            if (monthsRu[key] == "Сентябрь" ||
                monthsRu[key] == "Октябрь" ||
                monthsRu[key] == "Ноябрь" ||
                monthsRu[key] == "Декабрь"
            ) {
                displaySeason = season - 1
            } else {
                displaySeason = season

            }
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
                        <button type="button" disabled="enabled" class="btn btn-lg btn-bd-primary new-main-button">Оплатить</button>
                    </div>
                </div>
            `;

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

// Закрашивание ячеек в календаре
function highlightCalendarDates(scheduleUser) {
    if (scheduleUser) {
        scheduleUser.forEach(entry => {
            // Формат даты в dataset.date в элементе календаря совпадает с форматом в объекте scheduleUser
            const dayElement = document.querySelector(`[data-date="${entry.date}"]`);

            if (dayElement) {
                // dayElement.classList.add('scheduled-day');  // Добавляем общий класс для всех дней с расписанием

                // Закрашиваем в зависимости от состояния оплаты
                if (entry.is_enabled) {
                    dayElement.classList.add('is_enabled');
                } else if (entry.is_hospital) {
                    dayElement.classList.add('is_hospital');
                }
            }
        });
    }
}
//Создание календаря
function createCalendar() {
    let currentYear = new Date().getFullYear();
    let currentMonth = new Date().getMonth();

    function createCalendar(year, month) {
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
        // Закрашиваем ячейки на текущем месяце в соответствии с данными расписания
        highlightCalendarDates(globalScheduleData);
    }

    document.getElementById('prev-month').addEventListener('click', () => {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        createCalendar(currentYear, currentMonth);
    });

    document.getElementById('next-month').addEventListener('click', () => {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        createCalendar(currentYear, currentMonth);
    });

    // Создаем календарь для текущего месяца
    createCalendar(currentYear, currentMonth);

    // Обработчик правого клика на дате
    document.getElementById('days').addEventListener('contextmenu', function (event) {
        event.preventDefault();
        const target = event.target;
        if (target.dataset.date) {
            // showContextMenu(event.clientX, event.clientY, target.dataset.date);
            showContextMenu(target);

        }
    });

    // Показ контекстного меню
    // function showContextMenu(x, y, date) {
    //     const contextMenu = document.getElementById('context-menu');
    //     contextMenu.style.left = `${x}px`;
    //     contextMenu.style.top = `${y}px`;
    //     contextMenu.style.display = 'block';
    //     contextMenu.dataset.date = date;
    // }
    // function showContextMenu(target) {
    //     const contextMenu = document.getElementById('context-menu');
    //
    //     // Получаем размеры и позицию ячейки
    //     const rect = target.getBoundingClientRect();
    //     const x = rect.right;
    //     const y = rect.bottom;
    //
    //     // Устанавливаем позицию контекстного меню
    //     contextMenu.style.left = `${x}px`;
    //     contextMenu.style.top = `${y}px`;
    //     contextMenu.style.display = 'block';
    //     contextMenu.dataset.date = target.dataset.date;
    // }
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
    document.addEventListener('click', function (event) {
        const contextMenu = document.getElementById('context-menu');
        if (!contextMenu.contains(event.target)) {
            contextMenu.style.display = 'none';
        }
    });

    // Обработчик кликов по пунктам контекстного меню
    document.getElementById('context-menu').addEventListener('click', function (event) {
        const action = event.target.dataset.action;
        const date = this.dataset.date;
        let userName = $('#single-select-user').val();

        if (action && date && userName) {
            sendActionRequest(date, action, userName);
        }
        this.style.display = 'none';
    });

    // Функция отправки AJAX-запроса
    function sendActionRequest(date, action, userName) {
        $.ajax({
            url: '/your-server-endpoint',
            method: 'POST',
            data: {
                date: date,
                action: action,
                user: userName
            },
            success: function (response) {
                alert(`Action "${action}" for ${userName} on ${date} was successful!`);
            },
            error: function () {
                alert('An error occurred while processing your request.');
            }
        });
    }
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

}, false);


