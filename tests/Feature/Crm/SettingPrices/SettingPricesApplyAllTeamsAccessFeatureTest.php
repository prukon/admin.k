<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * POST /admin/setting-prices/set-price-all-teams требует setPrices.applyAllTeams.manage.
 *
 * @see SettingPricesApplyAllTeamsMarkupFeatureTest
 */
final class SettingPricesApplyAllTeamsAccessFeatureTest extends CrmTestCase
{
    private Team $team;

    private LessonPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'price_cents' => 150000,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'selectedDate' => 'Сентябрь 2024',
            'teamsData' => [
                [
                    'teamId' => $this->team->id,
                    'lesson_package_id' => $this->package->id,
                ],
            ],
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

    public function test_guest_cannot_apply_all_teams(): void
    {
        Auth::logout();

        $json = $this->withHeaders($this->ajaxHeaders())->postJson(route('setPriceAllTeams'), $this->payload());
        $this->assertContains($json->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());

        $html = $this->post(route('setPriceAllTeams'), $this->payload());
        $this->assertContains($html->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
    }

    public function test_user_without_set_prices_view_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllTeams'), $this->payload())
            ->assertForbidden();

        $this->post(route('setPriceAllTeams'), $this->payload())
            ->assertForbidden();
    }

    public function test_view_without_apply_all_teams_hides_button_and_rejects_post(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.applyAllTeams.manage', $this->partner);
        $this->grantPartnerRolePermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="set-price-all-teams"', $html);
        $this->assertStringContainsString('setting-prices-team-ok', $html);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllTeams'), $this->payload())
            ->assertForbidden();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllTeams'), $this->payload())
            ->assertForbidden();
    }

    public function test_user_with_apply_all_teams_can_post_and_sees_button(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.applyAllTeams.manage', $this->partner);
        $this->grantPartnerRolePermission($actor, 'setPrices.view');
        $this->grantPartnerRolePermission($actor, 'setPrices.applyAllTeams.manage');
        $this->grantLessonPackageTypePermissions($actor);
        $this->actingAs($actor);

        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="set-price-all-teams"', $html);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllTeams'), $this->payload())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_non_ajax_with_permission_redirects(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.applyAllTeams.manage', $this->partner);
        $this->grantPartnerRolePermission($actor, 'setPrices.view');
        $this->grantPartnerRolePermission($actor, 'setPrices.applyAllTeams.manage');
        $this->grantLessonPackageTypePermissions($actor);
        $this->actingAs($actor);

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllTeams'), $this->payload());
        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_get_patch_delete_are_not_allowed(): void
    {
        $this->asAdmin();
        $this->grantPartnerRolePermission($this->user, 'setPrices.applyAllTeams.manage');

        foreach (['GET', 'PATCH', 'DELETE'] as $method) {
            $html = $this->call($method, route('setPriceAllTeams'));
            $this->assertContains($html->getStatusCode(), [404, 405], "{$method} → {$html->getStatusCode()}");
            $this->assertNotSame(500, $html->getStatusCode());
            $this->assertNotSame(200, $html->getStatusCode());
        }
    }
}
