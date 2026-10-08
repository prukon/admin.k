<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;

/**
 * Кто видит счёт на email и флаги меню шестерёнки.
 */
final class SettingPricesInvoiceEmailAccessFeatureTest extends SettingPricesInvoiceEmailTestCase
{
    public function test_guest_cannot_open_preview_or_send(): void
    {
        $payload = $this->payload($this->charge());
        Auth::logout();

        foreach (['setting-prices.invoice-email.preview', 'setting-prices.invoice-email.send'] as $routeName) {
            $html = $this->post(route($routeName), $payload);
            $this->assertContains($html->getStatusCode(), [302, 401, 403]);
            $this->assertNotSame(200, $html->getStatusCode());
            $this->assertNotSame(500, $html->getStatusCode());

            $json = $this->postJson(route($routeName), $payload);
            $this->assertContains($json->getStatusCode(), [302, 401, 403]);
            $this->assertNotSame(200, $json->getStatusCode());
        }
    }

    public function test_manager_with_prices_view_but_without_invoice_permission_gets_403(): void
    {
        $actor = $this->createUserWithRole('user', $this->partner);
        $this->grant($actor, 'setPrices.view');
        $this->actingAs($actor);

        $payload = $this->payload($this->charge());
        $this->postJson(route('setting-prices.invoice-email.preview'), $payload)->assertForbidden();
        $this->postJson(route('setting-prices.invoice-email.send'), $payload)->assertForbidden();
        $this->post(route('setting-prices.invoice-email.send'), $payload)->assertForbidden();
    }

    public function test_invoice_permission_without_prices_view_still_gets_403(): void
    {
        $actor = $this->createUserWithRole('user', $this->partner);
        $this->grant($actor, 'setPrices.invoiceEmail.send');
        $this->actingAs($actor);

        $this->postJson(route('setting-prices.invoice-email.preview'), $this->payload($this->charge()))
            ->assertForbidden();
    }

    public function test_admin_can_open_both_tabs_and_the_invoice_flag_is_on(): void
    {
        $this->asAdmin();
        $this->charge();

        $this->get(route('admin.settingPrices.indexMenu'))->assertOk();
        $this->get(route('admin.settingPrices.users'))->assertOk();

        $this->postJson(route('getTeamPrice'), [
            'teamId' => $this->team->id,
            'selectedDate' => self::MONTH_LABEL,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('can_send_invoice_email', true);

        $this->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => 2026,
        ])->assertOk()
            ->assertJsonPath('can_send_invoice_email', true);
    }

    public function test_prices_viewer_does_not_get_the_invoice_menu_flag(): void
    {
        $actor = $this->createUserWithRole('user', $this->partner);
        $this->grant($actor, 'setPrices.view');
        $this->actingAs($actor);
        $this->charge();

        $this->postJson(route('getTeamPrice'), [
            'teamId' => $this->team->id,
            'selectedDate' => self::MONTH_LABEL,
        ])->assertOk()
            ->assertJsonPath('can_send_invoice_email', false);

        $this->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => 2026,
        ])->assertOk()
            ->assertJsonPath('can_send_invoice_email', false);
    }

    public function test_former_member_keeps_the_invoice_flag_when_the_admin_can_send(): void
    {
        $this->asAdmin();
        $this->charge(['is_paid' => 1]);
        $other = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Другая группа',
            'deleted_at' => null,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $other->id]);

        $this->postJson(route('setting-prices.user-year-prices'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'year' => 2026,
        ])->assertOk()
            ->assertJsonPath('is_former_member', true)
            ->assertJsonPath('can_manage_manual_paid', false)
            ->assertJsonPath('can_send_invoice_email', true);
    }

    public function test_get_patch_and_delete_do_not_return_success_or_server_error(): void
    {
        $this->asAdmin();

        foreach (['setting-prices.invoice-email.preview', 'setting-prices.invoice-email.send'] as $routeName) {
            foreach (['get', 'patch', 'delete'] as $method) {
                $response = $this->{$method}(route($routeName));
                $this->assertContains($response->getStatusCode(), [404, 405]);
                $this->assertNotSame(200, $response->getStatusCode());
                $this->assertNotSame(500, $response->getStatusCode());
            }

            $json = $this->patchJson(route($routeName), []);
            $this->assertContains($json->getStatusCode(), [404, 405]);
            $this->assertNotSame(200, $json->getStatusCode());
        }
    }
}
