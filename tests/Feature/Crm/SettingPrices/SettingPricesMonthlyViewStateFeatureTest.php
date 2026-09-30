<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Location;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\User;
use App\Models\UserPrice;
use App\Models\UserTableSetting;
use App\Services\LocationAdminUsersSyncService;
use App\Services\SettingPrices\SettingPricesMonthlyViewStateService;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Шапка «По месяцам» и серверное состояние месяца/фильтров на пользователя.
 *
 * @see /docs/documentation/setting-prices-monthly-users.html#monthly-view-state
 */
final class SettingPricesMonthlyViewStateFeatureTest extends CrmTestCase
{
    private const MONTH = 'Сентябрь 2026';

    private const MONTH_DATE = '2026-09-01';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
            'prices_month' => self::MONTH,
        ]);
        $this->asAdmin();
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule']);
    }

    public function test_monthly_toolbar_has_history_prolong_and_filters(): void
    {
        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>По месяцам<', $html);
        $this->assertStringContainsString('id="logs"', $html);
        $this->assertStringContainsString('>История<', $html);
        $this->assertStringContainsString('id="setting-prices-prolong-btn"', $html);
        $this->assertStringContainsString('Пролонгировать на следующий месяц', $html);
        $this->assertStringContainsString('id="settingPricesMonthlyFiltersToggle"', $html);
        $this->assertStringContainsString('>Фильтры<', $html);
        $this->assertStringContainsString('id="settingPricesMonthlyFiltersCollapse"', $html);
        $this->assertStringContainsString('id="single-select-date"', $html);
        $this->assertStringContainsString('id="filter-monthly-team-title"', $html);
        $this->assertStringContainsString('id="filter-monthly-user-package"', $html);

        $historyPos = strpos($html, 'id="logs"');
        $prolongPos = strpos($html, 'id="setting-prices-prolong-btn"');
        $filtersPos = strpos($html, 'id="settingPricesMonthlyFiltersToggle"');
        $monthPos = strpos($html, 'id="single-select-date"');
        $this->assertNotFalse($historyPos);
        $this->assertNotFalse($prolongPos);
        $this->assertNotFalse($filtersPos);
        $this->assertNotFalse($monthPos);
        $this->assertLessThan($prolongPos, $historyPos);
        $this->assertLessThan($filtersPos, $prolongPos);
        $this->assertLessThan($monthPos, $filtersPos);
    }

    public function test_team_filters_persist_for_one_admin_and_not_another(): void
    {
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Алмаз вид',
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Дубль вид',
        ]);

        $this->postJson(route('setting-prices.monthly-filters'), [
            'team_title' => 'Алмаз',
        ])->assertOk()->assertJson(['success' => true]);

        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertSee('Алмаз вид', false)
            ->assertDontSee('Дубль вид', false)
            ->getContent();

        $this->assertStringContainsString('value="Алмаз"', $html);
        $this->assertMatchesRegularExpression(
            '/class="collapse show[^"]*" id="settingPricesMonthlyFiltersCollapse"/',
            $html
        );

        $other = $this->makeSecondAdmin();
        $this->actingAs($other);

        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertSee('Алмаз вид', false)
            ->assertSee('Дубль вид', false);
    }

    public function test_month_is_stored_per_user_and_filters_survive_month_change(): void
    {
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Алмаз месяц',
        ]);

        $this->postJson(route('setting-prices.monthly-filters'), [
            'team_title' => 'Алмаз',
        ])->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('updateDate'), ['month' => 'Октябрь 2026'])
            ->assertOk();

        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertViewHas('monthString', 'Октябрь 2026')
            ->assertSee('Алмаз месяц', false);

        $other = $this->makeSecondAdmin();
        $this->actingAs($other);
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('updateDate'), ['month' => 'Ноябрь 2026'])
            ->assertOk();

        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertViewHas('monthString', 'Ноябрь 2026');

        $this->actingAs($this->user);
        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertViewHas('monthString', 'Октябрь 2026')
            ->assertSee('value="Алмаз"', false);

        $own = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', SettingPricesMonthlyViewStateService::TABLE_KEY)
            ->first();
        $foreign = UserTableSetting::query()
            ->where('user_id', $other->id)
            ->where('table_key', SettingPricesMonthlyViewStateService::TABLE_KEY)
            ->first();

        $this->assertSame('Октябрь 2026', $own?->columns['month'] ?? null);
        $this->assertSame('Алмаз', $own?->columns['team_title'] ?? null);
        $this->assertSame('Ноябрь 2026', $foreign?->columns['month'] ?? null);
        $this->assertSame('', $foreign?->columns['team_title'] ?? null);
    }

    public function test_reset_clears_filters_and_keeps_month(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('updateDate'), ['month' => 'Октябрь 2026'])
            ->assertOk();

        $this->postJson(route('setting-prices.monthly-filters'), [
            'team_title' => 'Алмаз',
            'user_paid' => 'unpaid',
        ])->assertOk();

        $this->postJson(route('setting-prices.monthly-filters'), [
            'reset' => 1,
            'team_package' => '999999',
        ])->assertOk()->assertJson(['success' => true]);

        $state = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', SettingPricesMonthlyViewStateService::TABLE_KEY)
            ->first();

        $this->assertSame('Октябрь 2026', $state?->columns['month'] ?? null);
        $this->assertSame('', $state?->columns['team_title'] ?? null);
        $this->assertSame('', $state?->columns['user_paid'] ?? null);
    }

    public function test_get_team_price_applies_saved_student_filters(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа учеников',
        ]);
        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(4, 60)->create([
            'name' => 'Тариф вида',
        ]);

        $unpaid = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванова',
            'name' => 'Анна',
            'is_enabled' => true,
        ]);
        $paid = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Петров',
            'name' => 'Пётр',
            'is_enabled' => true,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($unpaid, (int) $team->id);
        $sync->attachTeamForStudent($paid, (int) $team->id);

        UserPrice::forceCreate([
            'user_id' => $unpaid->id,
            'team_id' => $team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 100000,
            'lesson_package_id' => $package->id,
            'is_paid' => 0,
            'is_manual_paid' => null,
        ]);
        UserPrice::forceCreate([
            'user_id' => $paid->id,
            'team_id' => $team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 100000,
            'lesson_package_id' => null,
            'is_paid' => 1,
            'is_manual_paid' => null,
        ]);

        $this->postJson(route('setting-prices.monthly-filters'), [
            'user_name' => 'иван',
            'user_paid' => 'unpaid',
            'user_membership' => 'current',
            'user_package' => (string) $package->id,
        ])->assertOk();

        $response = $this->postJson(route('getTeamPrice'), [
            'teamId' => $team->id,
            'selectedDate' => self::MONTH,
        ])->assertOk()->assertJsonPath('success', true);

        $ids = collect($response->json('usersTeam'))
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->assertSame([(int) $unpaid->id], $ids);
    }

    public function test_unknown_package_returns_422_under_field(): void
    {
        $this->postJson(route('setting-prices.monthly-filters'), [
            'team_package' => '999999',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['team_package']);
    }

    public function test_non_ajax_save_redirects_to_monthly_tab(): void
    {
        $this->post(route('setting-prices.monthly-filters'), [
            'team_title' => 'Алмаз',
        ])->assertRedirect(route('admin.settingPrices.indexMenu'));

        $state = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', SettingPricesMonthlyViewStateService::TABLE_KEY)
            ->first();

        $this->assertSame('Алмаз', $state?->columns['team_title'] ?? null);
    }

    public function test_location_and_admin_filters_keep_matching_teams(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('locations.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'is_enabled' => true,
            'lastname' => 'Главный',
            'name' => 'Пётр',
        ]);
        $hall = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Зал Алмаз',
            'is_enabled' => true,
        ]);
        $yard = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Площадка Дубль',
            'is_enabled' => true,
        ]);
        app(LocationAdminUsersSyncService::class)->syncAdminsForLocation($hall, [(int) $admin->id]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа зала',
            'location_id' => $hall->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа площадки',
            'location_id' => $yard->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Группа без объекта',
            'location_id' => null,
        ]);

        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertSee('id="filter-monthly-location"', false)
            ->assertSee('id="filter-monthly-admin"', false)
            ->assertSee('Зал Алмаз', false)
            ->assertSee('Главный Пётр', false);

        $this->postJson(route('setting-prices.monthly-filters'), [
            'location_id' => (string) $hall->id,
        ])->assertOk();

        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertSee('Группа зала', false)
            ->assertDontSee('Группа площадки', false)
            ->assertDontSee('Группа без объекта', false);

        $this->postJson(route('setting-prices.monthly-filters'), [
            'admin_user_id' => (string) $admin->id,
        ])->assertOk();

        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertSee('Группа зала', false)
            ->assertDontSee('Группа площадки', false)
            ->assertDontSee('Группа без объекта', false);

        $this->postJson(route('setting-prices.monthly-filters'), [
            'admin_user_id' => 'none',
        ])->assertOk();

        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertDontSee('Группа зала', false)
            ->assertSee('Группа площадки', false)
            ->assertSee('Группа без объекта', false);

        $this->postJson(route('setting-prices.monthly-filters'), [
            'location_id' => '999999',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_guest_cannot_save_filters(): void
    {
        Auth::logout();

        $response = $this->postJson(route('setting-prices.monthly-filters'), [
            'team_title' => 'Алмаз',
        ]);

        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertDatabaseMissing('user_table_settings', [
            'table_key' => SettingPricesMonthlyViewStateService::TABLE_KEY,
        ]);
    }

    public function test_service_filters_former_membership(): void
    {
        $service = app(SettingPricesMonthlyViewStateService::class);

        $current = new User();
        $current->id = 11;
        $current->lastname = 'Иванова';
        $current->name = 'Анна';

        $former = new User();
        $former->id = 12;
        $former->lastname = 'Сидорова';
        $former->name = 'Оля';

        $currentPrice = new UserPrice([
            'user_id' => 11,
            'lesson_package_id' => null,
            'is_paid' => 0,
            'is_manual_paid' => null,
        ]);
        $currentPrice->setAttribute('is_former_member', false);

        $formerPrice = new UserPrice([
            'user_id' => 12,
            'lesson_package_id' => 4,
            'is_paid' => 0,
            'is_manual_paid' => null,
        ]);
        $formerPrice->setAttribute('is_former_member', true);

        [$users] = $service->filterMonthlyUsers(
            [$current, $former],
            [$currentPrice, $formerPrice],
            [
                'user_membership' => 'former',
            ]
        );

        $this->assertCount(1, $users);
        $this->assertSame(12, (int) $users[0]->id);
    }

    private function makeSecondAdmin(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'is_enabled' => true,
        ]);
    }
}
