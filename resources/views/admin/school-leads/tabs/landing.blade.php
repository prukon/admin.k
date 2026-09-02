<div class="main-content text-start">
    <p class="text-muted mt-3">
        Брендированная страница с полной формой заявки. Задайте короткий адрес и отправьте ссылку клиентам или разместите её в рекламе.
        Виджет для встраивания на сайт — на вкладке «Виджет для сайта».
    </p>
    <hr>

    <div class="container" style="max-width: 900px;">
        <form id="landingSlugForm" class="mb-4" novalidate>
            <label class="form-label fw-semibold" for="landingSlugInput">Адрес страницы</label>
            <div class="input-group mb-1">
                <span class="input-group-text text-muted">{{ rtrim(url('/'), '/') }}/lead/</span>
                <input type="text"
                       class="form-control"
                       id="landingSlugInput"
                       name="landing_slug"
                       value="{{ $widget->landing_slug }}"
                       placeholder="fk-dinamo"
                       autocomplete="off"
                       spellcheck="false"
                       maxlength="40">
                <button type="submit" class="btn btn-primary" id="saveLandingSlugBtn">Сохранить</button>
            </div>
            <div class="invalid-feedback d-block" id="landingSlugError"></div>
            <div class="form-text">
                Латинские буквы, цифры и дефис, от 3 до 40 символов. Пример: <code>shkola-rossi</code>
            </div>
            <div class="alert alert-success d-none mt-2 mb-0 small" id="landingSlugSuccess"></div>
        </form>

        @if ($landingUrl)
            <div class="mb-3">
                <label class="form-label fw-semibold">Ссылка на страницу заявки</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="landingUrl" value="{{ $landingUrl }}" readonly>
                    <button type="button" class="btn btn-outline-secondary" id="copyLandingUrlBtn">Копировать</button>
                </div>
                <span class="text-success ms-2 d-none" id="copyLandingSuccess">Скопировано</span>
            </div>

            <div class="mb-4">
                <a href="{{ $landingUrl }}" class="btn btn-outline-primary" target="_blank" rel="noopener noreferrer">
                    Открыть страницу
                </a>
                <button type="button"
                        class="btn btn-outline-secondary"
                        id="openInstructionSettingsBtn"
                        data-bs-toggle="modal"
                        data-bs-target="#instructionPhoneModal">
                    Инструкция для родителей
                </button>
            </div>
        @else
            <div class="alert alert-info mb-4">
                Сохраните адрес страницы — после этого появится готовая ссылка для клиентов.
            </div>
        @endif

        @if (!$widget->is_landing_active)
            <div class="alert alert-warning">
                Страница заявки отключена. Обратитесь к администратору платформы для включения.
            </div>
        @endif
    </div>
</div>

