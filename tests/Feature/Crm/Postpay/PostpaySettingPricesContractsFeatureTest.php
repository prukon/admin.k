<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Postpay;

use App\Models\LessonPackage;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;

/**
 * AJAX / non-AJAX контракты «Установка цен» для шаблона postpay.
 *
 * @see \Tests\Feature\Crm\SettingPrices\SettingPricesMonthlyPackageNonAjaxSafetyNetFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PostpaySettingPricesContractsFeatureTest extends PostpayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPostpay(withScheduleView: false, withSetPricesView: true);

        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price' => 100,
            'is_paid' => false,
            'lesson_package_id' => null,
        ]);
    }

    public function test_set_price_all_users_ajax_assigns_postpay_and_returns_json_contract(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Август 2026',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    [
                        'user_id' => $this->student->id,
                        'price' => 9999,
                        'lesson_package_id' => $this->postpayPackage->id,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'usersPrice', 'selectedDate', 'lessonPackages']);

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame((int) $this->postpayPackage->id, (int) $row->lesson_package_id);
        $this->assertSame(0.0, (float) $row->price);
        $this->assertSame(0, UserLessonPackage::query()->count());
    }

    public function test_set_price_all_users_non_ajax_redirects_and_assigns_postpay_not_empty_200(): void
    {
        $response = $this->post(route('setPriceAllUsers'), [
            'selectedDate' => 'Август 2026',
            'teamId' => $this->team->id,
            'usersPrice' => [
                [
                    'user_id' => $this->student->id,
                    'price' => 9999,
                    'lesson_package_id' => $this->postpayPackage->id,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => $this->postpayPackage->id,
            'price' => 0,
        ]);
        $this->assertSame(0, UserLessonPackage::query()->count());
    }

    public function test_set_team_price_non_ajax_with_postpay_redirects_and_persists(): void
    {
        $response = $this->post(route('setTeamPrice'), [
            'teamId' => $this->team->id,
            'lesson_package_id' => $this->postpayPackage->id,
            'selectedDate' => 'Август 2026',
        ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => $this->postpayPackage->id,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => $this->postpayPackage->id,
        ]);
    }

    public function test_set_team_price_ajax_with_postpay_returns_json_contract(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->postpayPackage->id,
                'selectedDate' => 'Август 2026',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package_id', $this->postpayPackage->id)
            ->assertJsonPath('teamId', $this->team->id);
    }

    public function test_get_team_price_ajax_includes_postpay_meta_on_users(): void
    {
        $this->ensureUserPriceRow(price: 0);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('getTeamPrice'), [
                'teamId' => $this->team->id,
                'selectedDate' => 'Август 2026',
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        $packages = collect($response->json('lessonPackages') ?? []);
        $pkg = $packages->firstWhere('id', $this->postpayPackage->id);
        $this->assertNotNull($pkg);
        $this->assertTrue((bool) ($pkg['is_postpay'] ?? false));
        $this->assertSame(LessonPackage::SCHEDULE_TYPE_POSTPAY, (string) ($pkg['schedule_type'] ?? ''));

        $users = collect($response->json('usersPrice') ?? []);
        $this->assertNotEmpty($users);

        $studentRow = $users->first(function ($row) {
            return is_array($row) && (int) ($row['user_id'] ?? 0) === (int) $this->student->id;
        });
        $this->assertNotNull($studentRow);
        $this->assertTrue((bool) ($studentRow['is_postpay'] ?? false));
        $this->assertArrayHasKey('postpay_visits', $studentRow);
        $this->assertSame(0, (int) $studentRow['postpay_visits']);
    }

    public function test_set_price_all_users_ajax_validation_returns_422(): void
    {
        $this->from(route('admin.settingPrices.indexMenu'))
            ->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Август 2026',
                'teamId' => $this->team->id,
                'usersPrice' => [
                    [
                        'user_id' => $this->student->id,
                        'price' => 'bad',
                        'lesson_package_id' => $this->postpayPackage->id,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }
}
