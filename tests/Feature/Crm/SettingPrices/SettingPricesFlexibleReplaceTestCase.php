<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фикстура замены предоплаты в установке цен (разложенный / оплаченный месяц).
 */
abstract class SettingPricesFlexibleReplaceTestCase extends CrmTestCase
{
    protected const MONTH_LABEL = 'Март 2026';

    protected const MONTH_DATE = '2026-03-01';

    protected const YEAR = 2026;

    protected Team $team;

    protected User $student;

    protected LessonPackage $from;

    protected LessonPackage $to;

    protected LessonPackage $small;

    protected LessonPackage $fixed;

    private int $layoutSlotSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule']);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа flexible replace',
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Предоплата',
            'lastname' => 'Ученик',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);

        $this->from = LessonPackage::factory()->forPartner((int) $this->partner->id)->flexible(8, 60)->create([
            'name' => 'Предоплата 8',
            'price_cents' => 500000,
            'is_active' => true,
        ]);
        $this->to = LessonPackage::factory()->forPartner((int) $this->partner->id)->flexible(12, 60)->create([
            'name' => 'Предоплата 12',
            'price_cents' => 800000,
            'is_active' => true,
        ]);
        $this->small = LessonPackage::factory()->forPartner((int) $this->partner->id)->flexible(4, 60)->create([
            'name' => 'Предоплата 4',
            'price_cents' => 300000,
            'is_active' => true,
        ]);
        $this->fixed = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(8, 90)->create([
            'name' => 'Фикс 8',
            'price_cents' => 900000,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    /**
     * @return array{user_id:int,price:float,lesson_package_id:int|null,user:array{name:string}}
     */
    protected function rightPayload(User $student, float $price, ?int $packageId): array
    {
        return [
            'user_id' => (int) $student->id,
            'price' => $price,
            'lesson_package_id' => $packageId,
            'user' => ['name' => (string) $student->name],
        ];
    }

    protected function seedPaidEmptyMonth(int $priceCents = 500000, bool $manualPaid = false): UserPrice
    {
        return UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => $priceCents,
            'is_paid' => $manualPaid ? 0 : 1,
            'is_manual_paid' => $manualPaid ? true : null,
            'lesson_package_id' => null,
        ]);
    }

    protected function createTrialUtss(string $date = '2026-03-04'): UserTeamScheduleSlot
    {
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'weekday' => 3,
            'time_start' => sprintf('15:%02d:00', 10 + $this->layoutSlotSeq),
            'time_end' => sprintf('16:%02d:00', 10 + $this->layoutSlotSeq),
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $this->layoutSlotSeq++;

        return UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => $date,
            'ends_at' => $date,
            'is_trial_lesson' => true,
            'trial_lessons_total' => 1,
            'trial_lessons_remaining' => 1,
            'created_by' => $this->user->id,
        ]);
    }

    protected function assignFromPackage(?User $student = null, float $price = 5000.0): UserPrice
    {
        $student = $student ?? $this->student;
        $row = UserPrice::forceCreate([
            'user_id' => $student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => (int) round($price * 100),
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => self::MONTH_LABEL,
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->rightPayload($student, $price, (int) $this->from->id),
            ],
        ])->assertOk();

        $row->refresh();
        $this->assertNotNull($row->user_lesson_package_id);

        return $row;
    }

    protected function layOut(UserLessonPackage $ulp): void
    {
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'weekday' => 3,
            'time_start' => sprintf('16:%02d:00', 41 + $this->layoutSlotSeq),
            'time_end' => sprintf('17:%02d:00', 41 + $this->layoutSlotSeq),
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $ulp->user_id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => '2026-03-04',
            'ends_at' => '2026-03-04',
            'created_by' => $this->user->id,
        ]);
        $this->layoutSlotSeq++;
    }

    /**
     * @return array{row: UserPrice, ulp: UserLessonPackage}
     */
    protected function seedLaidOutPrepaid(int $remaining = 5, bool $paid = false): array
    {
        $row = $this->assignFromPackage();
        $ulp = UserLessonPackage::query()->findOrFail($row->user_lesson_package_id);
        $this->layOut($ulp);
        $ulp->update(['lessons_remaining' => $remaining]);
        if ($paid) {
            $ulp->update(['is_paid' => true]);
            $row->update(['is_paid' => 1]);
        }

        return ['row' => $row->fresh(), 'ulp' => $ulp->fresh()];
    }

    protected function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Laravel assertJsonPath дробит ключи с точками (usersPrice.0.lesson_package_id).
     */
    protected function jsonFieldError(\Illuminate\Testing\TestResponse $response, string $field): string
    {
        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey($field, $errors, 'Нет errors['.$field.']: '.json_encode($errors, JSON_UNESCAPED_UNICODE));

        return (string) ($errors[$field][0] ?? '');
    }
}
