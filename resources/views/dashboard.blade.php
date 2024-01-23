@extends('layouts/main')
@extends('layouts/header')
@extends('layouts/footer')
@extends('layouts/mainMenu')

@section('content')

    {{--    <div class="container">--}}
    <div class="row">
    <div class="col-md-8 main-content">


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
            <input type="text" class="form-control" aria-label="Пример размера поля ввода"
                   aria-describedby="inputGroup-sizing-sm">
                     </span>

                <button type="button" class="btn btn-primary">Установить</button>
            </div>

            <h2>Расписание</h2>
            <div>
                <script type="text/javascript">
                    try {
                        $(function () {
                            $('#inlineCalendar').datepicker({
                                firstDay: 1,
                                dateFormat: "dd.mm.yy",
                                defaultDate: new Date(),
                                monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                                    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
                                dayNames: ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'],
                                dayNamesShort: ['вск', 'пнд', 'втр', 'срд', 'чтв', 'птн', 'сбт'],
                                dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                            });
                            $('#inlineCalendar').datepicker('setDate', new Date());
                        });
                    } catch (e) {
                    }
                </script>
            </div>
        </div>
    </div>
    </div>
@endsection