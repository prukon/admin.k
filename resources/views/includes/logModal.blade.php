<!-- Модальное окно логов -->
<div class="modal fade mt-3" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down" style="margin: 0 auto;"> <!-- Горизонтальное центрирование -->
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="historyModalLabel">История изменений</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body pl-1 pr-1">
                <!-- Обернем таблицу в div с классом table-responsive для мобильной адаптации -->
                <div class="table-responsive">
                    <table id="logsTable" class="display table table-striped w-100">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Действие</th>
                            <th>Автор</th>
{{--                            <th>Пользователь</th>--}}
                            <th>Что меняли</th>
                            <th>Описание</th>
                            <th>Дата создания</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>

    function showLogModal(routeName) {
        // Инициализация DataTables с серверной пагинацией

        $('#historyModal').on('shown.bs.modal', function () {

            if ( ! $.fn.DataTable.isDataTable('#logsTable') ) {

                $('#logsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: routeName, // URL для получения данных с сервера
                    columns: [
                        {data: 'id', name: 'id'},
                        {data: 'action', name: 'action'},
                        {data: 'author', name: 'author' },
                        // {data: 'user', name: 'user' },
                        {data: 'target_label', name: 'target_label' }, // заменили target

                        {data: 'description', name: 'description',
                            render: function (data, type, row) {
                                return data.replace(/\n/g, "<br>"); // Преобразование новых строк в <br>
                            }
                        },
                        {data: 'created_at', name: 'created_at'}
                    ],
                    order: [[5, 'desc']], // Сортировка по дате создания (последние записи первыми)
                    scrollX: true,
                    language: {
                        "processing": "Обработка...",
                        "search": "",
                        "searchPlaceholder": "Поиск...",

                        "lengthMenu": "Показать _MENU_",
                        "info": "С _START_ до _END_ из _TOTAL_ записей",
                        "infoEmpty": "С 0 до 0 из 0 записей",
                        "infoFiltered": "(отфильтровано из _MAX_ записей)",
                        "loadingRecords": "Загрузка записей...",
                        "zeroRecords": "Записи отсутствуют.",
                        "emptyTable": "В таблице отсутствуют данные",
                        "paginate": {
                            "first": "",
                            "previous": "",
                            "next": "",
                            "last": ""
                        },
                        "aria": {
                            "sortAscending": ": активировать для сортировки столбца по возрастанию",
                            "sortDescending": ": активировать для сортировки столбца по убыванию"
                        }
                    }
                }).columns.adjust().draw();
            }
            else {
                    // Если таблица уже есть — просто обновляем данные (если нужно)
                    let dt = $('#logsTable').DataTable();
                    dt.ajax.url(routeName).load();
                    dt.columns.adjust().draw();
                }
        });
    }
</script>
