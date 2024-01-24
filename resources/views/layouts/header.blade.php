@section('header')

    <header>

        <div class="header container">
            <div class="row align-items-center justify-content-center">
                <a href="/" class="logo col-2">
                    <img src=" {{ asset('img/logo.png') }}" alt="fc-istok.ru">
                </a>

                <nav class="main-menu col-8 col-sm-8 col-md-6 col-lg-5 col-xl-4 d-flex justify-content-between align-items-center">
                    <a href="#">Личный кабинет</a>
                    <a href="#">Расписание занятий</a>
                    <a href="#">Контакты</a>
                </nav>
                <nav class="d-flex col-6 col-sm-6 col-md-1 col-lg-2 social-networks justify-content-center ">
                    <a target="_blank" class="d-flex justify-content-center align-items-center"
                       href="https://vk.com/fc_istok_spb"><i class="fa-brands fa-vk"></i></a>
                    <a target="_blank" class="d-flex justify-content-center align-items-center"
                       href="https://www.youtube.com/channel/UCmOq_eBvQIQgP9sEGlpHwdg"><i
                                class="fa-brands fa-youtube"></i></a>
                </nav>
                <a class="tel col-6 col-sm-6 col-md-2 d-flex justify-content-center  align-items-center"
                   href="tel:78129204575">8 (812) 920-45-75</a>
                </a>

            </div>
        </div>

    </header>
@endsection