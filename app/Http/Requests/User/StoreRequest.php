<?php

namespace App\Http\Requests\User;

use App\Http\Requests\User\Concerns\ForbidsSuperadminRole;
use App\Http\Requests\User\Concerns\ValidatesStudentCommentAndSex;
use App\Http\Requests\User\Concerns\ValidatesStudentHealthFields;
use App\Http\Requests\User\Concerns\ValidatesStudentParent;
use App\Models\UserField;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    use ForbidsSuperadminRole;
    use ValidatesStudentCommentAndSex;
    use ValidatesStudentHealthFields;
    use ValidatesStudentParent;
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Пустая строка из формы → null: в БД храним NULL, каст password «hashed» не должен хешировать пустоту.
     */
    protected function prepareForValidation(): void
    {
        $password = $this->input('password');
        if ($password === null || trim((string) $password) === '') {
            $this->merge(['password' => null]);
        }

        // Телефон: приводим к канону +7XXXXXXXXXX (только если у текущего есть право)
        if ($this->has('phone')) {
            if ($this->user() ?->can('users.phone.update')) {
                $this->merge([
                    'phone' => $this->normalizeRuPhone($this->input('phone')),
                ]);
            } else {
                // Если права нет — вообще не учитываем входящий телефон
                $this->offsetUnset('phone');
            }
        }

        if ($this->has('school_lead_id') && $this->input('school_lead_id') === '') {
            $this->merge(['school_lead_id' => null]);
        }

        if ($this->has('team_ids')) {
            $ids = $this->input('team_ids');
            $ids = is_array($ids) ? $ids : [];
            $this->merge([
                'team_ids' => array_values(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)),
            ]);
        }

        $this->prepareStudentParentForValidation();
        $this->prepareStudentHealthFieldsForValidation();
        $this->prepareStudentCommentAndSexForValidation();
    }

    public function rules(): array
    {
        $partnerId = app(PartnerContext::class)->partnerId();

        $rules = [
            'name'        => 'required|string|max:25',
            'lastname'    => 'required|string|max:25',
            'birthday'    => 'nullable|date',
            'team_ids'    => 'nullable|array',
            'team_ids.*'  => ['integer', 'min:1'],
            'start_date'  => 'nullable|date',

            'email'       => 'nullable|email|max:255|unique:users,email',
            'password'    => 'nullable|string|min:8|max:255',

            'is_enabled'  => 'sometimes|boolean', // чекбокс может не прийти
            'role_id'     => 'required|integer|exists:roles,id',

            'custom'               => 'nullable|array',
            'custom.*'             => 'nullable|string|max:255',

        ];

        $rules = array_merge(
            $rules,
            $this->studentParentRules(),
            $this->studentHealthFieldRules(),
            $this->studentCommentAndSexRules(),
        );

        if ($partnerId) {
            $rules['team_ids.*'][] = Rule::exists('teams', 'id')->where(
                fn ($query) => $query->where('partner_id', $partnerId)
            );

            $rules['school_lead_id'] = [
                'nullable',
                'integer',
                Rule::exists('school_leads', 'id')->where(function ($query) use ($partnerId) {
                    $query->where('partner_id', $partnerId)
                        ->whereNull('user_id')
                        ->whereNull('deleted_at');
                }),
            ];
        }

        if ($this->user() ?->can('users.phone.update')) {
            $rules['phone'] = ['sometimes', 'nullable', 'regex:/^\+7\d{10}$/'];
        }

        return $rules;
    }

    public function attributes()
    {
        return [
            'name'       => 'Имя',
            'lastname'   => 'Фамилия',
            'email'      => 'Email',
            'password'   => 'Пароль',
            'birthday'   => 'Дата рождения',
            'start_date' => 'Дата начала',
            'team_ids'       => 'Группы',
            'team_ids.*'     => 'Группа',
            'is_enabled' => 'Активность',
            'role_id'        => 'Роль',
            'phone'          => 'Телефон',
            'school_lead_id' => 'Заявка с сайта',
        ] + $this->studentParentAttributes()
            + $this->studentHealthFieldAttributes()
            + $this->studentCommentAndSexAttributes();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->forbidSuperadminRoleAssignment($validator);

            $custom = $this->input('custom');
            if (!is_array($custom) || $custom === []) {
                return;
            }

            $partnerId = app(PartnerContext::class)->partnerId();
            if (!$partnerId) {
                return;
            }

            $allowedSlugs = UserField::query()
                ->where('partner_id', $partnerId)
                ->pluck('slug')
                ->all();

            foreach (array_keys($custom) as $slug) {
                if (!in_array($slug, $allowedSlugs, true)) {
                    $validator->errors()->add(
                        'custom.' . $slug,
                        'Указано недопустимое дополнительное поле.'
                    );
                }
            }
        });
    }

    public function messages()
    {
        return [
            'name.required'     => 'Пожалуйста, укажите имя.',
            'name.string'       => 'Имя должно быть строкой.',
            'name.max'          => 'Имя не должно превышать :max символов.',

            'lastname.required'     => 'Пожалуйста, укажите фамилию.',
            'lastname.string'       => 'Фамилия должна быть строкой.',
            'lastname.max'          => 'Фамилия не должно превышать :max символов.',

            'email.email'       => 'Введите корректный адрес электронной почты.',
            'email.unique'      => 'Этот адрес электронной почты уже зарегистрирован.',

            'password.string'   => 'Пароль должен быть строкой.',
            'password.min'      => 'Пароль должен содержать не менее :min символов.',
            'password.max'      => 'Пароль не должен превышать :max символов.',

            'is_enabled.boolean'=> 'Поле «Активность» должно быть булевым значением («1» или «0»).',

            'role_id.required'  => 'Пожалуйста, выберите роль.',
            'role_id.integer'   => 'Некорректный формат роли.',
            'role_id.exists'    => 'Выбранная роль не существует в базе.',

            'team_ids.array'    => 'Некорректный формат списка групп.',
            'team_ids.*.integer'=> 'Некорректный формат группы.',
            'team_ids.*.exists' => 'Выбранная группа не существует в базе.',


            'phone.regex'       => 'Поле "Телефон" должно быть российским номером в формате +7XXXXXXXXXX (11 цифр).',

            'school_lead_id.integer' => 'Некорректный идентификатор заявки.',
            'school_lead_id.exists'  => 'Заявка не найдена, уже привязана к клиенту или недоступна.',
        ] + $this->studentParentMessages()
            + $this->studentHealthFieldMessages()
            + $this->studentCommentAndSexMessages();
    }

    /**
     * Приводит российский номер к канону: +7XXXXXXXXXX (или null, если не удаётся привести).
     */
    private function normalizeRuPhone(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);
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
