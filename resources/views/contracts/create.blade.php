@extends('layouts.admin2')

@section('title','Создать договор')

@section('content')
    @php
        // На случай если контроллер не передал $partnerId
        $CURRENT_PARTNER_ID = $partnerId ?? (auth()->user()->partner_id ?? null);
    @endphp

    <div class="container py-3">
        <h1 class="h4 mb-3">Создать договор (загрузка PDF)</h1>

        <form id="contract-create-form" method="post" action="/contracts" enctype="multipart/form-data">

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
                    <label class="form-label">Группа (опц.)</label>
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


                <a href="{{ url('/contracts') }}" class="btn btn-outline-secondary">Отмена</a>
            </div>
        </form>
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
            const USERS_URL = '/contracts/users-search';

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
                '<span class="fw-semibold text-danger">Стоимость создания договора 50&nbsp;руб.</span><br>' +
                'Вы уверены, что хотите создать договор пользователя?',
                onConfirmCreateContract
            );

        }

        // Подтверждено пользователем — проверяем баланс и сабмитим форму
        function onConfirmCreateContract() {
            var $form = $('#contract-create-form');
            var $btn  = $('#btn-save');

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
                url: '/contracts/check-balance', // прямой URL роута
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
            var $btn  = $('#btn-save');

            $form.data('precheckDone', true);
            $btn.prop('disabled', false);
            $form[0].submit();
        }

        // Баланса не хватило / ошибка
        function handleCheckBalanceFail(xhr) {
            var $form = $('#contract-create-form');
            var $btn  = $('#btn-save');

            $btn.prop('disabled', false);

            var msg = 'Недостаточно средств для создания договора.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }

            var $alert = $('<div class="alert alert-danger alert-balance" role="alert"></div>').text(msg);
            $form.prepend($alert);

            if ($alert[0] && $alert[0].scrollIntoView) {
                $alert[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }


    </script>
@endpush