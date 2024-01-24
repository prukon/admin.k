@extends('layouts/main')
@extends('layouts/header')
@extends('layouts/mainMenu')
@extends('layouts/footer')


@section('content')

    {{--    <div class="container">--}}
    {{--    <div class="row">--}}
    <div class="col-md-9 main-content">


        <div>
            <h6 class="welcome-text">Добро пожаловать, <span>админ</span></h6>
            <h5 class="choose-user">Выбор ученика:</h5>

            <div class="row">
                <div class="col-3">

                    <select class="form-select" id="single-select-field" data-placeholder="Choose one thing">
                        <option></option>
                        <option>Reactive</option>
                        <option>Solution</option>
                        <option>Conglomeration</option>
                        <option>Algoritm</option>
                        <option>Holistic</option>
                    </select>
{{--                    <select>--}}
                        {{--            @foreach($users as $user)--}}
                        {{--                <option>{{($user->name)}}</option>--}}
                        {{--            @endforeach--}}
{{--                    </select>--}}
                </div>
                <div class="col-3">

                    <select class="form-select" id="single-select-field2" data-placeholder="Choose one thing">
                        <option></option>
                        <option>Reactive</option>
                        <option>Solution</option>
                        <option>Conglomeration</option>
                        <option>Algoritm</option>
                        <option>Holistic</option>
                    </select>

{{--                    <select>--}}
{{--                        @foreach($teams as $team)--}}
{{--                            <option>{{($team->title)}}</option>--}}
{{--                        @endforeach--}}
{{--                    </select>--}}
                </div>
                <div class="col-3">
                    <input type="text" class="form-control" aria-label="Пример размера поля ввода"
                           aria-describedby="inputGroup-sizing-sm">
                </div>
                <div class="col-3">
                    <button type="button" class="btn btn-primary">Установить</button>

                </div>
            </div>


        </div>

        <h5>Расписание:</h5>
        <div>
{{--            <script type="text/javascript">--}}
{{--                try {--}}
{{--                    $(function () {--}}
{{--                        $('#inlineCalendar').datepicker({--}}
{{--                            firstDay: 1,--}}
{{--                            dateFormat: "dd.mm.yy",--}}
{{--                            defaultDate: new Date(),--}}
{{--                            monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',--}}
{{--                                'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],--}}
{{--                            dayNames: ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'],--}}
{{--                            dayNamesShort: ['вск', 'пнд', 'втр', 'срд', 'чтв', 'птн', 'сбт'],--}}
{{--                            dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],--}}
{{--                        });--}}
{{--                        $('#inlineCalendar').datepicker('setDate', new Date());--}}
{{--                    });--}}
{{--                } catch (e) {--}}
{{--                }--}}
{{--            </script>--}}
        </div>
    </div>
    </div>
    </div>
@endsection