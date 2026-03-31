<?php

namespace App\Http\Requests\Tinkoff\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TinkoffPayoutsSelect2UsersSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'partner_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'q' => 'Поиск',
            'partner_id' => 'Партнёр',
        ];
    }

    public function messages(): array
    {
        return [
            'q.string' => 'Поле «:attribute» должно быть строкой.',
            'q.max' => 'Поле «:attribute» слишком длинное.',
            'partner_id.integer' => 'Поле «:attribute» должно быть числом.',
            'partner_id.min' => 'Поле «:attribute» должно быть больше нуля.',
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

