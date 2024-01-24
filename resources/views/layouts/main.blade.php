 <!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/media-style.css') }}">
    <link rel="icon" href=" {{ asset('img/favicon.png') }} " type="image/png">
    @vite(['resources/js/app.js', 'resources/sass/app.scss'])
    <script src="{{ asset('js/jquery/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('js/jquery/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('js/fontawesome/fontawesome.js') }}"></script>


{{--    <script src="https://kit.fontawesome.com/a56719d62b.js" crossorigin="anonymous"></script>--}}
    <script src="{{ asset('js/main.js') }}"></script>

{{--    select2--}}
    <link rel="stylesheet" href="{{ asset('css/select2/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/select2/select2-bootstrap-5-theme.min.css') }}">
    <script src="{{ asset('js/select2/select2.full.min.js') }}"></script>

    <title>Личный кабинет</title>
</head>
<body>

@yield('header')
@yield('mainMenu')
@yield('content')
@yield('footer')

</body>
</html>