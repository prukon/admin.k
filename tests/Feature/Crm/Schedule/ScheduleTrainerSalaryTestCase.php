<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\User;
use Illuminate\Support\Facades\DB;

abstract class ScheduleTrainerSalaryTestCase extends ScheduleJournalTestCase
{
    protected function grantTrainerSalaryView(?User $actor = null): void
    {
        $this->grantPermission('schedule.trainerSalary.view', $actor);
    }

    protected function grantTrainerSalaryManage(?User $actor = null): void
    {
        $this->grantPermission('schedule.trainerSalary.manage', $actor);
    }

    protected function grantPermission(string $permissionName, ?User $actor = null): void
    {
        $actor ??= $this->user;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
