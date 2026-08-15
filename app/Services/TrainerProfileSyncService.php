<?php

namespace App\Services;

use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Trainers\TrainerTypeCatalog;

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

            if (! $profile->trainer_type_id) {
                $type = app(TrainerTypeCatalog::class)->ensureSystemType($partnerId);
                $profile->forceFill(['trainer_type_id' => $type->id])->save();
            }

            return;
        }

        $type = app(TrainerTypeCatalog::class)->ensureSystemType($partnerId);

        TrainerProfile::create([
            'user_id' => $user->id,
            'partner_id' => $partnerId,
            'trainer_type_id' => $type->id,
            'description' => null,
            'is_enabled' => (bool) ($user->is_enabled ?? true),
            'sort_order' => 0,
        ]);
    }
}
