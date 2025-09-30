@php
    $user = auth()->user();
@endphp

<nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

        {{--Консоль--}}
        @can('dashboard-view')
        <li class="nav-item">
            <a href="/cabinet" class="nav-link">
                {{--<i class="nav-icon far fa-calendar-alt"></i>--}}
                <i class="nav-icon  fa-solid fa-house"></i>
                <p>Консоль</p>
            </a>

        </li>
        @endcan

        {{--Отчеты--}}
{{--        @can('reports')--}}
        @can('reports-view')
        <li class="nav-item">
                <a href="/admin/reports/payments" class="nav-link">
                    <i class="nav-icon fa-solid fa-folder"></i>
                    <p>Отчеты</p>
                </a>
            </li>
        @endcan

        {{--Платежи юзера--}}
        @can('myPayments-view')
            <li class="nav-item">
                <a href="/reports/payments" class="nav-link">
                    <i class="nav-icon fa-solid fa-receipt"></i>
                    <p>Мои платежи</p>
                </a>
            </li>
        @endcan

        {{--Группа юзера--}}
        @can('myGroup-view')
            <li class="nav-item">
                <a href="/my-group" class="nav-link">
                    <i class="nav-icon fa-solid fa-layer-group"></i>
                    <p>Моя группа</p>
                </a>
            </li>
        @endcan


        {{--Установка цен--}}
        @can('setPrices-view')
        <li class="nav-item">
                <a href="/admin/setting-prices?current-month" class="nav-link">
                    <i class="nav-icon fa-solid fa-receipt"></i>
                    <p>Установка цен</p>
                </a>
            </li>
        @endcan

        {{--Журнал расписания--}}
        @can('schedule-view')
            <li class="nav-item">
                <a href="/schedule" class="nav-link">
{{--                    <i class="nav-icon fa-solid fa-receipt"></i>--}}
                    <i class="nav-icon fa-solid fa-calendar-days"></i>
                    <p>Журнал расписания</p>
                </a>
            </li>
        @endcan

        {{--Пользователи--}}
        @can('users-view')
            <li class="nav-item">
                <a href="/admin/users" class="nav-link">
                    <i class="nav-icon fa-solid fa-users"></i>
                    <p>Пользователи<span class="badge badge-info right">{{ $allUsersCount}}</span></p>
                </a>
            </li>
        @endcan

        {{--Группы--}}
        @can('groups-view')
            <li class="nav-item">
                <a href="/admin/teams" class="nav-link">
                    <i class="nav-icon fa-solid fa-layer-group"></i>
                    <p>Группы<span class="badge badge-info right">{{ $allTeamsCount}}</span></p>
                </a>
            </li>
        @endcan

        {{--Договоры--}}
        @can('contracts-view')
            <li class="nav-item">
                <a href="/contracts" class="nav-link">
                    <i class="nav-icon fa-solid fa-layer-group"></i>
                    <p>Договоры</p>
                </a>
            </li>
        @endcan

        {{--Настройки--}}
        @can('settings-view')
        <li class="nav-item">
                <a href="/admin/settings" class="nav-link">
                    <i class="nav-icon fas fa-gear"></i>
                    <p>Настройки<span class="badge badge-info right"></span></p>
                </a>
            </li>
        @endcan


        {{--Учетная запись--}}
        @can('account-user-view')
        <li class="nav-item">
            <a href="/account-settings/users/{{ Auth::user()->id }}/edit" class="nav-link">
                <i class="nav-icon fa-solid fa-user"></i>
                <p>Учетная запись</p>
            </a>
        </li>
        @endcan

        {{--Сообщения (Чат)--}}
        @can('messages-view')
            <li class="nav-item">
                <a href="/chat" class="nav-link">
                    <i class="nav-icon fa-solid fa-message"></i>
                              <p>Сообщения</p>
                </a>
            </li>
        @endcan



        <hr class="sidebar-separator">



        {{--Оплата сервиса--}}
        @can('servicePayments-view')
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

            {{--заявки с сайта--}}
            @can('leads-view')
                <hr class="sidebar-separator">
                <li class="nav-item">
                    <a href="/leads" class="nav-link">
                        <i class="nav-icon fa-solid fa-credit-card"></i>

                        <p>Лиды<span class="badge badge-info right"></span></p>
                    </a>
                </li>
            @endcan

            {{--Партнеры--}}
            @can('partner-view')
                <li class="nav-item">
                    <a href="/admin/partners" class="nav-link">
                        <i class="nav-icon fa-solid fa-user-tie"></i>
                        <p>Партнеры
                        </p>
                    </a>
                </li>
            <li class="nav-item">
                <a href="/admin/tinkoff/partners/1" class="nav-link">
                    <i class="nav-icon fa-solid fa-user-tie"></i>
                    <p>Партнеры (новое)
                    </p>
                </a>
            </li>
            @endcan


    </ul>
</nav>
