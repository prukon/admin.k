const modal = event.target; // Получаем текущее модальное окно
const wrapper = document.querySelector('.wrapper'); // Находим элемент wrapper

if (wrapper && modal) {
    wrapper.prepend(modal); // Перемещаем модальное окно в начало wrapper
}




// Вызов модалки логаута
$(document).on('click', '.confirm-logout-modal', function () {
    logoutUser();
});

//Выполнение логаута
function logoutUser() {
    // Показываем модалку с текстом и передаём колбэк, который выполнит выход
    showConfirmDeleteModal(
        "Подтверждение выхода",
        "Вы уверены, что хотите выйти?",
        function () {
            $.ajax({
                url: "{{ route('logout') }}",   // маршрут выхода
                type: "POST",                  // метод запроса
                data: {
                    _token: "{{ csrf_token() }}" // обязательно передаём CSRF-токен
                },
                success: function (response) {
                    // Закрываем модальное окно
                    // $('#deleteConfirmationModal').modal('hide');
                    // Перезагружаем страницу или перенаправляем, если нужно
                    location.reload();
                },
                error: function (xhr) {
                    // alert('Ошибка при попытке выйти.');
                    location.reload();
                }
            });
        }
    );
}


    $.widget.bridge('uibutton', $.ui.button)
