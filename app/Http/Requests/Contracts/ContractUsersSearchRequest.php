<?php

namespace App\Http\Requests\Contracts;

class ContractUsersSearchRequest extends ContractsJsonRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'q' => 'Поиск',
        ];
    }

    public function messages(): array
    {
        return [
            'q.string' => 'Поле «:attribute» должно быть строкой.',
            'q.max'    => 'Поле «:attribute» слишком длинное.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $q = $this->input('q');
        if (is_string($q)) {
            $this->merge(['q' => trim($q)]);
        }
    }
}

