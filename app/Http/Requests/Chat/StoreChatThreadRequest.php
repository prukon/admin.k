<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\User;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreChatThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('messages.view') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $userId = $this->input('user_id');
        if (is_string($userId) && ctype_digit($userId)) {
            $this->merge(['user_id' => (int) $userId]);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'собеседник',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Выберите собеседника.',
            'user_id.integer' => 'Некорректное значение поля «Собеседник».',
            'user_id.min' => 'Выберите собеседника.',
            'user_id.exists' => 'Выбранный пользователь не найден.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $actor = $this->user();
            if (! $actor) {
                $validator->errors()->add('user_id', 'Необходима авторизация.');

                return;
            }

            $peerId = (int) $this->input('user_id');
            if ($peerId === (int) $actor->id) {
                $validator->errors()->add('user_id', 'Нельзя создать диалог с самим собой.');

                return;
            }

            $partnerId = app(PartnerContext::class)->partnerId();
            if (! $partnerId) {
                $validator->errors()->add('user_id', 'Текущая организация не определена.');

                return;
            }

            $peer = User::query()->find($peerId);
            if (! $peer || (int) $peer->partner_id !== (int) $partnerId) {
                $validator->errors()->add('user_id', 'Нельзя добавить пользователя другой организации.');

                return;
            }

            if ((int) ($peer->is_enabled ?? 1) !== 1) {
                $validator->errors()->add('user_id', 'Этот пользователь отключён.');
            }
        });
    }

    public function peerUserId(): int
    {
        return (int) $this->validated()['user_id'];
    }
}
