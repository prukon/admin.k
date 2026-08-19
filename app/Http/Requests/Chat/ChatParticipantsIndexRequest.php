<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\ChatThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChatParticipantsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('messages.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'after_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'after_user_id' => 'после участника',
        ];
    }

    public function messages(): array
    {
        return [
            'after_user_id.integer' => 'Некорректный идентификатор участника.',
            'after_user_id.min' => 'Некорректный идентификатор участника.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $raw = $this->input('after_user_id');
            if ($raw === null || $raw === '') {
                return;
            }
            $afterUserId = (int) $raw;

            $thread = $this->route('thread');
            if (! $thread instanceof ChatThread) {
                return;
            }

            if (! $thread->hasParticipant($afterUserId)) {
                $validator->errors()->add('after_user_id', 'Некорректный идентификатор участника.');
            }
        });
    }

    public function afterUserId(): ?int
    {
        $value = $this->validated()['after_user_id'] ?? null;

        return $value !== null ? (int) $value : null;
    }
}
