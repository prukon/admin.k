<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к запрету ручной оплаты при 0 ₽.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesManualPaidZeroPriceAccessFeatureTest extends SettingPricesManualPaidZeroPriceTestCase
{
    public function test_guest_cannot_mark_paid_at_zero_rub(): void
    {
        $row = $this->seedZeroUnpaidMonth();
        Auth::logout();

        $json = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload());
        $this->assertContains($json->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());

        $html = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.manual-paid'), $this->paidPayload());
        $this->assertContains($html->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'price_cents' => 0,
            'is_manual_paid' => null,
        ]);
    }

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

    public function test_manager_without_set_prices_view_gets_403(): void
    {
        $row = $this->seedZeroUnpaidMonth();
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.indexMenu'))->assertForbidden();
        $this->get(route('admin.settingPrices.users'))->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload())
            ->assertForbidden();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.manual-paid'), $this->paidPayload())
            ->assertForbidden();

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_manager_with_view_but_without_manual_paid_manage_gets_403(): void
    {
        $row = $this->seedZeroUnpaidMonth();
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.indexMenu'))->assertOk();
        $this->get(route('admin.settingPrices.users'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload())
            ->assertForbidden();

        $this->from(route('admin.settingPrices.users'))
            ->post(route('setting-prices.manual-paid'), $this->paidPayload())
            ->assertForbidden();

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_manager_with_rights_gets_422_on_paid_at_zero_not_200(): void
    {
        $row = $this->seedZeroUnpaidMonth();

        $this->get(route('admin.settingPrices.indexMenu'))->assertOk();
        $this->get(route('admin.settingPrices.users'))->assertOk();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->paidPayload());
        $response->assertStatus(422);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_get_patch_delete_on_manual_paid_are_method_not_allowed(): void
    {
        $row = $this->seedZeroUnpaidMonth();
        $url = route('setting-prices.manual-paid');

        foreach (['GET', 'PATCH', 'DELETE'] as $method) {
            $html = $this->call($method, $url, $this->paidPayload());
            $this->assertContains(
                $html->getStatusCode(),
                [404, 405],
                "{$method} HTML → {$html->getStatusCode()}"
            );
            $this->assertNotSame(500, $html->getStatusCode());
            $this->assertNotSame(200, $html->getStatusCode());

            $json = $this->json($method, $url, $this->paidPayload());
            $this->assertContains(
                $json->getStatusCode(),
                [404, 405],
                "{$method} JSON → {$json->getStatusCode()}"
            );
            $this->assertNotSame(500, $json->getStatusCode());
            $this->assertNotSame(200, $json->getStatusCode());
        }

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_foreign_partner_student_returns_404_and_does_not_write(): void
    {
        $this->asSuperadmin();
        $row = $this->seedZeroUnpaidMonth();

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
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $foreignStudent->id,
                'team_id' => $this->team->id,
                'selectedDate' => self::MONTH_LABEL,
                'mode' => 'paid',
                'comment' => 'Чужой ученик при нуле',
            ])
            ->assertStatus(404);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'is_manual_paid' => null,
        ]);
    }
}
