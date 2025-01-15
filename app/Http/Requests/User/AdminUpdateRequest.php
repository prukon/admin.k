<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => 'required|string|max:50',
            'birthday' => [
                'nullable',
                'date',
                'before_or_equal:today', // Или 'before_or_equal:today'
            ],
            'team_id' => 'nullable|string',
            'start_date' => [
                'nullable',
                'date',
                'before_or_equal:2030-12-31', // Или 'before_or_equal:today'
            ],
            'email' => 'required|string|email|max:50|unique:users,email,' . $userId,
            'is_enabled' => 'boolean',
            'custom.*' => 'nullable|string|max:255', // Замените правила в зависимости от типа данных
            'role' => 'required|string|max:12',

        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Имя',
            'birthday' => 'Дата рождения',
            'team_id' => 'Группа',
            'start_date' => 'Дата начала занятий',
            'email' => 'Email',
            'is_enabled' => 'Активность',
            'role' => 'Роль',
        ];
    }
    public function messages()
    {
        return [
            // Поле "Имя"
            'name.required' => 'Поле "Имя" обязательно для заполнения.',
            'name.string' => 'Поле "Имя" должно быть строкой.',
            'name.max' => 'Поле "Имя" не должно превышать :max символов.',

            // Поле "Дата рождения"
            'birthday.date' => 'Поле "Дата рождения" должно быть корректной датой.',
            'birthday.before_or_equal' => 'Поле "Дата рождения" не может быть позднее сегодняшнего дня.',

            // Поле "Группа"
            'team_id.string' => 'Поле "Группа" должно быть строкой.',

            // Поле "Дата начала занятий"
            'start_date.date' => 'Поле "Дата начала занятий" должно быть корректной датой.',
            'start_date.before_or_equal' => 'Поле "Дата начала занятий" должно быть корректной датой.',


            // Поле "Email"
            'email.required' => 'Поле "Email" обязательно для заполнения.',
            'email.string' => 'Поле "Email" должно быть строкой.',
            'email.email' => 'Поле "Email" должно быть действительным адресом электронной почты.',
            'email.max' => 'Поле "Email" не должно превышать :max символов.',
            'email.unique' => 'Этот email уже используется.',

            // Поле "Активность"
            'is_enabled.boolean' => 'Поле "Активность" должно быть истинным или ложным.',

            // Поле "Роль"
            'role.boolean' => 'Выберите поле "Права" из списка',
        ];
    }
}
