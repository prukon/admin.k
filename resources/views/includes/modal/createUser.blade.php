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
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create-name" class="form-label">Имя ученика*</label>
                                <input type="text" name="name" class="form-control" id="create-name">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create-lastname" class="form-label">Фамилия ученика*</label>
                                <input type="text"
                                       name="lastname"
                                       class="form-control"
                                       id="create-lastname"
                                       value="{{ old('lastname') }}">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create-birthday" class="form-label">Дата рождения</label>
                                <input type="date" name="birthday" class="form-control" id="create-birthday">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create-team" class="form-label">Группа</label>
                                <select name="team_id" id="create-team" class="form-select">
                                    <option value="" selected>Без группы</option>
                                    @foreach($allTeams as $team)
                                        <option value="{{ $team->id }}">{{ $team->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        @can('locations.view')
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create-location" class="form-label">Локация</label>
                                <select name="location_id" id="create-location" class="form-select">
                                    <option value="" selected>Без локации</option>
                                    @foreach($activeLocations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @endcan

                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create-email" class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" id="create-email">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            @php $canPhone = auth()->user()->can('users.phone.update'); @endphp
                            <div class="mb-3">
                                <label for="create-phone" class="form-label">Телефон</label>

                                <input
                                    type="tel"
                                    class="form-control"
                                    id="create-phone"
                                    name="phone"
                                    placeholder="+7 (XXX) XXX-XX-XX"
                                    data-can-phone="{{ $canPhone ? 1 : 0 }}"
                                    @unless($canPhone) disabled aria-disabled="true" @endunless
                                >

                                @unless($canPhone)
                                    <div class="form-text text-muted mt-1">
                                        <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение телефона
                                    </div>
                                @endunless
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="mb-3 wrap-cur-password">
                                <label for="create-password" class="form-label">Пароль</label>
                                <div class="position-relative">
                                    <input type="password"
                                           id="create-password"
                                           name="password"
                                           class="form-control"
                                           placeholder="Пароль">
                                    <span toggle="#create-password" class="fa fa-fw fa-eye field-icon toggle-password"></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create-is_enabled" class="form-label">Активность</label>
                                <select name="is_enabled" class="form-select" id="create-is_enabled">
                                    <option value="1">Да</option>
                                    <option value="0">Нет</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create_role_id" class="form-label">Роль</label>
                                <select name="role_id" class="form-select" id="create_role_id">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    @if(!empty($userFieldsPayload))
                        <div id="create-custom-fields-container" class="border-top pt-3 mt-2">
                            <div class="small text-muted mb-2">Дополнительные поля</div>
                            @foreach($userFieldsPayload as $field)
                                <div class="mb-3 custom-field" data-slug="{{ $field['slug'] }}">
                                    <label for="create-custom-{{ $field['slug'] }}" class="form-label">{{ $field['name'] }}</label>
                                    <input
                                        type="text"
                                        name="custom[{{ $field['slug'] }}]"
                                        class="form-control"
                                        id="create-custom-{{ $field['slug'] }}"
                                        value=""
                                        @unless(!empty($field['editable'])) disabled @endunless
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endif

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
            const passwordInput = document.querySelector('.wrap-cur-password #create-password');

            togglePassword.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        showCurPassword();

        // Телефон: та же маска, что и в редактировании
        const $phone = $('#create-phone');
        if ($phone.length && $phone.inputmask) {
            $phone.inputmask("+7 (999) 999-99-99");
        }

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
                    window.location.reload();
                },
                error: function (xhr) {
                    // Валидация Laravel
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;

                        Object.keys(errors).forEach(function (field) {
                            const messages = errors[field];

                            let $input = $();
                            const customMatch = /^custom\.(.+)$/.exec(field);
                            if (customMatch) {
                                const slug = customMatch[1];
                                $input = $form.find('[name="custom[' + slug + ']"]');
                            }
                            if (!$input.length) {
                                const safe = field.replace(/\./g, '\\.').replace(/\*/g, '\\*');
                                $input = $form.find('[name="' + safe + '"]');
                            }
                            if (!$input.length) {
                                $input = $form.find('#' + field.replace(/\./g, '\\.'));
                            }

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
