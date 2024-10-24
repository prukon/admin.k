{{--Cropie--}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css">
<link rel="stylesheet" href="{{ asset('css/croppie.css') }}">
<script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>

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

                    <!-- Блок для аватарки -->
                    <div class="mb-3 d-flex flex-column align-items-center">
                        <div class="avatar_wrapper d-flex align-items-center justify-content-center">
                            <img id="confirm-img" src="/img/default.png" alt="Аватар по умолчанию" class="img-thumbnail">
                        </div>
                        <div class="container-form mt-2">
                            <input id="selectedFile" class="disp-none" type="file" accept=".png, .jpg, .jpeg, .svg">
                            <button id="upload-photo" class="btn-primary btn">Выбрать фото...</button>
                        </div>
                    </div>

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

                    <!-- Блок изменения пароля -->
                    <div class="buttons-wrap change-pass-wrap" id="change-pass-wrap" style="display: none;">
                        <div class="d-flex align-items-center mt-3">
                            <div class="position-relative">
                                <input type="password" id="new-password" class="form-control" placeholder="Новый пароль">
                                <span toggle="#new-password" class="fa fa-fw fa-eye field-icon toggle-password"></span>
                            </div>
                            <button type="button" id="apply-password-btn" class="btn btn-primary ml-2">Применить</button>
                            <button type="button" id="cancel-change-password-btn" class="btn btn-danger ml-2">Отмена</button>
                        </div>
                        <div id="error-message" class="text-danger mt-2" style="display:none;">Пароль должен быть не менее 8 символов</div>
                    </div>

                    <!-- Кнопка для изменения пароля -->
                    <div class="button-group buttons-wrap mt-3">
                        <button type="button" id="change-password-btn" class="btn btn-danger ml-2">Изменить пароль</button>
                    </div>

                    <!-- Кнопка для сохранения данных -->
                    <button type="submit" class="btn btn-primary mt-3">Сохранить изменения</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Модалка для редактирования аватарки -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1" aria-labelledby="uploadPhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
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

    // Функция для обработки ответа сервера после обновления аватарки
    function handleAvatarUpdateResponse(response) {
        console.log('Ответ сервера:', response);

        // Проверяем, есть ли success в ответе
        if (response.success) {
            // Получаем URL нового аватара
            let newAvatarUrl = response.avatar_url + '?' + new Date().getTime();  // Добавляем метку времени для сброса кэша
            console.log('Новый URL аватара:', newAvatarUrl);

            // Устанавливаем новый URL на изображение в модалке
            $('#confirm-img').attr('src', newAvatarUrl);
            console.log('URL изображения после обновления:', $('#confirm-img').attr('src'));

            // Здесь можно добавить сообщение о успешном обновлении или закрыть модалку
            alert('Аватар успешно обновлен');
        } else {
            // Если ответа success нет, обрабатываем ошибку
            console.error('Ошибка обновления аватарки. Ответ сервера не содержит success.');
            alert('Не удалось обновить аватар.');
        }
    }

    $(document).ready(function () {
        var $uploadCrop;

        // Инициализация Croppie для аватарки
        $uploadCrop = $('#upload-demo').croppie({
            viewport: { width: 200, height: 250, type: 'square' },
            boundary: { width: 300, height: 300 },
            showZoomer: true
        });

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
            console.log('Текущий URL аватарки:', currentAvatarUrl);

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

        // Обработчик закрытия модалки выбора фото и возврат к основной модалке
        $('#uploadPhotoModal .close, #saveImageBtn').on('click', function () {
            console.log('Закрытие второй модалки');
            $('#uploadPhotoModal').modal('hide');
            $('#editUserModal').css('opacity', '1').modal('show'); // Возвращаем основную модалку после закрытия модалки выбора фото
        });


        // Использование обработчика события 'hidden.bs.modal' для восстановления прозрачности
        $('#uploadPhotoModal').on('hidden.bs.modal', function () {
            console.log('Модалка Croppie закрыта, восстанавливаем прозрачность основной модалки');
            $('#editUserModal').css('opacity', '1'); // Восстанавливаем прозрачность основной модалки
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
    });
</script>