<!-- Модальное окно логов -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="historyModalLabel">История изменений</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <!-- Обернем таблицу в div с классом table-responsive для мобильной адаптации -->
                <div class="table-responsive">
                    <table id="logsTable" class="display table table-striped w-100">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Действие</th>
                            <th>Автор</th>
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
        $(document).ready(function() {
            // Инициализация DataTables с серверной пагинацией
            $('#logsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: routeName, // URL для получения данных с сервера
                columns: [
                    { data: 'id', name: 'id' },
                    { data: 'action', name: 'action' },
                    { data: 'author', name: 'author' },
                    {
                        data: 'description',
                        name: 'description',
                        render: function(data, type, row) {
                            return data.replace(/\n/g, "<br>"); // Преобразование новых строк в <br>
                        }
                    },
                    { data: 'created_at', name: 'created_at' }
                ],
                order: [[4, 'desc']], // Сортировка по дате создания (последние записи первыми)
                columnDefs: [
                    { width: "40px", targets: 0 }, // ID
                    { width: "150px", targets: 4 } // Дата создания
                ],
                autoWidth: false, // Отключаем автоширину, чтобы вручную заданные стили применялись
                language: {
                    "processing": "Обработка...",
                    "search": "Поиск:",
                    "lengthMenu": "Показать _MENU_ записей",
                    "info": "Записи с _START_ до _END_ из _TOTAL_ записей",
                    "infoEmpty": "Записи с 0 до 0 из 0 записей",
                    "infoFiltered": "(отфильтровано из _MAX_ записей)",
                    "loadingRecords": "Загрузка записей...",
                    "zeroRecords": "Записи отсутствуют.",
                    "emptyTable": "В таблице отсутствуют данные",
                    "paginate": {
                        "first": "Первая",
                        "previous": "Предыдущая",
                        "next": "Следующая",
                        "last": "Последняя"
                    },
                    "aria": {
                        "sortAscending": ": активировать для сортировки столбца по возрастанию",
                        "sortDescending": ": активировать для сортировки столбца по убыванию"
                    }
                }
            });
        });
    }
</script>

<style>
    @media (max-width: 768px) {
        .modal-dialog {
            max-width: 100%;
            margin: 0;
        }
        .modal-content {
            width: 100%;
        }
        .table th, .table td {
            white-space: nowrap;
        }
    }
</style>
