<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к сохранению цены только с абонементом.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesRequirePackageForPriceAccessFeatureTest extends SettingPricesRequirePackageForPriceTestCase
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

    /**
     * @return list<array{url:string,data:array<string,mixed>}>
     */
    private function mutationRequests(): array
    {
        return [
            [
                'url' => route('setPriceAllUsers'),
                'data' => $this->monthlyPayload(1500, null),
            ],
            [
                'url' => route('setting-prices.user-year-prices.save'),
                'data' => $this->yearPayload(1500, null),
            ],
        ];
    }

    public function test_guest_cannot_save_price_without_package(): void
    {
        $row = $this->seedUnpaidMonth(0);
        Auth::logout();

        foreach ($this->mutationRequests() as $item) {
            $json = $this->withHeaders($this->ajaxHeaders())->postJson($item['url'], $item['data']);
            $this->assertContains($json->getStatusCode(), [302, 401, 403], $item['url'].' JSON '.$json->getStatusCode());
            $this->assertNotSame(500, $json->getStatusCode());
            $this->assertNotSame(200, $json->getStatusCode());

            $html = $this->from(route('admin.settingPrices.indexMenu'))
                ->post($item['url'], $item['data']);
            $this->assertContains($html->getStatusCode(), [302, 401, 403, 419], $item['url'].' HTML '.$html->getStatusCode());
            $this->assertNotSame(500, $html->getStatusCode());
            $this->assertNotSame(200, $html->getStatusCode());
        }

        $row->refresh();
        $this->assertNull($row->lesson_package_id);
        $this->assertSame(0, (int) $row->price_cents);
    }

    public function test_manager_without_permission_gets_403(): void
    {
        $row = $this->seedUnpaidMonth(0);
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.indexMenu'))->assertForbidden();
        $this->get(route('admin.settingPrices.users'))->assertForbidden();

        foreach ($this->mutationRequests() as $item) {
            $this->withHeaders($this->ajaxHeaders())
                ->postJson($item['url'], $item['data'])
                ->assertForbidden();

            $this->from(route('admin.settingPrices.indexMenu'))
                ->post($item['url'], $item['data'])
                ->assertForbidden();
        }

        $row->refresh();
        $this->assertSame(0, (int) $row->price_cents);
        $this->assertNull($row->lesson_package_id);
    }

    public function test_manager_with_view_can_open_pages_and_save_price_with_package(): void
    {
        $this->get(route('admin.settingPrices.indexMenu'))->assertOk();
        $this->get(route('admin.settingPrices.users'))->assertOk();

        $row = $this->seedUnpaidMonth(0);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), $this->monthlyPayload(4500, (int) $this->package->id))
            ->assertOk()
            ->assertJsonPath('success', true);

        $row->refresh();
        $this->assertSame((int) $this->package->id, (int) $row->lesson_package_id);
        $this->assertSame(450000, (int) $row->price_cents);
    }

    public function test_get_patch_delete_on_save_routes_are_not_allowed(): void
    {
        $row = $this->seedUnpaidMonth(0);
        $urls = [
            route('setPriceAllUsers'),
            route('setting-prices.user-year-prices.save'),
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

        $row->refresh();
        $this->assertSame(0, (int) $row->price_cents);
        $this->assertNull($row->lesson_package_id);
    }

    public function test_foreign_team_returns_404_and_does_not_save(): void
    {
        $row = $this->seedUnpaidMonth(0);
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
                    $this->rightPayload($this->student, 4500, (int) $this->package->id),
                ],
            ])
            ->assertStatus(404);

        $row->refresh();
        $this->assertNull($row->lesson_package_id);
        $this->assertSame(0, (int) $row->price_cents);
    }

    public function test_foreign_student_year_save_returns_404(): void
    {
        $row = $this->seedUnpaidMonth(0);
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
                    'price' => 4500,
                    'lesson_package_id' => (int) $this->package->id,
                ]],
            ])
            ->assertStatus(404);

        $row->refresh();
        $this->assertNull($row->lesson_package_id);
        $this->assertSame(0, (int) $row->price_cents);
    }
}
