@section('mainMenu')
    <style>
        .btn-bd-primary {
            /*--bs-btn-font-weight: 600;*/
            --bs-btn-font-size: 14px;
            --bs-btn-color: black;
            --bs-btn-bg: white;
            --bs-btn-border-color: #f3a12b;
            --bs-btn-hover-color: white;
            --bs-btn-hover-bg: #f3a12b;
            --bs-btn-hover-border-color: #f3a12b;
            --bs-btn-active-color: #f3a12b;
        }

        .btn-bd-primary-active {
            /*--bs-btn-font-weight: 600;*/
            --bs-btn-font-size: 14px;
            --bs-btn-color: white;
            --bs-btn-bg: #f3a12b;
            --bs-btn-border-color: #f3a12b;
            --bs-btn-hover-color: white;
            --bs-btn-hover-bg: #f3a12b;
            --bs-btn-active-color: #f3a12b;
            --bs-btn-hover-border-color: #f3a12b;
            --bs-btn-focus-shadow-rgb: ;
        }

        .side-menu button {
            width: 100%;
        }

    </style>

    <div class="container">
        <div class="row">
            <div class="col-md-3 side-menu-wrapper">

                <nav class="navbar navbar-default">
                    <div class="d-grid gap-2 side-menu">
                        <a href="{{route('dashboard.index')}}">
                            <button type="button" class="btn btn-lg  btn-bd-primary ">Консоль</button>
                        </a>
                        <a href="{{route('payments.index')}}">
                            <button type="button" class="btn btn-lg  btn-bd-primary">Установка цен</button>
                        </a>
                        <a href="{{route('prices.index')}}">
                            <button type="button" class="btn btn-lg  btn-bd-primary">Детали учетной записи</button>
                        </a>
                        <a href="{{route('user.index')}}">
                            <button type="button" class="btn btn-lg  btn-bd-primary">Пользователи</button>
                        </a>
                        <a href="{{route('team.index')}}">
                            <button type="button" class="btn btn-lg  btn-bd-primary">Группы</button>
                        </a>
                    </div>
                </nav>
            </div>
{{--        </div>--}}
    {{--    </div>--}}
@endsection