<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Перегруппировка permission_groups: каталог в БД и отображение на «Права и роли».
 * Маппинг синхронизирован с PermissionSeeder и миграцией reorganize_permission_groups.
 */
final class PermissionGroupsReorganizationFeatureTest extends CrmTestCase
{
    /**
     * @var list<string>
     */
    private const EXPECTED_GROUP_SLUGS = [
        'mainMenu',
        'reports',
        'schedule',
        'directories',
        'lessonPackages',
        'setPrices',
        'contracts',
        'leads',
        'partner',
        'account',
        'users',
        'settings',
        'paymentMethods',
        'misc',
    ];

    /**
     * @return array<string, int>
     */
    private function expectedPermissionCountsByGroupSlug(): array
    {
        return [
            'mainMenu'        => 6,
            'reports'         => 10,
            'schedule'        => 6,
            'directories'     => 6,
            'lessonPackages'  => 2,
            'setPrices'       => 3,
            'contracts'       => 4,
            'leads'           => 4,
            'partner'         => 4,
            'account'         => 11,
            'users'           => 14,
            'settings'        => 8,
            'paymentMethods'  => 6,
            'misc'            => 2,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function expectedPermissionGroupByName(): array
    {
        $map = [];

        foreach ($this->permissionNamesByGroupSlug() as $groupSlug => $names) {
            foreach ($names as $name) {
                $map[$name] = $groupSlug;
            }
        }

        return $map;
    }

    /**
     * @return array<string, list<string>>
     */
    private function permissionNamesByGroupSlug(): array
    {
        return [
            'mainMenu' => [
                'dashboard.view',
                'myPayments.view',
                'myGroup.view',
                'messages.view',
                'blog.view',
                'documentations.view',
            ],
            'reports' => [
                'reports.view',
                'reports.payment.intents.view',
                'reports.fiscal.receipts.view',
                'reports.emails.view',
                'reports.additional.value.view',
                'reports.payments.totals.net_to_partner.view',
                'reports.payments.totals.payout_amount.view',
                'reports.payments.totals.platform_commission.view',
                'reports.payments.commission_total.view',
                'reports.payments.payout_amount.column.view',
            ],
            'schedule' => [
                'schedule.view',
                'schedule.trainerSalary.view',
                'schedule.trainerSalary.manage',
                'scheduleSlots.view',
                'scheduleSlots.manage',
                'scheduleSlots.table',
            ],
            'directories' => [
                'districts.view',
                'locations.view',
                'locations.manage',
                'sport_types.view',
                'sport_types.manage',
                'groups.view',
            ],
            'lessonPackages' => [
                'lessonPackages.view',
                'lessonPackages.manualPaid.manage',
            ],
            'setPrices' => [
                'setPrices.view',
                'setPrices.customPayments.view',
                'setPrices.manualPaid.manage',
            ],
            'contracts' => [
                'contracts.view',
                'contracts.sync',
                'contracts.templates.fillSortOrder.edit',
                'account.contracts.showFieldKeys',
            ],
            'leads' => [
                'partnerLeads.view',
                'schoolLeads.view',
                'schoolWidget.view',
                'schoolLeadLanding.view',
            ],
            'partner' => [
                'partner.view',
                'partner.switch',
                'servicePayments.view',
                'partnerWallet.view',
            ],
            'account' => [
                'account.user.view',
                'account.partner.view',
                'account.documents.view',
                'account.user.name.update',
                'account.user.birthdate.update',
                'account.user.team.update',
                'account.user.startDate.update',
                'account.user.email.update',
                'account.user.phone.update',
                'account.user.parent.update',
                'account.partner.update',
            ],
            'users' => [
                'users.view',
                'trainers.view',
                'users.name.update',
                'users.birthdate.update',
                'users.group.update',
                'users.startDate.update',
                'users.email.update',
                'users.activity.update',
                'users.role.update',
                'users.password.update',
                'users.phone.update',
                'users.other.update',
                'users.sex',
                'users.comment',
            ],
            'settings' => [
                'settings.view',
                'settings.roles.view',
                'settings.paymentSystems.view',
                'settings.queues.view',
                'viewing.all.logs',
                'settings.force2fa.admins',
                'settings.queues.manage',
                'settings.registration.manage',
            ],
            'paymentMethods' => [
                'payment.method.robokassa',
                'payment.method.tbankCard',
                'payment.method.tbankSBP',
                'manage.payment.method.tbank',
                'tbank.payouts.manage',
                'settings.commission',
            ],
            'misc' => [
                'paying.classes',
                'payment.clubfee',
            ],
        ];
    }

    public function test_all_expected_permission_groups_exist_after_seed(): void
    {
        $slugs = DB::table('permission_groups')->pluck('slug')->all();

        foreach (self::EXPECTED_GROUP_SLUGS as $slug) {
            $this->assertContains($slug, $slugs, "Группа {$slug} должна существовать в permission_groups");
        }
    }

    public function test_permission_counts_per_group_match_catalog(): void
    {
        $counts = DB::table('permissions')
            ->join('permission_groups', 'permissions.permission_group_id', '=', 'permission_groups.id')
            ->selectRaw('permission_groups.slug, count(*) as cnt')
            ->groupBy('permission_groups.slug')
            ->pluck('cnt', 'slug')
            ->all();

        foreach ($this->expectedPermissionCountsByGroupSlug() as $slug => $expectedCount) {
            $this->assertSame(
                $expectedCount,
                (int) ($counts[$slug] ?? 0),
                "Число прав в группе {$slug}"
            );
        }
    }

    public function test_catalog_permissions_are_assigned_to_expected_groups_in_database(): void
    {
        $groupIdBySlug = DB::table('permission_groups')->pluck('id', 'slug')->all();

        foreach ($this->expectedPermissionGroupByName() as $permissionName => $expectedGroupSlug) {
            $this->assertArrayHasKey($expectedGroupSlug, $groupIdBySlug, "Группа {$expectedGroupSlug} не найдена");

            $permissionGroupId = DB::table('permissions')
                ->where('name', $permissionName)
                ->value('permission_group_id');

            $this->assertNotNull(
                $permissionGroupId,
                "Право {$permissionName} должно существовать после PermissionSeeder"
            );

            $this->assertSame(
                (int) $groupIdBySlug[$expectedGroupSlug],
                (int) $permissionGroupId,
                "Право {$permissionName} должно быть в группе {$expectedGroupSlug}"
            );
        }
    }

    public function test_main_menu_group_contains_only_root_navigation_permissions(): void
    {
        $names = DB::table('permissions')
            ->join('permission_groups', 'permissions.permission_group_id', '=', 'permission_groups.id')
            ->where('permission_groups.slug', 'mainMenu')
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();

        $this->assertEqualsCanonicalizing(
            $this->permissionNamesByGroupSlug()['mainMenu'],
            $names
        );
    }

    public function test_account_and_users_name_permissions_live_in_different_groups(): void
    {
        $this->assertPermissionInGroup('account.user.name.update', 'account');
        $this->assertPermissionInGroup('users.name.update', 'users');
    }

    public function test_rules_page_renders_reorganized_group_sections_for_superadmin(): void
    {
        $this->asSuperadmin();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->assertViewHas('groups')
            ->getContent();

        foreach ([
            'Главное меню',
            'Отчёты',
            'Расписание',
            'Справочники',
            'Абонементы',
            'Установка цен',
            'Договоры',
            'Заявки и лиды',
            'Партнёры и сервис',
            'Учетная запись',
            'Управление пользователями',
            'Настройки',
            'Способы оплаты',
            'Разное',
        ] as $groupTitle) {
            $this->assertStringContainsString($groupTitle, $html, "Заголовок группы «{$groupTitle}»");
        }

        $this->assertStringContainsString('account.user.name.update', $html);
        $this->assertStringContainsString('users.name.update', $html);
        $this->assertStringContainsString('reports.view', $html);
        $this->assertStringContainsString('scheduleSlots.view', $html);
    }

    public function test_partner_admin_sees_only_groups_with_visible_permissions(): void
    {
        $this->asAdmin();

        $groups = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->viewData('groups');

        $slugs = $groups->pluck('slug')->all();

        $this->assertContains('mainMenu', $slugs);
        $this->assertContains('reports', $slugs);
        $this->assertContains('users', $slugs);
        $this->assertContains('account', $slugs);
        $this->assertNotContains('leads', $slugs);
        $this->assertNotContains('partner', $slugs);
    }

    public function test_rules_controller_passes_fourteen_groups_to_view_for_superadmin(): void
    {
        $this->asSuperadmin();

        $groups = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->viewData('groups');

        $this->assertCount(14, $groups);

        $slugs = $groups->pluck('slug')->all();
        $this->assertEqualsCanonicalizing(self::EXPECTED_GROUP_SLUGS, $slugs);
    }

    private function assertPermissionInGroup(string $permissionName, string $groupSlug): void
    {
        $groupId = DB::table('permission_groups')->where('slug', $groupSlug)->value('id');
        $this->assertNotNull($groupId, "Группа {$groupSlug} не найдена");

        $permissionGroupId = DB::table('permissions')
            ->where('name', $permissionName)
            ->value('permission_group_id');

        $this->assertNotNull($permissionGroupId, "Право {$permissionName} не найдено");
        $this->assertSame((int) $groupId, (int) $permissionGroupId);
    }
}
