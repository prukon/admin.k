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
let hideAllSeason = function()
{
    var seasons = document.querySelectorAll('.season');
    for (var i = 0; i < seasons.length; i++) {
        seasons[i].classList.add('display-none');
    }

}



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


