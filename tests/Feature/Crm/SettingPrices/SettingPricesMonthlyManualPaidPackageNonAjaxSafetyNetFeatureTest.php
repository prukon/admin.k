<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для ручной оплаты с абонементом/ценой из карточки.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesMonthlyManualPaidPackageNonAjaxSafetyNetFeatureTest extends CrmTestCase
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
        $this->asAdmin();
        $this->grantPermission('setPrices.manualPaid.manage');
        $this->grantLessonPackageTypePermissions($this->user, ['fixed', 'flexible', 'no_schedule']);

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
            'name' => 'Рубин non-ajax',
            'price_cents' => 500000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
            'is_active' => true,
        ]);
        $this->packageB = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Ромашка non-ajax',
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

    public function test_non_ajax_paid_with_card_package_redirects_and_persists_both(): void
    {
        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'paid',
                'comment' => 'Non-AJAX смена абонемента и оплаты',
                'lesson_package_id' => $this->packageB->id,
                'price' => 7000.0,
            ]);

        $response->assertRedirect(route('admin.settingPrices.users'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageB->id,
            'price_cents' => 700000,
            'is_manual_paid' => 1,
        ]);
    }

    public function test_non_ajax_invalid_package_redirects_with_field_error_and_does_not_mark_paid(): void
    {
        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'paid',
                'comment' => 'Несуществующий абонемент',
                'lesson_package_id' => 999999,
                'price' => 7000.0,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['lesson_package_id']);

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_non_ajax_negative_price_redirects_with_price_error(): void
    {
        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'paid',
                'comment' => 'Отрицательная сумма non-ajax',
                'lesson_package_id' => $this->packageB->id,
                'price' => -5,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['price']);

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'lesson_package_id' => $this->packageA->id,
            'is_manual_paid' => null,
        ]);
    }

    public function test_non_ajax_short_comment_redirects_with_comment_error(): void
    {
        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.manual-paid'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'selectedDate' => 'Октябрь 2024',
                'mode' => 'paid',
                'comment' => 'аб',
                'lesson_package_id' => $this->packageB->id,
                'price' => 7000.0,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['comment']);

        $this->assertDatabaseHas('users_prices', [
            'id' => $this->row->id,
            'is_manual_paid' => null,
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
