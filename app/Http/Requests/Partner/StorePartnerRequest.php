<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Проверяем, имеет ли пользователь право создавать партнёра
        return $this->user()->can('manage-groups');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'business_type'       => 'required|in:company,individual_entrepreneur,non_commercial_organization,physical_person',
            'title'               => 'required|string|max:255',
            'tax_id'              => 'nullable|string|max:12',
            'kpp'                 => 'nullable|string|max:9',
            'registration_number' => 'nullable|string|max:20',
            'address'             => 'nullable|string|max:255',
            'phone'               => 'nullable|string|max:20',
            'email'               => 'required|email|max:255|unique:partners,email',
            'website'             => 'nullable|url|max:255',
            'bank_name'           => 'nullable|string|max:255',
            'bank_bik'            => 'nullable|string|max:9',
            'bank_account'        => 'nullable|string|max:20',
            'order_by'            => 'nullable|integer',
            'is_enabled'          => 'required|boolean',
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            // business_type
            'business_type.required'   => 'Поле «Тип бизнеса» обязательно для заполнения.',
            'business_type.in'         => 'Выбран некорректный тип бизнеса.',

            // title
            'title.required'           => 'Поле «Наименование» обязательно для заполнения.',
            'title.string'             => 'Поле «Наименование» должно быть строкой.',
            'title.max'                => 'Поле «Наименование» не должно превышать :max символов.',

            // tax_id
            'tax_id.string'            => 'Поле «ИНН» должно быть строкой.',
            'tax_id.max'               => 'Поле «ИНН» не должно превышать :max символов.',

            // kpp
            'kpp.string'               => 'Поле «КПП» должно быть строкой.',
            'kpp.max'                  => 'Поле «КПП» не должно превышать :max символов.',

            // registration_number
            'registration_number.string' => 'Поле «ОГРН (ОГРНИП)» должно быть строкой.',
            'registration_number.max'    => 'Поле «ОГРН (ОГРНИП)» не должно превышать :max символов.',

            // address
            'address.string'           => 'Поле «Почтовый адрес» должно быть строкой.',
            'address.max'              => 'Поле «Почтовый адрес» не должно превышать :max символов.',

            // phone
            'phone.string'             => 'Поле «Телефон» должно быть строкой.',
            'phone.max'                => 'Поле «Телефон» не должно превышать :max символов.',

            // email
            'email.required'           => 'Поле «E-mail партнёра» обязательно для заполнения.',
            'email.email'              => 'Поле «E-mail партнёра» должно быть корректным e-mail адресом.',
            'email.max'                => 'Поле «E-mail партнёра» не должно превышать :max символов.',
            'email.unique'             => 'Партнёр с таким «E-mail партнёра» уже существует.',

            // website
            'website.url'              => 'Поле «Сайт» должно быть корректным URL.',
            'website.max'              => 'Поле «Сайт» не должно превышать :max символов.',

            // bank_name
            'bank_name.string'         => 'Поле «Наименование банка» должно быть строкой.',
            'bank_name.max'            => 'Поле «Наименование банка» не должно превышать :max символов.',

            // bank_bik
            'bank_bik.string'          => 'Поле «БИК» должно быть строкой.',
            'bank_bik.max'             => 'Поле «БИК» не должно превышать :max символов.',

            // bank_account
            'bank_account.string'      => 'Поле «Расчетный счет» должно быть строкой.',
            'bank_account.max'         => 'Поле «Расчетный счет» не должно превышать :max символов.',

            // order_by
            'order_by.integer'         => 'Поле «Сортировка» должно быть целым числом.',

            // is_enabled
            'is_enabled.required'      => 'Поле «Активность» обязательно для заполнения.',
            'is_enabled.boolean'       => 'Поле «Активность» должно быть булевым (0 или 1).',
        ];
    }

    /**
     * Attribute names for better error messages.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'business_type'       => 'Тип бизнеса',
            'title'               => 'Наименование',
            'tax_id'              => 'ИНН',
            'kpp'                 => 'КПП',
            'registration_number' => 'ОГРН (ОГРНИП)',
            'address'             => 'Почтовый адрес',
            'phone'               => 'Телефон',
            'email'               => 'E-mail партнёра',
            'website'             => 'Сайт',
            'bank_name'           => 'Наименование банка',
            'bank_bik'            => 'БИК',
            'bank_account'        => 'Расчетный счет',
            'order_by'            => 'Сортировка',
            'is_enabled'          => 'Активность',
        ];
    }
}
