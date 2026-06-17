@php
    use App\Models\ParentProfile;
    use App\Models\Role;
    use App\Services\PartnerContext;

    $prefix = $prefix ?? 'create';
    $parentSelectId = $prefix . '-parent-id';
    $parentLastnameId = $prefix . '-parent-lastname';
    $parentFirstnameId = $prefix . '-parent-firstname';
    $parentMiddlenameId = $prefix . '-parent-middlename';
    $parentSearchUrl = route('admin.users.parents.search');
    $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');

    if (!isset($hasParentProfiles)) {
        $partnerId = app(PartnerContext::class)->partnerId();
        $hasParentProfiles = $partnerId
            ? ParentProfile::query()->where('partner_id', $partnerId)->exists()
            : false;
    }
@endphp

<div class="col-12 js-student-parent-fields {{ ($prefix ?? 'create') === 'lead' ? '' : 'd-none' }}"
     data-parent-prefix="{{ $prefix }}"
     data-student-role-id="{{ $studentRoleId }}"
     data-has-parent-profiles="{{ $hasParentProfiles ? '1' : '0' }}">
    <div class="small text-muted mb-2 js-parent-section-title {{ !empty($hideSectionTitle) ? 'd-none' : '' }}">Данные родителя</div>

    @if($hasParentProfiles)
        <div class="js-parent-mode-toggle-wrap mb-2" data-parent-prefix="{{ $prefix }}">
            <div class="parent-mode-segmented" role="group" aria-label="Способ указания родителя">
                <button type="button"
                        class="btn parent-mode-segmented__btn active js-parent-mode-btn"
                        data-parent-prefix="{{ $prefix }}"
                        data-mode="directory">
                    Из справочника
                </button>
                <button type="button"
                        class="btn parent-mode-segmented__btn js-parent-mode-btn"
                        data-parent-prefix="{{ $prefix }}"
                        data-mode="new">
                    Новый родитель
                </button>
            </div>
        </div>

        <div class="js-parent-select-wrap" data-parent-prefix="{{ $prefix }}">
            <div class="mb-2">
                <label for="{{ $parentSelectId }}" class="form-label">Родитель в справочнике</label>
                <select name="parent_id"
                        id="{{ $parentSelectId }}"
                        class="form-select js-parent-profile-select"
                        data-parent-prefix="{{ $prefix }}"
                        data-search-url="{{ $parentSearchUrl }}">
                    <option value=""></option>
                </select>
                {{-- <div class="form-text">
                    Начните вводить ФИО, чтобы найти существующего родителя (удобно, если у вас несколько детей).
                </div> --}}
            </div>
        </div>
    @endif

    <div class="js-parent-fio-section {{ $hasParentProfiles ? 'd-none' : '' }}" data-parent-prefix="{{ $prefix }}">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="mb-3">
                    <label for="{{ $parentLastnameId }}" class="form-label">Фамилия родителя</label>
                    <input type="text"
                           name="parent_lastname"
                           class="form-control js-parent-lastname"
                           id="{{ $parentLastnameId }}"
                           data-parent-prefix="{{ $prefix }}"
                           maxlength="100"
                           value="{{ $parentLastname ?? '' }}">
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="mb-3">
                    <label for="{{ $parentFirstnameId }}" class="form-label">Имя родителя</label>
                    <input type="text"
                           name="parent_firstname"
                           class="form-control js-parent-firstname"
                           id="{{ $parentFirstnameId }}"
                           data-parent-prefix="{{ $prefix }}"
                           maxlength="100"
                           value="{{ $parentFirstname ?? '' }}">
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="mb-3">
                    <label for="{{ $parentMiddlenameId }}" class="form-label">Отчество родителя</label>
                    <input type="text"
                           name="parent_middlename"
                           class="form-control js-parent-middlename"
                           id="{{ $parentMiddlenameId }}"
                           data-parent-prefix="{{ $prefix }}"
                           maxlength="100"
                           value="{{ $parentMiddlename ?? '' }}">
                </div>
            </div>
        </div>
    </div>

    <div class="js-parent-profile-section" data-parent-prefix="{{ $prefix }}">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="mb-3">
                    <label for="{{ $prefix }}-parent-passport" class="form-label">Паспорт родителя</label>
                    <input type="text"
                           name="parent_passport"
                           class="form-control js-parent-passport"
                           id="{{ $prefix }}-parent-passport"
                           data-parent-prefix="{{ $prefix }}"
                           maxlength="100"
                           value="{{ $parentPassport ?? '' }}">
                </div>
            </div>

            <div class="col-12">
                <div class="mb-3">
                    <label for="{{ $prefix }}-parent-passport-issued" class="form-label">Паспорт, кем и когда выдан</label>
                    <input type="text"
                           name="parent_passport_issued"
                           class="form-control js-parent-passport-issued"
                           id="{{ $prefix }}-parent-passport-issued"
                           data-parent-prefix="{{ $prefix }}"
                           maxlength="500"
                           value="{{ $parentPassportIssued ?? '' }}">
                </div>
            </div>

            <div class="col-12">
                <div class="mb-3">
                    <label for="{{ $prefix }}-parent-address" class="form-label">Адрес родителя</label>
                    <input type="text"
                           name="parent_address"
                           class="form-control js-parent-address"
                           id="{{ $prefix }}-parent-address"
                           data-parent-prefix="{{ $prefix }}"
                           maxlength="1000"
                           value="{{ $parentAddress ?? '' }}">
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="mb-3">
                    <label for="{{ $prefix }}-parent-phone" class="form-label">Телефон родителя</label>
                    @include('includes.fields.phone-input', [
                        'name' => 'parent_phone',
                        'id' => $prefix . '-parent-phone',
                        'value' => $parentPhone ?? '',
                        'parentPhone' => true,
                        'attributes' => [
                            'data-parent-prefix' => $prefix,
                        ],
                    ])
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="mb-3">
                    <label for="{{ $prefix }}-parent-email" class="form-label">Email родителя</label>
                    <input type="email"
                           name="parent_email"
                           class="form-control js-parent-email"
                           id="{{ $prefix }}-parent-email"
                           data-parent-prefix="{{ $prefix }}"
                           maxlength="255"
                           value="{{ $parentEmail ?? '' }}">
                </div>
            </div>
        </div>
    </div>
