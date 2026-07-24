<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Вкладка «по ученикам»: абонемент + цена по месяцам, обратная совместимость legacy-цен.
 */
final class SettingPricesUsersYearPackageFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
        ]);

        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);

        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Год тариф',
            'price_cents' => 450000,
        ]);
    }

    public function test_user_year_prices_returns_lesson_packages_and_package_id(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-03-01',
            'price' => 3200,
            'is_paid' => 0,
            'lesson_package_id' => $this->package->id,
        ]);

        $response = $this->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => 2024,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'lessonPackages' => [
                    ['id', 'name', 'price'],
                ],
                'months' => [
                    ['new_month', 'price', 'lesson_package_id'],
                ],
            ]);

        $packages = collect($response->json('lessonPackages'));
        $this->assertTrue($packages->contains(fn ($p) => (int) $p['id'] === (int) $this->package->id));

        $march = collect($response->json('months'))->firstWhere('new_month', '2024-03-01');
        $this->assertNotNull($march);
        $this->assertSame((int) $this->package->id, (int) $march['lesson_package_id']);
        $this->assertSame(3200.0, (float) $march['price']);
    }

    public function test_legacy_price_without_package_is_preserved_on_load_and_price_only_save(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-04-01',
            'price' => 16800,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => 2024,
        ])->assertOk()
            ->assertJsonPath('months.3.price', 16800)
            ->assertJsonPath('months.3.lesson_package_id', null);

        // Сохранение только цены без пакета (как старый клиент) — сумма не обнуляется
        $this->postJson(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => 2024,
            'prices' => [
                ['new_month' => '2024-04-01', 'price' => 16800],
            ],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-04-01',
            'price' => 16800,
            'lesson_package_id' => null,
        ]);
    }

    public function test_save_snapshots_package_and_does_not_create_user_lesson_packages(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-05-01',
            'price' => 1000,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->postJson(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => 2024,
            'prices' => [
                [
                    'new_month' => '2024-05-01',
                    'price' => 4500,
                    'lesson_package_id' => $this->package->id,
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-05-01',
            'price' => 4500,
            'lesson_package_id' => $this->package->id,
        ]);
        $this->assertSame(0, UserLessonPackage::query()->count());
    }

    public function test_save_skips_effective_paid_month(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-06-01',
            'price' => 2000,
            'is_paid' => 1,
            'lesson_package_id' => null,
        ]);

        $this->postJson(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => 2024,
            'prices' => [
                [
                    'new_month' => '2024-06-01',
                    'price' => 4500,
                    'lesson_package_id' => $this->package->id,
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-06-01',
            'price' => 2000,
            'is_paid' => 1,
            'lesson_package_id' => null,
        ]);
    }

    public function test_save_rejects_foreign_package(): void
    {
        $foreign = LessonPackage::factory()->forPartner(
            (int) \App\Models\Partner::factory()->create()->id
        )->create(['price_cents' => 10000]);

        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-07-01',
            'price' => 1000,
            'is_paid' => 0,
        ]);

        $this->postJson(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => 2024,
            'prices' => [
                [
                    'new_month' => '2024-07-01',
                    'price' => 1000,
                    'lesson_package_id' => $foreign->id,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['prices.0.lesson_package_id']);
    }

    public function test_save_unchanged_price_without_package_key_does_not_wipe_existing_package(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-08-01',
            'price' => 4500,
            'is_paid' => 0,
            'lesson_package_id' => $this->package->id,
        ]);

        // Старый клиент: без lesson_package_id и без изменения цены — no-op, пакет на месте
        $this->postJson(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => 2024,
            'prices' => [
                ['new_month' => '2024-08-01', 'price' => 4500],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-08-01',
            'price' => 4500,
            'lesson_package_id' => $this->package->id,
        ]);
    }
}
