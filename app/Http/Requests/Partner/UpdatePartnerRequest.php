<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()?->can('partner.view') === true;
    }

    protected function prepareForValidation()
    {
        if ($this->filled('website') && !preg_match('#^https?://#i', $this->website)) {
            $this->merge(['website' => 'http://' . $this->website]);
        }
    }

    public function rules()
    {
        $partnerId = $this->route('partner')->id;

        return [
            'title'        => 'required|string|max:255',
            'sms_name'     => 'nullable|string|max:14',
            'phone'        => 'nullable|string|max:20',
            'email'        => "required|email|max:255|unique:partners,email,{$partnerId}",
            'website'      => 'nullable|url|max:255',
            'order_by'     => 'nullable|integer',
            'is_enabled'   => 'required|boolean',
        ];
    }

    public function messages()
    {
        return [
            'email.unique' => 'Партнёр с таким E-mail уже существует.',
            'website.url'  => 'Поле «Сайт» должно быть корректным URL.',
        ];
    }

    public function attributes()
    {
        return [
            'title'            => 'Название школы/секции',
            'sms_name'         => 'Название для SMS/выписок',
            'phone'            => 'Телефон',
            'email'            => 'E-mail партнёра',
            'website'          => 'Сайт',
            'order_by'         => 'Сортировка',
            'is_enabled'       => 'Активность',
        ];
    }
}
