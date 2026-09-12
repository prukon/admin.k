<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фикстура: цена &gt; 0 только вместе с абонементом.
 */
abstract class SettingPricesRequirePackageForPriceTestCase extends CrmTestCase
{
    protected const MONTH_LABEL = 'Сентябрь 2024';

    protected const MONTH_DATE = '2024-09-01';

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

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа require-package',
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Иван',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);
        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Тариф с пакетом',
            'price_cents' => 450000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
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

    /**
     * @return array{selectedDate:string,teamId:int,usersPrice:list<array{user_id:int,price:float,lesson_package_id:int|null,user:array{name:string}}>}
     */
    protected function monthlyPayload(float $price, ?int $packageId, ?User $student = null): array
    {
        $student ??= $this->student;

        return [
            'selectedDate' => self::MONTH_LABEL,
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->rightPayload($student, $price, $packageId),
            ],
        ];
    }

    /**
     * @return array{user_id:int,team_id:int,year:int,prices:list<array{new_month:string,price:float,lesson_package_id:int|null}>}
     */
    protected function yearPayload(float $price, ?int $packageId, string $month = self::MONTH_DATE): array
    {
        return [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => self::YEAR,
            'prices' => [[
                'new_month' => $month,
                'price' => $price,
                'lesson_package_id' => $packageId,
            ]],
        ];
    }

    protected function seedUnpaidMonth(int $priceCents = 0, ?int $packageId = null): UserPrice
    {
        return UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => $priceCents,
            'is_paid' => 0,
            'lesson_package_id' => $packageId,
        ]);
    }

    protected function seedPaidMonth(int $priceCents = 450000, ?int $packageId = null): UserPrice
    {
        return UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => $priceCents,
            'is_paid' => 1,
            'lesson_package_id' => $packageId,
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
