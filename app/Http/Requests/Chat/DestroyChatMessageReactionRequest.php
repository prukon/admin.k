<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\ChatThread;
use Illuminate\Foundation\Http\FormRequest;

class DestroyChatMessageReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor?->can('messages.view')) {
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
}
