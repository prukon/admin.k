<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\Validator;

class AccountUpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => [
                'required',
                PasswordRule::min(8),
                'max:255',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'password' => 'Пароль',
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Введите новый пароль.',
            'password.min'      => 'Пароль должен быть не короче :min символов.',
            'password.max'      => 'Пароль не должен превышать :max символов.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $newPassword = (string) $this->input('password');
            $stored = $user?->getAuthPassword() ?? $user?->password;

            if (is_string($stored) && $stored !== '' && Hash::check($newPassword, $stored)) {
                $validator->errors()->add('password', 'Новый пароль совпадает с текущим.');
            }
        });
    }
}
