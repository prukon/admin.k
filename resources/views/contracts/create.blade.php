@extends('layouts.admin2')

@section('title','Создать договор')

@section('content')
    @php
        // На случай если контроллер не передал $partnerId
        $CURRENT_PARTNER_ID = $partnerId ?? (auth()->user()->partner_id ?? null);
    @endphp


    <div class="main-content text-start">

        <h4 class="pt-3">Создать договор</h4>
        <hr>
        {{--<div class="buttons d-flex flex-row align-items-center mb-3">--}}
            {{--<button id="new-team" type="button" class="btn btn-primary mr-2 new-team width-170"--}}
                    {{--data-bs-toggle="modal" data-bs-target="#createTeamModal">--}}
                {{--Добавить группу--}}
            {{--</button>--}}
            {{--<button type="button" class="btn btn-primary width-170" id="logs" data-bs-toggle="modal"--}}
                    {{--data-bs-target="#historyModal">История изменений--}}
            {{--</button>--}}
        {{--</div>--}}



        <div class="container py-3">
            {{--<h1 class="h4 mb-3">Создать договор (загрузка PDF)</h1>--}}

            <form id="contract-create-form" method="post" action="/client-contracts" enctype="multipart/form-data">

                @csrf

                <div class="row g-3">

                    {{-- Партнёр текущего пользователя (только просмотр) --}}
                    <div class="col-md-4">
                        <label class="form-label">Партнёр</label>
                        <input type="text" class="form-control" value="{{ $partner->title ?? '—' }}" disabled>
                    </div>

                    {{-- Ученик (Select2 через AJAX по активным ученикам текущего партнёра) --}}
                    <div class="col-md-4">
                        <label class="form-label">Ученик</label>
                        <select name="user_id" id="user_id" class="form-control" required></select>
                        @error('user_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Группа (заполняется автоматом по выбранному ученику, редактировать нельзя) --}}
                    <div class="col-md-4">
                        <label class="form-label">Группа</label>
                        <select id="group_id_select" class="form-control" disabled>
                            <option value="">—</option>
                        </select>
                        <input type="hidden" name="group_id" id="group_id_hidden">
                    </div>

                    <div class="col-12">
                        <label class="form-label">PDF-файл договора</label>
                        <input type="file" name="pdf" class="form-control" accept="application/pdf" required>
                        @error('pdf')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    {{--<button type="submit" class="btn btn-primary">Сохранить</button>--}}
                    {{--<button type="submit" id="btn-save" class="btn btn-primary">Сохранить</button>--}}
                    <button id="btn-save" type="button" class="btn btn-primary">Сохранить</button>


                    <a href="{{ url('/client-contracts') }}" class="btn btn-outline-secondary">Отмена</a>
                </div>
            </form>
            <hr>


            {{-- ====== Блок "Как это работает" (интеграция с Подпислоном) ====== --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                        <div>
                            <h2 class="h3 mb-3">Как это работает</h2>
                            <p class="text-muted mb-3">
                                В <strong>kidscrm.online</strong> вы загружаете PDF-договор и отправляете его клиенту на подпись
                                через интеграцию с сервисом <strong>Подпислон</strong>. Клиент получает SMS со ссылкой на договор и
                                вводит одноразовый СМС код для подписания договора — это простая электронная подпись (ПЭП).
                            </p>
                            <p  class="text-muted mb-3">
                                Таким образом договор будет подписан с двух сторон.
                                С вашей стороны мы подписываем его автоматически при загрузке.
                                Клиент подписывает договор вводом СМС кода.

                            </p>
                            <p class="text-muted mb-0">
                                Подписанный документ автоматически сохраняется в личном кабинете у вас и у клиента, а также отправляется по e-mail. Документ, подписанный ПЭП, обладает
                                юридической значимостью в рамках ФЗ&nbsp;№&nbsp;63 «Об электронной подписи», при соблюдении
                                условий его использования сторонами.
                            </p>
                        </div>

                    </div>

                    <hr class="my-4">

                    {{-- Шаги --}}
                    <div class="row g-4">
                        <div class="col-12">
                            <h3 class="h5 mb-3">Пошаговый процесс</h3>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="step-dot">1</span>
                                <div>
                                    <div class="fw-semibold mb-1">Загрузка документа</div>
                                    <div class="text-muted small">
                                      Вы загружаете договор, выбираете ученика и подписанта (родитель или представитель, с кем будет заключен договор).
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="step-dot">2</span>
                                <div>
                                    <div class="fw-semibold mb-1">Отправка ссылки</div>
                                    <div class="text-muted small">
                                        Сервис отправляет клиенту SMS со ссылкой на страницу подписания.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="step-dot">3</span>
                                <div>
                                    <div class="fw-semibold mb-1">Ознакомление</div>
                                    <div class="text-muted small">
                                        Клиент открывает документ из ссылку в СМС, знакомится с текстом договора.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="step-dot">4</span>
                                <div>
                                    <div class="fw-semibold mb-1">Подтверждение ПЭП</div>
                                    <div class="text-muted small">
                                        Клиент выражает согласие и вводит код из SMS (ПЭП).
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="step-dot">5</span>
                                <div>
                                    <div class="fw-semibold mb-1">Фиксация подписи</div>
                                    <div class="text-muted small">
                                        Регистрируется подпись, формируется отметка и сохраняется событие.
                                        Договор подписан с 2 сторон.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="step-dot">6</span>
                                <div>
                                    <div class="fw-semibold mb-1">Хранение</div>
                                    <div class="text-muted small">
                                        Подписанный файл доступен в личном кабинете компании и у клиента.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="step-dot">7</span>
                                <div>
                                    <div class="fw-semibold mb-1">Уведомления</div>
                                    <div class="text-muted small">
                                        Менеджер видит статус: «Отправлен», «Подписан», «Отменён» и т. д.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="step-dot">8</span>
                                <div>
                                    <div class="fw-semibold mb-1">Выдача клиенту</div>
                                    <div class="text-muted small">
                                        Клиент получает подписанный документ на e-mail и может скачать его со страницы.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Важно / условия --}}
                    <div class="alert alert-info mt-4 mb-0">
                        <div class="fw-semibold mb-1">Важно</div>
                        <ul class="mb-0 ps-3">
                            <li>Стоимость <u>создания</u> договора внутри сервиса составляет <strong>70&nbsp;₽</strong>. Это плата за формирование карточки договора и возможность его отправки на подпись.</li>
                            <li>После создания договора <strong>нельзя изменить подписанта</strong> и <strong>нельзя заменить загруженный файл</strong>.
                                Если нужно сменить подписанта или документ — создайте новый договор.</li>
                            <li>Услуга не подлежит возврату.</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Стили шагов --}}
            @push('styles')
                <style>
                    .step-dot{
                        display:inline-flex;align-items:center;justify-content:center;
                        width:40px;height:40px;border-radius:50%;
                        background:#ffe8cc;color:#fd7e14;font-weight:700;flex:0 0 40px;
                        box-shadow: inset 0 0 0 2px #fd7e14;
                    }
                </style>
            @endpush



            {{-- ====== Стили / Иконки ====== --}}
            @push('styles')
                <link rel="stylesheet"
                      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
                <style>
                    .step-dot {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        width: 40px;
                        height: 40px;
                        border-radius: 50%;
                        background: #ffe8cc;
                        color: #fd7e14;
                        font-weight: 700;
                        flex: 0 0 40px;
                        box-shadow: inset 0 0 0 2px #fd7e14;
                    }

                    .step-ic {
                        font-size: 1.1rem;
                        color: #fd7e14
                    }

                    .accordion-button:focus {
                        box-shadow: none
                    }
                </style>
            @endpush


        </div>

    </div>
