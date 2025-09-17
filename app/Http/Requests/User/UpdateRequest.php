<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Models\Setting;

class UpdateRequest extends FormRequest
{
    /**
     * Разрешаем запрос.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Препроцесс входных данных.
     *
     * - Не меняем two_factor_enabled, если поле не пришло (оставляем текущее из БД).
     * - Если пришло — нормализуем к 0/1.
     * - Нормализуем is_enabled к bool.
     * - Логируем ключевые моменты.
     */
    protected function prepareForValidation(): void
    {
        $targetUser = $this->route('user'); // редактируемый пользователь (Model Binding)
        $incomingHasTfa = $this->has('two_factor_enabled');

        $resolvedTwoFactorEnabled = $incomingHasTfa
            ? (int)!!$this->input('two_factor_enabled')
            : (int)($targetUser ? $targetUser->two_factor_enabled : 0);

        // Нормализуем is_enabled (чекбокс может не прийти)
        $resolvedIsEnabled = $this->boolean('is_enabled');

        $this->merge([
            'two_factor_enabled' => $resolvedTwoFactorEnabled,
            'is_enabled' => $resolvedIsEnabled,
        ]);

//        Log::info('UpdateRequest: prepareForValidation', [
//            'editor_user_id'        => optional($this->user())->id,
//            'target_user_id'        => optional($targetUser)->id,
//            'incoming_has_tfa'      => $incomingHasTfa,
//            'incoming_tfa_raw'      => $this->input('two_factor_enabled'),
//            'resolved_two_factor'   => $resolvedTwoFactorEnabled,
//            'resolved_is_enabled'   => $resolvedIsEnabled,
//        ]);


        // Нормализация телефона (только если поле пришло и у редактора есть право)
        if ($this->user() ?->can('users-phone-update') && $this->has('phone')) {
        $normalized = $this->normalizeRuPhone($this->input('phone'));
        $this->merge([
            'phone' => $normalized, // либо null, если номер пуст/некорректен
        ]);
    }


    }

    /**
     * Правила валидации.
     */
    public function rules(): array
    {
        $targetUser = $this->route('user');
        $targetUserId = is_object($targetUser) && method_exists($targetUser, 'getKey')
            ? $targetUser->getKey()
            : (int)$targetUser;

        $rules = [
            'custom.*' => ['nullable', 'string', 'max:255'],
            // 2FA (булево)
            'two_factor_enabled' => ['nullable', 'boolean'],
        ];

        if ($this->user()->can('users-name-update')) {
            $rules['name'] = ['required', 'string', 'max:30'];
            $rules['lastname'] = ['required', 'string', 'max:30'];
        }

        if ($this->user()->can('users-birthdate-update')) {
            $rules['birthday'] = ['nullable', 'date', 'before_or_equal:today'];
        }

        if ($this->user()->can('users-group-update')) {
            $rules['team_id'] = ['sometimes', 'nullable', 'integer', 'exists:teams,id'];
        }

        if ($this->user()->can('users-startDate-update')) {
            $rules['start_date'] = ['nullable', 'date'];
        }

        if ($this->user()->can('users-email-update')) {
            $rules['email'] = ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($targetUserId),];
        }

        if ($this->user()->can('users-phone-update')) {
            $rules['phone'] = ['sometimes','nullable','regex:/^\+7\d{10}$/'];

        }

        if ($this->user()->can('users-activity-update')) {
            $rules['is_enabled'] = ['sometimes', 'boolean'];
        }

        if ($this->user()->can('users-role-update')) {
            $rules['role_id'] = ['sometimes', 'required', 'integer', 'exists:roles,id'];
        }


