<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">


    <title>Kidslink.ru - сервис учета для детских садов, тематических школ и секций</title>
    <link rel="icon" href=" {{ asset('img/favicon.png') }} " type="image/png">
    {{--JQuery--}}
    <script src="{{ asset('js/jquery/jquery-3.7.1.min.js') }}"></script>
    {{--JQuery-UI--}}
    <script src="{{ asset('js/jquery/jquery-ui.min.js') }}"></script>
    {{--Fontawesome--}}
    <script src="{{ asset('js/fontawesome/fontawesome.js') }}"></script>
    {{--Datapicker--}}
    <link rel="stylesheet" href="{{ asset('css/datapicker/datepicker.material.css') }}">
    <link rel="stylesheet" href="{{ asset('css/datapicker/datepicker.minimal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/datapicker/themes-jquery-ui.css') }}">
    <script src="{{ asset('js/datapicker/datepicker.js') }}"></script>
    {{--scripts--}}
    <script src="{{ asset('js/main.js') }}"></script>
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="{{ asset('https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback') }}">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
    <!-- Ionicons -->
    <link rel="stylesheet" href="{{ asset('https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css') }}">
    <!-- Tempusdominus Bootstrap 4 -->
    <link rel="stylesheet" href="{{ asset('plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <!-- iCheck -->
    <link rel="stylesheet" href="{{ asset('plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
    <!-- JQVMap -->
    <link rel="stylesheet" href="{{ asset('plugins/jqvmap/jqvmap.min.css') }}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="{{ asset('plugins/overlayScrollbars/css/OverlayScrollbars.min.css') }}">
    <!-- Daterange picker -->
    <link rel="stylesheet" href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}">
    <!-- summernote -->
    <link rel="stylesheet" href="{{ asset('plugins/summernote/summernote-bs4.min.css') }}">

</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Preloader -->
    {{--    <div class="preloader flex-column justify-content-center align-items-center">--}}
    {{--        <img class="animation__shake" src="{{ asset('dist/img/AdminLTELogo.png') }}" alt="AdminLTELogo" height="60"--}}
    {{--             width="60">--}}
    {{--    </div>--}}

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>

            @foreach($menuItems as $item)
                <li class="nav-item d-none d-sm-inline-block">
                    <a target="{{ $item->target_blank ? '_blank' : '_self' }}" href="{{ $item->link }}" class="nav-link">{{ $item->name }}</a>
                </li>
            @endforeach
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto social-menu mr-3">
            <!-- Navbar Search -->

