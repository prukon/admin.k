<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\PartnerLegalEntityValidationRules;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;

class StorePartnerLegalEntityRequest extends FormRequest
{
    use PartnerLegalEntityValidationRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('legal_entities.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeLegalEntityCeoInput();
        $this->normalizeLegalEntityOrganizationNameInput();

        if ($this->has('vat') && $this->vat === '') {
            $this->merge(['vat' => null]);
        }
    }

    public function rules(): array
    {
        $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);

        return $this->baseLegalEntityRules($partnerId);
    }

    public function attributes(): array
    {
        return $this->baseLegalEntityAttributes();
    }

    public function messages(): array
    {
        return $this->baseLegalEntityMessages();
    }
}
