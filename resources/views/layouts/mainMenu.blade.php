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

    <script>
        $(document).ready(function() {
            // Восстановление активной кнопки при загрузке страницы
            var activeButtonIndex = localStorage.getItem('activeButtonIndex');
            if (activeButtonIndex !== null) {
                $('.side-menu a').eq(activeButtonIndex).find('button').addClass('btn-bd-primary-active');
            }

            $('.side-menu a').click(function() {
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

    <nav class="navbar navbar-default">
        <div class="container">
                <div class="d-grid gap-2 side-menu">
                    <a href="{{route('payments.index')}}">
                        <button type="button" class="btn btn-lg  btn-bd-primary ">Консоль</button>
                    </a>
                    <a href="{{route('prices.index')}}">
                        <button type="button" class="btn btn-lg  btn-bd-primary">Установка цен</button>
                    </a>
                    <a href="{{route('prices.index')}}">
                        <button type="button" class="btn btn-lg  btn-bd-primary">Детали учетной записи</button>
                    </a>
                    <a href="{{route('users.index')}}">
                        <button type="button" class="btn btn-lg  btn-bd-primary">Пользователи</button>
                    </a>
                </div>
        </div>
    </nav>



@endsection