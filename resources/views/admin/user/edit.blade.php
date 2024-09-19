{{--@extends('layouts/main2')--}}
@extends('layouts.admin2')


@section('content')
    <script>
        // Передача данных текущего пользователя из Blade в JavaScript
        {{--let currentUserName = "{{$currentUser->name}}";--}}
        {{--let currentUserRole = "{{$currentUser->role}}";--}}
        let currentUserName = "{{ auth()->user()->name }}";
        let currentUserRole = "{{ auth()->user()->role }}";
    </script>

    <script src="{{ asset('js/dashboard-ajax.js') }}"></script>

    <div class="col-md-12 main-content user-data">

        <h4 class="mt-3 mb-3">Редактирование пользователя</h4>


        <div class="row">
            {{--Аватар--}}
            <div class="col-12 col-lg-3 d-flex flex-column align-items-center">

                <div class="avatar_wrapper d-flex align-items-center justify-content-center">
                    <img id='confirm-img'
                         @if ($user->image_crop)
                             src="{{ asset('storage/avatars/' . $user->image_crop) }}"
                         alt="{{ $user->image_crop }}"
                         @else  src="/img/default.png" alt=""
                            @endif
                    >
                </div>
                <div class='container-form'>
                    <input id='selectedFile' class="disp-none" type='file' accept=".png, .jpg, .jpeg, .svg">
                    <button id="upload-photo" class="btn-primary btn">Выбрать фото...</button>
                </div>
            </div>
            {{--Данные пользователя--}}
            <div class="col-12 col-lg-6 user-data-wrap mb-1">
                <form action="{{ route('admin.user.update', $user->id)}}" method="post">
                    {{--             Токен (система защиты) необходим при использовании любого роута кроме get.--}}
                    @csrf
                    @method('patch')
                    <div class="mb-3">
                        <label for="title" class="form-label">Имя ученика*</label>
                        <input type="text" name="name" class="form-control" id="title" value="{{ $user->name }}">
                        @error('name')
                        <p class="text-danger">{{'Укажите имя'}}</p>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="title" class="form-label">Дата рождения</label>
                        <input type="date" name="birthday" class="form-control" id="birthday"
                               value="{{ $user->birthday }}">
                        @error('birthday')
                        <p class="text-danger">{{'Укажите день рождения'}}</p>
                        @enderror
                    </div>

