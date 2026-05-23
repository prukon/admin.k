<?php

namespace App\Http\Requests\Account;

use App\Models\Contract;
use App\Services\Contracts\ContractPdfGenerationService;
use Illuminate\Foundation\Http\FormRequest;

class AccountContractGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contract = $this->route('contract');

        return $contract instanceof Contract
            && (int) $contract->user_id === (int) $this->user()?->id;
    }

    public function rules(): array
    {
        /** @var Contract $contract */
        $contract = $this->route('contract');
        $contract->loadMissing('templateVersion');

        $schema = $contract->templateVersion?->fields_schema ?? [];
        $rules = ['fields' => ['required', 'array']];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }

            $label = (string) ($field['label'] ?? $key);
            $fieldRules = ['nullable', 'string', 'max:2000'];

            if (!empty($field['required'])) {
                $fieldRules = ['required', 'string', 'max:2000'];
            }

            $rules['fields.' . $key] = $fieldRules;
        }

        return $rules;
    }

    public function attributes(): array
    {
        /** @var Contract $contract */
        $contract = $this->route('contract');
        $contract->loadMissing('templateVersion');
        $schema = $contract->templateVersion?->fields_schema ?? [];

        $attrs = ['fields' => 'Поля договора'];
        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if (is_string($key) && $key !== '') {
                $attrs['fields.' . $key] = (string) ($field['label'] ?? $key);
            }
        }

        return $attrs;
    }

    public function messages(): array
    {
        return [
            'fields.required' => 'Заполните поля договора.',
            'fields.array'    => 'Некорректный формат полей договора.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function fieldValues(): array
    {
        $fields = $this->input('fields', []);
        if (!is_array($fields)) {
            return [];
        }

        $normalized = [];
        foreach ($fields as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = trim((string) $value);
            }
        }

        return $normalized;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Contract $contract */
            $contract = $this->route('contract');

            try {
                app(ContractPdfGenerationService::class)->assertCanGenerate($contract);
            } catch (\Illuminate\Validation\ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }
}
