<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions\Concerns;

use App\Models\LessonPackage;
use App\Models\Location;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Общие хелперы фичи «Расписание школы скрыто и выкл. для нового партнёра».
 *
 * @mixin \Tests\Feature\Crm\CrmTestCase
 */
trait SchoolScheduleHiddenDefaultPermissionsTestHelpers
{
    /**
     * @return list<string>
     */
    protected function schoolSchedulePermissionNames(): array
    {
        return [
            'scheduleSlots.view',
            'scheduleSlots.manage',
            'scheduleSlots.table',
            'lessonPackages.export',
            'setPrices.packageAssignments.view',
            'lessonPackages.manualPaid.manage',
        ];
    }

    /**
     * @param  list<string>  $names
     */
    protected function grantNamedPermissions(array $names, ?User $actor = null): void
    {
        $actor ??= $this->user;

        foreach ($names as $name) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $actor->role_id,
                'permission_id' => $this->permissionId($name),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function grantAllSchoolSchedulePermissions(?User $actor = null): void
    {
        $this->grantNamedPermissions($this->schoolSchedulePermissionNames(), $actor);
    }

    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    protected function sidebarChunk(string $html): string
    {
        // CSS в <head> тоже содержит «nav-sidebar»; якорь — разметка меню.
        $sidebarStart = strpos($html, 'nav nav-pills nav-sidebar');
        $this->assertNotFalse($sidebarStart, 'Sidebar not found in response');

        return substr($html, (int) $sidebarStart, 5000);
    }

    protected function lessonPackagesTabsChunk(string $html): string
    {
        $start = strpos($html, 'nav-tabs');
        $this->assertNotFalse($start, 'Вкладки раздела «Расписание школы» не найдены');
        $end = strpos($html, 'class="tab-content"', $start + 1);
        $this->assertNotFalse($end, 'tab-content не найден после вкладок');

        return substr($html, $start, $end - $start);
    }

    protected function packagesCreateModalHtml(string $pageHtml): string
    {
        $start = strpos($pageHtml, 'id="lessonPackageCreateForm"');
        $this->assertNotFalse($start, 'Не найдена форма lessonPackageCreateForm');
        $end = strpos($pageHtml, 'id="lessonPackageEditModal"', $start + 1);
        $this->assertNotFalse($end, 'Не найден маркер lessonPackageEditModal');

        return substr($pageHtml, $start, $end - $start);
    }

    /**
     * @return array<string, mixed>
     */
    protected function packageStorePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Пакет нового admin',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1200.00',
            'freeze_enabled' => 0,
            'freeze_days' => '',
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    /**
     * @return array{
     *     team: Team,
     *     location: Location,
     *     slot: TeamScheduleSlot,
     *     student: User,
     *     package: LessonPackage,
     *     assignment: UserLessonPackage,
     *     storePayload: array<string, mixed>,
     *     updatePayload: array<string, mixed>
     * }
     */
    protected function seedSchoolScheduleHiddenDefaultContext(): array
    {
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'name' => 'Объект скрытых прав',
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'title' => 'Группа скрытых прав',
        ]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
            'lastname' => 'Скрытые',
            'name' => 'Права',
        ]);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Шаблон скрытых прав',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);
        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 10000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $storePayload = [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 3,
            'time_start' => '11:00',
            'time_end' => '12:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'is_enabled' => 1,
        ];
        $updatePayload = [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'apply_changes_from' => '2026-01-01',
            'is_enabled' => 1,
        ];

        return compact(
            'team',
            'location',
            'slot',
            'student',
            'package',
            'assignment',
            'storePayload',
            'updatePayload'
        );
    }

    protected function assertDeniedNotEmpty200Or500(TestResponse $response, string $label): void
    {
        $this->assertContains(
            $response->getStatusCode(),
            [302, 401, 403, 419],
            $label.' → '.$response->getStatusCode()
        );
        $this->assertNotSame(500, $response->getStatusCode(), $label.' не должен быть 500');
        $this->assertNotSame(200, $response->getStatusCode(), $label.' не должен быть пустой/успешный 200');
    }

    /**
     * @param  list<int>  $permissionIds
     */
    protected function assertPermissionIdsDoNotIncludeSchoolSchedule(array $permissionIds): void
    {
        $ids = array_map('intval', $permissionIds);

        foreach ($this->schoolSchedulePermissionNames() as $name) {
            $this->assertNotContains(
                $this->permissionId($name),
                $ids,
                "Кастомная роль не должна получать {$name} из шаблона admin"
            );
        }
    }
}
