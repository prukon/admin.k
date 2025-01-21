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
        // Определяем ID партнёра из роуты (Route Model Binding)
        $partnerId = $this->route('partner')->id;

        return [
            'business_type' => [
                'required',
//                'in:company,individual_entrepreneur',
            ],
            'title' => [
                'required',
                'string',
                'min:2',
                'max:255',
            ],

            // только цифры, макс 100 символов, + уникальность
            'tax_id' => [
                'nullable',
                'regex:/^[0-9]+$/',    // только цифры
                'max:100',
                'unique:partners,tax_id,' . $partnerId,
            ],

            // только цифры, макс 100 символов,
            'kpp' => [
                'nullable',
                'regex:/^[0-9]+$/',    // только цифры
                'max:100',
//                'unique:partners,tax_id,' . $partnerId,
            ],


            // только цифры, макс 100 символов, + уникальность
            'registration_number' => [
                'nullable',
                'regex:/^[0-9]+$/',    // только цифры
                'max:100',
                'unique:partners,registration_number,' . $partnerId,
            ],

            // адрес: просто строка, макс 250
            'address' => [
                'nullable',
                'string',
                'max:250',
            ],

            // телефон: только цифры, макс 25 символов
            'phone' => [
                'nullable',
                'regex:/^[0-9]+$/',
                'max:25',
            ],

            // email: обязательно, формат email, макс 50, + уникальность
            'email' => [
                'required',
                'email',
                'max:50',
                'unique:partners,email,' . $partnerId,
            ],

            // сайт: можно добавить url или string; оставим string + max (пример)
            'website' => [
                'nullable',
                'string',
                'max:255',
            ],

            // банк: только цифры, макс 25/50 и т.д. - в зависимости от вашей логики
            'bank_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'bank_bik' => [
                'nullable',
                'regex:/^[0-9]+$/',
                'max:25',
            ],
            'bank_account' => [
                'nullable',
                'regex:/^[0-9]+$/',
                'max:50',
            ],
        ];
    }

    /**
     * Локализованные названия полей.
     */
    public function attributes(): array
    {
        return [
            'business_type'       => 'Тип бизнеса',
            'title'               => 'Наименование',
            'tax_id'              => 'ИНН',
            'kpp'                 => 'КПП',
            'registration_number' => 'Регистрационный номер',
            'address'             => 'Адрес',
            'phone'               => 'Телефон',
            'email'               => 'E-mail',
            'website'             => 'Сайт',
            'bank_name'           => 'Наименование банка',
            'bank_bik'            => 'БИК',
            'bank_account'        => 'Расчетный счет',

        ];
    }

    /**
     * Сообщения об ошибках.
     */
    public function messages(): array
    {
        return [
            // business_type
            'business_type.required' => 'Поле «:attribute» обязательно для заполнения.',
//            'business_type.in'       => 'Поле «:attribute» должно быть либо «company», либо «individual_entrepreneur».',

            // title
            'title.required' => 'Поле «:attribute» обязательно для заполнения.',
            'title.string'   => 'Поле «:attribute» должно быть строкой.',
            'title.min'      => 'Поле «:attribute» должно содержать не менее :min символов.',
            'title.max'      => 'Поле «:attribute» не может превышать :max символов.',

            // tax_id
            'tax_id.regex'   => 'Поле «:attribute» может содержать только цифры.',
            'tax_id.max'     => 'Поле «:attribute» не может превышать :max символов.',
            'tax_id.unique'  => 'Указанный «:attribute» уже зарегистрирован в системе.',

            // kpp
            'kpp.regex'   => 'Поле «:attribute» может содержать только цифры.',
            'kpp.max'     => 'Поле «:attribute» не может превышать :max символов.',

            // registration_number
            'registration_number.regex'  => 'Поле «:attribute» может содержать только цифры.',
            'registration_number.max'    => 'Поле «:attribute» не может превышать :max символов.',
            'registration_number.unique' => 'Указанный «:attribute» уже зарегистрирован в системе.',

            // address
            'address.string' => 'Поле «:attribute» должно быть строкой.',
            'address.max'    => 'Поле «:attribute» не может превышать :max символов.',

            // phone
            'phone.regex' => 'Поле «:attribute» может содержать только цифры.',
            'phone.max'   => 'Поле «:attribute» не может превышать :max символов.',

            // email
            'email.required' => 'Поле «:attribute» обязательно для заполнения.',
            'email.email'    => 'Поле «:attribute» должно содержать корректный E-mail адрес.',
            'email.max'      => 'Поле «:attribute» не может превышать :max символов.',
            'email.unique'   => 'Указанный «:attribute» уже используется.',

            // website
            'website.string' => 'Поле «:attribute» должно быть строкой.',
            'website.max'    => 'Поле «:attribute» не может превышать :max символов.',

            // bank_name
            'bank_name.string' => 'Поле «:attribute» должно быть строкой.',
            'bank_name.max'    => 'Поле «:attribute» не может превышать :max символов.',

            // bank_bik
            'bank_bik.regex' => 'Поле «:attribute» может содержать только цифры.',
            'bank_bik.max'   => 'Поле «:attribute» не может превышать :max символов.',

            // bank_account
            'bank_account.regex' => 'Поле «:attribute» может содержать только цифры.',
            'bank_account.max'   => 'Поле «:attribute» не может превышать :max символов.',
        ];
    }
}
