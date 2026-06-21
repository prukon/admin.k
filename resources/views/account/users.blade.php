{{--ВКЛАДКА ЮЗЕР--}}
<div class="row">
    {{--Аватар--}}

    <div class="avatar_wrapper">
        <div class="avatar">                         <!-- ВНЕШНИЙ контейнер (hover + меню) -->
            <div class="avatar-clip">                  <!-- ВНУТРЕННИЙ круг (обрезка фото + бордер) -->
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

    <!-- CRUD аватарки -->
    @include('includes.modal.editAvatar')


    {{--Данные пользователя--}}
    <div class="col-12 col-lg-10 user-data-wrap mb-3">

        {{--форма юзера--}}
        <form id='userUpdateForm' method="post">
            {{-- Токен (система защиты) необходим при использовании любого роута кроме get. --}}
            @csrf
            @method('patch')

            <div class="row g-3 mb-3 align-items-stretch">
            <div class="col-12 @if(!empty($showAccountParentSection)) col-lg-6 @endif">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">{{ $user->role?->label ?? $user->role?->name ?? 'Пользователь' }}</div>
                <div class="card-body">

            {{-- Поле "Фамилия" --}}
            @php $canEditName = auth()->user()->can('account.user.name.update'); @endphp
            <div class="mb-3">
                <label for="lastname" class="form-label">Фамилия*</label>

                <input type="text" id="lastname" name="lastname"
                       class="form-control @error('lastname') is-invalid @enderror"
                       value="{{ old('lastname', $user->lastname) }}"
                       @unless($canEditName) disabled aria-disabled="true" @endunless >

                @error('lastname')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror


                @unless($canEditName)
                    <div class="form-text text-muted mt-1">
                        <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение фамилии
                    </div>
                @endunless
            </div>


            {{-- Поле "Имя" --}}
            @php $canEditName = auth()->user()->can('account.user.name.update'); @endphp
            <div class="mb-3">
                <label for="name" class="form-label">Имя*</label>

                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name', $user->name) }}"
                       @unless($canEditName) disabled aria-disabled="true" @endunless >

                @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror

                @unless($canEditName)
                    <div class="form-text text-muted mt-1">
                        <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение имени
                    </div>
                @endunless
            </div>


            {{-- Поле "Дата рождения" --}}
            <div class="mb-3">
                <label for="birthday" class="form-label">Дата рождения</label>
                <input type="date" id="birthday" name="birthday"
                       class="form-control @error('birthday') is-invalid @enderror"
                       value="{{ old('birthday', $user->birthday_for_form) }}"
                       @cannot('account.user.birthdate.update') disabled aria-disabled="true" @endcannot>
                @error('birthday')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                @cannot('account.user.birthdate.update')
                    <div class="form-text text-muted mt-1"><i class="fa-solid fa-lock me-1"></i>Нет прав на изменение
                        даты рождения
                    </div>
                @endcannot
            </div>

            @can('users.sex')
                <div class="mb-3">
                    <label for="sex" class="form-label">Пол</label>
                    <select id="sex" name="sex" class="form-select @error('sex') is-invalid @enderror">
                        <option value="" @selected(old('sex', $user->sex) === null || old('sex', $user->sex) === '')>Не указано</option>
                        @foreach (\App\Enums\UserSex::cases() as $sexCase)
                            <option value="{{ $sexCase->value }}" @selected(old('sex', $user->sex) === $sexCase->value)>
                                {{ $sexCase->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('sex')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            @endcan

            {{-- Поле "Email" --}}
            <div class="mb-3">
                <label for="email" class="form-label">Адрес электронной почты (необязательно)</label>
                <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control @error('email') is-invalid @enderror"
                        placeholder="name@example.com"
                        value="{{ old('email', $user->email) }}"
                        @cannot('account.user.email.update') disabled aria-disabled="true" @endcannot
                >
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                @cannot('account.user.email.update')
                    <div class="form-text text-muted mt-1"><i class="fa-solid fa-lock me-1"></i>Нет прав на изменение
                        email
                    </div>
                @endcannot
            </div>

            {{-- Поле "Телефон" --}}
            @php
                $canPhone    = auth()->user()->can('account.user.phone.update');
                $verifiedAt  = $user->phone_verified_at ? \Carbon\Carbon::parse($user->phone_verified_at) : null;
                $isVerified  = (bool) $verifiedAt;
            @endphp

            <div class="mb-3">
                <label for="phone" class="form-label">Телефон</label>

                <div class="input-group">
                    @include('includes.fields.phone-input', [
                        'name' => 'phone',
                        'id' => 'phone',
                        'value' => old('phone', $user->phone),
                        'disabled' => $isVerified || !$canPhone,
                    ])

                    {{-- Кнопка подтверждения — скрыта, если номер уже подтверждён --}}
                    @if($canPhone && !$isVerified)
                        <button type="button" id="verify-phone-btn" class="btn btn-success">
                            Подтвердить
                        </button>
                    @else
                        <button type="button" id="verify-phone-btn"
                                class="btn btn-success d-none"
                                aria-disabled="true" tabindex="-1">
                            Подтвердить
                        </button>
                    @endif

                    {{-- Иконка статуса: зелёная если подтверждён, красная если нет --}}
                    <span id="phone-verified-icon"
                          class="input-group-text {{ $isVerified ? 'text-success' : 'text-danger' }}"
                          data-verified="{{ $isVerified ? 1 : 0 }}">
            <i class="fa-solid {{ $isVerified ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i>
        </span>
                </div>

                {{-- Статус + запрет редактирования после подтверждения --}}
                @if($isVerified)
                    <small id="phone-verify-status"
                           class="small text-success d-block mt-1"
                           data-verified="1"
                           data-verified-at="{{ $verifiedAt->format('Y-m-d H:i:s') }}">
                        Подтверждён {{ $verifiedAt->format('d.m.Y H:i') }}
                    </small>
                    <div class="form-text text-success mt-1" id="phone-edit-locked-note">
                        <i class="fa-solid fa-lock me-1"></i>
                        Редактирование номера запрещено после подтверждения.
                    </div>
                @else
                    <small id="phone-verify-status" class="small text-muted d-block mt-1 d-none" data-verified="0">
                        <!-- появляется после подтверждения -->
                    </small>
                    @unless($canPhone)
                        <div class="form-text text-muted mt-1"><i class="fa-solid fa-lock me-1"></i>Нет прав на
                            изменение телефона
                        </div>
                    @endunless
                @endif
            </div>


            {{--<h5>Пользовательские поля</h5>--}}
            @if($fields->isNotEmpty())

                <div class="mb-3">
                    <div id="custom-fields-container">

                        @foreach($fields as $field)
                            @php
                                $isEditable = $editableFields[$field->id] ?? false;
                                $userFieldValue = $userFieldValues[$field->id] ?? '';
                            @endphp

                            <div class="mb-3 custom-field" data-slug="{{ $field->slug }}">
                                <label for="custom-{{ $field->slug }}" class="form-label">
                                    {{ $field->name }}
                                </label>
                                <input
                                        type="text"
                                        name="custom[{{ $field->slug }}]"
                                        class="form-control"
                                        id="custom-{{ $field->slug }}"
                                        value="{{ old("custom.{$field->slug}", $userFieldValue) }}"
                                        {{ $isEditable ? '' : 'disabled' }}
                                />
                                @unless($isEditable)
                                    {{-- чтобы значение всё равно пришло на бек --}}
                                    <input type="hidden" name="custom[{{ $field->slug }}]"
                                           value="{{ $userFieldValue }}">
                                @endunless
                            </div>
                        @endforeach


                    </div>
                </div>

            @endif

            @if($user->role?->name !== 'admin')
            @php
                $user->loadMissing('teams');
                $studentTeamsLabel = $user->teams->pluck('title')->filter()->sort()->values()->implode(', ');
            @endphp
            <div class="mb-3">
                <label class="form-label">Группы</label>
                <input type="text"
                       class="form-control"
                       value="{{ $studentTeamsLabel !== '' ? $studentTeamsLabel : '—' }}"
                       readonly
                       disabled
                       aria-disabled="true">
                <div class="form-text text-muted mt-1">
                    <i class="fa-solid fa-lock me-1"></i>Изменение групп доступно только администратору CRM
                </div>
            </div>
            @endif

            {{-- 2FA (SMS) --}}
            @php
                // глобальная настройка «Обязательная 2FA для администраторов»
                $forceAdmin2fa = \App\Models\Setting::getBool('force_2fa_admins', false);
                $isAdmin = (int)$user->role_id === 10;
                $forcedForThisUser = $isAdmin && $forceAdmin2fa;
                $isChecked = $forcedForThisUser ? true : (bool) old('two_factor_enabled', $user->two_factor_enabled);
            @endphp
            <div class="mb-3 mb-lg-0">
                <label class="form-label d-block">Двухфакторная аутентификация (SMS)</label>

                @if($forcedForThisUser)
                    {{-- Глобально обязательно: отправляем значение как 1 даже если чекбокс disabled --}}
                    <input type="hidden" name="two_factor_enabled" value="1">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="two_factor_enabled" checked disabled>
                        <label class="form-check-label" for="two_factor_enabled">
                            Включена (обязательна для администраторов)
                        </label>
                    </div>
                    <small class="text-muted">Управляется общей настройкой «Обязательная 2FA для администраторов».
                    </small>
                @else
                    {{-- Обычное управление чекбоксом. Чтобы «снятие» отправляло 0 — добавляем hidden. --}}
                    <input type="hidden" name="two_factor_enabled" value="0">
                    <div class="form-check">
                        <input
                                class="form-check-input"
                                type="checkbox"
                                id="two_factor_enabled"
                                name="two_factor_enabled"
                                value="1"
                                {{ $isChecked ? 'checked' : '' }}
                        >
                        <label class="form-check-label" for="two_factor_enabled">
                            {{ $isChecked ? 'Включена' : 'Выключена' }}
                        </label>
                    </div>
                @endif

                @error('two_factor_enabled')
                <p class="text-danger">{{ $message }}</p>
                @enderror
            </div>

                </div>
            </div>
            </div>

            @if(!empty($showAccountParentSection))
            <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Данные родителя</div>
                <div class="card-body">

                <div class="mb-3">
                    <label for="parent_lastname" class="form-label">Фамилия родителя@if($canEditAccountParent)*@endif</label>
                    <input type="text"
                           id="parent_lastname"
                           name="parent_lastname"
                           class="form-control @error('parent_lastname') is-invalid @enderror"
                           maxlength="100"
                           value="{{ old('parent_lastname', $accountParentFields['parent_lastname'] ?? '') }}"
                           @unless($canEditAccountParent) disabled aria-disabled="true" @endunless>
                    @error('parent_lastname')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="parent_firstname" class="form-label">Имя родителя@if($canEditAccountParent)*@endif</label>
                    <input type="text"
                           id="parent_firstname"
                           name="parent_firstname"
                           class="form-control @error('parent_firstname') is-invalid @enderror"
                           maxlength="100"
                           value="{{ old('parent_firstname', $accountParentFields['parent_firstname'] ?? '') }}"
                           @unless($canEditAccountParent) disabled aria-disabled="true" @endunless>
                    @error('parent_firstname')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="parent_middlename" class="form-label">Отчество родителя</label>
                    <input type="text"
                           id="parent_middlename"
                           name="parent_middlename"
                           class="form-control @error('parent_middlename') is-invalid @enderror"
                           maxlength="100"
                           value="{{ old('parent_middlename', $accountParentFields['parent_middlename'] ?? '') }}"
                           @unless($canEditAccountParent) disabled aria-disabled="true" @endunless>
                    @error('parent_middlename')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="parent_passport" class="form-label">Паспорт</label>
                    <input type="text"
                           id="parent_passport"
                           name="parent_passport"
                           class="form-control @error('parent_passport') is-invalid @enderror"
                           maxlength="100"
                           value="{{ old('parent_passport', $accountParentFields['parent_passport'] ?? '') }}"
                           @unless($canEditAccountParent) disabled aria-disabled="true" @endunless>
                    @error('parent_passport')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="parent_passport_issued" class="form-label">Паспорт, кем и когда выдан</label>
                    <input type="text"
                           id="parent_passport_issued"
                           name="parent_passport_issued"
                           class="form-control @error('parent_passport_issued') is-invalid @enderror"
                           maxlength="500"
                           value="{{ old('parent_passport_issued', $accountParentFields['parent_passport_issued'] ?? '') }}"
                           @unless($canEditAccountParent) disabled aria-disabled="true" @endunless>
                    @error('parent_passport_issued')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="parent_address" class="form-label">Адрес</label>
                    <input type="text"
                           id="parent_address"
                           name="parent_address"
                           class="form-control @error('parent_address') is-invalid @enderror"
                           maxlength="1000"
                           value="{{ old('parent_address', $accountParentFields['parent_address'] ?? '') }}"
                           @unless($canEditAccountParent) disabled aria-disabled="true" @endunless>
                    @error('parent_address')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="parent_phone" class="form-label">Телефон</label>
                    @include('includes.fields.phone-input', [
                        'name' => 'parent_phone',
                        'id' => 'parent_phone',
                        'value' => old('parent_phone', $accountParentFields['parent_phone'] ?? ''),
                        'disabled' => !$canEditAccountParent,
                        'class' => $errors->has('parent_phone') ? 'is-invalid' : '',
                    ])
                    @error('parent_phone')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="parent_email" class="form-label">Email</label>
                    <input type="email"
                           id="parent_email"
                           name="parent_email"
                           class="form-control @error('parent_email') is-invalid @enderror"
                           maxlength="255"
                           value="{{ old('parent_email', $accountParentFields['parent_email'] ?? '') }}"
                           @unless($canEditAccountParent) disabled aria-disabled="true" @endunless>
                    @error('parent_email')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                @unless($canEditAccountParent)
                    <div class="form-text text-muted mt-1">
                        <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение данных родителя
                    </div>
                @endunless
                </div>
            </div>
            </div>
            @endif
            </div>

            {{-- Блок изменения пароля --}}
            <div class="buttons-wrap change-pass-wrap " id="change-pass-wrap" style="display: none;">
                <div class="d-flex align-items-center">
                    <div class="position-relative">
                        <input type="password" id="new-password" class="form-control"
                               placeholder="Новый пароль">
                        <span toggle="#new-password" class="fa fa-fw fa-eye field-icon toggle-password"></span>
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

            {{-- Кнопки "Обновить" и "Изменить пароль" --}}
            <div class="button-group buttons-wrap mt-3">
                <button type="submit" class="btn btn-primary update-btn ">Обновить</button>
                <button type="button" id="change-password-btn" class="btn btn-danger ml-2"><i
                            class="fa-solid fa-key me-1"></i>Изменить пароль
                </button>
            </div>

        </form>

    </div>
</div>
<div id="password-change-message" class="text-success ml-3" style="display:none;">Пароль
    изменен
</div>

{{-- Модалка ввода SMS-кода для подтверждения телефона --}}
<div class="modal fade" id="phoneCodeModal" tabindex="-1" aria-labelledby="phoneCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="phoneCodeModalLabel">Подтверждение телефона</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">На номер <strong id="code-target-phone"></strong> отправлен код.</p>
                <input type="text" class="form-control" id="phone-code-input" placeholder="Введите код из SMS"
                       autocomplete="one-time-code" inputmode="numeric" maxlength="6">
                <div class="text-danger small mt-2 d-none" id="phone-code-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" id="resend-code-btn" class="btn btn-link">Отправить код ещё раз</button>
                <button type="button" id="confirm-code-btn" class="btn btn-primary">Подтвердить код</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')

    <script>
        // 2fa: простая клиентская проверка телефона (оставлена как была)
        document.addEventListener('DOMContentLoaded', function () {
            $('#userUpdateForm').on('submit', function () {
                const isAdmin = {{ (int)$user->role_id === 10 ? 'true' : 'false' }};
                const twofaChecked = isAdmin ? true : $('#two_factor_enabled').is(':checked');
                const phone = ($('#phone').val() || '').replace(/\D+/g, '');
                // if (twofaChecked && phone.length < 11) {
                //     alert('Для включения 2FA укажите корректный телефон (формат 79XXXXXXXXX).');
                //     return false;
                // }
                return true;
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // Клик по ИЗМЕНИТЬ ПАРОЛЬ
            function changePasswordBtn() {
                document.getElementById('change-password-btn').addEventListener('click', function () {
                    document.getElementById('change-password-btn').style.display = 'none';
                    document.getElementById('change-pass-wrap').style.display = 'inline-block';
                });
            }

            // Клик по ПРИМЕНИТЬ ПАРОЛЬ
            function applyPasswordBtn() {
                document.getElementById('apply-password-btn').addEventListener('click', function () {
                    var newPassword = document.getElementById('new-password').value;
                    var token = '{{ csrf_token() }}';
                    var errorMessage = document.getElementById('error-message');

                    // Проверка длины пароля
                    if (newPassword.length < 8) {
                        errorMessage.style.display = 'block';
                        return;
                    } else {
                        errorMessage.style.display = 'none';
                    }

                    fetch(`{{ route('account.user.password.update') }}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({password: newPassword}),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('change-password-btn').style.display = 'inline-block';
                                document.querySelector('#change-pass-wrap').style.display = 'none';
                                showSuccessModal("Изменение пароля", "Пароль успешно изменен.");
                            }
                        });
                });
            }

            // Клик по ОТМЕНА
            function cancelChangePasswordBtn() {
                document.getElementById('cancel-change-password-btn').addEventListener('click', function () {
                    document.getElementById('change-password-btn').style.display = 'inline-block';
                    document.getElementById('change-pass-wrap').style.display = 'none';
                    document.getElementById('error-message').style.display = 'none';
                });
            }

            // Показ/скрытие пароля
            function showPassword() {
                const togglePassword = document.querySelector('.toggle-password');
                const passwordInput = document.getElementById('new-password');

                togglePassword.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }

            // Обновление юзера (AJAX)
            function updateUserData() {
                $('#userUpdateForm')
                    .off('submit')
                    .on('submit', function (e) {
                        e.preventDefault();

                        const $form = $(this);
                        const formData = $form.serialize();

                        // Сброс прошлых ошибок
                        $form.find('.is-invalid').removeClass('is-invalid');
                        $form.find('.invalid-feedback').remove();

                        $.ajax({
                            url: "{{ route('account.user.update') }}",
                            type: 'PATCH',
                            data: formData,
                            headers: {'Accept': 'application/json'},
                            success: function () {
                                location.reload();
                            },
                            error: function (xhr) {
                                // Laravel validation 422
                                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                                    const errors = xhr.responseJSON.errors;

                                    Object.keys(errors).forEach(function (field) {
                                        const messages = errors[field];
                                        const safe = field.replace(/\./g, '\\.').replace(/\*/g, '\\*');

                                        // Сначала ищем по name="field", если нет — по id
                                        let $input = $form.find('[name="' + safe + '"]');
                                        if (!$input.length) $input = $form.find('#' + field);

                                        if ($input.length) {
                                            $input.addClass('is-invalid');
                                            $('<div class="invalid-feedback d-block"></div>')
                                                .text(messages[0])
                                                .insertAfter($input);
                                        }
                                    });

                                    // Фокус на первое поле с ошибкой
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

            changePasswordBtn();
            applyPasswordBtn();
            cancelChangePasswordBtn();
            showPassword();
            updateUserData();
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var $phone = $('#phone');
            var $verifyBtn = $('#verify-phone-btn');
            var $icon = $('#phone-verified-icon');
            var $status = $('#phone-verify-status');

            var $codeModal = $('#phoneCodeModal');
            var $codeTarget = $('#code-target-phone');
            var $codeInput = $('#phone-code-input');
            var $codeErr = $('#phone-code-error');
            var $resend = $('#resend-code-btn');
            var $confirm = $('#confirm-code-btn');

            function isComplete() {
                return window.PhoneInputMask && window.PhoneInputMask.isComplete($phone);
            }

            function norm7(s) {
                var d = String(s || '').replace(/\D+/g, '');
                if (d.length === 11 && d[0] === '8') {
                    d = '7' + d.slice(1);
                }
                if (d.length === 10) {
                    d = '7' + d;
                }
                return d;
            }

            function prettyNow() {
                var d = new Date();
                return d.toLocaleDateString('ru-RU', {day: '2-digit', month: '2-digit', year: 'numeric'}) + ' ' +
                    d.toLocaleTimeString('ru-RU', {hour: '2-digit', minute: '2-digit'});
            }

            // Источник статуса — data-verified на иконке
            function isVerified() {
                return Number($icon.attr('data-verified')) === 1;
            }

            function showBtn() {
                $verifyBtn.removeClass('d-none').prop('disabled', false);
            }

            function hideBtn() {
                $verifyBtn.addClass('d-none').prop('disabled', true);
            }

            // Показывать / скрывать кнопку «Подтвердить»
            function updateVerifyUI() {
                if (!$phone.length || $phone.is(':disabled')) {
                    hideBtn();
                    return;
                }
                if (isVerified()) {
                    hideBtn();
                    return;
                }
                if (isComplete()) {
                    showBtn();
                } else {
                    hideBtn();
                }
            }

            // События Inputmask
            $phone.on('complete incomplete cleared input keyup paste blur', updateVerifyUI);
            updateVerifyUI();

            // Отправка кода
            $verifyBtn.on('click', function () {
                var n = norm7($phone.val());
                if (!/^7\d{10}$/.test(n)) return;

                $.ajax({
                    url: "{{ route('account.user.phoneSendCode', $user->id) }}",
                    method: 'POST',
                    data: {_token: "{{ csrf_token() }}", phone: n},
                    success: function () {
                        // Показ модалки ввода кода
                        $codeTarget.text($phone.val());
                        $codeInput.val('');
                        $codeErr.addClass('d-none').text('');
                        $codeModal.modal('show');
                        setTimeout(function () {
                            $codeInput.trigger('focus');
                        }, 150);
                    },
                    error: function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Не удалось отправить код. Попробуйте позже.';
                        alert(msg);
                    }
                });
            });

            // Повторная отправка
            $resend.on('click', function () {
                var n = norm7($phone.val());
                if (!/^7\d{10}$/.test(n)) return;
                $.ajax({
                    url: "{{ route('account.user.phoneSendCode', $user->id) }}",
                    method: 'POST',
                    data: {_token: "{{ csrf_token() }}", phone: n}
                });
            });

            // Подтверждение кода
            $confirm.on('click', function () {
                var n = norm7($phone.val());
                var code = String($codeInput.val() || '').trim();
                if (!/^\d{4,8}$/.test(code)) {
                    $codeErr.removeClass('d-none').text('Введите корректный код.');
                    return;
                }
                $.ajax({
                    url: "{{ route('account.user.phoneConfirmCode', $user->id) }}",
                    method: 'POST',
                    data: {_token: "{{ csrf_token() }}", phone: n, code: code},
                    success: function (res) {
                        $codeModal.modal('hide');

                        // 1) ИКОНКА -> зелёная галочка, verified=1
                        $icon
                            .removeClass('text-danger').addClass('text-success')
                            .attr('data-verified', '1')
                            .find('i').removeClass('fa-circle-xmark').addClass('fa-circle-check');

                        // 2) Создать/обновить статус
                        var ts = (res && res.verified_at) ? res.verified_at : prettyNow();
                        if (!$('#phone-verify-status').length) {
                            var $small = $('<small/>', {
                                id: 'phone-verify-status',
                                class: 'small text-success',
                                'data-verified': '1',
                                'data-verified-at': (res && res.verified_at) ? res.verified_at : ''
                            }).text('Подтверждён ' + ts);
                            $('.input-group').has('#phone').after($small);
                            $status = $small;
                        } else {
                            $status
                                .removeClass('d-none text-danger').addClass('text-success')
                                .attr('data-verified', '1')
                                .attr('data-verified-at', (res && res.verified_at) ? res.verified_at : '')
                                .text('Подтверждён ' + ts);
                        }

                        // 3) Скрыть кнопку подтверждения
                        hideBtn();

                        // 4) Заблокировать поле телефона и показать подпись о запрете редактирования
                        $phone.prop('disabled', true).attr('aria-disabled', 'true');
                        if (!$('#phone-edit-locked-note').length) {
                            $('<div/>', {
                                id: 'phone-edit-locked-note',
                                class: 'form-text text-danger mt-1'
                            }).html('<i class="fa-solid fa-lock me-1"></i>Редактирование номера запрещено после подтверждения.')
                                .insertAfter($('#phone-verify-status'));
                        }
                    },
                    error: function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Неверный код.';
                        $codeErr.removeClass('d-none').text(msg);
                    }
                });
            });

            // Скрывать ошибку при вводе кода
            $codeInput.on('input', function () {
                $codeErr.addClass('d-none').text('');
            });

            // Инициализация
            (function initVerifiedState() {
                updateVerifyUI();
            })();
        });
    </script>



@endpush
