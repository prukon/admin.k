@extends('layouts/main2')
@extends('layouts.admin2')

@section('content')

    <div class="col-md-9 main-content team-data">

        <h4 class="mt-3">Создание группы</h4>

        <form action="{{ route('admin.team.store')}}" method="post">
            @csrf
            <div class="mb-3">
                <label for="title" class="form-label">Название группы*</label>
                <input type="text" name="title" class="form-control" id="title" value="{{old('title')}}">
                @error('title')
                <p class="text-danger">{{'Введите название'}}</p>
                @enderror
            </div>

            <div class="mb-3">
                <div class="form-group">
                    <label for="weekdays">Расписание*</label>
                    <div id="weekdays">
                        @foreach($weekdays as $weekday)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="weekday-{{$weekday->id}}" name="weekdays[]"
                                       value="{{$weekday->id}}">
                                <label class="form-check-label" for="weekday-{{$weekday->id}}">
                                    {{$weekday->title}}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('weekdays')
                    <p class="text-danger">{{'Укажите дни недели'}}</p>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="order_by" class="form-label">Сортировка</label>
                <input type="text" name="order_by" placeholder="10" class="form-control" id="order_by" value="{{old('order_by')}}">
            </div>

            <div class="mb-3">
                <label for="activity">Активность</label>
                <select name="is_enabled" class="form-control" id='activity'>
                    <option value="1">Активен</option>
                    <option value="0">Неактивен</option>
                </select>
            </div>

            <span><a class="btn btn-danger" href="{{ route('admin.team.index') }}">Назад</a></span>
            <button type="submit" class="btn btn-primary">Создать</button>

        </form>

    </div>
@endsection
