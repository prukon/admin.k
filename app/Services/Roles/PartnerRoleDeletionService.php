<?php

namespace App\Services\Roles;

use App\Models\MyLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PartnerRoleDeletionService
{
    /**
     * Удаляет кастомную роль в контексте одного партнёра:
     * снимает права и привязку partner_role, переводит пользователей этой школы на роль user,
     * удаляет запись roles только если роль больше ни к одному партнёру не привязана.
     *
     * @throws NotFoundHttpException роль не найдена или не принадлежит партнёру
     * @throws \InvalidArgumentException системная роль
     */
    public function deleteForPartner(int $partnerId, int $roleId, ?int $authorId = null): void
    {
        /** @var Role|null $role */
        $role = Role::query()->whereKey($roleId)->first();

        if (! $role) {
            throw new NotFoundHttpException('Роль не найдена.');
        }

        if ((int) $role->is_sistem === 1) {
            throw new \InvalidArgumentException('Нельзя удалять системную роль.');
        }

        $belongsToPartner = DB::table('partner_role')
            ->where('partner_id', $partnerId)
            ->where('role_id', $role->id)
            ->exists();

        if (! $belongsToPartner) {
            throw new NotFoundHttpException('Роль не найдена или недоступна для текущего партнёра.');
        }

        $defaultRole = Role::firstOrCreate(
            ['name' => 'user'],
            [
                'label' => 'Пользователь',
                'is_sistem' => 1,
                'order_by' => 0,
            ]
        );

        $roleLabel = (string) ($role->label ?? $role->name);
        $roleName = (string) $role->name;

        DB::transaction(function () use ($role, $partnerId, $defaultRole, $authorId, $roleLabel, $roleName) {
            DB::table('permission_role')
                ->where('role_id', $role->id)
                ->where('partner_id', $partnerId)
                ->delete();

            User::query()
                ->where('partner_id', $partnerId)
                ->where('role_id', $role->id)
                ->update(['role_id' => $defaultRole->id]);

            DB::table('partner_role')
                ->where('partner_id', $partnerId)
                ->where('role_id', $role->id)
                ->delete();

            $stillLinkedToPartners = DB::table('partner_role')
                ->where('role_id', $role->id)
                ->exists();

            if (! $stillLinkedToPartners) {
                $role->delete();
            }

            MyLog::create([
                'type' => 700,
                'action' => 730,
                'author_id' => $authorId,
                'partner_id' => $partnerId,
                'description' => sprintf('Название: %s', $roleName !== '' ? $roleName : $roleLabel),
                'created_at' => now(),
            ]);
        });
    }
}
