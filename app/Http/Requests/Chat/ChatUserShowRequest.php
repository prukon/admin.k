<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\User;
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
        if (! $partnerId || (int) $peer->partner_id !== (int) $partnerId) {
            return false;
        }

        return true;
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
