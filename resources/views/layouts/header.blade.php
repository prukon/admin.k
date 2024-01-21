@section('header')

    <style>
        :root {
            --color-main-grey: #4e4e4e;
            --color-yellow: #f3a12b;
            --color-dark-red: #8e1c13;
        }
        header .head-top {
            background-color: #6969695c;
            padding: 5px 0;
            /*height: 25px;*/
        }

        .social-networks a i {
            width: 30px;
            transition: 0.5s all ease;
        }

        .social-networks a i:hover {
            color: var(--color-dark-red);
        }

        .social-networks a {
            color: black;
            text-decoration: none;
        }

        .social-networks {
            margin-right: 25px;
        }

        .my-profile {
            color: black;
            transition: 0.5s all ease;
        }

        .my-profile i {
            margin-right: 5px;
        }

        header .my-profile {
            /*height: 20px;*/
            border-left: 1px solid black;
        }

        header .head-top .my-profile:hover {
            color: var(--color-dark-red);
        }

        header .head-top figure {
            margin: 0;
        }

        header .header {
            border-bottom: 1px solid #69696957;
            padding: 14px 0;
        }

        header .main-menu a {
            text-transform: uppercase;
            /*padding-bottom: 3px;*/
            border-bottom: 1px solid transparent;
            transition: 0.5s all ease;
            text-decoration: none;
            color: var(--color-main-grey);
        }

        header .main-menu a:hover {
            color: var(--color-dark-red);
            border-bottom: 1px solid var(--color-dark-red);
        }

        header .tel {
            color: var(--color-main-grey);
        }

    </style>


    <style>
        /*media*/
        @media screen and (max-width: 1399px) {
            header .main-menu {
                font-size: 12px;
            }
            header .tel {
                font-size: 12px;
            }
        }

        @media screen and (max-width: 991px) {
            header .main-menu {
                font-size: 11px;
            }
            header .tel {
                font-size: 11px;
            }
        }
        @media screen and (max-width: 767px) {
      .social-networks {
          margin-right: 0;
      }
        }
    </style>
    <header>
{{--        <div class="head-top container-fluid ">--}}
{{--            <div class="container ">--}}
{{--                <div class="row justify-content-end align-items-center">--}}
{{--                    <nav class="d-flex col-1 social-networks">--}}
{{--                        <a target="_blank" class="d-flex justify-content-center align-items-center"--}}
{{--                           href="https://vk.com/fc_istok_spb"><i class="fa-brands fa-vk"></i></a>--}}
{{--                        <a target="_blank" class="d-flex justify-content-center align-items-center"--}}
{{--                           href="https://www.youtube.com/channel/UCmOq_eBvQIQgP9sEGlpHwdg"><i--}}
{{--                                    class="fa-brands fa-youtube"></i></a>--}}
{{--                    </nav>--}}
{{--                    <a href="#" class="my-profile col-2  d-flex align-items-center">--}}
{{--                        <figure class="d-flex align-items-center">--}}
{{--                            <i class="fa-solid fa-user "></i>--}}
{{--                            <figcaption>Мой профиль</figcaption>--}}
{{--                        </figure>--}}
{{--                    </a>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}

        <div class="header container-fluid">
            <div class="container">
                <div class="row align-items-center justify-content-center">
                    <a href="/" class="logo col-2">
                        <img src="resources/img/logo.png" alt="fc-istok.ru">
                    </a>

                    <nav class="main-menu col-sm-8 col-md-6 col-lg-5 col-xl-4 d-flex justify-content-between align-items-center">
                        <a href="#">Личный кабинет</a>
                        <a href="#">Расписание занятий</a>
                        <a href="#">Контакты</a>
                    </nav>
                    <nav class="d-flex col-sm-6 col-md-1 col-lg-2 social-networks justify-content-center ">
                        <a target="_blank" class="d-flex justify-content-center align-items-center"
                           href="https://vk.com/fc_istok_spb"><i class="fa-brands fa-vk"></i></a>
                        <a target="_blank" class="d-flex justify-content-center align-items-center"
                           href="https://www.youtube.com/channel/UCmOq_eBvQIQgP9sEGlpHwdg"><i
                                    class="fa-brands fa-youtube"></i></a>
                    </nav>
                    <a class="tel col-sm-6 col-md-2 d-flex justify-content-sm-center justify-content-md-start align-items-center" href="tel:78129204575">8 (812)
                        920-45-75</a>
                    </a>

                </div>
            </div>
        </div>
    </header>
@endsection