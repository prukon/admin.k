{{--
        Вызов модалки удаления в шаблоне
Пример:

  $(document).on('click', '.КЛАСС по которорому вызываем удаление', function () {
            deleteUser();
        });

            function deleteUser() {
            // Показываем модалку с текстом и передаём колбэк, который удалит пользователя
            showConfirmDeleteModal(
                "Удаление пользователя",
                "Вы уверены, что хотите удалить пользователя?",
                function() {
                    //Код выполняемый при удалении

                    $.ajax({
                        url: `/admin/user/${userId}`,
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': token },
                        success: function (response) {
                            if (response.success) {
                                $('#successModal').modal('show');
                                $('#editUserModal').modal('hide');
                            } else {
                                $('#error-message').text('Произошла ошибка при удалении пользователя.');
                                $('#errorModal').modal('show');
                            }
                        },
                        error: function () {
                            $('#error-message').text('Произошла ошибка при удалении пользователя.');
                            $('#errorModal').modal('show');
                        }
                    });
                }
            );
        }
--}}

<script>
// showConfirmDeleteModal(
// "Удаление пользователя",
// "Вы уверены, что хотите удалить пользователя?",
// function() {
//
// });
</script>



<!-- confirmDeleteModal.blade -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Заголовок</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                Текст
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Да</button>
            </div>
        </div>
    </div>
</div>

<script>
    // function showConfirmDeleteModal(headerText, messageText, confirmCallback) {
    //     // Подменяем текст
    //     $('#confirmDeleteModalLabel').text(headerText);
    //     $('#confirmDeleteModal .modal-body').text(messageText);
    //
    //     // Перед тем, как навесить новый обработчик, уберём старые
    //     $('#confirmDeleteBtn').off('click');
    //
    //     // Навешиваем обработчик под текущее действие
    //     $('#confirmDeleteBtn').on('click', function () {
    //         if (typeof confirmCallback === 'function') {
    //             confirmCallback();
    //         }
    //         // Закроем модалку при успехе (или оставим открытой, если надо)
    //         $('#confirmDeleteModal').modal('hide');
    //     });
    //
    //     // Показываем модалку
    //     $('#confirmDeleteModal').modal('show');
    // }

    function showConfirmDeleteModal(headerText, messageText, confirmCallback) {
        const modalElement = document.getElementById('confirmDeleteModal');
        const modalTitle = document.getElementById('confirmDeleteModalLabel');
        const modalBody = modalElement.querySelector('.modal-body');
        const confirmBtn = document.getElementById('confirmDeleteBtn');

        // Подменяем текст заголовка и тела
        if (modalTitle) modalTitle.textContent = headerText;
        if (modalBody) modalBody.textContent = messageText;

        // Удаляем предыдущие обработчики, если были
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        // Назначаем новый обработчик
        newConfirmBtn.addEventListener('click', function () {
            if (typeof confirmCallback === 'function') {
                confirmCallback();
            }
            const modalInstance = bootstrap.Modal.getInstance(modalElement);
            if (modalInstance) {
                modalInstance.hide();
            }
        });

        // Показываем модалку
        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
        modalInstance.show();
    }

</script>
