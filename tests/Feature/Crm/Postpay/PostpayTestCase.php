<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Postpay;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamUserSyncService;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Общая подготовка сценариев постоплаты (шаблон + users_prices, без ULP).
 */
abstract class PostpayTestCase extends CrmTestCase
{
    protected Team $team;

    protected User $student;

    protected LessonPackage $postpayPackage;

    protected ?int $attendedStatusId = null;

    protected function setUpPostpay(bool $withScheduleView = true, bool $withSetPricesView = true, bool $withLessonPackagesView = false): void
    {
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
        $this->attendedStatusId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);

        if ($withScheduleView) {
            $this->grantPermission('schedule.view');
        }
        if ($withSetPricesView) {
            $this->grantPermission('setPrices.view');
            $this->grantPermission('lessonPackages.type.postpay');
        }
        if ($withLessonPackagesView) {
            $this->grantPermission('lessonPackages.view');
            $this->grantPermission('lessonPackages.type.postpay');
        }

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа постоплата',
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'name' => 'Postpay',
            'lastname' => 'Student',
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->student, (int) $this->team->id);

        $this->postpayPackage = LessonPackage::factory()
            ->forPartner((int) $this->partner->id)
            ->postpay()
            ->create([
                'name' => 'Постоплата тест',
                'price_cents' => 50000,
            ]);
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

    protected function revokePermission(string $permissionName, ?User $actor = null): void
    {
        $actor ??= $this->user;

        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $actor->role_id)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
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

    protected function ensureUserPriceRow(
        string $month = '2026-08-01',
        ?int $packageId = null,
        float $price = 0,
        bool $paid = false,
        ?bool $manualPaid = null,
    ): UserPrice {
        /** @var UserPrice $row */
        $row = UserPrice::query()->updateOrCreate(
            [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'new_month' => $month,
            ],
            [
                'price_cents' => (int) round($price * 100),
                'lesson_package_id' => $packageId ?? $this->postpayPackage->id,
                'is_paid' => $paid,
                'is_manual_paid' => $manualPaid,
            ]
        );

        return $row->fresh();
    }

    protected function createConsumingCustomStatus(): LessonOccurrenceStatus
    {
        return LessonOccurrenceStatus::query()->create([
            'partner_id' => $this->partner->id,
            'code' => 'custom_consume_'.bin2hex(random_bytes(3)),
            'title' => 'Списывает',
            'icon' => 'fa-solid fa-star',
            'color' => '#112233',
            'is_system' => false,
            'is_active' => true,
            'consumes_lesson' => true,
            'sort_order' => 80,
        ]);
    }

    protected function assertNoUserLessonPackages(): void
    {
        $this->assertSame(0, \App\Models\UserLessonPackage::query()->count());
    }

    protected function countPostpayUtssForStudent(): int
    {
        return UserTeamScheduleSlot::query()
            ->where('user_id', $this->student->id)
            ->whereNull('user_lesson_package_id')
            ->where('is_trial_lesson', false)
            ->count();
    }
}
