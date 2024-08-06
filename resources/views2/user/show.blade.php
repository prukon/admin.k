{{--@extends('layouts/main')--}}
{{--@extends('layouts/header')--}}

{{--@extends('layouts/footer')--}}
{{--@extends('layouts/mainMenu')--}}


{{--@section('content')--}}


{{--    <div class="col-md-9 main-content">--}}
{{--        <h4>Данные ученика</h4>--}}

{{--        --}}{{--        @foreach($allUsers as $user)--}}
{{--        --}}{{--            <div>{{$user->id}}.{{$user->name}}</div>--}}
{{--        --}}{{--        @endforeach--}}


{{--        <form--}}
{{--                action="{{ route('user.store')}}"  method="post">--}}
{{--            --}}{{--             Токен (система защиты) необходим при использовании любого роута кроме get.--}}
{{--            @csrf--}}
{{--            <div class="mb-3">--}}
{{--                <label for="title" class="form-label">Имя ученика1</label>--}}
{{--                <label for="title" class="form-label">1 . {{$user->name}}</label>--}}
{{--                <input type="text" name="name" class="form-control" id="title" value="{{$user->name}}">--}}
{{--            </div>--}}

{{--            <div class="mb-3">--}}
{{--                <label for="title" class="form-label">Дата рождения</label>--}}
{{--                <input type="text" name="birthday" class="form-control" id="birthday">--}}
{{--            </div>--}}

{{--            <div class="mb-3">--}}
{{--                <label for="team">Группа</label>--}}
{{--                <select class="form-control" id='team' name='team_id'>--}}
{{--                    @foreach($allTeams as $team)--}}
{{--                        <option value="">{{$team->title}}</option>--}}
{{--                    @endforeach--}}
{{--                </select>--}}
{{--            </div>--}}

{{--            <div class="mb-3">--}}
{{--                <label for="formFile" class="form-label">Фото</label>--}}
{{--                <input class="form-control" type="file" id="formFile" name='image'>--}}
{{--            </div>--}}


{{--            <div class="mb-3">--}}
{{--                <label for="exampleFormControlInput1" class="form-label">Адрес электронной почты</label>--}}
{{--                <input name = "email" type="email" class="form-control" id="exampleFormControlInput1" placeholder="name@example.com">--}}
{{--            </div>--}}

{{--            <div class="mb-3">--}}
{{--                <label for="inputPassword6" class="col-form-label">Пароль</label>--}}
{{--                <input name='password' type="password" id="inputPassword6" class="form-control" aria-labelledby="passwordHelpInline">--}}
{{--                <span id="passwordHelpInline" class="form-text">--}}
{{--      Должно быть 8-20 символов.--}}
{{--    </span>--}}
{{--            </div>--}}


{{--            <div class="mb-3">--}}
{{--                <label for="activity">Активность</label>--}}
{{--                <select name = "is_enabled" class="form-control" id='activity' name='activity'>--}}
{{--                    <option value="1">Да</option>--}}
{{--                    <option value="0">Нет</option>--}}
{{--                </select>--}}
{{--            </div>--}}

{{--        </form>--}}










{{--        <div>--}}
{{--            <form action="{{route('user.delete', $user->id)}}" method="post">--}}
{{--                @csrf--}}
{{--                @method('delete')--}}
{{--                <span> <a class="btn btn-danger" href="{{route('user.index')}}">Назад</a> </span>--}}
{{--                <span> <a class="btn btn-danger" href="{{route('user.edit', $user->id)}}">Изменить</a> </span>--}}
{{--                <input type="submit" value="Удалить" class="btn btn-danger">--}}
{{--            </form>--}}
{{--        </div>--}}


{{--    </div>--}}
{{--    </div>--}}
{{--    </div>--}}
{{--@endsection--}}