@extends('layouts.main2')
@extends('layouts.admin2')
@section('content')

    {{--    <script src="{{ asset('js/my-croppie.js') }}"></script>--}}
    {{--    <script src="{{ asset('js/main.js') }}"></script>--}}
    <script src="{{ asset('js/dashboard-ajax.js') }}"></script>


    <script>
        // Передача данных текущего пользователя из Blade в JavaScript
        let currentUserName = "{{ auth()->user()->name }}";
        let currentUserRole = "{{ auth()->user()->role }}";
        {{--        let isUserAuthenticated = "{{ auth()->check() ? 'true' : 'false' }}";--}}

    </script>


    <div class=" col-md-9 main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3">Консоль</h4>
        <div>

            @can('view', auth()->user())
                <h5 class="choose-user-header">Выбор ученика:</h5>

                {{--Выбор ученика, группы, кнопка установить--}}
                <div class="row choose-user">
                    <div class="col-3">
                        <select class="form-select" id="single-select-user" data-placeholder="ФИО">
                            <option></option>
                            @foreach($allUsersSelect as $user)
                                <option label="{{$user->label}}">{{($user->name)}}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-3">
                        <select class="form-select" id="single-select-team" data-placeholder="Группа">
                            <option></option>
                            @foreach($allTeams as $team)
                                <option>{{($team->title)}}</option>
                            @endforeach
                        </select>
                        <i class="fa-thin fa-calendar-lines"></i>
                    </div>

                    <div class="col-3">
                        <div class="input-group flex-nowrap">
                            <input type="text" id="inlineCalendar" class="form-control" placeholder="01.02.2024"
                                   aria-label="Имя
      пользователя" aria-describedby="addon-wrapping">
                            <span class="input-group-text" id="addon-wrapping"><i
                                        class="fa-solid fa-calendar-days"></i></span>
                        </div>
                        <script>
                        </script>
                    </div>
                    <div class="col-3">
                        <button type="button" disabled id="setup-btn" class="btn btn-primary">Установить</button>

                    </div>
                </div>

                {{--            Чекбоксы дней недели--}}

                <div class="form-group">
                    <label for="weekdays">Расписание</label>
                    <div class="row weekday-checkbox">
                        <div class="col-12 ">
                            @foreach($weekdays as $weekday)
                                <div class="form-check form-check-inline weekday-disabled">
                                    <input
                                            @if($curTeam)
                                                @foreach($curTeam->weekdays as $teamWeekday)
                                                    {{$weekday->id === $teamWeekday->id ? 'checked' : ''}}
                                                @endforeach
                                            @endif
                                            {{--                                            {{$weekday->id === $teamWeekday->id ? 'checked' : ''}}--}}
                                            class="form-check-input " type="checkbox" id="{{$weekday->titleEn}}"
                                            value="{{$weekday->titleEn}}">
                                    <label class="form-check-label"
                                           for="{{$weekday->titleEn}}">{{$weekday->title}}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="row weekday-checkbox">
                    <div class="col-12" id="weekdayContainer"></div>
                </div>
            @endcan

            {{--Аватарка и личные данные--}}
            <div class="row personal-data">

                <div class="col-2">
                    <div class="avatar_wrapper d-flex align-items-center justify-content-center">
                        <img id='confirm-img'
                             @if ($curUser->image_crop)
                                 src="{{ asset('storage/avatars/' . $curUser->image_crop) }}"
                             alt="{{ $curUser->image_crop }}"
                             @else  src="/img/default.png" alt=""
                                @endif
                        >
                    </div>
                    <div class='container-form'>
                        <input id='selectedFile' class="disp-none" type='file' accept=".png, .jpg, .jpeg, .svg">
                        <button id="upload-photo" class="btn-primary btn">Выбрать фото...</button>
                    </div>
                </div>


                <div class="col-3">
                    <div class="personal-data-header">
                        <div class="group">Группа:</div>
                        <div class="birthday">Дата рождения:</div>
                        {{--                        <div class="count-training">Количество тренировок:</div>--}}
                    </div>

                </div>
                <div class="col-7">
                    <div class="personal-data-value">
                        <div class="group">
                            @if($curTeam)
                                {{$curTeam->title}}
                            @else
                                -
                            @endif
                        </div>
                        <div class="birthday">
                            @if($curUser->birthday)
                                {{$curUser->birthday}}
                            @else
                                -
                            @endif
                        </div>
                        {{--                        <div class="count-training">223</div>--}}
                    </div>
                </div>
            </div>

            <script>

                document.addEventListener("DOMContentLoaded", function () {
                   // передача расписания юзера для календаря
                    var scheduleUser = {!! json_encode($scheduleUserArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!};
                    updateGlobalScheduleData(scheduleUser);
                });

            </script>

            <h5 class="header-shedule display-none">Расписание:</h5>

            <div class="mt-3 mb-3 calendar">
                <div class="calendar-header">
                    <div id="prev-month">←</div>
                    <div id="calendar-title"></div>
                    <div id="next-month">→</div>
                </div>
                <div class="days-header">
                    <div>Пн</div>
                    <div>Вт</div>
                    <div>Ср</div>
                    <div>Чт</div>
                    <div>Пт</div>
                    <div>Сб</div>
                    <div>Вс</div>
                </div>
                <div class="days" id="days"></div>


                <!-- Контекстное меню -->
                <div id="context-menu" class="context-menu">
                    <div class="context-menu-item" data-action="add-freeze">Добавление заморозки</div>
                    <div class="context-menu-item" data-action="remove-freeze">Удаление заморозки</div>
                    <div class="context-menu-item" data-action="add-training">Добавление тренировки</div>
                    <div class="context-menu-item" data-action="remove-training">Удаление тренировки</div>
                    {{--                    <div class="context-menu-item" data-action="add-payment">Добавление оплаты</div>--}}
                    {{--                    <div class="context-menu-item" data-action="remove-payment">Удаление оплаты</div>--}}
                </div>
            </div>


            {{--Сезоны--}}
            {{--            fix автоматизировать--}}
            <div class="row seasons">
                <div class="col-12">

                    <div class="season season-2025" id="season-2025">
                        <div class="header-season">Сезон 2024 - 2025 <i class="fa fa-chevron-up"></i><span
                                    class="display-none from">2024</span><span class="display-none to">2025</span></div>
                        <span class="is_credit">Имеется просроченная задолженность в размере <span
                                    class="is_credit_value">0</span> руб.</span>
                        <span class="display-none1 total-summ"></span>
                        <div class="row justify-content-center align-items-center container" data-season="2025"></div>
                    </div>

                    <div class="season season-2024" id="season-2024">
                        <div class="header-season">Сезон 2023 - 2024 <i class="fa fa-chevron-up"></i><span
                                    class="display-none from">2023</span><span class="display-none to">2024</span></div>
                        <span class="is_credit">Имеется просроченная задолженность в размере <span
                                    class="is_credit_value">0</span> руб.</span>
                        <span class="display-none1 total-summ"></span>
                        <div class="row justify-content-center align-items-center container" data-season="2024"></div>
                    </div>

                    <div class="season season-2023" id="season-2023">
                        <div class="header-season">Сезон 2022 - 2023 <i class="fa fa-chevron-up"></i><span
                                    class="display-none from">2022</span><span class="display-none to">2023</span></div>
                        <span class="is_credit">Имеется просроченная задолженность в размере <span
                                    class="is_credit_value">0</span> руб.</span>
                        <div class="row justify-content-center align-items-center container" data-season="2023"></div>
                    </div>

                    <div class="season season-2022" id="season-2022">
                        <div class="header-season">Сезон 2021 - 2022 <i class="fa fa-chevron-up"></i></div>
                        <span class="is_credit">Имеется просроченная задолженность в размере <span
                                    class="is_credit_value">0</span> руб.</span>
                        <div class="row justify-content-center align-items-center container" data-season="2022"></div>
                    </div>
                </div>
            </div>


            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    // showSeasonsPrice();
                    createSeasons()     //Создание сезонов
                    clickSeason()       //Измерение иконок при клике
                    hideAllSeason()     //Скрытие всех сезонов при загрузке страницы
                    createCalendar();
                });
            </script>

        </div>
    </div>



    <script>
        const uploadUrl = "{{ route('profile.uploadAvatar') }}";
    </script>


    {{--    Модалка--}}
    <div class="modal fade" id="uploadPhotoModal" tabindex="-1" role="dialog" aria-labelledby="uploadPhotoModalLabel"
         aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadPhotoModalLabel">Загрузка аватарки</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form id="uploadImageForm" enctype="multipart/form-data">
                        @csrf
                        <!-- Выбор файла -->
                        <input class="mb-3" type="file" id="upload" accept="image/*">

                        <!-- Контейнер для Croppie -->
                        <div id="upload-demo" style="width:300px;"></div>

                        <!-- Скрытое поле для сохранения имени пользователя -->
                        <input type="hidden" id="selectedUserName" name="userName" value="">

                        <!-- Скрытое поле для обрезанного изображения -->
                        <input type="hidden" id="croppedImage" name="croppedImage">

                        <!-- Кнопка для сохранения изображения -->
                        <button type="button" id="saveImageBtn" class="btn btn-primary">Загрузить</button>
                    </form>


                </div>
                {{--                <div class="modal-footer">--}}
                {{--                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>--}}
                {{--                    <button type="button" class="btn btn-primary" id="saveImageBtn">Загрузить</button>--}}
                {{--                </div>--}}
            </div>
        </div>
    </div>


    <script>
        {{--    console.log({{ $scheduleUser }});--}}

    </script>
@endsection