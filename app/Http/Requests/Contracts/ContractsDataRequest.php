<?php

namespace App\Http\Requests\Contracts;

class ContractsDataRequest extends ContractsJsonRequest
{
    public function rules(): array
    {
        return [
            'status'       => ['nullable', 'string', 'max:50'],
            'group_id'     => ['nullable', 'string', 'max:50'], // id или 'none'
            'search_value' => ['nullable', 'string', 'max:255'],
            'draw'         => ['nullable', 'integer', 'min:0'],
            'start'        => ['nullable', 'integer', 'min:0'],
            'length'       => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return [
            'status'       => 'Статус',
            'group_id'     => 'Группа',
            'search_value' => 'Поиск',
            'draw'         => 'draw',
            'start'        => 'start',
            'length'       => 'length',
        ];
    }

    public function messages(): array
    {
        return [
            'status.string'        => 'Поле «:attribute» должно быть строкой.',
            'status.max'           => 'Поле «:attribute» слишком длинное.',
            'group_id.string'      => 'Поле «:attribute» должно быть строкой.',
            'group_id.max'         => 'Поле «:attribute» слишком длинное.',
            'search_value.string'  => 'Поле «:attribute» должно быть строкой.',
            'search_value.max'     => 'Поле «:attribute» слишком длинное.',
            'draw.integer'         => 'Поле «:attribute» должно быть числом.',
            'start.integer'        => 'Поле «:attribute» должно быть числом.',
            'start.min'            => 'Поле «:attribute» не может быть меньше :min.',
            'length.integer'       => 'Поле «:attribute» должно быть числом.',
            'length.min'           => 'Поле «:attribute» не может быть меньше :min.',
            'length.max'           => 'Поле «:attribute» не может быть больше :max.',
        ];
    }
}

