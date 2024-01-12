@extends('layouts/main')
@section('content')
    <div>

        <h3>Добро пожаловать, админ</h3>
<h2>Выбор ученика</h2>
        <select>
            @foreach($users as $user)
    <option>{{($user->name)}}</option>
            @endforeach
        </select>

        <select>
            @foreach($teams as $team)
                <option>{{($team->title)}}</option>
            @endforeach
        </select>

        <span class="input-group input-group-sm mb-3">
            <input type="text" class="form-control" aria-label="Пример размера поля ввода" aria-describedby="inputGroup-sizing-sm">
        </span>

        <button type="button" class="btn btn-primary">Установить</button>
    </div>
<h2>Расписание</h2>
    <div>

    </div>
@endsection