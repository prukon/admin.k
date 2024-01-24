$(document).ready(function () {
    // Восстановление активной кнопки при загрузке страницы
    var activeButtonIndex = localStorage.getItem('activeButtonIndex');
    if (activeButtonIndex !== null) {
        $('.side-menu a').eq(activeButtonIndex).find('button').addClass('btn-bd-primary-active');
    }

    $('.side-menu a').click(function () {
        // Удаление класса btn-bd-primary-active со всех кнопок
        // $('.side-menu a button').removeClass('btn-bd-primary-active');

        // Добавление класса btn-bd-primary-active к текущей кнопке
        // var button = $(this).find('button');
        // button.addClass('btn-bd-primary-active');

        // Сохранение индекса активной кнопки в локальное хранилище
        var activeButtonIndex = $('.side-menu a').index($(this));
        localStorage.setItem('activeButtonIndex', activeButtonIndex);
    });
});