{{--ВКЛАДКА ЮЗЕР--}}
<div class="row">
    {{--Аватар--}}
    <div class="col-12 col-lg-3 d-flex flex-column align-items-center">

        <div class="avatar_wrapper d-flex align-items-center justify-content-center">
            <img id='confirm-img'
                 @if ($user->image_crop)
                 src="{{ asset('storage/avatars/' . $user->image_crop) }}"
                 alt="{{ $user->image_crop }}"
                 @else
                 src="/img/default.png" alt="Аватар по умолчанию"
                    @endif
            >
        </div>
        <div class='container-form'>
            <input id='selectedFile' class="disp-none" type='file'
                   accept=".png, .jpg, .jpeg, .svg">
            <button id="upload-photo" class="btn-primary btn">Выбрать фото...</button>
        </div>
    </div>
    {{--Данные пользователя--}}
    <div class="col-12 col-lg-6 user-data-wrap mb-3">

        {{--форма юзера--}}
        <form id='userUpdateForm' method="post">
            {{-- Токен (система защиты) необходим при использовании любого роута кроме get. --}}
            @csrf
            @method('patch')

            {{-- Поле "Имя" --}}
            <div class="mb-3">
                <label for="name" class="form-label">Имя ученика*</label>
                <input type="text" name="name" class="form-control" id="name"
                       value="{{ old('name', $user->name) }}"
                       @cannot('name-editing') disabled @endcannot>

                @error('name')
                <p class="text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{-- Поле "Дата рождения" --}}
            <div class="mb-3">
                <label for="birthday" class="form-label">Дата рождения</label>
                <input type="date" name="birthday" class="form-control" id="birthday"
{{--                       value="{{ old('birthday', $user->birthday) }}" max="{{ date('Y-m-d') }}"--}}
                       value="{{ old('birthday', $user->birthday_for_form) }}"



                >


                @error('birthday')
                <p class="text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{-- Поле "Группа" --}}
            <div class="mb-3">
                <label for="team" class="form-label">Группа</label>
                <select class="form-control" id="team" name="team_id"
                        @cannot('changing-your-group') disabled @endcannot>
                    <option value="" {{ old('team_id', $user->team_id) == null ? 'selected' : '' }}>Без группы
                    </option>
                    @foreach($allTeams as $team)
                        <option
                                {{ old('team_id', $user->team_id) == $team->id ? 'selected' : '' }}
                                value="{{ $team->id }}">{{ $team->title }}</option>
                    @endforeach
                </select>
                @error('team_id')
                <p class="text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{-- Поле "Дата начала занятий" --}}
            <div class="mb-3">
                <label for="start_date" class="form-label">Дата начала занятий</label>
                {{--                        <input type="date" name="start_date" class="form-control" id="start_date" value="{{ old('start_date', $user->start_date) }}">--}}
                <input disabled type="date" name="start_date" class="form-control" id="start_date"
                       value="{{ old('start_date', $user->start_date) }}" max="{{ date('2030-01-01') }}">

                @error('start_date')
                <p class="text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{--<h5>Пользовательские поля</h5>--}}
            @if($fields->isNotEmpty())

                <div class="mb-3">
                    <div id="custom-fields-container">
                        {{--@foreach($fields as $field)--}}
                            {{--@php--}}
                                {{--$userFieldValue = $userFieldValues[$field->id] ?? '';--}}
                                {{--$permissions = is_array($field->permissions_id) ? $field->permissions_id : json_decode($field->permissions_id, true);--}}
                                {{--$hasAccess = is_array($permissions) && in_array((string) $currentUser->role_id, array_map('strval', $permissions));--}}

                                 {{--$hasAccess = $field->roles->isEmpty() || $field->roles->contains('id', $currentUser->role_id);--}}

                            {{--@endphp--}}

                            {{--<div class="mb-3 custom-field" data-slug="{{ $field->slug }}">--}}
                                {{--<label for="custom-{{ $field->slug }}" class="form-label">--}}
                                    {{--{{ $field->name }}--}} 
                                {{--</label>--}}
                                {{--<input--}}
                                        {{--type="text"--}}
                                        {{--name="custom[{{ $field->slug }}]"--}}
                                        {{--class="form-control"--}}
                                        {{--id="custom-{{ $field->slug }}"--}}
                                        {{--value="{{ old("custom.{$field->slug}", $userFieldValue) }}"--}}
                                        {{--{{ $hasAccess ? '' : 'disabled' }}--}}
                                {{--/>--}}

                            {{--@unless($hasAccess)--}}
                                {{--<!-- Добавляем скрытое поле, чтобы данные передавались -->--}}
                                    {{--<input type="hidden" name="custom[{{ $field->slug }}]"--}}
                                           {{--value="{{ $userFieldValue }}">--}}
                                {{--@endunless--}}
                            {{--</div>--}}
                        {{--@endforeach--}}


                        {{--@foreach($fields as $field)--}}
                            {{--@php--}}
                                {{--// берем из контроллера--}}
                                {{--$isEditable = $editableFields[$field->id] ?? false;--}}
                                {{--$userFieldValue = $userFieldValues[$field->id] ?? '';--}}
                            {{--@endphp--}}

                            {{--<div class="mb-3 custom-field" data-slug="{{ $field->slug }}">--}}
                                {{--<label for="custom-{{ $field->slug }}" class="form-label">--}}
                                    {{--{{ $field->name }}--}}
                                {{--</label>--}}
                                {{--<input--}}
                                        {{--type="text"--}}
                                        {{--name="custom[{{ $field->slug }}]"--}}
                                        {{--class="form-control"--}}
                                        {{--id="custom-{{ $field->slug }}"--}}
                                        {{--value="{{ old("custom.{$field->slug}", $userFieldValue) }}"--}}
                                        {{--{{ $isEditable ? '' : 'disabled' }}--}}
                                {{--/>--}}
                                {{--@unless($isEditable)--}}
                                    {{-- чтобы данные не потерялись --}}
                                    {{--<input type="hidden" name="custom[{{ $field->slug }}]" value="{{ $userFieldValue }}">--}}
                                {{--@endunless--}}
                            {{--</div>--}}
                        {{--@endforeach--}}

                        @foreach($fields as $field)
                            @php
                                $isEditable = $editableFields[$field->id] ?? false;
                                $userFieldValue = $userFieldValues[$field->id] ?? '';
                            @endphp

                            <div class="mb-3 custom-field" data-slug="{{ $field->slug }}">
                                <label for="custom-{{ $field->slug }}" class="form-label">
                                    {{ $field->name }}
                                </label>
                                <input
                                        type="text"
                                        name="custom[{{ $field->slug }}]"
                                        class="form-control"
                                        id="custom-{{ $field->slug }}"
                                        value="{{ old("custom.{$field->slug}", $userFieldValue) }}"
                                        {{ $isEditable ? '' : 'disabled' }}
                                />
                                @unless($isEditable)
                                    {{-- чтобы значение всё равно пришло на бек --}}
                                    <input type="hidden" name="custom[{{ $field->slug }}]" value="{{ $userFieldValue }}">
                                @endunless
                            </div>
                        @endforeach


                    </div>
                </div>

            @endif

            {{-- Поле "Email" --}}
            <div class="mb-3">
                <label for="email" class="form-label">Адрес электронной почты*</label>
                <input name="email" type="email" class="form-control" id="email" placeholder="name@example.com"
                       value="{{ old('email', $user->email) }}">
                @error('email')
                <p class="text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{-- Поле "Активность" --}}
            <div class="mb-3">
                <label for="activity" class="form-label">Активность</label>
                <select name="is_enabled" class="form-control" id="activity"
                        @cannot('changing-user-activity') disabled @endcannot>
                    <option value="0" {{ old('is_enabled', $user->is_enabled) == 0 ? 'selected' : '' }}>
                        Неактивен
                    </option>
                    <option value="1" {{ old('is_enabled', $user->is_enabled) == 1 ? 'selected' : '' }}>
                        Активен
                    </option>
                </select>
                @error('is_enabled')
                <p class="text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{-- Блок изменения пароля --}}
            <div class="buttons-wrap change-pass-wrap " id="change-pass-wrap" style="display: none;">
                <div class="d-flex align-items-center">
                    <div class="position-relative">
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

            <hr>

            {{-- Кнопки "Обновить" и "Изменить пароль" --}}
            <div class="button-group buttons-wrap mt-3">
                <button type="submit" class="btn btn-primary update-btn ">Обновить</button>
                <button type="button" id="change-password-btn" class="btn btn-danger ml-2">Изменить пароль
                </button>
            </div>

        </form>

    </div>
</div>
<div id="password-change-message" class="text-success ml-3" style="display:none;">Пароль
    изменен
</div>
{{--Модалка аватарки--}}
<div class="modal fade" id="uploadPhotoModal" tabindex="-1" role="dialog"
     aria-labelledby="uploadPhotoModalLabel"
     aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadPhotoModalLabel">Загрузка аватарки</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <form id="uploadImageForm" enctype="multipart/form-data">
                @csrf
                <!-- Выбор файла -->
                    <input class="mb-3" type="file" id="upload" accept="image/*">

                    <!-- Контейнер для Croppie -->
                    <div id="upload-demo" style="width:300px;"></div>

                    <!-- Скрытое поле для сохранения имени пользователя -->
                    <input type="hidden" id="selectedUserName" name="userName" value="">

                    <!-- Скрытое поле для обрезанного изображения -->
                    <input type="hidden" id="croppedImage" name="croppedImage">

                    <!-- Кнопка для сохранения изображения -->
                    <button type="button" id="saveImageBtn" class="btn btn-primary">
                        Загрузить
                    </button>
                </form>

            </div>
        </div>
    </div>
</div>


@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const uploadUrl = "{{ route('profile.user.uploadAvatar') }}";


            //Добавление имени пользователя в скрытое поле формы для формы отправки аватарки
            function appendUserNametoForm(name) {
                if (currentUserRole === "admin") {
                    if (name) {
                        $('#selectedUserName').val(name);
                    } else {
                        // берем имя юзера из селекта
                        $('#selectedUserName').val($('#single-select-user').val());
                    }
                } else {
                    // берем имя пользователя авторизованного юзера
                    $('#selectedUserName').val(currentUserName);
                }
            }

            // Клик по ИЗМЕНИТЬ ПАРОЛЬ
            function changePasswordBtn() {
                document.getElementById('change-password-btn').addEventListener('click', function () {
                    console.log(1);
                    document.getElementById('change-password-btn').style.display = 'none';
                    document.getElementById('change-pass-wrap').style.display = 'inline-block';
                });
            }

            // Клик по ПРИМЕНИТЬ ПАРОЛЬ
            function applyPasswordBtn() {
                document.getElementById('apply-password-btn').addEventListener('click', function () {
                            {{--var userId = '{{ $user->id }}';--}}
                    var newPassword = document.getElementById('new-password').value;
                    var token = '{{ csrf_token() }}';
                    var errorMessage = document.getElementById('error-message');

                    // Проверка длины пароля
                    if (newPassword.length < 8) {
                        errorMessage.style.display = 'block'; // Показываем сообщение об ошибке
                        return; // Прерываем выполнение, если пароль слишком короткий
                    } else {
                        errorMessage.style.display = 'none'; // Скрываем сообщение об ошибке
                    }

                    fetch(`/user/update-password`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({password: newPassword}),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('change-password-btn').style.display = 'inline-block';
                                document.querySelector('#change-pass-wrap').style.display = 'none';
                                showSuccessModal("Изменение пароля", "Пароль успешно изменен.");
                            }
                        });
                });
            }

            // Клик по ОТМЕНА
            function cancelChangePasswordBtn() {
                document.getElementById('cancel-change-password-btn').addEventListener('click', function () {
                    document.getElementById('change-password-btn').style.display = 'inline-block';
                    document.getElementById('change-pass-wrap').style.display = 'none';
                    document.getElementById('error-message').style.display = 'none';
                });
            }

            // Функция для показа/скрытия пароля с помощью иконки глаза
            function showPassword() {
                const togglePassword = document.querySelector('.toggle-password');
                const passwordInput = document.getElementById('new-password');

                togglePassword.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);

                    // Меняем иконку глаза
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }

            //Обновление юзера
            function updateUserData() {
                // Отслеживаем событие отправки формы
                $('#userUpdateForm').on('submit', function (e) {
                    e.preventDefault(); // Отключаем стандартную отправку формы

                    // Собираем данные формы
                    let formData = $(this).serialize();
                    console.log(formData);
                    console.log('123');

                    // AJAX-запрос
                    $.ajax({
                        // url: $(this).attr('action'),   // Используем URL из атрибута action
                        url: "{{ route('account.user.update', $user->id) }}", // Явно указанный URL

                        type: 'PATCH',                 // Метод PATCH (Laravel примет, т.к. есть _method=patch)
                        data: formData,
                        success: function (response) {
                            // Действия при успешном ответе
                            // Вы можете, например, вывести сообщение об успехе или сделать редирект:
                            // alert('Данные пользователя успешно обновлены!');
                            showSuccessModal("Редактирование пользователя", "Пользователь успешно обновлен.", 1);
                            console.log(response);
                        },
                        error: function (xhr, status, error) {
                            // Действия при ошибке
                            // Например, можно обработать валидационные ошибки (422) или другие
                            if (xhr.status === 422) {
                                // Ошибки валидации
                                let errors = xhr.responseJSON.errors;
                                console.log(errors);
                                // Сделайте подсветку полей или вывод сообщений об ошибках по своему вкусу
                            } else {
                                $('#errorModal').modal('show');
                            }
                        }
                    });
                });
            }

            // AJAX Вызов модалки
            function showModal() {
                document.getElementById('upload-photo').addEventListener('click', function () {
                    $('#uploadPhotoModal').modal('show');
                });

                // Инициализация Croppie для аватарки
                $uploadCrop = $('#upload-demo').croppie({
                    viewport: {width: 200, height: 250, type: 'square'},
                    boundary: {width: 300, height: 300},
                    showZoomer: true
                });

                // Получаем текущий URL аватарки
                var currentAvatarUrl = $('#confirm-img').attr('src');
                console.log('Текущий URL аватарки:', currentAvatarUrl);

                $uploadCrop.croppie('bind', {
                    url: '/img/white.jpg'
                });

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

                // При выборе файла изображение загружается в Croppie
                $('#upload').on('change', function () {

                    var reader = new FileReader();
                    reader.onload = function (e) {
                        $uploadCrop.croppie('bind', {
                            url: e.target.result
                        }).then(function () {
                            // Croppie готов к использованию
                        });
                    }
                    reader.readAsDataURL(this.files[0]);
                });

                // Сохранение обрезанного изображения и отправка через AJAX
                $('#saveImageBtn').on('click', function () {
                    $uploadCrop.croppie('result', {
                        type: 'base64',
                        size: 'viewport'
                    }).then(function (resp) {
                        // Заполняем скрытое поле base64 изображением
                        $('#croppedImage').val(resp);

                        let userName = $('#selectedUserName').val();

                        // Создаем FormData для отправки
                        var formData = new FormData();
                        formData.append('_token', $('input[name="_token"]').val()); // Добавляем CSRF-токен
                        formData.append('croppedImage', $('#croppedImage').val()); // Добавляем обрезанное изображение
                        formData.append('userName', userName); // Добавляем имя пользователя

                        // Отправка данных через AJAX
                        $.ajax({
                            url: uploadUrl, // URL маршрута
                            type: 'POST', // Метод POST
                            data: formData, // Данные формы
                            contentType: false,
                            processData: false,
                            success: function (response) {
                                if (response.success) {
                                    $('#confirm-img').attr('src', response.image_url);
                                    showSuccessModal("Обновление аватара", "Аватар успешно обновлен.", 1);
                                } else {
                                    alert('Ошибка загрузки изображения');
                                }
                            },
                            error: function (xhr, status, error) {
                                console.error('Ошибка:', error);
                                $('#errorModal').modal('show');
                            }
                        });
                    });
                });
            }

            // Скрытие модалки
            function hideModal() {
                // Закрытие модального окна при клике на крестик
                $('#uploadPhotoModal .close').on('click', function () {
                    $('#uploadPhotoModal').modal('hide');
                });
            }

            appendUserNametoForm("{{ $user->name }}");
            changePasswordBtn();
            applyPasswordBtn();
            cancelChangePasswordBtn();
            showPassword();
            updateUserData();
            showModal();
            hideModal();

        });
    </script>
    {{--    <script src="{{ asset('js/dashboard-ajax.js') }}"></script>--}}
@endsection
