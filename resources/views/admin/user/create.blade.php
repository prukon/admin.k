{{--@extends('layouts/main2')--}}
{{--@extends('layouts.admin2')--}}

{{--@section('content')--}}

{{--    <div class="col-md-9 main-content user-data">--}}

{{--        <h4 class="mt-3">Создание пользователя</h4>--}}

{{--        <form--}}
{{--                action="{{ route('admin.user.store')}}" method="post">--}}
{{--            --}}{{--             Токен (система защиты) необходим при использовании любого роута кроме get.--}}
{{--            @csrf--}}
{{--            <div class="mb-3">--}}
{{--                <label for="title" class="form-label">Имя ученика*</label>--}}
{{--                <input type="text" name="name" class="form-control" id="title" value="{{old('name')}}">--}}
{{--                @error('name' )--}}
{{--                <p class="text-danger">{{'Введите имя'}}</p>--}}
{{--                @enderror--}}
{{--            </div>--}}

{{--            <div class="mb-3">--}}
{{--                <label for="title" class="form-label">Дата рождения</label>--}}
{{--                <input type="date" name="birthday" class="form-control" id="birthday">--}}
{{--                @error('birthday' )--}}
{{--                <p class="text-danger">{{'Введите корректную дату'}}</p>--}}
{{--                @enderror--}}
{{--            </div>--}}

{{--            <div class="mb-3">--}}
{{--                <label for="team">Группа</label>--}}

{{--                <select class="form-select" id='team' name='team_id'>--}}
{{--                    <option value="" {{ old('team_id') == null ? 'selected' : "" }}>Без группы</option>--}}
{{--                    @foreach($allTeams as $team)--}}
{{--                        <option--}}
{{--                                {{ old('team_id') == $team->id ? 'selected' : "" }}--}}
{{--                                value="{{$team->id}}">{{$team->title}}</option>--}}
{{--                    @endforeach--}}
{{--                </select>--}}
{{--            </div>--}}

{{--            <div class="mb-3">--}}
{{--                <label for="start_date" class="form-label">Дата начала занятий</label>--}}
{{--                <input type="date" name="start_date" class="form-control" id="start_date">--}}
{{--                @error('start_date' )--}}
{{--                <p class="text-danger">{{'Введите корректную дату'}}</p>--}}
{{--                @enderror--}}
{{--            </div>--}}

{{--            <div class="mb-3">--}}
{{--                <label for="exampleFormControlInput1" class="form-label">Адрес электронной почты*</label>--}}
{{--                <input name="email" type="email" class="form-control" id="exampleFormControlInput1"--}}
{{--                       placeholder="name@example.com" value="{{ old('email') }}">--}}
{{--                @if ($errors->has('email'))--}}
{{--                    @foreach ($errors->get('email') as $error)--}}
{{--                        <p class="text-danger">{{ $error }}</p>--}}
{{--                    @endforeach--}}
{{--                @endif--}}
{{--            </div>--}}

{{--            <div class="mb-3">--}}
{{--                <label for="passwordInput" class="col-form-label">Пароль*</label>--}}
{{--                <div class="position-relative">--}}
{{--                    <input name="password" type="password" id="passwordInput" class="form-control"--}}
{{--                           aria-describedby="passwordHelpInline">--}}
{{--                    <span toggle="#passwordInput" class="fa fa-fw fa-eye field-icon toggle-password"></span>--}}
{{--                </div>--}}

{{--                <span id="passwordHelpInline" class="form-text">Должно быть 8-20 символов.</span>--}}

{{--                @if ($errors->has('password'))--}}
{{--                    @foreach ($errors->get('password') as $error)--}}
{{--                        <p class="text-danger">{{ $error }}</p>--}}
{{--                    @endforeach--}}
{{--                @endif--}}
{{--            </div>--}}

{{--            <div class="mb-3">--}}
{{--                <label for="activity">Активность</label>--}}
{{--                <select name="is_enabled" class="form-select" id='activity' name='activity'>--}}
{{--                    <option value="1">Да</option>--}}
{{--                    <option value="0">Нет</option>--}}
{{--                </select>--}}
{{--            </div>--}}

{{--            <hr class="mt-3">--}}


{{--            <div class="buttons-wrap mb-3">--}}
{{--                <button type="button" class="btn btn-danger"--}}
{{--                        onclick="window.location='{{ route('admin.user.index') }}'">Назад--}}
{{--                </button>--}}
{{--                <button type="submit" class="ml-2 btn btn-primary">Создать</button>--}}
{{--            </div>--}}
{{--        </form>--}}

{{--    </div>--}}
{{--    </div>--}}
{{--    </div>--}}

{{--    <script>--}}
{{--        document.addEventListener('DOMContentLoaded', function () {--}}
{{--            // Функция для показа/скрытия пароля с помощью иконки глаза--}}
{{--            function showPassword() {--}}
{{--                const togglePassword = document.querySelector('.toggle-password');--}}
{{--                const passwordInput = document.querySelector('#passwordInput');--}}

{{--                togglePassword.addEventListener('click', function () {--}}
{{--                    // Переключаем тип input между 'password' и 'text'--}}
{{--                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';--}}
{{--                    passwordInput.setAttribute('type', type);--}}
{{--                    // Меняем иконку глаза--}}
{{--                    this.classList.toggle('fa-eye');--}}
{{--                    this.classList.toggle('fa-eye-slash');--}}
{{--                });--}}
{{--            }--}}

{{--            showPassword();--}}
{{--        });--}}
{{--    </script>--}}
{{--@endsection--}}

