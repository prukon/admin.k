<?php

namespace App\Http\Requests\PartnerLegalEntity;

use App\Enums\PartnerLegalEntityBusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmRegisterPartnerLegalEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('legal_entities.manage');
    }

    public function rules(): array
    {
        return [
            'business_type' => ['required', 'string', Rule::in(PartnerLegalEntityBusinessType::values())],
            'title' => ['required', 'string', 'max:255'],
            'organization_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'tax_id' => ['required', 'string', 'max:20'],
            'registration_number' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'zip' => ['required', 'string', 'max:20', 'regex:/^\d{6}$/'],
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_bik' => ['required', 'string', 'max:20'],
            'bank_account' => ['required', 'string', 'max:32'],
            'sm_details_template' => ['required', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'url', 'max:255'],
            'kpp' => ['nullable', 'string', 'max:12'],
            'ceo' => ['nullable', 'array'],
            'ceo.lastName' => [
                $this->routeIs('admin.legal-entities.sm-patch') ? 'nullable' : 'required',
                'string',
                'max:100',
            ],
            'ceo.firstName' => [
                $this->routeIs('admin.legal-entities.sm-patch') ? 'nullable' : 'required',
                'string',
                'max:100',
            ],
            'ceo.middleName' => ['nullable', 'string', 'max:100'],
            'ceo.phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function attributes(): array
    {
        return [
            'business_type' => 'форма организации',
            'title' => 'наименование',
            'organization_name' => 'наименование организации',
            'email' => 'E-mail',
            'tax_id' => 'ИНН',
            'registration_number' => 'ОГРН/ОГРНИП',
            'address' => 'адрес',
            'city' => 'город',
            'zip' => 'индекс',
            'bank_name' => 'банк',
            'bank_bik' => 'БИК',
            'bank_account' => 'расчётный счёт',
            'sm_details_template' => 'назначение платежа',
            'phone' => 'телефон',
            'website' => 'сайт',
            'kpp' => 'КПП',
            'ceo.lastName' => 'фамилия руководителя',
            'ceo.firstName' => 'имя руководителя',
            'ceo.middleName' => 'отчество руководителя',
            'ceo.phone' => 'телефон руководителя',
        ];
    }

    public function messages(): array
    {
        return [
            'business_type.required' => 'Выберите форму организации',
            'organization_name.required' => 'Введите наименование организации',
            'email.required' => 'Введите E-mail',
            'tax_id.required' => 'Введите ИНН',
            'registration_number.required' => 'Введите ОГРН/ОГРНИП',
            'zip.regex' => 'Индекс должен содержать 6 цифр',
        ];
    }
}
