<!-- Модальное окно для создания пользователя -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createUserModalLabel">Создание пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="create-user-form" class="text-start" method="post" action="{{ route('admin.user.store') }}">
                @csrf
                <!-- Поля для ввода данных пользователя -->
                    <div class="mb-3">
                        <label for="name" class="form-label">Имя ученика*</label>
                        <input type="text" name="name" class="form-control" id="name">
                    </div>

                    <div class="mb-3">
                        <label for="lastname" class="form-label">Фамилия ученика*</label>
                        <input type="text" name="lastname" class="form-control" id="lastname"
                               value="{{ old('lastname') }}">
                    </div>


                    <div class="mb-3">
                        <label for="birthday" class="form-label">Дата рождения</label>
                        <input type="date" name="birthday" class="form-control" id="birthday">
                    </div>
                    <div class="mb-3">
                        <label for="team" class="form-label">Группа</label>
                        <select name="team_id" id="team" class="form-select">
                            <option value="" selected>Без группы</option>
                            @foreach($allTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email*</label>
                        <input type="email" name="email" class="form-control" id="email">
                    </div>
                    <div class="mb-3 wrap-cur-password">
                        <label for="password" class="form-label">Пароль*</label>
                        <div class="position-relative">
                            <input type="password" id="password" name="password" class="form-control"
                                   placeholder="Пароль">
                            <span toggle="#password" class="fa fa-fw fa-eye field-icon toggle-password"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="is_enabled" class="form-label">Активность</label>
                        <select name="is_enabled" class="form-select" id="is_enabled">
                            <option value="1">Да</option>
                            <option value="0">Нет</option>
                        </select>
                    </div>
                    <!-- Поле "Роль" -->
                    <div class="mb-3">
                        <label for="role_id" class="form-label">Роль</label>
                        <select name="role_id" class="form-select" id="create_role_id">
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->label }}</option>
                            @endforeach
                        </select>
                    </div>


                    <div class="modal-footer-modal-user">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-primary">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Функция показа/скрытия пароля
        function showCurPassword() {
            const togglePassword = document.querySelector('.wrap-cur-password .toggle-password');
            const passwordInput = document.querySelector('.wrap-cur-password #password');

            togglePassword.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        showCurPassword();

        // Обработчик отправки формы создания пользователя
        $('#create-user-form2').on('submit', function (e) {
            e.preventDefault();

            // Очищаем предыдущие ошибки
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').remove();

            $.ajax({
                url: $(this).attr('action'),
                method: $(this).attr('method'),
                data: $(this).serialize(),

                success: function (response) {
                    showSuccessModal("Создание пользователя", "Пользователь успешно создан.", 1);
                },
                error: function (response) {
                    eroorRespone(response);

                }
            });
        });

        $('#create-user-form').on('submit', function (e) {
            e.preventDefault();

            const $form = $(this); // ← вот его и не хватало

            // Стираем прошлые ошибки
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').remove();

            $.ajax({
                url: $form.attr('action'),
                method: $form.attr('method'),
                data: $form.serialize(),
                headers: { 'Accept': 'application/json' }, // чтобы 422 пришёл JSON'ом
                success: function (response) {
                    showSuccessModal("Создание пользователя", "Пользователь успешно создан.", 1);
                    // по желанию: $form[0].reset();
                },
                error: function (xhr) {
                    // Валидация Laravel
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;

                        Object.keys(errors).forEach(function (field) {
                            const messages = errors[field];
                            const safe = field.replace(/\./g, '\\.').replace(/\*/g, '\\*');

                            // Ищем по name="field", если нет — попробуем по id
                            let $input = $form.find('[name="' + safe + '"]');
                            if (!$input.length) $input = $form.find('#' + field);

                            if ($input.length) {
                                $input.addClass('is-invalid');
                                // просто «по-ларевелски»: .invalid-feedback сразу после поля
                                $('<div class="invalid-feedback d-block"></div>')
                                    .text(messages[0])
                                    .insertAfter($input);
                            }
                        });

                        // Фокус на первое неверное поле
                        $form.find('.is-invalid').first().trigger('focus');
                        return;
                    }

                    // Прочие ошибки
                    const msg = xhr.responseJSON?.message || 'Что-то пошло не так. Попробуйте ещё раз.';
                    if (typeof showErrorModal === 'function') {
                        showErrorModal('Ошибка', msg, 1);
                    } else {
                        alert(msg);
                    }
                }
            });
        });


    });
</script>
