<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\User;
use App\Services\Chat\ChatSupportIdentity;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreChatGroupThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('messages.view') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        if (is_string($title)) {
            $this->merge(['title' => trim($title)]);
        }

        $ids = $this->input('user_ids');
        if ($ids === null) {
            return;
        }

        if (! is_array($ids)) {
            $this->merge(['user_ids' => []]);

            return;
        }

        $normalized = [];
        foreach ($ids as $id) {
            if (is_string($id) && ctype_digit($id)) {
                $normalized[] = (int) $id;
            } elseif (is_int($id)) {
                $normalized[] = $id;
            } else {
                $normalized[] = $id;
            }
        }

        $this->merge(['user_ids' => app(ChatSupportIdentity::class)->mapSupportPeerIds($normalized)]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:100'],
            'user_ids' => ['required', 'array', 'min:2', 'max:100'],
            'user_ids.*' => ['integer', 'min:1', 'exists:users,id', 'distinct'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'название группы',
            'user_ids' => 'участники',
            'user_ids.*' => 'участник',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Введите название группы.',
            'title.string' => 'Название группы должно быть текстом.',
            'title.min' => 'Введите название группы.',
            'title.max' => 'Название группы слишком длинное (максимум 100 символов).',
            'user_ids.required' => 'Выберите минимум двух участников.',
            'user_ids.array' => 'Некорректный список участников.',
            'user_ids.min' => 'Выберите минимум двух участников.',
            'user_ids.max' => 'Слишком много участников (максимум 100).',
            'user_ids.distinct' => 'Список участников содержит повторы.',
            'user_ids.*.distinct' => 'Список участников содержит повторы.',
            'user_ids.*.integer' => 'Некорректное значение поля «Участник».',
            'user_ids.*.min' => 'Выберите участников.',
            'user_ids.*.exists' => 'Выбранный пользователь не найден.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($validator->errors()->getMessages() as $key => $messages) {
                if (preg_match('/^user_ids\.\d+$/', $key) && ! $validator->errors()->has('user_ids')) {
                    $validator->errors()->add('user_ids', (string) ($messages[0] ?? 'Выбранный пользователь не найден.'));
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $actor = $this->user();
            if (! $actor) {
                $validator->errors()->add('user_ids', 'Необходима авторизация.');

                return;
            }

            $partnerId = app(PartnerContext::class)->partnerId();
            if (! $partnerId) {
                $validator->errors()->add('user_ids', 'Текущая организация не определена.');

                return;
            }

            $memberIds = array_values(array_unique(array_map('intval', (array) $this->input('user_ids', []))));
            $actorId = (int) $actor->id;

            if (count($memberIds) < 2) {
                $validator->errors()->add('user_ids', 'Выберите минимум двух участников.');

                return;
            }

            if (in_array($actorId, $memberIds, true)) {
                $validator->errors()->add('user_ids', 'Нельзя добавить себя в список участников.');

                return;
            }

            $users = User::query()->whereIn('id', $memberIds)->get()->keyBy('id');
            foreach ($memberIds as $memberId) {
                $peer = $users->get($memberId);
                if (! $peer) {
                    $validator->errors()->add('user_ids', 'Выбранный пользователь не найден.');

                    return;
                }

                if (! app(ChatSupportIdentity::class)->isAllowedPeerInPartner($peer, (int) $partnerId)) {
                    $validator->errors()->add('user_ids', 'Нельзя добавить пользователя другой организации.');

                    return;
                }

                if ((int) ($peer->is_enabled ?? 1) !== 1) {
                    $validator->errors()->add('user_ids', 'Этот пользователь отключён.');

                    return;
                }
            }
        });
    }

    public function groupTitle(): string
    {
        return (string) $this->validated()['title'];
    }

    /**
     * @return list<int>
     */
    public function memberIds(): array
    {
        $ids = array_map('intval', (array) ($this->validated()['user_ids'] ?? []));

        return array_values(array_unique($ids));
    }
}
