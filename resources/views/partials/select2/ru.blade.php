@php
    // Общая русская локализация Select2 (объект для опции language или $.fn.select2.defaults.set).
@endphp
{
    errorLoading: function () {
        return {!! json_encode('Не удалось загрузить результаты.', JSON_UNESCAPED_UNICODE) !!};
    },
    inputTooLong: function (args) {
        return 'Сократите ввод на ' + (args.input.length - args.maximum) + ' символ(а/ов).';
    },
    inputTooShort: function (args) {
        var n = Math.max(args.minimum - (args.input ? args.input.length : 0), 1);
        return 'Введите ещё ' + n + ' символ(а/ов).';
    },
    loadingMore: function () {
        return {!! json_encode('Загрузка данных…', JSON_UNESCAPED_UNICODE) !!};
    },
    maximumSelected: function (args) {
        return 'Можно выбрать не более ' + args.maximum + ' элемент(а/ов).';
    },
    noResults: function () {
        return {!! json_encode('Ничего не найдено', JSON_UNESCAPED_UNICODE) !!};
    },
    searching: function () {
        return {!! json_encode('Поиск…', JSON_UNESCAPED_UNICODE) !!};
    },
    removeAllItems: function () {
        return {!! json_encode('Удалить все элементы', JSON_UNESCAPED_UNICODE) !!};
    }
}
