<nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <!-- Add icons to the links using the .nav-icon class
             with font-awesome or any other icon font library -->

{{--        <li class="nav-header">EXAMPLES</li>--}}
        <li class="nav-item">
            <a href="/" class="nav-link">
                <i class="nav-icon far fa-calendar-alt"></i>
                <p>Консоль</p>
            </a>
        </li>
{{--        @can('view', auth()->user())--}}
{{--            <li class="nav-item">--}}
{{--                <a href="/admin/payments" class="nav-link">--}}
{{--                    <i class="nav-icon far fa-image"></i>--}}
{{--                    <p>Платежи</p>--}}
{{--                </a>--}}
{{--            </li>--}}
{{--        @endcan--}}
        @can('view', auth()->user())
            <li class="nav-item">
                <a href="/admin/reports/payments" class="nav-link">
                    <i class="nav-icon far fa-image"></i>
                    <p>Отчеты</p>
                </a>
            </li>
        @endcan
        @can('view', auth()->user())
        <li class="nav-item">
            <a href="/admin/setting-prices?current-month" class="nav-link">
                <i class="nav-icon far fa-image"></i>
                <p>Установка цен</p>
            </a>
        </li>
        @endcan

        @can('view', auth()->user())
        <li class="nav-item">
            <a href="/admin/users" class="nav-link">
                <i class="nav-icon fas fa-columns"></i>
                <p>Пользователи<span class="badge badge-info right">{{ $allUsersCount}}</span></p>
            </a>
        </li>
        @endcan

        @can('view', auth()->user())
        <li class="nav-item">
            <a href="/admin/teams" class="nav-link">
                <i class="nav-icon fas fa-columns"></i>
                <p>Группы<span class="badge badge-info right">{{ $allTeamsCount}}</span></p>
            </a>
        </li>
        @endcan



        @can('view', auth()->user())
            <li class="nav-item">
                <a href="/admin/settings" class="nav-link">
                    <i class="nav-icon fas fa-columns"></i>
                    <p>Настройки<span class="badge badge-info right"></span></p>
                </a>
            </li>
        @endcan



        <li class="nav-item">
            <a href="/account-settings/users/{{ Auth::user()->id }}/edit" class="nav-link">
                <i class="nav-icon fas fa-columns"></i>
                <p>Учетная запись</p>
            </a>
        </li>


    </ul>
</nav>
