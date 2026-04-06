@php
    $telegramContactUrl = 'https://t.me/prukon?' . http_build_query([
        'utm_source' => 'kidscrm.online',
        'utm_medium' => 'mobile_nav',
        'utm_campaign' => 'telegram_contact',
    ]);
    $navUserLabel = auth()->check()
        ? (auth()->user()->full_name ?? auth()->user()->name)
        : '';
    $homeSectionHref = static function (string $fragment): string {
        if (request()->routeIs('blog.*')) {
            return url('/') . $fragment;
        }
        if (request()->is('/')) {
            return $fragment;
        }

        return url('/') . $fragment;
    };
@endphp

<header class="bg-white shadow-sm">
    <nav class="navbar navbar-expand-md public-site-nav" aria-label="Основная навигация">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">
                <img src="{{ asset('img/logo.png') }}" alt="kidscrm.online" height="80">
            </a>

            <button class="navbar-toggler d-md-none public-nav-drawer-toggle" type="button"
                    aria-controls="publicNavDrawer"
                    aria-expanded="false"
                    aria-label="Открыть меню">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="navbar-collapse flex-grow-1 align-items-center d-none d-md-flex" id="mainNav">
                <ul class="navbar-nav mx-auto mb-2 mb-md-0">
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="{{ $homeSectionHref('#how-it-works') }}">Как это работает</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="{{ $homeSectionHref('#features') }}">Функционал</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="{{ $homeSectionHref('#advantages') }}">Преимущества</a>
                    </li>

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
                                   href="/crm-dlya-futbolnoy-sekcii">Футбольных секций</a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ request()->is('crm-dlya-tancevalnoy-studii') ? 'active' : '' }}"
                                   href="/crm-dlya-tancevalnoy-studii">Танцевальных студий</a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ request()->is('crm-dlya-shkoly-edinoborstv') ? 'active' : '' }}"
                                   href="/crm-dlya-shkoly-edinoborstv">Школ единоборств</a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ request()->is('crm-dlya-detskogo-razvivayushchego-centra') ? 'active' : '' }}"
                                   href="/crm-dlya-detskogo-razvivayushchego-centra">Детских развивающих центров</a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ request()->is('crm-dlya-shkol-gimnastiki-i-akrobatiki') ? 'active' : '' }}"
                                   href="/crm-dlya-shkol-gimnastiki-i-akrobatiki">Школ гимнастики и акробатики</a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ request()->is('crm-dlya-detskih-yazykovyh-shkol') ? 'active' : '' }}"
                                   href="/crm-dlya-detskih-yazykovyh-shkol">Детских языковых школ</a>
                            </li>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link text-dark {{ request()->routeIs('blog.*') ? 'fw-bold' : '' }}" href="{{ route('blog.index') }}">Блог</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="{{ $homeSectionHref('#pricing') }}">Цены</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="{{ $homeSectionHref('#contacts') }}">Контакты</a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto align-items-center flex-row gap-2">
                    @guest
                        @if (Route::has('login'))
                            <li class="nav-item">
                                <a class="btn btn-primary" href="{{ route('login') }}">Войти</a>
                            </li>
                            @if (Route::has('partner.register'))
                                <li class="nav-item">
                                    <a class="btn btn-outline-primary" href="{{ route('partner.register') }}">Регистрация</a>
                                </li>
                            @endif
                        @endif
                    @else
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">
                                {{ $navUserLabel }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <form method="POST" action="{{ route('dashboard') }}">@csrf
                                        <button type="submit"
                                                class="dropdown-item d-flex align-items-center hover-underline">
                                            <img src="{{ asset('img/landing/icons/login/home.png') }}"
                                                 alt=""
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
                                                 alt=""
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

    <div class="public-nav-drawer d-md-none" id="publicNavDrawer" aria-hidden="true">
        <div class="public-nav-drawer__backdrop" data-public-nav-close tabindex="-1" aria-hidden="true"></div>
        <div class="public-nav-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="publicNavDrawerTitle">
            <div class="public-nav-drawer__head">
                <span id="publicNavDrawerTitle" class="public-nav-drawer__title">Меню</span>
                <button type="button" class="btn-close public-nav-drawer__close" data-public-nav-close aria-label="Закрыть меню"></button>
            </div>

            <div class="public-nav-drawer__body">
                <ul class="public-nav-drawer__list list-unstyled mb-0">
                    <li>
                        <a class="public-nav-drawer__link" href="{{ $homeSectionHref('#how-it-works') }}">Как это работает</a>
                    </li>
                    <li>
                        <a class="public-nav-drawer__link" href="{{ $homeSectionHref('#features') }}">Функционал</a>
                    </li>
                    <li>
                        <a class="public-nav-drawer__link" href="{{ $homeSectionHref('#advantages') }}">Преимущества</a>
                    </li>

                    <li class="public-nav-drawer__accordion">
                        <button type="button"
                                class="public-nav-drawer__accordion-toggle"
                                id="publicNavSolutionsBtn"
                                aria-expanded="false"
                                aria-controls="publicNavSolutionsPanel">
                            <span>Решения для</span>
                            <svg class="public-nav-drawer__chevron" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                            </svg>
                        </button>
                        <div class="public-nav-drawer__accordion-panel" id="publicNavSolutionsPanel" role="region" aria-labelledby="publicNavSolutionsBtn" aria-hidden="true">
                            <ul class="public-nav-drawer__sublist list-unstyled">
                                <li><a class="public-nav-drawer__sublink" href="/crm-dlya-futbolnoy-sekcii">Футбольных секций</a></li>
                                <li><a class="public-nav-drawer__sublink" href="/crm-dlya-tancevalnoy-studii">Танцевальных студий</a></li>
                                <li><a class="public-nav-drawer__sublink" href="/crm-dlya-shkoly-edinoborstv">Школ единоборств</a></li>
                                <li><a class="public-nav-drawer__sublink" href="/crm-dlya-detskogo-razvivayushchego-centra">Детских развивающих центров</a></li>
                                <li><a class="public-nav-drawer__sublink" href="/crm-dlya-shkol-gimnastiki-i-akrobatiki">Школ гимнастики и акробатики</a></li>
                                <li><a class="public-nav-drawer__sublink" href="/crm-dlya-detskih-yazykovyh-shkol">Детских языковых школ</a></li>
                            </ul>
                        </div>
                    </li>

                    <li>
                        <a class="public-nav-drawer__link {{ request()->routeIs('blog.*') ? 'fw-bold' : '' }}" href="{{ route('blog.index') }}">Блог</a>
                    </li>
                    <li>
                        <a class="public-nav-drawer__link" href="{{ $homeSectionHref('#pricing') }}">Цены</a>
                    </li>
                    <li>
                        <a class="public-nav-drawer__link" href="{{ $homeSectionHref('#contacts') }}">Контакты</a>
                    </li>
                </ul>

                <a class="public-nav-drawer__telegram" href="{{ $telegramContactUrl }}" target="_blank" rel="noopener noreferrer"
                   aria-label="Связаться в Telegram, @prukon">
                    <span class="public-nav-drawer__telegram-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" focusable="false">
                            <path fill="currentColor" d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                        </svg>
                    </span>
                    <span class="public-nav-drawer__telegram-text">
                        <span class="public-nav-drawer__telegram-label">Связаться в Telegram</span>
                        <span class="public-nav-drawer__telegram-hint">@prukon</span>
                    </span>
                </a>

                <div class="public-nav-drawer__auth">
                    @guest
                        @if (Route::has('login'))
                            <a class="btn btn-primary w-100 mb-2" href="{{ route('login') }}">Войти</a>
                            @if (Route::has('partner.register'))
                                <a class="btn btn-outline-primary w-100" href="{{ route('partner.register') }}">Регистрация</a>
                            @endif
                        @endif
                    @else
                        <div class="public-nav-drawer__user text-muted small mb-2">{{ $navUserLabel }}</div>
                        <form method="POST" action="{{ route('dashboard') }}" class="mb-2">@csrf
                            <button type="submit" class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2">
                                <img src="{{ asset('img/landing/icons/login/home.png') }}" alt="" width="22" height="22" style="object-fit:contain;">
                                В личный кабинет
                            </button>
                        </form>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button type="submit" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2">
                                <img src="{{ asset('img/landing/icons/login/exit.png') }}" alt="" width="22" height="22" style="object-fit:contain;">
                                Выйти
                            </button>
                        </form>
                    @endguest
                </div>
            </div>
        </div>
    </div>
</header>
