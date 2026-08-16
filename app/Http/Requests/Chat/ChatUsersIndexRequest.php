<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class ChatUsersIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('messages.view') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $q = $this->input('q');
        if (is_string($q)) {
            $this->merge(['q' => trim($q)]);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'q' => 'поиск',
        ];
    }

    public function messages(): array
    {
        return [
            'q.string' => 'Строка поиска должна быть текстом.',
            'q.max' => 'Строка поиска слишком длинная (максимум 120 символов).',
        ];
    }

    public function searchQuery(): string
    {
        return trim((string) ($this->validated()['q'] ?? ''));
    }
}
