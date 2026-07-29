<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт assign-fixed для конфликтов расписания (fetch + X-Requested-With из модалки слота).
 *
 * Успех → 200 JSON { message }; конфликт → 422 JSON { message, errors.patterns }, не 500 и не пустой 200.
 * Контроль доступа: гость / без lessonPackages.view.
 *
 * @see LessonPackageSchoolScheduleExportAjaxContractFeatureTest
 * @see LessonPackageSchoolScheduleFixedRegistrationFeatureTest
 */
final class LessonPackageSchoolScheduleFixedOverlapAjaxContractFeatureTest extends CrmTestCase
{
    private const MONDAY = '2026-05-04';

    private const NEXT_MONDAY = '2026-05-11';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->assertSame(1, (int) CarbonImmutable::parse(self::MONDAY)->format('N'));
        $this->assertSame(1, (int) CarbonImmutable::parse(self::NEXT_MONDAY)->format('N'));
    }

    private function grantPermission(string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function fetchAjaxHeaders(): array
    {
        return [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_ACCEPT' => 'application/json',
        ];
    }

    private function studentUser(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
    }

    private function mondaySlot(string $timeStart = '14:15', string $timeEnd = '16:00'): TeamScheduleSlot
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        return TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
    }

    /**
     * @return array{student: User, slot: TeamScheduleSlot, ulp: UserLessonPackage}
     */
    private function unboundFixedContext(TeamScheduleSlot $slot): array
    {
        $student = $this->studentUser();
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Ajax overlap фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => '10.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        return ['student' => $student, 'slot' => $slot, 'ulp' => $ulp];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignBody(User $student, UserLessonPackage $ulp, TeamScheduleSlot $slot, string $anchor): array
    {
        return [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'anchor_date' => $anchor,
            'patterns' => [
                [
                    'weekday' => 1,
                    'time_start' => substr((string) $slot->time_start, 0, 5),
                    'time_end' => substr((string) $slot->time_end, 0, 5),
                ],
            ],
        ];
    }

    public function test_guest_is_denied_on_assign_fixed_json(): void
    {
        Auth::logout();

        $response = $this->call(
            'POST',
            route('admin.lesson-packages.school-schedule.assign-fixed'),
            [
                'user_id' => 1,
                'user_lesson_package_id' => 1,
                'team_schedule_slot_id' => 1,
                'anchor_date' => self::MONDAY,
                'patterns' => [
                    ['weekday' => 1, 'time_start' => '14:15', 'time_end' => '16:00'],
                ],
            ],
            [],
            [],
            $this->fetchAjaxHeaders()
        );

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_without_permission_assign_fixed_returns_403_json(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $slot = $this->mondaySlot();
        $student = $this->studentUser();
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Ajax без прав',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => '10.00',
            'is_paid' => false,
            'created_by' => $denied->id,
        ]);

        $this->postJson(
            route('admin.lesson-packages.school-schedule.assign-fixed'),
            $this->assignBody($student, $ulp, $slot, self::MONDAY),
            $this->fetchAjaxHeaders()
        )->assertForbidden();
    }

    public function test_ajax_overlap_returns_422_json_with_message_and_patterns_errors(): void
    {
        $this->grantPermission('lessonPackages.view');

        $slot = $this->mondaySlot();
        ['student' => $student, 'ulp' => $ulp] = $this->unboundFixedContext($slot);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::MONDAY,
            'ends_at' => self::MONDAY,
            'is_trial_lesson' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            route('admin.lesson-packages.school-schedule.assign-fixed'),
            $this->assignBody($student, $ulp, $slot, self::MONDAY),
            $this->fetchAjaxHeaders()
        );

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['patterns'],
            ]);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());

        $patternsError = (string) ($response->json('errors.patterns.0') ?? '');
        $this->assertStringContainsString('Конфликт расписания на '.self::MONDAY, $patternsError);
        $this->assertStringContainsString('14:15–16:00', $patternsError);
        $this->assertSame((string) $response->json('message'), $patternsError);

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_ajax_ends_at_tail_success_returns_message_json_and_creates_row(): void
    {
        $this->grantPermission('lessonPackages.view');

        $slot = $this->mondaySlot();
        ['student' => $student, 'ulp' => $ulp] = $this->unboundFixedContext($slot);

        $oldPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Старый ajax tail',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $oldUlp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $oldPackage->id,
            'starts_at' => self::MONDAY,
            'ends_at' => '2026-06-03',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => '10.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $oldUlp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::MONDAY,
            'ends_at' => '2026-06-03',
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(
            route('admin.lesson-packages.school-schedule.assign-fixed'),
            $this->assignBody($student, $ulp, $slot, self::NEXT_MONDAY),
            $this->fetchAjaxHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure(['message'])
            ->assertJsonPath('message', 'Абонемент назначен, занятия привязаны к расписанию школы.');

        $this->assertArrayNotHasKey('errors', $response->json() ?? []);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::NEXT_MONDAY,
        ]);
    }

    public function test_page_with_permission_returns_200_not_empty(): void
    {
        $this->grantPermission('lessonPackages.view');

        $response = $this->get(route('admin.lesson-packages.school-schedule'));

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertSee('submitSchoolCalSlotFixedRegistration', false);
        $response->assertSee('schoolCalSlotFixedFormWrap', false);
    }
}
