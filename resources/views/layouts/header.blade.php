    @section('header')

        <style>
            :root {
                --color1: #f3a12b;
            }


            header .head-top {
                background-color: #6969695c;
                padding: 5px, 0;
                /*height: 25px;*/
            }
            header .head-top nav a i {
                width: 30px;
                transition: 0.5s all ease;
            }

            header .head-top nav a i:hover{
                color: var(--color1);
            }

            header nav a {
                color: black;
                text-decoration: none;
            }
            .head-top nav {
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

            header .head-top .my-profile:hover  {
                color: var(--color1);
            }
            header .head-top figure {
                margin: 0;
            }

        </style>

        <header>
            <div class="head-top container-fluid ">
                <div class="container ">
                    <div class="row justify-content-end align-items-center">
<nav class="d-flex col-1 ">
    <a target="_blank" class="d-flex justify-content-center align-items-center" href="https://vk.com/fc_istok_spb"><i class="fa-brands fa-vk"></i></a>
    <a target="_blank" class="d-flex justify-content-center align-items-center" href="https://www.youtube.com/channel/UCmOq_eBvQIQgP9sEGlpHwdg"><i class="fa-brands fa-youtube"></i></a>
</nav>
                        <a href="#" class="my-profile col-2  d-flex align-items-center">
                            <figure class="d-flex align-items-center">
                                <i class="fa-solid fa-user "></i>
                                <figcaption>Мой профиль</figcaption>
                            </figure>
                        </a>




                    </div>
                </div>
            </div>
        </header>

<div class="container text-center">
    <div class="row">
        <nav class="navbar navbar-expand-lg navbar-default bg-light">

        <div class="col-xl-4  col-sm-12 text-center">
            <a class="navbar-brand" href="/"><img src="public/logo.jpg" alt="logo"></a>
            <a href="tel:78129204575">8 (812) 920-45-75</a>
        </div>

        <div class="col-xl-4  col-sm-12  text-center">
            <ul class="navbar-nav text-uppercase">
                <li class="active"><a class="nav-link" href="/">Главная </a></li>
                <li><a class="nav-link" href="https://fc-istok.ru/raspisanie/">Расписание занятий</a></li>
                <li><a class="nav-link" href="https://fc-istok.ru/#contacts">Контакты</a></li>
        </ul>
        </div>

        <div class="col-xl-4  col-sm-12  text-center">
            <ul class="navbar-nav">
                <li><a target="_blank" class="nav-link" href="https://vk.com/fc_istok_spb"><i class="fa-brands fa-vk"></i></a></li>
                <li><a target="_blank" class="nav-link" href="https://www.youtube.com/channel/UCmOq_eBvQIQgP9sEGlpHwdg"><i class="fa-brands fa-youtube"></i></a></li>
                <li><a target="_blank" class="nav-link" href="https://vk.com/fc_istok_spb"><i class="fa-solid fa-user"></i></a></li>
            </ul>
        </div>
        </nav>
    </div>

</div>

    @endsection