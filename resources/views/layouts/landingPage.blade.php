<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" href="{{ asset('img/landing/favicon.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset('img/landing/favicon.png') }}" type="image/png">

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">
    {{--JQuery--}}
    <script src="{{ asset('js/jquery/jquery-3.7.1.min.js') }}"></script>
    {{--JQuery-UI--}}
    <script src="{{ asset('js/jquery/jquery-ui.min.js') }}"></script>
    {{--Fontawesome--}}
    <script src="{{ asset('js/fontawesome/fontawesome.js') }}"></script>
    {{--bootstrap--}}
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>

    @vite([
    'resources/js/vendor.js',
    'resources/css/landing.css',
    'resources/sass/app.scss'
    ])


    <script>
        function showModalQueued(modalId, opts = {}) {
            const $current = $('.modal.show').last();                 // текущая (если есть)
            const currentId = $current.length ? $current.attr('id') : null;

            const targetEl = document.getElementById(modalId);
            if (!targetEl) return;

            // гарантируем, что модалка — прямой ребёнок body
            document.body.appendChild(targetEl);

            const target = bootstrap.Modal.getOrCreateInstance(targetEl, opts);

            // когда НОВАЯ закроется — вернуть предыдущую (если была)
            $(targetEl).off('hidden.bs.modal.return').one('hidden.bs.modal.return', function () {
                if (currentId) {
                    const prevEl = document.getElementById(currentId);
                    if (prevEl) bootstrap.Modal.getOrCreateInstance(prevEl).show();
                }
            });

            if (currentId && currentId !== modalId) {
                const prevEl = document.getElementById(currentId);
                const prev = bootstrap.Modal.getInstance(prevEl);
                // после полного скрытия предыдущей — показать новую
                $(prevEl).off('hidden.bs.modal.openNext').one('hidden.bs.modal.openNext', function () {
                    target.show();
                });
                prev.hide();
            } else {
                target.show();
            }
        }
    </script>

</head>
<body>

<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=105845730', 'ym');

    ym(105845730, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/105845730" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
<div id="app">

    <header class="bg-white shadow-sm">
        <nav class="navbar navbar-expand-md">
            <div class="container">
                <!-- Лого -->
{{--                <a class="navbar-brand" href="{{ url('/') }}">--}}
{{--                    <img src="{{ asset('img/logo3.png') }}" alt="kidscrm.online" height="80">--}}
{{--                </a>--}}


                                <a class="navbar-brand" href="{{ url('/') }}">
                                    kidscrm.online
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

@include('includes.modal.confirmDeleteModal')
@include('includes.modal.successModal')
@include('includes.modal.errorModal')

</body>
</html>
