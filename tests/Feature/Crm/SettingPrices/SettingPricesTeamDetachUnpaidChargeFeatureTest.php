<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Enums\AuditEvent;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use App\Services\UserService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Уход из группы снимает неоплаченные неиспользованные начисления этой группы.
 */
final class SettingPricesTeamDetachUnpaidChargeFeatureTest extends CrmTestCase
{
    private Team $oldTeam;

    private Team $newTeam;

    private User $student;

    private LessonPackage $package;

    private TeamUserSyncService $teamSync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule']);
        $this->teamSync = app(TeamUserSyncService::class);

        $this->oldTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Старая группа',
        ]);
        $this->newTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Новая группа',
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'lastname' => 'Садовская',
            'name' => 'Полина',
        ]);
        $this->teamSync->syncTeamsForStudent($this->student, [
            (int) $this->oldTeam->id,
            (int) $this->newTeam->id,
        ]);
        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(8, 30)->create([
            'name' => 'Флорбол школа 2 раза',
            'price_cents' => 500000,
            'is_active' => true,
        ]);
    }

    public function test_leaving_team_clears_unpaid_unused_charges_and_keeps_paid_and_current_team(): void
    {
        $paid = $this->makeCharge($this->oldTeam, '2026-09-01', 336700, true);
        $unpaid = $this->makeChargeWithUlp($this->oldTeam, '2026-10-01', 550000);
        $kept = $this->makeCharge($this->newTeam, '2026-10-01', 500000, false);
        $ulpId = (int) $unpaid->user_lesson_package_id;

        $this->teamSync->syncTeamsForStudent($this->student, [(int) $this->newTeam->id]);

        $paid->refresh();
        $unpaid->refresh();
        $kept->refresh();

        $this->assertSame(336700, (int) $paid->price_cents);
        $this->assertSame(500000, (int) $kept->price_cents);
        $this->assertSame((int) $this->package->id, (int) $kept->lesson_package_id);
        $this->assertSame(0, (int) $unpaid->price_cents);
        $this->assertNull($unpaid->lesson_package_id);
        $this->assertNull($unpaid->user_lesson_package_id);
        $this->assertNull(UserLessonPackage::query()->find($ulpId));
        $this->assertSame([(int) $this->newTeam->id], $this->teamSync->teamIdsForStudent($this->student));

        $log = MyLog::query()->where('event', AuditEvent::PricingFormerChargeCleared->value)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('5500 руб.', (string) $log->description);
        $this->assertStringContainsString('Абонемент #'.$this->package->id, (string) $log->description);
        $this->assertStringContainsString('Октябрь 2026', (string) $log->description);
        $this->assertStringContainsString('Старая группа', (string) $log->description);
        $this->assertStringContainsString('Садовская Полина', (string) $log->description);
    }

    public function test_removing_student_from_all_teams_clears_unpaid_charge(): void
    {
        $row = $this->makeCharge($this->oldTeam, '2026-10-01', 550000, false);

        $this->teamSync->syncTeamsForStudent($this->student, []);

        $row->refresh();
        $this->assertSame(0, (int) $row->price_cents);
        $this->assertSame([], $this->teamSync->teamIdsForStudent($this->student));
    }

    public function test_used_package_stays_when_student_leaves_team(): void
    {
        $row = $this->makeChargeWithUlp($this->oldTeam, '2026-10-01', 550000);
        UserLessonPackage::query()->whereKey($row->user_lesson_package_id)->update([
            'lessons_remaining' => 7,
        ]);

        $this->teamSync->syncTeamsForStudent($this->student, [(int) $this->newTeam->id]);

        $row->refresh();
        $this->assertSame(550000, (int) $row->price_cents);
        $this->assertSame((int) $this->package->id, (int) $row->lesson_package_id);
        $this->assertNotNull(UserLessonPackage::query()->find($row->user_lesson_package_id));
        $this->assertSame([(int) $this->newTeam->id], $this->teamSync->teamIdsForStudent($this->student));
    }

    public function test_adding_team_does_not_clear_charges_of_teams_student_keeps(): void
    {
        $extra = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Третья группа',
        ]);
        $row = $this->makeCharge($this->oldTeam, '2026-10-01', 550000, false);

        $this->teamSync->syncTeamsForStudent($this->student, [
            (int) $this->oldTeam->id,
            (int) $this->newTeam->id,
            (int) $extra->id,
        ]);

        $row->refresh();
        $this->assertSame(550000, (int) $row->price_cents);
    }

    public function test_journal_sync_teams_clears_unpaid_charge_of_removed_team(): void
    {
        $row = $this->makeChargeWithUlp($this->oldTeam, '2026-10-01', 550000);

        $this->postJson(route('user.sync.teams', $this->student), [
            'team_ids' => [(int) $this->newTeam->id],
        ])->assertOk()->assertJsonPath('success', true);

        $row->refresh();
        $this->assertSame(0, (int) $row->price_cents);
        $this->assertNull(UserLessonPackage::query()->find($row->user_lesson_package_id));
    }

    public function test_user_card_update_clears_unpaid_charge_of_removed_team(): void
    {
        $row = $this->makeCharge($this->oldTeam, '2026-10-01', 550000, false);

        app(UserService::class)->update($this->student, [
            'team_ids' => [(int) $this->newTeam->id],
        ]);

        $row->refresh();
        $this->assertSame(0, (int) $row->price_cents);
        $this->assertSame([(int) $this->newTeam->id], $this->teamSync->teamIdsForStudent($this->student->fresh()));
    }

    public function test_deleting_team_does_not_clear_unpaid_charges(): void
    {
        $row = $this->makeCharge($this->oldTeam, '2026-10-01', 550000, false);

        $this->teamSync->detachTeamFromAllStudents((int) $this->oldTeam->id, (int) $this->partner->id);

        $row->refresh();
        $this->assertSame(550000, (int) $row->price_cents);
        $this->assertNotContains((int) $this->oldTeam->id, $this->teamSync->teamIdsForStudent($this->student));
    }

    private function makeCharge(Team $team, string $month, int $cents, bool $paid): UserPrice
    {
        return UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $team->id,
            'new_month' => $month,
            'price_cents' => $cents,
            'lesson_package_id' => $this->package->id,
            'is_paid' => $paid ? 1 : 0,
        ]);
    }

    private function makeChargeWithUlp(Team $team, string $month, int $cents): UserPrice
    {
        $lessons = (int) $this->package->lessons_count;
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $team->id,
            'lesson_package_id' => $this->package->id,
            'billing_month' => $month,
            'starts_at' => null,
            'ends_at' => '2026-10-31',
            'lessons_total' => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount_cents' => $cents,
            'is_paid' => false,
        ]);

        return UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $team->id,
            'new_month' => $month,
            'price_cents' => $cents,
            'lesson_package_id' => $this->package->id,
            'user_lesson_package_id' => $ulp->id,
            'is_paid' => 0,
        ]);
    }
}
