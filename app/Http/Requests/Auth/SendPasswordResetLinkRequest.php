<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendPasswordResetLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'Email',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Укажите email.',
            'email.email' => 'Введите корректный email.',
            'email.max' => 'Поле «:attribute» не должно превышать :max символов.',
        ];
    }
}
