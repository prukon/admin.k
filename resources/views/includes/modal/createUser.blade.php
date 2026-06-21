<!-- Модальное окно для создания пользователя -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createUserModalLabel">Создание пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="create-user-form" class="text-start" method="post" action="{{ route('admin.user.store') }}">
                @csrf
                <input type="hidden" name="school_lead_id" id="create-school-lead-id" value="">
                <!-- Поля для ввода данных пользователя -->
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create-name" class="form-label">Имя ученика*</label>
                                <input type="text" name="name" class="form-control" id="create-name">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create-lastname" class="form-label">Фамилия ученика*</label>
                                <input type="text"
                                       name="lastname"
                                       class="form-control"
                                       id="create-lastname"
                                       value="{{ old('lastname') }}">
                            </div>
                        </div>

                        @include('admin.users._parent_form', [
                            'prefix' => 'create',
                            'parentLastname' => old('parent_lastname'),
                            'parentFirstname' => old('parent_firstname'),
                            'parentMiddlename' => old('parent_middlename'),
                        ])

                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create-birthday" class="form-label">Дата рождения</label>
                                <input type="date" name="birthday" class="form-control" id="create-birthday">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            @include('admin.users._student_teams_multiselect', [
                                'teamsFieldId' => 'createStudentTeamIds',
                                'teamOptions' => $allTeams,
                                'canEditTeams' => true,
                            ])
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create-email" class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" id="create-email">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            @php $canPhone = auth()->user()->can('users.phone.update'); @endphp
                            <div class="mb-3">
                                <label for="create-phone" class="form-label">Телефон</label>

                                @include('includes.fields.phone-input', [
                                    'name' => 'phone',
                                    'id' => 'create-phone',
                                    'disabled' => !$canPhone,
                                    'attributes' => [
                                        'data-can-phone' => $canPhone ? 1 : 0,
                                    ],
                                ])

                                @unless($canPhone)
                                    <div class="form-text text-muted mt-1">
                                        <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение телефона
                                    </div>
                                @endunless
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="mb-3 wrap-cur-password">
                                <label for="create-password" class="form-label">Пароль</label>
                                <div class="position-relative">
                                    <input type="password"
                                           id="create-password"
                                           name="password"
                                           class="form-control"
                                           placeholder="Пароль">
                                    <span toggle="#create-password" class="fa fa-fw fa-eye field-icon toggle-password"></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="create-is_enabled" class="form-label">Активность</label>
                                <select name="is_enabled" class="form-select" id="create-is_enabled">
                                    <option value="1">Да</option>
                                    <option value="0">Нет</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            @if(!empty($lockStudentRole) && !empty($studentRoleId))
                                <input type="hidden" name="role_id" value="{{ (int) $studentRoleId }}">
                            @else
                            <div class="mb-3">
                                <label for="create_role_id" class="form-label">Роль</label>
                                <select name="role_id" class="form-select" id="create_role_id">
                                    @foreach($roles as $role)
                                        @continue($role->name === 'superadmin')
                                        <option value="{{ $role->id }}">{{ $role->label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                        </div>

                        @include('includes.modal._student_health_fields', ['prefix' => 'create'])
                        @include('includes.modal._student_comment_sex_fields', [
                            'prefix' => 'create',
                            'canViewUserSex' => $canViewUserSex ?? null,
                            'canViewUserComment' => $canViewUserComment ?? null,
                        ])
                    </div>

                    @if(!empty($userFieldsPayload))
                        <div id="create-custom-fields-container" class="border-top pt-3 mt-2">
                            <div class="small text-muted mb-2">Дополнительные поля</div>
                            @foreach($userFieldsPayload as $field)
                                <div class="mb-3 custom-field" data-slug="{{ $field['slug'] }}">
                                    <label for="create-custom-{{ $field['slug'] }}" class="form-label">{{ $field['name'] }}</label>
                                    <input
                                        type="text"
                                        name="custom[{{ $field['slug'] }}]"
                                        class="form-control"
                                        id="create-custom-{{ $field['slug'] }}"
                                        value=""
                                        @unless(!empty($field['editable'])) disabled @endunless
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="modal-footer-modal-user">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-primary">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Функция показа/скрытия пароля
        function showCurPassword() {
            const togglePassword = document.querySelector('.wrap-cur-password .toggle-password');
            const passwordInput = document.querySelector('.wrap-cur-password #create-password');

            togglePassword.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        showCurPassword();

        function createStudentRoleId() {
            const studentRoleId = $('.js-student-parent-fields[data-parent-prefix="create"]').data('student-role-id');
            return studentRoleId ? parseInt(studentRoleId, 10) : null;
        }

        function boolToTriState(value) {
            if (value === true || value === 1 || value === '1') {
                return '1';
            }
            if (value === false || value === 0 || value === '0') {
                return '0';
            }
            return '';
        }

        function setCreateUserHealthFields(values) {
            $('#create-is_individual_traits').val(boolToTriState(values.is_individual_traits));
            $('#create-is_on_medical_register').val(boolToTriState(values.is_on_medical_register));
            $('#create-is_with_disability').val(boolToTriState(values.is_with_disability));
        }

        function resetCreateUserHealthFields() {
            setCreateUserHealthFields({
                is_individual_traits: '',
                is_on_medical_register: '',
                is_with_disability: '',
            });
        }

        function syncCreateUserHealthFields(roleId) {
            const $wrap = $('#create-user-form .js-user-health-wrap');
            if (!$wrap.length) {
                return;
            }

            const studentRoleId = createStudentRoleId();
            const isStudent = studentRoleId && parseInt(roleId, 10) === studentRoleId;

            $wrap.toggleClass('d-none', !isStudent);
            $wrap.find('.js-user-health-field').prop('disabled', !isStudent);
        }

        function setCreateUserCommentSexFields(values) {
            if ($('#create-sex').length) {
                $('#create-sex').val(values.sex || '');
            }
            if ($('#create-comment').length) {
                $('#create-comment').val(values.comment || '');
            }
        }

        function resetCreateUserCommentSexFields() {
            setCreateUserCommentSexFields({ sex: '', comment: '' });
        }

        function syncCreateUserCommentSexFields(roleId) {
            const $wrap = $('#create-user-form .js-user-comment-sex-wrap');
            if (!$wrap.length) {
                return;
            }

            const studentRoleId = createStudentRoleId();
            const isStudent = studentRoleId && parseInt(roleId, 10) === studentRoleId;

            $wrap.toggleClass('d-none', !isStudent);
            $wrap.find('.js-user-comment-sex-field').prop('disabled', !isStudent);
        }

        window.resetCreateUserCommentSexFields = resetCreateUserCommentSexFields;
        window.setCreateUserHealthFieldsFromLead = setCreateUserHealthFields;
        window.resetCreateUserHealthFields = resetCreateUserHealthFields;
        window.syncCreateUserHealthFields = syncCreateUserHealthFields;
        window.syncCreateUserCommentSexFields = syncCreateUserCommentSexFields;

        function currentCreateRoleId() {
            const $roleSelect = $('#create_role_id');
            if ($roleSelect.length) {
                return $roleSelect.val();
            }

            const hiddenRoleId = $('#create-user-form').find('input[name="role_id"]').val();
            return hiddenRoleId || null;
        }

        const $createUserFormRoot = $('#create-user-form');

        $createUserFormRoot.on('change', '#create_role_id', function () {
            const roleId = $(this).val();
            syncCreateUserHealthFields(roleId);
            syncCreateUserCommentSexFields(roleId);
        });

        syncCreateUserHealthFields(currentCreateRoleId());
        syncCreateUserCommentSexFields(currentCreateRoleId());

        $createUserFormRoot.on('submit', function (e) {
            e.preventDefault();

            const $form = $(this); // ← вот его и не хватало

            // Стираем прошлые ошибки
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').remove();

            $.ajax({
                url: $form.attr('action'),
                method: $form.attr('method'),
                data: $form.serialize(),
                headers: { 'Accept': 'application/json' }, // чтобы 422 пришёл JSON'ом
                success: function (response) {
                    if ($form.data('success-handler') === 'school-leads-table') {
                        var modalEl = document.getElementById('createUserModal');
                        var modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                        if (typeof window.onSchoolLeadUserCreated === 'function') {
                            window.onSchoolLeadUserCreated(response);
                        }
                        return;
                    }

                    showSuccessModal("Создание пользователя", "Пользователь успешно создан.", 1);
                    window.location.reload();
                },
                error: function (xhr) {
                    // Валидация Laravel
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;

                        Object.keys(errors).forEach(function (field) {
                            const messages = errors[field];

                            let $input = $();
                            const customMatch = /^custom\.(.+)$/.exec(field);
                            if (customMatch) {
                                const slug = customMatch[1];
                                $input = $form.find('[name="custom[' + slug + ']"]');
                            }
                            if (!$input.length && (field === 'team_ids' || field.startsWith('team_ids.'))) {
                                $input = $form.find('#createStudentTeamIds');
                                const $teamError = $form.find('[data-error-for="team_ids"]');
                                if ($teamError.length) {
                                    $teamError.text(messages[0]);
                                }
                                if (window.KidsCrmGenericMultiselectSelect2 && $input.length) {
                                    KidsCrmGenericMultiselectSelect2.markInvalid($input);
                                }
                            }
                            if (!$input.length) {
                                const safe = field.replace(/\./g, '\\.').replace(/\*/g, '\\*');
                                $input = $form.find('[name="' + safe + '"]');
                            }
                            if (!$input.length) {
                                $input = $form.find('#' + field.replace(/\./g, '\\.'));
                            }

                            if ($input.length) {
                                $input.addClass('is-invalid');
                                if ($input.hasClass('js-generic-multiselect-select')) {
                                    const $teamError = $form.find('[data-error-for="team_ids"]');
                                    if ($teamError.length) {
                                        $teamError.text(messages[0]);
                                    }
                                } else {
                                    $('<div class="invalid-feedback d-block"></div>')
                                        .text(messages[0])
                                        .insertAfter($input);
                                }
                            } else if (field === 'team_ids' || field.startsWith('team_ids.')) {
                                const $teamError = $form.find('[data-error-for="team_ids"]');
                                if ($teamError.length) {
                                    $teamError.text(messages[0]);
                                }
                            }
                        });

                        // Фокус на первое неверное поле
                        $form.find('.is-invalid').first().trigger('focus');
                        return;
                    }

                    // Прочие ошибки
                    const msg = xhr.responseJSON?.message || 'Что-то пошло не так. Попробуйте ещё раз.';
                    if (typeof showErrorModal === 'function') {
                        showErrorModal('Ошибка', msg, 1);
                    } else {
                        alert(msg);
                    }
                }
            });
        });


    });
</script>
