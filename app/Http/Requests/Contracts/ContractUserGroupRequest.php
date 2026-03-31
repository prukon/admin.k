<?php

namespace App\Http\Requests\Contracts;

class ContractUserGroupRequest extends ContractsJsonRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
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
            'user_id.required' => 'Выберите ученика.',
            'user_id.integer'  => 'Некорректный идентификатор ученика.',
            'user_id.min'      => 'Некорректный идентификатор ученика.',
        ];
    }
}

