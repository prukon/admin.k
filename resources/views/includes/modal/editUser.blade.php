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
                    <div class="mb-3 ">
                        <label for="edit-name" class="form-label ">Имя ученика *</label>
                        <input type="text" name="name" class="form-control" id="edit-name" required>
                    </div>

                    <!-- Поле "Дата рождения" -->
                    <div class="mb-3 ">
                        <label for="edit-birthday" class="form-label">Дата рождения</label>
                        <input type="date" name="birthday" class="form-control" id="edit-birthday">
                    </div>

                    <!-- Поле "Группа" -->
                    <div class="mb-3 ">
                        <label for="edit-team" class="form-label">Группа</label>
                        <select class="form-control" id="edit-team" name="team_id">
                            <option value="">Без группы</option>
                            @foreach($allTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Поле "Дата начала занятий" -->
                    <div class="mb-3 ">
                        <label for="edit-start_date" class="form-label">Дата начала занятий</label>
                        <input type="date" name="start_date" class="form-control" id="edit-start_date">
                    </div>

                    <!-- Поле "Email" -->
                    <div class="mb-3 ">
                        <label for="edit-email" class="form-label">Адрес электронной почты*</label>
                        <input name="email" type="email" class="form-control" id="edit-email" required>
                    </div>

                    <!-- Поле "Активность" -->
                    <div class="mb-3 ">
                        <label for="edit-activity" class="form-label">Активность</label>
                        <select name="is_enabled" class="form-control" id="edit-activity">
                            <option value="0">Неактивен</option>
                            <option value="1">Активен</option>
                        </select>
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
                    <div class="button-group buttons-wrap mt-3">
                        <button type="button" id="change-password-btn" class="btn btn-primary  change-password-btn">
                            Изменить пароль
                        </button>
                    </div>

                    <!-- Кнопка для сохранения данных -->
                    <button type="submit" class="btn btn-primary mt-3 save-change-modal">Сохранить изменения</button>
                    <!-- Кнопка для сохранения данных -->

                    {{--<button type="submit" class="btn btn-danger mt-3 save-change-modal">Удалить</button>--}}
                    <button type="button" id="delete-user-btn" class="btn btn-danger mt-3">Удалить</button>

                </form>
            </div>

        </div>
    </div>
</div>

<!-- Модалка для редактирования аватарки -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1" aria-labelledby="uploadPhotoModalLabel" aria-hidden="true">
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

<!-- Модалка для успешного удаления -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="successModalLabel">Успех</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Пользователь успешно удалён.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>


<!-- Модалка для ошибки -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="errorModalLabel">Ошибка</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Ошибка при удалении пользователя. Пожалуйста, попробуйте снова или проверьте лог сервера.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
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

                // initializeCroppie();
            });

            // function closeUploadAvatarModal() {
            //     console.log('Закрытие второй модалки');
            //     $('#uploadPhotoModal').modal('hide');
            //     $('#editUserModal').css('opacity', '1').modal('show'); // Возвращаем основную модалку после закрытия модалки выбора фото
            //     console.log("closeUploadAvatarModal()");
            // }

            function closeUploadAvatarModal2() {
                console.log("closeUploadAvatarModal2()");
                console.log('Модалка Croppie закрыта, восстанавливаем прозрачность основной модалки');
                $('#editUserModal').css('opacity', '1'); // Восстанавливаем прозрачность основной модалки
            }

            // // Обработчик закрытия модалки выбора фото и возврат к основной модалке
            // $('#uploadPhotoModal .close').on('click', function () {
            //     // closeUploadAvatarModal()
            // });


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
                    console.log('Action формы:', action);

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
                                        alert('Аватар успешно обновлен');

                                        // Закрытие второй модалки и возврат к основной модалке
                                        $('#uploadPhotoModal').modal('hide');
                                        $('#editUserModal').css('opacity', '1').modal('show');
                                    } else {
                                        console.error('Ответ сервера не содержит success:', response);
                                        alert('Не удалось обновить аватар. Ошибка ответа.');
                                    }
                                } catch (error) {
                                    console.error('Ошибка обработки ответа от сервера:', error);
                                }
                            },
                            error: function (xhr, status, error) {
                                console.error('Ошибка при загрузке аватарки:', error);
                                console.log('Статус ответа:', status);
                                console.log('Ответ сервера:', xhr.responseText);
                                alert('Ошибка при загрузке аватарки. Проверьте лог сервера.');
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
                var errorMessage = $('#error-message');

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
                        }
                    },
                    error: function () {
                        console.error('Ошибка при изменении пароля');
                    }
                });
            });

            // Отмена изменения пароля
            $('#cancel-change-password-btn').on('click', function () {
                $('#change-password-btn').show();
                $('#change-pass-wrap').hide();
                $('#error-message').hide();
            });

            // Открываем модалку и загружаем данные пользователя для редактирования
            $('.edit-user-link').on('click', function () {
                let userId = $(this).data('id'); // Получаем ID пользователя
                let url = `/admin/users/${userId}/edit`; // Маршрут для получения данных пользователя (GET)
                console.log('Открываем модалку для редактирования пользователя с ID:', userId);

                // AJAX-запрос для получения данных пользователя
                $.ajax({
                    url: url,
                    method: 'GET',
                    success: function (response) {
                        console.log('Данные пользователя получены:', response);

                        // Заполняем поля в модалке
                        $('#edit-name').val(response.user.name);
                        $('#edit-birthday').val(response.user.birthday);
                        $('#edit-team').val(response.user.team_id);
                        $('#edit-start_date').val(response.user.start_date);
                        $('#edit-email').val(response.user.email);
                        $('#edit-activity').val(response.user.is_enabled);

                        // Устанавливаем маршрут для обновления пользователя в форме
                        $('#edit-user-form').attr('action', `/admin/users/${userId}`);

                        // Проверяем, есть ли аватарка, и устанавливаем её в модалку
                        if (response.user.image_crop) {
                            $('#confirm-img').attr('src', `/storage/avatars/${response.user.image_crop}`); // Загружаем текущую аватарку
                            console.log('Текущая аватарка пользователя:', response.user.image_crop);
                        } else {
                            $('#confirm-img').attr('src', '/img/default.png'); // Загружаем изображение по умолчанию, если аватарки нет
                            console.log('Загружено изображение по умолчанию');
                        }
                        // Проверяем и переключаем кнопку удаления аватарки
                        toggleDeleteButton();

                        // Открываем модальное окно
                        $('#editUserModal').modal('show');
                    },
                    error: function () {
                        console.error('Ошибка при загрузке данных пользователя');
                    }
                });
            });

            // Обработчик обновления данных пользователя
            $('#edit-user-form').on('submit', function (e) {
                e.preventDefault();

                let form = $(this);
                let url = form.attr('action');

                console.log('Отправляем форму для обновления пользователя с URL:', url);

                // AJAX-запрос для обновления данных пользователя
                $.ajax({
                    url: url,
                    method: 'PATCH',
                    data: form.serialize(),
                    success: function () {
                        console.log('Данные пользователя успешно обновлены');
                        $('#editUserModal').modal('hide');
                        location.reload(); // Обновляем страницу
                    },
                    error: function () {
                        console.error('Ошибка при обновлении данных пользователя');
                    }
                });
            });
        }

        //Клик по обновить фотографию
        function clickToUpdatePhoto() {
            document.getElementById('update-photo').addEventListener('click', function (e) {
                e.preventDefault();
                // Открытие модального окна для обновления аватарки
                $('#uploadPhotoModal').modal('show');
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

                // Подтверждение удаления фотографии
                if (confirm('Вы уверены, что хотите удалить фотографию?')) {
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
                                alert('Аватарка успешно удалена');
                                toggleDeleteButton();
                            } else {
                                alert('Ошибка удаления аватарки');
                            }
                        },
                        error: function () {
                            alert('Ошибка удаления аватарки. Проверьте лог сервера.');
                        }
                    });
                }
            });
        }

        // function deleteUser() {
        //     $(document).ready(function () {
        //         // Обработчик клика по кнопке "Удалить"
        //         $('#delete-user-btn').on('click', function (e) {
        //             e.preventDefault();
        //
        //             if (confirm('Вы уверены, что хотите удалить пользователя?')) {
        //                 let userId = $('#edit-user-form').attr('action').split('/').pop(); // Получаем ID пользователя
        //                 let token = $('input[name="_token"]').val();
        //
        //                 $.ajax({
        //                     url: `/admin/user/${userId}`,
        //                     method: 'DELETE',
        //                     headers: {
        //                         'X-CSRF-TOKEN': token
        //                     },
        //                     success: function (response) {
        //                         console.log('Ответ сервера:', response);
        //                         if (response.success) {
        //                             alert('Пользователь успешно удалён');
        //                             $('#editUserModal').modal('hide');
        //                             location.reload(); // Обновляем страницу после удаления
        //                         } else {
        //                             alert('Ошибка при удалении пользователя');
        //                         }
        //                     },
        //                     error: function () {
        //                         alert('Ошибка при удалении пользователя. Проверьте лог сервера.');
        //                     }
        //                 });
        //             }
        //         });
        //     });
        // }

        function deleteUser() {
            $(document).ready(function () {
                // Обработчик клика по кнопке "Удалить"
                $('#delete-user-btn').on('click', function (e) {
                    e.preventDefault();

                    if (confirm('Вы уверены, что хотите удалить пользователя?')) {
                        let userId = $('#edit-user-form').attr('action').split('/').pop(); // Получаем ID пользователя
                        let token = $('input[name="_token"]').val();

                        $.ajax({
                            url: `/admin/user/${userId}`,
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': token
                            },
                            success: function (response) {
                                console.log('Ответ сервера:', response);
                                if (response.success) {
                                    $('#successModal').modal('show'); // Показываем модалку успеха
                                    $('#editUserModal').modal('hide'); // Показываем модалку успеха
                                } else {
                                    $('#errorModal').modal('show'); // Показываем модалку ошибки
                                    $('#editUserModal').modal('hide'); // Показываем модалку успеха

                                }
                            },
                            error: function () {
                                $('#errorModal').modal('show'); // Показываем модалку ошибки
                            }
                        });
                    }
                });

                // Перезагрузка страницы после закрытия модалки успеха
                $('#successModal').on('hidden.bs.modal', function () {
                    location.reload(); // Перезагружаем страницу после закрытия модалки успеха
                });

                // Перезагрузка страницы после закрытия модалки ошибки
                $('#errorModal').on('hidden.bs.modal', function () {
                    location.reload(); // Перезагружаем страницу после закрытия модалки ошибки
                });
            });
        }



        clickToUpdatePhoto();
        editMidalUser();
        initializeCroppie();
        showContexMenu();
        deteleAvatar();
        deleteUser();
    });
</script>


