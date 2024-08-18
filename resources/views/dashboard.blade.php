@extends('layouts.main2')
@extends('layouts.admin2')
@section('content')

    <script src="{{ asset('js/my-croppie.js') }}"></script>
{{--    <script src="{{ asset('js/main.js') }}"></script>--}}
    <script src="{{ asset('js/dashboard-ajax.js') }}"></script>

    <!-- CSS для Croppie -->
{{--    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css">--}}

    <!-- JS для Croppie -->
{{--    <script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>--}}



    <div class=" col-md-9 main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3">Консоль</h4>
        {{--        Модалка загрузки аватара--}}
        <div class="modal fade" id="imageModalContainer" tabindex="-1" aria-labelledby="exampleModalLabel"
             aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content modal-content1 modal-content1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="imageModal">Редактирование</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body modal-body1">
                        <div id='crop-image-container'></div>
                    </div>
                    <div class="modal-footer">
                        {{--                        <form action="{{ route('post.update', $post->id) }}" method="post">--}}
                        {{--                            @csrf--}}
                        {{--                            @method('patch')--}}
                        <button type="button" class="btn btn-secondary cancel-modal" data-dismiss="modal">Отмена
                        </button>
                        <button type="button" class="btn btn-primary save-modal">Сохранить</button>
                        {{--                        </form>--}}
                    </div>
                </div>
            </div>
        </div>


        <div>

            @can('view', auth()->user())
                <h5 class="choose-user-header">Выбор ученика:</h5>

                {{--Выбор ученика, группы, кнопка установить--}}
                <div class="row choose-user">
                    <div class="col-3">
                        <select class="form-select" id="single-select-user" data-placeholder="ФИО">
                            <option></option>
                            @foreach($allUsers as $user)
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
                             @if ($curUser->image)
                                 src="{{ asset('storage/avatars/' . $curUser->image) }}" alt="{{ $curUser->image }}"
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

            <h5 class="header-shedule display-none">Расписание:</h5>

            {{--Сезоны--}}
            {{--            fix автоматизировать--}}
            <div class="row seasons">
                <div class="col-12">

                    <div class="season season-2025" id="season-2025">
                        <div class="header-season">Сезон 2024 - 2025 <i class="fa fa-chevron-up"></i><span
                                    class="display-none from">2024</span><span class="display-none to">2025</span></div>
                        <span class="is_credit">Имеется просроченная задолженность в размере <span
                                    class="is_credit_value">0</span> руб.</span>
                        <div class="row justify-content-center align-items-center container" data-season="2025"></div>
                    </div>
                    <div class="season season-2024" id="season-2024">
                        <div class="header-season">Сезон 2023 - 2024 <i class="fa fa-chevron-up"></i><span
                                    class="display-none from">2023</span><span class="display-none to">2024</span></div>
                        <span class="is_credit">Имеется просроченная задолженность в размере <span
                                    class="is_credit_value">0</span> руб.</span>
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


            {{--            <div class="row seasons">--}}
{{--                <div class="col-12" id="seasons-container"></div>--}}
{{--            </div>--}}





            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    // showSeasonsPrice();
                    createSeasons()     //Создание сезонов
                    clickSeason()       //Измерение иконок при клике
                    hideAllSeason()     //Скрытие всех сезонов при загрузке страницы


                });
            </script>

        </div>
    </div>



    <script>
        const uploadUrl = "{{ route('profile.uploadAvatar') }}";
    </script>



{{--    Модалка--}}
    <div class="modal fade" id="uploadPhotoModal" tabindex="-1" role="dialog" aria-labelledby="uploadPhotoModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadPhotoModalLabel">Загрузка аватарки</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">



{{--                    <form id="uploadImageForm" method="POST" enctype="multipart/form-data" action="{{ route('profile.uploadAvatar') }}">--}}
{{--                        @csrf--}}
{{--                        <div class="form-group">--}}
{{--                            <label for="avatar">Выберите изображение:</label>--}}
{{--                            <input type="file" class="form-control-file" id="avatar" name="avatar" accept=".png, .jpg, .jpeg, .svg" required>--}}
{{--                        </div>--}}
{{--                    </form>--}}
                    <form id="uploadImageForm" enctype="multipart/form-data">
                        @csrf
                        <!-- Выбор файла -->
                        <input type="file" id="upload" accept="image/*">

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

@endsection