<nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <!-- Add icons to the links using the .nav-icon class
             with font-awesome or any other icon font library -->

{{--        <li class="nav-header">EXAMPLES</li>--}}
        <li class="nav-item">
            <a href="/admin/dashboard" class="nav-link">
                <i class="nav-icon far fa-calendar-alt"></i>
                <p>Консоль</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="/" class="nav-link">
                <i class="nav-icon far fa-image"></i>
                <p>Установка цен</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="/" class="nav-link">
                <i class="nav-icon fas fa-columns"></i>
                <p>Детали учетной записи</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="/admin/users" class="nav-link">
                <i class="nav-icon fas fa-columns"></i>
                <p>Пользователи<span class="badge badge-info right">{{ $allUsersCount}}</span></p>
            </a>
        </li>
        <li class="nav-item">
            <a href="/admin/teams" class="nav-link">
                <i class="nav-icon fas fa-columns"></i>
                <p>Группы<span class="badge badge-info right">{{ $allTeamsCount}}</span></p>
            </a>
        </li>
    </ul>
</nav>
