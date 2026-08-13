<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\Postpay\PostpayAmountCalculator;
use App\Services\TeamUserSyncService;
use App\Support\Money;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Персональная скидка ученика в установке цен (monthly apply-all, right apply, year JSON).
 *
 * @see /docs/documentation/setting-prices-monthly-users.html
 * @see /docs/documentation/admin-users.html#user-percent-discount
 */
final class SettingPricesUserPercentDiscountFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $withDiscount;

    private User $withoutDiscount;

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
            'title' => 'Группа скидка',
        ]);

        $this->withDiscount = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'СоСкидкой',
            'lastname' => 'Ученик',
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);
        $this->withoutDiscount = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'БезСкидки',
            'lastname' => 'Ученик',
        ]);

        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Тариф 1000',
            'price_cents' => 100000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
            'is_active' => true,
        ]);
    }

    public function test_set_team_price_applies_per_student_discount(): void
    {
        $this->postJson(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->package->id,
            'selectedDate' => 'Сентябрь 2024',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('teamPrice', 1000);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price_cents' => 90000,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->withoutDiscount->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price_cents' => 100000,
        ]);
        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2024-09-01',
            'price_cents' => 100000,
        ]);
    }

    public function test_right_apply_stamps_snapshot_when_submitted_matches_catalog_formula(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 0,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Октябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->withDiscount->id,
                    'price' => 900.0,
                    'lesson_package_id' => $this->package->id,
                    'user' => ['name' => $this->withDiscount->name],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $row = UserPrice::query()
            ->where('user_id', $this->withDiscount->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2024-10-01')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(90000, (int) $row->price_cents);
        $this->assertSame(10, (int) $row->discount_percent);
        $this->assertSame('Льгота', (string) $row->discount_comment);
    }

    public function test_right_apply_clears_snapshot_on_manual_override(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 0,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Октябрь 2024',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->withDiscount->id,
                    'price' => 850.0,
                    'lesson_package_id' => $this->package->id,
                    'user' => ['name' => $this->withDiscount->name],
                ],
            ],
        ])->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->withDiscount->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2024-10-01')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(85000, (int) $row->price_cents);
        $this->assertNull($row->discount_percent);
        $this->assertNull($row->discount_comment);
    }

    public function test_changing_card_percent_does_not_recalculate_existing_row(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 90000,
            'is_paid' => 0,
            'lesson_package_id' => $this->package->id,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);

        $this->withDiscount->update([
            'discount_percent' => 50,
            'discount_comment' => 'Новая льгота',
        ]);

        $row = UserPrice::query()
            ->where('user_id', $this->withDiscount->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2024-10-01')
            ->firstOrFail();

        $this->assertSame(90000, (int) $row->price_cents);
        $this->assertSame(10, (int) $row->discount_percent);
    }

    public function test_user_year_prices_json_includes_applied_and_card_discount(): void
    {
        UserPrice::forceCreate([
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-03-01',
            'price_cents' => 90000,
            'is_paid' => 0,
            'lesson_package_id' => $this->package->id,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);

        $json = $this->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'year' => 2024,
        ])->assertOk()->json();

        $this->assertTrue((bool) ($json['success'] ?? false));
        $this->assertSame(10, (int) $json['user']['discount_percent']);
        $this->assertSame('Льгота', (string) $json['user']['discount_comment']);

        $march = collect($json['months'] ?? [])->firstWhere('new_month', '2024-03-01');
        $this->assertNotNull($march);
        $this->assertSame(10, (int) $march['applied_discount_percent']);
        $this->assertSame('Скидка 10%. Льгота', (string) $march['applied_discount_tooltip']);
    }

    public function test_postpay_calculator_uses_row_snapshot_not_later_card_percent(): void
    {
        $postpay = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Постоплата скидка',
            'price_cents' => 100000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'is_active' => true,
        ]);

        $row = UserPrice::forceCreate([
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 0,
            'is_paid' => 0,
            'lesson_package_id' => $postpay->id,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);
        $row->setRelation('lessonPackage', $postpay);

        $this->withDiscount->update(['discount_percent' => 50]);

        $calc = app(PostpayAmountCalculator::class)->forUserPrice($row, $postpay);
        $this->assertSame(100000, $calc['price_per_lesson_cents']);
        $this->assertSame(0, $calc['visits']);
        $this->assertSame(0, $calc['amount_cents']);

        $grossTwoVisits = 2 * 100000;
        $this->assertSame(
            180000,
            Money::payableAfterDiscountCents($grossTwoVisits, (int) $row->discount_percent)
        );
        $this->assertSame(
            100000,
            Money::payableAfterDiscountCents($grossTwoVisits, 50)
        );
    }

    public function test_year_save_stamps_snapshot_when_submitted_matches_catalog_formula(): void
    {
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->withDiscount, [(int) $this->team->id]);

        UserPrice::forceCreate([
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-05-01',
            'price_cents' => 0,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->postJson(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'year' => 2024,
            'prices' => [
                [
                    'new_month' => '2024-05-01',
                    'price' => 900,
                    'lesson_package_id' => $this->package->id,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $row = UserPrice::query()
            ->where('user_id', $this->withDiscount->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2024-05-01')
            ->firstOrFail();

        $this->assertSame(90000, (int) $row->price_cents);
        $this->assertSame(10, (int) $row->discount_percent);
        $this->assertSame('Льгота', (string) $row->discount_comment);
    }

    public function test_year_save_clears_snapshot_on_manual_override(): void
    {
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->withDiscount, [(int) $this->team->id]);

        UserPrice::forceCreate([
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-05-01',
            'price_cents' => 0,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->postJson(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'year' => 2024,
            'prices' => [
                [
                    'new_month' => '2024-05-01',
                    'price' => 850,
                    'lesson_package_id' => $this->package->id,
                ],
            ],
        ])->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->withDiscount->id)
            ->where('team_id', $this->team->id)
            ->where('new_month', '2024-05-01')
            ->firstOrFail();

        $this->assertSame(85000, (int) $row->price_cents);
        $this->assertNull($row->discount_percent);
        $this->assertNull($row->discount_comment);
    }

    public function test_get_team_price_json_includes_applied_snapshot_on_student_rows(): void
    {
        $this->postJson(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->package->id,
            'selectedDate' => 'Сентябрь 2024',
        ])->assertOk();

        $json = $this->postJson(route('getTeamPrice'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamId' => $this->team->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertNotSame('', trim((string) json_encode($json, JSON_UNESCAPED_UNICODE)));

        $row = collect($json['usersPrice'] ?? [])->first(
            fn ($item) => (int) ($item['user_id'] ?? 0) === (int) $this->withDiscount->id
        );
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(900.0, (float) ($row['price'] ?? 0), 0.001);
        $this->assertSame(10, (int) ($row['applied_discount_percent'] ?? 0));
        $this->assertSame('Льгота', (string) ($row['applied_discount_comment'] ?? ''));
        $this->assertSame('Скидка 10%. Льгота', (string) ($row['applied_discount_tooltip'] ?? ''));
        $this->assertSame(10, (int) ($row['user_discount_percent'] ?? 0));

        $without = collect($json['usersPrice'] ?? [])->first(
            fn ($item) => (int) ($item['user_id'] ?? 0) === (int) $this->withoutDiscount->id
        );
        $this->assertNotNull($without);
        $this->assertEqualsWithDelta(1000.0, (float) ($without['price'] ?? 0), 0.001);
        $this->assertTrue(
            ($without['applied_discount_percent'] ?? null) === null
            || (int) $without['applied_discount_percent'] === 0
        );
    }

    public function test_set_team_price_non_ajax_redirects_and_stamps_per_student_discount(): void
    {
        $response = $this->post(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->package->id,
            'selectedDate' => 'Октябрь 2024',
        ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 90000,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);
        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 100000,
        ]);
    }

    public function test_year_save_non_ajax_redirects_and_stamps_snapshot(): void
    {
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->withDiscount, [(int) $this->team->id]);

        UserPrice::forceCreate([
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-06-01',
            'price_cents' => 0,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $this->post(route('setting-prices.user-year-prices.save'), [
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'year' => 2024,
            'prices' => [
                [
                    'new_month' => '2024-06-01',
                    'price' => 900,
                    'lesson_package_id' => $this->package->id,
                ],
            ],
        ])->assertRedirect(route('admin.settingPrices.users'));

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->withDiscount->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-06-01',
            'price_cents' => 90000,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);
    }

    public function test_monthly_and_year_pages_include_discount_badge_helpers(): void
    {
        $this->withoutVite();

        $monthly = (string) $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('KidsCrmUserDiscount', $monthly);
        $this->assertStringContainsString('kids-user-discount-price-wrap', $monthly);

        $year = (string) $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('KidsCrmUserDiscount', $year);
        $this->assertStringContainsString('payableRubAfterUserDiscount', $year);
        $this->assertStringContainsString('applied_discount_percent', $year);
    }
}
