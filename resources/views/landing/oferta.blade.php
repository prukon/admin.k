@extends('layouts.landingPage')

@section('title', 'кружок.online — Управление спортом онлайн')

@section('content')
    <!-- Hero -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row justify-content-center mb-4">
                <div class="col-lg-10">
                    <h1 class="display-6 fw-bold text-center mb-4">Публичная оферта</h1>

                    <div class="mb-4">
                        <p><strong>Индивидуальный предприниматель:</strong> Устьян Евгений Артурович</p>
                        <p><strong>ИНН:</strong> 110211351590</p>
                        <p><strong>ЕГРНИП:</strong> 324784700017432</p>
                        <p><strong>Основание:</strong> Выписка из ЕГРИП от 23.01.2024</p>
                        <p><strong>ОКВЭД:</strong><br>
                            – Основной: 68.20<br>
                            – Дополнительные: 62.01, 62.02, 62.09, 63.11, 63.12, 68.10, 68.32, 73.11, 77.21
                        </p>
                        <p><strong>Юридический адрес:</strong> г. Санкт-Петербург, Плесецкая ул., д. 16, стр. 1, кв. 354, 197373</p>
                        <p><strong>Email:</strong> <a href="mailto:kidslinkru@yandex.ru">kidslinkru@yandex.ru</a></p>
                    </div>

                    <div class="text-justify">
                        <h5 class="fw-bold">1. Общие положения</h5>
                        <p>1.1. Настоящая публичная оферта (далее — Оферта) является официальным предложением ИП Устьян Е.А. (далее — Исполнитель) заключить договор на оказание услуг через онлайн-сервис (далее — Сервис).</p>
                        <p>1.2. Акцептом Оферты считается регистрация и использование Сервиса Клиентом.</p>

                        <h5 class="fw-bold mt-4">2. Предмет Оферты</h5>
                        <p>2.1. Исполнитель предоставляет доступ к Сервису для управления расписанием и оплатами, а Клиент обязуется оплатить услуги согласно условиям Оферты.</p>

                        <h5 class="fw-bold mt-4">3. Порядок заключения договора</h5>
                        <p>3.1. Договор считается заключённым с момента регистрации Клиента в Сервисе.</p>

                        <h5 class="fw-bold mt-4">4. Права и обязанности сторон</h5>
                        <p><strong>Обязанности Исполнителя:</strong></p>
                        <ul>
                            <li>Предоставление доступа к Сервису согласно тарифу</li>
                            <li>Техническая поддержка и обновления</li>
                            <li>Защита персональных данных</li>
                        </ul>
                        <p><strong>Обязанности Клиента:</strong></p>
                        <ul>
                            <li>Своевременная оплата услуг</li>
                            <li>Предоставление достоверных данных</li>
                            <li>Использование Сервиса в соответствии с условиями</li>
                        </ul>

                        <h5 class="fw-bold mt-4">5. Стоимость и порядок оплаты</h5>
                        <p>5.1. Стоимость определяется тарифным планом.<br>
                            5.2. Оплата — авансом ежемесячно через онлайн-эквайринг.<br>
                            5.3. При просрочке Исполнитель приостанавливает доступ.</p>

                        <h5 class="fw-bold mt-4">6. Интеллектуальная собственность</h5>
                        <p>6.1. Все права на программное обеспечение и дизайн принадлежат Исполнителю.<br>
                            6.2. Клиенту предоставляется неисключительное право на использование.</p>

                        <h5 class="fw-bold mt-4">7. Конфиденциальность</h5>
                        <p>7.1. Персональные данные не подлежат разглашению.<br>
                            7.2. Клиент даёт согласие на обработку данных.</p>

                        <h5 class="fw-bold mt-4">8. Ответственность</h5>
                        <p>8.1. Исполнитель не несёт ответственность за технические сбои.<br>
                            8.2. При нарушении условий доступ может быть прекращён без возврата оплаты.</p>

                        <h5 class="fw-bold mt-4">9. Срок действия и изменения</h5>
                        <p>9.1. Оферта действует с момента публикации.<br>
                            9.2. Изменения публикуются на сайте и вступают в силу автоматически.</p>

                        <h5 class="fw-bold mt-4">10. Заключительные положения</h5>
                        <p>10.1. Все споры решаются путём переговоров или в суде согласно законодательству РФ.<br>
                            10.2. Контактный email: <a href="mailto:kidslinkru@yandex.ru">kidslinkru@yandex.ru</a></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4">
        <div class="container">
            <p class="mb-1">&copy; 2024 – 2025 кружок.online</p>
            <div>
                <a href="#" class="text-white text-decoration-none mx-2">Политика конфиденциальности</a>
                <a href="#" class="text-white text-decoration-none mx-2">Условия использования</a>
            </div>
        </div>
    </footer>

    <!-- Modal Demo -->
    <div class="modal fade" id="demoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Запись на демо</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('demo.request') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Ваше имя</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="tel" name="phone" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Записаться</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
