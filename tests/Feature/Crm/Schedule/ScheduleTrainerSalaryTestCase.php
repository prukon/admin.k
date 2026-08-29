<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\User;
use App\Models\TrainerProfile;
use App\Services\Trainers\TrainerTypeCatalog;
use Illuminate\Support\Facades\DB;

abstract class ScheduleTrainerSalaryTestCase extends ScheduleJournalTestCase
{
    protected function grantTrainerSalaryView(?User $actor = null): void
    {
        $this->grantPermission('schedule.trainerSalary.view', $actor);
        $this->grantClassicScheme($actor);
    }

    protected function grantTrainerSalaryManage(?User $actor = null): void
    {
        $this->grantPermission('schedule.trainerSalary.manage', $actor);
    }

    protected function grantClassicScheme(?User $actor = null): void
    {
        $this->grantPermission('schedule.trainerSalary.scheme.classic', $actor);
    }

    protected function grantKansasScheme(?User $actor = null): void
    {
        $this->grantPermission('schedule.trainerSalary.scheme.kansas', $actor);
    }

    protected function grantSalesScheme(?User $actor = null): void
    {
        $this->grantPermission('schedule.trainerSalary.scheme.sales', $actor);
    }

    protected function grantTrainerSalaryViewSales(?User $actor = null): void
    {
        $this->grantPermission('schedule.trainerSalary.view', $actor);
        $this->grantSalesScheme($actor);
    }

    protected function grantTrainerSalaryViewKansas(?User $actor = null): void
    {
        $this->grantPermission('schedule.trainerSalary.view', $actor);
        $this->grantKansasScheme($actor);
    }

    protected function revokePermission(string $permissionName, ?User $actor = null): void
    {
        $actor ??= $this->user;

        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $actor->role_id)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
    }

    protected function useClassicSchemeOnly(?User $actor = null): void
    {
        $this->revokePermission('schedule.trainerSalary.scheme.kansas', $actor);
        $this->revokePermission('schedule.trainerSalary.scheme.sales', $actor);
        $this->grantClassicScheme($actor);
    }

    protected function useKansasSchemeOnly(?User $actor = null): void
    {
        $this->revokePermission('schedule.trainerSalary.scheme.classic', $actor);
        $this->revokePermission('schedule.trainerSalary.scheme.sales', $actor);
        $this->grantKansasScheme($actor);
    }

    protected function useSalesSchemeOnly(?User $actor = null): void
    {
        $this->revokePermission('schedule.trainerSalary.scheme.classic', $actor);
        $this->revokePermission('schedule.trainerSalary.scheme.kansas', $actor);
        $this->grantSalesScheme($actor);
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

    protected function setTrainerTypeRates(
        TrainerProfile $trainer,
        int|string $rateRubles,
        int|string $premiumRubles = 0,
    ): void {
        $catalog = app(TrainerTypeCatalog::class);
        $type = $catalog->ensureProfileHasType($trainer->fresh(['trainerType']));
        $catalog->update($type, [
            'name' => $type->name,
            'sort_order' => (int) $type->sort_order,
            'is_enabled' => true,
            'rate_per_training' => $rateRubles,
            'base_premium' => $premiumRubles,
        ]);
        $trainer->refresh();
    }
}
