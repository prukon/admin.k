<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\ChatThread;
use App\Services\Chat\ChatEmoji;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatMessageReactionRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        $emoji = $this->input('emoji');
        if (is_string($emoji)) {
            $this->merge(['emoji' => trim($emoji)]);
        }
    }

    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', 'max:32', Rule::in(ChatEmoji::reactions())],
        ];
    }

    public function attributes(): array
    {
        return [
            'emoji' => 'смайлик',
        ];
    }

    public function messages(): array
    {
        return [
            'emoji.required' => 'Выберите смайлик.',
            'emoji.string' => 'Смайлик должен быть строкой.',
            'emoji.max' => 'Смайлик слишком длинный.',
            'emoji.in' => 'Этот смайлик нельзя поставить как реакцию.',
        ];
    }

    public function emoji(): string
    {
        return (string) $this->validated()['emoji'];
    }
}
