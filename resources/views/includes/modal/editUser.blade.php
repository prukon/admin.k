{{--Cropie--}}

<!-- Модальное окно редактирования пользователя -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog ">
        <div class="modal-content background-color-grey">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Редактирование пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="edit-user-form" class="text-start" method="post">
                    @csrf
                    @method('patch')
                    <div class="mb-3 d-flex flex-column align-items-center">

                        <!-- Блок для аватарки -->
                        <div class="mb-3 d-flex flex-column align-items-center">
                            <div>
                                <div class="avatar_wrapper">
                                    <div class="avatar">
                                        <!-- ВНЕШНИЙ контейнер (hover + меню) -->
                                        <div class="avatar-clip">
                                            <!-- ВНУТРЕННИЙ круг (обрезка фото + бордер) -->
                                            <img
                                                    src="{{ auth()->user()->image_crop ? asset('storage/avatars/'.auth()->user()->image_crop) : asset('/img/default-avatar.png') }}"
                                                    alt="Avatar">
                                        </div>

                                        <div class="avatar-actions">
                                            <button class="dropdown-item js-open-photo" type="button">
                                                <i class="fa-solid fa-image"></i> Открыть фото
                                            </button>
                                            <button class="dropdown-item js-change-photo" type="button"
                                                    data-bs-toggle="modal" data-bs-target="#avatarEditModal">
                                                <i class="fa-solid fa-pen-to-square"></i> Изменить фото
                                            </button>
                                            <button class="dropdown-item text-danger js-delete-photo" type="button">
                                                <i class="fa-solid fa-trash"></i> Удалить фото
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                @include('includes.modal.editAvatar')
                            </div>
                        </div>

                    </div>


                    @include('includes.modal._user_modal_accordion_support')

                    <!-- ДВЕ КОЛОНКИ -->
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="accordion accordion-flush user-modal-accordion js-user-student-accordion"
                                 id="editUserStudentAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="editUserStudentHeading">
                                        <button class="accordion-button collapsed js-user-student-accordion-btn"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#editUserStudentCollapse"
                                                aria-expanded="false"
                                                aria-controls="editUserStudentCollapse">
                                            Ученик
                                        </button>
                                    </h2>
                                    <div id="editUserStudentCollapse"
                                         class="accordion-collapse collapse js-user-student-accordion-panel"
                                         aria-labelledby="editUserStudentHeading"
                                         data-bs-parent="#editUserStudentAccordion">
                                        <div class="accordion-body pt-2">
                                            <div class="row g-3">
                                                <div class="col-12 col-md-6">
                                                    <div class="mb-3">
                                                        <label for="edit-name" class="form-label">Имя ученика*</label>
                                                        <input type="text"
                                                               name="name"
                                                               class="form-control"
                                                               id="edit-name"
                                                               @cannot('users.name.update') disabled aria-disabled="true" @endcannot>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-6">
                                                    <div class="mb-3">
                                                        <label for="edit-lastname" class="form-label">Фамилия ученика*</label>
                                                        <input type="text"
                                                               name="lastname"
                                                               class="form-control"
                                                               id="edit-lastname"
                                                               @cannot('users.name.update') disabled aria-disabled="true" @endcannot>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-6">
                                                    <div class="mb-3">
                                                        <label for="edit-birthday" class="form-label">Дата рождения</label>
                                                        <input
                                                                type="date"
                                                                name="birthday"
                                                                id="edit-birthday"
                                                                class="form-control"
                                                                @cannot('users.birthdate.update') disabled aria-disabled="true" @endcannot
                                                        >
                                                        @cannot('users.birthdate.update')
                                                            <div class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i>Нет прав на
                                                                изменение
                                                                даты рождения
                                                            </div>
                                                        @endcannot
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-6 js-user-student-team-wrap">
                                                    @include('admin.users._student_teams_multiselect', [
                                                        'teamsFieldId' => 'editStudentTeamIds',
                                                        'teamOptions' => $allTeams,
                                                        'canEditTeams' => auth()->user()->can('users.group.update'),
                                                    ])
                                                </div>

                                                <div class="col-12 col-md-6">
                                                    <div class="mb-3">
                                                        <label for="edit-email" class="form-label">Email</label>
                                                        <input
                                                                type="email"
                                                                id="edit-email"
                                                                name="email"
                                                                class="form-control"
                                                                @cannot('users.email.update') disabled aria-disabled="true" @endcannot
                                                        >
                                                        @cannot('users.email.update')
                                                            <div class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i>Нет прав на
                                                                изменение
                                                                email
                                                            </div>
                                                        @endcannot
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-6">
                                                    @php $canPhone = auth()->user()->can('users.phone.update'); @endphp
                                                    <div class="mb-3">
                                                        <label for="edit-phone" class="form-label">Телефон</label>

                                                        <div class="input-group">
                                                            @include('includes.fields.phone-input', [
                                                                'name' => 'phone',
                                                                'id' => 'edit-phone',
                                                                'value' => old('phone', $user->phone),
                                                                'disabled' => !$canPhone,
                                                                'attributes' => [
                                                                    'data-can-phone' => $canPhone ? 1 : 0,
                                                                ],
                                                            ])

                                                            <span id="phone-verify-icon" class="input-group-text d-none">
                                                                <i class="fa-solid fa-circle-check"></i>
                                                            </span>
                                                        </div>

                                                        @php
                                                            $verifiedAt = $user->phone_verified_at ? \Carbon\Carbon::parse($user->phone_verified_at) : null;
                                                        @endphp
                                                        <small
                                                                id="phone-verify-status"
                                                                class="small {{ $verifiedAt ? 'text-success' : 'd-none' }}"
                                                                data-verified-at="{{ $verifiedAt ? $verifiedAt->format('Y-m-d H:i:s') : '' }}"
                                                        >
                                                            @if($verifiedAt)
                                                                Подтверждён {{ $verifiedAt->format('d.m.Y H:i') }}
                                                            @endif
                                                        </small>

                                                        @unless($canPhone)
                                                            <div class="form-text text-muted mt-1">
                                                                <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение телефона
                                                            </div>
                                                        @endunless
                                                    </div>
                                                </div>

                                                @include('includes.modal._student_health_fields', [
                                                    'variant' => 'checkbox',
                                                ])
                                                @include('includes.modal._student_comment_sex_fields', [
                                                    'only' => 'sex',
                                                    'canViewUserSex' => $canViewUserSex ?? null,
                                                    'canViewUserComment' => $canViewUserComment ?? null,
                                                ])
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @can('trainers.view')
                        <div class="col-12 col-md-6 js-user-trainer-teams-wrap d-none">
                            @include('admin.trainers._teams_checkboxes', [
                                'teamsFieldIdPrefix' => 'edit-user-trainer',
                                'teamOptions' => $allTeams,
                                'teamsLabel' => 'Группы тренера',
                            ])
                        </div>
                        @endcan

                        @include('admin.users._parent_form', ['prefix' => 'edit'])

                        @include('includes.modal._student_comment_sex_fields', [
                            'only' => 'comment',
                            'canViewUserSex' => $canViewUserSex ?? null,
                            'canViewUserComment' => $canViewUserComment ?? null,
                        ])

                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="edit-activity" class="form-label">Активность</label>
                                <select
                                        id="edit-activity"
                                        name="is_enabled"
                                        class="form-select"
                                        @cannot('users.activity.update') disabled aria-disabled="true" @endcannot
                                >
                                    <option value="0">Неактивен</option>
                                    <option value="1">Активен</option>
                                </select>
                                @cannot('users.activity.update')
                                    <div class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i>Нет прав на
                                        изменение
                                        активности
                                    </div>
                                @endcannot
                            </div>
                        </div>

                        <!-- НОВОЕ ПОЛЕ: Договор -->
                        {{--<div class="col-12">--}}
                        {{--<label class="form-label d-block">Договор</label>--}}

                        {{--<!-- реальный контрол (select или hidden) -->--}}
                        {{--<select id="contract_status" name="contract_status" class="form-select d-none">--}}
                        {{--<option value="signed"    {{ $user->contract_status === 'signed' ? 'selected' : '' }}>подписан</option>--}}
                        {{--<option value="unsigned"  {{ $user->contract_status === 'unsigned' ? 'selected' : '' }}>не подписан</option>--}}
                        {{--</select>--}}

                        {{--<!-- визуальный индикатор -->--}}
                        {{--<div id="contract-indicator" class="d-inline-flex align-items-center gap-2 small fw-medium">--}}
                        {{--<i class="fa-regular fa-circle" aria-hidden="true"></i>--}}
                        {{--<span>—</span>--}}
                        {{--<button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="toggle-contract">--}}
                        {{--Переключить статус--}}
                        {{--</button>--}}
                        {{--</div>--}}
                        {{--<div class="form-text">Иконка + текст меняются в зависимости от статуса.</div>--}}
                        {{--</div>--}}



                        {{--Пользовательские поля--}}
                        <div class="col-12 col-md-6">
                            @if(!empty($userFieldsPayload))
                                <!-- Проверяем, есть ли пользовательские поля -->
                                <div class="mb-3">
                                    <div id="custom-fields-container"> <!-- Контейнер для пользовательских полей -->


                                    </div>
                                </div>
                            @endif

                            <!-- Поле "Email" -->
                        </div>

                        <!-- Блок изменения пароля -->
                        <div class="buttons-wrap change-pass-wrap" id="change-pass-wrap" style="display: none;">
                            <div class="d-flex align-items-center mt-3">
                                <div class="position-relative wrap-change-password">
                                    <input type="password" id="new-password" class="form-control"
                                           placeholder="Новый пароль">
                                    <span toggle="#new-password"
                                          class="fa fa-fw fa-eye field-icon toggle-password"></span>
                                </div>
                                <button type="button" id="apply-password-btn" class="btn btn-primary ml-2">Применить
                                </button>
                                <button type="button" id="cancel-change-password-btn" class="btn btn-danger ml-2">Отмена
                                </button>
                            </div>
                            <div id="error-message" class="text-danger mt-2" style="display:none;">Пароль должен быть не
                                менее 8 символов
                            </div>
                        </div>

                        @php $canChange = auth()->user()->can('users.password.update'); @endphp

                        <div class="button-group buttons-wrap mt-3">
                            <button type="button"
                                    id="change-password-btn"
                                    class="btn btn-primary mt-3 change-password-btn {{ $canChange ? '' : 'opacity-50 pe-none' }}"
                                    @unless($canChange)
                                        aria-disabled="true"
                                    tabindex="-1"
                                    data-kids-tooltip-hint
                                    data-bs-toggle="tooltip"
                                    title="Нет прав на изменение пароля"
                                    @endunless
                            >
                                <i class="fa-solid fa-key me-1"></i> Изменить пароль
                            </button>

                            @unless($canChange)
                                <div class="form-text text-muted mt-2">
                                    <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение пароля
                                </div>
                            @endunless

                            <button type="button"
                                    id="send-welcome-credentials-btn"
                                    class="btn btn-outline-primary mt-3 d-none"
                                    data-kids-tooltip-hint
                                    data-bs-toggle="tooltip"
                                    title="Сгенерировать новый пароль и отправить его на email ученика">
                                <i class="fa-solid fa-envelope me-1"></i> Отправить новый пароль по почте
                            </button>

                            <!-- Кнопка для сохранения данных -->
                            <button type="submit" class="btn btn-primary mt-3 save-change-modal">Сохранить изменения
                            </button>
                            <!-- Кнопка для сохранения данных -->

                            {{--<button type="submit" class="btn btn-danger mt-3 save-change-modal">Удалить</button>--}}
                            <button type="button" id="delete-user-btn" class="btn btn-danger mt-3 confirm-delete-modal">
                                Удалить
                            </button>
                        </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
    // Отрисовка индикатора по значению select
    function renderContractIndicator() {
        var val = $('#contract_status').val();        // signed | unsigned
        var $ind = $('#contract-indicator');
        var $ico = $ind.find('i');
        var $txt = $ind.find('span');

        if (val === 'signed') {
            $ico.attr('class', 'fa-solid fa-circle-check text-success');
            $txt.text('Подписан');
        } else {
            $ico.attr('class', 'fa-solid fa-circle-xmark text-danger');
            $txt.text('Не подписан');
        }
    }

    $(document).on('shown.bs.modal', '#editUserModal', function () {
        renderContractIndicator();
    });

    // Кнопка переключения (можно убрать и управлять только сервером)
    $('#toggle-contract').on('click', function () {
        var val = $('#contract_status').val() === 'signed' ? 'unsigned' : 'signed';
        $('#contract_status').val(val);
        renderContractIndicator();
    });
