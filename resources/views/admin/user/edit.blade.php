@extends('layouts/main2')
@extends('layouts.admin2')


@section('content')

    <div class="col-md-9 main-content">

        <h4 class="mt-3">Редактирование пользователя</h4>

        <form action="{{ route('admin.user.update', $user->id)}}" method="post">
            {{--             Токен (система защиты) необходим при использовании любого роута кроме get.--}}
            @csrf
            @method('patch')
            <div class="mb-3">
                <label for="title" class="form-label">Имя ученика</label>
                <input type="text" name="name" class="form-control" id="title" value="{{ $user->name }}">
            </div>

            <div class="mb-3">
                <label for="title" class="form-label">Дата рождения</label>
                <input type="date" name="birthday" class="form-control" id="birthday" value="{{ $user->birthday }}">
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
            </div>


            <div class="mb-3">
                <label for="start_date" class="form-label">Дата начала занятий</label>
                <input type="date" name="start_date" class="form-control" id="start_date" value="{{ $user->start_date }}">
            </div>






            <div class="mb-3">
                <label for="formFile" class="form-label">Фото</label>
                <input class="form-control" type="file" id="formFile" name='image' value="{{ $user->image }}">
            </div>


            <div class="mb-3">
                <label for="exampleFormControlInput1" class="form-label">Адрес электронной почты</label>
                <input name="email" type="email" class="form-control" id="exampleFormControlInput1"
                       placeholder="name@example.com" value="{{ $user->email }}">
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

            </div>

            <span><a class="btn btn-danger" href="{{ route('admin.user.index') }}">Назад</a></span>
            <button type="submit" class="btn btn-primary">Обновить</button>

        </form>
        <form class="mt-3" action="{{ route('admin.user.delete', $user->id)}}" method="post">
            @csrf
            @method('delete')
            <input type="submit" value="Удалить" class="btn btn-danger">
        </form>



        <div>

        </div>

    </div>
    </div>
    </div>
@endsection