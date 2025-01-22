<!-- Модальное окно успешного сохранения -->
{{--id="dataUpdatedModal"--}}
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="dataUpdatedModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dataUpdatedModalLabel">Данные успешно обновлены</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success">
                    Данные были успешно обновлены.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btn-success" data-bs-dismiss="modal">ОК</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Перезагрузка страницы при нажатии "ОК" в модалке успешного обновления
        $('#btn-success').on('click', function () {
            location.reload(); // Перезагружаем страницу
        });
    });
</script>