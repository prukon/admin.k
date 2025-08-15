<?php
// app/Http/Requests/User/UpdatePasswordRequest.php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // дублируем проверку права на уровне запроса
//        return $this->user()?->can('users-password-update') === true;
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => [
                'required',
                PasswordRule::min(8), // при желании добавьте ->letters()->numbers()->mixedCase()->symbols()
                // при необходимости можно нарастить сложность:
                // ->letters()      // хотя бы 1 буква
                // ->mixedCase()    // и верхний, и нижний регистр
                // ->numbers()      // хотя бы 1 цифра
                // ->symbols()      // хотя бы 1 символ
                // ->uncompromised()// не встречался в базах утечек (HIBP)
                'max:255',
            ],
        ];
    }

    public function attributes(): array
    {
        return ['password' => 'Пароль'];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Введите новый пароль.',
            'password.min'      => 'Пароль должен быть не короче :min символов.',
            'password.max'      => 'Пароль не должен превышать :max символов.',
        ];
    }
}
