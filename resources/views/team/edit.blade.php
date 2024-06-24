@extends('layouts/main')
@extends('layouts/header')

@extends('layouts/footer')
@extends('layouts/mainMenu')

@section('content')

    <div class="col-md-9 main-content">

        <h4 class="mt-3">Редактирование группы</h4>

        <form action="{{ route('team.update', $team->id)}}" method="post">
            @csrf
            @method('patch')
            <div class="mb-3">
                <label for="title" class="form-label">Название группы</label>
                <input type="text" name="title" class="form-control" id="title" value="{{ $team->title }}">
            </div>

            <div class="mb-3">
                <label for="title" class="form-label">Расписание</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="monday">
                    <label class="form-check-label" for="monday">
                        Понедельник
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="tuesday">
                    <label class="form-check-label" for="tuesday">
                        Вторник
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="wednesday">
                    <label class="form-check-label" for="wednesday">
                        Среда
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="thursday">
                    <label class="form-check-label" for="thursday">
                        Четверг
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="friday">
                    <label class="form-check-label" for="friday">
                        Пятница
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="saturday">
                    <label class="form-check-label" for="saturday">
                        Суббота
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="sunday">
                    <label class="form-check-label" for="sunday">
                        Воскресенье
                    </label>
                </div>

            </div>

            <div class="mb-3">
                <label for="title" class="form-label">Сортировка</label>
                <input type="text" name="order_by" class="form-control" id="order_by" value="{{ $team->order_by }}">
            </div>

            <div class="mb-3">
                <label for="activity">Активность</label>
                <select name="is_enabled" class="form-control" id='activity' name='activity'>
                    @for($i=0; $i<2;$i++)
                        <option
                                {{ $i == $team->is_enabled ? 'selected' : ''}}
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

            <span><a class="btn btn-danger" href="{{ route('team.index') }}">Назад</a></span>
            <button type="submit" class="btn btn-primary">Обновить</button>

        </form>
        <form class="mt-3" action="{{ route('team.delete', $team->id)}}" method="post">
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