    @section('mainMenu')
{{--        <div>--}}
{{--            <div>--}}
{{--                <nav>--}}
{{--                    <ul>--}}
{{--                        <li><a href="{{route('dashboard.index')}}">Консоль</a></li>--}}
{{--                        <li><a href="{{route('payments.index')}}">Заказ</a></li>--}}
{{--                        <li><a href="{{route('prices.index')}}">Установка цен</a></li>--}}
{{--                        <li><a href="{{route('users.index')}}">Пользователи</a></li>--}}
{{--                        <li><a href="#">Выйти</a></li>--}}
{{--                    </ul>--}}
{{--                </nav>--}}
{{--            </div>--}}
{{--        </div>--}}

        <nav class="navbar navbar-default">
            <div class="container">
                <div>
                    <ul class="nav navbar-nav">
                        <li class="active"><a href="{{route('dashboard.index')}}">Консоль</a></li>
                        <li><a href="{{route('payments.index')}}">Заказ</a></li>
                        <li><a href="{{route('prices.index')}}">Установка цен</a></li>
                        <li><a href="{{route('users.index')}}">Пользователи</a></li>
                        <li><a href="#">Выйти</a></li>
                    </ul>
                </div>
            </div>
        </nav>

    @endsection