<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Http\Requests\Admin\SetManualUserPricePaidRequest;
use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фикстура: ручную оплату при итоговых 0 ₽ ставить нельзя.
 */
abstract class SettingPricesManualPaidZeroPriceTestCase extends CrmTestCase
{
    protected const MONTH_LABEL = 'Октябрь 2024';

    protected const MONTH_DATE = '2024-10-01';

    protected const YEAR = 2024;

    protected Team $team;

    protected User $student;

    protected LessonPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule']);
        $this->grantPermission($this->user, 'setPrices.manualPaid.manage');

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа zero-paid',
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Тимофей',
            'lastname' => 'Голин',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);
        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => '12 занятий в месяц',
            'price_cents' => 980000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
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
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function paidPayload(array $extra = []): array
    {
        return array_merge([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => self::MONTH_LABEL,
            'mode' => 'paid',
            'comment' => 'Перерасчет нулевой суммы',
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function unpaidPayload(array $extra = []): array
    {
        return array_merge([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => self::MONTH_LABEL,
            'mode' => 'unpaid',
            'comment' => 'Снять ошибочную отметку при нуле',
        ], $extra);
    }

    protected function seedZeroUnpaidMonth(): UserPrice
    {
        return UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 0,
            'is_paid' => 0,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    protected function seedPositiveUnpaidMonth(int $priceCents = 980000): UserPrice
    {
        return UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => $priceCents,
            'is_paid' => 0,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    protected function seedZeroManuallyPaidMonth(): UserPrice
    {
        return UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 0,
            'is_paid' => 0,
            'is_manual_paid' => 1,
            'lesson_package_id' => $this->package->id,
        ]);
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

    protected function zeroPriceMessage(): string
    {
        return SetManualUserPricePaidRequest::ZERO_PRICE_MESSAGE;
    }

    /**
     * Laravel assertJsonPath дробит ключи с точками.
     */
    protected function jsonFieldError(\Illuminate\Testing\TestResponse $response, string $field): string
    {
        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey($field, $errors, 'Нет errors['.$field.']: '.json_encode($errors, JSON_UNESCAPED_UNICODE));

        return (string) ($errors[$field][0] ?? '');
    }
}