{{--                    <div class="mb-3">--}}
{{--                        <label for="team">Группа</label>--}}
{{--                        <select class="form-control" id='team' name='team_id'>--}}
{{--                            @foreach($allTeams as $team)--}}
{{--                                <option--}}
{{--                                        {{ $team->id == $user->team_id ? 'selected' : ''}}--}}
{{--                                        value="{{ $team->id }}">{{$team->title}}</option>--}}
{{--                            @endforeach--}}
{{--                        </select>--}}
{{--                        @error('team_id')--}}
{{--                        <p class="text-danger">{{'Выберите команду'}}</p>--}}
{{--                        @enderror--}}
{{--                    </div>--}}

                    <div class="mb-3">
                        <label for="team">Группа</label>
                        <select class="form-control" id='team' name='team_id'>
                            <option value="" {{ $user->team_id == null ? 'selected' : '' }}>Без группы</option>
                            @foreach($allTeams as $team)
                                <option
                                        {{ $team->id == $user->team_id ? 'selected' : ''}}
                                        value="{{ $team->id }}">{{$team->title}}</option>
                            @endforeach
                        </select>
                        @error('team_id')
                        <p class="text-danger">{{'Выберите команду'}}</p>
                        @enderror
                    </div>


                    <div class="mb-3">
                        <label for="start_date" class="form-label">Дата начала занятий</label>
                        <input type="date" name="start_date" class="form-control" id="start_date"
                               value="{{ $user->start_date }}">
                        @error('start_date')
                        <p class="text-danger">{{'Укажите дату начала занятий'}}</p>
                        @enderror
                    </div>


                    {{--            <div class="mb-3">--}}
                    {{--                <label for="formFile" class="form-label">Фото</label>--}}
                    {{--                <input class="form-control" type="file" id="formFile" name='image' value="{{ $user->image }}">--}}
                    {{--            </div>--}}


                    <div class="mb-3">
                        <label for="exampleFormControlInput1" class="form-label">Адрес электронной почты*</label>
                        <input name="email" type="email" class="form-control" id="exampleFormControlInput1"
                               placeholder="name@example.com" value="{{ $user->email }}">
                        @error('email')
                        <p class="text-danger">{{'Укажите email'}}</p>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="activity">Активность</label>
                        <select name="is_enabled" class="form-control" id='activity' name='activity'>
                            @for($i=0; $i<2;$i++)
                                <option
                                        {{ $i == $user->is_enabled ? 'selected' : ''}}
                                        value=" {{ $i }} ">
                                    @if($i == 0)
                                        {{"Неактивен"}}
                                    @else
                                        {{"Активен"}}
                                    @endif
                                </option>
                            @endfor
                        </select>
                        @error('activity')
                        <p class="text-danger">{{'Укажите активность'}}</p>
                        @enderror

                    </div>

                    <!-- Кнопка ОБНОВИТЬ -->
                    <button type="submit" class="btn btn-primary update-btn  ">Обновить</button>
                    <br>
                    <!-- Кнопка ИЗМЕНИТЬ ПАРОЛЬ -->


                    <br>
                    <button type="button" id="change-password-btn" class="btn btn-primary mr-3">Изменить пароль</button>
{{--                    <button type="submit"  id="change-password-btn" class="btn btn-primary update-btn  ">Изменить пароль</button>--}}

                    <div class="change-pass-wrap" id = "change-pass-wrap">
                        <div class="update-pass d-inline-block mt-3 mr-3 d-flex flex-row align-items-center">
                            <input type="password" id="new-password" class="form-control mr-2"
                                   placeholder="Новый пароль">
                            <button type="button" id="apply-password-btn" class="btn btn-primary mr-2">Применить
                            </button>
                            <button type="button" id="cancel-change-password-btn" class="btn btn-primary mr-2">Отмена
                            </button>

                        </div>

                        <div class="form-check">
                            <input type="checkbox" id="show-password" class="form-check-input">
                            <label class="form-check-label" for="show-password">Показать пароль</label>
                        </div>

                        <div id="error-message" class="text-danger mt-2" style="display:none;">Пароль должен быть не менее 8 символов</div>
                    </div>

                    <div id="password-change-message" class="text-success ml-3" style="display:none;">Пароль
                        изменен
                    </div>


                    <script>
                        document.getElementById('show-password').addEventListener('change', function () {
                            const passwordInput = document.getElementById('new-password');
                            if (this.checked) {
                                passwordInput.type = 'text';
                            } else {
                                passwordInput.type = 'password';
                            }
                        });
                    </script>


                    {{--<div class="password-description"><em>Длина не менее 8 символов</em></div>--}}
                </form>
            </div>
        </div>

        <hr>

        {{--        КНОПКИ--}}
        <div class="row buttons d-flex justify-content-start align-items-center mt-3 mb-3">
            <!-- Кнопка  НАЗАД -->
            <div class="back me-2 d-inline-block"><a class="btn btn-danger"
                                                     href="{{ route('admin.user.index') }}">Назад</a></div>
            <!-- Кнопка  УДАЛИТЬ -->
            <form class="delete me-2 d-inline-block" action="{{ route('admin.user.delete', $user->id)}}" method="post">
                @csrf
                @method('delete')
                <input type="submit" value="Удалить" class="btn btn-danger">
            </form>

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
                            <button type="button" id="saveImageBtn" class="btn btn-primary">Загрузить</button>
                        </form>


                    </div>
                    {{--                <div class="modal-footer">--}}
                    {{--                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>--}}
                    {{--                    <button type="button" class="btn btn-primary" id="saveImageBtn">Загрузить</button>--}}
                    {{--                </div>--}}
                </div>
            </div>
        </div>

        <div>
        </div>

    </div>
    </div>
    </div>
