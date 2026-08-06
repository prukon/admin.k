<?php

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Правая колонка «по месяцам»: смена абонемента в select → «Применить» (setPriceAllUsers).
 *
 * Контракт как у фронта settings-prices.js (buildRightApplyPayloadFromDom):
 * selectedDate, teamId, usersPrice[{user_id, price, lesson_package_id, user}].
 */
final class SettingPricesMonthlyRightApplyPackageFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $studentA;

    private User $studentB;

    private LessonPackage $packageA;

    private LessonPackage $packageB;

    private LessonPackage $postpayPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа right-apply',
        ]);

        $this->studentA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Антон',
            'lastname' => 'Алов',
        ]);
        $this->studentB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Борис',
            'lastname' => 'Белов',
        ]);

        $this->packageA = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Тариф A',
            'price_cents' => 500000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
            'is_active' => true,
        ]);
        $this->packageB = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Тариф B',
            'price_cents' => 700000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
            'is_active' => true,
        ]);
        $this->postpayPackage = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Постоплата тест',
            'price_cents' => 120000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'is_active' => true,
        ]);
    }

    /**
     * Сценарий UI: select был пустой → выбрали абонемент → «Применить» справа.
     */
    public function test_right_apply_sets_package_when_changing_from_empty_to_package(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->studentA->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price' => 0,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Октябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->frontPayloadRow($this->studentA, 5000.0, (int) $this->packageA->id),
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->studentA->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'lesson_package_id' => $this->packageA->id,
            'price' => 5000,
        ]);

        $row = $this->findResponseUserPrice(
            $this->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
            ])->assertOk()->json('usersPrice'),
            (int) $this->studentA->id
        );
        $this->assertSame((int) $this->packageA->id, (int) $row['lesson_package_id']);
        $this->assertSame(5000.0, (float) $row['price']);
    }

    /**
     * Сценарий UI: был абонемент A → в select выбрали B → «Применить» справа.
     */
    public function test_right_apply_changes_existing_package_a_to_b(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->studentA->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price' => 5000,
            'is_paid' => 0,
            'lesson_package_id' => $this->packageA->id,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Октябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                // Как после смены select: цена тарифа B + lesson_package_id B
                $this->frontPayloadRow($this->studentA, 7000.0, (int) $this->packageB->id),
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->studentA->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'lesson_package_id' => $this->packageB->id,
            'price' => 7000,
        ]);
        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $this->studentA->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'lesson_package_id' => $this->packageA->id,
        ]);
    }

    /**
     * Несколько учеников: у каждого свой новый абонемент в одном Apply.
     */
    public function test_right_apply_changes_packages_for_multiple_students_in_one_request(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->studentA->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price' => 0,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);
        UserPrice::forceCreate([
            'user_id' => $this->studentB->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price' => 5000,
            'is_paid' => 0,
            'lesson_package_id' => $this->packageA->id,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Октябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->frontPayloadRow($this->studentA, 7000.0, (int) $this->packageB->id),
                $this->frontPayloadRow($this->studentB, 5000.0, (int) $this->packageA->id),
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->studentA->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'lesson_package_id' => $this->packageB->id,
            'price' => 7000,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->studentB->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'lesson_package_id' => $this->packageA->id,
            'price' => 5000,
        ]);
    }

    /**
     * Смена постоплаты на обычный тариф через «Применить» справа.
     */
    public function test_right_apply_switches_from_postpay_to_flexible_package(): void
    {
        $this->grantPermission('lessonPackages.type.postpay');

        UserPrice::forceCreate([
            'user_id' => $this->studentA->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price' => 0,
            'is_paid' => 0,
            'lesson_package_id' => $this->postpayPackage->id,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Октябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->frontPayloadRow($this->studentA, 5000.0, (int) $this->packageA->id),
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->studentA->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'lesson_package_id' => $this->packageA->id,
            'price' => 5000,
        ]);
    }

    /**
     * Смена обычного тарифа на постоплату через «Применить» справа.
     */
    public function test_right_apply_switches_from_flexible_to_postpay_package(): void
    {
        $this->grantPermission('lessonPackages.type.postpay');

        UserPrice::forceCreate([
            'user_id' => $this->studentA->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price' => 5000,
            'is_paid' => 0,
            'lesson_package_id' => $this->packageA->id,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Октябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                // Фронт для postpay шлёт visits×rate (здесь 0 посещений → 0).
                $this->frontPayloadRow($this->studentA, 0.0, (int) $this->postpayPackage->id),
            ],
        ])->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->studentA->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2024-10-01')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame((int) $this->postpayPackage->id, (int) $row->lesson_package_id);
        $this->assertSame(0.0, (float) $row->price);
    }

    /**
     * Оплаченный месяц: смена абонемента в payload игнорируется.
     */
    public function test_right_apply_does_not_change_package_for_paid_student(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->studentA->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price' => 5000,
            'is_paid' => 1,
            'lesson_package_id' => $this->packageA->id,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Октябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                $this->frontPayloadRow($this->studentA, 7000.0, (int) $this->packageB->id),
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->studentA->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'lesson_package_id' => $this->packageA->id,
            'price' => 5000,
            'is_paid' => 1,
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{user_id:int,price:float,lesson_package_id:int|null,user:array{name:string}}
     */
    private function frontPayloadRow(User $student, float $price, ?int $lessonPackageId): array
    {
        return [
            'user_id' => (int) $student->id,
            'price' => $price,
            'lesson_package_id' => $lessonPackageId,
            'user' => ['name' => (string) $student->name],
        ];
    }

    /**
     * @param  list<array<string,mixed>>|null  $usersPrice
     * @return array<string,mixed>
     */
    private function findResponseUserPrice(?array $usersPrice, int $userId): array
    {
        $this->assertIsArray($usersPrice);
        foreach ($usersPrice as $row) {
            if ((int) ($row['user_id'] ?? 0) === $userId) {
                return $row;
            }
        }
        $this->fail("usersPrice row for user_id={$userId} not found");
    }
}
