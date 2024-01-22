@section('footer')
    <style>
        footer {
            background-color: #f3f5f7;
        }
        footer .company-details {
            margin-bottom: 15px;
            margin-top: 15px;
        }
        footer .social-networks {
            margin-bottom: 15px;
        }
        footer .copyright {
            margin-bottom: 15px;
        }
        footer .dev-author {
            margin-right: 5px;
        }
        footer .social-networks {
            color: black;
        }
    </style>

    <footer>
        <div class="footer container-fluid">
            <div class="container">
                <div class="row align-items-center justify-content-center">

                    <a class="col-12 d-flex  social-networks justify-content-center company-details" href="#">Реквизиты</a>

                    <nav class="col-12 d-flex  social-networks justify-content-center ">
                        <a target="_blank" class="d-flex justify-content-center align-items-center"
                           href="https://vk.com/fc_istok_spb"><i class="fa-brands fa-vk"></i></a>
                        <a target="_blank" class="d-flex justify-content-center align-items-center"
                           href="https://www.youtube.com/channel/UCmOq_eBvQIQgP9sEGlpHwdg"><i
                                    class="fa-brands fa-youtube"></i></a>
                    </nav>

                    <div class="col-12 d-flex  social-networks justify-content-center text-center  copyright" href="/">Copyright © 2015 - 2024. Все права защищены. Футбольная школа "Исток".</div>

                    <div class="col-12 d-flex  social-networks justify-content-center developer"><span class="dev-author">Разработка:</span><a target="_blank" href="https://krasivo-agency.ru/">krasivo-agency.ru</a></div>

                </div>
            </div>
        </div>
    </footer>
@endsection