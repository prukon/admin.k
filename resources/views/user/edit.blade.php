{{--@extends('layouts/main2')--}}
@extends('layouts.admin2')


@section('content')

    <div class="col-md-9 main-content user-data">

        <h4 class="mt-3">Редактирование пользователя</h4>

        <form action="{{ route('user.update', $currentUser->id)}}" method="post">
            {{--             Токен (система защиты) необходим при использовании любого роута кроме get.--}}
            @csrf
            @method('patch')
            <div class="mb-3">
                <label for="title"  class="form-label">Имя ученика*</label>
                <input disabled type="text" name="name" class="form-control" id="title" value="{{ $currentUser->name }}">
                @error('name')
                <p class="text-danger">{{'Укажите имя'}}</p>
                @enderror
            </div>

            <div class="mb-3">
                <label for="title" class="form-label">Дата рождения</label>
                <input type="date" name="birthday" class="form-control" id="birthday" value="{{ $currentUser->birthday }}">
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
                <input disabled type="date" name="start_date" class="form-control" id="start_date" value="{{ $currentUser->start_date }}">
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


            <!-- Кнопка и поле для изменения пароля -->
            <div class="mb-3">
                <button type="submit" class="btn btn-primary">Обновить</button>
                <button type="button" id="change-password-btn" class="btn btn-primary">Изменить пароль</button>

                <div id="password-change-section" class="mt-3" style="display:none;">
                    <input type="password" id="new-password" class="form-control" placeholder="Введите новый пароль">
                    <button type="button" id="apply-password-btn" class="btn btn-success mt-2">Применить</button>
                    <p id="password-change-message" class="text-success mt-2" style="display:none;">Пароль изменен</p>
                </div>
            </div>


        </form>
        <div>
        </div>

    </div>
    </div>
    </div>
@endsection
@section('scripts')
    <script>
        document.getElementById('change-password-btn').addEventListener('click', function() {
            document.getElementById('password-change-section').style.display = 'block';
        });

        document.getElementById('apply-password-btn').addEventListener('click', function() {
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
                        document.getElementById('password-change-message').style.display = 'block';
                    }
                });
        });
    </script>
@endsection