    @extends('layouts/main2')

@extends('layouts.admin2')

@section('content')

    <script src="{{ asset('js/my-croppie.js') }}"></script>

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
                        <div id='crop-image-container'>

                        </div>
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


        {{--        <form method="POST" action="/store.blade.php" enctype="multipart/form-data">--}}
        {{--            @csrf--}}
        {{--            <input type="file" name="image" id="image-input" accept="image/*">--}}
        {{--            <div id="image-preview"></div>--}}
        {{--            <input type="hidden" name="base64_image" id="base64-image">--}}
        {{--            <button type="submit">Upload</button>--}}
        {{--        </form>--}}

        {{--        <script>--}}
        {{--            $(document).ready(function() {--}}
        {{--                var preview = new Croppie($('#image-preview')[0], {--}}
        {{--                    viewport: {--}}
        {{--                        width: 800,--}}
        {{--                        height: 400,--}}
        {{--                        type: 'square'--}}
        {{--                    },--}}
        {{--                    boundary: {--}}
        {{--                        width: 810,--}}
        {{--                        height: 410--}}
        {{--                    },--}}
        {{--                    enableResize: false,--}}
        {{--                    enableOrientation: true,--}}
        {{--                    enableExif: true,--}}
        {{--                });--}}

        {{--                $('#image-input').on('change', function(e) {--}}
        {{--                    var file = e.target.files[0];--}}
        {{--                    var reader = new FileReader();--}}

        {{--                    reader.onload = function() {--}}
        {{--                        var base64data = reader.result;--}}
        {{--                        $('#base64-image').val(base64data);--}}

        {{--                        preview.bind({--}}
        {{--                            url: base64data--}}
        {{--                        }).then(function() {--}}
        {{--                            console.log('Croppie bind complete');--}}
        {{--                        });--}}
        {{--                    }--}}

        {{--                    reader.readAsDataURL(file);--}}
        {{--                });--}}

        {{--                $('form').on('submit', function(e) {--}}
        {{--                    e.preventDefault();--}}

        {{--                    preview.result('base64').then(function(result) {--}}
        {{--                        $('#base64-image').val(result);--}}
        {{--                        $('form')[0].submit();--}}
        {{--                    });--}}
        {{--                });--}}
        {{--            });--}}
        {{--        </script>--}}


        <div>

            {{--            <h6 class="welcome-text">Добро пожаловать, <span>админ</span></h6>--}}
            @can('view', auth()->user())
                <h5 class="choose-user-header">Выбор ученика:</h5>

                {{--Выбор ученика, группы, кнопка установить--}}
                <div class="row choose-user">

                    <div class="col-3">
                        <select class="form-select" id="single-select-field" data-placeholder="ФИО">
                            <option></option>
                            @foreach($allUsers as $user)
                                <option>{{($user->name)}}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-3">
                        <select class="form-select" id="single-select-field2" data-placeholder="Группа">
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
                        <button type="button" class="btn btn-primary">Установить</button>

                    </div>
                </div>

                {{--            Чекбоксы дней недели--}}

                <div class="form-group">
                    <label for="weekdays">Расписание</label>
                    <div class="row weekday-checkbox">
                        <div class="col-12 ">
                            @foreach($weekdays as $weekday)
                                <div class="form-check form-check-inline">
                                    <input
                                            @foreach($curTeam->weekdays as $teamWeekday)
                                                {{$weekday->id === $teamWeekday->id ? 'checked' : ''}}
                                            @endforeach
                                            {{--                                            {{$weekday->id === $teamWeekday->id ? 'checked' : ''}}--}}
                                            class="form-check-input" type="checkbox" id="Monday" value="Monday">
                                    <label class="form-check-label" for="Monday">{{$weekday->title}}</label>
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
                                 src="{{$curUser->image}}" alt={{$curUser->name}}
{{--        @else  src="/img/logo2.jpg" alt=""--}}
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
                        <div class="count-training">Количество тренировок:</div>
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
                        <div class="count-training">223</div>
                    </div>
                </div>
            </div>

            <h5>Расписание:</h5>

            {{--Сезоны--}}

            <div class="row seasons">
                <div class="col-12">
                    <div class="season season-2024" id="season-2024">
                        <div class="header-season">Сезон 2023 - 2024 <i class="fa fa-chevron-down"></i><span
                                    class="display-none from">2023</span><span class="display-none to">2024</span></div>
                        <span class="is_credit">Имеется просроченная задолженность в размере <span
                                    class="is_credit_value">0</span> руб.</span>
                        <div class="row justify-content-center align-items-center container" data-season="2024"></div>
                    </div>
                    <div class="season season-2023" id="season-2023">
                        <div class="header-season">Сезон 2022 - 2023 <i class="fa fa-chevron-down"></i><span
                                    class="display-none from">2022</span><span class="display-none to">2023</span></div>
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


                <script>

            document.addEventListener('DOMContentLoaded', function() {
                createSeasons()
                clickSeason()
            });
                </script>

            {{--Создание сезонов--}}

{{--            <script> createSeasons() </script>--}}

            {{--Измерение иконок при клике --}}
            {{-- <script> clickSeason() </script>--}}
        </div>
    </div>
    </div>
    </div>
    </div>

@endsection