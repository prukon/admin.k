@php
    $partner = $partner ?? null;
    $contractTemplates = $contractTemplates ?? collect();
    $preselectedUser = $preselectedUser ?? null;
@endphp

<div class="modal fade"
     id="createContractModal"
     tabindex="-1"
     aria-labelledby="createContractModalLabel"
     aria-hidden="true"
     data-bs-backdrop="static"
     data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered contract-create-modal">
        <div class="modal-content">
            <form id="contract-create-form" method="post" action="{{ route('contracts.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="createContractModalLabel">Создать договор</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>

                <div class="modal-body text-start contract-create-modal-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="contract-create-modal-fields row g-3">
                        <div class="col-12">
                            <label class="form-label" for="user_id">Ученик</label>
                            <select name="user_id"
                                    id="user_id"
                                    class="form-select @error('user_id') is-invalid @enderror"
                                    data-placeholder="Начните вводить имя/телефон/email"
                                    required></select>
                            <div class="field-error-msg text-danger small mt-1"
                                 data-field-error="user_id">@error('user_id'){{ $message }}@enderror</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="parent_full_name_display">ФИО родителя</label>
                            <input type="text"
                                   id="parent_full_name_display"
                                   class="form-control"
                                   value="—"
                                   disabled
                                   readonly>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="group_id_select">Группа</label>
                            <select id="group_id_select" class="form-select" disabled>
                                <option value="">—</option>
                            </select>
                            <input type="hidden" name="group_id" id="group_id_hidden">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Партнёр</label>
                            <input type="text" class="form-control" value="{{ $partner->title ?? '—' }}" disabled>
                        </div>

                        <div class="col-12">
                            <label class="form-label d-block">Способ создания</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="creation_mode" id="mode_pdf"
                                       value="pdf" @checked(old('creation_mode', 'pdf') === 'pdf')>
                                <label class="form-check-label" for="mode_pdf">Прикрепить готовый PDF</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="creation_mode" id="mode_template"
                                       value="template" @checked(old('creation_mode') === 'template')>
                                <label class="form-check-label" for="mode_template">Отправить шаболн договора</label>
                            </div>
                            <div class="field-error-msg text-danger small mt-1"
                                 data-field-error="creation_mode">@error('creation_mode'){{ $message }}@enderror</div>
                        </div>

                        <div class="col-12" id="block-pdf">
                            <label class="form-label" for="input_pdf">PDF-файл договора</label>
                            <input type="file" name="pdf" id="input_pdf" class="form-control @error('pdf') is-invalid @enderror" accept="application/pdf">
                            <div class="field-error-msg text-danger small mt-1"
                                 data-field-error="pdf">@error('pdf'){{ $message }}@enderror</div>
                        </div>

                        <div class="col-12" id="block-template" style="display:none">
                            <label class="form-label" for="contract_template_id">Шаблон договора</label>
                            @if($contractTemplates->isEmpty())
                                <div class="alert alert-warning mb-0" role="alert">
                                    Шаблонов нет.
                                    <a href="{{ route('contract-templates.index', ['create' => 1]) }}" class="alert-link">Создать шаблон</a>
                                </div>
                            @else
                                <select name="contract_template_id" id="contract_template_id" class="form-select @error('contract_template_id') is-invalid @enderror">
                                    <option value="">— выберите шаблон —</option>
                                    @foreach($contractTemplates as $tpl)
                                        <option value="{{ $tpl->id }}" @selected((int) old('contract_template_id') === (int) $tpl->id)>
                                            {{ $tpl->title }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    <a href="{{ route('contract-templates.index') }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       onclick="setTimeout(function () { window.focus(); }, 0);">Управление шаблонами</a>
                                </div>
                            @endif
                            <div class="field-error-msg text-danger small mt-1"
                                 data-field-error="contract_template_id">@error('contract_template_id'){{ $message }}@enderror</div>
                        </div>
                    </div>

                    <div class="contract-create-modal-help mt-3">
                        <button type="button"
                                class="btn btn-link btn-sm px-0 text-decoration-none"
                                id="contractHowItWorksToggle"
                                aria-expanded="false"
                                aria-controls="contractCreateHowItWorks">
                            Как это работает
                        </button>
                        <div id="contractCreateHowItWorks"
                             class="contract-create-how-it-works-panel mt-2"
                             hidden
                             tabindex="0"
                             aria-label="Как это работает">
                            @include('contracts.partials.how-it-works')
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button id="btn-save" type="button" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
    <style>
        .contract-create-modal {
            max-width: 560px;
        }

        #createContractModal .modal-body {
            max-height: calc(100vh - 11rem);
            overflow-y: auto;
        }

        #createContractModal .contract-create-how-it-works-panel {
            display: block;
            height: 300px;
            max-height: 45vh;
            overflow-x: hidden;
            overflow-y: scroll;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            border: 2px solid #dee2e6;
            border-radius: 0.5rem;
            background-color: #f8f9fa;
        }

        #createContractModal .contract-create-how-it-works-panel[hidden] {
            display: none !important;
        }

        #createContractModal .contract-create-how-it-works-panel .card {
            border: 0 !important;
            box-shadow: none !important;
            margin-bottom: 0;
            background: transparent;
        }

        #createContractModal .contract-create-how-it-works-panel .card-body {
            padding: 0.5rem 0.4rem 0.5rem 0.15rem !important;
        }

        #createContractModal .contract-create-how-it-works-panel .h3 {
            font-size: 1.05rem;
            margin-bottom: 0.4rem;
        }

        #createContractModal .contract-create-how-it-works-panel .text-muted {
            font-size: 0.8rem;
            line-height: 1.35;
            margin-bottom: 0.4rem !important;
        }

        #createContractModal .contract-create-how-it-works-panel hr {
            margin: 0.5rem 0;
        }

        #createContractModal .contract-create-how-it-works-panel .contract-how-steps {
            --bs-gutter-y: 0.35rem;
            --bs-gutter-x: 0.25rem;
        }

        #createContractModal .contract-create-how-it-works-panel .contract-how-steps-title {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        #createContractModal .contract-create-how-it-works-panel .step-dot {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background: #ffe8cc;
            color: #fd7e14;
            font-weight: 700;
            font-size: 0.7rem;
            flex: 0 0 1.5rem;
            box-shadow: inset 0 0 0 1px #fd7e14;
            margin-right: 0.25rem;
        }

        #createContractModal .contract-create-how-it-works-panel .contract-how-step-title {
            font-size: 0.8rem;
            margin-bottom: 0.05rem !important;
            line-height: 1.2;
        }

        #createContractModal .contract-create-how-it-works-panel .contract-how-step-text {
            font-size: 0.72rem;
            line-height: 1.25;
        }

        #createContractModal .contract-create-how-it-works-panel .alert {
            margin-top: 0.5rem;
            margin-bottom: 0;
            padding: 0.45rem 0.5rem 0.45rem 0.4rem;
            font-size: 0.78rem;
        }

        #createContractModal .contract-create-how-it-works-panel .alert ul {
            margin-bottom: 0;
            padding-left: 0.9rem;
        }

        #createContractModal .contract-create-how-it-works-panel .alert li {
            margin-bottom: 0.2rem;
        }

        #createContractModal .contract-create-how-it-works-panel .alert li:last-child {
            margin-bottom: 0;
        }

        /* disabled «Сохранить»: без белого текста на белом фоне из глобального .btn-primary */
        #createContractModal #btn-save:disabled,
        #createContractModal #btn-save.disabled {
            opacity: 1;
            pointer-events: none;
            color: #6c757d !important;
            background-color: #e9ecef !important;
            border-color: #ced4da !important;
        }

        #createContractModal .field-error-msg:empty {
            display: none;
        }

        #createContractModal .select2-container.is-invalid .select2-selection {
            border-color: #dc3545;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            const hasContractTemplates = @json($contractTemplates->isNotEmpty());
            const preselectedUser = @json($preselectedUser);
            const shouldOpenCreateModal = @json($shouldOpenCreateModal ?? false);
            const createModalEl = document.getElementById('createContractModal');
            let suppressCreateModalReset = false;

            function getContractCreateFieldInput(fieldName) {
                if (fieldName === 'creation_mode') {
                    return $('input[name="creation_mode"]').first();
                }

                return $('#contract-create-form').find('[name="' + fieldName + '"]');
            }

            function getContractCreateFieldErrorEl(fieldName) {
                return $('#contract-create-form').find('[data-field-error="' + fieldName + '"]');
            }

            function markContractCreateFieldInvalid(fieldName, isInvalid) {
                if (fieldName === 'user_id') {
                    const $userSelect = $('#user_id');
                    $userSelect.toggleClass('is-invalid', isInvalid);
                    $userSelect.next('.select2-container').toggleClass('is-invalid', isInvalid);
                    return;
                }

                if (fieldName === 'creation_mode') {
                    $('input[name="creation_mode"]').toggleClass('is-invalid', isInvalid);
                    return;
                }

                const $input = getContractCreateFieldInput(fieldName);
                if ($input.length) {
                    $input.toggleClass('is-invalid', isInvalid);
                }
            }

            function clearContractCreateFieldError(fieldName) {
                const $error = getContractCreateFieldErrorEl(fieldName);
                $error.text('');
                markContractCreateFieldInvalid(fieldName, false);
            }

            function showContractCreateFieldError(fieldName, message) {
                getContractCreateFieldErrorEl(fieldName).text(message);
                markContractCreateFieldInvalid(fieldName, true);
            }

            function clearAllContractCreateFieldErrors() {
                $('#contract-create-form [data-field-error]').each(function () {
                    $(this).text('');
                });
                $('#contract-create-form .is-invalid').removeClass('is-invalid');
                $('#user_id').next('.select2-container').removeClass('is-invalid');
            }

            function applyServerContractCreateFieldErrors() {
                $('#contract-create-form [data-field-error]').each(function () {
                    const $error = $(this);
                    const fieldName = $error.data('field-error');
                    const text = $error.text().trim();

                    if (text !== '') {
                        markContractCreateFieldInvalid(fieldName, true);
                    }
                });
            }

            function validateContractCreateForm() {
                clearAllContractCreateFieldErrors();

                let valid = true;
                let firstInvalidField = null;

                const userId = $('#user_id').val();
                if (!userId || String(userId).trim() === '') {
                    showContractCreateFieldError('user_id', 'Выберите ученика.');
                    valid = false;
                    firstInvalidField = firstInvalidField || 'user_id';
                }

                const mode = $('input[name="creation_mode"]:checked').val();

                if (mode === 'pdf') {
                    const pdfInput = document.getElementById('input_pdf');
                    if (!pdfInput || !pdfInput.files || pdfInput.files.length === 0) {
                        showContractCreateFieldError('pdf', 'Загрузите PDF-файл договора.');
                        valid = false;
                        firstInvalidField = firstInvalidField || 'pdf';
                    }
                } else if (mode === 'template' && hasContractTemplates) {
                    const templateId = $('#contract_template_id').val();
                    if (!templateId || String(templateId).trim() === '') {
                        showContractCreateFieldError('contract_template_id', 'Выберите шаблон договора.');
                        valid = false;
                        firstInvalidField = firstInvalidField || 'contract_template_id';
                    }
                }

                if (!valid && firstInvalidField) {
                    if (firstInvalidField === 'user_id') {
                        $('#user_id').select2('open');
                    } else {
                        const $input = getContractCreateFieldInput(firstInvalidField);
                        if ($input.length) {
                            $input.trigger('focus');
                        }
                    }
                }

                return valid;
            }

            function destroyContractUserSelect2() {
                const $userSelect = $('#user_id');
                if ($userSelect.data('select2')) {
                    $userSelect.select2('destroy');
                }
            }

            function setParentFullNameDisplay(value) {
                const display = (value && String(value).trim() !== '') ? String(value).trim() : '—';
                $('#parent_full_name_display').val(display);
            }

            function initContractUserSelect2() {
                const $userSelect = $('#user_id');
                if (!$userSelect.length) {
                    return;
                }

                destroyContractUserSelect2();

                $userSelect.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $userSelect.data('placeholder') || '',
                    language: @include('partials.select2.ru'),
                    allowClear: true,
                    minimumInputLength: 0,
                    dropdownParent: $('#createContractModal'),
                    ajax: {
                        url: @json(route('contracts.users.search')),
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {q: params.term || ''};
                        },
                        processResults: function (data) {
                            return data && Array.isArray(data.results) ? data : {results: []};
                        }
                    }
                });

                $userSelect.off('select2:select.contractCreate select2:clear.contractCreate');
                $userSelect.on('select2:select.contractCreate', function (e) {
                    clearContractCreateFieldError('user_id');

                    const d = e.params.data || {};
                    const $g = $('#group_id_select');
                    const $h = $('#group_id_hidden');

                    setParentFullNameDisplay(d.parent_full_name);

                    $g.empty();
                    $h.val('');

                    if (d.team_id && d.team_title) {
                        $g.append(new Option(d.team_title, d.team_id, true, true));
                        $h.val(d.team_id);
                    } else {
                        $g.append(new Option('— группы нет —', '', true, true));
                    }
                });

                $userSelect.on('select2:clear.contractCreate', function () {
                    clearContractCreateFieldError('user_id');

                    setParentFullNameDisplay('');
                    $('#group_id_select').empty().append(new Option('—', '', true, true));
                    $('#group_id_hidden').val('');
                });
            }

            function applyPreselectedStudent() {
                if (!preselectedUser) {
                    return;
                }

                const $userSelect = $('#user_id');
                const $g = $('#group_id_select');
                const $h = $('#group_id_hidden');

                if ($userSelect.find('option[value="' + preselectedUser.id + '"]').length === 0) {
                    $userSelect.append(new Option(preselectedUser.text, preselectedUser.id, true, true));
                }
                $userSelect.val(String(preselectedUser.id)).trigger('change');

                setParentFullNameDisplay(preselectedUser.parent_full_name);

                $g.empty();
                $h.val('');

                if (preselectedUser.team_id && preselectedUser.team_title) {
                    $g.append(new Option(preselectedUser.team_title, preselectedUser.team_id, true, true));
                    $h.val(preselectedUser.team_id);
                } else {
                    $g.append(new Option('— группы нет —', '', true, true));
                }
            }

            function toggleCreationMode() {
                const mode = $('input[name="creation_mode"]:checked').val();
                const $templateSelect = $('#contract_template_id');

                if (mode === 'template') {
                    $('#block-pdf').hide();
                    $('#input_pdf').prop('required', false);
                    $('#block-template').show();
                    if (hasContractTemplates) {
                        $templateSelect.prop('required', true);
                        $('#btn-save').prop('disabled', false);
                    } else {
                        $('#btn-save').prop('disabled', true);
                    }
                } else {
                    $('#block-pdf').show();
                    $('#input_pdf').prop('required', true);
                    $('#block-template').hide();
                    if (hasContractTemplates) {
                        $templateSelect.prop('required', false);
                    }
                    $('#btn-save').prop('disabled', false);
                }
            }

            function onSaveClick(e) {
                e.preventDefault();

                if (!validateContractCreateForm()) {
                    return;
                }

                suppressCreateModalReset = true;

                showConfirmDeleteModal(
                    'Создание договора',
                    'Изменить файл и ученика после создания договора будет нельзя.<br>' +
                    '<span class="fw-semibold">Стоимость создания договора 70&nbsp;руб.</span><br>' +
                    'Создать договор?<br>',
                    onConfirmCreateContract
                );
            }

            function onConfirmCreateContract() {
                const $form = $('#contract-create-form');
                const $btn = $('#btn-save');

                if ($form.data('precheckDone') === true) {
                    $form[0].submit();
                    return;
                }

                $('.alert-balance').remove();
                $btn.prop('disabled', true);

                $.ajax({
                    method: 'POST',
                    url: @json(url('/client-contracts/check-balance')),
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                })
                    .done(function () {
                        $form.data('precheckDone', true);
                        $btn.prop('disabled', false);
                        $form[0].submit();
                    })
                    .fail(function (xhr) {
                        $btn.prop('disabled', false);

                        let msg = 'Недостаточно средств для создания договора.';
                        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }

                        const $alert = $('<div class="alert alert-danger alert-balance" role="alert"></div>').text(msg);
                        $form.find('.modal-body').prepend($alert);
                    });
            }

            function resetCreateContractForm() {
                const form = document.getElementById('contract-create-form');
                if (!form) {
                    return;
                }

                form.reset();
                clearAllContractCreateFieldErrors();
                form.querySelectorAll('.alert-balance').forEach(function (el) {
                    el.remove();
                });
                $(form).data('precheckDone', false);

                destroyContractUserSelect2();

                $('#group_id_select').empty().append(new Option('—', '', true, true));
                $('#group_id_hidden').val('');
                setParentFullNameDisplay('');
                $('#block-template').hide();
                $('#block-pdf').show();
                $('#btn-save').prop('disabled', false);
                toggleCreationMode();

                const $howItWorks = $('#contractCreateHowItWorks');
                const $howItWorksToggle = $('#contractHowItWorksToggle');
                $howItWorks.prop('hidden', true).prop('scrollTop', 0);
                $howItWorksToggle.attr('aria-expanded', 'false');
                if ($howItWorksToggle.length) {
                    $howItWorksToggle.text($howItWorksToggle.data('default-label') || 'Как это работает');
                }
            }

            function stripCreateModalQueryParams() {
                if (!window.location.search) {
                    return;
                }

                const url = new URL(window.location.href);
                url.searchParams.delete('create');
                url.searchParams.delete('user_id');
                window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
            }

            $(function () {
                if (!createModalEl) {
                    return;
                }

                $('#contractHowItWorksToggle').on('click', function (e) {
                    e.preventDefault();

                    const $panel = $('#contractCreateHowItWorks');
                    const $toggle = $(this);
                    const defaultLabel = $toggle.data('default-label') || 'Как это работает';

                    if (!$toggle.data('default-label')) {
                        $toggle.data('default-label', defaultLabel);
                    }

                    const willShow = $panel.prop('hidden');
                    $panel.prop('hidden', !willShow);
                    $toggle.attr('aria-expanded', willShow ? 'true' : 'false');
                    $toggle.text(willShow ? 'Скрыть: как это работает' : defaultLabel);

                    if (willShow) {
                        $panel.prop('scrollTop', 0);
                    }
                });

                createModalEl.addEventListener('shown.bs.modal', function () {
                    suppressCreateModalReset = false;
                    initContractUserSelect2();
                    applyPreselectedStudent();
                    toggleCreationMode();
                    applyServerContractCreateFieldErrors();
                });

                createModalEl.addEventListener('hidden.bs.modal', function () {
                    if (suppressCreateModalReset) {
                        return;
                    }

                    if (@json($errors->any())) {
                        return;
                    }

                    resetCreateContractForm();
                    stripCreateModalQueryParams();
                });

                $('input[name="creation_mode"]').on('change', function () {
                    clearContractCreateFieldError('pdf');
                    clearContractCreateFieldError('contract_template_id');
                    toggleCreationMode();
                });
                $('#input_pdf').on('change', function () {
                    clearContractCreateFieldError('pdf');
                });
                $('#contract_template_id').on('change', function () {
                    clearContractCreateFieldError('contract_template_id');
                });
                $('#btn-save').on('click', onSaveClick);

                if (shouldOpenCreateModal) {
                    bootstrap.Modal.getOrCreateInstance(createModalEl).show();
                }
            });
        })();
    </script>
@endpush