</script>


<script>

    $(document).ready(function () {

        const hasTrainerTeamsUi = $('.js-user-trainer-teams-wrap').length > 0;
        let editUserRolesCache = [];

        function trainerRoleIdFromRoles(roles) {
            const found = (roles || []).find(function (r) {
                return r.name === 'trainer';
            });
            return found ? parseInt(found.id, 10) : null;
        }

        function studentRoleIdFromRoles(roles) {
            const found = (roles || []).find(function (r) {
                return r.name === 'user';
            });
            return found ? parseInt(found.id, 10) : null;
        }

        function isHealthCheckboxChecked(value) {
            return value === true || value === 1 || value === '1';
        }

        function setEditUserHealthFields(user) {
            $('#edit-is_individual_traits').prop('checked', isHealthCheckboxChecked(user.is_individual_traits));
            $('#edit-is_on_medical_register').prop('checked', isHealthCheckboxChecked(user.is_on_medical_register));
            $('#edit-is_with_disability').prop('checked', isHealthCheckboxChecked(user.is_with_disability));
        }

        function syncEditUserHealthFields(roleId, roles) {
            const $wrap = $('.js-user-health-wrap');
            if (!$wrap.length) {
                return;
            }

            const studentRoleId = studentRoleIdFromRoles(roles);
            const isStudent = studentRoleId && parseInt(roleId, 10) === studentRoleId;

            $wrap.toggleClass('d-none', !isStudent);
            $wrap.find('.js-user-health-field').prop('disabled', !isStudent);
        }

        function setEditUserCommentSexFields(user) {
            if ($('#edit-sex').length) {
                $('#edit-sex').val(user.sex || '');
            }
            if ($('#edit-comment').length) {
                $('#edit-comment').val(user.comment || '');
            }
        }

        function applyEditUserCommentSexPermissions(ui) {
            const canSex = ui?.canViewUserSex === true;
            const canComment = ui?.canViewUserComment === true;

            if (!canSex) {
                $('.js-user-sex-wrap').remove();
            }

            if (!canComment) {
                $('.js-user-comment-wrap').remove();
            }
        }

        function syncEditUserCommentSexFields(roleId, roles) {
            const studentRoleId = studentRoleIdFromRoles(roles);
            const isStudent = studentRoleId && parseInt(roleId, 10) === studentRoleId;

            $('#edit-user-form').find('.js-user-sex-wrap, .js-user-comment-wrap').each(function () {
                $(this).toggleClass('d-none', !isStudent);
                $(this).find('.js-user-comment-sex-field').prop('disabled', !isStudent);
            });
        }

        function setEditUserStudentTeamIds(teamIds) {
            const $select = $('#editStudentTeamIds');
            if (!$select.length || !window.KidsCrmGenericMultiselectSelect2) {
                return;
            }
            KidsCrmGenericMultiselectSelect2.setValues($select, teamIds || []);
        }

        function setEditUserTrainerTeamIds(teamIds) {
            const ids = (teamIds || []).map(function (id) {
                return parseInt(id, 10);
            });
            $('#edit-user-form input[name="team_ids[]"]').each(function () {
                $(this).prop('checked', ids.includes(parseInt($(this).val(), 10)));
            });
        }

        function setEditUserRoleContext(response) {
            const $form = $('#edit-user-form');

            if (response.targetIsSuperadmin) {
                editUserRolesCache = [];
            } else {
                editUserRolesCache = response.roles || [];
            }

            $form.data('role-id', response.user.role_id);
        }

        function isEditUserStudent(user) {
            if (user && user.role && user.role.name === 'user') {
                return true;
            }

            var roleId = user && user.role_id
                ? String(user.role_id)
                : String($('#edit-user-form').data('role-id') || '');

            var role = (editUserRolesCache || []).find(function (item) {
                return String(item.id) === roleId;
            });

            return !!(role && role.name === 'user');
        }

        function syncSendWelcomeCredentialsBtn(user) {
            var $btn = $('#send-welcome-credentials-btn');
            if (!$btn.length) {
                return;
            }

            var email = user && user.email !== undefined
                ? String(user.email || '').trim()
                : String($('#edit-user-form #edit-email').val() || '').trim();
            var visible = isEditUserStudent(user || {}) && email !== '';

            $btn.toggleClass('d-none', !visible).prop('disabled', false);
        }

        function syncEditUserTeamFields(roleId, roles, trainerTeamIds, studentTeamIds) {
            const trainerRoleId = trainerRoleIdFromRoles(roles);
            const isTrainer = trainerRoleId && parseInt(roleId, 10) === trainerRoleId;
            const $studentWrap = $('.js-user-student-team-wrap');
            const $studentSelect = $('#editStudentTeamIds');
            const $trainerWrap = $('.js-user-trainer-teams-wrap');
            const teamLocked = String($studentSelect.data('team-locked')) === '1'
                || $studentSelect.prop('disabled');

            if (isTrainer) {
                $studentWrap.addClass('d-none');
                if ($studentSelect.length) {
                    $studentSelect.prop('disabled', true);
                    if (window.KidsCrmGenericMultiselectSelect2) {
                        KidsCrmGenericMultiselectSelect2.setValues($studentSelect, []);
                    }
                }
                if (hasTrainerTeamsUi) {
                    $trainerWrap.removeClass('d-none');
                    $trainerWrap.find('input[name="team_ids[]"]').prop('disabled', false);
                    if (trainerTeamIds !== undefined && trainerTeamIds !== null) {
                        setEditUserTrainerTeamIds(trainerTeamIds);
                    }
                }
            } else {
                $studentWrap.removeClass('d-none');
                if ($studentSelect.length) {
                    $studentSelect.prop('disabled', teamLocked);
                    if (studentTeamIds !== undefined && studentTeamIds !== null) {
                        setEditUserStudentTeamIds(studentTeamIds);
                    }
                }
                if (hasTrainerTeamsUi) {
                    $trainerWrap.addClass('d-none');
                    $trainerWrap.find('input[name="team_ids[]"]').prop('disabled', true).prop('checked', false);
                }
            }

            syncEditUserHealthFields(roleId, roles);
            syncEditUserCommentSexFields(roleId, roles);

            const studentRoleId = studentRoleIdFromRoles(roles);
            const isStudent = studentRoleId && parseInt(roleId, 10) === studentRoleId;

            if (typeof window.syncUserStudentAccordionMode === 'function') {
                window.syncUserStudentAccordionMode('edit', isStudent);
            }

            if (typeof window.syncStudentParentFieldsVisibility === 'function') {
                window.syncStudentParentFieldsVisibility('edit');
            }
        }

        function applyPhoneUI(user){
            const $phone    = $('#edit-phone');
            const $iconWrap = $('#phone-verify-icon');
            const $icon     = $iconWrap.find('i');
            const $status   = $('#phone-verify-status');
            const canPhone  = String($phone.data('can-phone')) === '1';

            $('#phone-edit-locked-note').remove();
            $iconWrap.addClass('d-none'); $icon.attr('class','');
            if ($status.length){ $status.addClass('d-none').text(''); }

            // База по правам админа
            $phone.prop('disabled', !canPhone)
                .attr('aria-disabled', !canPhone ? 'true' : null);

            // Применение правил для текущего пользователя
            const verifiedAt = user.phone_verified_at ? user.phone_verified_at : null;

            if (verifiedAt) {
                // Подтвержден → всегда locked
                $phone.prop('disabled', true).attr('aria-disabled','true');
                $icon.attr('class','fa-solid fa-circle-check text-success');
                $iconWrap.removeClass('d-none');
                if ($status.length){
                    if (!$status.text().trim()) $status.text('Подтверждён');
                    $status.removeClass('d-none');
                }
                $('<div/>', {
                    id: 'phone-edit-locked-note',
                    class: 'form-text text-success mt-1'
                }).html('<i class="fa-solid fa-lock me-1"></i>Редактирование номера запрещено после подтверждения.')
                    .insertAfter($('#phone-verify-status'));
            } else {
                // Не подтвержден → редактировать можно только если есть право
                if (canPhone) $phone.prop('disabled', false).removeAttr('aria-disabled');
                $icon.attr('class','fa-solid fa-circle-xmark text-danger');
                $iconWrap.removeClass('d-none');
            }
        }


        // Функция редактирования пользователя
        function editMidalUser() {

            // Функция для показа/скрытия пароля с помощью иконки глаза  fix
            function showPassword() {
                const togglePassword = document.querySelector('.wrap-change-password .toggle-password');
                const passwordInput = document.querySelector('.wrap-change-password #new-password');

                togglePassword.addEventListener('click', function () {
                    // Переключаем тип input между 'password' и 'text'
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    // Меняем иконку глаза
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }

            showPassword();


            // Показать/скрыть изменение пароля
            $('#change-password-btn').on('click', function () {
                $('#change-password-btn').hide();
                $('#change-pass-wrap').show();
            });

            // Применение нового пароля
            $('#apply-password-btn').on('click', function () {
                var userId = $('#edit-user-form').attr('action').split('/').pop();
                console.log('Применение нового пароля для пользователя с ID:', userId);
                var newPassword = $('#new-password').val();
                var token = $('input[name="_token"]').val();
                var $passwordError = $('#error-message');

                // Проверка длины пароля
                if (newPassword.length < 8) {
                    $passwordError.show();
                    return;
                }
                $passwordError.hide();

                $.ajax({
                    url: `/admin/user/${userId}/update-password`,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token
                    },
                    data: {
                        password: newPassword
                    },
                    success: function (response) {
                        console.log('Ответ сервера на обновление пароля:', response);
                        if (response.success) {
                            $('#change-password-btn').show();
                            $('#change-pass-wrap').hide();
                            $('#password-change-message').show().delay(3000).fadeOut();
                            showSuccessModal("Обновление пароля", "Пароль успешно обновлен.");
                        }
                    },
                    error: function (response) {
                        if (typeof eroorRespone === 'function') {
                            eroorRespone(response);
                        } else {
                            let msg = 'Произошла ошибка при сохранении данных.';
                            if (response.responseJSON?.errors) {
                                msg = Object.values(response.responseJSON.errors).flat().join('\n');
                            } else if (response.responseJSON?.message) {
                                msg = response.responseJSON.message;
                            }
                            $('#error-modal-message').text(msg).show();
                            $('#errorModal').modal('show');
                        }
                    }
                });
            });

            // Отмена изменения пароля
            $('#cancel-change-password-btn').on('click', function () {
                $('#change-password-btn').show();
                $('#change-pass-wrap').hide();
                $('#error-message').hide();
            });

            function sendWelcomeCredentialsRequest($btn) {
                var userId = ($('#edit-user-form').attr('action') || '').split('/').pop();

                $btn.prop('disabled', true);

                $.ajax({
                    url: '/admin/users/' + userId + '/send-welcome-credentials',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('input[name="_token"]').val(),
                        'Accept': 'application/json',
                    },
                    success: function (response) {
                        var msg = response.message || 'Пароль отправлен.';
                        if (typeof showSuccessModal === 'function') {
                            showSuccessModal('Отправка пароля', msg);
                        } else if (typeof showToast === 'function') {
                            showToast(msg, 'success');
                        } else {
                            alert(msg);
                        }
                    },
                    error: function (xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : 'Не удалось отправить письмо.';
                        if (typeof showErrorModal === 'function') {
                            showErrorModal('Отправка пароля', msg);
                        } else if (typeof eroorRespone === 'function') {
                            eroorRespone(xhr);
                        } else if (typeof showToast === 'function') {
                            showToast(msg, 'error');
                        } else {
                            $('#error-modal-message').text(msg).show();
                            $('#errorModal').modal('show');
                        }
                    },
                    complete: function () {
                        syncSendWelcomeCredentialsBtn({
                            role_id: $('#edit-user-form').data('role-id'),
                            email: $('#edit-user-form #edit-email').val(),
                        });
                    },
                });
            }

            $(document).off('click.sendWelcomeCredentials').on('click.sendWelcomeCredentials', '#send-welcome-credentials-btn', function () {
                var email = String($('#edit-user-form #edit-email').val() || '').trim();
                var userId = ($('#edit-user-form').attr('action') || '').split('/').pop();
                var $btn = $(this);

                if (!userId || !email) {
                    return;
                }

                var confirmMessage = 'Будет сгенерирован новый пароль и отправлен на ' + email + '.\n'
                    + 'Старый пароль перестанет работать. Продолжить?';

                if (typeof showConfirmDeleteModal === 'function') {
                    showConfirmDeleteModal(
                        'Отправка пароля по почте',
                        confirmMessage,
                        function () {
                            sendWelcomeCredentialsRequest($btn);
                        }
                    );
                    return;
                }

                if (window.confirm(confirmMessage)) {
                    sendWelcomeCredentialsRequest($btn);
                }
            });

            $('#edit-user-form').off('input.sendWelcomeCredentials').on('input.sendWelcomeCredentials', '#edit-email', function () {
                syncSendWelcomeCredentialsBtn({
                    role_id: $('#edit-user-form').data('role-id'),
                    email: $(this).val(),
                });
            });
        }

        // ОКРЫТЫТЬ МОДАЛКУ ЮЗЕРА и загружаем его данные для редактирования UserController edit
        function editUserLink2() {
            $('.edit-user-link').on('click', function () {
                let userId = $(this).data('id'); // Получаем ID пользователя
                console.log('Открываем модалку для редактирования пользователя с ID:', userId);

                // AJAX-запрос для получения данных пользователя
                $.ajax({
                    url: `/admin/users/${userId}/edit`,
                    method: 'GET',

                    success: function (response) {
                        const current = response.currentUser;
                        const isSuperadmin = current.isSuperadmin;
                        console.log('\\Log full response =', response);

                        // 1) Заполняем стандартные поля
                        $('#edit-user-form #edit-name').val(response.user.name);
                        $('#edit-user-form #edit-lastname').val(response.user.lastname);
                        if (typeof window.setStudentParentForm === 'function') {
                            window.setStudentParentForm('edit', {
                                parent_id: response.user.parent_id,
                                parent_lastname: response.user.parent_lastname,
                                parent_firstname: response.user.parent_firstname,
                                parent_middlename: response.user.parent_middlename,
                                parent_passport: response.user.parent_passport,
                                parent_passport_issued: response.user.parent_passport_issued,
                                parent_address: response.user.parent_address,
                                parent_phone: response.user.parent_phone,
                                parent_email: response.user.parent_email,
                            });
                        }
                        $('#edit-user-form #edit-birthday').val(response.user.birthday);
                        $('#edit-user-form #edit-email').val(response.user.email);
                        window.PhoneInputMask?.setValue('#edit-user-form #edit-phone', response.user.phone);
                        $('#edit-user-form #edit-activity').val(response.user.is_enabled);

                        // 2) Контекст роли (без смены роли в модалке)
                        setEditUserRoleContext(response);
                        syncEditUserTeamFields(
                            response.user.role_id,
                            editUserRolesCache,
                            response.user.trainer_team_ids || [],
                            response.user.team_ids || []
                        );
                        setEditUserHealthFields(response.user);
                        syncEditUserHealthFields(response.user.role_id, editUserRolesCache);
                        applyEditUserCommentSexPermissions(response.ui || {});
                        setEditUserCommentSexFields(response.user);
                        syncEditUserCommentSexFields(response.user.role_id, editUserRolesCache);
                        syncSendWelcomeCredentialsBtn(response.user);

                        // 3) Устанавливаем action формы
                        $('#edit-user-form').attr('action', `/admin/users/${response.user.id}`);

                        if (response.user && response.user.image_crop) {
                            $('.avatar-clip img').attr('src', "{{ asset('storage/avatars') }}/" + response.user.image_crop);
                        } else {
                            $('.avatar-clip img').attr('src', "{{ asset('img/default-avatar.png') }}");
                        }

                        // большая аватарка (для модалки просмотра)
                        setZoomImageFromUser(response.user);
                        //Вставка data-id в кнопку добавления аватарки (для добавления чужих аватаров)
                        setSelectedUserContext(response.user);
                        // Вставим data-id в кнопку удаления аватарки (для удаления чужих аватаров)
                        $('.js-delete-photo').attr('data-id', response.user.id);
                        //отключение кнопки "открыть фото" где нет фото
                        setOpenPhotoVisibilityByUser(response.user);

                        // 5) Заполняем кастомные поля
                        const container = $('#custom-fields-container');
                        container.empty();

                        const currentRoleId = response.currentUser.role_id;
                        console.log('\\Log currentRoleId =', currentRoleId);

                        response.fields.forEach(function (field, idx) {
                            // 1) Значение поля для текущего пользователя
                            let userValue = '';
                            if (response.user.fields) {
                                const uf = response.user.fields.find(uf => uf.slug === field.slug);
                                if (uf) userValue = uf.pivot.value || '';
                            }

                            console.log(`\\Log [Field ${field.slug}] editable =`, field.editable);

                            const roles = Array.isArray(field.roles) ? field.roles : [];
                            const disabledAttr = field.editable ? '' : 'disabled';

                            const html = `
                                <div class="mb-3 custom-field" data-slug="${field.slug}">
                                    <label for="custom-${field.slug}" class="form-label">${field.name}</label>
                                    <input
                                        type="text"
                                        name="custom[${field.slug}]"
                                        class="form-control"
                                        id="custom-${field.slug}"
                                        value="${userValue}"
                                        ${disabledAttr}
                                    />
                                </div>
                            `;
                            container.append(html);
                        });

                        // 6) Открываем модалку
                        $('#editUserModal').modal('show');
                        applyPhoneUI(response.user);

                    },

                    error: function () {
                        console.error('Ошибка при загрузке данных пользователя');
                    }
                });
            });
        }
        // ОТПРАВКА AJAX.-> /UserController->Update Обработчик обновления данных пользователя

        // ОТКРЫТЬ МОДАЛКУ ЮЗЕРА и загрузить его данные для редактирования UserController@edit
        function editUserLink() {
            // делегированный обработчик – работает и для динамических элементов (DataTables)
            $(document).off('click.editUser').on('click.editUser', '.edit-user-link', function (e) {
                e.preventDefault();

                let userId = $(this).data('id'); // Получаем ID пользователя
                console.log('Открываем модалку для редактирования пользователя с ID:', userId);

                // AJAX-запрос для получения данных пользователя
                $.ajax({
                    url: `/admin/users/${userId}/edit`,
                    method: 'GET',

                    success: function (response) {
                        const current      = response.currentUser;
                        const isSuperadmin = current.isSuperadmin;
                        console.log('\\Log full response =', response);

                        // 1) Заполняем стандартные поля
                        $('#edit-user-form #edit-name').val(response.user.name);
                        $('#edit-user-form #edit-lastname').val(response.user.lastname);
                        if (typeof window.setStudentParentForm === 'function') {
                            window.setStudentParentForm('edit', {
                                parent_id: response.user.parent_id,
                                parent_lastname: response.user.parent_lastname,
                                parent_firstname: response.user.parent_firstname,
                                parent_middlename: response.user.parent_middlename,
                                parent_passport: response.user.parent_passport,
                                parent_passport_issued: response.user.parent_passport_issued,
                                parent_address: response.user.parent_address,
                                parent_phone: response.user.parent_phone,
                                parent_email: response.user.parent_email,
                            });
                        }
                        $('#edit-user-form #edit-birthday').val(response.user.birthday);
                        $('#edit-user-form #edit-email').val(response.user.email);
                        window.PhoneInputMask?.setValue('#edit-user-form #edit-phone', response.user.phone);
                        $('#edit-user-form #edit-activity').val(response.user.is_enabled);

                        // 2) Контекст роли (без смены роли в модалке)
                        setEditUserRoleContext(response);
                        syncEditUserTeamFields(
                            response.user.role_id,
                            editUserRolesCache,
                            response.user.trainer_team_ids || [],
                            response.user.team_ids || []
                        );
                        setEditUserHealthFields(response.user);
                        syncEditUserHealthFields(response.user.role_id, editUserRolesCache);
                        applyEditUserCommentSexPermissions(response.ui || {});
                        setEditUserCommentSexFields(response.user);
                        syncEditUserCommentSexFields(response.user.role_id, editUserRolesCache);
                        syncSendWelcomeCredentialsBtn(response.user);

                        // 3) Устанавливаем action формы (используется в обновлении и смене пароля/удалении)
                        $('#edit-user-form').attr('action', `/admin/users/${response.user.id}`);

                        // 4) Аватар
                        if (response.user && response.user.image_crop) {
                            $('.avatar-clip img').attr('src', "{{ asset('storage/avatars') }}/" + response.user.image_crop);
                        } else {
                            $('.avatar-clip img').attr('src', "{{ asset('img/default-avatar.png') }}");
                        }

                        setZoomImageFromUser(response.user);
                        setSelectedUserContext(response.user);
                        $('.js-delete-photo').attr('data-id', response.user.id);
                        setOpenPhotoVisibilityByUser(response.user);

                        // 5) Кастомные поля
                        const container    = $('#custom-fields-container');
                        const currentRoleId = response.currentUser.role_id;
                        container.empty();

                        response.fields.forEach(function (field, idx) {
                            let userValue = '';
                            if (response.user.fields) {
                                const uf = response.user.fields.find(uf => uf.slug === field.slug);
                                if (uf) userValue = uf.pivot.value || '';
                            }

                            const disabledAttr = field.editable ? '' : 'disabled';

                            const html = `
                        <div class="mb-3 custom-field" data-slug="${field.slug}">
                            <label for="custom-${field.slug}" class="form-label">${field.name}</label>
                            <input
                                type="text"
                                name="custom[${field.slug}]"
                                class="form-control"
                                id="custom-${field.slug}"
                                value="${userValue}"
                                ${disabledAttr}
                            />
                        </div>
                    `;
                            container.append(html);
                        });

                        // 6) Открываем модалку
                        $('#editUserModal').modal('show');
                        applyPhoneUI(response.user);
                    },

                    error: function () {
                        console.error('Ошибка при загрузке данных пользователя');
                    }
                });
            });
        }


        function editUserForm() {
            $('#edit-user-form')
                .off('submit') // чтобы не дублировались хендлеры при повторном открытии
                .on('submit', function (e) {
                    e.preventDefault();

                    const $form = $(this);
                    const url = $form.attr('action');

                    // Сброс прошлых ошибок
                    $form.find('.is-invalid').removeClass('is-invalid');
                    $form.find('.invalid-feedback').remove();

                    $.ajax({
                        url: url,
                        method: 'PATCH',
                        data: $form.serialize(),
                        headers: {'Accept': 'application/json'}, // Laravel вернёт JSON для 422
                        success: function (response) {
                            showSuccessModal("Редактирование пользователя", "Пользователь успешно обновлён.", 1);
                            // при желании: обновить список/закрыть модалку и т.п.
                        },
                        error: function (xhr) {
                            // Валидация
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
                                    if (!$input.length) {
                                        const safe = field.replace(/\./g, '\\.').replace(/\*/g, '\\*');
                                        $input = $form.find('[name="' + safe + '"]');
                                    }
                                    if (!$input.length && customMatch) {
                                        $input = $form.find('#custom-' + customMatch[1]);
                                    }
                                    if (!$input.length) {
                                        $input = $form.find('#' + field.replace(/\./g, '\\.'));
                                    }
                                    if (!$input.length && field.split('.')[0] === 'team_ids') {
                                        $form.find('.js-trainer-teams-checkboxes').addClass('border-danger');
                                        $input = $form.find('input[name="team_ids[]"]').first();
                                    }

                                    if ($input.length) {
                                        $input.addClass('is-invalid');
                                        $('<div class="invalid-feedback d-block"></div>')
                                            .text(messages[0])
                                            .insertAfter($input);
                                    }
                                });

                                if (typeof window.syncStudentUserAccordionsForErrors === 'function') {
                                    window.syncStudentUserAccordionsForErrors('edit', $form);
                                } else if (typeof window.syncStudentParentAccordionForErrors === 'function') {
                                    window.syncStudentParentAccordionForErrors('edit');
                                }

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
        }

        // Вызов модалки удаления (только кнопка в этой модалке)
        $(document).on('click', '#delete-user-btn.confirm-delete-modal', function () {
            deleteUser();
        });

        function deleteUser() {
            showConfirmDeleteModal(
                "Удаление пользователя",
                "Вы уверены, что хотите удалить пользователя?",
                function () {
                    const confirmEl = document.getElementById('confirmDeleteModal');
                    const editEl = document.getElementById('editUserModal');

                    $(confirmEl).off('hidden.bs.modal.return');

                    const userId = $('#edit-user-form').attr('action').split('/').pop();
                    const token = $('input[name="_token"]').val();

                    $.ajax({
                        url: `/admin/user/${userId}`,
                        method: 'DELETE',
                        headers: {'X-CSRF-TOKEN': token},
                        success: function (response) {
                            if (response.success) {
                                $(editEl).off('hidden.bs.modal.openNext');
                                bootstrap.Modal.getInstance(editEl)?.hide();

                                if ($.fn.DataTable.isDataTable('#users-table')) {
                                    $('#users-table').DataTable().ajax.reload(null, false);
                                }

                                showSuccessModal("Удаление пользователя", "Пользователь успешно удален.", 0);
                            } else {
                                $('#error-modal-message').text('Произошла ошибка при удалении пользователя.');
                                $('#errorModal').modal('show');
                            }
                        },
                        error: function () {
                            $('#error-modal-message').text('Произошла ошибка при удалении пользователя.');
                            $('#errorModal').modal('show');
                        }
                    });
                }
            );
        }

        $(document).on('hidden.bs.modal', '#editUserModal', function () {
            const $form   = $('#edit-user-form');
            const $phone  = $('#edit-phone');
            const canPhone = String($phone.data('can-phone')) === '1';

            $form[0]?.reset?.();

            $('#phone-verify-icon').addClass('d-none').find('i').attr('class','');
            $('#phone-verify-status').addClass('d-none').text('');
            $('#phone-edit-locked-note').remove();

            // Вернуть базу по правам админа (чтобы не «залипало» между пользователями)
            $phone.prop('disabled', !canPhone)
                .attr('aria-disabled', !canPhone ? 'true' : null);

            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').remove();
            $form.find('.js-trainer-teams-checkboxes').removeClass('border-danger');
            $('.js-user-trainer-teams-wrap').addClass('d-none');
            $('.js-user-student-team-wrap').removeClass('d-none');
            $form.removeData('role-id');
            editUserRolesCache = [];
        });


        editMidalUser();
        editUserLink();
        editUserForm();
    });
</script>



