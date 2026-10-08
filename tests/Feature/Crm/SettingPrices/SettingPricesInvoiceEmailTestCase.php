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
 * Фикстура ручного счёта на email из строки месяца.
 */
abstract class SettingPricesInvoiceEmailTestCase extends CrmTestCase
{
    protected const MONTH_DATE = '2026-10-01';

    protected const MONTH_LABEL = 'Октябрь 2026';

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

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Младшая группа',
            'deleted_at' => null,
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'email' => 'student-invoice@example.com',
            'name' => 'Иван',
            'lastname' => 'Тестов',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);

        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
            'name' => 'Фикс октябрь',
            'price_cents' => 500000,
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
     * @param  array<string, mixed>  $overrides
     */
    protected function charge(array $overrides = []): UserPrice
    {
        return UserPrice::query()->create(array_merge([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->package->id,
        ], $overrides));
    }

    /**
     * @return array{user_id: int, team_id: int, new_month: string}
     */
    protected function payload(UserPrice $row): array
    {
        return [
            'user_id' => (int) $row->user_id,
            'team_id' => (int) $row->team_id,
            'new_month' => substr((string) $row->new_month, 0, 10),
        ];
    }

    protected function grant(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function seedTbank(): void
    {
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_INVOICE',
            'token_password' => 'PWD_INVOICE',
            'e2c_terminal_key' => 'E2C_INVOICE',
            'e2c_token_password' => 'E2C_INVOICE_PWD',
        ]);
        $entity = $this->seedRegisteredLegalEntityForPartner($this->partner, 'SHOP-INVOICE');
        $this->team->update(['legal_entity_id' => $entity->id]);
    }
}
