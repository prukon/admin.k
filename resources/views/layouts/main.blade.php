 <!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/media-style.css') }}">
    <link rel="icon" href=" {{ asset('img/favicon.png') }} " type="image/x-icon">
    @vite(['resources/js/app.js', 'resources/sass/app.scss'])
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="= crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
    <script src="https://kit.fontawesome.com/a56719d62b.js" crossorigin="anonymous"></script>

    <title>Document</title>
</head>
<body>
 
@yield('header')
@yield('mainMenu')
@yield('content')
@yield('footer')

</body>
</html>