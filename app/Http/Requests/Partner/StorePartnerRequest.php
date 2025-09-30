<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    public function authorize()
    {
        // Дай свою политику, оставляю как у тебя
        return $this->user()->can('manage-groups');
    }

    /**
     * Готовим данные: если website без схемы — добавим http://
     */
    protected function prepareForValidation()
    {
        if ($this->filled('website') && !preg_match('#^https?://#i', $this->website)) {
            $this->merge(['website' => 'http://' . $this->website]);
        }
    }

    public function rules()
    {
        return [
            'business_type'       => 'required|in:company,individual_entrepreneur,non_commercial_organization,physical_person',
            'title'               => 'required|string|max:255',
            'tax_id'              => 'nullable|string|max:12',
            'kpp'                 => 'nullable|string|max:9',
            'registration_number' => 'nullable|string|max:20',

            // НОВЫЕ ПОЛЯ
            'sms_name'            => 'nullable|string|max:14',
            'city'                => 'nullable|string|max:100',
            'zip'                 => 'nullable|string|max:20|regex:/^\d{6}$/',
            'address'             => 'nullable|string|max:255', // лейбл «Адрес»

            'phone'               => 'nullable|string|max:20',
            'email'               => 'required|email|max:255|unique:partners,email',
            'website'             => 'nullable|url|max:255',

            'bank_name'           => 'nullable|string|max:255',
            'bank_bik'            => 'nullable|string|max:9',
            'bank_account'        => 'nullable|string|max:20',

            'order_by'            => 'nullable|integer',
            'is_enabled'          => 'required|boolean',

            // CEO JSON
            'ceo'                 => 'nullable|array',
            'ceo.last_name'       => 'nullable|string|max:100',
            'ceo.first_name'      => 'nullable|string|max:100',
            'ceo.middle_name'     => 'nullable|string|max:100',
            'ceo.phone'           => 'nullable|string|max:20',
        ];
    }

    public function messages()
    {
        return [
            'business_type.required'   => 'Поле «Тип бизнеса» обязательно для заполнения.',
            'business_type.in'         => 'Выбран некорректный тип бизнеса.',

            'title.required'           => 'Поле «Наименование» обязательно для заполнения.',
            'title.string'             => 'Поле «Наименование» должно быть строкой.',
            'title.max'                => 'Поле «Наименование» не должно превышать :max символов.',

            'tax_id.string'            => 'Поле «ИНН» должно быть строкой.',
            'tax_id.max'               => 'Поле «ИНН» не должно превышать :max символов.',

            'kpp.string'               => 'Поле «КПП» должно быть строкой.',
            'kpp.max'                  => 'Поле «КПП» не должно превышать :max символов.',

            'registration_number.string' => 'Поле «ОГРН (ОГРНИП)» должно быть строкой.',
            'registration_number.max'    => 'Поле «ОГРН (ОГРНИП)» не должно превышать :max символов.',

            'website.url'              => 'Поле «Сайт» должно быть корректным URL.',
            'website.max'              => 'Поле «Сайт» не должно превышать :max символов.',

            'bank_name.string'         => 'Поле «Наименование банка» должно быть строкой.',
            'bank_name.max'            => 'Поле «Наименование банка» не должно превышать :max символов.',

            'bank_bik.string'          => 'Поле «БИК» должно быть строкой.',
            'bank_bik.max'             => 'Поле «БИК» не должно превышать :max символов.',

            'bank_account.string'      => 'Поле «Расчётный счёт» должно быть строкой.',
            'bank_account.max'         => 'Поле «Расчётный счёт» не должно превышать :max символов.',

            'order_by.integer'         => 'Поле «Сортировка» должно быть целым числом.',

            'is_enabled.required'      => 'Поле «Активность» обязательно для заполнения.',
            'is_enabled.boolean'       => 'Поле «Активность» должно быть булевым (0 или 1).',

            'email.required'           => 'Поле «E-mail партнёра» обязательно для заполнения.',
            'email.email'              => 'Поле «E-mail партнёра» должно быть корректным e-mail адресом.',
            'email.max'                => 'Поле «E-mail партнёра» не должно превышать :max символов.',
            'email.unique'             => 'Партнёр с таким E-mail уже существует.',

            // Новые
            'zip.regex'                => 'Индекс должен содержать 6 цифр (например, 197350).',
        ];
    }

    public function attributes()
    {
        return [
            'business_type'       => 'Тип бизнеса',
            'title'               => 'Наименование',
            'tax_id'              => 'ИНН',
            'kpp'                 => 'КПП',
            'registration_number' => 'ОГРН (ОГРНИП)',

            // Новые
            'sms_name'            => 'Название для SMS/выписок',
            'city'                => 'Город',
            'zip'                 => 'Индекс',
            'address'             => 'Адрес', // было «Почтовый адрес»

            'phone'               => 'Телефон',
            'email'               => 'E-mail партнёра',
            'website'             => 'Сайт',
            'bank_name'           => 'Наименование банка',
            'bank_bik'            => 'БИК',
            'bank_account'        => 'Расчётный счёт',
            'order_by'            => 'Сортировка',
            'is_enabled'          => 'Активность',

            // CEO
            'ceo.last_name'       => 'Фамилия руководителя',
            'ceo.first_name'      => 'Имя руководителя',
            'ceo.middle_name'     => 'Отчество руководителя',
            'ceo.phone'           => 'Телефон руководителя',
        ];
    }
}
