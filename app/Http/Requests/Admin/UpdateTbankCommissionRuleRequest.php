<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\TbankCommissionRulePayloadRules;
use App\Models\TinkoffCommissionRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTbankCommissionRuleRequest extends FormRequest
{
    use TbankCommissionRulePayloadRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('settings.commission');
    }

    protected function prepareForValidation(): void
    {
        $rule = TinkoffCommissionRule::query()->find((int) $this->route('id'));
        if ($rule) {
            $this->merge([
                'partner_id' => $rule->partner_id,
                'method' => $rule->method,
            ]);
        }

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

    protected function getRedirectUrl(): string
    {
        return route('admin.setting.tbankCommissions', [
            'edit' => (int) $this->route('id'),
        ]);
    }
}
