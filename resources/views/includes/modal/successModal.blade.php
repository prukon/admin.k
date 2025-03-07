
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="dataUpdatedModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dataUpdatedModalLabel">Данные успешно обновлены</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success " id="success-message">
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

    });

    function showSuccessModal(headerText, messageText, confirmCallback) {
        // Подменяем заголовок и текст сообщения
        $('#successModal #dataUpdatedModalLabel').text(headerText);
        $('#successModal #success-message').text(messageText);

        // Сначала убираем все старые обработчики, чтобы при повторных вызовах не накапливались
        $('#btn-success').off('click');

        // Если confirmCallback == 1, то при нажатии "ОК" делаем перезагрузку страницы
        if (confirmCallback === 1) {
            $('#btn-success').on('click', function () {
                location.reload();
            });
        }

        // Показываем модалку
        $('#successModal').modal('show');
    }
</script>