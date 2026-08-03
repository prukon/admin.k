<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Каталог группы setPrices: подписи прав, перенос payment.clubfee, UI матрицы.
 *
 * Новых store/update endpoint'ов нет — AJAX/non-AJAX safety-net и BladeInlineJs не применимы.
 * Доступ к club-fee: PaymentClubFeePageFullAccessFeatureTest.
 * Сезоны на /cabinet: DashboardCabinetSeasonsAccessFeatureTest.
 *
 * @see PermissionGroupsReorganizationFeatureTest
 * @see PartnerBasePermissionsTest
 */
final class SetPricesPermissionCatalogFeatureTest extends CrmTestCase
{
    /**
     * @return array<string, string>
     */
    private function expectedSetPricesDescriptions(): array
    {
        return [
            'setPrices.cabinetSeasons.view' => 'Оплата сезонов',
            'setPrices.customPayments.view' => 'Дополнительные платежи',
            'setPrices.manualPaid.manage' => 'Установка цен: ручная отметка оплаты месяца',
            'setPrices.packageAssignments.view' => 'Назначение абонементов',
            'payment.clubfee' => 'Оплата клубного взноса',
        ];
    }

    public function test_set_prices_permissions_have_expected_descriptions_and_group(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'setPrices')->value('id');
        $this->assertGreaterThan(0, $groupId);

        foreach ($this->expectedSetPricesDescriptions() as $name => $description) {
            $row = DB::table('permissions')->where('name', $name)->first();
            $this->assertNotNull($row, "Право {$name} должно существовать");
            $this->assertSame($description, (string) $row->description, "description для {$name}");
            $this->assertSame($groupId, (int) $row->permission_group_id, "группа setPrices для {$name}");
            $this->assertSame(0, (int) $row->is_visible, "is_visible=0 для {$name}");
        }
    }

    public function test_payment_clubfee_sort_order_follows_package_assignments(): void
    {
        $rows = DB::table('permissions')
            ->join('permission_groups', 'permissions.permission_group_id', '=', 'permission_groups.id')
            ->where('permission_groups.slug', 'setPrices')
            ->orderBy('permissions.sort_order')
            ->orderBy('permissions.id')
            ->pluck('permissions.name')
            ->all();

        $this->assertSame([
            'setPrices.cabinetSeasons.view',
            'setPrices.customPayments.view',
            'setPrices.manualPaid.manage',
            'setPrices.packageAssignments.view',
            'payment.clubfee',
        ], $rows);

        $clubfeeSort = (int) DB::table('permissions')->where('name', 'payment.clubfee')->value('sort_order');
        $this->assertSame(23, $clubfeeSort);
    }

    public function test_payment_clubfee_is_not_in_misc_group(): void
    {
        $miscId = DB::table('permission_groups')->where('slug', 'misc')->value('id');
        $this->assertNotNull($miscId);

        $clubfeeGroupId = DB::table('permissions')->where('name', 'payment.clubfee')->value('permission_group_id');
        $this->assertNotSame((int) $miscId, (int) $clubfeeGroupId);
    }

    public function test_superadmin_rules_page_shows_set_prices_permission_labels(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Установка цен', $html);
        foreach ($this->expectedSetPricesDescriptions() as $name => $description) {
            $this->assertStringContainsString($name, $html, "name {$name} на матрице");
            $this->assertStringContainsString($description, $html, "description «{$description}» на матрице");
        }
        $this->assertNotSame(500, $this->get(route('admin.setting.rule'))->getStatusCode());
    }

    public function test_guest_is_denied_on_rules_page(): void
    {
        Auth::logout();

        $response = $this->get(route('admin.setting.rule'));

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_user_without_settings_roles_view_gets_403_on_rules_page(): void
    {
        $actor = $this->createUserWithoutPermission('settings.roles.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.setting.rule'))->assertForbidden();
    }

    public function test_new_partner_base_roles_do_not_receive_payment_clubfee(): void
    {
        $partner = Partner::factory()->create();
        $permId = $this->permissionId('payment.clubfee');

        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $roleId = $this->roleId($roleName);
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permId)
                    ->exists(),
                "Роль {$roleName} нового партнёра не должна иметь payment.clubfee"
            );
        }
    }
}
