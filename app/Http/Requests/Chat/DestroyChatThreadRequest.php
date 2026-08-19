<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\ChatThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DestroyChatThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor || ! $actor->can('messages.view') || ! $actor->can('messages.threads.delete')) {
            return false;
        }

        $thread = $this->route('thread');
        if (! $thread instanceof ChatThread) {
            return false;
        }

        return $thread->hasParticipant((int) $actor->id);
    }

    public function rules(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'thread' => 'чат',
        ];
    }

    public function messages(): array
    {
        return [
            'thread.required' => 'Чат не найден.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $thread = $this->route('thread');
            if (! $thread instanceof ChatThread) {
                $validator->errors()->add('thread', 'Чат не найден.');

                return;
            }

            if ($thread->team_id) {
                $validator->errors()->add('thread', 'Нельзя удалить чат учебной группы.');
            }
        });
    }
}