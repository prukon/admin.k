<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class ChatMessagesIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('messages.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'after_id' => ['nullable', 'integer', 'min:1'],
            'before_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'after_id' => 'после сообщения',
            'before_id' => 'до сообщения',
        ];
    }

    public function messages(): array
    {
        return [
            'after_id.integer' => 'Некорректный идентификатор сообщения.',
            'after_id.min' => 'Некорректный идентификатор сообщения.',
            'before_id.integer' => 'Некорректный идентификатор сообщения.',
            'before_id.min' => 'Некорректный идентификатор сообщения.',
        ];
    }

    public function afterId(): ?int
    {
        $value = $this->validated()['after_id'] ?? null;

        return $value !== null ? (int) $value : null;
    }

    public function beforeId(): ?int
    {
        $value = $this->validated()['before_id'] ?? null;

        return $value !== null ? (int) $value : null;
    }
}
