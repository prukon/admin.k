<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ и AJAX/non-AJAX контракт POST …/former-month-charge/clear.
 */
final class SettingPricesMonthlyFormerChargeClearAccessFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $formerStudent;

    private UserPrice $row;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $teamSync = app(TeamUserSyncService::class);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Алмаз Access корзина',
        ]);

        $this->formerStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'lastname' => 'Бывший',
            'name' => 'Access',
        ]);
        $teamSync->syncTeamsForStudent($this->formerStudent, [(int) $this->team->id]);

        $this->row = UserPrice::forceCreate([
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-02-01',
            'price_cents' => 326700,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);
        $teamSync->syncTeamsForStudent($this->formerStudent, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'user_id' => $this->formerStudent->id,
            'team_id' => $this->team->id,
            'selectedDate' => 'Февраль 2026',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
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

    private function assertRowUnchanged(): void
    {
        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'price_cents' => 326700,
            'lesson_package_id' => null,
        ]);
    }

    public function test_guest_cannot_clear_former_charge(): void
    {
        Auth::logout();

        $json = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->payload());
        $this->assertContains($json->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $json->getStatusCode());

        $html = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.former-month-charge.clear'), $this->payload());
        $this->assertContains($html->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());

        $this->assertRowUnchanged();
    }

    public function test_user_without_set_prices_view_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.indexMenu'))->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->payload())
            ->assertForbidden();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.former-month-charge.clear'), $this->payload())
            ->assertForbidden();

        $this->assertRowUnchanged();
    }

    public function test_view_without_manual_paid_manage_can_clear(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.indexMenu'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->payload())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'price_cents' => 0,
        ]);
    }

    public function test_missing_fields_return_422_with_request_messages(): void
    {
        $this->asAdmin();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), [])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Не указан ученик.')
            ->assertJsonPath('errors.team_id.0', 'Выберите группу.')
            ->assertJsonPath('errors.selectedDate.0', 'Укажите месяц.');

        $this->assertRowUnchanged();
    }

    public function test_get_patch_delete_are_method_not_allowed(): void
    {
        $this->asAdmin();
        $url = route('setting-prices.former-month-charge.clear');

        foreach (['GET', 'PATCH', 'DELETE'] as $method) {
            $html = $this->call($method, $url);
            $this->assertContains(
                $html->getStatusCode(),
                [404, 405],
                "{$method} HTML → {$html->getStatusCode()}"
            );
            $this->assertNotSame(500, $html->getStatusCode());
            $this->assertNotSame(200, $html->getStatusCode());

            $json = $this->json($method, $url, $this->payload());
            $this->assertContains(
                $json->getStatusCode(),
                [404, 405],
                "{$method} JSON → {$json->getStatusCode()}"
            );
            $this->assertNotSame(500, $json->getStatusCode());
            $this->assertNotSame(200, $json->getStatusCode());
        }

        $this->assertRowUnchanged();
    }

    public function test_foreign_partner_student_returns_404_and_does_not_write(): void
    {
        $this->asSuperadmin();

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
            ->postJson(route('setting-prices.former-month-charge.clear'), [
                'user_id' => $foreignStudent->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertStatus(404);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), [
                'user_id' => $this->formerStudent->id,
                'team_id' => $foreignTeam->id,
                'selectedDate' => 'Февраль 2026',
            ])
            ->assertStatus(404);

        $this->assertRowUnchanged();
    }

    public function test_ajax_success_returns_json_200(): void
    {
        $this->asAdmin();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->payload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Начисление снято.');

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'price_cents' => 0,
        ]);
    }

    public function test_non_ajax_success_redirects_to_monthly_and_clears_row(): void
    {
        $this->asAdmin();

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.former-month-charge.clear'), $this->payload());

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'price_cents' => 0,
        ]);
    }

    public function test_non_ajax_validation_redirects_with_errors_and_does_not_write(): void
    {
        $this->asAdmin();

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.former-month-charge.clear'), [
                'user_id' => $this->formerStudent->id,
                'team_id' => $this->team->id,
            ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $response->assertSessionHasErrors(['selectedDate' => 'Укажите месяц.']);
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertRowUnchanged();
    }

    public function test_put_on_clear_is_not_empty_200(): void
    {
        $this->asAdmin();
        $url = route('setting-prices.former-month-charge.clear');

        $html = $this->call('PUT', $url, $this->payload());
        $this->assertContains($html->getStatusCode(), [404, 405]);
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());

        $json = $this->json('PUT', $url, $this->payload());
        $this->assertContains($json->getStatusCode(), [404, 405]);
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());

        $this->assertRowUnchanged();
    }

    public function test_foreign_partner_actor_does_not_see_or_clear_row(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->foreignUser->role_id,
            'permission_id' => $this->permissionId('setPrices.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->foreignUser);
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.former-month-charge.clear'), $this->payload())
            ->assertStatus(404);

        $this->assertRowUnchanged();
    }

    public function test_user_without_partner_is_logged_out_from_clear(): void
    {
        $this->asAdmin();
        $this->user->partner_id = null;
        $this->user->save();

        $this->actingAs($this->user);
        $this->withSession([
            'current_partner' => null,
            '2fa:passed' => true,
        ]);

        $response = $this->post(route('setting-prices.former-month-charge.clear'), $this->payload());
        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => 'Ваша организация недоступна.']);

        $this->assertRowUnchanged();
    }

    public function test_manager_with_view_opens_monthly_page_not_empty(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $page = $this->get(route('admin.settingPrices.indexMenu'));
        $page->assertOk();
        $this->assertNotSame('', trim($page->getContent()));
        $this->assertNotSame(500, $page->getStatusCode());
    }
}
