<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ImportCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.import') ?? false;
    }

    public function rules(): array
    {
        return [
            'import_token' => ['required', 'string', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'import_token' => 'Токен импорта',
        ];
    }

    public function messages(): array
    {
        return [
            'import_token.required' => 'Сессия импорта не найдена. Загрузите файл повторно.',
            'import_token.uuid' => 'Некорректный токен импорта.',
        ];
    }
}
