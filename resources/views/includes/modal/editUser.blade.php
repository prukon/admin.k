{{--Cropie--}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css">
<link rel="stylesheet" href="{{ asset('css/croppie.css') }}">
<script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>

<!-- Модальное окно редактирования пользователя -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog ">
        <div class="modal-content background-color-grey">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Редактирование пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="edit-user-form" class="text-start" method="post">
                @csrf
                @method('patch')

                <!-- Блок для аватарки -->
                    <div class="mb-3 d-flex flex-column align-items-center">
                        <div>
                            <div class="avatar_wrapper d-flex align-items-center justify-content-center">
                                {{--                                <span>--}}
                                <img id="confirm-img" src="/img/default.png" alt="Аватар по умолчанию"
                                     class="img-thumbnail">
                                {{--                            </span>--}}
                            </div>
                        </div>
                        <div class="avatar-menu mt-3">
                            <ul class=" ">
                                {{--                                <li class="pl-3 pr-3 pt-1 pb-1">--}}
                                {{--                                    <i class="fa-regular fa-image mr-2"></i>--}}
                                {{--                                    <a href="#" id="open-photo">Открыть фотографию</a>--}}
                                {{--                                </li>--}}
                                <li class="pl-3 pr-3 pt-1 pb-1">
                                    <i class="fa-regular fa-pen-to-square mr-2"></i>
                                    <a href="#" id="update-photo">Обновить фотографию</a>
                                </li>
                                <li class="pl-3 pr-3 pt-1 pb-1">
                                    <i class="fa-regular fa-trash-can mr-2"></i>
                                    <a href="#" disabled id="delete-photo">Удалить фотографию</a>
                                </li>
                            </ul>
                        </div>

                    </div>

                    <!-- Поле "Имя" -->


                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Имя ученика *</label>
                        <input type="text"
                               name="name"
                               class="form-control"
                               id="edit-name"
                               @cannot('users-name-update') disabled aria-disabled="true" @endcannot>
                    </div>

                    <!-- Поле "Дата рождения" -->
                                     <div class="mb-3">
                        <label for="edit-birthday" class="form-label">Дата рождения</label>
                        <input
                                type="date"
                                name="birthday"
                                id="edit-birthday"
                                class="form-control"
                                @cannot('users-birthdate-update') disabled aria-disabled="true" @endcannot
                        >
                        @cannot('users-birthdate-update')
                            <div class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i>Нет прав на изменение даты рождения</div>
                        @endcannot
                    </div>

                    <!-- Поле "Группа" -->
                                  <div class="mb-3">
                        <label for="edit-team" class="form-label">Группа</label>
                        <select
                                id="edit-team"
                                name="team_id"
                                class="form-select"
                                @cannot('users-group-update') disabled aria-disabled="true" @endcannot
                        >
                            <option value="">Без группы</option>
                            @foreach($allTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>

                        @cannot('users-group-update')
                            <div class="form-text text-muted">
                                <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение группы
                            </div>
                        @endcannot
                    </div>





                    <!-- Поле "Дата начала занятий" -->
                    <div class="mb-3">
                        <label for="edit-start_date" class="form-label">Дата начала занятий</label>
                        <input
                                type="date"
                                id="edit-start_date"
                                name="start_date"
                                class="form-control"
                                @cannot('users-startDate-update') disabled aria-disabled="true" @endcannot
                        >
                        @cannot('users-startDate-update')
                            <div class="form-text text-muted">
                                <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение даты начала
                            </div>
                        @endcannot
                    </div>



                @if($fields->isNotEmpty()) <!-- Проверяем, есть ли пользовательские поля -->
                    <div class="mb-3">
                        {{--<h5>Пользовательские поля</h5>--}}
                        <div id="custom-fields-container"> <!-- Контейнер для пользовательских полей -->


                        </div>
                    </div>
                @endif

                <!-- Поле "Email" -->
                    <div class="mb-3">
                        <label for="edit-email" class="form-label">Адрес электронной почты*</label>
                        <input
                                type="email"
                                id="edit-email"
                                name="email"
                                class="form-control"
                                required
                                @cannot('users-email-update') disabled aria-disabled="true" @endcannot
                        >
                        @cannot('users-email-update')
                            <div class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i>Нет прав на изменение email</div>
                        @endcannot
                    </div>



                    {{-- Поле "Телефон" --}}
                    {{-- Поле "Телефон" --}}
                    @php $canPhone = auth()->user()->can('users-phone-update'); @endphp

                    <div class="mb-3">
                        <label for="edit-phone" class="form-label">Телефон</label>

                        <div class="input-group">
                            <input
                                    type="tel"
                                    class="form-control"
                                    id="edit-phone"
                                    name="phone"
                                    value="{{ old('phone', $user->phone) }}"
                                    placeholder="+7 (___) ___-__-__"
                                    data-original="{{ $user->phone ?? '' }}"
                                    data-verified="{{ $user->phone_verified_at ? 1 : 0 }}"
                                    @unless($canPhone) disabled aria-disabled="true" @endunless
                            >

                            {{-- Индикатор статуса (галка/крест) --}}
                            <span id="phone-verify-icon" class="input-group-text d-none">
            <i class="fa-solid fa-circle-check"></i>
        </span>
                        </div>

                        @php
                            $verifiedAt = $user->phone_verified_at ? \Carbon\Carbon::parse($user->phone_verified_at) : null;
                        @endphp
                        <small
                                id="phone-verify-status"
                                class="small {{ $verifiedAt ? 'text-success' : 'd-none' }}"
                                data-verified-at="{{ $verifiedAt ? $verifiedAt->format('Y-m-d H:i:s') : '' }}"
                        >
                            @if($verifiedAt)
                                Подтверждён {{ $verifiedAt->format('d.m.Y H:i') }}
                            @endif
                        </small>

                        @unless($canPhone)
                            <div class="form-text text-muted mt-1">
                                <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение телефона
                            </div>
                        @endunless
                    </div>




                    <!-- Поле "Активность" -->
                    <div class="mb-3">
                        <label for="edit-activity" class="form-label">Активность</label>
                        <select
                                id="edit-activity"
                                name="is_enabled"
                                class="form-select"
                                @cannot('users-activity-update') disabled aria-disabled="true" @endcannot
                        >
                            <option value="0">Неактивен</option>
                            <option value="1">Активен</option>
                        </select>
                        @cannot('users-activity-update')
                            <div class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i>Нет прав на изменение активности</div>
                        @endcannot
                    </div>

                    <!-- Поле "Роль" -->
                    <div class="mb-3">
                        <label for="role_id" class="form-label">Роль</label>
                        <select
                                id="role_id"
                                name="role_id"
                                class="form-select"
                                @cannot('users-role-update') disabled aria-disabled="true" @endcannot
                        >
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}"
                                        @if(($editingUser->role_id ?? $user->role_id ?? null) === $role->id) selected @endif>
                                    {{ $role->label }}
                                </option>
                            @endforeach
                        </select>
                        @cannot('users-role-update')
                            <div class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i>Нет прав на изменение роли</div>
                        @endcannot
                    </div>


                    <!-- Блок изменения пароля -->
                    <div class="buttons-wrap change-pass-wrap" id="change-pass-wrap" style="display: none;">
                        <div class="d-flex align-items-center mt-3">
                            <div class="position-relative wrap-change-password">
                                <input type="password" id="new-password" class="form-control"
                                       placeholder="Новый пароль">
                                <span toggle="#new-password" class="fa fa-fw fa-eye field-icon toggle-password"></span>
                            </div>
                            <button type="button" id="apply-password-btn" class="btn btn-primary ml-2">Применить
                            </button>
                            <button type="button" id="cancel-change-password-btn" class="btn btn-danger ml-2">Отмена
                            </button>
                        </div>
                        <div id="error-message" class="text-danger mt-2" style="display:none;">Пароль должен быть не
                            менее 8 символов
                        </div>
                    </div>

                    <!-- Кнопка для изменения пароля -->
                    {{--<div class="button-group buttons-wrap mt-3">--}}
                        {{--<button type="button" id="change-password-btn" class="btn btn-primary  change-password-btn">--}}
                            {{--Изменить пароль--}}
                        {{--</button>--}}
                    {{--</div>--}}

                    @php $canChange = auth()->user()->can('users-password-update'); @endphp

                    <div class="button-group buttons-wrap mt-3">
                        <button type="button"
                                id="change-password-btn"
                                class="btn btn-primary change-password-btn {{ $canChange ? '' : 'opacity-50 pe-none' }}"
                                @unless($canChange)
                                aria-disabled="true"
                                tabindex="-1"
                                data-bs-toggle="tooltip"
                                title="Нет прав на изменение пароля"
                                @endunless
                        >
                            <i class="fa-solid fa-key me-1"></i> Изменить пароль
                        </button>

                        @unless($canChange)
                            <div class="form-text text-muted mt-2">
                                <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение пароля
                            </div>
                        @endunless
                    </div>

                    @unless($canChange)
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const btn = document.getElementById('change-password-btn');
                                if (btn) new bootstrap.Tooltip(btn);
                            });
                        </script>
                    @endunless






                <!-- Кнопка для сохранения данных -->
                    <button type="submit" class="btn btn-primary mt-3 save-change-modal">Сохранить изменения</button>
                    <!-- Кнопка для сохранения данных -->

                    {{--<button type="submit" class="btn btn-danger mt-3 save-change-modal">Удалить</button>--}}
                    <button type="button" id="delete-user-btn" class="btn btn-danger mt-3 confirm-delete-modal">
                        Удалить
                    </button>

                </form>
            </div>

        </div>
    </div>
