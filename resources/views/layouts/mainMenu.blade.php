@section('mainMenu')

    <script>
        $(document).ready(function () {
            // Восстановление активной кнопки при загрузке страницы
            var activeButtonIndex = localStorage.getItem('activeButtonIndex');
            if (activeButtonIndex !== null) {
                $('.side-menu a').eq(activeButtonIndex).find('button').addClass('btn-bd-primary-active');
            }

            $('.side-menu a').click(function () {
                // Удаление класса btn-bd-primary-active со всех кнопок
                // $('.side-menu a button').removeClass('btn-bd-primary-active');

                // Добавление класса btn-bd-primary-active к текущей кнопке
                // var button = $(this).find('button');
                // button.addClass('btn-bd-primary-active');

                // Сохранение индекса активной кнопки в локальное хранилище
                var activeButtonIndex = $('.side-menu a').index($(this));
                localStorage.setItem('activeButtonIndex', activeButtonIndex);
            });
        });
    </script>

    <div class="container">
        <div class="row">
            <div class="col-md-4">


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
                        <a href="{{route('users.index')}}">
                            <button type="button" class="btn btn-lg  btn-bd-primary">Пользователи</button>
                        </a>
                    </div>
                </nav>
            </div>
        </div>
    {{--    </div>--}}
@endsection