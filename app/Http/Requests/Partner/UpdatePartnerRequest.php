<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerRequest extends FormRequest
{
    /**
     * Подготовка данных перед валидацией:
     * если в поле website нет схемы, добавляем http://
     */
    protected function prepareForValidation()
    {
        if ($this->filled('website') && !preg_match('#^https?://#i', $this->website)) {
            $this->merge([
                'website' => 'http://' . $this->website,
            ]);
        }
    }

    /**
     * Правила валидации.
     *
     * @return array
     */
    public function rules()
    {
        $partnerId = $this->route('partner')->id;

        return [
            'business_type'       => 'required|in:company,individual_entrepreneur,non_commercial_organization,physical_person',
            'title'               => 'required|string|max:255',
            'tax_id'              => 'nullable|string|max:12',
            'kpp'                 => 'nullable|string|max:9',
            'registration_number' => 'nullable|string|max:20',

            // Новые поля:
            'sms_name'            => 'nullable|string|max:14',
            'city'                => 'nullable|string|max:100',
            'zip'                 => 'nullable|string|max:20|regex:/^\d{6}$/',

            'address'             => 'nullable|string|max:255',
            'phone'               => 'nullable|string|max:20',
            'email'               => "required|email|max:255|unique:partners,email,{$partnerId}",
            'website'             => 'nullable|url|max:255',
            'bank_name'           => 'nullable|string|max:255',
            'bank_bik'            => 'nullable|string|max:9',
            'bank_account'        => 'nullable|string|max:20',
            'order_by'            => 'nullable|integer',
            'is_enabled'          => 'required|boolean',

            // Данные руководителя (JSON)
            'ceo'                 => 'nullable|array',
            'ceo.last_name'       => 'nullable|string|max:100',
            'ceo.first_name'      => 'nullable|string|max:100',
            'ceo.middle_name'     => 'nullable|string|max:100',
            'ceo.phone'           => 'nullable|string|max:20',
        ];
    }

    /**
     * Сообщения об ошибках.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'email.unique'       => 'Партнёр с таким E-mail уже существует.',
            'website.url'        => 'Поле «Сайт» должно быть корректным URL. Например: example.com или http://example.com.',
            'website.max'        => 'Поле «Сайт» не должно превышать :max символов.',
            // остальные сообщения можно перенести из StorePartnerRequest…
        ];
    }

    /**
     * Названия полей для вывода в сообщениях.
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

            // Новые:
            'sms_name'            => 'Название для SMS/выписок',
            'city'                => 'Город',
            'zip'                 => 'Индекс',


            'address'             => 'Адрес',
            'phone'               => 'Телефон',
            'email'               => 'E-mail партнёра',
            'website'             => 'Сайт',
            'bank_name'           => 'Наименование банка',
            'bank_bik'            => 'БИК',
            'bank_account'        => 'Расчетный счет',
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
