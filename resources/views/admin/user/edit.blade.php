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

                    <div class="mb-3">
                        <label for="team">Группа</label>
                        <select class="form-control" id='team' name='team_id'>
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
                    <div class="update-pass d-inline-block mt-3 mr-3">
                        <button type="button" id="change-password-btn" class="btn btn-primary mr-3">Изменить пароль</button>
                        <div id="password-change-section" class="mt-3" style="display: none;">
                            <div class="input-group">
                                <input type="password" id="new-password" class="form-control" placeholder="Новый пароль">
                                <div class="input-group-append">
                                    <button type="button" id="apply-password-btn" class="btn btn-success">Применить</button>
                                </div>
                            </div>
                            <p id="password-change-message" class="text-success mt-2" style="display:none;">Пароль изменен</p>
                        </div>
                    </div>

                </form>
            </div>
        </div>

        <hr>

        {{--        КНОПКИ--}}
        <div class="row buttons d-flex justify-content-start align-items-center mt-3 mb-3">
            <!-- Кнопка  НАЗАД -->
            <div class="back me-2 d-inline-block"><a class="btn btn-danger" href="{{ route('admin.user.index') }}">Назад</a></div>
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
            apendUserNametoForm("{{ $user->name }}");


        });
    </script>
    <script>
        document.getElementById('change-password-btn').addEventListener('click', function () {
            document.getElementById('password-change-section').style.display = 'inline-block';
        });

        document.getElementById('apply-password-btn').addEventListener('click', function () {
            var userId = '{{ $user->id }}';
            var newPassword = document.getElementById('new-password').value;
            var token = '{{ csrf_token() }}';

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
                        document.getElementById('password-change-message').style.display = 'block';
                    }
                });
        });
    </script>
@endsection
