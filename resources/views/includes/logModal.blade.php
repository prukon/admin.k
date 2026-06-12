<!-- Модальное окно логов -->
<div class="modal fade mt-3" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down" style="margin: 0 auto;">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="historyModalLabel">История изменений</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body pl-1 pr-1">
                <table id="logsTable" class="table table-striped dt-columns-managed w-100">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>ID</th>
                        <th>Действие</th>
                        <th>Автор</th>
                        <th>Что меняли</th>
                        <th>Описание</th>
                        <th>Дата создания</th>
                    </tr>
                    </thead>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var logsModalDtApi = null;
    var logsModalRoute = null;

    window.showLogModal = function (routeName) {
        logsModalRoute = routeName;
    };

    $(function () {
        $('#historyModal').on('shown.bs.modal', function () {
            if (!logsModalRoute || !window.KidsCrmDataTable) {
                return;
            }

            if (!logsModalDtApi) {
                logsModalDtApi = KidsCrmDataTable.create('#logsTable', {
                    dataTable: {
                        ajax: {
                            url: logsModalRoute,
                            type: 'GET'
                        },
                        order: [[6, 'desc']],
                        language: @include('partials.datatables.ru')
                    },
                    columns: [
                        { type: 'rownum' },
                        { key: 'id', type: 'id', data: 'id' },
                        { key: 'action', type: 'text', data: 'action' },
                        { key: 'author', type: 'text', data: 'author' },
                        { key: 'target_label', type: 'text', data: 'target_label' },
                        {
                            key: 'description',
                            type: 'text',
                            data: 'description',
                            name: 'description',
                            className: 'dt-col-text',
                            render: function (data, type) {
                                if (!data) {
                                    return '';
                                }
                                if (type !== 'display') {
                                    return data;
                                }
                                return String(data).replace(/\n/g, '<br>');
                            }
                        },
                        {
                            key: 'created_at',
                            type: 'text',
                            data: 'created_at',
                            name: 'created_at',
                            className: 'text-nowrap'
                        }
                    ]
                });
                return;
            }

            logsModalDtApi.table.ajax.url(logsModalRoute).load();
        });
    });
})();
</script>
