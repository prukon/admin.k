<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\ChatThread;
use App\Models\Team;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

        if ($this->exists('team_id') && ! is_array($this->input('team_id'))) {
            $this->merge(['team_id' => trim((string) $this->input('team_id'))]);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'team_id' => ['nullable', 'string', 'max:20'],
            'exclude_thread_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'q' => 'поиск',
            'team_id' => 'группа',
            'exclude_thread_id' => 'группа чата',
        ];
    }

    public function messages(): array
    {
        return [
            'q.string' => 'Строка поиска должна быть текстом.',
            'q.max' => 'Строка поиска слишком длинная (максимум 120 символов).',
            'team_id.string' => 'Группа должна быть текстом.',
            'team_id.max' => 'Некорректное значение фильтра по группе.',
            'exclude_thread_id.integer' => 'Некорректный идентификатор чата.',
            'exclude_thread_id.min' => 'Некорректный идентификатор чата.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $teamId = trim((string) $this->input('team_id', ''));
            if ($teamId !== '' && $teamId !== 'none') {
                if (! ctype_digit($teamId) || (int) $teamId <= 0) {
                    $validator->errors()->add('team_id', 'Выберите группу из списка.');
                } else {
                    $partnerId = app(PartnerContext::class)->partnerId();
                    if (! $partnerId) {
                        $validator->errors()->add('team_id', 'Текущая организация не определена.');
                    } else {
                        $exists = Team::query()
                            ->whereKey((int) $teamId)
                            ->where('partner_id', (int) $partnerId)
                            ->exists();

                        if (! $exists) {
                            $validator->errors()->add('team_id', 'Выберите группу из списка.');
                        }
                    }
                }
            }

            $excludeId = $this->input('exclude_thread_id');
            if ($excludeId !== null && $excludeId !== '') {
                $thread = ChatThread::query()->find((int) $excludeId);
                $actor = $this->user();
                if (! $thread || ! $thread->is_group) {
                    $validator->errors()->add('exclude_thread_id', 'Некорректный идентификатор чата.');
                } elseif (! $actor || ! $thread->hasParticipant((int) $actor->id)) {
                    $validator->errors()->add('exclude_thread_id', 'Нет доступа к этому диалогу.');
                }
            }
        });
    }

    public function searchQuery(): string
    {
        return trim((string) ($this->validated()['q'] ?? ''));
    }

    public function teamFilter(): string
    {
        return trim((string) ($this->validated()['team_id'] ?? ''));
    }

    public function excludeThreadId(): ?int
    {
        $value = $this->validated()['exclude_thread_id'] ?? null;

        return $value !== null ? (int) $value : null;
    }
}
