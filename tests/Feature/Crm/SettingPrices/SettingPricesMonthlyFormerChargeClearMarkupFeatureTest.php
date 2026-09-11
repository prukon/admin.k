<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка: корзина только в monthly Vite-модуле, не в первом HTML и не на «По ученикам».
 */
final class SettingPricesMonthlyFormerChargeClearMarkupFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $formerStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();

        $teamSync = app(TeamUserSyncService::class);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Алмаз Markup корзина',
        ]);
        $this->formerStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'lastname' => 'Бывший',
            'name' => 'Markup',
        ]);
        $teamSync->syncTeamsForStudent($this->formerStudent, [(int) $this->team->id]);
        UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
            'is_paid' => 0,
        ]);
        $teamSync->syncTeamsForStudent($this->formerStudent, []);
    }

    public function test_monthly_first_open_does_not_render_trash_in_html(): void
    {
        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="confirmDeleteModal"', $html);
        $this->assertStringContainsString('function showConfirmDeleteModal', $html);
        $this->assertMatchesRegularExpression('/class="row mb-2 wrap-users text-start\s*"><\/div>/', $html);

        $this->assertStringNotContainsString('user-price-former-clear', $html);
        $this->assertStringNotContainsString('setting-prices-monthly-clear-btn', $html);
        $this->assertStringNotContainsString('former-clear-error', $html);
        $this->assertStringNotContainsString('can_clear_former_charge', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/monthly.blade.php'));
        $this->assertStringContainsString("@vite(['resources/js/settings-prices.js'])", $blade);
        $this->assertStringNotContainsString('user-price-former-clear', $blade);
        $this->assertStringNotContainsString('former-month-charge/clear', $blade);
    }

    public function test_users_tab_does_not_render_monthly_trash_even_for_former(): void
    {
        $html = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString((string) $this->formerStudent->id, $html);
        $this->assertStringNotContainsString('user-price-former-clear', $html);
        $this->assertStringNotContainsString('former-month-charge/clear', $html);
        $this->assertStringNotContainsString('can_clear_former_charge', $html);
        $this->assertStringNotContainsString('resources/js/settings-prices.js', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/users.blade.php'));
        $this->assertStringNotContainsString('resources/js/settings-prices.js', $blade);
        $this->assertStringNotContainsString('user-price-former-clear', $blade);
        $this->assertStringNotContainsString('former-month-charge/clear', $blade);
        $this->assertStringNotContainsString('fa-trash', $blade);
    }

    public function test_monthly_page_with_view_without_manual_paid_still_loads_vite_module(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="confirmDeleteModal"', $html);
        $this->assertStringNotContainsString('user-price-former-clear', $html);

        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/monthly.blade.php'));
        $this->assertStringContainsString("@vite(['resources/js/settings-prices.js'])", $blade);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
