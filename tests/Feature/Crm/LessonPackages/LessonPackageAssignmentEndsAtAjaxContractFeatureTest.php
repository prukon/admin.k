<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт смены ends_at назначения (модалка «Изменение абонемента»).
 *
 * @see LessonPackageAssignmentsTabFeatureTest
 * @see LessonPackagesAjaxContractFeatureTest
 */
final class LessonPackageAssignmentEndsAtAjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('setPrices.packageAssignments.view');
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
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    /**
     * @return array{
     *     student: User,
     *     package: LessonPackage,
     *     assignment: UserLessonPackage,
     *     utss: UserTeamScheduleSlot|null
     * }
     */
    private function seedContext(bool $withCalendarRow = true, ?string $lastLessonDate = '2026-04-20'): array
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'EndsAt',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'EndsAt ajax package',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-05-01',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => '100.00',
            'is_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $utss = null;
        if ($withCalendarRow && $lastLessonDate !== null) {
            $team = Team::factory()->create(['partner_id' => $this->partner->id]);
            $slot = TeamScheduleSlot::query()->create([
                'partner_id' => $this->partner->id,
                'team_id' => $team->id,
                'location_id' => null,
                'weekday' => 1,
                'time_start' => '10:00:00',
                'time_end' => '11:00:00',
                'date_start' => '2020-01-01',
                'date_end' => '9999-12-31',
                'is_enabled' => 1,
            ]);

            $utss = UserTeamScheduleSlot::query()->create([
                'partner_id' => $this->partner->id,
                'user_id' => $student->id,
                'user_lesson_package_id' => $assignment->id,
                'team_schedule_slot_id' => $slot->id,
                'starts_at' => $lastLessonDate,
                'ends_at' => '2026-05-01',
                'created_by' => $this->user->id,
            ]);
        }

        return [
            'student' => $student,
            'package' => $package,
            'assignment' => $assignment,
            'utss' => $utss,
        ];
    }

    public function test_show_ajax_contract_includes_period_fields(): void
    {
        $ctx = $this->seedContext(withCalendarRow: false);

        $this->getJson(
            route('admin.lesson-packages.assignments.show', ['assignment' => $ctx['assignment']->id]),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonStructure([
                'assignment' => [
                    'id',
                    'period_display',
                    'period_start',
                    'period_end',
                    'period_editable',
                    'fee_amount',
                ],
            ])
            ->assertJsonPath('assignment.period_editable', true)
            ->assertJsonPath('assignment.period_start', '2026-04-01')
            ->assertJsonPath('assignment.period_end', '2026-05-01');
    }

    public function test_update_ends_at_ajax_success_returns_assignment_json_and_syncs_utss(): void
    {
        $ctx = $this->seedContext();

        $response = $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
            [
                'fee_amount' => '100.00',
                'ends_at' => '2026-07-01',
            ],
            $this->ajaxHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment.id', (int) $ctx['assignment']->id)
            ->assertJsonPath('assignment.period_start', '2026-04-01')
            ->assertJsonPath('assignment.period_end', '2026-07-01')
            ->assertJsonPath('assignment.period_editable', true);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['assignment']->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-07-01',
        ]);
        $this->assertDatabaseHas('user_team_schedule_slots', [
            'id' => $ctx['utss']->id,
            'ends_at' => '2026-07-01',
        ]);
        $this->assertSame(
            1,
            UserTeamScheduleSlot::query()
                ->where('user_lesson_package_id', $ctx['assignment']->id)
                ->count()
        );
    }

    public function test_update_ends_at_ajax_allows_equal_to_last_lesson_date(): void
    {
        $ctx = $this->seedContext(lastLessonDate: '2026-04-20');

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
            [
                'fee_amount' => '100.00',
                'ends_at' => '2026-04-20',
            ],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('assignment.period_end', '2026-04-20');

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['assignment']->id,
            'ends_at' => '2026-04-20',
        ]);
    }

    public function test_update_ends_at_ajax_422_before_last_lesson_with_errors_under_field(): void
    {
        $ctx = $this->seedContext(lastLessonDate: '2026-04-20');

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
            [
                'fee_amount' => '100.00',
                'ends_at' => '2026-04-10',
            ],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ends_at']);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['assignment']->id,
            'ends_at' => '2026-05-01',
        ]);
    }

    public function test_update_ends_at_ajax_422_before_starts_at(): void
    {
        $ctx = $this->seedContext(withCalendarRow: false);

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
            [
                'fee_amount' => '100.00',
                'ends_at' => '2026-03-15',
            ],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ends_at']);
    }

    public function test_update_ends_at_ajax_422_when_period_not_set(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'No period yet',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => '100.00',
            'is_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $this->getJson(
            route('admin.lesson-packages.assignments.show', ['assignment' => $assignment->id]),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('assignment.period_editable', false);

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $assignment->id]),
            [
                'fee_amount' => '100.00',
                'ends_at' => '2026-06-01',
            ],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ends_at']);
    }

    public function test_update_without_changing_ends_at_keeps_same_value_and_returns_200(): void
    {
        $ctx = $this->seedContext();

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
            [
                'fee_amount' => '120.00',
                'ends_at' => '2026-05-01',
            ],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('assignment.fee_amount', '120.00')
            ->assertJsonPath('assignment.period_end', '2026-05-01');

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'id' => $ctx['utss']->id,
            'ends_at' => '2026-05-01',
        ]);
    }
}
