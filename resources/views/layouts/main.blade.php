<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
<h2>header</h2>



<div>
    <div>
        <nav>
            <ul>
                <li><a href="{{route('dashboard.index')}}">Консоль</a></li>
                <li><a href="{{route('payments.index')}}">Заказ</a></li>
                <li><a href="{{route('prices.index')}}">Установка цен</a></li>
                <li><a href="{{route('users.index')}}">Пользователи</a></li>
                <li><a href="#">Выйти</a></li>
            </ul>
        </nav>
    </div>
</div>
@yield('content')

<h2>footer</h2>
</body>
</html>