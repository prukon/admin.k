<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => 'текст сообщения',
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Введите текст сообщения.',
            'body.string' => 'Текст сообщения должен быть строкой.',
            'body.min' => 'Введите текст сообщения.',
            'body.max' => 'Сообщение слишком длинное (максимум 5000 символов).',
        ];
    }

    public function messageBody(): string
    {
        return (string) $this->validated()['body'];
    }
}
