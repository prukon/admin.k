<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class SaveChatDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('messages.view') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');
        if (is_string($body)) {
            $this->merge(['body' => trim($body)]);
        }
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => 'черновик',
        ];
    }

    public function messages(): array
    {
        return [
            'body.string' => 'Черновик должен быть строкой.',
            'body.max' => 'Черновик слишком длинный (максимум 5000 символов).',
        ];
    }

    public function draftBody(): string
    {
        $body = $this->validated()['body'] ?? '';

        return is_string($body) ? $body : '';
    }
}
