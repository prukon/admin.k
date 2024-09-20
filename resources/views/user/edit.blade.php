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
                         @if ($currentUser->image_crop)
                             src="{{ asset('storage/avatars/' . $currentUser->image_crop) }}"
                         alt="{{ $currentUser->image_crop }}"
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
                <form action="{{ route('user.update', $currentUser->id)}}" method="post">
                    {{--             Токен (система защиты) необходим при использовании любого роута кроме get.--}}
                    @csrf
                    @method('patch')
                    <div class="mb-3">
                        <label for="title" class="form-label">Имя ученика*</label>
                        <input disabled type="text" name="name" class="form-control" id="title" value="{{ $currentUser->name }}">
                        @error('name')
                        <p class="text-danger">{{'Укажите имя'}}</p>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="title" class="form-label">Дата рождения</label>
                        <input type="date" name="birthday" class="form-control" id="birthday"
                               value="{{ $currentUser->birthday }}">
                        @error('birthday')
                        <p class="text-danger">{{'Укажите день рождения'}}</p>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="team">Группа</label>
                        <select disabled class="form-control" id='team' name='team_id'>
                            @foreach($allTeams as $team)
                                <option
                                        {{ $team->id == $currentUser->team_id ? 'selected' : ''}}
                                        value="{{ $team->id }}">{{$team->title}}</option>
                            @endforeach
                        </select>
                        @error('team_id')
                        <p class="text-danger">{{'Выберите команду'}}</p>
                        @enderror
                    </div>


                    <div class="mb-3">
                        <label for="start_date" class="form-label">Дата начала занятий</label>
                        <input disabled type="date" name="start_date" class="form-control" id="start_date"
                               value="{{ $currentUser->start_date }}">
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
                               placeholder="name@example.com" value="{{ $currentUser->email }}">
                        @error('email')
                        <p class="text-danger">{{'Укажите email'}}</p>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="activity">Активность</label>
                        <select disabled name="is_enabled" class="form-control" id='activity' name='activity'>
                            @for($i=0; $i<2;$i++)
                                <option
                                        {{ $i == $currentUser->is_enabled ? 'selected' : ''}}
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
                        <button type="button" id="cancel-change-password-btn" class="btn btn-primary mr-3">Отмена
                        </button>
                        <button type="button" id="change-password-btn" class="btn btn-primary mr-3">Изменить пароль
                        </button>
                        <div id="password-change-section" class="mt-3" style="display: none;">
                            <div class="input-group">
                                <input type="password" id="new-password" class="form-control"
                                       placeholder="Новый пароль">
                                <div class="input-group-append">
                                    <button type="button" id="apply-password-btn" class="btn btn-success">Применить</button>
                                </div>
                            </div>
                        </div>
                        <p id="password-change-message" class="text-success mt-2" style="display:none;">Пароль изменен</p>

                    </div>
                    <div class="password-description"><em>Длина не менее 8 символов</em></div>

                </form>
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
            function apendUserNametoForm(name) {
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
            function changePasswordBtn() {
                document.getElementById('change-password-btn').addEventListener('click', function () {
                    document.getElementById('change-password-btn').style.display = 'none';
                    document.getElementById('cancel-change-password-btn').style.display = 'inline-block';
                    document.getElementById('password-change-section').style.display = 'inline-block';
                    document.querySelector('#password-change-message').style.display = 'none';
                    document.querySelector('.password-description').style.display = 'inline-block';


                });
            }
            // Клик по ПРИМЕНИТЬ в изменении пароля
            function applyPasswordBtn() {
                document.getElementById('apply-password-btn').addEventListener('click', function () {
                    var userId = '{{ $currentUser->id }}';
                    var newPassword = document.getElementById('new-password').value;
                    var token = '{{ csrf_token() }}';

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
                                // document.getElementById('password-change-message').style.display = 'block';
                                document.getElementById('change-password-btn').style.display = 'inline-block';
                                document.getElementById('cancel-change-password-btn').style.display = 'none';
                                document.querySelector('#password-change-section').style.display = 'none';
                                document.querySelector('#password-change-message').style.display = 'inline-block';
                                document.querySelector('.password-description').style.display = 'none';

                            }
                        });
                });
            }
            // Клик по ОТМЕНА
             function cancelChangePasswordBtn() {
                document.getElementById('cancel-change-password-btn').addEventListener('click', function () {
                    document.getElementById('change-password-btn').style.display = 'inline-block';
                    document.getElementById('cancel-change-password-btn').style.display = 'none';
                    document.getElementById('password-change-section').style.display = 'none';
                    document.querySelector('.password-description').style.display = 'none';

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

            function hideModal() {
                $(document).ready(function() {
                    // Закрытие модального окна при клике на крестик
                    $('#uploadPhotoModal .close').on('click', function() {
                        $('#uploadPhotoModal').modal('hide');
                    });
                });
            }



            apendUserNametoForm("{{ $currentUser->name }}");
            changePasswordBtn();
            applyPasswordBtn();
            cancelChangePasswordBtn();
            showModal();
            hideModal();

        });

    </script>
@endsection
