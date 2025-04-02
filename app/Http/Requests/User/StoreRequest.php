<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:20',
            'birthday' => 'nullable|date',
            'team_id' => 'nullable|string',
            'start_date' => 'nullable|date',
//            'image' => '',
//            'email' => 'required|string|email|max:255',
            'email' => 'required|string|email|max:255|unique:users,email', // Поле email обязательно, должно быть строкой, соответствовать формату email и не превышать 255 символов

//            'role' => 'required|string|max:255',  // Поле role обязательно и должно быть строкой не более 255 символов
            'password' => 'required|string|min:8|max:255',  // Поле password обязательно, строка, минимальная длина 8 символов
            'is_enabled' => 'boolean',  // Поле is_enabled должно быть булевым значением
            'role_id'    => 'required|integer|exists:roles,id',

        ];
    }

    public function attributes()
    {
        return [
            'name'       => 'Имя',
            'email'      => 'Email',
            'password'   => 'Пароль',
            'birthday'   => 'Дата рождения',
            'start_date' => 'Дата начала',
            'team_id'    => 'Группа',
            'is_enabled' => 'Активность',
            'role_id'    => 'Роль',
        ];
    }

    public function messages()
    {
        return [
            // Сообщения для поля name
            'name.required' => 'Пожалуйста, укажите имя.',
            'name.string'   => 'Имя должно быть строкой.',
            'name.max'      => 'Имя не должно превышать :max символов.',

            // Сообщения для поля email
            'email.required' => 'Пожалуйста, введите адрес электронной почты.',
            'email.email'    => 'Введите корректный адрес электронной почты.',
            'email.unique'   => 'Этот адрес электронной почты уже зарегистрирован.',

            // Сообщения для поля password
            'password.required' => 'Пожалуйста, введите пароль.',
            'password.string'   => 'Пароль должен быть строкой.',
            'password.min'      => 'Пароль должен содержать не менее :min символов.',
            'password.max'      => 'Пароль не должен превышать :max символов.',

            // Сообщения для поля is_enabled
            'is_enabled.boolean' => 'Поле «Активность» должно быть булевым значением («1» или «0»).',

            // Сообщения для role_id
            'role_id.required' => 'Пожалуйста, выберите роль.',
            'role_id.integer'  => 'Некорректный формат роли.',
            'role_id.exists'   => 'Выбранная роль не существует в базе.',
        ];
    }
}
