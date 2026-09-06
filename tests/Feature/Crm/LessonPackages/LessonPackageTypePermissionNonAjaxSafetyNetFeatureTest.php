<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net type.*: 302 на раздел, запись создана/отклонена, errors по полям.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageTypePermissionNonAjaxSafetyNetFeatureTest extends CrmTestCase
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
            'name' => 'Non-ajax fixed',
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

    public function test_store_without_ajax_and_with_permission_creates_and_redirects(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['flexible']);

        $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), [
                'name' => 'Создан non-ajax flexible',
                'schedule_type' => 'flexible',
                'duration_days' => 30,
                'lessons_count' => 8,
                'price' => '1500.00',
            ])
            ->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Создан non-ajax flexible',
            'schedule_type' => 'flexible',
        ]);
    }

    public function test_directories_store_without_ajax_returns_to_directories(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        $this->from(route('admin.directories.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), [
                'name' => 'Dirs non-ajax fixed',
                'schedule_type' => 'fixed',
                'duration_days' => 30,
                'lessons_count' => 8,
                'price' => '1500.00',
            ])
            ->assertRedirect(route('admin.directories.lesson-packages.index'))
            ->assertSessionHas('success');
    }

    public function test_store_without_ajax_and_without_permission_redirects_with_schedule_type_error(): void
    {
        $response = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), [
                'name' => 'Denied non-ajax no_schedule',
                'schedule_type' => 'no_schedule',
                'duration_days' => 1,
                'lessons_count' => 1,
                'price' => '500.00',
            ]);

        $response->assertStatus(302)
            ->assertSessionHasErrors(['schedule_type']);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(
            'Недостаточно прав для выбора типа «Разовое занятие».',
            session('errors')->first('schedule_type')
        );
        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Denied non-ajax no_schedule',
        ]);
    }

    public function test_update_without_ajax_keeps_existing_type_without_permission_and_redirects(): void
    {
        $response = $this->from(route('admin.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', $this->fixedPackage), [
                'name' => 'Non-ajax keep type',
                'schedule_type' => 'fixed',
                'duration_days' => 30,
                'lessons_count' => 8,
                'price' => '200.00',
            ]);

        $response->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');
        $this->assertNotSame(200, $response->getStatusCode());

        $this->fixedPackage->refresh();
        $this->assertSame('Non-ajax keep type', $this->fixedPackage->name);
        $this->assertSame('fixed', $this->fixedPackage->schedule_type);
    }

    public function test_set_price_all_users_without_ajax_and_without_permission_redirects_with_field_error(): void
    {
        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price_cents' => 0,
            'is_paid' => false,
            'lesson_package_id' => null,
        ]);

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setPriceAllUsers'), [
                'selectedDate' => 'Август 2026',
                'teamId' => $this->team->id,
                'usersPrice' => [[
                    'user_id' => $this->student->id,
                    'price' => 0,
                    'lesson_package_id' => $this->fixedPackage->id,
                ]],
            ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertSessionHasErrors(['usersPrice.0.lesson_package_id']);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'lesson_package_id' => null,
        ]);
    }

    public function test_set_team_price_without_ajax_and_with_permission_redirects_and_writes(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);

        $response = $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setTeamPrice'), [
                'teamId' => $this->team->id,
                'lesson_package_id' => $this->fixedPackage->id,
                'selectedDate' => 'Август 2026',
            ]);

        $response->assertRedirect(route('admin.settingPrices.indexMenu'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'lesson_package_id' => $this->fixedPackage->id,
        ]);
    }

    public function test_assignments_store_without_ajax_and_without_permission_redirects_with_package_error(): void
    {
        $this->grantPermission('setPrices.packageAssignments.view');

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                'user_id' => $this->student->id,
                'lesson_package_id' => $this->fixedPackage->id,
                'fee_amount' => '100.00',
            ]);

        $response->assertStatus(302)
            ->assertSessionHasErrors(['lesson_package_id']);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertDatabaseMissing('user_lesson_packages', [
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);
    }
}
