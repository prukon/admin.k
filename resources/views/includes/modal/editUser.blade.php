<!-- Модальное окно редактирования пользователя -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Редактирование пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="edit-user-form" method="post">
                    @csrf
                    @method('patch')

                    <!-- Поле "Имя" -->
                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Имя ученика*</label>
                        <input type="text" name="name" class="form-control" id="edit-name" required>
                    </div>

                    <!-- Поле "Дата рождения" -->
                    <div class="mb-3">
                        <label for="edit-birthday" class="form-label">Дата рождения</label>
                        <input type="date" name="birthday" class="form-control" id="edit-birthday">
                    </div>

                    <!-- Поле "Группа" -->
                    <div class="mb-3">
                        <label for="edit-team" class="form-label">Группа</label>
                        <select class="form-control" id="edit-team" name="team_id">
                            <option value="">Без группы</option>
                            @foreach($allTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Поле "Дата начала занятий" -->
                    <div class="mb-3">
                        <label for="edit-start_date" class="form-label">Дата начала занятий</label>
                        <input type="date" name="start_date" class="form-control" id="edit-start_date">
                    </div>

                    <!-- Поле "Email" -->
                    <div class="mb-3">
                        <label for="edit-email" class="form-label">Адрес электронной почты*</label>
                        <input name="email" type="email" class="form-control" id="edit-email" required>
                    </div>

                    <!-- Поле "Активность" -->
                    <div class="mb-3">
                        <label for="edit-activity" class="form-label">Активность</label>
                        <select name="is_enabled" class="form-control" id="edit-activity">
                            <option value="0">Неактивен</option>
                            <option value="1">Активен</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                </form>
            </div>
        </div>
    </div>
</div>



<script>
    $(document).ready(function () {
        // Открываем модалку и загружаем данные пользователя
        $('.edit-user-link').on('click', function () {
            let userId = $(this).data('id'); // Получаем ID пользователя
            let url = `/admin/users/${userId}/edit`; // Маршрут для получения данных пользователя (GET)
            let updateUrl = `/admin/users/${userId}`; // Маршрут для обновления данных пользователя (PATCH)

            // AJAX-запрос для получения данных пользователя
            $.ajax({
                url: url,
                method: 'GET',
                success: function (response) {
                    console.log(1);
                    // Заполняем поля в модалке
                    $('#edit-name').val(response.user.name);
                    $('#edit-birthday').val(response.user.birthday);
                    $('#edit-team').val(response.user.team_id);
                    $('#edit-start_date').val(response.user.start_date);
                    $('#edit-email').val(response.user.email);
                    $('#edit-activity').val(response.user.is_enabled);

                    // Устанавливаем маршрут для обновления пользователя в форме
                    $('#edit-user-form').attr('action', `/admin/users/${userId}`); // Здесь корректный маршрут для PATCH

                    // Открываем модальное окно
                    $('#editUserModal').modal('show');
                },
                error: function (response) {
                    alert('Ошибка при загрузке данных пользователя');
                }
            });
        });


        $('#edit-user-form').on('submit', function (e) {
            e.preventDefault();

            let form = $(this);
            let url = form.attr('action');

            console.log(url);

            // AJAX-запрос для обновления данных пользователя
            $.ajax({
                url: url,
                method: 'PATCH',
                data: form.serialize(),
                success: function (response) {
                    console.log(2);
                    // Закрываем модалку
                    $('#editUserModal').modal('hide');
                    console.log(3);
                    // Обновляем данные на странице (опционально)
                    location.reload();
                },
                error: function (response) {
                    alert('Ошибка при обновлении данных пользователя');
                }
            });
        });

    });





</script>