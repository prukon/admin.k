@php
    // Общая русская локализация jQuery DataTables (объект для опции language).
    $dataTablesRu = [
        'processing' => 'Обработка...',
        'search' => '',
        'searchPlaceholder' => 'Поиск...',
        'lengthMenu' => 'Показать _MENU_',
        'info' => 'С _START_ до _END_ из _TOTAL_ записей',
        'infoEmpty' => 'С 0 до 0 из 0 записей',
        'infoFiltered' => '(отфильтровано из _MAX_ записей)',
        'loadingRecords' => 'Загрузка записей...',
        'zeroRecords' => 'Записи отсутствуют.',
        'emptyTable' => 'В таблице отсутствуют данные',
        'paginate' => [
            'first' => '',
            'previous' => '',
            'next' => '',
            'last' => '',
        ],
        'aria' => [
            'sortAscending' => ': активировать для сортировки столбца по возрастанию',
            'sortDescending' => ': активировать для сортировки столбца по убыванию',
        ],
    ];
@endphp
{!! json_encode($dataTablesRu, JSON_UNESCAPED_UNICODE) !!}
