<!--
Для вызова в шаблоне использовать: $('#errorModal').modal('show');
Для изменения тескта ошибки: $('#error-message').text(errorMessage);
Пример:
  $('#errorModal').modal('show');
  $('#error-message').text(response.message || 'Ошибка при создании роли!');


  новый пример:
    error: function(response) {
           eroorRespone(response);
      }


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


    // function eroorRespone(response) {
    //     console.log('eroorRespone');
    //     let message = '';
    //
    //     if (response.responseJSON && response.responseJSON.errors) {
    //         let errorMessages = [];
    //         $.each(response.responseJSON.errors, function (field, messages) {
    //             errorMessages = errorMessages.concat(messages);
    //         });
    //         // Объединяем сообщения, используя символ переноса строки
    //         message = errorMessages.join('\n');
    //     } else {
    //         message = response.responseJSON && response.responseJSON.message
    //             ? response.responseJSON.message
    //             : 'Ошибка при создании пользователя!';
    //     }
    //
    //     $('#errorModal').modal('show');
    //     // Заменяем \n на тег <br> и выводим через .html()
    //     $('#error-message').html(message.replace(/\n/g, '<br>'));
    //
    // }


    function eroorRespone(response) {
        console.log('eroorRespone');
        let message = '';

        if (response.responseJSON && response.responseJSON.errors) {
            let errorMessages = [];
            for (const field in response.responseJSON.errors) {
                if (Array.isArray(response.responseJSON.errors[field])) {
                    errorMessages = errorMessages.concat(response.responseJSON.errors[field]);
                }
            }
            message = errorMessages.join('\n');
        } else {
            message = response.responseJSON && response.responseJSON.message
                ? response.responseJSON.message
                : 'Ошибка при создании пользователя!';
        }

        // Показ модального окна Bootstrap 5
        const modalElement = document.getElementById('errorModal');
        if (modalElement) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
            modalInstance.show();
        }

        // Вставка текста ошибки с <br> вместо \n
        const messageContainer = document.getElementById('error-message');
        if (messageContainer) {
            messageContainer.innerHTML = message.replace(/\n/g, '<br>');
        }
    }


</script>