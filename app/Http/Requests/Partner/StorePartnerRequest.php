<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('manage-groups');
    }

    protected function prepareForValidation()
    {
        if ($this->filled('website') && !preg_match('#^https?://#i', $this->website)) {
            $this->merge(['website' => 'http://' . $this->website]);
        }
        // нормализуем ceo: принимаем и snake_case, и camelCase -> приводим к camelCase
        $ceo = $this->input('ceo', []);
        if (is_array($ceo)) {
            $this->merge([
                'ceo' => [
                    'lastName'   => $ceo['lastName']   ?? $ceo['last_name']   ?? '',
                    'firstName'  => $ceo['firstName']  ?? $ceo['first_name']  ?? '',
                    'middleName' => $ceo['middleName'] ?? $ceo['middle_name'] ?? '',
                    'phone'      => $ceo['phone']      ?? '',
                ]
            ]);
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

            'sms_name'            => 'nullable|string|max:14',
            'city'                => 'nullable|string|max:100',
            'zip'                 => 'nullable|string|max:20|regex:/^\d{6}$/',
            'address'             => 'nullable|string|max:255',

            'phone'               => 'nullable|string|max:20',
            'email'               => 'required|email|max:255|unique:partners,email',
            'website'             => 'nullable|url|max:255',

            'bank_name'           => 'nullable|string|max:255',
            'bank_bik'            => 'nullable|string|max:9',
            'bank_account'        => 'nullable|string|max:20',

            'order_by'            => 'nullable|integer',
            'is_enabled'          => 'required|boolean',

            'ceo'                 => 'nullable|array',
            'ceo.lastName'        => 'nullable|string|max:100',
            'ceo.firstName'       => 'nullable|string|max:100',
            'ceo.middleName'      => 'nullable|string|max:100',
            'ceo.phone'           => 'nullable|string|max:20',
        ];
    }

    public function messages()
    {
        return [
            'email.unique' => 'Партнёр с таким E-mail уже существует.',
            'website.url'  => 'Поле «Сайт» должно быть корректным URL.',
            'zip.regex'    => 'Индекс должен содержать 6 цифр (например, 197350).',
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

            'sms_name'            => 'Название для SMS/выписок',
            'city'                => 'Город',
            'zip'                 => 'Индекс',
            'address'             => 'Адрес',

            'phone'               => 'Телефон',
            'email'               => 'E-mail партнёра',
            'website'             => 'Сайт',
            'bank_name'           => 'Наименование банка',
            'bank_bik'            => 'БИК',
            'bank_account'        => 'Расчётный счёт',
            'order_by'            => 'Сортировка',
            'is_enabled'          => 'Активность',

            'ceo.lastName'        => 'Фамилия руководителя',
            'ceo.firstName'       => 'Имя руководителя',
            'ceo.middleName'      => 'Отчество руководителя',
            'ceo.phone'           => 'Телефон руководителя',
        ];
    }
}
