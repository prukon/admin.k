<h4 class="pt-3 pb-3 text-start">Платежные системы</h4>

<div class="row mt-4">
    {{-- Карточка: Робокасса --}}
    <div class="col-sm-3 mb-4">
        <div class="card shadow h-100">
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <img src="{{ asset('img/partners/robokassa.png') }}" alt="Робокасса" class="mb-3">
                <h5 class="card-title">Робокасса</h5>
                @if($robokassa && $robokassa->is_connected)
                    <button
                            class="btn btn-success mt-3 toggleable-status-btn"
                            data-original-text="Подключено"
                            data-hover-text="Отключить"
                            data-id="{{ $robokassa->id }}"
                            data-url="{{ route('payment-systems.destroy', ['payment_system' => $robokassa->id]) }}">
                        Подключено
                    </button>


                    @if($robokassa->test_mode)
                        <div class="mt-2 text-muted small">Тестовый режим</div>
                    @endif

                @else
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#modalRobokassa">
                        Подключить
                    </button>
                @endif

                <div class="mt-3">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#modalRobokassaInfo">Подробнее</a>
                </div>
            </div>
        </div>
    </div>

{{-- Карточка: T-Банк --}}
<div class="col-sm-3 mb-4">
    <div class="card shadow h-100">
        <div class="card-body text-center d-flex flex-column justify-content-center">
            <img src="{{ asset('img/partners/tbank.png') }}" alt="T-Банк" class="mb-3">
            <h5 class="card-title">T‑Банк (мультирасчёты)</h5>

            @if($tbank && $tbank->is_connected)
                <button
                        class="btn btn-success mt-3 toggleable-status-btn"
                        data-original-text="Подключено"
                        data-hover-text="Отключить"
                        data-id="{{ $tbank->id }}"
                        data-url="{{ route('payment-systems.destroy', ['payment_system' => $tbank->id]) }}">
                    Подключено
                </button>

                @if($tbank->test_mode)
                    <div class="mt-2 text-muted small">Тестовый режим</div>
                @endif
            @else
                <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#modalTbank">
                    Подключить
                </button>
            @endif

            <div class="mt-3">
                <a href="#" data-bs-toggle="modal" data-bs-target="#modalTbankInfo">Подробнее</a>
            </div>
        </div>
    </div>
</div>


</div>

{{--Модалка для ввода настроек Робокассы--}}
<div class="modal fade" id="modalRobokassa" tabindex="-1" aria-labelledby="modalRobokassaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="robokassaForm">
                @csrf
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="modalRobokassaLabel">Подключение Робокассы</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="merchant_login" class="form-label">Merchant Login</label>

                        {{--<input type="text" class="form-control" name="merchant_login" id="merchant_login"--}}
                               {{--placeholder="Введите Merchant Login">--}}
                        {{----}}
                        @php($rob = $robokassa)
                        {{-- безопасно читаем --}}
                        @php($s = is_array($rob?->settings ?? null) ? $rob->settings : [])
                        {{-- дальше через data_get --}}
                        <input name="merchant_login" value="{{ data_get($s, 'merchant_login') }}">


                    </div>
                    <div class="mb-3">
                        <label for="password1" class="form-label">Пароль #1</label>
                        <input type="text" class="form-control" name="password1" id="password1"
                               placeholder="Введите Пароль #1">
                    </div>
                    <div class="mb-3">
                        <label for="password2" class="form-label">Пароль #2</label>
                        <input type="text" class="form-control" name="password2" id="password2"
                               placeholder="Введите Пароль #2">
                    </div>
                    <div class="mb-3">
                        <label for="password3" class="form-label">Пароль #3 (для API возвратов)</label>
                        <input type="text" class="form-control" name="password3" id="password3"
                               placeholder="Введите Пароль #3 (нужен только для возвратов)">
                        <div class="form-text">
                            Требуется для Robokassa Refund API (JWT). Включается через заявку «Доступ к API возвратов».
                        </div>
                    </div>

                    <div class="form-check">
                        <input type="hidden" name="test_mode" value="0">
                        <input type="checkbox" class="form-check-input" name="test_mode" id="robokassa_test_mode"
                               value="1" {{ old('test_mode') ? 'checked' : '' }}>
                        <label class="form-check-label" for="robokassa_test_mode">Тестовый режим</label>
                    </div>

                    {{-- Скрытое поле name="robokassa" --}}
                    <input type="hidden" name="name" value="robokassa">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{--Модалка "Подробнее" для Робокассы--}}
<div class="modal fade" id="modalRobokassaInfo" tabindex="-1" aria-labelledby="modalRobokassaInfoLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="modalRobokassaInfoLabel">Подробнее о Робокассе</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p>
                    Робокасса — один из самых популярных сервисов приёма платежей в России.
                    Поддерживает оплату банковскими картами, через СБП, электронные кошельки и интернет-банкинг.
                    Работает как в тестовом, так и в боевом режиме. Для подключения нужны данные: логин магазина и два пароля.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

