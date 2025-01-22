
{{--Для работы модалки подтврждения удаления добавить в шаблон кнопку с классом:
confirm-delete-modal

Выполнить подтвержденное действие вызывать по классу:
#confirmDeleteBtn

Пример:
        $('#confirmDeleteBtn').on('click', function () {

        });
--}}




<!-- Модальное окно подтверждения удаления -->
<div class="modal fade confirmDeleteModal" id="confirmDeleteModal" tabindex="-1"
     aria-labelledby="confirmDeleteModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Подтвердите удаление</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Вы уверены, что хотите удалить это поле?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger confirmDeleteBtn" id="confirmDeleteBtn">Удалить</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {

        // Отображение модалки удаления
        $(document).on('click', '.confirm-delete-modal', function () {
            $('#confirmDeleteModal').modal('show'); // Показываем модалку подтверждения
        });

        // Подтверждение удаления внутри модалки
        $('#confirmDeleteBtn').on('click', function () {
                $('#confirmDeleteModal').modal('hide'); // Закрываем модалку
        });
    });
</script>