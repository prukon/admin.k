{{--Страница авторизации (логин--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{--    <title>{{ config('app.name', 'Laravel') }}</title>--}}
    <title>@yield('title', config('app.name'))</title>

    <link rel="icon" href="{{ asset('img/landing/favicon.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset('img/landing/favicon.png') }}" type="image/png">

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>

    @vite([
    'resources/js/common-scripts.js',
    'resources/js/landing.js',
    'resources/css/landing.css',
    'resources/sass/app.scss'
    ])

</head>
<body>

<div id="app">

    @include('includes.public-navbar')

    <main class="py-4">
        @yield('content')
    </main>
</div>

@yield('scripts')

</body>
</html>
