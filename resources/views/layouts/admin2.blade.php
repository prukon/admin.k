<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">


    <title>кружок.online - сервис учета для детских садов, тематических школ и секций</title>
    <link rel="icon" href=" {{ asset('img/favicon.png') }} " type="image/png">

    {{--JQuery--}}
    <script src="{{ asset('js/jquery/jquery-3.7.1.min.js') }}"></script>

    {{--JQuery-UI--}}
    <script src="{{ asset('js/jquery/jquery-ui.min.js') }}"></script>

    {{--bootstrap--}}
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>

    {{--Fontawesome--}}
    <script src="{{ asset('js/fontawesome/fontawesome.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">


    {{--Datapicker--}}
    <link rel="stylesheet" href="{{ asset('css/datapicker/datepicker.material.css') }}">
    <link rel="stylesheet" href="{{ asset('css/datapicker/datepicker.minimal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/datapicker/themes-jquery-ui.css') }}">
    <script src="{{ asset('js/datapicker/datepicker.js') }}"></script>

    <!-- Google Font: Source Sans Pro -->
    {{--<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">--}}

<!-- Ionicons -->
    {{--<link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">--}}



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

    <!-- daterangepicker -->
    <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('plugins/summernote/summernote-bs4.min.js') }}"></script>

    <!-- AdminLTE App -->
    <script src="{{ asset('dist/js/adminlte.js') }}"></script>

    {{--Cropie--}}
    {{--<link rel="stylesheet" href="{{ asset('css/croppie.min.css') }}">--}}
    {{--<link rel="stylesheet" href="{{ asset('css/croppie.css') }}">--}}
    {{--    <script src="{{ asset('js/croppie.min.js') }}"></script>--}}

    {{--Select2--}}
    <link rel="stylesheet" href="{{ asset('css/select2/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/select2/select2-bootstrap-5-theme.min.css') }}">
    <script src="{{ asset('js/select2/select2.full.min.js') }}"></script>

    <!-- Включение DataTables CSS -->
    <link rel="stylesheet" href="{{ asset('css/jquery.dataTables.min.css') }}">
    <script src="{{ asset('js/datatables/jquery.dataTables.min.js') }}"></script>

    <link rel="stylesheet" href="https://unpkg.com/cropperjs@1.6.1/dist/cropper.min.css">
    <script src="https://unpkg.com/cropperjs@1.6.1/dist/cropper.min.js"></script>

    @vite([
    'resources/sass/app.scss',
    'resources/css/style.css'
    ])


    <style>
        /* Переопределяем пути к стрелкам сортировки */
        table.dataTable thead .sorting {
            background-image: url("/img/datatables/sort_both.png") !important;
        }

        table.dataTable thead .sorting_asc {
            background-image: url("/img/datatables/sort_asc.png") !important;
        }

        table.dataTable thead .sorting_desc {
            background-image: url("/img/datatables/sort_desc.png") !important;
        }

        table.dataTable thead .sorting_asc_disabled {
            background-image: url("/img/datatables/sort_asc_disabled.png") !important;
        }

        table.dataTable thead .sorting_desc_disabled {
            background-image: url("/img/datatables/sort_desc_disabled.png") !important;
        }
    </style>

    <script>
        /**
         * Открывает modalId. Если уже открыта другая модалка —
         * сначала её аккуратно прячем, затем показываем новую.
         * После закрытия новой возвращаем предыдущую модалку.
         */
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
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Preloader -->
{{--<div class="preloader flex-column justify-content-center align-items-center">--}}
{{--<img class="animation__shake" src="{{ asset('dist/img/AdminLTELogo.png') }}" alt="AdminLTELogo" height="60"--}}
{{--width="60">--}}
{{--</div>--}}

<!-- Navbar -->
    @php
        $user = auth()->user();
    @endphp

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Левый бар (акардион для моб версии) -->
        <ul class="navbar-nav ml-3">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>


            @foreach($menuItems as $item)
                <li class="nav-item d-none d-sm-inline-block">
                    <a target="{{ $item->target_blank ? '_blank' : '_self' }}" href="{{ $item->link }}"
                       class="nav-link">{{ $item->name }}</a>
                </li>
            @endforeach
        </ul>

        <!-- Форма переключения партнёров -->
        @can('partner-view')
            <div class="collapse navbar-collapse mr-3">
                <form action="{{ route('partner.switch') }}" method="POST" class="d-flex ms-auto">
                    @csrf
                    <select name="partner_id" class="form-select" onchange="this.form.submit()">
                        @foreach(App\Models\Partner::all() as $partner)
                            <option value="{{ $partner->id }}" {{ session('current_partner') == $partner->id ? 'selected' : '' }}>
                                {{ $partner->title }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
    @endcan



    <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto social-menu mr-3">
            <!-- Navbar Search -->


            @foreach($socialItems as $social)
                <li class="nav-item {{ $loop->first ? '' : 'ml-2' }}">
                    <a target="_blank" class="d-flex justify-content-center align-items-center"
                       href="{{ $social->link }}">
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


            <li class="nav-item d-flex align-items-center">
                <button type="button" class="btn btn-primary logout confirm-logout-modal" data-bs-toggle="modal"
                        data-bs-target="#logoutModal">Выйти
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


            <script>
                // Вызов модалки логаута
                $(document).on('click', '.confirm-logout-modal', function () {
                    logoutUser();
                });

                //Выполнение логаута
                function logoutUser() {
                    // Показываем модалку с текстом и передаём колбэк, который выполнит выход
                    showConfirmDeleteModal(
                        "Подтверждение выхода",
                        "Вы уверены, что хотите выйти?",
                        function () {
                            $.ajax({
                                url: "{{ route('logout') }}",   // маршрут выхода
                                type: "POST",                  // метод запроса
                                data: {
                                    _token: "{{ csrf_token() }}" // обязательно передаём CSRF-токен
                                },
                                success: function (response) {
                                    // Закрываем модальное окно
                                    // $('#deleteConfirmationModal').modal('hide');
                                    // Перезагружаем страницу или перенаправляем, если нужно
                                    location.reload();
                                },
                                error: function (xhr) {
                                    // alert('Ошибка при попытке выйти.');
                                    location.reload();
                                }
                            });
                        }
                    );
                }

            </script>


        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Левое меню -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="/" class="brand-link my-brand-link  ml-3">
            {{--<a href="/" class="ml-3">--}}
            {{--                        <img src="{{ asset('dist/img/AdminLTELogo.png') }}" alt="Kidslink Logo"--}}
            {{--                             class="brand-image img-circle elevation-3" style="opacity: .8">--}}
            <span class="brand-text font-weight-light">кружок.online</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel (optional) -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                {{--                                <div class="image">--}}
                {{--                                    <img src="{{ asset('dist/img/user2-160x160.jpg') }}" class="img-circle elevation-2"--}}
                {{--                                         alt="User Image">--}}
                {{--                                </div>--}}
                <div class="info text-light">
                    <a href="#" class="d-block"></a>
                    <h6> Имя: {{auth()->user()->name}}</h6>
                    {{--                    <h6> Id: {{auth()->user()->id}}</h6>--}}
                    <h6> Почта: {{auth()->user()->email}}</h6>
                    <h6> Роль: {{auth()->user()->role->label}}</h6>

                    @can('servicePayments-view')

                        @php
                            $parsedDate = \Carbon\Carbon::parse($latestEndDate);
                            // Проверяем, меньше ли текущая дата, чем $parsedDate
                            $isFuture = now()->lessThan($parsedDate);
                        @endphp

                        <h6>
                            Оплачено до:
                            <a href="/partner-payment/history">
        <span class="badge {{ $isFuture ? 'badge-success' : 'badge-danger' }} latestEndDate">
            {{ $parsedDate->format('d.m.Y') }}
        </span>
                            </a>

                        </h6>
                    @endcan


                    @can('partnerWallet-view')
                        <h6> Баланс: {{ number_format((float)($partnerWalletBalance ?? 0), 0, ',', ' ') }} руб.
                            <a href="/partner-wallet">(пополнить)</a></h6>
                    @endcan

                </div>
            </div>


            <!-- SidebarSearch Form -->
        {{--                    <div class="form-inline">--}}
        {{--                        <div class="input-group" data-widget="sidebar-search">--}}
        {{--                            <input class="form-control form-control-sidebar" type="search" placeholder="Search"--}}
        {{--                                   aria-label="Search">--}}
        {{--                            <div class="input-group-append">--}}
        {{--                                <button class="btn btn-sidebar">--}}
        {{--                                    <i class="fas fa-search fa-fw"></i>--}}
        {{--                                </button>--}}
        {{--                            </div>--}}
        {{--                        </div>--}}
        {{--                    </div>--}}

        <!-- Sidebar Menu -->
        @include('includes.sidebar')
        <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Контент -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">

                @yield('content')

            </div>
        </section>
    </div>

    <!-- Футер -->
    <footer class="main-footer">

        <div> Copyright &copy; 2023-2025 <a target="_blank" href="https://кружок.online/">кружок.online</a>.
            Все права защищены.
        </div>
        <div class="float-right d-none d-sm-inline-block">
            {{--            <b>Version</b> 3.2.0--}}
        </div>
    </footer>


</div>

<script>
    $.widget.bridge('uibutton', $.ui.button)
</script>


@yield('scripts')


@if (auth()->check() && optional(auth()->user()->role)->name === 'admin' && !auth()->user()->offer_accepted)
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var modal = new bootstrap.Modal(document.getElementById('partnerOfferModal'));
            modal.show();
        });
    </script>
    @include('includes.modal.offerModal')
@endif

@stack('scripts')

{{--jivo site--}}
{{--<script src="//code.jivo.ru/widget/3lc75ICTPG" async></script>--}}


@include('includes.modal.confirmDeleteModal')
@include('includes.modal.successModal')
@include('includes.modal.errorModal')

</body>
</html>