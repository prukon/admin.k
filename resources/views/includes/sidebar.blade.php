@php
    $user = auth()->user();
@endphp

<nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

        {{--Консоль--}}
        <li class="nav-item">
            <a href="/cabinet" class="nav-link">
                {{--<i class="nav-icon far fa-calendar-alt"></i>--}}
                <i class="nav-icon  fa-solid fa-house"></i>
                <p>Консоль</p>
            </a>

        </li>


        {{--<li>--}}
            {{--<form method="POST" action="{{ route('dashboard') }}">@csrf--}}
                {{--<button type="submit" class="dropdown-item">В личный кабинет</button>--}}
            {{--</form>--}}
        {{--</li>--}}

        {{--Отчеты--}}
        @can('reports')
        <li class="nav-item">
                <a href="/admin/reports/payments" class="nav-link">
                    <i class="nav-icon fa-solid fa-folder"></i>
                    <p>Отчеты</p>
                </a>
            </li>
        @endcan

        {{--Платежи юзера--}}
        @can('my-payments')
            <li class="nav-item">
                <a href="/reports/payments" class="nav-link">
                    <i class="nav-icon fa-solid fa-receipt"></i>
                    <p>Платежи</p>
                </a>
            </li>
        @endcan

        {{--Установка цен--}}
        @can('set-prices')
        <li class="nav-item">
                <a href="/admin/setting-prices?current-month" class="nav-link">
                    <i class="nav-icon fa-solid fa-receipt"></i>
                    <p>Установка цен</p>
                </a>
            </li>
        @endcan

        {{--Журнал расписания--}}
        @can('schedule-journal')
            <li class="nav-item">
                <a href="/schedule" class="nav-link">
{{--                    <i class="nav-icon fa-solid fa-receipt"></i>--}}
                    <i class="nav-icon fa-solid fa-calendar-days"></i>
                    <p>Журнал расписания</p>
                </a>
            </li>
        @endcan

        {{--Пользователи--}}
        @can('manage-users')
            <li class="nav-item">
                <a href="/admin/users" class="nav-link">
                    <i class="nav-icon fa-solid fa-users"></i>
                    <p>Пользователи<span class="badge badge-info right">{{ $allUsersCount}}</span></p>
                </a>
            </li>
        @endcan

        {{--Группы--}}
        @can('manage-groups')
            <li class="nav-item">
                <a href="/admin/teams" class="nav-link">
                    <i class="nav-icon fa-solid fa-layer-group"></i>
                    <p>Группы<span class="badge badge-info right">{{ $allTeamsCount}}</span></p>
                </a>
            </li>
        @endcan

        {{--Настройки--}}
        @can('general-settings')
        <li class="nav-item">
                <a href="/admin/settings" class="nav-link">
                    <i class="nav-icon fas fa-gear"></i>
                    <p>Настройки<span class="badge badge-info right"></span></p>
                </a>
            </li>
        @endcan

        {{--Учетная запись--}}
        <li class="nav-item">
            <a href="/account-settings/users/{{ Auth::user()->id }}/edit" class="nav-link">
                <i class="nav-icon fa-solid fa-user"></i>
                <p>Учетная запись</p>
            </a>


        </li>
            </li>
        <hr class="sidebar-separator">


        {{--Оплата сервиса--}}
        @can('service-payment')
        <li class="nav-item">
                <a href="/partner-payment/recharge" class="nav-link">
                    <i class="nav-icon fa-solid fa-credit-card"></i>

                    <p>Оплата сервиса<span class="badge badge-info right"></span></p>
                </a>
            </li>
        @endcan

        {{--О сервисе--}}
            <li class="nav-item">
                <a href="/about" class="nav-link">
                    <i class="nav-icon fas fa-solid fa-briefcase"></i>

                    <p>О сервисе<span class="badge badge-info right"></span></p>
                </a>
            </li>
    </ul>
</nav>
