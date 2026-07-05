<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.import') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'Файл Excel',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Выберите файл Excel для импорта.',
            'file.file' => 'Загруженный файл некорректен.',
            'file.mimes' => 'Допустим только формат .xlsx.',
            'file.max' => 'Размер файла не должен превышать 5 МБ.',
        ];
    }
}
