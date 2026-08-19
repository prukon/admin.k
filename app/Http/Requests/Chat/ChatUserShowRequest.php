<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\User;
use App\Services\Chat\ChatSupportIdentity;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;

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

        $partnerId = app(PartnerContext::class)->partnerId();
        $support = app(ChatSupportIdentity::class);

        if ((int) $peer->id === (int) $actor->id) {
            return true;
        }

        if ($support->isCanonicalUserId((int) $peer->id)) {
            return true;
        }

        if (! $partnerId || (int) $peer->partner_id !== (int) $partnerId) {
            return false;
        }

        return ! $support->isSupportUser($peer);
    }

    public function rules(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [];
    }
}
