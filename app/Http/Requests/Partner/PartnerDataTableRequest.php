<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class PartnerDataTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('partner.view') === true;
    }

    public function rules(): array
    {
        return [
            'title'  => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
            'draw'   => 'nullable|integer|min:0',
            'start'  => 'nullable|integer|min:0',
            'length' => 'nullable|integer|min:1|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'title.string'   => 'Поиск по названию должен быть строкой.',
            'title.max'      => 'Поиск по названию не должен превышать 255 символов.',
            'status.in'      => 'Недопустимое значение фильтра статуса.',
            'draw.integer'   => 'Параметр draw должен быть целым числом.',
            'draw.min'       => 'Параметр draw не может быть отрицательным.',
            'start.integer'  => 'Параметр start должен быть целым числом.',
            'start.min'      => 'Параметр start не может быть отрицательным.',
            'length.integer' => 'Параметр length должен быть целым числом.',
            'length.min'     => 'Параметр length должен быть не меньше 1.',
            'length.max'     => 'Параметр length не должен превышать 500.',
        ];
    }
}
