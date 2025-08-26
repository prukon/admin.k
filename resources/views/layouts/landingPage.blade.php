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

    <link rel="stylesheet" href="{{ asset('css/select2/select2-bootstrap-5-theme.min.css') }}">

    <script src="{{ asset('js/jquery/jquery-3.7.1.min.js') }}"></script>
{{--    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>--}}
    {{--<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>--}}



    @vite([
    'resources/js/vendor.js',

    {{--'resources/js/common-scripts.js',--}}
    {{--'resources/js/landing.js',--}}

    'resources/css/landing.css',
    'resources/sass/app.scss'
    ])

</head>
<body>

<div id="app">

    <header class="bg-white shadow-sm">
        <nav class="navbar navbar-expand-md">
            <div class="container">
                <!-- Лого -->
                <a class="navbar-brand" href="{{ url('/') }}">
                    <img src="{{ asset('img/logo3.png') }}" alt="кружок.online" height="80">
                </a>

                <!-- Кнопка‑гамбургер -->
                <button class="navbar-toggler" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#mainNav"
                        aria-controls="mainNav"
                        aria-expanded="false"
                        aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Содержимое меню -->
                <div class="collapse navbar-collapse" id="mainNav">
                    <!-- Центрированное меню -->
                    <ul class="navbar-nav mx-auto mb-2 mb-md-0">
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="#how-it-works">Как это работает</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="#features">Функционал</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="#advantages">Преимущества</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="#pricing">Цены</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="#contacts">Контакты</a>
                        </li>
                    </ul>

                    <!-- Кнопки авторизации -->
                    <ul class="navbar-nav ms-auto">
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="btn btn-primary  me-2" href="{{ route('login') }}">Войти</a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button"
                                   data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ Auth::user()->name }}
                                </a>

                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <form method="POST" action="{{ route('dashboard') }}">@csrf
                                            <button type="submit"
                                                    class="dropdown-item d-flex align-items-center  hover-underline">
                                                <img src="{{ asset('img/landing/icons/login/home.png') }}"
                                                     alt="Иконка договора"
                                                     class="me-2"
                                                     style="width:24px; height:24px; object-fit:contain;">
                                                В личный кабинет
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">@csrf
                                            <button type="submit"
                                                    class="dropdown-item d-flex align-items-center  hover-underline">
                                                <img src="{{ asset('img/landing/icons/login/exit.png') }}"
                                                     alt="Иконка договора"
                                                     class="me-2"
                                                     style="width:24px; height:24px; object-fit:contain;">
                                                Выйти
                                            </button>
                                        </form>
                                    </li>
                                </ul>

                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="py-4">
        @yield('content')
    </main>
</div>

@yield('scripts')

</body>
</html>
