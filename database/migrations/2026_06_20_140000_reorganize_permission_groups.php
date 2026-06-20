<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Перегруппировка прав в матрице ролей (UI). Имена прав и permission_role не меняются.
     * Маппинг синхронизирован с PermissionGroupsSeeder и PermissionSeeder.
     */
    public function up(): void
    {
        $now = Carbon::now();

        DB::table('permission_groups')->upsert(
            [
                [
                    'slug' => 'mainMenu',
                    'name' => 'Главное меню',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'reports',
                    'name' => 'Отчёты',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 11,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'schedule',
                    'name' => 'Расписание',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 12,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'directories',
                    'name' => 'Справочники',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 13,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'lessonPackages',
                    'name' => 'Абонементы',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 14,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'setPrices',
                    'name' => 'Установка цен',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 15,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'contracts',
                    'name' => 'Договоры',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 16,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'leads',
                    'name' => 'Заявки и лиды',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 17,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'partner',
                    'name' => 'Партнёры и сервис',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 18,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'account',
                    'name' => 'Учетная запись',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 20,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'users',
                    'name' => 'Управление пользователями',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 30,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'settings',
                    'name' => 'Настройки',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 32,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'paymentMethods',
                    'name' => 'Способы оплаты',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 35,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'misc',
                    'name' => 'Разное',
                    'description' => null,
                    'is_visible' => 1,
                    'sort_order' => 999,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['slug'],
            ['name', 'description', 'is_visible', 'sort_order', 'updated_at']
        );

        $groupIdBySlug = DB::table('permission_groups')->pluck('id', 'slug')->all();

        /** @var array<string, list<string>> $permissionNamesByGroupSlug */
        $permissionNamesByGroupSlug = [
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

        foreach ($permissionNamesByGroupSlug as $groupSlug => $permissionNames) {
            $groupId = $groupIdBySlug[$groupSlug] ?? null;
            if ($groupId === null || $permissionNames === []) {
                continue;
            }

            DB::table('permissions')
                ->whereIn('name', $permissionNames)
                ->update([
                    'permission_group_id' => $groupId,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Намеренно без отката: чисто UI-перегруппировка.
    }
};