@endsection

<style>
    /* ГЛАВНОЕ: поднимаем высоту селекта и синхронизируем line-height */
    :root {
        --s2h: 44px; /* нужная высота: поставь 40–48px как нравится */
    }

    /* Ширина */
    .select2-container {
        width: 100% !important;
    }

    /* SINGLE */
    .select2-container--default .select2-selection--single {
        height: var(--s2h) !important;
        min-height: var(--s2h) !important;
        border: 1px solid #ced4da !important;
        border-radius: .375rem !important;
        padding: 0 .75rem !important;
        display: flex !important;
        align-items: center !important; /* центрируем текст по вертикали */
        box-sizing: border-box !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1.25 !important;
        width: 100% !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: var(--s2h) !important;
        right: .5rem !important;
    }

    /* MULTIPLE (на будущее) */
    .select2-container--default .select2-selection--multiple {
        min-height: var(--s2h) !important;
        border: 1px solid #ced4da !important;
        border-radius: .375rem !important;
        display: flex !important;
        align-items: center !important;
        padding: .25rem .5rem !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: .25rem !important;
    }

    /* Фокус как у Bootstrap */
    .select2-container--default .select2-selection--single:focus,
    .select2-container--default .select2-selection--multiple:focus {
        outline: 0 !important;
        border-color: #86b7fe !important;
        box-shadow: 0 0 0 .25rem rgba(13, 110, 253, .25) !important;
    }

    /* Вспомогательно: высота строки в выпадающем списке */
    .select2-results__option {
        padding: .5rem .75rem !important;
        line-height: 1.25 !important;
    }
