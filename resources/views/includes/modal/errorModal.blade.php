<!--
Для вызова в шаблоне использовать: $('#errorModal').modal('show');
Для изменения тескта ошибки: $('#error-modal-message').text(errorMessage);
Пример:
  $('#errorModal').modal('show');
  $('#error-modal-message').text(response.message || 'Ошибка при создании роли!');


  новый пример:
    error: function(response) {
           $('#error-modal-message').text(response.message || 'Ошибка при удалении роли!');
           eroorRespone(response);
      }




Пример AJAX ответа:
   error: function (response) {
       let errorMessage = 'Произошла ошибка при сохранении данных.';
       if (response.responseJSON && response.responseJSON.message) {
       errorMessage = response.responseJSON.message; // Используем сообщение с сервера, если оно есть
   }
       $('#error-modal-message').text(errorMessage); // Устанавливаем сообщение ошибки
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
                <div class="alert alert-danger" id="error-modal-message">
                    Произошла ошибка при сохранении данных.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btn-modal-error" data-bs-dismiss="modal">Закрыть
                </button>
            </div>
        </div>
    </div>
</div>

<script>

    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('btn-modal-error');

        if (btn) {
            btn.addEventListener('click', function () {
                location.reload();
            });
        }
    });


    function eroorRespone(response) {
        // 1) Собираем сообщение
        let message = 'Ошибка при создании пользователя!';
        if (response?.responseJSON?.errors) {
            message = Object.values(response.responseJSON.errors).flat().join('\n');
        } else if (response?.responseJSON?.message) {
            message = response.responseJSON.message;
        }

        // 2) Берём ИМЕННО ту модалку, что будем показывать (из <body>)
        const modalEl = document.querySelector('body > #errorModal') || document.getElementById('errorModal');
        if (!modalEl) return;

        // 3) Обновляем текст ВНУТРИ этой модалки
        const msgEl = modalEl.querySelector('#error-modal-message');
        if (msgEl) {
            // безопасно экранируем + переводим \n в <br>
            const esc = (s) => String(s).replace(/[&<>"']/g, m => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[m]));
            msgEl.innerHTML = esc(message).replace(/\n/g, '<br>');
        }

        // 4) Гарантируем, что модалка — прямой ребёнок body (если вдруг включили внутрь другой)
        document.body.appendChild(modalEl);

        // 5) Показываем ТОЛЬКО через очередь, без прямого .show()
        showModalQueued('errorModal', {backdrop: 'static', keyboard: false});
    }


</script>