@endsection
@section('scripts')
    <script>
        const uploadUrl = "{{ route('profile.uploadAvatar') }}";
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function () {

            //Добавление имени пользователя в скрытое поле формы для формы отправки аватарки
            let apendUserNametoForm = function (name) {
                if (currentUserRole == "admin") {
                    if (name) {
                        $('#selectedUserName').val(name);
                    } else {
                        //берем имя юзера из селекта
                        $('#selectedUserName').val($('#single-select-user').val());
                    }
                } else {
                    // берем имя пользователя авторизованного юхера
                    $('#selectedUserName').val(currentUserName);
                }
            }
            // Клик по ИЗМЕНИТЬ ПАРОЛЬ
            let changePasswordBtn = function () {
                document.getElementById('change-password-btn').addEventListener('click', function () {
                    document.getElementById('change-password-btn').style.display = 'none';
                    document.getElementById('change-pass-wrap').style.display = 'inline-block';
                });
            }
            // Клик по ПРИМЕНИТЬ в изменении пароля
{{--            let applyPasswordBtn = function () {--}}
{{--                document.getElementById('apply-password-btn').addEventListener('click', function () {--}}
{{--                    var userId = '{{ $user->id }}';--}}
{{--                    var newPassword = document.getElementById('new-password').value;--}}
{{--                    var token = '{{ csrf_token() }}';--}}

{{--                    fetch(`/admin/user/${userId}/update-password`, {--}}
{{--                        method: 'POST',--}}
{{--                        headers: {--}}
{{--                            'Content-Type': 'application/json',--}}
{{--                            'X-CSRF-TOKEN': token,--}}
{{--                        },--}}
{{--                        body: JSON.stringify({password: newPassword}),--}}
{{--                    })--}}
{{--                        .then(response => response.json())--}}
{{--                        .then(data => {--}}
{{--                            if (data.success) {--}}
{{--                                document.getElementById('change-password-btn').style.display = 'inline-block';--}}
{{--                                document.querySelector('#change-pass-wrap').style.display = 'none';--}}


{{--                                function showPasswordChangeMessage() {--}}
{{--                                    const message = document.getElementById('password-change-message');--}}
{{--                                    message.style.display = 'block'; // Показываем сообщение--}}
{{--                                    setTimeout(() => {--}}
{{--                                        message.classList.add('fade-out'); // Начинаем плавное исчезновение--}}
{{--                                    }, 2000); // Через 3 секунды--}}

{{--                                    setTimeout(() => {--}}
{{--                                        message.style.display = 'none'; // Полностью скрываем через 4 секунды (учитывая время на анимацию)--}}
{{--                                        message.classList.remove('fade-out'); // Удаляем класс, чтобы можно было показать сообщение снова--}}
{{--                                    }, 3000);--}}
{{--                                }--}}

{{--// Пример вызова функции--}}
{{--                                showPasswordChangeMessage();--}}

{{--                            }--}}
{{--                        });--}}
{{--                });--}}
{{--            }--}}

            let applyPasswordBtn = function () {
                document.getElementById('apply-password-btn').addEventListener('click', function () {
                    var userId = '{{ $user->id }}';
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

                    fetch(`/admin/user/${userId}/update-password`, {
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

                                function showPasswordChangeMessage() {
                                    const message = document.getElementById('password-change-message');
                                    message.style.display = 'block'; // Показываем сообщение
                                    setTimeout(() => {
                                        message.classList.add('fade-out'); // Начинаем плавное исчезновение
                                    }, 2000); // Через 2 секунды

                                    setTimeout(() => {
                                        message.style.display = 'none'; // Полностью скрываем через 3 секунды
                                        message.classList.remove('fade-out'); // Удаляем класс, чтобы можно было показать сообщение снова
                                    }, 3000);
                                }

                                // Пример вызова функции
                                showPasswordChangeMessage();
                            }
                        });
                });
            }

// Функция для показа/скрытия пароля
            document.getElementById('show-password').addEventListener('change', function () {
                const passwordInput = document.getElementById('new-password');
                passwordInput.type = this.checked ? 'text' : 'password';
            });


            // Клик по ОТМЕНА
            let cancelChangePasswordBtn = function () {
                document.getElementById('cancel-change-password-btn').addEventListener('click', function () {
                    document.getElementById('change-password-btn').style.display = 'inline-block';
                    document.getElementById('change-pass-wrap').style.display = 'none';
                    document.getElementById('error-message ').style.display = 'none';


                });
            }

            // AJAX Вызов модалки
            function showModal() {
                document.getElementById('upload-photo').addEventListener('click', function () {
                    $('#uploadPhotoModal').modal('show');
                });

                $(document).ready(function () {
                    // Инициализация Croppie
                    var $uploadCrop = $('#upload-demo').croppie({
                        viewport: {width: 141, height: 190, type: 'square'},
                        boundary: {width: 300, height: 300},
                        showZoomer: true
                    });

                    // При выборе файла изображение загружается в Croppie
                    $('#upload').on('change', function () {
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            $uploadCrop.croppie('bind', {
                                url: e.target.result
                            }).then(function () {
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

                            // Устанавливаем имя пользователя в скрытое поле
                            // let userName = $('#single-select-user').val();
                            //
                            // $('#selectedUserName').val(userName);
                            let userName = $('#selectedUserName').val();


                            // Создаем FormData для отправки
                            var formData = new FormData();
                            formData.append('_token', $('input[name="_token"]').val()); // Добавляем CSRF-токен
                            formData.append('croppedImage', $('#croppedImage').val()); // Добавляем обрезанное изображение
                            formData.append('userName', userName); // Добавляем имя пользователя

                            // console.log(userName);
                            // console.log(formData);
                            // Отправка данных через AJAX
                            $.ajax({
                                // url: "{{ route('profile.uploadAvatar') }}", // URL маршрута
                                url: uploadUrl, // URL маршрута
                                type: 'POST', // Метод POST
                                data: formData, // Данные формы
                                contentType: false,
                                processData: false,
                                success: function (response) {
                                    if (response.success) {
                                        // Обновляем изображение на странице
                                        $('#confirm-img').attr('src', response.image_url);
                                        console.log('Изображение успешно загружено!');
                                    } else {
                                        alert('Ошибка загрузки изображения');
                                    }
                                    location.reload();
                                },
                                error: function (xhr, status, error) {
                                    console.error('Ошибка:', error);
                                    alert('Ошибка на сервере');
                                }
                            });
                        });
                    });
                });

            }


            apendUserNametoForm("{{ $user->name }}");
            changePasswordBtn();
            applyPasswordBtn();
            cancelChangePasswordBtn();
            showModal();

        });

    </script>
@endsection