</style>






@push('styles')
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        /* Настраиваем явную высоту Select2 */
        :root {
            --s2h: 46px;
        }

        /* можно 44–48px подогнать под "Партнёр" */

        /* Ширина на 100% */
        .select2-container {
            width: 100% !important;
        }

        /* Single select */
        .select2-container--default .select2-selection--single {
            height: var(--s2h) !important;
            min-height: var(--s2h) !important;
            border: 1px solid #ced4da !important;
            border-radius: .375rem !important;
            padding: 0 .75rem !important;
            display: flex !important;
            align-items: center !important; /* вертикальное центрирование текста */
            box-sizing: border-box !important;
            background-color: #fff !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            margin: 0 !important;
            padding: 0 !important;
            line-height: 1.25 !important;
            width: 100% !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: var(--s2h) !important;
            right: .5rem !important;
        }

        /* Фокус как у Bootstrap */
        .select2-container--default .select2-selection--single:focus {
            outline: 0 !important;
            border-color: #86b7fe !important;
            box-shadow: 0 0 0 .25rem rgba(13, 110, 253, .25) !important;
        }

        /* Немного приятнее выпадающее меню */
        .select2-results__option {
            padding: .5rem .75rem !important;
            line-height: 1.25 !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(function () {
            const USERS_URL = '/client-contracts/users-search';

            // Русификация без внешнего ru.js
            $.fn.select2.defaults.set('language', {
                errorLoading: () => 'Не удалось загрузить результаты.',
                inputTooLong: args => `Сократите ввод на ${args.input.length - args.maximum} символ(а/ов).`,
                inputTooShort: args => {
                    const n = Math.max(args.minimum - (args.input ? args.input.length : 0), 1);
                    return `Введите ещё ${n} символ(а/ов).`;
                },
                loadingMore: () => 'Загрузка данных…',
                maximumSelected: args => `Можно выбрать не более ${args.maximum} элемент(а/ов).`,
                noResults: () => 'Ничего не найдено',
                searching: () => 'Поиск…',
                removeAllItems: () => 'Удалить все элементы'
            });

            // Select2 по ученикам (только имя в списке)
            $('#user_id').select2({
                placeholder: 'Начните вводить имя/телефон/email',
                allowClear: true,
                minimumInputLength: 0,
                width: '100%',
                ajax: {
                    url: USERS_URL,
                    dataType: 'json',
                    delay: 200,
                    transport: function (params, success, failure) {
                        const req = $.ajax({
                            url: params.url,
                            method: 'GET',
                            dataType: 'json',
                            data: params.data || {},
                            cache: true,
                            timeout: 15000,
                            beforeSend: function (xhr, settings) {
                                console.log('[S2][beforeSend]', settings.type, settings.url, settings.data);
                            }
                        });
                        req.then(function (resp, textStatus, jqXHR) {
                            console.log('[S2][success]', jqXHR.status, resp);
                            success(resp);
                        }).fail(function (jqXHR, textStatus, errorThrown) {
                            console.error('[S2][fail]', {
                                status: jqXHR.status,
                                statusText: jqXHR.statusText,
                                textStatus,
                                errorThrown,
                                responseText: jqXHR.responseText
                            });
                            failure();
                        });
                        return req;
                    },
                    data: function (params) {
                        const payload = {q: params.term || ''};
                        console.log('[S2][data] payload =', payload);
                        return payload;
                    },
                    processResults: function (data) {
                        console.log('[S2][processResults] raw =', data);
                        return data && Array.isArray(data.results) ? data : {results: []};
                    }
                }
            });

            // Без второго AJAX: берём группу из выбранного элемента
            $('#user_id').on('select2:select', function (e) {
                const d = e.params.data || {};
                console.log('[user:selected]', d);

                const $g = $('#group_id_select');
                const $h = $('#group_id_hidden');

                $g.empty();
                $h.val('');

                if (d.team_id && d.team_title) {
                    $g.append(new Option(d.team_title, d.team_id, true, true));
                    $h.val(d.team_id);
                } else {
                    $g.append(new Option('— группы нет —', '', true, true));
                }
            });

            $('#user_id').on('select2:clear', function () {
                console.log('[select2:clear]');
                $('#group_id_select').empty().append(new Option('—', '', true, true));
                $('#group_id_hidden').val('');
            });
        });


        // Инициализация
        $(function initContractCreateForm() {
            $('#btn-save').on('click', onSaveClick);
        });

        // Клик по кнопке "Создать"
        function onSaveClick(e) {
            e.preventDefault();


            showConfirmDeleteModal(
                'Создание договора',

                'Изменить файл и ученика после создания договора будет нельзя.<br>' +
                '<span class="fw-semibold text-danger">Стоимость создания договора 70&nbsp;руб.</span><br>' +
                'Создать договор?<br>',
                onConfirmCreateContract
            );

        }






        // Подтверждено пользователем — проверяем баланс и сабмитим форму
        function onConfirmCreateContract() {
            var $form = $('#contract-create-form');
            var $btn = $('#btn-save');

            // Если предчек уже был — сразу сабмит
            if ($form.data('precheckDone') === true) {
                $form[0].submit();
                return;
            }

            // UI
            $('.alert-balance').remove();
            $btn.prop('disabled', true);

            $.ajax({
                method: 'POST',
                url: '/client-contracts/check-balance', // прямой URL роута
                dataType: 'json',
                headers: { // csrf из meta-тега
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            })
                .done(handleCheckBalanceSuccess)
                .fail(handleCheckBalanceFail);
        }

        // Успешный предчек: разрешаем сабмит формы
        function handleCheckBalanceSuccess() {
            var $form = $('#contract-create-form');
            var $btn = $('#btn-save');

            $form.data('precheckDone', true);
            $btn.prop('disabled', false);
            $form[0].submit();
        }

        // Баланса не хватило / ошибка
        function handleCheckBalanceFail(xhr) {
            var $form = $('#contract-create-form');
            var $btn = $('#btn-save');

            $btn.prop('disabled', false);

            var msg = 'Недостаточно средств для создания договора.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }

            var $alert = $('<div class="alert alert-danger alert-balance" role="alert"></div>').text(msg);
            $form.prepend($alert);

            if ($alert[0] && $alert[0].scrollIntoView) {
                $alert[0].scrollIntoView({behavior: 'smooth', block: 'start'});
            }
        }


    </script>
@endpush