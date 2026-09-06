<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\ChatParticipant;
use App\Models\User;
use App\Services\Chat\ChatSupportIdentity;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChatUserShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if ($actor === null || ! $actor->can('messages.view')) {
            return false;
        }

        $peer = $this->route('user');
        if (! $peer instanceof User) {
            return false;
        }

        $partnerContext = app(PartnerContext::class);
        $partnerId = $partnerContext->partnerId();
        $support = app(ChatSupportIdentity::class);

        if ((int) $peer->id === (int) $actor->id) {
            return true;
        }

        if ($support->isCanonicalUserId((int) $peer->id)) {
            return true;
        }

        if ($support->isSupportUser($peer)) {
            return false;
        }

        if ($partnerId && (int) $peer->partner_id === (int) $partnerId) {
            return true;
        }

        return $partnerContext->isSuperAdmin($actor)
            && $this->sharesLiveThread($actor, $peer);
    }

    public function rules(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'user' => 'пользователь',
        ];
    }

    public function messages(): array
    {
        return [
            'user.unauthorized' => 'Нет доступа к карточке этого пользователя.',
        ];
    }

    protected function failedAuthorization(): void
    {
        $message = $this->messages()['user.unauthorized'];

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => ['user' => [$message]],
        ], 403));
    }

    private function sharesLiveThread(User $actor, User $peer): bool
    {
        $actorId = (int) $actor->id;
        $peerId = (int) $peer->id;
        if ($actorId < 1 || $peerId < 1 || $actorId === $peerId) {
            return false;
        }

        return ChatParticipant::query()
            ->where('user_id', $actorId)
            ->whereHas('thread')
            ->whereIn(
                'thread_id',
                ChatParticipant::query()->where('user_id', $peerId)->select('thread_id')
            )
            ->exists();
    }
}
