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

// Вызов datapicker
//     var inlineCalendar = new Datepicker('#inlineCalendar',{
//         weekStart: 1,
//     });



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


