@extends('layouts/main2')
@extends('layouts.admin2')

@section('content')

<div class="col-md-9 main-content">

        <h4 class="mt-3">Создание группы</h4>

        <form action="{{ route('admin.team.store', $team->id)}}" method="post">
            @csrf
            <div class="mb-3">
                <label for="title" class="form-label">Название группы*</label>
                <input type="text" name="title" class="form-control" id="title" value="{{old('title')}}">
                @error('title' )
                <p class="text-danger">{{'Введите название'}}</p>
                @enderror
            </div>
            <div class="mb-3">

{{--                <div class="form-group">--}}
{{--                    <label for="weekdays">Расписание*</label>--}}
{{--                    <select multiple class="form-control" id="weekdays" name="weekdays[]">--}}
{{--                        @foreach($weekdays as $weekday)--}}
{{--                            <option value="{{$weekday->id}}">{{$weekday->title}}</option>--}}
{{--                        @endforeach--}}
{{--                        --}}{{--                        @error('weekdays')--}}
{{--                        --}}{{--                        <p class="text-danger">{{'Укажите расписание'}}</p>--}}
{{--                        --}}{{--                        @enderror--}}
{{--                    </select>--}}

{{--                    @error('weekdays') --}}
{{--                    <p class="text-danger">{{'Укажите дни недели'}}</p>--}}
{{--                    @enderror--}}
{{--                </div>--}}
                <div class="form-group">
                    <label for="weekdays">Расписание*</label>
                    <select multiple class="form-control" id="weekdays" name="weekdays[]">
                        @foreach($weekdays as $weekday)
                            <option value="{{$weekday->id}}">{{$weekday->title}}</option>
                        @endforeach
                    </select>

                    @error('weekdays')
                    <p class="text-danger">{{'Укажите дни недели'}}</p>
                    @enderror
                </div>




            </div>

            <div class="mb-3">
                <label for="title" class="form-label">Сортировка</label>
                <input type="text" name="order_by" placeholder="10" class="form-control" id="order_by"
                       value="{{old('order_by')}}">

            </div>

            <div class="mb-3">
                <label for="activity">Активность</label>
                <select name="is_enabled" class="form-control" id='activity' name='activity'>
                    <option value="1"> {{"Активен"}} </option>
                    <option value="0"> {{"Неактивен"}} </option>
                </select>
            </div>

            <span><a class="btn btn-danger" href="{{ route('admin.team.index') }}">Назад</a></span>
            <button type="submit" class="btn btn-primary">Создать</button>

        </form>

        <div>

        </div>

    </div>
    </div>
    </div>
@endsection