{{--Модалка для Tбанка--}}
<div class="modal fade" id="modalTbank" tabindex="-1" aria-labelledby="modalTbankLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="tbankForm">
                @csrf
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="modalTbankLabel">Подключение TБанка</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="terminal_key" class="form-label">TerminalKey (приём платежей)</label>
                        <input type="text" class="form-control" name="terminal_key" id="terminal_key"
                               placeholder="Введите TerminalKey">
                    </div>
                    <div class="mb-3">
                        <label for="token_password" class="form-label">Пароль для Token (приём платежей)</label>
                        <input type="text" class="form-control" name="token_password" id="token_password"
                               placeholder="Введите пароль для подписи Token">
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label for="e2c_terminal_key" class="form-label">TerminalKey (выплаты партнёру)</label>
                        <input type="text" class="form-control" name="e2c_terminal_key" id="e2c_terminal_key"
                               placeholder="Введите TerminalKey для e2c">
                    </div>
                    <div class="mb-3">
                        <label for="e2c_token_password" class="form-label">Пароль для Token (выплаты партнёру)</label>
                        <input type="text" class="form-control" name="e2c_token_password" id="e2c_token_password"
                               placeholder="Введите пароль для подписи Token (e2c)">
                    </div>

                    <div class="form-check">
                        <input type="hidden" name="test_mode" value="0">
                        <input type="checkbox" class="form-check-input" name="test_mode" id="tbank_test_mode" value="1">
                        <label class="form-check-label" for="tbank_test_mode">Тестовый режим</label>
                    </div>

                    <input type="hidden" name="name" value="tbank">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{--Модалка "Подробнее" для Tбанка--}}
<div class="modal fade" id="modalTbankInfo" tabindex="-1" aria-labelledby="modalTbankInfoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="modalTbankInfoLabel">Подробнее о TБанке</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p>
                    Информация о подключении и возможностях TБанка...
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

@section('scripts')

    <script>
        // Сохранение данных Робокасса
        $('#robokassaForm').on('submit', function (e) {
            e.preventDefault();
            let formArray = $(this).serializeArray();
            let formData = {};
            formArray.forEach(function (item) {
                formData[item.name] = item.value;
            });
            // Приводим test_mode к числу 1 или 0 (Laravel это принимает как boolean)
            formData['test_mode'] = $('#robokassa_test_mode').is(':checked') ? 1 : 0;
            $.ajax({
                url: '{{ route('payment-systems.store') }}',
                method: 'POST',
                data: formData,
                success: function (response) {
                    showSuccessModal("Подключение Robokassa", "Данные для использования Robokassa сохранены.", 1);
                },
                error: function (xhr) {
                    $('#errorModal').modal('show');
                    $('#error-modal-message').text(xhr.responseJSON?.message || 'Ошибка при создании данных.');
                }
            });
        });

        // Сохранение данных Тбанка
        $('#tbankForm').on('submit', function (e) {
            e.preventDefault();
            let formArray = $(this).serializeArray();
            let formData = {};
            formArray.forEach(function (item) {
                formData[item.name] = item.value;
            });
            // Приводим test_mode к числу 1 или 0 (Laravel это принимает как boolean)
            formData['test_mode'] = $('#tbank_test_mode').is(':checked') ? 1 : 0;
            $.ajax({
                url: '{{ route('payment-systems.store') }}',
                method: 'POST',
                data: formData,
                success: function (response) {
                    showSuccessModal("Подключение Tbank", "Данные для использования Tbank сохранены.", 1);
                },
                error: function (xhr) {
                    $('#errorModal').modal('show');
                    $('#error-modal-message').text(xhr.responseJSON?.message || 'Ошибка при создании данных.');
                }
            });
        });


        // ховер кноки + Отключение ПС
        $(document).ready(function () {
            // Hover-эффект
            $('.toggleable-status-btn').hover(
                function () {
                    const $btn = $(this);
                    $btn.text($btn.data('hover-text'));
                    $btn.removeClass('btn-success').addClass('btn-danger');
                },
                function () {
                    const $btn = $(this);
                    $btn.text($btn.data('original-text'));
                    $btn.removeClass('btn-danger').addClass('btn-success');
                }
            );

            // Клик по кнопке "Отключить"
            $('.toggleable-status-btn').on('click', function (e) {
                e.preventDefault();

                const $btn = $(this);
                const url = $btn.data('url');

                showConfirmDeleteModal(
                    "Удаление платежной системы",
                    "Вы действительно хотите отключить платёжную систему?",
                    function () {
                        $.ajax({
                            url: url,
                            type: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function (response) {
                                location.reload();
                            },
                            error: function (xhr) {
                                alert('Ошибка при отключении платёжной системы');
                            }
                        });
                    });


            });
        });


    </script>

    <style>
        .toggleable-status-btn {
            font-size: 13px !important;
        }
    </style>

@endsection
