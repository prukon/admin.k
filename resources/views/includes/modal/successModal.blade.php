
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

{{--Пример:--}}
{{--showSuccessModal("Редактирование пользователя", "Пользователь успешно обновлен.", 1);--}}


<script>


    // function showSuccessModal(headerText, messageText, confirmCallback) {
    //     // Подменяем заголовок и текст сообщения
    //     $('#successModal #dataUpdatedModalLabel').text(headerText);
    //     $('#successModal #success-message').text(messageText);
    //
    //     // Сначала убираем все старые обработчики, чтобы при повторных вызовах не накапливались
    //     $('#btn-success').off('click');
    //
    //     // Если confirmCallback == 1, то при нажатии "ОК" делаем перезагрузку страницы
    //     if (confirmCallback === 1) {
    //         $('#btn-success').on('click', function () {
    //             location.reload();
    //         });
    //     }
    //
    //     // Показываем модалку
    //     $('#successModal').modal('show');
    // }

    function showSuccessModal(headerText, messageText, confirmCallback = 0) {
        const $success = $('#successModal');
        $('#dataUpdatedModalLabel').text(headerText);
        $('#success-message').text(messageText);

        // чтобы не накапливать клики
        $('#btn-success').off('click').one('click', function () {
            if (confirmCallback === 1) location.reload();
        });

        // на всякий случай гарантируем, что модалка не вложена
        $success.appendTo('body');

        // если уже есть открытая модалка — закрываем её и ждём
        const $opened = $('.modal.show').not($success);
        const showSuccess = () =>
            bootstrap.Modal.getOrCreateInstance($success[0], { backdrop: 'static', keyboard: false }).show();

        if ($opened.length) {
            $opened.one('hidden.bs.modal', showSuccess).modal('hide');
        } else {
            showSuccess();
        }
    }
</script>