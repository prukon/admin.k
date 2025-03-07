<!--
Для вызова в шаблоне использовать: $('#errorModal').modal('show');
Для изменения тескта ошибки: $('#error-message').text(errorMessage);


Пример AJAX ответа:
   error: function (response) {
       let errorMessage = 'Произошла ошибка при сохранении данных.';
       if (response.responseJSON && response.responseJSON.message) {
       errorMessage = response.responseJSON.message; // Используем сообщение с сервера, если оно есть
   }
       $('#error-message').text(errorMessage); // Устанавливаем сообщение ошибки
       $('#errorModal').modal('show');    // Показываем модалку ошибки
   }
-->


<!-- Модальное окно успешного сохранения -->
<div class="modal fade errorModal" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="errorModalLabel">Ошибка</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger" id="error-message">
                    Произошла ошибка при сохранении данных.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btn-modal-error" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Перезагрузка страницы при нажатии "закрыть"
        $('#btn-modal-error').on('click', function () {
            location.reload();
        });
    });
</script>