@if ($landingUrl)
    @php
        $instructionAdminPhones = $instructionAdminPhones ?? [];
    @endphp
    <div class="modal fade" id="instructionPhoneModal" tabindex="-1" aria-labelledby="instructionPhoneModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="instructionPhoneForm"
                      method="post"
                      action="{{ route('admin.school-leads.instruction-preview') }}"
                      novalidate>
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="instructionPhoneModalLabel">Инструкция для родителей</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body text-start">
                        <p class="mb-3">Нужно ли указывать номер телефона в инструкции?</p>

                        <div class="form-check mb-3">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value="1"
                                   id="instructionOmitPhone"
                                   name="omit_phone">
                            <label class="form-check-label" for="instructionOmitPhone">
                                Не указывать номер телефона
                            </label>
                            <div class="invalid-feedback d-block" data-error-for="omit_phone"></div>
                        </div>

                        <div id="instructionPhoneFields">
                            @if (count($instructionAdminPhones) > 0)
                                <div class="mb-3">
                                    <label class="form-label" for="instructionAdminPhoneSelect">Номер администратора</label>
                                    <select class="form-select" id="instructionAdminPhoneSelect">
                                        <option value="">Выберите номер</option>
                                        @foreach ($instructionAdminPhones as $adminPhone)
                                            <option value="{{ $adminPhone['id'] }}"
                                                    data-phone="{{ $adminPhone['digits'] }}">
                                                {{ $adminPhone['label'] }}
                                            </option>
                                        @endforeach
                                        <option value="__custom__">Другой номер</option>
                                    </select>
                                </div>
                            @endif

                            <div class="mb-0">
                                <label class="form-label" for="instructionPhoneInput">Номер телефона</label>
                                @include('includes.fields.phone-input', [
                                    'name' => 'phone',
                                    'id' => 'instructionPhoneInput',
                                    'value' => '',
                                    'required' => false,
                                ])
                                <div class="invalid-feedback d-block" data-error-for="phone"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary" id="instructionOpenBtn">Открыть инструкцию</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@section('scripts')
    <script>
        $(function () {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            var $form = $('#landingSlugForm');
            var $slugInput = $('#landingSlugInput');
            var $slugError = $('#landingSlugError');
            var $slugSuccess = $('#landingSlugSuccess');
            var $saveBtn = $('#saveLandingSlugBtn');

            function showSlugErrors(errors) {
                $slugError.empty();
                if (!errors) {
                    $slugInput.removeClass('is-invalid');
                    return;
                }
                $slugInput.addClass('is-invalid');
                var messages = errors.landing_slug || [errors.message || 'Проверьте адрес страницы.'];
                if (!Array.isArray(messages)) {
                    messages = [messages];
                }
                messages.forEach(function (msg) {
                    $slugError.append($('<div></div>').text(msg));
                });
            }

            $form.on('submit', function (e) {
                e.preventDefault();
                $slugSuccess.addClass('d-none');
                showSlugErrors(null);
                $saveBtn.prop('disabled', true);

                $.ajax({
                    url: @json(route('admin.school-leads.landing-slug.update')),
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: { landing_slug: $slugInput.val() },
                })
                    .done(function (data) {
                        $slugInput.val(data.landing_slug || $slugInput.val());
                        $slugSuccess.text(data.message || 'Сохранено.').removeClass('d-none');
                        window.location.reload();
                    })
                    .fail(function (xhr) {
                        var body = xhr.responseJSON || {};
                        showSlugErrors(body.errors || { message: body.message });
                    })
                    .always(function () {
                        $saveBtn.prop('disabled', false);
                    });
            });

            $('#copyLandingUrlBtn').on('click', function () {
                var text = $('#landingUrl').val();
                if (!text) {
                    return;
                }
                navigator.clipboard.writeText(text).then(function () {
                    $('#copyLandingSuccess').removeClass('d-none');
                    setTimeout(function () { $('#copyLandingSuccess').addClass('d-none'); }, 2000);
                });
            });

            var $instructionForm = $('#instructionPhoneForm');
            var $instructionModal = $('#instructionPhoneModal');
            var $omitPhone = $('#instructionOmitPhone');
            var $phoneFields = $('#instructionPhoneFields');
            var $adminPhoneSelect = $('#instructionAdminPhoneSelect');
            var $phoneInput = $('#instructionPhoneInput');
            var $instructionOpenBtn = $('#instructionOpenBtn');

            function clearInstructionErrors() {
                $instructionForm.find('[data-error-for]').empty();
                $phoneInput.removeClass('is-invalid');
                $omitPhone.removeClass('is-invalid');
                $adminPhoneSelect.removeClass('is-invalid');
            }

            function showInstructionErrors(errors) {
                clearInstructionErrors();
                if (!errors) {
                    return;
                }
                Object.keys(errors).forEach(function (field) {
                    var messages = errors[field];
                    if (!Array.isArray(messages)) {
                        messages = [messages];
                    }
                    var $box = $instructionForm.find('[data-error-for="' + field + '"]');
                    messages.forEach(function (msg) {
                        $box.append($('<div></div>').text(msg));
                    });
                    if (field === 'phone') {
                        $phoneInput.addClass('is-invalid');
                    }
                    if (field === 'omit_phone') {
                        $omitPhone.addClass('is-invalid');
                    }
                });
            }

            function syncInstructionPhoneFields() {
                var omit = $omitPhone.is(':checked');
                $phoneFields.toggleClass('d-none', omit);
                $phoneFields.find('input, select').prop('disabled', omit);
            }

            function resetInstructionForm() {
                $omitPhone.prop('checked', false);
                if ($adminPhoneSelect.length) {
                    $adminPhoneSelect.val('');
                }
                if (window.PhoneInputMask) {
                    window.PhoneInputMask.setValue($phoneInput, '');
                } else {
                    $phoneInput.val('');
                }
                clearInstructionErrors();
                syncInstructionPhoneFields();
            }

            $omitPhone.on('change', function () {
                clearInstructionErrors();
                syncInstructionPhoneFields();
            });

            $adminPhoneSelect.on('change', function () {
                var selected = $(this).find('option:selected');
                var digits = selected.attr('data-phone') || '';
                var value = $(this).val();
                clearInstructionErrors();
                if (!value || value === '__custom__') {
                    if (window.PhoneInputMask) {
                        window.PhoneInputMask.setValue($phoneInput, '');
                    } else {
                        $phoneInput.val('');
                    }
                    if (value === '__custom__') {
                        $phoneInput.trigger('focus');
                    }
                    return;
                }
                if (window.PhoneInputMask) {
                    window.PhoneInputMask.setValue($phoneInput, digits);
                } else {
                    $phoneInput.val(digits);
                }
            });

            $phoneInput.on('input', function () {
                if (!$adminPhoneSelect.length) {
                    return;
                }
                var selected = $adminPhoneSelect.find('option:selected');
                var selectedDigits = selected.attr('data-phone') || '';
                var currentDigits = window.PhoneInputMask
                    ? window.PhoneInputMask.digits($phoneInput)
                    : String($phoneInput.val() || '').replace(/\D+/g, '');
                if (selectedDigits && currentDigits !== selectedDigits) {
                    $adminPhoneSelect.val('__custom__');
                }
            });

            $instructionModal.on('show.bs.modal', function () {
                resetInstructionForm();
            });

            $instructionForm.on('submit', function (e) {
                e.preventDefault();
                clearInstructionErrors();
                $instructionOpenBtn.prop('disabled', true);

                var omit = $omitPhone.is(':checked');
                $.ajax({
                    url: @json(route('admin.school-leads.instruction-preview')),
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    data: {
                        omit_phone: omit ? 1 : 0,
                        phone: omit ? '' : $phoneInput.val(),
                    },
                })
                    .done(function (data) {
                        var url = data && data.instruction_url;
                        if (!url) {
                            return;
                        }
                        var modal = bootstrap.Modal.getInstance($instructionModal[0]);
                        if (modal) {
                            modal.hide();
                        }
                        window.open(url, '_blank', 'noopener,noreferrer');
                    })
                    .fail(function (xhr) {
                        var body = xhr.responseJSON || {};
                        showInstructionErrors(body.errors || { phone: [body.message || 'Проверьте номер телефона.'] });
                    })
                    .always(function () {
                        $instructionOpenBtn.prop('disabled', false);
                    });
            });
        });
    </script>
@endsection
