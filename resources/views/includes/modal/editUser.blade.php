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


                    <!-- ДВЕ КОЛОНКИ -->
                    <div class="row g-3"> <!-- g-3 = отступы между полями -->

                        <!-- Поле "Имя" -->
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

                        <!-- Поле "Фамилия" -->
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


                        <!-- Поле "Дата рождения" -->
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

                        <!-- Поле "Группа" (ученик / сотрудник) -->
                        <div class="col-12 col-md-6 js-user-student-team-wrap">
                            <div class="mb-3">
                                <label for="edit-team" class="form-label">Группа</label>
                                <select
                                        id="edit-team"
                                        name="team_id"
                                        class="form-select"
                                        data-team-locked="{{ auth()->user()->can('users.group.update') ? '0' : '1' }}"
                                        @cannot('users.group.update') disabled aria-disabled="true" @endcannot
                                >
                                    <option value="">Без группы</option>
                                    @foreach($allTeams as $team)
                                        <option value="{{ $team->id }}">{{ $team->title }}</option>
                                    @endforeach
                                </select>

                                @cannot('users.group.update')
                                    <div class="form-text text-muted">
                                        <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение группы
                                    </div>
                                @endcannot
                            </div>
                        </div>

                        @can('trainers.view')
                        <!-- Группы тренера -->
                        <div class="col-12 col-md-6 js-user-trainer-teams-wrap d-none">
                            @include('admin.trainers._teams_checkboxes', [
                                'teamsFieldIdPrefix' => 'edit-user-trainer',
                                'teamOptions' => $allTeams,
                                'teamsLabel' => 'Группы тренера',
                            ])
                        </div>
                        @endcan

                        @include('admin.users._parent_form', ['prefix' => 'edit'])

                        {{-- Поле "email" --}}
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

                        {{-- Поле "Телефон" --}}
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

                                    {{-- Индикатор статуса (галка/крест) --}}
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

                        <!-- Поле "Роль" -->
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label for="role_id" class="form-label">Роль</label>
                                <select
                                        id="role_id"
                                        name="role_id"
                                        class="form-select"
                                        @cannot('users.role.update') disabled aria-disabled="true" @endcannot
                                >
                                    @foreach($roles as $role)
                                        @continue($role->name === 'superadmin')
                                        <option value="{{ $role->id }}"
                                                @if(($editingUser->role_id ?? $user->role_id ?? null) === $role->id) selected @endif>
                                            {{ $role->label }}
                                        </option>
                                    @endforeach
                                </select>
                                <div id="edit-role-superadmin-lock" class="form-text text-muted d-none">
                                    <i class="fa-solid fa-lock me-1"></i>Роль «Суперадмин» нельзя изменить через CRM
                                </div>
                                @cannot('users.role.update')
                                    <div class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i>Нет прав на
                                        изменение
                                        роли
                                    </div>
                                @endcannot
                            </div>
                        </div>

                        @include('includes.modal._student_health_fields')

                        <!-- Поле "Активность" -->
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

        function triStateSelectValue(val) {
            if (val === true || val === 1 || val === '1') {
                return '1';
            }
            if (val === false || val === 0 || val === '0') {
                return '0';
            }
            return '';
        }

        function setEditUserHealthFields(user) {
            $('#edit-is_individual_traits').val(triStateSelectValue(user.is_individual_traits));
            $('#edit-is_on_medical_register').val(triStateSelectValue(user.is_on_medical_register));
            $('#edit-is_with_disability').val(triStateSelectValue(user.is_with_disability));
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

        function setEditUserTrainerTeamIds(teamIds) {
            const ids = (teamIds || []).map(function (id) {
                return parseInt(id, 10);
            });
            $('#edit-user-form input[name="team_ids[]"]').each(function () {
                $(this).prop('checked', ids.includes(parseInt($(this).val(), 10)));
            });
        }

        function applyEditUserRoleSelect(response) {
            const roleSelect = $('#edit-user-form #role_id');
            const $lockHint = $('#edit-role-superadmin-lock');

            roleSelect.empty().prop('disabled', false).removeAttr('aria-disabled');
            $lockHint.addClass('d-none');

            if (response.targetIsSuperadmin) {
                roleSelect.append(
                    $('<option>', {value: response.user.role_id, text: 'Суперадмин'})
                );
                roleSelect.val(String(response.user.role_id));
                roleSelect.prop('disabled', true).attr('aria-disabled', 'true');
                $lockHint.removeClass('d-none');
                editUserRolesCache = [];
                return;
            }

            (response.roles || []).forEach(function (role) {
                roleSelect.append(
                    $('<option>', {value: role.id, text: role.label})
                );
            });
            roleSelect.val(response.user.role_id);
            editUserRolesCache = response.roles || [];
        }

        function syncEditUserTeamFields(roleId, roles, trainerTeamIds) {
            const trainerRoleId = trainerRoleIdFromRoles(roles);
            const isTrainer = trainerRoleId && parseInt(roleId, 10) === trainerRoleId;
            const $studentWrap = $('.js-user-student-team-wrap');
            const $teamSelect = $('#edit-team');
            const $trainerWrap = $('.js-user-trainer-teams-wrap');
            const teamLocked = String($teamSelect.data('team-locked')) === '1';

            if (isTrainer) {
                $studentWrap.addClass('d-none');
                $teamSelect.prop('disabled', true);
                if (hasTrainerTeamsUi) {
                    $trainerWrap.removeClass('d-none');
                    $trainerWrap.find('input[name="team_ids[]"]').prop('disabled', false);
                    if (trainerTeamIds !== undefined && trainerTeamIds !== null) {
                        setEditUserTrainerTeamIds(trainerTeamIds);
                    }
                }
            } else {
                $studentWrap.removeClass('d-none');
                $teamSelect.prop('disabled', teamLocked);
                if (hasTrainerTeamsUi) {
                    $trainerWrap.addClass('d-none');
                    $trainerWrap.find('input[name="team_ids[]"]').prop('disabled', true).prop('checked', false);
                }
            }

            syncEditUserHealthFields(roleId, roles);
        }

        $(document).on('change', '#edit-user-form #role_id', function () {
            syncEditUserTeamFields($(this).val(), editUserRolesCache, null);
        });

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
                        $('#edit-user-form #edit-team').val(response.user.team_id);
                        $('#edit-user-form #edit-email').val(response.user.email);
                        window.PhoneInputMask?.setValue('#edit-user-form #edit-phone', response.user.phone);
                        $('#edit-user-form #edit-activity').val(response.user.is_enabled);

                        // 2) Роли
                        applyEditUserRoleSelect(response);
                        syncEditUserTeamFields(response.user.role_id, editUserRolesCache, response.user.trainer_team_ids || []);
                        setEditUserHealthFields(response.user);
                        syncEditUserHealthFields(response.user.role_id, editUserRolesCache);

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
                        $('#edit-user-form #edit-team').val(response.user.team_id);
                        $('#edit-user-form #edit-email').val(response.user.email);
                        window.PhoneInputMask?.setValue('#edit-user-form #edit-phone', response.user.phone);
                        $('#edit-user-form #edit-activity').val(response.user.is_enabled);

                        // 2) Роли
                        applyEditUserRoleSelect(response);
                        syncEditUserTeamFields(response.user.role_id, editUserRolesCache, response.user.trainer_team_ids || []);
                        setEditUserHealthFields(response.user);
                        syncEditUserHealthFields(response.user.role_id, editUserRolesCache);

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
            editUserRolesCache = [];
        });


        editMidalUser();
        editUserLink();
        editUserForm();
    });
</script>



