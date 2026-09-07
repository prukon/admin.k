<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Foundation\Http\FormRequest;

class DestroyChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor || ! $actor->can('messages.view') || ! $actor->can('messages.own.delete')) {
            return false;
        }

        $thread = $this->route('thread');
        if (! $thread instanceof ChatThread) {
            return false;
        }

        if (! $thread->hasParticipant((int) $actor->id)) {
            return false;
        }

        $message = $this->route('message');
        if (! $message instanceof ChatMessage) {
            return false;
        }

        return (int) $message->user_id === (int) $actor->id;
    }

    public function rules(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'message' => 'сообщение',
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Сообщение не найдено.',
        ];
    }
}
