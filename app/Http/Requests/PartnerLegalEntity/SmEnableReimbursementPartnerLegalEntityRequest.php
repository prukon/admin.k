<?php

namespace App\Http\Requests\PartnerLegalEntity;

use Illuminate\Foundation\Http\FormRequest;

class SmEnableReimbursementPartnerLegalEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('legal_entities.manage');
    }

    public function rules(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [];
    }
}
