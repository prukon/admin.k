<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $pageTitle = trim($__env->yieldContent('title', config('app.name')));
        $pageDescription = trim($__env->yieldContent(
            'meta_description',
            'kidscrm.online — современная CRM для детских спортивных и творческих секций, кружков и студий. Автоматизируйте учет учеников, расписание, оплаты, долги и онлайн-договоры. Попробуйте бесплатно!'
        ));
        $pageCanonical = trim($__env->yieldContent('canonical', url()->current()));
        $pageRobots = trim($__env->yieldContent('meta_robots', 'index,follow'));

        $pageOgType = trim($__env->yieldContent('og_type', 'website'));
        $pageOgImage = trim($__env->yieldContent('og_image', asset('img/landing/dashboard.png')));
        $pageOgTitle = trim($__env->yieldContent('og_title', $pageTitle));
        $pageOgDescription = trim($__env->yieldContent('og_description', $pageDescription));
        $pageOgUrl = trim($__env->yieldContent('og_url', $pageCanonical));

        $pageTwitterCard = trim($__env->yieldContent('twitter_card', 'summary_large_image'));
        $pageTwitterTitle = trim($__env->yieldContent('twitter_title', $pageTitle));
        $pageTwitterDescription = trim($__env->yieldContent('twitter_description', $pageDescription));
        $pageTwitterImage = trim($__env->yieldContent('twitter_image', $pageOgImage));
    @endphp

    <title>{{ $pageTitle }}</title>

    <meta name="description" content="{{ $pageDescription }}">
    @hasSection('meta_keywords')
        <meta name="keywords" content="@yield('meta_keywords')">
    @endif
    <meta name="robots" content="{{ $pageRobots }}">
    <link rel="canonical" href="{{ $pageCanonical }}">

    <meta property="og:site_name" content="kidscrm.online" />
    <meta property="og:locale" content="ru_RU" />
    <meta property="og:title" content="{{ $pageOgTitle }}" />
    <meta property="og:description" content="{{ $pageOgDescription }}" />
    <meta property="og:url" content="{{ $pageOgUrl }}" />
    <meta property="og:type" content="{{ $pageOgType }}" />
    <meta property="og:image" content="{{ $pageOgImage }}" />

    <meta name="twitter:card" content="{{ $pageTwitterCard }}" />
    <meta name="twitter:title" content="{{ $pageTwitterTitle }}" />
    <meta name="twitter:description" content="{{ $pageTwitterDescription }}" />
    <meta name="twitter:image" content="{{ $pageTwitterImage }}" />

    @stack('head')
    @include('includes.favicons')

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
        'resources/js/landing.js',
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

@include('includes.metrika')
@include('includes.gtm')

<div id="app">

    @include('includes.public-navbar')

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
          
            Copyright &copy; 2022-{{ date('Y') }}
            <a target="_blank" href="https://kidscrm.online/">kidscrm.online</a>.
            Все права защищены.
            <div>
                <a target="_blank"   href="{{ route('public-offerta') }}" class="text-white text-decoration-none mx-2">Оферта</a>
                <a target="_blank"   href="{{ route('policy') }}"
                   class="text-white text-decoration-none mx-2">
                    Политика конфиденциальности
                </a>
            </div>
        </div> 
    </div>
</footer>



</body>
</html>
