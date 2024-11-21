@php
    $user = auth()->user();
@endphp

<nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

        {{--Консоль--}}
        <li class="nav-item">
            <a href="/" class="nav-link">
                {{--<i class="nav-icon far fa-calendar-alt"></i>--}}
                <i class="nav-icon  fa-solid fa-house"></i>
                <p>Консоль</p>
            </a>
        </li>

        {{--Отчеты--}}
        @if($user && ($user->role == 'admin' || $user->role == 'superadmin'))
            <li class="nav-item">
                <a href="/admin/reports/payments" class="nav-link">
                    <i class="nav-icon fa-solid fa-folder"></i>
                    <p>Отчеты</p>
                </a>
            </li>
        @endif

        {{--Установка цен--}}
        @if($user && ($user->role == 'admin' || $user->role == 'superadmin'))
            <li class="nav-item">
                <a href="/admin/setting-prices?current-month" class="nav-link">
                    <i class="nav-icon fa-solid fa-credit-card"></i>
                    <p>Установка цен</p>
                </a>
            </li>
        @endif

        {{--Пользователи--}}
        @if($user && ($user->role == 'admin' || $user->role == 'superadmin'))
            <li class="nav-item">
                <a href="/admin/users" class="nav-link">
                    <i class="nav-icon fa-solid fa-users"></i>
                    <p>Пользователи<span class="badge badge-info right">{{ $allUsersCount}}</span></p>
                </a>
            </li>
        @endif

        {{--Группы--}}
        @if($user && ($user->role == 'admin' || $user->role == 'superadmin'))
            <li class="nav-item">
                <a href="/admin/teams" class="nav-link">
                    <i class="nav-icon fa-solid fa-layer-group"></i>
                    <p>Группы<span class="badge badge-info right">{{ $allTeamsCount}}</span></p>
                </a>
            </li>
        @endif

        {{--Настройки--}}
        @if($user && ($user->role == 'admin' || $user->role == 'superadmin'))
            <li class="nav-item">
                <a href="/admin/settings" class="nav-link">
                    <i class="nav-icon fas fa-gear"></i>
                    <p>Настройки<span class="badge badge-info right"></span></p>
                </a>
            </li>
        @endif

        {{--Учетная запись--}}
        <li class="nav-item">
            <a href="/account-settings/users/{{ Auth::user()->id }}/edit" class="nav-link">
                <i class="nav-icon fa-solid fa-user"></i>
                <p>Учетная запись</p>
            </a>
        </li>

        <hr class="sidebar-separator">

        {{--Организация--}}
        @if($user && ($user->role == 'admin' || $user->role == 'superadmin'))
            <li class="nav-item">
                <a href="/about" class="nav-link">
                    <i class="nav-icon fas fa-solid fa-briefcase"></i>

                    <p>О сервисе<span class="badge badge-info right"></span></p>
                </a>
            </li>
        @endif

    </ul>
</nav>
