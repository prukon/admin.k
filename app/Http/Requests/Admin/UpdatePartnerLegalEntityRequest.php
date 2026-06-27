<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\PartnerLegalEntityValidationRules;
use App\Models\PartnerLegalEntity;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerLegalEntityRequest extends FormRequest
{
    use PartnerLegalEntityValidationRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('legal_entities.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeLegalEntityCeoInput();

        if ($this->has('taxation_system') && $this->taxation_system === '') {
            $this->merge(['taxation_system' => null]);
        }
        if ($this->has('vat') && $this->vat === '') {
            $this->merge(['vat' => null]);
        }
    }

    public function rules(): array
    {
        $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);
        /** @var PartnerLegalEntity|null $entity */
        $entity = $this->route('legalEntity');

        return $this->baseLegalEntityRules($partnerId, $entity?->id);
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
