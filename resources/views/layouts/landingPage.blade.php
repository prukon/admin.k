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

                <!-- Кнопка-гамбургер -->
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

                        {{-- ▼▼ ДОБАВЛЕНО: выпадающее меню со ссылками на SEO-лендинги ▼▼ --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-dark"
                               href="#"
                               id="solutionsDropdown"
                               role="button"
                               data-bs-toggle="dropdown"
                               aria-expanded="false">
                                Решения для
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="solutionsDropdown">
                                <li>
                                    <a class="dropdown-item {{ request()->is('crm-dlya-futbolnoy-sekcii') ? 'active' : '' }}"
                                       href="/crm-dlya-futbolnoy-sekcii">
                                        Футбольных секций
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->is('crm-dlya-tancevalnoy-studii') ? 'active' : '' }}"
                                       href="/crm-dlya-tancevalnoy-studii">
                                        Танцевальных студий
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->is('crm-dlya-shkoly-edinoborstv') ? 'active' : '' }}"
                                       href="/crm-dlya-shkoly-edinoborstv">
                                        Школ единоборств
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->is('crm-dlya-detskogo-razvivayushchego-centra') ? 'active' : '' }}"
                                       href="/crm-dlya-detskogo-razvivayushchego-centra">
                                        Детских развивающих центров
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->is('crm-dlya-shkol-gimnastiki-i-akrobatiki') ? 'active' : '' }}"
                                       href="/crm-dlya-shkol-gimnastiki-i-akrobatiki">
                                        Школ гимнастики и акробатики
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->is('crm-dlya-detskih-yazykovyh-shkol') ? 'active' : '' }}"
                                       href="/crm-dlya-detskih-yazykovyh-shkol">
                                        Детских языковых школ
                                    </a>
                                </li>
                            </ul>
                        </li>
                        {{-- ▲▲ КОНЕЦ ДОБАВЛЕННОГО БЛОКА ▲▲ --}}

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
                                    <a class="btn btn-primary me-2" href="{{ route('login') }}">Войти</a>
                                </li>
                            @endif
                            {{--
                                Временно скрыто: кнопка регистрации в шапке (по запросу).
                                При необходимости вернуть — раскомментировать блок ниже.
                            --}}
                            {{--
                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="btn btn-outline-primary" href="{{ route('register') }}">Регистрация</a>
                                </li>
                            @endif
                            --}}
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
                                                    class="dropdown-item d-flex align-items-center hover-underline">
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
                                                    class="dropdown-item d-flex align-items-center hover-underline">
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
@include('includes.modal.order') {{-- Модальное окно заявки на ленде --}}

