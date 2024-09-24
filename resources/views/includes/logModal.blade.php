<!-- Модальное окно логов -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="historyModalLabel">История изменений</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <!-- Таблица для отображения логов -->
                <table id="logsTable" class="display table table-striped w-100">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Тип</th>
                        <th>Автор</th>
                        <th>Описание</th>
                        <th>Дата создания</th>
                    </tr>
                    </thead>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Инициализация DataTables с серверной пагинацией
        $('#logsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('logs.data') }}", // URL для получения данных с сервера
            columns: [
                { data: 'id', name: 'id' },
                { data: 'type', name: 'type' },
                { data: 'author', name: 'author' },
                { data: 'description', name: 'description' },
                { data: 'created_at', name: 'created_at' }
            ],
            order: [[4, 'desc']], // Сортировка по дате создания (последние записи первыми)

            // Задаем ширину для столбца ID
            columnDefs: [
                { width: "40px", targets: 0 }, // Устанавливаем ширину 50px для первого столбца
                { width: "150px", targets: 4 } // Устанавливаем ширину 50px для первого столбца

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
</script>
