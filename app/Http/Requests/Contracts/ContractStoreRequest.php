<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;

class ContractStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'pdf'     => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'Ученик',
            'pdf'     => 'PDF-файл договора',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Выберите ученика.',
            'user_id.integer'  => 'Некорректный идентификатор ученика.',
            'user_id.min'      => 'Некорректный идентификатор ученика.',

            'pdf.required' => 'Загрузите PDF-файл договора.',
            'pdf.file'     => 'Файл договора должен быть файлом.',
            'pdf.mimes'    => 'Файл договора должен быть в формате PDF.',
            'pdf.max'      => 'PDF-файл договора не должен превышать :max КБ.',
        ];
    }
}

