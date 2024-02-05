@extends('layouts/main')
@extends('layouts/header')
@extends('layouts/mainMenu')
@extends('layouts/footer')
@section('content')

    <div class="col-md-9 main-content" xmlns="http://www.w3.org/1999/html">

        <div>
            <h6 class="welcome-text">Добро пожаловать, <span>админ</span></h6>
            <h5 class="choose-user-header">Выбор ученика:</h5>

            {{--Выбор ученика, группы, кнопка установить--}}
            <div class="row choose-user">

                <div class="col-3">
                    <select class="form-select" id="single-select-field" data-placeholder="ФИО">
                        <option></option>
                        @foreach($users as $user)
                            <option>{{($user->name)}}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-3">
                    <select class="form-select" id="single-select-field2" data-placeholder="Группа">
                        <option></option>
                        @foreach($teams as $team)
                            <option>{{($team->title)}}</option>
                        @endforeach
                    </select>
                    <i class="fa-thin fa-calendar-lines"></i>
                </div>

                <div class="col-3">
                    <div class="input-group flex-nowrap">
                        <input type="text" id="inlineCalendar" class="form-control" placeholder="01.02.2024" aria-label="Имя
      пользователя" aria-describedby="addon-wrapping">
                        <span class="input-group-text" id="addon-wrapping"><i
                                    class="fa-solid fa-calendar-days"></i></span>
                    </div>
                    <script>
                    </script>

                </div>
                <div class="col-3">
                    <button type="button" class="btn btn-primary">Установить</button>

                </div>
            </div>

{{--            Чекбоксы дней недели--}}
            <div class="row weekday-checkbox">
                <div class="col-12 ">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="Monday" value="Monday">
                        <label class="form-check-label" for="Monday">Понедельник</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="Tuesday" value="Tuesday">
                        <label class="form-check-label" for="Tuesday">Вторник</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="Wednesday" value="Wednesday">
                        <label class="form-check-label" for="Wednesday">Среда</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="Thursday" value="Thursday">
                        <label class="form-check-label" for="Thursday">Четверг</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="Friday" value="Friday">
                        <label class="form-check-label" for="Friday">Пятница</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="Saturday" value="Saturday">
                        <label class="form-check-label" for="Saturday">Суббота</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="Sunday" value="Sunday">
                        <label class="form-check-label" for="Sunday">Воскресенье</label>
                    </div>

                </div>
            </div>

            <div class="row weekday-checkbox">
                <div class="col-12" id="weekdayContainer"></div>
            </div>





            {{--Аватарка и личные данные--}}
            <div class="row personal-data">
                <div class="col-2">
                    <div class="avatar_wrapper d-flex align-items-center justify-content-center">
                        <img src=" {{ asset('img/logo.png') }}" alt="fc-istok.ru">
                    </div>
                </div>

                <div class="col-3">
                    <div class="personal-data-header">
                        <div class="group">Группа:</div>
                        <div class="birthday">Дата рождения:</div>
                        <div class="count-training">Количество тренировок:</div>
                    </div>

                </div>
                <div class="col-7">
                    <div class="personal-data-value">
                        <div class="group">дубль <span class="change-team"> (изменить)</span></div>
                        <div class="birthday">01.01.2010</div>
                        <div class="count-training">22</div>
                    </div>
                </div>
            </div>

            <h5>Расписание:</h5>


            {{--Сезоны--}}


            <div class="row seasons">
                <div class="col-12">
                    <div class="season season-2024" id="season-2024">
                        <div class="header-season">Сезон 2023 - 2024 <i class="fa fa-chevron-down"></i><span class="display-none from">2023</span><span class="display-none to">2024</span></div>
                        <span class="is_credit">Имеется просроченная задолженность в размере <span
                                    class="is_credit_value">0</span> руб.</span>
                        <div class="row justify-content-center align-items-center container" data-season="2024"></div>
                    </div>
                    <div class="season season-2023" id="season-2023">
                            <div class="header-season">Сезон 2022 - 2023 <i class="fa fa-chevron-down"></i><span class="display-none from">2022</span><span class="display-none to">2023</span></div>
                        <span class="is_credit">Имеется просроченная задолженность в размере <span
                                    class="is_credit_value">0</span> руб.</span>
                        <div class="row justify-content-center align-items-center container" data-season="2023"></div>
                    </div>
                    <div class="season season-2022" id="season-2022">
                        <div class="header-season">Сезон 2021 - 2022 <i class="fa fa-chevron-down"></i></div>
                        <span class="is_credit">Имеется просроченная задолженность в размере <span
                                    class="is_credit_value">0</span> руб.</span>
                        <div class="row justify-content-center align-items-center container" data-season="2022"></div>
                    </div>
                </div>
            </div>

            {{--Создание сезонов--}}
            <script> createSeasons() </script>

            {{--Измерение иконок при клике --}}
            <script> clickSeason() </script>
        </div>
    </div>
    </div>
    </div>
    </div>
@endsection