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
    <div class="col-12 col-lg-6 user-data-wrap mb-3">

        {{--форма юзера--}}
        <form id='userUpdateForm' method="post">
            {{-- Токен (система защиты) необходим при использовании любого роута кроме get. --}}
            @csrf
            @method('patch')

            {{-- Поле "Имя" --}}
            @php $canEditName = auth()->user()->can('account.user.name.update'); @endphp
            <div class="mb-3">
                <label for="name" class="form-label">Имя ученика*</label>

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



            {{-- Поле "Фамилия" --}}
            @php $canEditName = auth()->user()->can('account.user.name.update'); @endphp
            <div class="mb-3">
                <label for="lastname" class="form-label">Фамилия ученика*</label>

                <input type="text" id="lastname" name="lastname" class="form-control @error('lastname') is-invalid @enderror"
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
                    </div> @endcannot
            </div>

            {{-- Поле "Email" --}}
            <div class="mb-3">
                <label for="email" class="form-label">Адрес электронной почты*</label>
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
                    </div> @endcannot
            </div>

            {{-- Поле "Телефон" --}}
            @php $canPhone = auth()->user()->can('account.user.phone.update'); @endphp

            <div class="mb-3">
                <label for="phone" class="form-label">Телефон</label>
                <div class="input-group">
                    @include('includes.fields.phone-input', [
                        'name' => 'phone',
                        'id' => 'phone',
                        'value' => old('phone', $user->phone),
                        'disabled' => !$canPhone,
                        'attributes' => [
                            'data-original' => $user->phone ?? '',
                            'data-verified' => $user->phone_verified_at ? 1 : 0,
                        ],
                    ])

                    @if($canPhone)
                        <button type="button" id="verify-phone-btn" class="btn btn-success">Подтвердить</button>
                    @else
                        <button type="button" id="verify-phone-btn" class="btn btn-success disabled pe-none"
                                aria-disabled="true" tabindex="-1" data-kids-tooltip-hint data-bs-toggle="tooltip"
                                title="Нет прав на изменение телефона">
                            Подтвердить
                        </button>
                    @endif

                    <span id="phone-verified-icon" class="input-group-text text-success d-none">
      <i class="fa-solid fa-circle-check"></i>
    </span>
                </div>

                @php $verifiedAt = $user->phone_verified_at ? \Carbon\Carbon::parse($user->phone_verified_at) : null; @endphp
                <small id="phone-verify-status"
                       class="small {{ $verifiedAt ? 'text-success' : ($user->phone ? 'text-danger' : 'd-none') }}"
                       data-verified="{{ $verifiedAt ? 1 : 0 }}"
                       data-verified-at="{{ $verifiedAt ? $verifiedAt->format('Y-m-d H:i:s') : '' }}">
                    @if($user->phone)
                        @if($verifiedAt) Подтверждён {{ $verifiedAt->format('d.m.Y H:i') }} @else Подтвердите
                        номер @endif
                    @endif
                </small>

                @unless($canPhone)
                    <div class="form-text text-muted mt-1"><i class="fa-solid fa-lock me-1"></i>Нет прав на изменение
                        телефона
                    </div>
                @endunless
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

            {{-- Поле "Дата начала занятий" --}}
            <div class="mb-3">
                <label for="start_date" class="form-label">Дата начала занятий</label>
                <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        class="form-control @error('start_date') is-invalid @enderror"
                        value="{{ old('start_date', $user->start_date) }}"
                        max="{{ date('2030-01-01') }}"
                        @cannot('account.user.startDate.update') disabled aria-disabled="true" @endcannot
                >
                @error('start_date')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                @cannot('account.user.startDate.update')
                    <div class="form-text text-muted mt-1"><i class="fa-solid fa-lock me-1"></i>Нет прав на изменение
                        даты начала занятий
                    </div> @endcannot
            </div>

            {{-- 2FA (SMS) --}}
            @php
                // глобальная настройка «Обязательная 2FA для администраторов»
                $forceAdmin2fa = \App\Models\Setting::getBool('force_2fa_admins', false);
                $isAdmin = (int)$user->role_id === 10;
                $forcedForThisUser = $isAdmin && $forceAdmin2fa;
                $isChecked = $forcedForThisUser ? true : (bool) old('two_factor_enabled', $user->two_factor_enabled);
            @endphp
            <div class="mb-3">
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
                    {{--<small class="text-muted">Вы можете самостоятельно включить или выключить 2FA.</small>--}}
                @endif

                @error('two_factor_enabled')
                <p class="text-danger">{{ $message }}</p>
                @enderror
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

            <hr>

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

