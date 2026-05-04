<?php

namespace App\Http\Requests\User;

use App\Models\UserField;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
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
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:25',
            'lastname'    => 'required|string|max:25',
            'birthday'    => 'nullable|date',
            'team_id'     => 'nullable|integer|exists:teams,id', // было string — ставим integer + exists
            'start_date'  => 'nullable|date',

            'email'       => 'nullable|email|max:255|unique:users,email',
            'password'    => 'nullable|string|min:8|max:255',

            'is_enabled'  => 'sometimes|boolean', // чекбокс может не прийти
            'role_id'     => 'required|integer|exists:roles,id',

            'custom'               => 'nullable|array',
            'custom.*'             => 'nullable|string|max:255',
        ];
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
            'team_id'    => 'Группа',
            'is_enabled' => 'Активность',
            'role_id'    => 'Роль',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
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

            'team_id.integer'   => 'Некорректный формат группы.',
            'team_id.exists'    => 'Выбранная группа не существует в базе.',
        ];
    }
}
