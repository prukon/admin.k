<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\Chat\TeamGroupChatService;

class TeamGroupChatUserObserver
{
    public function saved(User $user): void
    {
        app(TeamGroupChatService::class)->syncUserAfterSave($user);
    }
}
