<?php

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Выбор шаблона абонемента на вкладке «по месяцам»:
 * снимок цены + lesson_package_id в users_prices без создания user_lesson_packages.
 */
final class SettingPricesMonthlyLessonPackageFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Тариф Стандарт',
            'price_cents' => 450000,
            'is_active' => true,
        ]);
    }

    public function test_get_team_price_returns_lesson_packages_catalog(): void
    {
        $foreignPartner = Partner::factory()->create();
        LessonPackage::factory()->forPartner((int) $foreignPartner->id)->create([
            'name' => 'Чужой абонемент',
            'price_cents' => 10000,
        ]);

        $response = $this->postJson(route('getTeamPrice'), [
            'teamId' => $this->team->id,
            'selectedDate' => 'Сентябрь 2024',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'lessonPackages' => [
                    ['id', 'name', 'price'],
                ],
                'usersPrice',
                'usersTeam',
            ]);

        $packages = collect($response->json('lessonPackages'));
        $this->assertTrue($packages->contains(fn ($p) => (int) $p['id'] === (int) $this->package->id));
        $this->assertFalse($packages->contains(fn ($p) => $p['name'] === 'Чужой абонемент'));

        $own = $packages->firstWhere('id', $this->package->id);
        $this->assertSame(4500.0, (float) $own['price']);
    }

    public function test_set_price_all_users_snapshots_price_and_lesson_package_id(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 0,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->student->id,
                    'price' => 4500,
                    'lesson_package_id' => $this->package->id,
                    'user' => ['name' => $this->student->name],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 4500,
            'lesson_package_id' => $this->package->id,
        ]);

        $this->assertSame(0, UserLessonPackage::query()->count());
    }

    public function test_manual_price_override_keeps_lesson_package_id(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 4500,
            'is_paid' => 0,
            'lesson_package_id' => $this->package->id,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->student->id,
                    'price' => 3990,
                    'lesson_package_id' => $this->package->id,
                    'user' => ['name' => $this->student->name],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 3990,
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_legacy_price_without_package_still_updates(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 1000,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->student->id,
                    'price' => 1200,
                    'user' => ['name' => $this->student->name],
                ],
            ],
        ])->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2024-09-01')
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals(1200, (float) $row->price);
        $this->assertNull($row->lesson_package_id);
    }

    public function test_rejects_foreign_partner_lesson_package(): void
    {
        $foreignPartner = Partner::factory()->create();
        $foreignPackage = LessonPackage::factory()->forPartner((int) $foreignPartner->id)->create([
            'price_cents' => 100000,
        ]);

        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 0,
            'is_paid' => 0,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->student->id,
                    'price' => 1000,
                    'lesson_package_id' => $foreignPackage->id,
                    'user' => ['name' => $this->student->name],
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['usersPrice.0.lesson_package_id']);
    }

    public function test_changing_only_package_updates_lesson_package_id(): void
    {
        $other = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Тариф Плюс',
            'price_cents' => 450000,
        ]);

        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 4500,
            'is_paid' => 0,
            'lesson_package_id' => $this->package->id,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->student->id,
                    'price' => 4500,
                    'lesson_package_id' => $other->id,
                    'user' => ['name' => $this->student->name],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price' => 4500,
            'lesson_package_id' => $other->id,
        ]);
    }
}
