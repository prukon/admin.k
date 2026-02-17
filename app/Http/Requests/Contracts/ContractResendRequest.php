<?php

namespace App\Http\Requests\Contracts;

class ContractResendRequest extends ContractsJsonRequest
{
    public function rules(): array
    {
        return [
            'sid' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sid' => 'SID подписанта',
        ];
    }

    public function messages(): array
    {
        return [
            'sid.string' => 'Поле «:attribute» должно быть строкой.',
            'sid.max'    => 'Поле «:attribute» слишком длинное.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $sid = $this->input('sid');
        if (is_string($sid)) {
            $this->merge(['sid' => trim($sid)]);
        }
    }
}

