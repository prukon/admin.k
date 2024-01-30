$(document).ready(function () {

// Подсвечивание бокового меню при переключении
    var activeButtonIndex = localStorage.getItem('activeButtonIndex');
    if (activeButtonIndex !== null) {
        $('.side-menu a').eq(activeButtonIndex).find('button').addClass('btn-bd-primary-active');
    }
    $('.side-menu a').click(function () {
        // Сохранение индекса активной кнопки в локальное хранилище
        var activeButtonIndex = $('.side-menu a').index($(this));
        localStorage.setItem('activeButtonIndex', activeButtonIndex);
    });
});


let clickSeason = function () {
    var chevronDownIcons = document.querySelectorAll('.header-season');
    // Добавляем обработчик события клика для каждого элемента
    chevronDownIcons.forEach(function(icon) {
        icon.addEventListener('click', function() {
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
            borderPriceElements.forEach(function(borderPrice) {
                if (icon.children[0].classList.contains('fa-chevron-up')) {
                    borderPrice.style.display = 'none';
                } else {
                    borderPrice.style.display = 'block';
                }
            });
        });
    });
}


document.addEventListener('DOMContentLoaded', function () {

    // Select2
    $('#single-select-field').select2({
        theme: "bootstrap-5",
        width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
        placeholder: $(this).data('placeholder'),
    });
    $('#single-select-field2').select2({
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


