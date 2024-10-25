<!-- Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createUserModalLabel">Создание пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="create-user-form" method="post" action="{{ route('admin.user.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label">Имя ученика*</label>
                        <input type="text" name="name" class="form-control" id="name">
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
{{--                    <div class="mb-3">--}}
{{--                        <label for="password" class="form-label">Пароль*</label>--}}
{{--                        <input type="password" name="password" class="form-control" id="password">--}}
{{--                    </div>--}}


                    <div class="mb-3 wrap-cur-password">
                        <label for="password" class="form-label">Пароль*</label>
                        <div class="position-relative">
                        <input type="password" id="password"  name="password" class="form-control"
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
                    <div class="modal-footer">
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




        $('#create-user-form').on('submit', function (e) {
            e.preventDefault();

            // Очищаем предыдущие ошибки
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').remove();

            $.ajax({
                url: $(this).attr('action'),
                method: $(this).attr('method'),
                data: $(this).serialize(),
                success: function (response) {
                    // Закрываем модальное окно
                    $('#createUserModal').modal('hide');

                    // Очищаем форму
                    $('#create-user-form')[0].reset();

                    if (response.user) {
                        // Формируем HTML-код нового пользователя
                        let newUser = `
                        <div class="user">
                            <a href="javascript:void(0);" class="edit-user-link" data-id="${response.user.id}" style="${response.user.is_enabled === 'Нет' ? 'color: red;' : ''}">
                                ${response.user.name}
                            </a>
                        </div>
                    `;

                        // Найдем место для вставки нового пользователя по алфавиту
                        let inserted = false;
                        let newUserElement;

                        // Используем jQuery метод .get().reverse() для обратного перебора
                        $('.wrap-user-list .user').get().reverse().forEach(function (element) {
                            if (!inserted) {  // Добавляем проверку, чтобы пользователь вставлялся только один раз
                                let currentUserName = $(element).find('a').text().trim(); // Получаем имя пользователя, включая номер

                                // Извлекаем имя пользователя, удаляя порядковый номер
                                let actualName = currentUserName.replace(/^\d+\.\s*/, ''); // Убираем порядковый номер и пробел

                                // Сравниваем имя нового пользователя с текущим пользователем
                                if (response.user.name.localeCompare(actualName) >= 0) {
                                    $(element).after(newUser); // Вставляем после текущего элемента
                                    newUserElement = $(element).next(); // Сохраняем новый элемент для дальнейших действий
                                    inserted = true; // Отмечаем, что пользователь был вставлен
                                }
                            }
                        });

                        // Если не нашли место, добавляем в начало списка (возможно это редкий случай)
                        if (!inserted) {
                            $('.wrap-user-list').prepend(newUser);
                            newUserElement = $('.wrap-user-list .user').first(); // Если в начало, то новый элемент — первый
                        }

                        // Удаляем порядковые номера
                        function removeOrdinalNumbers() {
                            const userLinks = document.querySelectorAll('.user a'); // выбираем все ссылки пользователей
                            userLinks.forEach(link => {
                                const text = link.innerHTML.trim(); // получаем текст ссылки
                                const newText = text.replace(/^\d+\.\s*/, ''); // удаляем порядковый номер в начале
                                link.innerHTML = newText; // устанавливаем новый текст ссылки
                            });
                        }

                        // Вставялем порядковые номера
                        function addOrdinalNumbersToUsers() {
                            $('.wrap-user-list .user').each(function (index) {
                                var currentText = $(this).find('a').text();
                                $(this).find('a').text((index + 1) + '. ' + currentText);
                            });
                        }

                        // Вызов функции
                        removeOrdinalNumbers();
                        addOrdinalNumbersToUsers();
                    }

                    // Показываем сообщение об успехе (опционально)
                    alert(response.message);

                    // Функция редактирования пользователя
                    function editModalUser() {
                        // Функция для показа/скрытия пароля с помощью иконки глаза  fix
                        function showPassword() {
                            const togglePassword = document.querySelector('.toggle-password');
                            const passwordInput = document.querySelector('#passwordInput');

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

                            // initializeCroppie();
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
                            console.log(1);
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


                    }

                    editModalUser();
                },
                error: function (response) {
                    // Обрабатываем ошибки валидации
                    let errors = response.responseJSON.errors;
                    if (errors) {
                        for (let field in errors) {
                            $(`#${field}`).addClass('is-invalid');
                            $(`#${field}`).after(`<div class="invalid-feedback">${errors[field][0]}</div>`);
                        }
                    }
                }
            });
        });
        // Функция для показа/скрытия пароля с помощью иконки глаза  fix
        function showCurPassword() {
            const togglePassword = document.querySelector('.wrap-cur-password .toggle-password');
            const passwordInput = document.querySelector('.wrap-cur-password #password');

            togglePassword.addEventListener('click', function () {
                // Переключаем тип input между 'password' и 'text'
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                // Меняем иконку глаза
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
        showCurPassword();

    });

</script>