        return $rules;
    }

    /**
     * Дополнительные проверки.
     *
     * - Если глобалка force_2fa_admins включена и редактируемый пользователь — админ (role_id = 10),
     *   то запрещаем попытку выключить 2FA.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($afterValidator) {
            $targetUser = $this->route('user');
            if (!$targetUser) {
                return;
            }

            // Читаем глобалку force_2fa_admins (partner_id = NULL)
            try {
                if (method_exists(Setting::class, 'getBool')) {
                    $forceTwoFactorForAdmins = Setting::getBool('force_2fa_admins', false, null);
                } else {
                    $row = DB::table('settings')
                        ->where('name', 'force_2fa_admins')
                        ->whereNull('partner_id')
                        ->first(['status']);
                    $forceTwoFactorForAdmins = $row ? (bool)$row->status : false;
                }
            } catch (\Throwable $exception) {
                Log::error('AdminUpdateRequest: failed to read force_2fa_admins', [
                    'error' => $exception->getMessage(),
                ]);
                $forceTwoFactorForAdmins = false;
            }

            $incomingTwoFactorEnabled = $this->boolean('two_factor_enabled');
            $isTargetUserAdmin = ((int)$targetUser->role_id === 10);

            Log::info('AdminUpdateRequest: after validator check', [
                'target_user_id' => $targetUser->id,
                'is_target_user_admin' => $isTargetUserAdmin,
                'force_two_factor_for_admins' => $forceTwoFactorForAdmins,
                'incoming_two_factor' => $incomingTwoFactorEnabled,
                'current_two_factor' => (bool)$targetUser->two_factor_enabled,
            ]);

            if ($isTargetUserAdmin && $forceTwoFactorForAdmins && $incomingTwoFactorEnabled === false) {
                $afterValidator->errors()->add(
                    'two_factor_enabled',
                    'Для администраторов 2FA обязательна согласно общей настройке.'
                );
            }
        });
    }

    /**
     * Подписи полей.
     */
    public function attributes(): array
    {
        return [
            'name' => 'Имя',
            'lastname' => 'Фамилия',
            'birthday' => 'Дата рождения',
            'team_id' => 'Группа',
            'start_date' => 'Дата начала занятий',
            'email' => 'Email',
            'phone' => 'Телефон',
            'is_enabled' => 'Активность',
            'role_id' => 'Роль',
            'two_factor_enabled' => 'Двухфакторная аутентификация',
        ];
    }

    /**
     * Сообщения об ошибках.
     */
    public function messages(): array
    {
        return [
            // Имя
            'name.required' => 'Поле "Имя" обязательно для заполнения.',
            'name.string' => 'Поле "Имя" должно быть строкой.',
            'name.max' => 'Поле "Имя" не должно превышать :max символов.',

            // Фамилия
            'lastname.required' => 'Поле "Фамилия" обязательно для заполнения.',
            'lastname.string' => 'Поле "Фамилия" должно быть строкой.',
            'lastname.max' => 'Поле "Фамилия" не должно превышать :max символов.',


            // Дата рождения
            'birthday.date' => 'Поле "Дата рождения" должно быть корректной датой.',
            'birthday.before_or_equal' => 'Поле "Дата рождения" не может быть позднее сегодняшнего дня.',

            // Дата начала занятий
            'start_date.date' => 'Поле "Дата начала занятий" должно быть корректной датой.',


            // Группа
            'team_id.integer' => 'Поле "Группа" должно быть числом (ID группы).',
            'team_id.exists' => 'Выбранная группа не существует в базе.',

            // Email
            'email.required' => 'Поле "Email" обязательно для заполнения.',
            'email.string' => 'Поле "Email" должно быть строкой.',
            'email.email' => 'Поле "Email" должно быть действительным адресом электронной почты.',
            'email.max' => 'Поле "Email" не должно превышать :max символов.',
            'email.unique' => 'Этот email уже используется.',

            // Телефон
            'phone.regex'   => 'Поле "Телефон" должно быть российским номером в формате +7XXXXXXXXXX (11 цифр).',



            // Активность
            'is_enabled.boolean' => 'Поле "Активность" должно быть истинным или ложным.',

            // Роль
            'role_id.integer' => 'Некорректный формат роли.',
            'role_id.exists' => 'Выбранная роль не существует в базе.',

            // 2FA
            'two_factor_enabled.boolean' => 'Некорректное значение поля 2FA.',
        ];
    }

    /**
     * Приводит российский номер к канону: +7XXXXXXXXXX (или null, если не удаётся привести).
     */
    private function normalizeRuPhone(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string)$input);
        if ($digits === '' || $digits === null) {
            return null;
        }

        // 8XXXXXXXXXX -> 7XXXXXXXXXX
        if (str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        // если не начинается с 7 — подставим 7 (на случай, если прислали только 10 цифр без кода страны)
        if (!str_starts_with($digits, '7')) {
            $digits = '7' . $digits;
        }

        // Ровно 11 цифр
        $digits = substr($digits, 0, 11);
        if (strlen($digits) !== 11 || !str_starts_with($digits, '7')) {
            return null; // невалидно
        }

        return '+7' . substr($digits, 1); // канон: +7XXXXXXXXXX
    }

}
