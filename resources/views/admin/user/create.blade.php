@extends('layouts/main2')
@extends('layouts.admin2')


@section('content')

    <div class="col-md-9 main-content">

        <h4 class="mt-3">Создание пользователя</h4>

        <form
                action="{{ route('admin.user.store')}}" method="post">
            {{--             Токен (система защиты) необходим при использовании любого роута кроме get.--}}
            @csrf
            <div class="mb-3">
                <label for="title" class="form-label">Имя ученика*</label>
                <input type="text" name="name" class="form-control" id="title"  value="{{old('name')}}">
                @error('name' )
                <p class="text-danger">{{'Введите имя'}}</p>
                @enderror
            </div>

            <div class="mb-3">
                <label for="title" class="form-label">Дата рождения</label>
                <input type="date" name="birthday" class="form-control" id="birthday">
            </div>

            <div class="mb-3">
                <label for="team">Группа</label>


                <select class="form-select" id='team' name='team_id'>
                    @foreach($allTeams as $team)
                        <option
                        {{ old('team_id') == $team->id ? 'selected' : "" }}
                         value="{{$team->id}}">{{$team->title}}</option>
                    @endforeach
                </select>
            </div>


            <div class="mb-3">
                <label for="start_date" class="form-label">Дата начала занятий</label>
                <input type="date" name="start_date" class="form-control" id="start_date">
            </div>



            <div class="mb-3">
                <label for="formFile" class="form-label">Фото</label>
                <input class="form-control" type="file" id="formFile" name='image'>
            </div>


            <div class="mb-3">
                <label for="exampleFormControlInput1" class="form-label">Адрес электронной почты*</label>
                <input name="email" type="email" class="form-control" id="exampleFormControlInput1"
                       placeholder="name@example.com" value="{{old('email')}}">
                @error('email' )
                <p class="text-danger">{{'Введите email'}}</p>
                @enderror
            </div>

            <div class="mb-3">
                <label for="inputPassword6" class="col-form-label">Пароль</label>
                <input name='password' type="password" id="inputPassword6" class="form-control"
                       aria-labelledby="passwordHelpInline">
                <span id="passwordHelpInline" class="form-text">
      Должно быть 8-20 символов.
    </span>
            </div>


            <div class="mb-3">
                <label for="activity">Активность*</label>
                <select name="is_enabled" class="form-select" id='activity' name='activity'>
                    <option value="1">Да</option>
                    <option value="0">Нет</option>
                </select>

            </div>
            <span><a class="btn btn-danger" href="{{ route('admin.user.index') }}">Назад</a></span>
            <button type="submit" class="btn btn-primary">Создать</button>
        </form>


    </div>
    </div>
    </div>
@endsection