</div>

<!-- Модалка для редактирования аватарки -->
{{--<div class="modal fade" id="uploadPhotoModal" tabindex="-1" aria-labelledby="uploadPhotoModalLabel" aria-hidden="true">--}}
<div class="modal fade" id="editPhotoModal" tabindex="-1" aria-labelledby="uploadPhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog ">
        <div class="modal-content background-color-grey">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadPhotoModalLabel">Редактирование аватарки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Поле для загрузки изображения -->
                <input type="file" id="upload" accept="image/*" class="form-control mb-3">

                <!-- Контейнер для Croppie -->
                <div id="upload-demo"></div>

                <!-- Кнопка для сохранения изображения -->
                <button id="saveImageBtn" class="btn btn-primary mt-3">Сохранить фото</button>
            </div>
        </div>
    </div>
</div>

<script>

    $(document).ready(function () {

        var $uploadCrop = null;

        // Функция инициализации Croppie
        function initializeCroppie() {
            if ($uploadCrop) {
                console.log('Croppie уже инициализирован, пропускаем инициализацию.');
                return;
            }
            $uploadCrop = $('#upload-demo').croppie({
                viewport: {width: 138, height: 170, type: 'square'},
                boundary: {width: 300, height: 300},
                showZoomer: true
            });
            console.log('Croppie успешно инициализирован.');
        }

        // Проверка, если аватарка дефолтная, отключаем кнопку "Удалить фотографию"
        function toggleDeleteButton() {
            const defaultAvatarUrl = '/img/default.png';
            const currentAvatarUrl = $('#confirm-img').attr('src');
            const deletePhotoButton = $('#delete-photo');

            if (currentAvatarUrl === defaultAvatarUrl) {
                deletePhotoButton.addClass('disabled').attr('disabled', true);
            } else {
                deletePhotoButton.removeClass('disabled').attr('disabled', false);
            }
        }

        // Функция редактирования пользователя
        function editMidalUser() {

            // Функция для показа/скрытия пароля с помощью иконки глаза  fix
            function showPassword() {
                const togglePassword = document.querySelector('.wrap-change-password .toggle-password');
                const passwordInput = document.querySelector('.wrap-change-password #new-password');

                togglePassword.addEventListener('click', function () {
                    // Переключаем тип input между 'password' и 'text'
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    // Меняем иконку глаза
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }

            showPassword();

            // При выборе файла изображения загружаем его в Croppie
            $('#upload').on('change', function () {
                var reader = new FileReader();
                reader.onload = function (e) {
                    console.log('Загружен файл для Croppie:', e.target.result);
                    $uploadCrop.croppie('bind', {
                        url: e.target.result
                    }).then(function () {
                        console.log('Croppie bind complete');
                    });
                };
                reader.readAsDataURL(this.files[0]);
            });

            // Открытие модалки для выбора фото (без закрытия основной модалки)
            $('#upload-photo').on('click', function (e) {
                e.preventDefault(); // Предотвращаем закрытие основной модалки
                console.log('Открыта вторая модалка для загрузки аватарки');


                // Получаем текущий URL аватарки
                var currentAvatarUrl = $('#confirm-img').attr('src');
                // var currentAvatarUrl = '/img/default.png'   ;
                console.log('Текущий URL аватарки:', currentAvatarUrl);

                $uploadCrop.croppie('bind', {
                    url: '/img/white.jpg'
                })

                // $('#upload-demo').css('background-color', '#ffffff');


                // Если аватарка не является изображением по умолчанию, загружаем её в Croppie
                if (currentAvatarUrl && currentAvatarUrl !== '/img/default.png') {
                    $uploadCrop.croppie('bind', {
                        url: currentAvatarUrl
                    }).then(function () {
                        console.log('Текущая аватарка успешно загружена в Croppie.');
                    }).catch(function (error) {
                        console.error('Ошибка загрузки текущей аватарки в Croppie:', error);
                    });
                }


                $('#editUserModal').css('opacity', '0.5'); // Уменьшаем прозрачность основной модалки
                $('#uploadPhotoModal').modal({
                    backdrop: 'static',  // Запрещаем закрытие модалки при клике вне её
                    keyboard: false      // Отключаем закрытие модалки по клавише Esc
                }).modal('show'); // Открываем модалку для выбора фото

            });


            function closeUploadAvatarModal2() {
                console.log("closeUploadAvatarModal2()");
                console.log('Модалка Croppie закрыта, восстанавливаем прозрачность основной модалки');
                $('#editUserModal').css('opacity', '1'); // Восстанавливаем прозрачность основной модалки
            }


            // Закрытие второй модалки
            $('#uploadPhotoModal').on('hidden.bs.modal', function () {
                closeUploadAvatarModal2();
            });

            // Обработчик сохранения фото
            $('#saveImageBtn').on('click', function () {
                console.log('Клик по кнопке "Сохранить фото" во второй модалке.');

                $uploadCrop.croppie('result', {
                    type: 'canvas',
                    size: 'viewport'
                }).then(function (resp) {
                    console.log('Base64 данных изображения:', resp);

                    var action = $('#edit-user-form').attr('action');
                    // console.log('Action формы:', action);

                    if (action) {
                        let userId = action.split('/').pop();
                        console.log('User ID:', userId);

                        let token = $('input[name="_token"]').val();

                        $.ajax({
                            url: `/admin/user/${userId}/update-avatar`,
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': token
                            },
                            data: {
                                avatar: resp
                            },
                            success: function (response) {
                                console.log('Ответ сервера:', response);
                                try {
                                    if (response.success) {
                                        // Обновляем аватарку в модалке
                                        let newAvatarUrl = response.avatar_url + '?' + new Date().getTime();
                                        $('#confirm-img').attr('src', newAvatarUrl);
                                        console.log('URL изображения после обновления:', $('#confirm-img').attr('src'));
                                        showSuccessModal("Обновление аватара", "Аватар успешно обновлен.");


                                        // Закрытие второй модалки и возврат к основной модалке
                                        $('#editPhotoModal').modal('hide');
                                        $('#editUserModal').css('opacity', '1').modal('show');
                                    } else {
                                        // console.error('Ответ сервера не содержит success:', response);
                                        // alert('Не удалось обновить аватар. Ошибка ответа.');
                                        $('#errorModal').modal('show');
                                    }
                                } catch (error) {
                                    $('#errorModal').modal('show');
                                    // console.error('Ошибка обработки ответа от сервера:', error);
                                }
                            },
                            error: function (xhr, status, error) {
                                // console.error('Ошибка при загрузке аватарки:', error);
                                // console.log('Статус ответа:', status);
                                // console.log('Ответ сервера:', xhr.responseText);
                                // alert('Ошибка при загрузке аватарки. Проверьте лог сервера.');
                                $('#errorModal').modal('show');
                            }
                        });
                    } else {
                        console.error('Action attribute отсутствует или пустой в форме.');
                    }
                }).catch(function (error) {
                    console.error('Ошибка при обработке изображения Croppie:', error);
                });
                // closeUploadAvatarModal();
            });

            // Показать/скрыть изменение пароля
            $('#change-password-btn').on('click', function () {
                $('#change-password-btn').hide();
                $('#change-pass-wrap').show();
            });

            // Применение нового пароля
            $('#apply-password-btn').on('click', function () {
                var userId = $('#edit-user-form').attr('action').split('/').pop();
                console.log('Применение нового пароля для пользователя с ID:', userId);
                var newPassword = $('#new-password').val();
                var token = $('input[name="_token"]').val();
                var errorMessage = $('#error-modal-message');

                // Проверка длины пароля
                if (newPassword.length < 8) {
                    errorMessage.show(); // Показываем сообщение об ошибке
                    return; // Прерываем выполнение, если пароль слишком короткий
                } else {
                    errorMessage.hide(); // Скрываем сообщение об ошибке
                }

                $.ajax({
                    url: `/admin/user/${userId}/update-password`,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token
                    },
                    data: {
                        password: newPassword
                    },
                    success: function (response) {
                        console.log('Ответ сервера на обновление пароля:', response);
                        if (response.success) {
                            $('#change-password-btn').show();
                            $('#change-pass-wrap').hide();
                            $('#password-change-message').show().delay(3000).fadeOut();
                            showSuccessModal("Обновление пароля", "Пароль успешно обновлен.");
                        }
                    },
                    // error: function () {
                    //     $('#errorModal').modal('show');
                    // }

                    error: function (response) {
                        let errorMessage = 'Произошла ошибка при сохранении данных.';
                        if (response.responseJSON && response.responseJSON.message) {
                            errorMessage = response.responseJSON.message; // Используем сообщение с сервера, если оно есть
                        }
                        $('#error-modal-message').text(errorMessage); // Устанавливаем сообщение ошибки
                        $('#errorModal').modal('show');    // Показываем модалку ошибки
                    }
                });
            });

            // Отмена изменения пароля
            $('#cancel-change-password-btn').on('click', function () {
                $('#change-password-btn').show();
                $('#change-pass-wrap').hide();
                $('#error-message').hide();
            });

        }

        //Клик по обновить фотографию
        function clickToUpdatePhoto() {
            document.getElementById('update-photo').addEventListener('click', function (e) {
                e.preventDefault();
                // Открытие модального окна для обновления аватарки
                // $('#uploadPhotoModal').modal('show');
                $('#editPhotoModal').modal('show');
            });
        }

        // Показывать контекстое меню при наведении
        function showContexMenu() {
            let menuTimeout;

            // Показываем меню при наведении на аватар
            $('.avatar_wrapper').hover(
                function () {
                    clearTimeout(menuTimeout);
                    $(document).find('.avatar-menu').show();
                },
                function () {
                    menuTimeout = setTimeout(() => {
                        $(document).find('.avatar-menu').hide();
                    }, 300); // добавляем небольшую задержку
                }
            );

            // Устанавливаем обработчики для меню, чтобы оно не исчезало при наведении
            $('.avatar-menu').hover(
                function () {
                    clearTimeout(menuTimeout); // отменяем таймер скрытия
                    $(document).show();
                },
                function () {
                    menuTimeout = setTimeout(() => {
                        $(document).hide();
                        $(document).find('.avatar-menu').hide();
                    }, 300); // добавляем небольшую задержку перед скрытием
                }
            );
        }

        //Удаление аватарки
        function deteleAvatar() {
            document.getElementById('delete-photo').addEventListener('click', function (e) {
                e.preventDefault();


                showConfirmDeleteModal(
                    "Удаление аватарки",
                    "Вы уверены, что хотите удалить аватарку?",
                    function () {
                        // Подтверждение удаления фотографии
                        // if (confirm('Вы уверены, что хотите удалить фотографию?')) {
                        let userId = $('#edit-user-form').attr('action').split('/').pop(); // Получаем ID пользователя
                        let token = $('input[name="_token"]').val();

                        $.ajax({
                            url: `/admin/user/${userId}/delete-avatar`, // Указываем маршрут для удаления аватарки
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': token
                            },
                            success: function (response) {
                                if (response.success) {
                                    $('#confirm-img').attr('src', '/img/default.png'); // Устанавливаем аватарку по умолчанию
                                    showSuccessModal("Удаление аватара", "Аватар успешно удален.");
                                    toggleDeleteButton();
                                } else {
                                    // alert('Ошибка удаления аватарки');
                                    $('#errorModal').modal('show');
                                }
                            },
                            error: function () {
                                // alert('Ошибка удаления аватарки. Проверьте лог сервера.');
                                $('#errorModal').modal('show');
                            }
                        });
                        // }
                    });


            });
        }

        // ОКРЫТЫТЬ МОДАЛКУ ЮЗЕРА и загружаем его данные для редактирования UserController edit
        function editUserLink() {
            $('.edit-user-link').on('click', function () {
                let userId = $(this).data('id'); // Получаем ID пользователя
                console.log('Открываем модалку для редактирования пользователя с ID:', userId);

                // AJAX-запрос для получения данных пользователя
                $.ajax({
                    url: `/admin/users/${userId}/edit`,
                    method: 'GET',

                    success: function (response) {
                        const current = response.currentUser;
                        const isSuperadmin = current.isSuperadmin;
                        console.log('\\Log full response =', response);

                        // 1) Заполняем стандартные поля
                        $('#edit-user-form #edit-name').val(response.user.name);
                        $('#edit-user-form #edit-birthday').val(response.user.birthday);
                        $('#edit-user-form #edit-team').val(response.user.team_id);
                        $('#edit-user-form #edit-start_date').val(response.user.start_date);
                        $('#edit-user-form #edit-email').val(response.user.email);
                        $('#edit-user-form #edit-phone').val(response.user.phone);
                        $('#edit-user-form #edit-activity').val(response.user.is_enabled);

                        // 2) Рисуем <option> для ролей из response.roles
                        const roleSelect = $('#edit-user-form #role_id');
                        roleSelect.empty();
                        response.roles.forEach(function (role) {
                            roleSelect.append(
                                $('<option>', { value: role.id, text: role.label })
                            );
                        });
                        roleSelect.val(response.user.role_id);

                        // 3) Устанавливаем action формы
                        $('#edit-user-form').attr('action', `/admin/users/${response.user.id}`);

                        // 4) Отображаем аватарку
                        if (response.user.image_crop) {
                            $('#confirm-img').attr('src', `/storage/avatars/${response.user.image_crop}`);
                        } else {
                            $('#confirm-img').attr('src', '/img/default.png');
                        }
                        toggleDeleteButton();

                        // 5) Заполняем кастомные поля
                        const container = $('#custom-fields-container');
                        container.empty();

                        const currentRoleId = response.currentUser.role_id;
                        console.log('\\Log currentRoleId =', currentRoleId);

                        response.fields.forEach(function (field, idx) {
                            // 1) Значение поля для текущего пользователя
                            let userValue = '';
                            if (response.user.fields) {
                                const uf = response.user.fields.find(uf => uf.slug === field.slug);
                                if (uf) userValue = uf.pivot.value || '';
                            }



                            // 2) Проверяем доступ по pivot‑таблице user_field_role
                            //    (field.roles — массив ID ролей)
                            // console.log(`\\Log [Field ${field.slug}] roles =`, field.roles);
                            console.log(`\Log [Field ${field.slug}] editable =`, field.editable); // Изменено: логируем для отладки


                            const roles = Array.isArray(field.roles) ? field.roles : [];
                            // const hasAccess = isSuperadmin || roles.includes(current.role_id);


                            // let hasAccess = Array.isArray(field.roles) && field.roles.includes(currentRoleId);
                            // console.log(`\\Log [Field ${field.slug}] hasAccess =`, hasAccess);



                            // let disabledAttr = hasAccess ? '' : 'disabled';

                            const disabledAttr = field.editable ? '' : 'disabled'; // Изменено: вместо расчёта по role_id


                            // 3) Генерируем HTML
                            const html = `
            <div class="mb-3 custom-field" data-slug="${field.slug}">
                <label for="custom-${field.slug}" class="form-label">${field.name}</label>
                <input
                    type="text"
                    name="custom[${field.slug}]"
                    class="form-control"
                    id="custom-${field.slug}"
                    value="${userValue}"
                    ${disabledAttr}
                />
            </div>
        `;
                            container.append(html);
                        });

                        // 6) Открываем модалку
                        $('#editUserModal').modal('show');
                    },

                    error: function () {
                        console.error('Ошибка при загрузке данных пользователя');
                    }
                });
            });
        }

        // ОТПРАВКА AJAX.-> /UserController->Update Обработчик обновления данных пользователя
        function editUserForm() {
            $('#edit-user-form').on('submit', function (e) {
                e.preventDefault();

                let form = $(this);
                let url = form.attr('action');

                console.log('Отправляем форму для обновления пользователя с URL:', url);
                console.log('form.serialize():' + form.serialize());

                // AJAX-запрос для обновления данных пользователя
                $.ajax({
                    url: url,
                    method: 'PATCH',
                    data: form.serialize(),
                    success: function (response) {
                        showSuccessModal("Редактирование пользователя", "Пользователь успешно обновлен.", 1);
                        console.log(response);
                    },
                    error: function (response) {
                        eroorRespone(response);
                    }
                });
            });
        }


        function editUserForm2() {
            $('#edit-user-form')
                .off('submit.edit') // чтобы не дублировались хендлеры
                .on('submit.edit', function (e) {
                    e.preventDefault();

                    const $form  = $(this);
                    const url    = $form.attr('action');
                    const method = 'PATCH';

                    // 1) Что реально отправится как строка (как у вас)
                    const qs = $form.serialize();

                    // 2) Те же данные в виде объекта (нагляднее)
                    const obj = {};
                    $form.serializeArray().forEach(({ name, value }) => {
                        // поддержка массивов: name[]
                        if (name.endsWith('[]')) {
                            const key = name.slice(0, -2);
                            (obj[key] ||= []).push(value);
                        } else if (obj[name] !== undefined) {
                            obj[name] = [].concat(obj[name], value);
                        } else {
                            obj[name] = value;
                        }
                    });

                    // 3) Лог чекбоксов (важно: неотмеченные НЕ попадают в serialize())
                    const checkboxes = $form.find('input[type="checkbox"]').map((_, el) => ({
                        name: el.name,
                        checked: el.checked,
                        value: el.value
                    })).get();

                    console.groupCollapsed(`[EDIT USER] ${method} ${url}`);
                    console.log('Query string:', qs);
                    console.table($form.serializeArray());   // таблицей — наглядно
                    console.log('Как объект:', obj);
                    console.table(checkboxes);
                    console.groupEnd();

                    $.ajax({
                        url: url,
                        type: method,
                        data: qs, // то же, что и раньше
                        headers: {
                            // если в meta уже есть токен — пробросим его
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        beforeSend: function (jqXHR, settings) {
                            console.groupCollapsed('[AJAX beforeSend]');
                            console.log('settings.type:', settings.type);
                            console.log('settings.url:', settings.url);
                            console.log('settings.data:', settings.data);
                            console.groupEnd();
                        },
                        success: function (response, status, xhr) {
                            console.log('[AJAX success]', { status: xhr.status, response });
                            showSuccessModal("Редактирование пользователя", "Пользователь успешно обновлен.", 1);
                        },
                        error: function (xhr) {
                            console.warn('[AJAX error]', { status: xhr.status, text: xhr.responseText });
                            eroorRespone(xhr);
                        },
                        complete: function (xhr, status) {
                            console.log('[AJAX complete]', status);
                        }
                    });
                });
        }




        // Вызов модалки удаления
        $(document).on('click', '.confirm-delete-modal', function () {
            deleteUser();
        });

        function deleteUser() {
            // Показываем модалку с текстом и передаём колбэк, который удалит пользователя
            showConfirmDeleteModal(
                "Удаление пользователя",
                "Вы уверены, что хотите удалить пользователя?",
                function () {
                    let userId = $('#edit-user-form').attr('action').split('/').pop(); // Получаем ID пользователя
                    let token = $('input[name="_token"]').val();

                    $.ajax({
                        url: `/admin/user/${userId}`,
                        method: 'DELETE',
                        headers: {'X-CSRF-TOKEN': token},
                        success: function (response) {
                            if (response.success) {
                                showSuccessModal("Удаление пользователя", "Пользователь успешно удален.", 1);
                            } else {
                                $('#error-modal-message').text('Произошла ошибка при удалении пользователя.');
                                $('#errorModal').modal('show');
                            }
                        },
                        error: function () {
                            $('#error-modal-message').text('Произошла ошибка при удалении пользователя.');
                            $('#errorModal').modal('show');
                        }
                    });
                }
            );
        }

        clickToUpdatePhoto();
        editMidalUser();
        initializeCroppie();
        showContexMenu();
        deteleAvatar();
        editUserLink();
        editUserForm();
    });
