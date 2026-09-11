<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к apply/save замены предоплаты: гость, без права, с правом, чужой партнёр, чужие методы.
 */
final class SettingPricesFlexibleReplaceAccessFeatureTest extends SettingPricesFlexibleReplaceTestCase
{
    public function test_guest_is_redirected_from_setting_prices_pages(): void
    {
        Auth::logout();

        foreach ([
            route('admin.settingPrices.indexMenu'),
            route('admin.settingPrices.users'),
        ] as $url) {
            $response = $this->get($url);
            $this->assertContains($response->getStatusCode(), [302, 401, 403]);
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_guest_cannot_replace_paid_prepaid(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);
        Auth::logout();

        $payloads = [
            [
                'url' => route('setPriceAllUsers'),
                'data' => [
                    'selectedDate' => self::MONTH_LABEL,
                    'teamId' => $this->team->id,
                    'usersPrice' => [
                        $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                    ],
                ],
            ],
            [
                'url' => route('setting-prices.user-year-prices.save'),
                'data' => [
                    'user_id' => $this->student->id,
                    'team_id' => $this->team->id,
                    'year' => self::YEAR,
                    'prices' => [[
                        'new_month' => self::MONTH_DATE,
                        'price' => 8000,
                        'lesson_package_id' => (int) $this->to->id,
                    ]],
                ],
            ],
            [
                'url' => route('setTeamPrice'),
                'data' => [
                    'selectedDate' => self::MONTH_LABEL,
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->to->id,
                ],
            ],
        ];

        foreach ($payloads as $item) {
            $json = $this->withHeaders($this->ajaxHeaders())->postJson($item['url'], $item['data']);
            $this->assertContains($json->getStatusCode(), [302, 401, 403]);
            $this->assertNotSame(500, $json->getStatusCode());

            $html = $this->from(route('admin.settingPrices.indexMenu'))
                ->post($item['url'], $item['data']);
            $this->assertContains($html->getStatusCode(), [302, 401, 403, 419]);
            $this->assertNotSame(500, $html->getStatusCode());
            $this->assertNotSame(200, $html->getStatusCode());
        }

        $seed['row']->refresh();
        $this->assertSame((int) $this->from->id, (int) $seed['row']->lesson_package_id);
        $this->assertSame(500000, (int) $seed['row']->price_cents);
    }

    public function test_user_without_set_prices_view_gets_403(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.indexMenu'))->assertForbidden();
        $this->get(route('admin.settingPrices.users'))->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ])
            ->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
                'prices' => [[
                    'new_month' => self::MONTH_DATE,
                    'price' => 8000,
                    'lesson_package_id' => (int) $this->to->id,
                ]],
            ])
            ->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->to->id,
            ])
            ->assertForbidden();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ])
            ->assertForbidden();

        $seed['row']->refresh();
        $this->assertSame((int) $this->from->id, (int) $seed['row']->lesson_package_id);
    }

    public function test_manager_with_view_can_replace_paid_prepaid(): void
    {
        $this->get(route('admin.settingPrices.indexMenu'))->assertOk();
        $this->get(route('admin.settingPrices.users'))->assertOk();

        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $this->team->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $seed['row']->refresh();
        $this->assertSame((int) $this->to->id, (int) $seed['row']->lesson_package_id);
        $this->assertSame(500000, (int) $seed['row']->price_cents);
    }

    public function test_get_patch_delete_on_replace_routes_are_method_not_allowed(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);
        $urls = [
            route('setPriceAllUsers'),
            route('setting-prices.user-year-prices.save'),
            route('setTeamPrice'),
        ];

        foreach ($urls as $url) {
            foreach (['GET', 'PATCH', 'DELETE'] as $method) {
                $html = $this->call($method, $url);
                $this->assertContains(
                    $html->getStatusCode(),
                    [404, 405],
                    "{$method} HTML {$url} → {$html->getStatusCode()}"
                );
                $this->assertNotSame(500, $html->getStatusCode());
                $this->assertNotSame(200, $html->getStatusCode());

                $json = $this->json($method, $url, []);
                $this->assertContains(
                    $json->getStatusCode(),
                    [404, 405],
                    "{$method} JSON {$url} → {$json->getStatusCode()}"
                );
                $this->assertNotSame(500, $json->getStatusCode());
                $this->assertNotSame(200, $json->getStatusCode());
            }
        }

        $seed['row']->refresh();
        $this->assertSame((int) $this->from->id, (int) $seed['row']->lesson_package_id);
    }

    public function test_foreign_team_returns_404_and_does_not_write(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);

        $foreignPartner = Partner::factory()->create();
        $foreignTeam = Team::factory()->create([
            'partner_id' => $foreignPartner->id,
            'deleted_at' => null,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $foreignTeam->id,
                'usersPrice' => [
                    $this->rightPayload($this->student, 8000.0, (int) $this->to->id),
                ],
            ])
            ->assertStatus(404);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'selectedDate' => self::MONTH_LABEL,
                'teamId' => $foreignTeam->id,
                'lesson_package_id' => $this->to->id,
            ])
            ->assertStatus(404);

        $seed['row']->refresh();
        $this->assertSame((int) $this->from->id, (int) $seed['row']->lesson_package_id);
    }

    public function test_foreign_student_year_save_returns_404(): void
    {
        $seed = $this->seedLaidOutPrepaid(remaining: 5, paid: true);
        $foreignPartner = Partner::factory()->create();
        $foreignTeam = Team::factory()->create([
            'partner_id' => $foreignPartner->id,
            'deleted_at' => null,
        ]);
        $foreignStudent = User::factory()->create([
            'partner_id' => $foreignPartner->id,
            'team_id' => $foreignTeam->id,
            'is_enabled' => true,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $foreignStudent->id,
                'team_id' => $this->team->id,
                'year' => self::YEAR,
                'prices' => [[
                    'new_month' => self::MONTH_DATE,
                    'price' => 8000,
                    'lesson_package_id' => (int) $this->to->id,
                ]],
            ])
            ->assertStatus(404);

        $seed['row']->refresh();
        $this->assertSame((int) $this->from->id, (int) $seed['row']->lesson_package_id);
    }
}
