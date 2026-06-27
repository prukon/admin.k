<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\PartnerLegalEntityBusinessType;
use Illuminate\Validation\Rule;

trait PartnerLegalEntityValidationRules
{
    /**
     * @return list<string>
     */
    protected function businessTypeRuleValues(): array
    {
        return PartnerLegalEntityBusinessType::values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseLegalEntityRules(int $partnerId, ?int $ignoreEntityId = null): array
    {
        $taxIdUnique = Rule::unique('partner_legal_entities', 'tax_id')
            ->where(fn ($query) => $query->where('partner_id', $partnerId)->whereNull('deleted_at'));

        if ($ignoreEntityId !== null && $ignoreEntityId > 0) {
            $taxIdUnique->ignore($ignoreEntityId);
        }

        return [
            'business_type' => ['required', 'string', Rule::in($this->businessTypeRuleValues())],
            'title' => ['required', 'string', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:12', $taxIdUnique],
            'kpp' => ['nullable', 'string', 'max:9'],
            'registration_number' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:20', 'regex:/^\d{6}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_bik' => ['nullable', 'string', 'max:20'],
            'bank_account' => ['nullable', 'string', 'max:32'],
            'sm_details_template' => ['nullable', 'string', 'max:500'],
            'taxation_system' => ['nullable', 'integer', 'in:0,1,2,3,4,5'],
            'vat' => ['nullable', 'integer', 'in:0,5,7,10,20,22,105,107,110,120,122'],
            'sms_name' => ['nullable', 'string', 'max:14'],
            'is_default' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function baseLegalEntityAttributes(): array
    {
        return [
            'business_type' => 'форма организации',
            'title' => 'наименование',
            'organization_name' => 'наименование организации',
            'tax_id' => 'ИНН',
            'kpp' => 'КПП',
            'registration_number' => 'ОГРН/ОГРНИП',
            'city' => 'город',
            'zip' => 'индекс',
            'address' => 'адрес',
            'bank_name' => 'банк',
            'bank_bik' => 'БИК',
            'bank_account' => 'расчётный счёт',
            'sm_details_template' => 'назначение платежа',
            'taxation_system' => 'система налогообложения',
            'vat' => 'ставка НДС',
            'sms_name' => 'название для SMS/выписок',
            'is_default' => 'основное юр. лицо',
            'is_enabled' => 'активность',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function baseLegalEntityMessages(): array
    {
        return [
            'business_type.required' => 'Выберите форму организации',
            'business_type.in' => 'Недопустимая форма организации',
            'title.required' => 'Введите наименование',
            'tax_id.unique' => 'Юр. лицо с таким ИНН уже существует',
            'zip.regex' => 'Индекс должен содержать 6 цифр',
        ];
    }
}
