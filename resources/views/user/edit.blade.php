{{--@extends('layouts.admin2')--}}


{{--@section('content')--}}
{{--    <script>--}}
{{--        // Передача данных текущего пользователя из Blade в JavaScript--}}
{{--        let currentUserName = "{{ auth()->user()->name }}";--}}
{{--        let currentUserRole = "{{ auth()->user()->role }}";--}}
{{--    </script>--}}

{{--    <script src="{{ asset('js/dashboard-ajax.js') }}"></script>--}}

{{--    <div class="col-md-12 main-content user-data">--}}

{{--        <h4 class="mt-3 mb-3">Редактирование пользователя</h4>--}}

{{--        <div class="row">--}}
{{--            --}}{{--Аватар--}}
{{--            <div class="col-12 col-lg-3 d-flex flex-column align-items-center">--}}

{{--                <div class="avatar_wrapper d-flex align-items-center justify-content-center">--}}
{{--                    <img id='confirm-img'--}}
{{--                         @if ($currentUser->image_crop)--}}
{{--                             src="{{ asset('storage/avatars/' . $currentUser->image_crop) }}"--}}
{{--                         alt="{{ $currentUser->image_crop }}"--}}
{{--                         @else  src="/img/default.png" alt=""--}}
{{--                            @endif--}}
{{--                    >--}}
{{--                </div>--}}
{{--                <div class='container-form'>--}}
{{--                    <input id='selectedFile' class="disp-none" type='file' accept=".png, .jpg, .jpeg, .svg">--}}
{{--                    <button id="upload-photo" class="btn-primary btn">Выбрать фото...</button>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--            --}}{{--Данные пользователя--}}
{{--            <div class="col-12 col-lg-6 user-data-wrap mb-1">--}}
{{--                <form action="{{ route('user.update', $currentUser->id)}}" method="post">--}}
{{--                    --}}{{--             Токен (система защиты) необходим при использовании любого роута кроме get.--}}
{{--                    @csrf--}}
{{--                    @method('patch')--}}
{{--                    <div class="mb-3">--}}
{{--                        <label for="title" class="form-label">Имя ученика*</label>--}}
{{--                        <input disabled type="text" name="name" class="form-control" id="title"--}}
{{--                               value="{{ $currentUser->name }}">--}}
{{--                        @error('name')--}}
{{--                        <p class="text-danger">{{'Укажите имя'}}</p>--}}
{{--                        @enderror--}}
{{--                    </div>--}}

{{--                    <div class="mb-3">--}}
{{--                        <label for="title" class="form-label">Дата рождения</label>--}}
{{--                        <input type="date" name="birthday" class="form-control" id="birthday"--}}
{{--                               value="{{ $currentUser->birthday }}">--}}
{{--                        @error('birthday')--}}
{{--                        <p class="text-danger">{{'Укажите день рождения'}}</p>--}}
{{--                        @enderror--}}
{{--                    </div>--}}

{{--                    <div class="mb-3">--}}
{{--                        <label for="team">Группа</label>--}}
{{--                        <select disabled class="form-control" id='team' name='team_id'>--}}
{{--                            @foreach($allTeams as $team)--}}
{{--                                <option--}}
{{--                                        {{ $team->id == $currentUser->team_id ? 'selected' : ''}}--}}
{{--                                        value="{{ $team->id }}">{{$team->title}}</option>--}}
{{--                            @endforeach--}}
{{--                        </select>--}}
{{--                        @error('team_id')--}}
{{--                        <p class="text-danger">{{'Выберите команду'}}</p>--}}
{{--                        @enderror--}}
{{--                    </div>--}}

{{--                    <div class="mb-3">--}}
{{--                        <label for="start_date" class="form-label">Дата начала занятий</label>--}}
{{--                        <input disabled type="date" name="start_date" class="form-control" id="start_date"--}}
{{--                               value="{{ $currentUser->start_date }}">--}}
{{--                        @error('start_date')--}}
{{--                        <p class="text-danger">{{'Укажите дату начала занятий'}}</p>--}}
{{--                        @enderror--}}
{{--                    </div>--}}

{{--                    <div class="mb-3">--}}
{{--                        <label for="exampleFormControlInput1" class="form-label">Адрес электронной почты*</label>--}}
{{--                        <input name="email" type="email" class="form-control" id="exampleFormControlInput1"--}}
{{--                               placeholder="name@example.com" value="{{ $currentUser->email }}">--}}
{{--                        @error('email')--}}
{{--                        <p class="text-danger">{{'Укажите email'}}</p>--}}
{{--                        @enderror--}}
{{--                    </div>--}}

{{--                    <div class="mb-3">--}}
{{--                        <label for="activity">Активность</label>--}}
{{--                        <select disabled name="is_enabled" class="form-control" id='activity' name='activity'>--}}
{{--                            @for($i=0; $i<2;$i++)--}}
{{--                                <option--}}
{{--                                        {{ $i == $currentUser->is_enabled ? 'selected' : ''}}--}}
{{--                                        value=" {{ $i }} ">--}}
{{--                                    @if($i == 0)--}}
{{--                                        {{"Неактивен"}}--}}
{{--                                    @else--}}
{{--                                        {{"Активен"}}--}}
{{--                                    @endif--}}
{{--                                </option>--}}
{{--                            @endfor--}}
{{--                        </select>--}}
{{--                        @error('activity')--}}
{{--                        <p class="text-danger">{{'Укажите активность'}}</p>--}}
{{--                        @enderror--}}

{{--                    </div>--}}

{{--                    <!-- Кнопка ОБНОВИТЬ -->--}}
{{--                    <button type="submit" class="btn btn-primary update-btn  ">Обновить</button>--}}
{{--                    <br>--}}
{{--                    <!-- Кнопка ИЗМЕНИТЬ ПАРОЛЬ -->--}}

{{--                    <div class="update-pass d-inline-block mt-3 mr-3">--}}
{{--                        <button type="button" id="cancel-change-password-btn" class="btn btn-primary mr-3">Отмена--}}
{{--                        </button>--}}
{{--                        <button type="button" id="change-password-btn" class="btn btn-primary mr-3">Изменить пароль--}}
{{--                        </button>--}}
{{--                        <div id="password-change-section" class="mt-3" style="display: none;">--}}
{{--                            <div class="input-group">--}}
{{--                                <input type="password" id="new-password" class="form-control"--}}
{{--                                       placeholder="Новый пароль">--}}
{{--                                <div class="input-group-append">--}}
{{--                                    <button type="button" id="apply-password-btn" class="btn btn-success">Применить--}}
{{--                                    </button>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <p id="password-change-message" class="text-success mt-2" style="display:none;">Пароль--}}
{{--                            изменен</p>--}}

{{--                    </div>--}}
{{--                    <div class="password-description"><em>Длина не менее 8 символов</em></div>--}}

{{--                </form>--}}
{{--            </div>--}}
{{--        </div>--}}

{{--        --}}{{--Модалка аватарки--}}
{{--        <div class="modal fade" id="uploadPhotoModal" tabindex="-1" role="dialog"--}}
{{--             aria-labelledby="uploadPhotoModalLabel"--}}
{{--             aria-hidden="true">--}}
{{--            <div class="modal-dialog" role="document">--}}
{{--                <div class="modal-content">--}}
{{--                    <div class="modal-header">--}}
{{--                        <h5 class="modal-title" id="uploadPhotoModalLabel">Загрузка аватарки</h5>--}}
{{--                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">--}}
{{--                            <span aria-hidden="true">&times;</span>--}}
{{--                        </button>--}}
{{--                    </div>--}}
{{--                    <div class="modal-body">--}}

{{--                        <form id="uploadImageForm" enctype="multipart/form-data">--}}
{{--                            @csrf--}}
{{--                            <!-- Выбор файла -->--}}
{{--                            <input class="mb-3" type="file" id="upload" accept="image/*">--}}

{{--                            <!-- Контейнер для Croppie -->--}}
{{--                            <div id="upload-demo" style="width:300px;"></div>--}}

{{--                            <!-- Скрытое поле для сохранения имени пользователя -->--}}
{{--                            <input type="hidden" id="selectedUserName" name="userName" value="">--}}

{{--                            <!-- Скрытое поле для обрезанного изображения -->--}}
{{--                            <input type="hidden" id="croppedImage" name="croppedImage">--}}

{{--                            <!-- Кнопка для сохранения изображения -->--}}
{{--                            <button type="button" id="saveImageBtn" class="btn btn-primary">Загрузить</button>--}}
{{--                        </form>--}}

{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--        <div>--}}
{{--        </div>--}}
{{--    </div>--}}
{{--    </div>--}}
{{--    </div>--}}
{{--@endsection--}}
{{--@section('scripts')--}}
{{--    <script>--}}
{{--        const uploadUrl = "{{ route('profile.uploadAvatar') }}";--}}
{{--    </script>--}}
{{--    <script>--}}
{{--        document.addEventListener('DOMContentLoaded', function () {--}}

{{--            //Добавление имени пользователя в скрытое поле формы для формы отправки аватарки--}}
{{--            function apendUserNametoForm(name) {--}}
{{--                if (currentUserRole == "admin") {--}}
{{--                    if (name) {--}}
{{--                        $('#selectedUserName').val(name);--}}
{{--                    } else {--}}
{{--                        //берем имя юзера из селекта--}}
{{--                        $('#selectedUserName').val($('#single-select-user').val());--}}
{{--                    }--}}
{{--                } else {--}}
{{--                    // берем имя пользователя авторизованного юхера--}}
{{--                    $('#selectedUserName').val(currentUserName);--}}
{{--                }--}}
{{--            }--}}

{{--            // Клик по ИЗМЕНИТЬ ПАРОЛЬ--}}
{{--            function changePasswordBtn() {--}}
{{--                document.getElementById('change-password-btn').addEventListener('click', function () {--}}
{{--                    document.getElementById('change-password-btn').style.display = 'none';--}}
{{--                    document.getElementById('cancel-change-password-btn').style.display = 'inline-block';--}}
{{--                    document.getElementById('password-change-section').style.display = 'inline-block';--}}
{{--                    document.querySelector('#password-change-message').style.display = 'none';--}}
{{--                    document.querySelector('.password-description').style.display = 'inline-block';--}}


{{--                });--}}
{{--            }--}}

{{--            // Клик по ПРИМЕНИТЬ в изменении пароля--}}
{{--            function applyPasswordBtn() {--}}
{{--                document.getElementById('apply-password-btn').addEventListener('click', function () {--}}
{{--                    var userId = '{{ $currentUser->id }}';--}}
{{--                    var newPassword = document.getElementById('new-password').value;--}}
{{--                    var token = '{{ csrf_token() }}';--}}

{{--                    fetch(`/user/${userId}/update-password`, {--}}
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
{{--                                // document.getElementById('password-change-message').style.display = 'block';--}}
{{--                                document.getElementById('change-password-btn').style.display = 'inline-block';--}}
{{--                                document.getElementById('cancel-change-password-btn').style.display = 'none';--}}
{{--                                document.querySelector('#password-change-section').style.display = 'none';--}}
{{--                                document.querySelector('#password-change-message').style.display = 'inline-block';--}}
{{--                                document.querySelector('.password-description').style.display = 'none';--}}

{{--                            }--}}
{{--                        });--}}
{{--                });--}}
{{--            }--}}

{{--            // Клик по ОТМЕНА--}}
{{--            function cancelChangePasswordBtn() {--}}
{{--                document.getElementById('cancel-change-password-btn').addEventListener('click', function () {--}}
{{--                    document.getElementById('change-password-btn').style.display = 'inline-block';--}}
{{--                    document.getElementById('cancel-change-password-btn').style.display = 'none';--}}
{{--                    document.getElementById('password-change-section').style.display = 'none';--}}
{{--                    document.querySelector('.password-description').style.display = 'none';--}}

{{--                });--}}
{{--            }--}}

{{--            // AJAX Вызов модалки--}}
{{--            function showModal() {--}}
{{--                document.getElementById('upload-photo').addEventListener('click', function () {--}}
{{--                    $('#uploadPhotoModal').modal('show');--}}
{{--                });--}}

{{--                $(document).ready(function () {--}}
{{--                    // Инициализация Croppie--}}
{{--                    var $uploadCrop = $('#upload-demo').croppie({--}}
{{--                        viewport: {width: 141, height: 190, type: 'square'},--}}
{{--                        boundary: {width: 300, height: 300},--}}
{{--                        showZoomer: true--}}
{{--                    });--}}

{{--                    // При выборе файла изображение загружается в Croppie--}}
{{--                    $('#upload').on('change', function () {--}}
{{--                        var reader = new FileReader();--}}
{{--                        reader.onload = function (e) {--}}
{{--                            $uploadCrop.croppie('bind', {--}}
{{--                                url: e.target.result--}}
{{--                            }).then(function () {--}}
{{--                            });--}}
{{--                        }--}}
{{--                        reader.readAsDataURL(this.files[0]);--}}

{{--                    });--}}

{{--                    // Сохранение обрезанного изображения и отправка через AJAX--}}
{{--                    $('#saveImageBtn').on('click', function () {--}}
{{--                        $uploadCrop.croppie('result', {--}}
{{--                            type: 'base64',--}}
{{--                            size: 'viewport'--}}
{{--                        }).then(function (resp) {--}}
{{--                            // Заполняем скрытое поле base64 изображением--}}
{{--                            $('#croppedImage').val(resp);--}}

{{--                            // Устанавливаем имя пользователя в скрытое поле--}}
{{--                            // let userName = $('#single-select-user').val();--}}
{{--                            //--}}
{{--                            // $('#selectedUserName').val(userName);--}}
{{--                            let userName = $('#selectedUserName').val();--}}


{{--                            // Создаем FormData для отправки--}}
{{--                            var formData = new FormData();--}}
{{--                            formData.append('_token', $('input[name="_token"]').val()); // Добавляем CSRF-токен--}}
{{--                            formData.append('croppedImage', $('#croppedImage').val()); // Добавляем обрезанное изображение--}}
{{--                            formData.append('userName', userName); // Добавляем имя пользователя--}}

{{--                            // console.log(userName);--}}
{{--                            // console.log(formData);--}}
{{--                            // Отправка данных через AJAX--}}
{{--                            $.ajax({--}}
{{--                                // url: "{{ route('profile.uploadAvatar') }}", // URL маршрута--}}
{{--                                url: uploadUrl, // URL маршрута--}}
{{--                                type: 'POST', // Метод POST--}}
{{--                                data: formData, // Данные формы--}}
{{--                                contentType: false,--}}
{{--                                processData: false,--}}
{{--                                success: function (response) {--}}
{{--                                    if (response.success) {--}}
{{--                                        // Обновляем изображение на странице--}}
{{--                                        $('#confirm-img').attr('src', response.image_url);--}}
{{--                                        console.log('Изображение успешно загружено!');--}}
{{--                                    } else {--}}
{{--                                        alert('Ошибка загрузки изображения');--}}
{{--                                    }--}}
{{--                                    location.reload();--}}
{{--                                },--}}
{{--                                error: function (xhr, status, error) {--}}
{{--                                    console.error('Ошибка:', error);--}}
{{--                                    alert('Ошибка на сервере');--}}
{{--                                }--}}
{{--                            });--}}
{{--                        });--}}
{{--                    });--}}
{{--                });--}}

{{--            }--}}

{{--            // Скрытие модалки--}}
{{--            function hideModal() {--}}
{{--                $(document).ready(function () {--}}
{{--                    // Закрытие модального окна при клике на крестик--}}
{{--                    $('#uploadPhotoModal .close').on('click', function () {--}}
{{--                        $('#uploadPhotoModal').modal('hide');--}}
{{--                    });--}}
{{--                });--}}
{{--            }--}}


{{--            apendUserNametoForm("{{ $currentUser->name }}");--}}
{{--            changePasswordBtn();--}}
{{--            applyPasswordBtn();--}}
{{--            cancelChangePasswordBtn();--}}
{{--            showModal();--}}
{{--            hideModal();--}}

{{--        });--}}

{{--    </script>--}}
{{--@endsection--}}

@extends('layouts.admin2')
@section('content')
    <script>
        // Передача данных текущего пользователя из Blade в JavaScript
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
                         @else
                             src="/img/default.png" alt="Аватар по умолчанию"
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
                <form action="{{ route('user.update', $user->id)}}" method="post">
                    {{-- Токен (система защиты) необходим при использовании любого роута кроме get. --}}
                    @csrf
                    @method('patch')

                    {{-- Поле "Имя" --}}
                    <div class="mb-3">
                        <label for="name" class="form-label">Имя ученика*</label>
                        <input disabled type="text" name="name" class="form-control" id="name"
                               value="{{ old('name', $user->name) }}">
                        @error('name')
                        <p class="text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Поле "Дата рождения" --}}
                    <div class="mb-3">
                        <label for="birthday" class="form-label">Дата рождения</label>
                        {{--                        <input type="date" name="birthday" class="form-control" id="birthday" value="{{ old('birthday', $user->birthday) }}">--}}
                        <input type="date" name="birthday" class="form-control" id="birthday"
                               value="{{ old('birthday', $user->birthday) }}" max="{{ date('Y-m-d') }}">

                        @error('birthday')
                        <p class="text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Поле "Группа" --}}
                    <div class="mb-3">
                        <label for="team" class="form-label">Группа</label>
                        <select disabled class="form-control" id="team" name="team_id">
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
                        <select disabled name="is_enabled" class="form-control" id="activity">
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
                    <div class="buttons-wrap change-pass-wrap" id="change-pass-wrap" style="display: none;">
                        <div class="d-flex align-items-center mt-3">
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

                    <hr class="mt-3">

                    {{-- Кнопки "Обновить" и "Изменить пароль" --}}
                    <div class="button-group buttons-wrap mt-3">
                        <button type="submit" class="btn btn-primary update-btn">Обновить</button>
                        <button type="button" id="change-password-btn" class="btn btn-danger ml-2">Изменить пароль
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <div id="password-change-message" class="text-success ml-3" style="display:none;">Пароль изменен</div>


        {{-- КНОПКИ --}}
        <div class="row buttons-wrap d-flex justify-content-start align-items-center mb-3">

            <!-- Блок изменения пароля -->
            <div class="change-pass-wrap" id="change-pass-wrap" style="display: none;">
                <!-- Ваш код для изменения пароля -->
            </div>

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
                </div>
            </div>
        </div>

        <div>
        </div>

    </div>


@endsection
@section('scripts')
    <script>
        const uploadUrl = "{{ route('profile.user.uploadAvatar') }}";
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

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
                    document.getElementById('change-password-btn').style.display = 'none';
                    document.getElementById('change-pass-wrap').style.display = 'inline-block';
                });
            }

            // Клик по ПРИМЕНИТЬ ПАРОЛЬ
            function applyPasswordBtn() {
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

                    fetch(`/user/${userId}/update-password`, {
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

            // Клик по ОТМЕНА
            function cancelChangePasswordBtn() {
                document.getElementById('cancel-change-password-btn').addEventListener('click', function () {
                    document.getElementById('change-password-btn').style.display = 'inline-block';
                    document.getElementById('change-pass-wrap').style.display = 'none';
                    document.getElementById('error-message').style.display = 'none';
                });
            }

            // AJAX Вызов модалки
            function showModal() {
                document.getElementById('upload-photo').addEventListener('click', function () {
                    $('#uploadPhotoModal').modal('show');
                });

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
            }

            // Скрытие модалки
            function hideModal() {
                // Закрытие модального окна при клике на крестик
                $('#uploadPhotoModal .close').on('click', function () {
                    $('#uploadPhotoModal').modal('hide');
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

            // Вызов функций при загрузке страницы
            appendUserNametoForm("{{ $user->name }}");
            changePasswordBtn();
            applyPasswordBtn();
            cancelChangePasswordBtn();
            showModal();
            hideModal();
            showPassword();
        });

    </script>
@endsection