{{-- Модалка ввода SMS-кода для нового телефона --}}
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
                       autocomplete="one-time-code" inputmode="numeric">
                <div class="text-danger small mt-2 d-none" id="phone-code-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" id="resend-code-btn" class="btn btn-link">Отправить код ещё раз</button>
                <button type="button" id="confirm-code-btn" class="btn btn-primary">Подтвердить код</button>
            </div>
        </div>
    </div>
</div>






@section('scripts')

    <script>
        // ==================== PhoneFlow (button ↔ green check) ====================
        window.PhoneFlow = (function ($) {
            'use strict';

            const state = {
                $phone: null,
                $verifyBtn: null,
                $verifiedIcon: null,   // ← новая ссылка на иконку
                $updateBtn: null,
                $status: null,
                $form: null,
                $codeModal: null,
                $codeTarget: null,
                $codeInput: null,
                $codeErr: null,
                $codeResend: null,
                $codeConfirm: null,

                originalDigits: '',
                originalVerified: false,
                phoneChangeVerified: false,

                sendCodeUrl: '',
                confirmCodeUrl: '',
                csrf: ''
            };

            // ---------- utils ----------
            function onlyDigits(s) {
                return String(s ?? '').replace(/\D+/g, '');
            }

            function normalizeRu(raw) {
                let d = onlyDigits(raw);
                if (!d) return '';
                if (d.length === 11 && d[0] === '8') d = '7' + d.slice(1);
                if (d[0] !== '7') d = '7' + d.replace(/^7?/, '');
                return d.slice(0, 11);
            }

            function displayPhone() {
                return window.PhoneInputMask
                    ? window.PhoneInputMask.formatDisplay(state.$phone)
                    : String(state.$phone.val() || '');
            }

            function pad2(n) {
                return String(n).padStart(2, '0');
            }

            function formatRuDateTime(iso) {
                if (!iso) return '';
                const d = new Date(iso.replace(' ', 'T'));
                return `${pad2(d.getDate())}.${pad2(d.getMonth() + 1)}.${d.getFullYear()} ${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
            }

            // ---------- UI helpers ----------
            function showVerifyBtn(show, enabled) {
                if (!state.$verifyBtn) return;
                state.$verifyBtn.toggleClass('d-none', !show);
                if (typeof enabled === 'boolean') state.$verifyBtn.prop('disabled', !enabled);
            }

            function showVerifiedIcon(show) {
                if (!state.$verifiedIcon) return;
                state.$verifiedIcon.toggleClass('d-none', !show);
            }

            function setStatusConfirmNeeded() {
                if (!state.$status) return;
                state.$status.removeClass('d-none text-success').addClass('text-danger').text('Подтвердите номер');
                if (state.$updateBtn) state.$updateBtn.prop('disabled', true);
            }

            function setStatusConfirmed(verifiedAtIso) {
                if (!state.$status) return;
                const when = formatRuDateTime(verifiedAtIso || state.$status.attr('data-verified-at'));
                state.$status.removeClass('d-none text-danger').addClass('text-success').text(when ? `Подтверждён ${when}` : 'Подтверждён');
                if (verifiedAtIso) state.$status.attr('data-verified-at', verifiedAtIso).attr('data-verified', '1');
                if (state.$updateBtn) state.$updateBtn.prop('disabled', false);
            }

            function hideStatus() {
                if (!state.$status) return;
                state.$status.addClass('d-none').removeClass('text-success text-danger').text('');
                if (state.$updateBtn) state.$updateBtn.prop('disabled', false);
            }

            // Ключевая логика показа/скрытия кнопки/иконки/статуса
            function updateUiState() {
                const d = normalizeRu(state.$phone.val());
                const lenOK = d.length === 11;
                const confirmed = (state.originalVerified && d === state.originalDigits) || (state.phoneChangeVerified && d === state.originalDigits);

                if (!d) {
                    // Пусто: прячем всё, статус скрыт, кнопку "Обновить" включаем
                    hideStatus();
                    showVerifiedIcon(false);
                    showVerifyBtn(false);
                    return;
                }

                if (confirmed) {
                    // Подтверждённый текущий номер: иконка, зелёный статус, кнопки нет
                    setStatusConfirmed();
                    showVerifiedIcon(true);
                    showVerifyBtn(false);
                    return;
                }

                // Номер НЕ подтверждён (либо изменён, либо изначально не был подтверждён):
                setStatusConfirmNeeded();
                showVerifiedIcon(false);
                // Кнопка "Подтвердить" появляется ТОЛЬКО если 11 цифр и номер отличается от подтверждённого
                const canShowBtn = lenOK && (!state.originalVerified || d !== state.originalDigits);
                showVerifyBtn(canShowBtn, /*enabled*/ true);
            }

            // ---------- Verify flow ----------
            function wireVerifyFlow() {
                const {$phone, $verifyBtn} = state;
                if (!$phone || !$phone.length || !$verifyBtn || !$verifyBtn.length) return;

                state.originalDigits = normalizeRu($phone.attr('data-original') ?? $phone.val());
                state.originalVerified = (state.$status && state.$status.attr('data-verified') === '1');
                state.phoneChangeVerified = false;

                // первичный UI (если уже подтверждён — покажем иконку, спрячем кнопку)
                updateUiState();

                $verifyBtn.on('click', function () {
                    const d = normalizeRu($phone.val());
                    if (!d) {
                        alert('Поле телефона пустое. Чтобы удалить номер — просто нажмите «Обновить».');
                        return;
                    }

                    // Если уже подтверждённый номер — ничего не делаем
                    if (state.originalVerified && d === state.originalDigits) {
                        updateUiState();
                        return;
                    }

                    $.ajax({
                        url: state.sendCodeUrl,
                        method: 'POST',
                        data: {_token: state.csrf, phone: d},
                        success: function (res) {
                            if (res.alreadyVerified) {
                                // На случай гонок
                                state.originalVerified = true;
                                state.originalDigits = d;
                                updateUiState();
                                return;
                            }
                            if (state.$codeModal && state.$codeModal.length) {
                                state.$codeTarget.text(displayPhone());
                                state.$codeInput.val('');
                                state.$codeErr.addClass('d-none').text('');
                                state.$codeModal.modal('show');
                                state.$codeInput.trigger('focus');
                            } else {
                                alert('Код отправлен на ' + displayPhone());
                            }
                        },
                        error: function (xhr) {
                            alert(xhr?.responseJSON?.message || 'Не удалось отправить код.');
                        }
                    });
                });

                if (state.$codeResend && state.$codeResend.length) {
                    state.$codeResend.on('click', function () {
                        const d = normalizeRu($phone.val());
                        if (!d) return;
                        $.post(state.sendCodeUrl, {_token: state.csrf, phone: d});
                    });
                }

                if (state.$codeConfirm && state.$codeConfirm.length) {
                    state.$codeConfirm.on('click', function () {
                        const d = normalizeRu($phone.val());
                        const code = String(state.$codeInput.val() || '').trim();
                        if (!/^\d{4,8}$/.test(code)) {
                            state.$codeErr.removeClass('d-none').text('Введите корректный код.');
                            return;
                        }
                        $.ajax({
                            url: state.confirmCodeUrl,
                            method: 'POST',
                            data: {_token: state.csrf, phone: d, code: code},
                            success: function (res) {
                                state.phoneChangeVerified = true;
                                state.originalDigits = d;
                                state.originalVerified = true;
                                $phone.attr('data-original', d).attr('data-verified', '1');
                                if (state.$codeModal && state.$codeModal.length) state.$codeModal.modal('hide');

                                // после подтверждения: иконка visible, кнопка hide, статус зелёный
                                setStatusConfirmed(res?.verified_at || '');
                                showVerifiedIcon(true);
                                showVerifyBtn(false);
                            },
                            error: function (xhr) {
                                const msg = xhr?.responseJSON?.message || 'Неверный код.';
                                if (state.$codeErr && state.$codeErr.length) state.$codeErr.removeClass('d-none').text(msg);
                                else alert(msg);
                            }
                        });
                    });
                }
            }

            // ---------- Сабмит формы ----------
            function wireFormPatch() {
                if (!state.$form || !state.$form.length || !state.$phone || !state.$phone.length) return;
                state.$form.on('submit.phoneflow-patch', function () {
                    const d = normalizeRu(state.$phone.val());
                    if (!d) return true; // удалить номер — можно
                    const needsConfirm = ((d !== state.originalDigits) || !state.originalVerified) && !state.phoneChangeVerified;
                    if (needsConfirm) {
                        alert('Подтвердите новый номер через SMS перед сохранением.');
                        return false;
                    }
                    const formatted = state.$phone.val();
                    state.$phone.data('prev', formatted);
                    state.$phone.val(d);
                    setTimeout(() => {
                        state.$phone.val(state.$phone.data('prev'));
                    }, 0);
                    return true;
                });
            }

            function init(opts) {
                state.sendCodeUrl = opts.sendCodeUrl;
                state.confirmCodeUrl = opts.confirmCodeUrl;
                state.csrf = opts.csrf;

                state.$phone = $('#phone');
                state.$verifyBtn = $('#verify-phone-btn');
                state.$verifiedIcon = $('#phone-verified-icon');
                state.$updateBtn = $('.update-btn');
                state.$status = $('#phone-verify-status');
                state.$form = $('#userUpdateForm');

                state.$codeModal = $('#phoneCodeModal');
                state.$codeTarget = $('#code-target-phone');
                state.$codeInput = $('#phone-code-input');
                state.$codeErr = $('#phone-code-error');
                state.$codeResend = $('#resend-code-btn');
                state.$codeConfirm = $('#confirm-code-btn');

                state.$phone.on('complete incomplete cleared input keyup paste blur', updateUiState);

                wireVerifyFlow();
                updateUiState();
                wireFormPatch();
            }

            return {init, normalize: normalizeRu, format: displayPhone};
        })(jQuery);

        // запуск
        document.addEventListener('DOMContentLoaded', function () {
            window.PhoneFlow.init({
                sendCodeUrl: "{{ route('account.user.phoneSendCode', $user->id) }}",
                confirmCodeUrl: "{{ route('account.user.phoneConfirmCode', $user->id) }}",
                csrf: "{{ csrf_token() }}"
            });
        });
    </script>


    <script>


        document.addEventListener('DOMContentLoaded', function () {


            // 2fa
            // При включении чекбокса 2FA — простая клиентская проверка телефона
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

            // Клик по ИЗМЕНИТЬ ПАРОЛЬ
            function changePasswordBtn() {
                document.getElementById('change-password-btn').addEventListener('click', function () {
                    console.log(1);
                    document.getElementById('change-password-btn').style.display = 'none';
                    document.getElementById('change-pass-wrap').style.display = 'inline-block';
                });
            }

            // Клик по ПРИМЕНИТЬ ПАРОЛЬ
            function applyPasswordBtn() {
                document.getElementById('apply-password-btn').addEventListener('click', function () {
                    {{--var userId = '{{ $user->id }}';--}}
                    var newPassword = document.getElementById('new-password').value;
                    var token = '{{ csrf_token() }}';
                    var errorMessage = document.getElementById('error-message');

                    // Проверка длины пароля
                    if (newPassword.length < 8) {
                        errorMessage.style.display = 'block'; // Показываем сообщение об ошибке
                        return; // Прерываем выполнение, если пароль слишком короткий
                    } else {
                        errorMessage.style.display = 'none'; // Скрываем сообщение об ошибке
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

            // Функция для показа/скрытия пароля с помощью иконки глаза
            function showPassword() {
                const togglePassword = document.querySelector('.toggle-password');
                const passwordInput = document.getElementById('new-password');

                togglePassword.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);

                    // Меняем иконку глаза
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }

            //Обновление юзера
            function updateUserData2() {
                // Отслеживаем событие отправки формы
                $('#userUpdateForm').on('submit', function (e) {
                    e.preventDefault(); // Отключаем стандартную отправку формы


                    // Собираем данные формы
                    let formData = $(this).serialize();
                    console.log(formData);
                    console.log('123');

                    // AJAX-запрос
                    $.ajax({
                        // url: $(this).attr('action'),   // Используем URL из атрибута action
                        url: "{{ route('account.user.update') }}", // Явно указанный URL

                        type: 'PATCH',                 // Метод PATCH (Laravel примет, т.к. есть _method=patch)
                        data: formData,
                        success: function (response) {
                            showSuccessModal("Редактирование пользователя", "Пользователь успешно обновлен.", 1);
                        },
                        error: function (response) {
                            eroorRespone(response);
                        }
                    });
                });
            }

            function updateUserData() {
                $('#userUpdateForm')
                    .off('submit') // чтобы не плодились хэндлеры при повторных инициализациях
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
                            headers: { 'Accept': 'application/json' }, // важно для 422 в JSON
                            success: function () {
                                showSuccessModal("Редактирование пользователя", "Пользователь успешно обновлён.", 1);
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



@endsection
