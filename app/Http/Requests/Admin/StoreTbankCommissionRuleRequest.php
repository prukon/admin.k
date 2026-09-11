<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\TbankCommissionRulePayloadRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreTbankCommissionRuleRequest extends FormRequest
{
    use TbankCommissionRulePayloadRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('settings.commission');
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTbankCommissionRulePayload();
    }

    public function rules(): array
    {
        return $this->tbankCommissionRuleRules();
    }

    public function attributes(): array
    {
        return $this->tbankCommissionRuleAttributes();
    }

    public function messages(): array
    {
        return $this->tbankCommissionRuleMessages();
    }
}
