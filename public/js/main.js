// Подсвечивание бокового меню при переключении

// $(document).ready(function () {
//     var activeButtonIndex = localStorage.getItem('activeButtonIndex');
//     if (activeButtonIndex !== null) {
//         $('.side-menu a').eq(activeButtonIndex).find('button').addClass('btn-bd-primary-active');
//     }
//     $('.side-menu a').click(function () {
//         // Сохранение индекса активной кнопки в локальное хранилище
//         var activeButtonIndex = $('.side-menu a').index($(this));
//         localStorage.setItem('activeButtonIndex', activeButtonIndex);
//     });
// });


// Создание сезонов
let createSeasons = function () {
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
let clickSeason = function () {

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
let hideAllSeason = function () {
    var seasons = document.querySelectorAll('.season');
    for (var i = 0; i < seasons.length; i++) {
        seasons[i].classList.add('display-none');
    }

}


// createSeasons()     //Создание сезонов
// clickSeason()       //Измерение иконок при клике
// hideAllSeason()     //Скрытие всех сезонов при загрузке страницы

let createCalendar = function () {
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
    document.getElementById('days').addEventListener('contextmenu', function(event) {
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
    document.addEventListener('click', function(event) {
        const contextMenu = document.getElementById('context-menu');
        if (!contextMenu.contains(event.target)) {
            contextMenu.style.display = 'none';
        }
    });

    // Обработчик кликов по пунктам контекстного меню
    document.getElementById('context-menu').addEventListener('click', function(event) {
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
            success: function(response) {
                alert(`Action "${action}" for ${userName} on ${date} was successful!`);
            },
            error: function() {
                alert('An error occurred while processing your request.');
            }
        });
    }
}

// createCalendar();
// let showSeasonsPrice = function () {
//
//     // Получаем текущий год
//     const currentYear = new Date().getFullYear();
//
//     // Определяем, сколько сезонов необходимо отобразить
//     const numberOfSeasons = 4;
//
//     // Селектор контейнера для сезонов
//     const container = document.getElementById('seasons-container');
//
//     // Генерируем сезоны
//     for (let i = 0; i < numberOfSeasons; i++) {
//         const fromYear = currentYear - i - 1;
//         const toYear = currentYear - i;
//
//         // Создаем div для каждого сезона
//         const seasonDiv = document.createElement('div');
//         seasonDiv.className = `season season-${toYear}`;
//         seasonDiv.id = `season-${toYear}`;
//
//         seasonDiv.innerHTML = `
//             <div class="header-season">Сезон ${fromYear} - ${toYear} <i class="fa fa-chevron-up"></i>
//                 <span class="display-none from">${fromYear}</span>
//                 <span class="display-none to">${toYear}</span>
//             </div>
//             <span class="is_credit">Имеется просроченная задолженность в размере
//                 <span class="is_credit_value">0</span> руб.
//             </span>
//             <div class="row justify-content-center align-items-center container" data-season="${toYear}"></div>
//         `;
//
//         // Вставляем новый div в контейнер
//         container.appendChild(seasonDiv);
//     }
//
// }


document.addEventListener('DOMContentLoaded', function () {


    // Select2
    $('#single-select-user').select2({
        theme: "bootstrap-5",
        width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
        placeholder: $(this).data('placeholder'),
    });
    $('#single-select-team').select2({
        theme: "bootstrap-5",
        width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
        placeholder: $(this).data('placeholder'),
    });

// datapicker
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

}, false);