</script>

<script>
    (function () {
        const $doc = $(document);
        const digits = s => String(s || '').replace(/\D/g, '');
        function formatPhone(raw) {
            let d = digits(raw).replace(/^8/, '7');
            if (d && d[0] !== '7') d = '7' + d;
            d = d.slice(0, 11);
            const p1 = d.slice(1,4), p2 = d.slice(4,7), p3 = d.slice(7,9), p4 = d.slice(9,11);
            return '+7 (' + (p1.padEnd(3,'_')) + ') ' + (p2.padEnd(3,'_')) + '-' + (p3.padEnd(2,'_')) + '-' + (p4.padEnd(2,'_'));
        }
        function sameAsOriginal($input) {
            const orig = digits($input.data('original') || '').replace(/^8/,'7');
            const cur  = digits($input.val()).replace(/^8/,'7');
            return !!orig && orig === cur;
        }
        function updateIcon($input) {
            const $iconWrap = $('#phone-verify-icon');
            const $icon     = $iconWrap.find('i');
            const $status   = $('#phone-verify-status');
            const hasAny    = digits($input.val()).length > 0;
            const wasVerified = Number($input.data('verified')) === 1;
            const isSame    = sameAsOriginal($input);

            if (!hasAny) {
                $iconWrap.addClass('d-none');
                $status.addClass('d-none');
                return;
            }

            // Верифицирован и номер не меняли -> зелёная галка + "Подтверждён …"
            if (wasVerified && isSame) {
                $iconWrap.removeClass('d-none text-danger').addClass('text-success');
                $icon.removeClass('fa-circle-xmark').addClass('fa-circle-check');
                const vAt = $status.data('verified-at');
                if (vAt) {
                    $status.removeClass('d-none').addClass('text-success')
                        .text('Подтверждён ' + formatDate(vAt));
                } else {
                    $status.addClass('d-none');
                }
            } else {
                // Иначе — только красный крестик, без подписи
                $iconWrap.removeClass('d-none text-success').addClass('text-danger');
                $icon.removeClass('fa-circle-check').addClass('fa-circle-xmark');
                $status.addClass('d-none');
            }
        }
        function formatDate(str) {
            const d = new Date(String(str).replace(' ', 'T'));
            const pad = n => String(n).padStart(2,'0');
            return pad(d.getDate()) + '.' + pad(d.getMonth()+1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        }

        // Делегированные обработчики — работают и в модалках
        $doc.on('input', '#edit-phone', function () {
            const pos = this.selectionStart || 0;
            this.value = formatPhone(this.value);
            try { this.setSelectionRange(pos, pos); } catch(e) {}
            updateIcon($(this));
        });
        $doc.on('paste blur', '#edit-phone', function () {
            const el = this;
            setTimeout(() => { el.value = formatPhone(el.value); updateIcon($(el)); }, 0);
        });

        // Инициализация при показе модалки/страницы
        $doc.on('shown.bs.modal', function (e) {
            const $inp = $(e.target).find('#edit-phone');
            if ($inp.length) {
                if ($inp.val()) $inp.val(formatPhone($inp.val()));
                updateIcon($inp);
            }
        });
        $(function () {
            const $inp = $('#edit-phone');
            if ($inp.length) { if ($inp.val()) $inp.val(formatPhone($inp.val())); updateIcon($inp); }
        });
    })();
</script>
