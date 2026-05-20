<?php

namespace App\Services;

use App\Models\TrainerProfile;
use App\Models\User;

class TrainerProfileSyncService
{
    /**
     * Создаёт или восстанавливает профиль тренера, если у пользователя роль trainer.
     * При смене роли на другую — мягко удаляет профиль.
     */
    public function syncForUser(User $user): void
    {
        $user->loadMissing('role');

        $partnerId = (int) ($user->partner_id ?? 0);
        if ($partnerId <= 0) {
            return;
        }

        if ($user->role?->name !== 'trainer') {
            TrainerProfile::query()
                ->where('user_id', $user->id)
                ->delete();

            return;
        }

        $profile = TrainerProfile::withTrashed()
            ->where('user_id', $user->id)
            ->first();

        if ($profile) {
            if ($profile->trashed()) {
                $profile->restore();
            }

            $profile->update([
                'partner_id' => $partnerId,
                'is_enabled' => (bool) ($user->is_enabled ?? true),
            ]);

            return;
        }

        TrainerProfile::create([
            'user_id' => $user->id,
            'partner_id' => $partnerId,
            'description' => null,
            'is_enabled' => (bool) ($user->is_enabled ?? true),
            'sort_order' => 0,
        ]);
    }
}
