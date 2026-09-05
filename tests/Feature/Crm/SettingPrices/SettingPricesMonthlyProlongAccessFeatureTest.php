<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к превью/записи пролонгации месяца.
 */
final class SettingPricesMonthlyProlongAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    /**
     * @return list<string>
     */
    private function prolongPostRoutes(): array
    {
        return [
            'setting-prices.prolong-month.preview',
            'setting-prices.prolong-month.apply',
        ];
    }

    public function test_guest_cannot_preview_or_apply(): void
    {
        Auth::logout();

        foreach ($this->prolongPostRoutes() as $route) {
            $response = $this->postJson(route($route), [
                'selectedDate' => 'Сентябрь 2026',
            ]);
            $this->assertContains($response->getStatusCode(), [302, 401, 403]);
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_guest_cannot_open_monthly_tab(): void
    {
        Auth::logout();

        $response = $this->get(route('admin.settingPrices.indexMenu'));
        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('id="setting-prices-prolong-btn"', $response->getContent());
    }

    public function test_guest_non_ajax_apply_does_not_write(): void
    {
        $this->asAdmin();
        $this->seedSeptemberPackage();
        Auth::logout();

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.prolong-month.apply'), [
                'selectedDate' => 'Сентябрь 2026',
            ]);

        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertDatabaseMissing('users_prices', [
            'new_month' => '2026-10-01',
        ]);
    }

    public function test_without_set_prices_view_returns_403(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.indexMenu'))->assertForbidden();

        $this->postJson(route('setting-prices.prolong-month.preview'), [
            'selectedDate' => 'Сентябрь 2026',
        ])->assertForbidden();

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])->assertForbidden();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.prolong-month.apply'), [
                'selectedDate' => 'Сентябрь 2026',
            ])
            ->assertForbidden();
    }

    public function test_get_patch_delete_on_prolong_endpoints_are_method_not_allowed(): void
    {
        $this->asAdmin();

        foreach ($this->prolongPostRoutes() as $routeName) {
            $url = route($routeName);

            foreach (['GET', 'PATCH', 'DELETE'] as $method) {
                $html = $this->call($method, $url);
                $this->assertContains(
                    $html->getStatusCode(),
                    [404, 405],
                    "{$method} {$routeName} HTML → {$html->getStatusCode()}"
                );
                $this->assertNotSame(500, $html->getStatusCode());

                $json = $this->json($method, $url, ['selectedDate' => 'Сентябрь 2026']);
                $this->assertContains(
                    $json->getStatusCode(),
                    [404, 405],
                    "{$method} {$routeName} JSON → {$json->getStatusCode()}"
                );
                $this->assertNotSame(500, $json->getStatusCode());
            }
        }
    }

    public function test_with_set_prices_view_preview_and_apply_return_200(): void
    {
        $this->asAdmin();

        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertSee('id="setting-prices-prolong-btn"', false);

        $this->postJson(route('setting-prices.prolong-month.preview'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'source_month',
                'target_month',
                'can_apply',
                'counts',
                'skip_reasons',
                'items',
                'message',
            ]);

        $this->postJson(route('setting-prices.prolong-month.apply'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function seedSeptemberPackage(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(4, 60)->create([
            'price_cents' => 600000,
        ]);
        TeamPrice::query()->create([
            'team_id' => $team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 600000,
            'lesson_package_id' => $package->id,
        ]);
        UserPrice::forceCreate([
            'user_id' => $student->id,
            'team_id' => $team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 600000,
            'lesson_package_id' => $package->id,
            'is_paid' => 0,
        ]);
    }
}
