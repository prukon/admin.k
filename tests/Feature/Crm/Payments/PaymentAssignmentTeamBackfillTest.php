<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\Team;
use App\Models\UserCustomPayment;
use App\Services\Payments\PaymentAssignmentTeamBackfill;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;

final class PaymentAssignmentTeamBackfillTest extends CrmTestCase
{
    public function test_backfill_sets_primary_team_on_custom_payment_without_team_id(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа Backfill']);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        $row = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => null,
            'amount' => '150.00',
            'is_paid' => false,
        ]);

        $result = app(PaymentAssignmentTeamBackfill::class)->run();

        $this->assertGreaterThanOrEqual(1, $result['custom_payments_updated']);
        $this->assertSame((int) $team->id, (int) $row->fresh()->team_id);
    }

    public function test_backfill_skips_custom_payment_when_student_has_no_groups(): void
    {
        $row = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => null,
            'amount' => '99.00',
            'is_paid' => false,
        ]);

        app(PaymentAssignmentTeamBackfill::class)->run();

        $this->assertNull($row->fresh()->team_id);
    }

    public function test_backfill_sets_primary_team_on_lesson_package_without_team_id(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа ULP Backfill']);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        $package = \App\Models\LessonPackage::factory()->create([
            'partner_id' => $this->partner->id,
            'schedule_type' => 'no_schedule',
        ]);

        $ulp = \App\Models\UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'team_id' => null,
            'lesson_package_id' => $package->id,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => '500.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $result = app(PaymentAssignmentTeamBackfill::class)->run();

        $this->assertGreaterThanOrEqual(1, $result['lesson_packages_updated']);
        $this->assertSame((int) $team->id, (int) $ulp->fresh()->team_id);
    }
}
