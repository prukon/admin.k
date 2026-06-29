<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\CloudKassirVatRate;
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
            'organization_name' => ['required', 'string', 'max:255'],
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
            'vat' => ['nullable', 'integer', Rule::in(CloudKassirVatRate::codes())],
            'ceo' => ['nullable', 'array'],
            'ceo.lastName' => ['nullable', 'string', 'max:100'],
            'ceo.firstName' => ['nullable', 'string', 'max:100'],
            'ceo.middleName' => ['nullable', 'string', 'max:100'],
            'ceo.phone' => ['nullable', 'string', 'max:32'],
            'is_default' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }

    protected function normalizeLegalEntityCeoInput(): void
    {
        $ceo = $this->input('ceo', []);
        if (! is_array($ceo)) {
            return;
        }

        $this->merge([
            'ceo' => [
                'lastName' => trim((string) ($ceo['lastName'] ?? $ceo['last_name'] ?? '')),
                'firstName' => trim((string) ($ceo['firstName'] ?? $ceo['first_name'] ?? '')),
                'middleName' => trim((string) ($ceo['middleName'] ?? $ceo['middle_name'] ?? '')),
                'phone' => trim((string) ($ceo['phone'] ?? '')),
            ],
        ]);
    }

    protected function normalizeLegalEntityOrganizationNameInput(): void
    {
        if ($this->has('organization_name')) {
            $this->merge(['organization_name' => trim((string) $this->input('organization_name'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function baseLegalEntityAttributes(): array
    {
        return [
            'business_type' => 'форма организации',
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
            'vat' => 'ставка НДС',
            'ceo.lastName' => 'фамилия руководителя',
            'ceo.firstName' => 'имя руководителя',
            'ceo.middleName' => 'отчество руководителя',
            'ceo.phone' => 'телефон руководителя',
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
            'organization_name.required' => 'Введите наименование организации',
            'tax_id.unique' => 'Юр. лицо с таким ИНН уже существует',
            'zip.regex' => 'Индекс должен содержать 6 цифр',
        ];
    }
}
