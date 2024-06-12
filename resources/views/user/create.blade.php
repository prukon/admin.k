@extends('layouts/main')
@extends('layouts/header')

@extends('layouts/footer')
@extends('layouts/mainMenu')

@section('content')

    <div class="col-md-9 main-content">

        <form
                action="{{ route('user.store')}}"  method="post">
            {{--             Токен (система защиты) необходим при использовании любого роута кроме get.--}}
            @csrf
            <div class="mb-3">
                <label for="title" class="form-label">Имя ученика</label>
                    <input type="text" name="name" class="form-control" id="title">
            </div>

            <div class="mb-3">
                <label for="title" class="form-label">Дата рождения</label>
                <input type="text" name="birthday" class="form-control" id="birthday">
            </div>

            <div class="mb-3">
                <label for="team">Группа</label>
                <select class="form-control" id='team' name='team_id'>
                    @foreach($AllTeams as $team)
                        <option value="">{{$team->title}}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="formFile" class="form-label">Фото</label>
                <input class="form-control" type="file" id="formFile" name='image'>
            </div>


            <div class="mb-3">
                <label for="exampleFormControlInput1" class="form-label">Адрес электронной почты</label>
                <input name = "email" type="email" class="form-control" id="exampleFormControlInput1" placeholder="name@example.com">
            </div>

            <div class="mb-3">
                <label for="inputPassword6" class="col-form-label">Пароль</label>
                <input name='password' type="password" id="inputPassword6" class="form-control" aria-labelledby="passwordHelpInline">
                <span id="passwordHelpInline" class="form-text">
      Должно быть 8-20 символов.
    </span>
            </div>


            <div class="mb-3">
                <label for="activity">Активность</label>
                <select name = "is_enabled" class="form-control" id='activity' name='activity'>
                    <option value="1">Да</option>
                    <option value="0">Нет</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Создать</button>
        </form>


    </div>
    </div>
    </div>
@endsection