</div>

@once
    @push('styles')
        <style>
            /* Палитра как у payments-report-toolbar на /admin/users */
            .parent-mode-segmented {
                display: inline-flex;
                width: 100%;
                max-width: 22rem;
                padding: 0.2rem;
                gap: 0.15rem;
                border-radius: 0.5rem;
                border: 1px solid #b6d4fe;
            }

            .parent-mode-segmented__btn {
                flex: 1 1 0;
                margin: 0;
                padding: 0.3rem 0.55rem;
                font-size: 0.8125rem;
                line-height: 1.25;
                font-weight: 500;
                color: #212529;
                background: transparent;
                border: none;
                border-radius: 0.375rem;
                box-shadow: none;
                white-space: nowrap;
                transition: background 0.15s ease, box-shadow 0.15s ease;
            }

            .parent-mode-segmented__btn:hover,
            .parent-mode-segmented__btn:focus {
                color: #212529;
                background: #f0f7ff;
            }

            .parent-mode-segmented__btn.active,
            .parent-mode-segmented__btn.active:hover,
            .parent-mode-segmented__btn.active:focus {
                color: #212529;
                font-weight: 600;
                background: #cfe2ff;
                box-shadow: 0 1px 3px rgba(13, 110, 253, 0.28);
            }

            .parent-mode-segmented__btn:disabled {
                opacity: 0.55;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function ($) {
                if (!window.__studentParentSelect2FocusFixBound) {
                    window.__studentParentSelect2FocusFixBound = true;
                    document.addEventListener('focusin', function (e) {
                        const target = e.target;
                        if (!target || typeof target.closest !== 'function') {
                            return;
                        }
                        if (target.closest('.select2-container, .select2-dropdown')) {
                            e.stopImmediatePropagation();
                        }
                    }, true);
                }

                function parentFieldIds(prefix) {
                    return {
                        select: '#' + prefix + '-parent-id',
                        lastname: '#' + prefix + '-parent-lastname',
                        firstname: '#' + prefix + '-parent-firstname',
                        middlename: '#' + prefix + '-parent-middlename',
                        passport: '#' + prefix + '-parent-passport',
                        passportIssued: '#' + prefix + '-parent-passport-issued',
                        address: '#' + prefix + '-parent-address',
                        phone: '#' + prefix + '-parent-phone',
                        email: '#' + prefix + '-parent-email',
                    };
                }

                function parentFieldsWrap(prefix) {
                    return $('.js-student-parent-fields[data-parent-prefix="' + prefix + '"]');
                }

                function hasParentProfilesFor(prefix) {
                    return String(parentFieldsWrap(prefix).data('has-parent-profiles')) === '1';
                }

                function parentUiRoots(prefix) {
                    return {
                        selectWrap: parentFieldsWrap(prefix).find('.js-parent-select-wrap'),
                        fioSection: parentFieldsWrap(prefix).find('.js-parent-fio-section'),
                        modeButtons: parentFieldsWrap(prefix).find('.js-parent-mode-btn'),
                    };
                }

                function syncParentModeButtons(prefix, mode) {
                    const ui = parentUiRoots(prefix);
                    ui.modeButtons.each(function () {
                        const $btn = $(this);
                        $btn.toggleClass('active', $btn.data('mode') === mode);
                    });
                }

                /** @param {'directory'|'new'} mode */
                function setParentFormMode(prefix, mode, options) {
                    options = options || {};
                    const ui = parentUiRoots(prefix);
                    const isNew = mode === 'new';

                    if (!hasParentProfilesFor(prefix)) {
                        ui.fioSection.removeClass('d-none');
                        return;
                    }

                    ui.selectWrap.toggleClass('d-none', isNew);
                    ui.fioSection.toggleClass('d-none', !isNew);

                    if (!options.skipButtonsUpdate) {
                        syncParentModeButtons(prefix, mode);
                    }
                }

                window.syncStudentParentFieldsVisibility = function (prefix) {
                    const $wrap = parentFieldsWrap(prefix);
                    if (prefix === 'lead') {
                        $wrap.removeClass('d-none');
                        $wrap.find('input, select, textarea').prop('disabled', false);
                        $wrap.find('.js-parent-mode-btn').prop('disabled', false);

                        return;
                    }

                    const $form = $wrap.closest('form');
                    const studentRoleId = String($wrap.data('student-role-id') || '');
                    const currentRoleId = String($form.find('select[name="role_id"]').val() || '');
                    const show = studentRoleId !== '' && currentRoleId === studentRoleId;

                    $wrap.toggleClass('d-none', !show);
                    $wrap.find('input, select, textarea').prop('disabled', !show);
                    $wrap.find('.js-parent-mode-btn').prop('disabled', !show);
                };

                function syncParentFormUi(prefix, payload) {
                    payload = payload || {};

                    if (!hasParentProfilesFor(prefix)) {
                        setParentFormMode(prefix, 'new');
                        return;
                    }

                    const parentId = payload.parent_id ? String(payload.parent_id) : '';
                    const hasFio = [payload.parent_lastname, payload.parent_firstname, payload.parent_middlename]
                        .some(function (value) {
                            return String(value || '').trim() !== '';
                        });

                    if (parentId) {
                        setParentFormMode(prefix, 'directory');
                        return;
                    }

                    setParentFormMode(prefix, hasFio ? 'new' : 'directory');
                }

                function fillParentFio(prefix, data) {
                    const ids = parentFieldIds(prefix);
                    $(ids.lastname).val(data.parent_lastname || '');
                    $(ids.firstname).val(data.parent_firstname || '');
                    $(ids.middlename).val(data.parent_middlename || '');
                    $(ids.passport).val(data.parent_passport || '');
                    $(ids.passportIssued).val(data.parent_passport_issued || '');
                    $(ids.address).val(data.parent_address || '');
                    window.PhoneInputMask?.setValue(ids.phone, data.parent_phone || '');
                    $(ids.email).val(data.parent_email || '');
                }

                function clearParentSelect(prefix) {
                    const ids = parentFieldIds(prefix);
                    const $select = $(ids.select);
                    if (!$select.length) {
                        return;
                    }

                    if ($select.data('select2')) {
                        if (!$select.find('option[value=""]').length) {
                            $select.prepend(new Option('', '', true, true));
                        }
                        $select.val(null).trigger('change');
                    } else {
                        $select.val('');
                    }
                }

                function initParentSelect($select) {
                    const prefix = $select.data('parent-prefix');
                    const searchUrl = $select.data('search-url');

                    if ($select.data('select2')) {
                        $select.select2('destroy');
                    }

                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        allowClear: true,
                        placeholder: 'ФИО родителя',
                        language: @include('partials.select2.ru'),
                        minimumInputLength: 0,
                        ajax: {
                            url: searchUrl,
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term || '',
                                    limit: 20,
                                };
                            },
                            processResults: function (data) {
                                return {
                                    results: (data.results || []).map(function (item) {
                                        return {
                                            id: item.id,
                                            text: item.text,
                                            parent_lastname: item.parent_lastname,
                                            parent_firstname: item.parent_firstname,
                                            parent_middlename: item.parent_middlename,
                                            parent_passport: item.parent_passport,
                                            parent_passport_issued: item.parent_passport_issued,
                                            parent_address: item.parent_address,
                                            parent_phone: item.parent_phone,
                                            parent_email: item.parent_email,
                                        };
                                    }),
                                };
                            },
                        },
                    });

                    $select.off('select2:open.studentParent select2:select.studentParent select2:clear.studentParent');
                    $select.on('select2:open.studentParent', function () {
                        setTimeout(function () {
                            const field = document.querySelector(
                                '.select2-container--open .select2-search__field'
                            );
                            if (field) {
                                field.focus();
                            }
                        }, 0);
                    });
                    $select.on('select2:select.studentParent', function (e) {
                        const row = e.params.data || {};
                        fillParentFio(prefix, row);
                        setParentFormMode(prefix, 'directory');
                    });
                    $select.on('select2:clear.studentParent', function () {
                        fillParentFio(prefix, {});
                        setParentFormMode(prefix, 'directory');
                        if (!$select.find('option[value=""]').length) {
                            $select.prepend(new Option('', '', true, true));
                        } else {
                            $select.val('').trigger('change');
                        }
                    });
                }

                window.setStudentParentForm = function (prefix, payload) {
                    payload = payload || {};
                    const ids = parentFieldIds(prefix);
                    const $select = $(ids.select);
                    const parentId = payload.parent_id ? String(payload.parent_id) : '';

                    fillParentFio(prefix, payload);

                    if ($select.length) {
                        if (!parentId) {
                            clearParentSelect(prefix);
                        } else {
                            const label = [payload.parent_lastname, payload.parent_firstname, payload.parent_middlename]
                                .filter(Boolean)
                                .join(' ')
                                .trim();

                            const option = new Option(label || ('Родитель #' + parentId), parentId, true, true);
                            $select.empty().append(option).trigger('change');
                        }
                    }

                    if (payload.forceNewParent) {
                        clearParentSelect(prefix);
                        fillParentFio(prefix, payload);
                        setParentFormMode(prefix, 'new', {skipButtonsUpdate: true});
                        syncParentModeButtons(prefix, 'new');
                    } else {
                        syncParentFormUi(prefix, payload);
                    }

                    window.syncStudentParentFieldsVisibility(prefix);
                };

                window.resetStudentParentForm = function (prefix) {
                    window.setStudentParentForm(prefix, {
                        parent_id: null,
                        parent_lastname: '',
                        parent_firstname: '',
                        parent_middlename: '',
                        parent_passport: '',
                        parent_passport_issued: '',
                        parent_address: '',
                        parent_phone: '',
                        parent_email: '',
                    });
                };

                function initParentSelectsInModal($modal) {
                    $modal.find('.js-parent-profile-select').each(function () {
                        initParentSelect($(this));
                    });
                }

                $(document).on('click', '.js-parent-mode-btn', function () {
                    const prefix = $(this).data('parent-prefix');
                    const mode = $(this).data('mode');

                    if (mode === 'new') {
                        clearParentSelect(prefix);
                        fillParentFio(prefix, {});
                        setParentFormMode(prefix, 'new', {skipButtonsUpdate: true});
                        syncParentModeButtons(prefix, 'new');
                        $(parentFieldIds(prefix).lastname).trigger('focus');
                        return;
                    }

                    fillParentFio(prefix, {});
                    setParentFormMode(prefix, 'directory', {skipButtonsUpdate: true});
                    syncParentModeButtons(prefix, 'directory');
                });

                $(document).on('change', '#create-user-form select[name="role_id"], #edit-user-form select[name="role_id"]', function () {
                    const prefix = $(this).closest('#create-user-form').length ? 'create' : 'edit';
                    window.syncStudentParentFieldsVisibility(prefix);
                });

                $(function () {
                    $('#createUserModal').on('shown.bs.modal', function () {
                        initParentSelectsInModal($(this));
                        window.syncStudentParentFieldsVisibility('create');

                        var $form = $('#create-user-form');
                        var leadPrefill = $form.data('school-lead-prefill');
                        if (leadPrefill && typeof leadPrefill === 'object') {
                            window.setStudentParentForm('create', Object.assign({}, leadPrefill, {
                                parent_id: null,
                                forceNewParent: true,
                            }));
                            $form.removeData('school-lead-prefill');
                            return;
                        }

                        window.resetStudentParentForm('create');
                    });

                    $('#editUserModal').on('shown.bs.modal', function () {
                        initParentSelectsInModal($(this));
                        window.syncStudentParentFieldsVisibility('edit');
                    });

                    $('#editLeadModal').on('shown.bs.modal', function () {
                        initParentSelectsInModal($(this));
                        window.syncStudentParentFieldsVisibility('lead');
                    });
                });
            })(jQuery);
        </script>
    @endpush
@endonce
