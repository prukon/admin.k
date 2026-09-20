<?php

namespace App\Http\Requests\Contracts;

class ContractUserPackagesRequest extends ContractsJsonRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contracts.lessonPackage.bind') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'Ученик',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.integer'  => 'Некорректный идентификатор ученика.',
            'user_id.min'      => 'Некорректный идентификатор ученика.',
        ];
    }
}