<footer>
    <!-- Контакты -->
    <section id="contacts" class="py-5 bg-light">
        <div class="container">
            <div class="row gy-4 align-items-start">

                {{-- Реквизиты --}}
                <div class="col-md-4">
                    <h3 class="fw-bold mb-4">Реквизиты</h3>
                    <ul class="list-unstyled mb-4">
                        <li class="mb-2"><strong>ИП:</strong> Устьян Евгений Артурович</li>
                        <li class="mb-2"><strong>ИНН:</strong> 110211351590</li>
                        <li><strong>ЕГРНИП:</strong> 324784700017432</li>
                    </ul>

                    <h5 class="fw-bold mb-3">Соцсети и Email</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="mailto:kidscrmonline@gmail.com"
                                    class="d-flex align-items-center text-dark text-decoration-none">
                                <img src="{{ asset('img/landing/icons/social/gmail.png') }}"
                                     alt="Email"
                                     class="me-2"
                                     style="width:24px; height:24px; object-fit:contain;">
                                 <span>kidscrmonline@gmail.com</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="https://t.me/prukon" target="_blank"
                               class="d-flex align-items-center text-dark text-decoration-none">
                                <img src="{{ asset('img/landing/icons/social/telegram.png') }}"
                                     alt="Telegram"
                                     class="me-2"
                                     style="width:24px; height:24px; object-fit:contain;">
                                <span>@prukon</span>
                            </a>
                        </li>
                        <li>
                            <a href="https://vk.com/prukon" target="_blank"
                               class="d-flex align-items-center text-dark text-decoration-none">
                                <img src="{{ asset('img/landing/icons/social/vk.png') }}"
                                     alt="ВКонтакте"
                                     class="me-2"
                                     style="width:24px; height:24px; object-fit:contain;">
                                <span>@prukon</span>
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- Решения для (SEO-блок) --}}
                <div class="col-md-4">
                    <h3 class="fw-bold mb-4">Решения для</h3>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="/crm-dlya-futbolnoy-sekcii"
                               class="text-dark text-decoration-none {{ request()->is('crm-dlya-futbolnoy-sekcii') ? 'fw-bold' : '' }}">
                                Футбольных секций
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="/crm-dlya-tancevalnoy-studii"
                               class="text-dark text-decoration-none {{ request()->is('crm-dlya-tancevalnoy-studii') ? 'fw-bold' : '' }}">
                                Танцевальных студий
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="/crm-dlya-shkoly-edinoborstv"
                               class="text-dark text-decoration-none {{ request()->is('crm-dlya-shkoly-edinoborstv') ? 'fw-bold' : '' }}">
                                Школ единоборств
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="/crm-dlya-detskogo-razvivayushchego-centra"
                               class="text-dark text-decoration-none {{ request()->is('crm-dlya-detskogo-razvivayushchego-centra') ? 'fw-bold' : '' }}">
                                Детских развивающих центров
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="/crm-dlya-shkol-gimnastiki-i-akrobatiki"
                               class="text-dark text-decoration-none {{ request()->is('crm-dlya-shkol-gimnastiki-i-akrobatiki') ? 'fw-bold' : '' }}">
                                Гимнастики и акробатики
                            </a>
                        </li>
                        <li>
                            <a href="/crm-dlya-detskih-yazykovyh-shkol"
                               class="text-dark text-decoration-none {{ request()->is('crm-dlya-detskih-yazykovyh-shkol') ? 'fw-bold' : '' }}">
                                Языковых школ
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- Связаться --}}
                <div class="col-md-4">
                    <h3 class="fw-bold mb-4">Свяжитесь с нами</h3>
                    <p class="text-muted mb-4">
                        Оставьте сообщение – мы оперативно ответим и поможем запустить вашу детскую школу
                        без лишних забот.
                    </p>
                    <div class="d-flex flex-wrap">
                        <a href="mailto:kidscrmonline@gmail.com"
                           class="btn btn-primary me-3 mb-2 d-flex align-items-center">
                            <img src="{{ asset('img/landing/icons/social/gmail.png') }}"
                                 alt="Email"
                                 class="me-2"
                                 style="width:20px; height:20px; object-fit:contain;">
                            Написать на Email
                        </a>
                        <a href="https://t.me/prukon" target="_blank"
                           class="btn btn-outline-primary me-3 mb-2 d-flex align-items-center">
                            <img src="{{ asset('img/landing/icons/social/telegram.png') }}"
                                 alt="Telegram"
                                 class="me-2"
                                 style="width:20px; height:20px; object-fit:contain;">
                            Telegram
                        </a>
                        <a href="https://vk.com/prukon" target="_blank"
                           class="btn btn-outline-primary mb-2 d-flex align-items-center">
                            <img src="{{ asset('img/landing/icons/social/vk.png') }}"
                                 alt="ВКонтакте"
                                 class="me-2"
                                 style="width:20px; height:20px; object-fit:contain;">
                            ВКонтакте
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Кусты -->
    <section id="bushes" class="bg-light py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 text-center">
                    <img src="{{ asset('img/landing/bushes.png') }}"
                         alt="bushes"
                         class="img-fluid rounded mx-auto d-block">
                </div>
            </div>
        </div>
    </section>

    <!-- Нижний футер -->
    <div class="bg-dark text-white text-center py-4">
        <div class="container">
            <p class="mb-1">Все права защищены. 2024 – {{ date('Y') }} kidscrm.online &copy;</p>
            <div>
                <a href="/oferta" class="text-white text-decoration-none mx-2">Оферта</a>
                <a href="{{ route('privacy.policy') }}"
                   class="text-white text-decoration-none mx-2">
                    Политика конфиденциальности
                </a>
            </div>
        </div>
    </div>
</footer>



</body>
</html>