{{--            <li class="nav-item">--}}
{{--                <a target="_blank" class="d-flex justify-content-center align-items-center"--}}
{{--                   href="https://vk.com/fc_istok_spb"><i class="fa-brands fa-vk" aria-hidden="true"></i></a>--}}
{{--            </li>--}}
{{--            <li class="nav-item ml-2">--}}
{{--                <a target="_blank" class="d-flex justify-content-center align-items-center"--}}
{{--                   href="https://www.youtube.com/channel/UCmOq_eBvQIQgP9sEGlpHwdg"><i class="fa-brands fa-youtube"--}}
{{--                                                                                      aria-hidden="true"></i></a>--}}
{{--            </li>--}}

            @foreach($socialItems as $social)
                <li class="nav-item {{ $loop->first ? '' : 'ml-2' }}">
                    <a target="_blank" class="d-flex justify-content-center align-items-center" href="{{ $social->link }}">
                        @if($social->name === 'vk.com' && $social->link != '')
                            <i class="fa-brands fa-vk" aria-hidden="true"></i>
                        @elseif($social->name === 'YouTube.com' && $social->link != '')
                            <i class="fa-brands fa-youtube" aria-hidden="true"></i>
                        @elseif($social->name === 'RuTube.ru' && $social->link != '')
                            <i class="fa-brands fa-rutube" aria-hidden="true"></i>
                        @elseif($social->name === 'facebook.com' && $social->link != '')
                            <i class="fa-brands fa-facebook" aria-hidden="true"></i>
                        @elseif($social->name === 'Instagram.com' && $social->link != '')
                            <i class="fa-brands fa-instagram" aria-hidden="true"></i>
                        @elseif($social->name === 'Twitter.com' && $social->link != '')
                            <i class="fa-brands fa-twitter" aria-hidden="true"></i>
                        @elseif($social->name === 'LinkedIn.com' && $social->link != '')
                            <i class="fa-brands fa-linkedin" aria-hidden="true"></i>
                        @elseif($social->name === 'Telegram.org' && $social->link != '')
                            <i class="fa-brands fa-telegram" aria-hidden="true"></i>
                        @elseif($social->name === 'Pinterest.com' && $social->link != '')
                            <i class="fa-brands fa-pinterest" aria-hidden="true"></i>
                        @elseif($social->name === 'TikTok.com' && $social->link != '')
                            <i class="fa-brands fa-tiktok" aria-hidden="true"></i>
                        @elseif($social->name === 'Reddit.com' && $social->link != '')
                            <i class="fa-brands fa-reddit" aria-hidden="true"></i>
                        @elseif($social->name === 'Snapchat.com' && $social->link != '')
                            <i class="fa-brands fa-snapchat" aria-hidden="true"></i>
                        @elseif($social->name === 'WhatsApp.com' && $social->link != '')
                            <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                        @elseif($social->name === 'Discord.com' && $social->link != '')
                            <i class="fa-brands fa-discord" aria-hidden="true"></i>
                        @elseif($social->name === 'Tumblr.com' && $social->link != '')
                            <i class="fa-brands fa-tumblr" aria-hidden="true"></i>
                        @elseif($social->name === 'Dribbble.com' && $social->link != '')
                            <i class="fa-brands fa-dribbble" aria-hidden="true"></i>
                        @elseif($social->name === 'GitHub.com' && $social->link != '')
                            <i class="fa-brands fa-github" aria-hidden="true"></i>
                        @elseif($social->name === 'Vimeo.com' && $social->link != '')
                            <i class="fa-brands fa-vimeo" aria-hidden="true"></i>
                        @elseif($social->name === 'Slack.com' && $social->link != '')
                            <i class="fa-brands fa-slack" aria-hidden="true"></i>
                        @elseif($social->name === 'Dropbox.com' && $social->link != '')
                            <i class="fa-brands fa-dropbox" aria-hidden="true"></i>
                        @endif
                    </a>

                </li>
            @endforeach

            {{--<li class="nav-item d-flex align-items-center">--}}
                {{--<form method="POST" action="{{ route('logout') }}" class="d-flex align-items-center mb-0">--}}
                    {{--@csrf--}}
                    {{--<button type="submit" class="btn btn-primary logout">Выйти</button>--}}
                {{--</form>--}}
            {{--</li>--}}



            <li class="nav-item d-flex align-items-center">
                <button
                        type="button"
                        class="btn btn-primary logout"
                        data-bs-toggle="modal"
                        data-bs-target="#logoutModal">
                    Выйти
                </button>
            </li>

            <script>
                document.addEventListener('show.bs.modal', function (event) {
                    const modal = event.target; // Получаем текущее модальное окно
                    const wrapper = document.querySelector('.wrapper'); // Находим элемент wrapper

                    if (wrapper && modal) {
                        wrapper.prepend(modal); // Перемещаем модальное окно в начало wrapper
                    }
                });
            </script>


            {{--<!-- Модальное окно -->--}}
            {{--<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">--}}
                {{--<div class="modal-dialog">--}}
                    {{--<div class="modal-content">--}}
                        {{--<div class="modal-header">--}}
                            {{--<h5 class="modal-title" id="logoutModalLabel">Подтверждение выхода</h5>--}}
                            {{--<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>--}}
                        {{--</div>--}}
                        {{--<div class="modal-body">--}}
                            {{--Вы уверены, что хотите выйти?--}}
                        {{--</div>--}}
                        {{--<div class="modal-footer">--}}
                            {{--<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>--}}
                            {{--<form method="POST" action="{{ route('logout') }}" class="d-inline">--}}
                                {{--@csrf--}}
                                {{--<button type="submit" class="btn btn-danger">Выйти</button>--}}
                            {{--</form>--}}
                        {{--</div>--}}
                    {{--</div>--}}
                {{--</div>--}}
            {{--</div>--}}


            <!-- Модальное окно настройки меню -->
            @include('includes.confirmLogout')

            {{--            <li class="nav-item">--}}
            {{--                <a class="nav-link" data-widget="navbar-search" href="#" role="button">--}}
            {{--                    <i class="fas fa-search"></i>--}}
            {{--                </a>--}}
            {{--                <div class="navbar-search-block">--}}
            {{--                    <form class="form-inline">--}}
            {{--                        <div class="input-group input-group-sm">--}}
            {{--                            <input class="form-control form-control-navbar" type="search" placeholder="Search"--}}
            {{--                                   aria-label="Search">--}}
            {{--                            <div class="input-group-append">--}}
            {{--                                <button class="btn btn-navbar" type="submit">--}}
            {{--                                    <i class="fas fa-search"></i>--}}
            {{--                                </button>--}}
            {{--                                <button class="btn btn-navbar" type="button" data-widget="navbar-search">--}}
            {{--                                    <i class="fas fa-times"></i>--}}
            {{--                                </button>--}}
            {{--                            </div>--}}
            {{--                        </div>--}}
            {{--                    </form>--}}
            {{--                </div>--}}
            {{--            </li>--}}

            <!-- Messages Dropdown Menu -->
            {{--            <li class="nav-item dropdown">--}}
            {{--                <a class="nav-link" data-toggle="dropdown" href="#">--}}
            {{--                    <i class="far fa-comments"></i>--}}
            {{--                    <span class="badge badge-danger navbar-badge">3</span>--}}
            {{--                </a>--}}
            {{--                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">--}}
            {{--                    <a href="#" class="dropdown-item">--}}
            {{--                        <!-- Message Start -->--}}
            {{--                        <div class="media">--}}
            {{--                            <img src="{{ asset('dist/img/user1-128x128.jpg') }}" alt="User Avatar"--}}
            {{--                                 class="img-size-50 mr-3 img-circle">--}}
            {{--                            <div class="media-body">--}}
            {{--                                <h3 class="dropdown-item-title">--}}
            {{--                                    Brad Diesel--}}
            {{--                                    <span class="float-right text-sm text-danger"><i class="fas fa-star"></i></span>--}}
            {{--                                </h3>--}}
            {{--                                <p class="text-sm">Call me whenever you can...</p>--}}
            {{--                                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>--}}
            {{--                            </div>--}}
            {{--                        </div>--}}
            {{--                        <!-- Message End -->--}}
            {{--                    </a>--}}
            {{--                    <div class="dropdown-divider"></div>--}}
            {{--                    <a href="#" class="dropdown-item">--}}
            {{--                        <!-- Message Start -->--}}
            {{--                        <div class="media">--}}
            {{--                            <img src="{{ asset('dist/img/user8-128x128.jpg') }}" alt="User Avatar"--}}
            {{--                                 class="img-size-50 img-circle mr-3">--}}
            {{--                            <div class="media-body">--}}
            {{--                                <h3 class="dropdown-item-title">--}}
            {{--                                    John Pierce--}}
            {{--                                    <span class="float-right text-sm text-muted"><i class="fas fa-star"></i></span>--}}
            {{--                                </h3>--}}
            {{--                                <p class="text-sm">I got your message bro</p>--}}
            {{--                                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>--}}
            {{--                            </div>--}}
            {{--                        </div>--}}
            {{--                        <!-- Message End -->--}}
            {{--                    </a>--}}
            {{--                    <div class="dropdown-divider"></div>--}}
            {{--                    <a href="#" class="dropdown-item">--}}
            {{--                        <!-- Message Start -->--}}
            {{--                        <div class="media">--}}
            {{--                            <img src="{{ asset('dist/img/user3-128x128.jpg') }}" alt="User Avatar"--}}
            {{--                                 class="img-size-50 img-circle mr-3">--}}
            {{--                            <div class="media-body">--}}
            {{--                                <h3 class="dropdown-item-title">--}}
            {{--                                    Nora Silvester--}}
            {{--                                    <span class="float-right text-sm text-warning"><i class="fas fa-star"></i></span>--}}
            {{--                                </h3>--}}
            {{--                                <p class="text-sm">The subject goes here</p>--}}
            {{--                                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>--}}
            {{--                            </div>--}}
            {{--                        </div>--}}
            {{--                        <!-- Message End -->--}}
            {{--                    </a>--}}
            {{--                    <div class="dropdown-divider"></div>--}}
            {{--                    <a href="#" class="dropdown-item dropdown-footer">See All Messages</a>--}}
            {{--                </div>--}}
            {{--            </li>--}}
            <!-- Notifications Dropdown Menu -->
            {{--            <li class="nav-item dropdown">--}}
            {{--                <a class="nav-link" data-toggle="dropdown" href="#">--}}
            {{--                    <i class="far fa-bell"></i>--}}
            {{--                    <span class="badge badge-warning navbar-badge">15</span>--}}
            {{--                </a>--}}
            {{--                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">--}}
            {{--                    <span class="dropdown-item dropdown-header">15 Notifications</span>--}}
            {{--                    <div class="dropdown-divider"></div>--}}
            {{--                    <a href="#" class="dropdown-item">--}}
            {{--                        <i class="fas fa-envelope mr-2"></i> 4 new messages--}}
            {{--                        <span class="float-right text-muted text-sm">3 mins</span>--}}
            {{--                    </a>--}}
            {{--                    <div class="dropdown-divider"></div>--}}
            {{--                    <a href="#" class="dropdown-item">--}}
            {{--                        <i class="fas fa-users mr-2"></i> 8 friend requests--}}
            {{--                        <span class="float-right text-muted text-sm">12 hours</span>--}}
            {{--                    </a>--}}
            {{--                    <div class="dropdown-divider"></div>--}}
            {{--                    <a href="#" class="dropdown-item">--}}
            {{--                        <i class="fas fa-file mr-2"></i> 3 new reports--}}
            {{--                        <span class="float-right text-muted text-sm">2 days</span>--}}
            {{--                    </a>--}}
            {{--                    <div class="dropdown-divider"></div>--}}
            {{--                    <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>--}}
            {{--                </div>--}}
            {{--            </li>--}}
            {{--            <li class="nav-item">--}}
            {{--                <a class="nav-link" data-widget="fullscreen" href="#" role="button">--}}
            {{--                    <i class="fas fa-expand-arrows-alt"></i>--}}
            {{--                </a>--}}
            {{--            </li>--}}
            {{--            <li class="nav-item">--}}
            {{--                <a class="nav-link" data-widget="control-sidebar" data-controlsidebar-slide="true" href="#"--}}
            {{--                   role="button">--}}
            {{--                    <i class="fas fa-th-large"></i>--}}
            {{--                </a>--}}
            {{--            </li>--}}
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="/" class="brand-link ml-3">
            {{--            <img src="{{ asset('dist/img/AdminLTELogo.png') }}" alt="Kidslink Logo"--}}
            {{--                 class="brand-image img-circle elevation-3" style="opacity: .8">--}}
            <span class="brand-text font-weight-light">Kidslink.ru</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel (optional) -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                {{--                <div class="image">--}}
                {{--                    <img src="{{ asset('dist/img/user2-160x160.jpg') }}" class="img-circle elevation-2"--}}
                {{--                         alt="User Image">--}}
                {{--                </div>--}}
                <div class="info text-light">
                    <a href="#" class="d-block"></a>
                    <h6> Имя: {{auth()->user()->name}}</h6>
                    <h6> Id: {{auth()->user()->id}}</h6>
                    {{--                    <h6> Почта: {{auth()->user()->email}}</h6>--}}
                    <h6> Роль: {{auth()->user()->role}}</h6>

                    {{--<h6> Баланс:  <a href="/partner-payment/recharge"><span class="badge badge-success">100 руб </span></a> </h6>--}}

                </div>
            </div>


            <!-- SidebarSearch Form -->
            {{--            <div class="form-inline">--}}
            {{--                <div class="input-group" data-widget="sidebar-search">--}}
            {{--                    <input class="form-control form-control-sidebar" type="search" placeholder="Search"--}}
            {{--                           aria-label="Search">--}}
            {{--                    <div class="input-group-append">--}}
            {{--                        <button class="btn btn-sidebar">--}}
            {{--                            <i class="fas fa-search fa-fw"></i>--}}
            {{--                        </button>--}}
            {{--                    </div>--}}
            {{--                </div>--}}
            {{--            </div>--}}

            <!-- Sidebar Menu -->
            @include('includes.sidebar')
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        {{--        <div class="content-header">--}}
        {{--            <div class="container-fluid">--}}
        {{--                <div class="row mb-2">--}}
        {{--                    <div class="col-sm-6">--}}
        {{--                        <h1 class="m-0">Dashboard</h1>--}}
        {{--                    </div>--}}
        {{--                    <!-- /.col -->--}}

        {{--                    <div class="col-sm-6">--}}
        {{--                        <ol class="breadcrumb float-sm-right">--}}
        {{--                            <li class="breadcrumb-item"><a href="#">Home</a></li>--}}
        {{--                            <li class="breadcrumb-item active">Dashboard v1</li>--}}
        {{--                        </ol>--}}
        {{--                    </div><!-- /.col -->--}}

        {{--                </div><!-- /.row -->--}}
        {{--            </div><!-- /.container-fluid -->--}}
        {{--        </div>--}}
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">

                @yield('content')

            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
    <footer class="main-footer">

        <div> Copyright &copy; 2023-2024 <a target="_blank" href="https://kidslink.ru/">Kidslink.ru</a>.
            Все права защищены.
        </div>
        <div class="float-right d-none d-sm-inline-block">
            {{--            <b>Version</b> 3.2.0--}}
        </div>
    </footer>

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
        <!-- Control sidebar content goes here -->
    </aside>
    <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- jQuery -->
{{--<script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>--}}
{{--<!-- jQuery UI 1.11.4 -->--}}
{{--<script src="{{ asset('plugins/jquery-ui/jquery-ui.min.js') }}"></script>--}}
<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->


{{--JQuery--}}
<script src="{{ asset('js/jquery/jquery-3.7.1.min.js') }}"></script>

{{--JQuery-UI--}}
<script src="{{ asset('js/jquery/jquery-ui.min.js') }}"></script>

<script>
    $.widget.bridge('uibutton', $.ui.button)
</script>
<!-- Bootstrap 4 -->
{{--<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>--}}
<!-- ChartJS -->
{{--<script src="{{ asset('plugins/chart.js/Chart.min.js') }}"></script>--}}
<!-- Sparkline -->
{{--<script src="{{ asset('plugins/sparklines/sparkline.js') }}"></script>--}}
<!-- JQVMap -->
{{--<script src="{{ asset('plugins/jqvmap/jquery.vmap.min.js') }}"></script>--}}
{{--<script src="{{ asset('plugins/jqvmap/maps/jquery.vmap.usa.js') }}"></script>--}}
<!-- jQuery Knob Chart -->
{{--<script src="{{ asset('plugins/jquery-knob/jquery.knob.min.js') }}"></script>--}}
<!-- daterangepicker -->
<script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
<script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
<!-- Tempusdominus Bootstrap 4 -->
<script src="{{ asset('plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
<!-- Summernote -->
<script src="{{ asset('plugins/summernote/summernote-bs4.min.js') }}"></script>
<!-- overlayScrollbars -->
{{--<script src="{{ asset('plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js') }}"></script>--}}
<!-- AdminLTE App -->
<script src="{{ asset('dist/js/adminlte.js') }}"></script>
<!-- AdminLTE for demo purposes -->
{{--<script src="{{ asset('dist/js/demo.js') }}"></script>--}}
<!-- AdminLTE dashboard demo (This is only for demo purposes) -->
{{--<script src="{{ asset('dist/js/pages/dashboard.js') }}"></script>--}}
@yield('scripts')

{{--Cropie--}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css">
<link rel="stylesheet" href="{{ asset('css/croppie.css') }}">
<script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>

{{--Select2--}}
<link rel="stylesheet" href="{{ asset('css/select2/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/select2/select2-bootstrap-5-theme.min.css') }}">
<script src="{{ asset('js/select2/select2.full.min.js') }}"></script>

{{--Bootstrap--}}
@vite(['resources/js/app.js', 'resources/sass/app.scss'])

<link rel="stylesheet" href="{{ asset('css/fcistok.css') }}">
<link rel="stylesheet" href="{{ asset('css/style.css') }}">
<link rel="stylesheet" href="{{ asset('css/media-style.css') }}">
<link rel="stylesheet" href="{{ asset('css/calendar.css') }}">

<!-- Включение DataTables CSS -->
<link href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css" rel="stylesheet">

<!-- Включение DataTables JS -->
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>

</body>
</html>
