<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к POST …/manual-paid при сохранении абонемента/цены из карточки.
 */
final class SettingPricesMonthlyManualPaidPackageAccessFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $packageA;

    private LessonPackage $packageB;

    private UserPrice $row;

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
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
        ]);
        $this->packageA = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Рубин access',
            'price_cents' => 500000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
            'is_active' => true,
        ]);
        $this->packageB = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Ромашка access',
            'price_cents' => 700000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
            'is_active' => true,
        ]);
        $this->row = UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->packageA->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cardPaidPayload(): array
    {
        return [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => 'Октябрь 2024',
            'mode' => 'paid',
            'comment' => 'Смена абонемента вместе со статусом',
            'lesson_package_id' => $this->packageB->id,
            'price' => 7000.0,
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

    public function test_guest_cannot_mark_paid_with_package_from_card(): void
    {
        Auth::logout();

        $json = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->cardPaidPayload());
        $this->assertContains($json->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $json->getStatusCode());

        $html = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.manual-paid'), $this->cardPaidPayload());
        $this->assertContains($html->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_user_without_set_prices_view_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.indexMenu'))->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->cardPaidPayload())
            ->assertForbidden();

        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.manual-paid'), $this->cardPaidPayload())
            ->assertForbidden();

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_user_with_view_but_without_manual_paid_manage_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.indexMenu'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->cardPaidPayload())
            ->assertForbidden();

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_manager_with_view_manage_and_type_can_apply_package_and_mark_paid(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'setPrices.manualPaid.manage');
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule']);

        $this->get(route('admin.settingPrices.indexMenu'))->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), $this->cardPaidPayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_price.lesson_package_id', $this->packageB->id);

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageB->id,
            'price_cents' => 700000,
            'is_manual_paid' => 1,
        ]);
    }

    public function test_get_patch_delete_on_manual_paid_are_method_not_allowed(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'setPrices.manualPaid.manage');

        $url = route('setting-prices.manual-paid');

        foreach (['GET', 'PATCH', 'DELETE'] as $method) {
            $html = $this->call($method, $url);
            $this->assertContains(
                $html->getStatusCode(),
                [404, 405],
                "{$method} HTML → {$html->getStatusCode()}"
            );
            $this->assertNotSame(500, $html->getStatusCode());
            $this->assertNotSame(200, $html->getStatusCode());

            $json = $this->json($method, $url, $this->cardPaidPayload());
            $this->assertContains(
                $json->getStatusCode(),
                [404, 405],
                "{$method} JSON → {$json->getStatusCode()}"
            );
            $this->assertNotSame(500, $json->getStatusCode());
            $this->assertNotSame(200, $json->getStatusCode());
        }

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
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
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id' => $foreignStudent->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'paid',
                'comment' => 'Чужой ученик',
                'lesson_package_id' => $this->packageB->id,
                'price' => 7000.0,
            ])
            ->assertStatus(404);

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
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
