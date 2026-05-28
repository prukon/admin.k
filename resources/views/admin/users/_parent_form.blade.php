@php
    $prefix = $prefix ?? 'create';
    $parentSelectId = $prefix . '-parent-id';
    $parentLastnameId = $prefix . '-parent-lastname';
    $parentFirstnameId = $prefix . '-parent-firstname';
    $parentMiddlenameId = $prefix . '-parent-middlename';
    $parentFioWrapClass = 'js-parent-fio-wrap-' . $prefix;
    $parentSearchUrl = route('admin.users.parents.search');
@endphp

<div class="col-12 js-student-parent-block" data-parent-prefix="{{ $prefix }}">
    <div class="small text-muted mb-2">Данные родителя (подписант договора)</div>
</div>

<div class="col-12">
    <div class="mb-3">
        <label for="{{ $parentSelectId }}" class="form-label">Родитель в справочнике</label>
        <select name="parent_id"
                id="{{ $parentSelectId }}"
                class="form-select js-parent-profile-select"
                data-parent-prefix="{{ $prefix }}"
                data-search-url="{{ $parentSearchUrl }}">
            <option value="">Без родителя / новый по ФИО ниже</option>
        </select>
        <div class="form-text">
            Выберите существующего родителя (для следующего ребёнка укажите того же) или оставьте пустым и заполните ФИО — будет создан новый.
        </div>
    </div>
</div>

<div class="col-12 col-md-4 {{ $parentFioWrapClass }}">
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

<div class="col-12 col-md-4 {{ $parentFioWrapClass }}">
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

<div class="col-12 col-md-4 {{ $parentFioWrapClass }}">
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

@once
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
                    };
                }

                function fillParentFio(prefix, data) {
                    const ids = parentFieldIds(prefix);
                    $(ids.lastname).val(data.parent_lastname || '');
                    $(ids.firstname).val(data.parent_firstname || '');
                    $(ids.middlename).val(data.parent_middlename || '');
                }

                function initParentSelect($select) {
                    const prefix = $select.data('parent-prefix');
                    const searchUrl = $select.data('search-url');
                    const ids = parentFieldIds(prefix);

                    if ($select.data('select2')) {
                        $select.select2('destroy');
                    }

                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        allowClear: true,
                        placeholder: 'Начните вводить ФИО родителя',
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
                    });
                    $select.on('select2:clear.studentParent', function () {
                        fillParentFio(prefix, {});
                        if (!$select.find('option[value=""]').length) {
                            $select.prepend(new Option('Без родителя / новый по ФИО ниже', '', true, true));
                        } else {
                            $select.val('').trigger('change');
                        }
                    });
                }

                window.setStudentParentForm = function (prefix, payload) {
                    const ids = parentFieldIds(prefix);
                    const $select = $(ids.select);
                    const parentId = payload && payload.parent_id ? String(payload.parent_id) : '';

                    fillParentFio(prefix, payload || {});

                    if (!$select.length) {
                        return;
                    }

                    if (!parentId) {
                        $select.val(null).trigger('change');
                        return;
                    }

                    const label = [payload.parent_lastname, payload.parent_firstname, payload.parent_middlename]
                        .filter(Boolean)
                        .join(' ')
                        .trim();

                    const option = new Option(label || ('Родитель #' + parentId), parentId, true, true);
                    $select.empty().append(option).trigger('change');
                };

                window.resetStudentParentForm = function (prefix) {
                    window.setStudentParentForm(prefix, {
                        parent_id: null,
                        parent_lastname: '',
                        parent_firstname: '',
                        parent_middlename: '',
                    });
                };

                function initParentSelectsInModal($modal) {
                    $modal.find('.js-parent-profile-select').each(function () {
                        initParentSelect($(this));
                    });
                }

                $(function () {
                    $('#createUserModal').on('shown.bs.modal', function () {
                        initParentSelectsInModal($(this));
                        if (!$('#create-user-form').data('school-lead-prefill')) {
                            window.resetStudentParentForm('create');
                        }
                    });

                    $('#editUserModal').on('shown.bs.modal', function () {
                        initParentSelectsInModal($(this));
                    });
                });
            })(jQuery);
        </script>
    @endpush
@endonce
