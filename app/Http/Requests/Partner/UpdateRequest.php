<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Определяем, авторизован ли пользователь.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $partnerId = $this->route('partner')->id;

        return [
            'title' => [
                'required',
                'string',
                'min:2',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'regex:/^[0-9\(\)\-\+\s]+$/',
                'max:25',
            ],
            'email' => [
                'required',
                'email',
                'max:50',
                'unique:partners,email,' . $partnerId,
            ],
            'website' => [
                'nullable',
                'string',
                'max:255',
            ],
            'sms_name' => [
                'nullable',
                'string',
                'max:14',
            ],
        ];
    }

    /**
     * Локализованные названия полей.
     */
    public function attributes(): array
    {
        return [
            'title'    => 'Название школы/секции',
            'phone'    => 'Телефон',
            'email'    => 'E-mail',
            'website'  => 'Сайт',
            'sms_name' => 'Название для SMS/выписок',
        ];
    }

    /**
     * Сообщения об ошибках.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Поле «:attribute» обязательно для заполнения.',
            'title.string'   => 'Поле «:attribute» должно быть строкой.',
            'title.min'      => 'Поле «:attribute» должно содержать не менее :min символов.',
            'title.max'      => 'Поле «:attribute» не может превышать :max символов.',

            'phone.regex' => 'Поле «:attribute» может содержать только цифры, пробелы и символы + ( ) -.',
            'phone.max'   => 'Поле «:attribute» не может превышать :max символов.',

            'email.required' => 'Поле «:attribute» обязательно для заполнения.',
            'email.email'    => 'Поле «:attribute» должно содержать корректный E-mail адрес.',
            'email.max'      => 'Поле «:attribute» не может превышать :max символов.',
            'email.unique'   => 'Указанный «:attribute» уже используется.',

            'website.string' => 'Поле «:attribute» должно быть строкой.',
            'website.max'    => 'Поле «:attribute» не может превышать :max символов.',
        ];
    }
}
