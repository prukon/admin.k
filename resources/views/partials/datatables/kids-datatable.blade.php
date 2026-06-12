{{--
    Пресет DataTables для admin-списков.

    JS: resources/js/kids-datatable.js (глобально в layouts/admin2 через Vite).

    KidsCrmDataTable.create('#my-table', {
        columnsSettings: {
            defaults: { name: true, actions: true },
            urls: { get: '.../columns-settings', save: '.../columns-settings' },
            csrfToken: '...',
        },
        dataTable: {
            ajax: { url: '.../data', data: (d) => { ... } },
            order: [[1, 'asc']],
            language: @include('partials.datatables.ru'),
        },
        columns: [
            { key: 'id', type: 'id', data: 'id' },
            { key: 'name', type: 'text', data: 'name' },
            { key: 'teams_label', type: 'list', data: 'teams_label', itemsKey: 'teams_titles' },
            { key: 'is_enabled_label', type: 'badge', data: 'is_enabled_label', badgeKey: 'is_enabled' },
            { key: 'actions', type: 'actions', render: (data, type, row) => '...' },
        ],
    });

    Типы колонок: id, rownum, sort, text, text-long, list, badge, count, money, link, actions, custom.
--}}
