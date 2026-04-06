<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerSelfRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_title'     => ['required', 'string', 'max:255'],
            'name'             => ['required', 'string', 'max:255'],
            'email'            => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
                Rule::unique('partners', 'email')->whereNull('deleted_at'),
            ],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
            'recaptcha_token'  => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'school_title.required' => 'Укажите название школы.',
            'name.required'         => 'Укажите ваше имя.',
            'email.required'        => 'Укажите email.',
            'email.email'           => 'Введите корректный email.',
            'email.unique'          => 'Этот email уже используется.',
            'password.required'     => 'Укажите пароль.',
            'password.min'          => 'Пароль должен быть не короче :min символов.',
            'password.confirmed'    => 'Пароли не совпадают.',
            'recaptcha_token.required' => 'Не пройдена защита от спама. Обновите страницу и попробуйте ещё раз.',
        ];
    }
}
