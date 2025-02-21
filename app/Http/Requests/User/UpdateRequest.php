<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Определяет, может ли пользователь выполнять этот запрос.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Получает правила валидации для запроса.
     */
    public function rules(): array
    {
        // Получаем ID пользователя из параметра маршрута
        $userId = $this->route('user')->id ?? null;

        return [
            'birthday' => 'nullable|date|before_or_equal:today',
            'email' => 'required|string|email|max:255|unique:users,email,' . $userId,
            'custom' => 'nullable|array',
            'custom.*' => 'nullable|string|max:255',
        ];
    }

    /**
     * Определяет, какие имена будут отображаться в сообщениях об ошибках.
     */
    public function attributes(): array
    {
        return [
            'birthday' => 'Дата рождения',
            'email' => 'Email',
            'custom' => 'Пользовательские поля',
            'custom.*' => 'Пользовательское поле',
        ];
    }

    /**
     * Сообщения об ошибках валидации.
     */
    public function messages(): array
    {
        return [
            // Поле "Дата рождения"
            'birthday.date' => 'Поле "Дата рождения" должно быть корректной датой.',
            'birthday.before_or_equal' => 'Поле "Дата рождения" не может быть в будущем.',

            // Поле "Email"
            'email.required' => 'Поле "Email" обязательно для заполнения.',
            'email.string' => 'Поле "Email" должно быть строкой.',
            'email.email' => 'Поле "Email" должно быть действительным адресом электронной почты.',
            'email.max' => 'Поле "Email" не должно превышать :max символов.',
            'email.unique' => 'Этот email уже используется.',

            // Поле "custom"
            'custom.array' => 'Ошибка передачи пользовательских полей.',
            'custom.*.string' => 'Пользовательское поле должно быть строкой.',
            'custom.*.max' => 'Пользовательское поле не должно превышать :max символов.',
        ];
    }
}
