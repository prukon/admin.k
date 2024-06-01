@extends('layouts/main')
@extends('layouts/header')
@extends('layouts/mainMenu')
@extends('layouts/footer')
@section('content')

    <div class="col-md-9 main-content" xmlns="http://www.w3.org/1999/html">




        <div class='container-form'>
            <input id='selectedFile' class="disp-none" type='file' accept=".png, .jpg, .jpeg, .svg">
            <button id="upload-aphoto" class="btn-primary btn">Select image</button>
        </div>
        <div class='container-image'>
            <img id='confirm-img' src=''>
        </div>

        <div class="modal fade" id="imageModalContainer" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content modal-content1 modal-content1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="imageModal">Crop Image</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body modal-body1">
                        <div id='crop-image-container'>

                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary save-modal">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).on('click', '#upload-aphoto', function () {
                document.getElementById('selectedFile').click();
            });

            $('#selectedFile').change(function () {
                if (this.files[0] == undefined)
                    return;
                $('#imageModalContainer').modal('show');
                let reader = new FileReader();
                reader.addEventListener("load", function () {
                    window.src = reader.result;
                    $('#selectedFile').val('');
                }, false);
                if (this.files[0]) {
                    reader.readAsDataURL(this.files[0]);
                }
            });

            let croppi;
            $('#imageModalContainer').on('shown.bs.modal', function () {
                let width = document.getElementById('crop-image-container').offsetWidth - 20;
                $('#crop-image-container').height((width - 80) + 'px');
                croppi = $('#crop-image-container').croppie({
                    viewport: {
                        width: width,
                        height: width
                    },
                });
                $('.modal-body1').height(document.getElementById('crop-image-container').offsetHeight + 50 + 'px');
                croppi.croppie('bind', {
                    url: window.src,
                }).then(function () {
                    croppi.croppie('setZoom', 0);
                });
            });
            $('#imageModalContainer').on('hidden.bs.modal', function () {
                croppi.croppie('destroy');
            });

            $(document).on('click', '.save-modal', function (ev) {
                croppi.croppie('result', {
                    type: 'base64',
                    format: 'jpeg',
                    size: 'original'
                }).then(function (resp) {
                    $('#confirm-img').attr('src', resp);
                    $('.modal').modal('hide');
                });
            });
        </script>































{{--        <form method="POST" action="/store" enctype="multipart/form-data">--}}
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