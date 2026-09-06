<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\PaymentNotificationRule;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use App\Support\LessonPackageTypePermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX JSON: 200/422 на schedule_type и поле пакета, show для edit-inject.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageTypePermissionAjaxContractFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $fixedPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantPermission('lessonPackages.view');

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);

        $this->fixedPackage = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Ajax fixed',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
            'price_cents' => 10000,
            'lessons_count' => 8,
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

    public function test_ajax_store_with_permission_returns_json_success_not_empty_html(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['no_schedule']);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.lesson-packages.store'), [
                'name' => 'Ajax разовое',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE,
                'price' => 500,
                'lessons_count' => 1,
                'duration_days' => 1,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonMissingPath('errors');
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax разовое',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE,
        ]);
    }

    public function test_ajax_store_without_permission_returns_422_under_schedule_type(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.lesson-packages.store'), [
                'name' => 'Ajax denied flexible',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_FLEXIBLE,
                'price' => 200,
                'lessons_count' => 8,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['schedule_type']]);
        $this->assertSame(
            LessonPackageTypePermission::denyScheduleTypeMessage('flexible'),
            $response->json('errors.schedule_type.0')
        );
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax denied flexible',
        ]);
    }

    public function test_ajax_update_keep_existing_type_without_permission_returns_json_success(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.update', $this->fixedPackage), [
                'name' => 'Ajax fixed updated',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
                'price' => 333,
                'lessons_count' => 8,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->fixedPackage->refresh();
        $this->assertSame('Ajax fixed updated', $this->fixedPackage->name);
        $this->assertSame(LessonPackage::SCHEDULE_TYPE_FIXED, $this->fixedPackage->schedule_type);
    }

    public function test_ajax_update_change_type_without_permission_returns_422_and_does_not_write(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.update', $this->fixedPackage), [
                'name' => $this->fixedPackage->name,
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE,
                'price' => 100,
                'lessons_count' => 1,
                'duration_days' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schedule_type']);

        $this->fixedPackage->refresh();
        $this->assertSame(LessonPackage::SCHEDULE_TYPE_FIXED, $this->fixedPackage->schedule_type);
    }

    public function test_show_json_for_edit_modal_returns_code_even_without_type_permission(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.show', $this->fixedPackage))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.schedule_type', 'fixed')
            ->assertJsonStructure(['success', 'lesson_package' => ['id', 'name', 'schedule_type']]);
    }

    public function test_set_team_price_ajax_with_permission_returns_json_contract(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setTeamPrice'), [
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->fixedPackage->id,
                'selectedDate' => 'Август 2026',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'teamPrice', 'lesson_package_id', 'teamId']);
    }

    public function test_payment_notifications_ajax_store_with_permission_returns_created_json(): void
    {
        $this->grantPermission('setPrices.paymentNotifications.manage');
        $this->grantLessonPackageTypePermissions($this->user, ['flexible']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.settingPrices.paymentNotifications.rules.store'), [
                'name' => 'Ajax PN flexible',
                'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
                'trigger_value' => 5,
                'billing_month_offset' => 0,
                'schedule_types' => ['flexible'],
                'subject_template' => 'Тема',
                'body_html_template' => '<p>Тело</p>',
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('rule.schedule_types.0', 'flexible');
    }

    public function test_payment_notifications_ajax_store_without_permission_returns_422_on_schedule_types(): void
    {
        $this->grantPermission('setPrices.paymentNotifications.manage');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.settingPrices.paymentNotifications.rules.store'), [
                'name' => 'Ajax PN denied',
                'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
                'trigger_value' => 5,
                'billing_month_offset' => 0,
                'schedule_types' => ['fixed'],
                'subject_template' => 'Тема',
                'body_html_template' => '<p>Тело</p>',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schedule_types']);
    }

    public function test_set_price_all_users_ajax_with_permission_updates_package(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price_cents' => 0,
            'is_paid' => false,
            'lesson_package_id' => null,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setPriceAllUsers'), [
                'selectedDate' => 'Август 2026',
                'teamId' => $this->team->id,
                'usersPrice' => [[
                    'user_id' => $this->student->id,
                    'price' => 0,
                    'lesson_package_id' => $this->fixedPackage->id,
                    'user' => ['name' => $this->student->name],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => $this->fixedPackage->id,
        ]);
    }
}
