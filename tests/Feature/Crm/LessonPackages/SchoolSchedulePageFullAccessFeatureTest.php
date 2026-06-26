<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Location;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа: страница «Расписание школы» и все endpoint'ы раздела
 * отдают 200 при lessonPackages.view; без права и для гостя — отказ.
 */
final class SchoolSchedulePageFullAccessFeatureTest extends CrmTestCase
{
    private const WEEK_MONDAY = '2026-05-04';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->assertSame(
            1,
            (int) CarbonImmutable::parse(self::WEEK_MONDAY)->format('N'),
            'Тестовая дата должна быть понедельником (ISO weekday 1).'
        );
    }

    private function grantLessonPackagesView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function actingAsViewer(): User
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantLessonPackagesView($actor);

        return $actor;
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('is_visible', 1)->orderBy('order_by')->value('id');
    }

    private function studentUser(string $suffix = ''): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Ученик'.$suffix,
            'lastname'   => 'Доступ',
            'is_enabled' => 1,
        ]);
    }

    private function mondaySlot(Team $team, string $timeStart, string $timeEnd): TeamScheduleSlot
    {
        return TeamScheduleSlot::query()->create([
            'partner_id'  => $this->partner->id,
            'team_id'     => $team->id,
            'location_id' => null,
            'weekday'     => 1,
            'time_start'  => $timeStart,
            'time_end'    => $timeEnd,
            'date_start'  => '2026-01-01',
            'date_end'    => '9999-12-31',
            'is_enabled'  => 1,
        ]);
    }

    /**
     * @return array{patterns: list<array{weekday: int, time_start: string, time_end: string}>}
     */
    private function fixedCalendarBindPattern(int $weekday, string $timeStart, string $timeEnd): array
    {
        return [
            'patterns' => [
                [
                    'weekday'    => $weekday,
                    'time_start' => $timeStart,
                    'time_end'   => $timeEnd,
                ],
            ],
        ];
    }

    public function test_guest_is_denied_on_all_school_schedule_endpoints(): void
    {
        Auth::logout();

        foreach ($this->deniedRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_permission_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->deniedRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $allowed = $item['method'] === 'DELETE' ? [403, 404] : [403];

            $this->assertContains(
                $response->getStatusCode(),
                $allowed,
                "Без lessonPackages.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_viewer_with_lesson_packages_view_page_and_all_endpoints_return_200(): void
    {
        $this->actingAsViewer();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $studentRead = $this->studentUser('Read');
        $slotRead = $this->mondaySlot($team, '08:00', '09:00');

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('Расписание школы', false)
            ->assertSee('historyModal', false)
            ->assertSee('fa-clock-rotate-left', false)
            ->assertSee('schoolCalGrid', false);

        $this->getJson(route('logs.data.school-schedule', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.lesson-packages.school-schedule.week', ['week' => self::WEEK_MONDAY]))
            ->assertOk()
            ->assertJsonStructure(['week_start', 'occurrences']);

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week'        => self::WEEK_MONDAY,
            'location_id' => $location->id,
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.view-settings'))
            ->assertOk()
            ->assertJsonStructure(['view_start_min', 'view_end_min']);

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 480,
            'view_end_min'   => 1200,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson(route('admin.lesson-packages.school-schedule.assignment-availability'))
            ->assertOk()
            ->assertJsonStructure(['flexible', 'fixed', 'single_lesson']);

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-packages'))
            ->assertOk()
            ->assertJsonStructure(['packages']);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', ['user_id' => 0]))
            ->assertOk()
            ->assertExactJson(['assignments' => []]);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', ['user_id' => $studentRead->id]))
            ->assertOk()
            ->assertJsonStructure(['assignments']);

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-assignments', ['user_id' => $studentRead->id]))
            ->assertOk()
            ->assertJsonStructure(['assignments']);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-users-search', ['q' => '']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-assignments', ['user_id' => 0]))
            ->assertOk()
            ->assertExactJson(['assignments' => []]);

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-users-search', ['q' => '']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('admin.lesson-packages.assignments.users-search', ['q' => '']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id'               => $studentRead->id,
            'team_schedule_slot_id' => $slotRead->id,
            'occurrence_date'       => self::WEEK_MONDAY,
        ]))->assertOk()
            ->assertJsonStructure(['allowed', 'reason']);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id'               => $studentRead->id,
            'team_schedule_slot_id' => $slotRead->id,
            'occurrence_date'       => self::WEEK_MONDAY,
        ]))->assertOk()
            ->assertJsonStructure([
                'flexible'      => ['allowed', 'reason', 'existing_assignments'],
                'fixed'         => ['allowed', 'reason'],
                'single_lesson' => ['allowed', 'reason'],
                'trial'         => ['allowed', 'reason'],
            ]);

        // assign-flexible
        $studentFlex = $this->studentUser('Flex');
        $flexPackage = LessonPackage::query()->create([
            'partner_id'    => $this->partner->id,
            'name'          => 'Full access flex',
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 5,
            'price_cents'   => 5000,
            'freeze_enabled'=> 0,
            'freeze_days'   => 0,
            'is_active'     => 1,
        ]);
        $ulpFlex = UserLessonPackage::query()->create([
            'user_id'           => $studentFlex->id,
            'lesson_package_id' => $flexPackage->id,
            'starts_at'         => '2026-04-01',
            'ends_at'           => '2026-12-31',
            'lessons_total'     => 5,
            'lessons_remaining' => 3,
            'created_by'        => $this->user->id,
        ]);
        $slotFlex = $this->mondaySlot($team, '10:00', '11:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulpFlex->id,
            'team_schedule_slot_id'  => $slotFlex->id,
            'occurrence_date'        => self::WEEK_MONDAY,
        ])->assertOk();

        // assign-fixed
        $studentFixed = $this->studentUser('Fixed');
        $fixedPackage = LessonPackage::query()->create([
            'partner_id'    => $this->partner->id,
            'name'          => 'Full access fixed',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents'   => 8000,
            'freeze_enabled'=> 0,
            'freeze_days'   => 0,
            'is_active'     => 1,
        ]);
        $ulpFixed = UserLessonPackage::query()->create([
            'user_id'           => $studentFixed->id,
            'lesson_package_id' => $fixedPackage->id,
            'starts_at'         => null,
            'ends_at'           => null,
            'lessons_total'     => 1,
            'lessons_remaining' => 1,
            'fee_amount'        => '80.00',
            'is_paid'           => false,
            'created_by'        => $this->user->id,
        ]);
        $slotFixed = $this->mondaySlot($team, '11:00', '12:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), array_merge([
            'user_id'                => $studentFixed->id,
            'user_lesson_package_id' => $ulpFixed->id,
            'team_schedule_slot_id'  => $slotFixed->id,
            'anchor_date'            => self::WEEK_MONDAY,
        ], $this->fixedCalendarBindPattern(1, '11:00', '12:00')))->assertOk();

        // assign-single-lesson (существующее назначение)
        $studentAssignSingle = $this->studentUser('AssignSingle');
        $singlePackage = LessonPackage::query()->create([
            'partner_id'    => $this->partner->id,
            'name'          => 'Full access single assign',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents'   => 150000,
            'freeze_enabled'=> 0,
            'freeze_days'   => 0,
            'is_active'     => 1,
        ]);
        $ulpSingle = UserLessonPackage::query()->create([
            'user_id'           => $studentAssignSingle->id,
            'lesson_package_id' => $singlePackage->id,
            'starts_at'         => null,
            'ends_at'           => null,
            'lessons_total'     => 1,
            'lessons_remaining' => 1,
            'created_by'        => $this->user->id,
        ]);
        $slotAssignSingle = $this->mondaySlot($team, '12:00', '13:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-single-lesson'), [
            'user_lesson_package_id' => $ulpSingle->id,
            'team_schedule_slot_id'  => $slotAssignSingle->id,
            'occurrence_date'        => self::WEEK_MONDAY,
        ])->assertOk();

        // single-lesson-registration (новое назначение из модалки)
        $studentRegSingle = $this->studentUser('RegSingle');
        $regPackage = LessonPackage::query()->create([
            'partner_id'    => $this->partner->id,
            'name'          => 'Full access single reg',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents'   => 200000,
            'freeze_enabled'=> 0,
            'freeze_days'   => 0,
            'is_active'     => 1,
        ]);
        $slotRegSingle = $this->mondaySlot($team, '13:00', '14:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id'               => $studentRegSingle->id,
            'team_schedule_slot_id' => $slotRegSingle->id,
            'occurrence_date'       => self::WEEK_MONDAY,
            'lesson_package_id'     => $regPackage->id,
            'fee_amount'            => 1800,
        ])->assertOk();

        $regBindId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $studentRegSingle->id)
            ->where('team_schedule_slot_id', $slotRegSingle->id)
            ->value('id');
        $this->assertGreaterThan(0, $regBindId);

        $this->deleteJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', [
            'userTeamScheduleSlot' => $regBindId,
        ]))->assertOk();

        // trial registration
        $studentTrial = $this->studentUser('Trial');
        $slotTrial = $this->mondaySlot($team, '14:00', '15:00');

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id'               => $studentTrial->id,
            'team_schedule_slot_id' => $slotTrial->id,
            'occurrence_date'       => self::WEEK_MONDAY,
        ])->assertOk();

        $trialId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $studentTrial->id)
            ->where('is_trial_lesson', true)
            ->value('id');
        $this->assertGreaterThan(0, $trialId);

        // occurrence status + history
        $status = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'user_id'                     => $studentTrial->id,
            'team_schedule_slot_id'       => $slotTrial->id,
            'occurrence_date'             => self::WEEK_MONDAY,
            'lesson_occurrence_status_id' => $status->id,
        ])->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.occurrence-status.history', [
            'user_id'               => $studentTrial->id,
            'team_schedule_slot_id' => $slotTrial->id,
            'occurrence_date'       => self::WEEK_MONDAY,
        ]))->assertOk()
            ->assertJsonStructure(['events']);

        $this->deleteJson(route('admin.lesson-packages.school-schedule.trial-registration.destroy', [
            'userTeamScheduleSlot' => $trialId,
        ]))->assertOk();
    }

    public function test_admin_all_school_schedule_endpoints_return_200(): void
    {
        $this->asAdmin();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->studentUser('Admin');
        $slot = $this->mondaySlot($team, '16:00', '17:00');

        foreach ($this->readOnlyRoutesPayload($student->id, $slot->id) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Админ read: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk();

        $this->getJson(route('logs.data.school-schedule', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk();

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id'               => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date'       => self::WEEK_MONDAY,
        ])->assertOk();

        $trialId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('is_trial_lesson', true)
            ->value('id');

        $this->deleteJson(route('admin.lesson-packages.school-schedule.trial-registration.destroy', [
            'userTeamScheduleSlot' => $trialId,
        ]))->assertOk();
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function deniedRoutesPayload(): array
    {
        return array_merge(
            $this->readOnlyRoutesPayload(userId: 1, slotId: 1),
            [
                [
                    'method' => 'POST',
                    'url'    => route('admin.lesson-packages.school-schedule.view-settings.save'),
                    'data'   => ['view_start_min' => 480, 'view_end_min' => 1200],
                ],
                [
                    'method' => 'POST',
                    'url'    => route('admin.lesson-packages.school-schedule.assign-flexible'),
                    'data'   => [
                        'user_lesson_package_id' => 1,
                        'team_schedule_slot_id'  => 1,
                        'occurrence_date'        => self::WEEK_MONDAY,
                    ],
                ],
                [
                    'method' => 'POST',
                    'url'    => route('admin.lesson-packages.school-schedule.assign-fixed'),
                    'data'   => array_merge([
                        'user_id'                => 1,
                        'user_lesson_package_id' => 1,
                        'team_schedule_slot_id'  => 1,
                        'anchor_date'            => self::WEEK_MONDAY,
                    ], $this->fixedCalendarBindPattern(1, '10:00', '11:00')),
                ],
                [
                    'method' => 'POST',
                    'url'    => route('admin.lesson-packages.school-schedule.assign-single-lesson'),
                    'data'   => [
                        'user_lesson_package_id' => 1,
                        'team_schedule_slot_id'  => 1,
                        'occurrence_date'        => self::WEEK_MONDAY,
                    ],
                ],
                [
                    'method' => 'POST',
                    'url'    => route('admin.lesson-packages.school-schedule.single-lesson-registration.store'),
                    'data'   => [
                        'user_id'               => 1,
                        'team_schedule_slot_id' => 1,
                        'occurrence_date'       => self::WEEK_MONDAY,
                        'lesson_package_id'     => 1,
                        'fee_amount'            => 1000,
                    ],
                ],
                [
                    'method' => 'POST',
                    'url'    => route('admin.lesson-packages.school-schedule.trial-registration.store'),
                    'data'   => [
                        'user_id'               => 1,
                        'team_schedule_slot_id' => 1,
                        'occurrence_date'       => self::WEEK_MONDAY,
                    ],
                ],
                [
                    'method' => 'DELETE',
                    'url'    => route('admin.lesson-packages.school-schedule.trial-registration.destroy', [
                        'userTeamScheduleSlot' => 1,
                    ]),
                ],
                [
                    'method' => 'DELETE',
                    'url'    => route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', [
                        'userTeamScheduleSlot' => 1,
                    ]),
                ],
                [
                    'method' => 'POST',
                    'url'    => route('admin.lesson-packages.school-schedule.occurrence-status.store'),
                    'data'   => [
                        'user_id'                     => 1,
                        'team_schedule_slot_id'       => 1,
                        'occurrence_date'             => self::WEEK_MONDAY,
                        'lesson_occurrence_status_id' => 1,
                    ],
                ],
                [
                    'method' => 'GET',
                    'url'    => route('admin.lesson-packages.school-schedule.occurrence-status.history', [
                        'user_id'               => 1,
                        'team_schedule_slot_id' => 1,
                        'occurrence_date'       => self::WEEK_MONDAY,
                    ]),
                ],
            ]
        );
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function readOnlyRoutesPayload(int $userId, int $slotId): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.lesson-packages.school-schedule'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('logs.data.school-schedule', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.lesson-packages.school-schedule.week', ['week' => self::WEEK_MONDAY]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.lesson-packages.school-schedule.view-settings'),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.lesson-packages.school-schedule.assignment-availability'),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.lesson-packages.school-schedule.fixed-packages'),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.lesson-packages.school-schedule.flexible-assignments', ['user_id' => $userId]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.lesson-packages.school-schedule.fixed-assignments', ['user_id' => $userId]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.lesson-packages.school-schedule.flexible-users-search', ['q' => '']),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.lesson-packages.school-schedule.single-lesson-assignments', ['user_id' => $userId]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.lesson-packages.school-schedule.single-lesson-users-search', ['q' => '']),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.lesson-packages.assignments.users-search', ['q' => '']),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
                    'user_id'               => $userId,
                    'team_schedule_slot_id' => $slotId,
                    'occurrence_date'       => self::WEEK_MONDAY,
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
                    'user_id'               => $userId,
                    'team_schedule_slot_id' => $slotId,
                    'occurrence_date'       => self::WEEK_MONDAY,
                ]),
            ],
        ];
    }
}
