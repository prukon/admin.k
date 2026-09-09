<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Enums\AuditEvent;
use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Ручная отметка оплаты на «По месяцам»: при mode=paid сначала применяется
 * абонемент/цена из карточки (как Apply), затем ставится is_manual_paid.
 */
final class SettingPricesMonthlyManualPaidAppliesPackageFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $packageA;

    private LessonPackage $packageB;

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
            'title' => 'Группа manual-paid package',
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'name' => 'Иван',
            'lastname' => 'Тестов',
        ]);

        $this->packageA = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Рубин',
            'price_cents' => 500000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
            'is_active' => true,
        ]);
        $this->packageB = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Ромашка',
            'price_cents' => 700000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
            'is_active' => true,
        ]);
    }

    public function test_paid_with_package_and_price_applies_card_then_marks_paid(): void
    {
        $this->asSuperadmin();

        $row = UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->packageA->id,
        ]);

        $this->postJson(route('setting-prices.manual-paid'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => 'Октябрь 2024',
            'mode' => 'paid',
            'comment' => 'Смена абонемента вместе со статусом',
            'lesson_package_id' => $this->packageB->id,
            'price' => 7000.0,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_price.lesson_package_id', $this->packageB->id);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'lesson_package_id' => $this->packageB->id,
            'price_cents' => 700000,
            'is_manual_paid' => 1,
            'is_paid' => 0,
        ]);

        $this->assertDatabaseHas('my_logs', [
            'event' => AuditEvent::PricingStudentApply->value,
            'user_id' => $this->student->id,
        ]);
        $this->assertDatabaseHas('my_logs', [
            'event' => AuditEvent::PricingManualMonthPaid->value,
            'user_id' => $this->student->id,
        ]);
    }

    public function test_paid_without_package_fields_only_sets_manual_flag(): void
    {
        $this->asSuperadmin();

        $row = UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->packageA->id,
        ]);

        $this->postJson(route('setting-prices.manual-paid'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => 'Октябрь 2024',
            'mode' => 'paid',
            'comment' => 'Только статус, без абонемента',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'lesson_package_id' => $this->packageA->id,
            'price_cents' => 500000,
            'is_manual_paid' => 1,
        ]);
    }

    public function test_unpaid_ignores_package_and_price_in_payload(): void
    {
        $this->asSuperadmin();

        $row = UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'is_manual_paid' => 1,
            'lesson_package_id' => $this->packageA->id,
        ]);

        $this->postJson(route('setting-prices.manual-paid'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => 'Октябрь 2024',
            'mode' => 'unpaid',
            'comment' => 'Снять оплату, абонемент не трогать',
            'lesson_package_id' => $this->packageB->id,
            'price' => 7000.0,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'lesson_package_id' => $this->packageA->id,
            'price_cents' => 500000,
            'is_manual_paid' => 0,
        ]);
    }

    public function test_unknown_package_returns_422_on_lesson_package_id_and_does_not_mark_paid(): void
    {
        $this->asSuperadmin();

        $row = UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->packageA->id,
        ]);

        $this->postJson(route('setting-prices.manual-paid'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => 'Октябрь 2024',
            'mode' => 'paid',
            'comment' => 'Чужой абонемент',
            'lesson_package_id' => 999999,
            'price' => 7000.0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_package_id']);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'lesson_package_id' => $this->packageA->id,
            'price_cents' => 500000,
            'is_manual_paid' => null,
        ]);
    }

    public function test_negative_price_returns_422_on_price_and_does_not_mark_paid(): void
    {
        $this->asSuperadmin();

        $row = UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->packageA->id,
        ]);

        $this->postJson(route('setting-prices.manual-paid'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => 'Октябрь 2024',
            'mode' => 'paid',
            'comment' => 'Отрицательная сумма',
            'lesson_package_id' => $this->packageB->id,
            'price' => -10,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price']);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_manager_with_rights_marks_paid_and_keeps_changed_package_from_card(): void
    {
        $this->asAdmin();
        $this->grantPermission('setPrices.view');
        $this->grantPermission('setPrices.manualPaid.manage');
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule']);

        $row = $this->unpaidRubyRow();

        $this->postJson(route('setting-prices.manual-paid'), $this->paidCardPayload([
            'lesson_package_id' => $this->packageB->id,
            'price' => 7000.0,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_price.lesson_package_id', $this->packageB->id)
            ->assertJsonPath('user_price.user_id', $this->student->id);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'lesson_package_id' => $this->packageB->id,
            'price_cents' => 700000,
            'is_manual_paid' => 1,
        ]);
    }

    public function test_changing_only_price_then_marking_paid_saves_price_and_keeps_package(): void
    {
        $this->asSuperadmin();
        $row = $this->unpaidRubyRow();

        $this->postJson(route('setting-prices.manual-paid'), $this->paidCardPayload([
            'price' => 6100.0,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'lesson_package_id' => $this->packageA->id,
            'price_cents' => 610000,
            'is_manual_paid' => 1,
        ]);
    }

    public function test_changing_only_package_then_marking_paid_keeps_previous_price(): void
    {
        $this->asSuperadmin();
        $row = $this->unpaidRubyRow();

        $this->postJson(route('setting-prices.manual-paid'), $this->paidCardPayload([
            'lesson_package_id' => $this->packageB->id,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'lesson_package_id' => $this->packageB->id,
            'price_cents' => 500000,
            'is_manual_paid' => 1,
        ]);
    }

    public function test_already_assigned_package_can_be_resent_without_type_permission(): void
    {
        $this->asAdmin();
        $this->grantPermission('setPrices.view');
        $this->grantPermission('setPrices.manualPaid.manage');

        $row = $this->unpaidRubyRow();

        $this->postJson(route('setting-prices.manual-paid'), $this->paidCardPayload([
            'lesson_package_id' => $this->packageA->id,
            'price' => 5000.0,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => 1,
        ]);
    }

    public function test_laid_out_package_change_returns_422_and_does_not_mark_paid(): void
    {
        $this->asSuperadmin();

        $row = UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-11-01',
            'price_cents' => 0,
            'is_paid' => 0,
            'lesson_package_id' => null,
        ]);

        $fixedA = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(4, 60)->create([
            'name' => 'Fixed A laid-out',
            'price_cents' => 800000,
            'is_active' => true,
        ]);
        $fixedB = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(8, 90)->create([
            'name' => 'Fixed B laid-out',
            'price_cents' => 1200000,
            'is_active' => true,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [[
                'user_id' => $this->student->id,
                'price' => 8000.0,
                'lesson_package_id' => $fixedA->id,
                'user' => ['name' => $this->student->name],
            ]],
        ])->assertOk();

        $row->refresh();
        $ulpId = (int) $row->user_lesson_package_id;
        $this->assertGreaterThan(0, $ulpId);
        UserLessonPackage::query()->whereKey($ulpId)->update([
            'starts_at' => '2025-11-03',
            'ends_at' => '2025-12-03',
        ]);

        $this->postJson(route('setting-prices.manual-paid'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => 'Ноябрь 2025',
            'mode' => 'paid',
            'comment' => 'Смена разложенного абонемента при оплате',
            'lesson_package_id' => $fixedB->id,
            'price' => 12000.0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_package_id']);

        $row->refresh();
        $this->assertSame((int) $fixedA->id, (int) $row->lesson_package_id);
        $this->assertNull($row->is_manual_paid);
        $this->assertSame($ulpId, (int) $row->user_lesson_package_id);
    }

    public function test_new_package_type_without_permission_returns_422_and_does_not_mark_paid(): void
    {
        $this->asAdmin();
        $this->grantPermission('setPrices.view');
        $this->grantPermission('setPrices.manualPaid.manage');
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        $row = UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->packageA->id,
        ]);

        $this->postJson(route('setting-prices.manual-paid'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => 'Октябрь 2024',
            'mode' => 'paid',
            'comment' => 'Нет права на тип нового абонемента',
            'lesson_package_id' => $this->packageB->id,
            'price' => 7000.0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_package_id']);

        $this->assertDatabaseHas('users_prices', [
            'id' => $row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function paidCardPayload(array $extra = []): array
    {
        return array_merge([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'selectedDate' => 'Октябрь 2024',
            'mode' => 'paid',
            'comment' => 'Комментарий к ручной отметке оплаты',
        ], $extra);
    }

    private function unpaidRubyRow(): UserPrice
    {
        return UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2024-10-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->packageA->id,
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
