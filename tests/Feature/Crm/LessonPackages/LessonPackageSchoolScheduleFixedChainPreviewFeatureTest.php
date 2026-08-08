<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Location;
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
 * Превью цепочки фиксированной привязки (POST assign-fixed-preview): JSON без записи в БД,
 * UI-маркеры, контроль доступа (гость / 403 / viewer), AJAX-контракт и non-AJAX safety-net.
 *
 * @see docs/documentation/school-schedule-calendar.html
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageSchoolScheduleFixedChainPreviewFeatureTest extends CrmTestCase
{
    private const WEEK_MONDAY = '2026-05-04';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->assertSame(
            1,
            (int) CarbonImmutable::parse(self::WEEK_MONDAY)->format('N'),
            'Тестовая дата должна быть понедельником (ISO weekday 1).'
        );
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

    private function studentUser(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'name' => 'Превью',
            'lastname' => 'Фикс',
            'is_enabled' => 1,
        ]);
    }

    /**
     * @return array{student: User, ulp: UserLessonPackage, mon: TeamScheduleSlot, wed: TeamScheduleSlot}
     */
    private function previewContext(int $lessonsTotal = 8, int $durationDays = 30): array
    {
        $student = $this->studentUser();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $location = Location::factory()->forPartner($this->partner->id)->create();

        $mon = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $wed = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 3,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс превью',
            'schedule_type' => 'fixed',
            'duration_days' => $durationDays,
            'lessons_count' => $lessonsTotal,
            'price_cents' => 500000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => $lessonsTotal,
            'lessons_remaining' => $lessonsTotal,
            'fee_amount_cents' => 5000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        return compact('student', 'ulp', 'mon', 'wed');
    }

    /**
     * @return array<string, mixed>
     */
    private function previewPayload(User $student, UserLessonPackage $ulp, TeamScheduleSlot $anchor, array $patterns): array
    {
        return [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $anchor->id,
            'anchor_date' => self::WEEK_MONDAY,
            'patterns' => $patterns,
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function previewSurfaceRoutes(): array
    {
        return [
            ['GET', route('admin.lesson-packages.school-schedule'), []],
            ['POST', route('admin.lesson-packages.school-schedule.assign-fixed-preview'), [
                'user_id' => 1,
                'user_lesson_package_id' => 1,
                'team_schedule_slot_id' => 1,
                'anchor_date' => self::WEEK_MONDAY,
                'patterns' => [
                    ['weekday' => 1, 'time_start' => '10:00', 'time_end' => '11:00'],
                ],
            ]],
        ];
    }

    public function test_school_schedule_page_contains_fixed_chain_preview_ui_markers(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('schoolCalFixedChainPreview', false)
            ->assertSee('schoolCalFixedChainPreviewTitle', false)
            ->assertSee('schoolCalFixedChainPreviewHint', false)
            ->assertSee('schoolCalFixedChainPreviewList', false)
            ->assertSee('fixedAssignPreview', false)
            ->assertSee('scheduleSchoolCalFixedChainPreview', false)
            ->assertSee('refreshSchoolCalFixedChainPreview', false)
            ->assertSee('hideSchoolCalFixedChainPreview', false)
            ->assertSee('patternsReadyForFixedPreview', false);
    }

    public function test_guest_is_denied_on_preview_surface_endpoints(): void
    {
        Auth::logout();

        foreach ($this->previewSurfaceRoutes() as [$method, $url, $data]) {
            $response = $this->call($method, $url, $data, [], [], ['HTTP_ACCEPT' => 'application/json']);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "{$method} {$url} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_without_permission_gets_403_on_preview_surface_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.lesson-packages.school-schedule'))->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed-preview'), [
            'user_id' => 1,
            'user_lesson_package_id' => 1,
            'team_schedule_slot_id' => 1,
            'anchor_date' => self::WEEK_MONDAY,
            'patterns' => [
                ['weekday' => 1, 'time_start' => '10:00', 'time_end' => '11:00'],
            ],
        ])->assertForbidden();
    }

    public function test_viewer_with_permission_gets_200_on_page_and_preview_not_empty(): void
    {
        $this->grantPermission('lessonPackages.view');

        $ctx = $this->previewContext(2, 30);

        $page = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('schoolCalOpenFixed', false);
        $this->assertNotSame('', (string) $page->getContent());

        $preview = $this->postJson(
            route('admin.lesson-packages.school-schedule.assign-fixed-preview'),
            $this->previewPayload($ctx['student'], $ctx['ulp'], $ctx['mon'], [
                ['weekday' => 1, 'time_start' => '10:00', 'time_end' => '11:00'],
                ['weekday' => 3, 'time_start' => '10:00', 'time_end' => '11:00'],
            ])
        )
            ->assertOk()
            ->assertJsonStructure([
                'lessons_needed',
                'found',
                'complete',
                'title',
                'items' => [
                    ['date', 'label', 'time_start', 'time_end'],
                ],
            ]);

        $this->assertNotSame('', (string) $preview->getContent());
        $this->assertNotSame(500, $preview->getStatusCode());
    }

    public function test_preview_ajax_returns_chain_items_with_year_and_does_not_write_db(): void
    {
        $this->grantPermission('lessonPackages.view');

        $ctx = $this->previewContext(4, 30);

        $res = $this->postJson(
            route('admin.lesson-packages.school-schedule.assign-fixed-preview'),
            $this->previewPayload($ctx['student'], $ctx['ulp'], $ctx['mon'], [
                ['weekday' => 1, 'time_start' => '10:00', 'time_end' => '11:00'],
                ['weekday' => 3, 'time_start' => '10:00', 'time_end' => '11:00'],
            ])
        )
            ->assertOk()
            ->assertJsonStructure([
                'lessons_needed',
                'found',
                'complete',
                'title',
                'hint',
                'items' => [
                    ['date', 'label', 'time_start', 'time_end'],
                ],
            ])
            ->assertJsonPath('lessons_needed', 4)
            ->assertJsonPath('found', 4)
            ->assertJsonPath('complete', true)
            ->assertJsonPath('hint', null)
            ->assertJsonPath('title', 'Распределение 4 занятия абонемента:')
            ->assertJsonPath('items.0.date', self::WEEK_MONDAY)
            ->assertJsonPath('items.0.time_start', '10:00')
            ->assertJsonPath('items.0.time_end', '11:00')
            ->assertJsonPath('items.0.label', '4 мая 2026 10:00-11:00 (понедельник)')
            ->assertJsonPath('items.1.label', '6 мая 2026 10:00-11:00 (среда)');

        $this->assertNotSame('', (string) $res->getContent());
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ctx['ulp']->id)->count());
        $ctx['ulp']->refresh();
        $this->assertNull($ctx['ulp']->starts_at);
        $this->assertNull($ctx['ulp']->ends_at);
    }

    public function test_preview_ajax_partial_chain_returns_found_hint(): void
    {
        $this->grantPermission('lessonPackages.view');

        // Короткий период: 7 дней от якоря — при 2 слотах/нед максимум ~3 вхождения, нужно 8.
        $ctx = $this->previewContext(8, 7);

        $res = $this->postJson(
            route('admin.lesson-packages.school-schedule.assign-fixed-preview'),
            $this->previewPayload($ctx['student'], $ctx['ulp'], $ctx['mon'], [
                ['weekday' => 1, 'time_start' => '10:00', 'time_end' => '11:00'],
                ['weekday' => 3, 'time_start' => '10:00', 'time_end' => '11:00'],
            ])
        )
            ->assertOk()
            ->assertJsonPath('lessons_needed', 8)
            ->assertJsonPath('complete', false)
            ->assertJsonPath('title', 'Распределение 8 занятий абонемента:');

        $found = (int) $res->json('found');
        $hint = (string) $res->json('hint');
        $this->assertGreaterThan(0, $found);
        $this->assertLessThan(8, $found);
        $this->assertSame('найдено '.$found.' из 8', $hint);
        $this->assertSame(0, UserTeamScheduleSlot::query()->count());
    }

    public function test_preview_ajax_returns_422_when_patterns_miss_anchor_slot(): void
    {
        $this->grantPermission('lessonPackages.view');

        $ctx = $this->previewContext(2, 30);

        $this->postJson(
            route('admin.lesson-packages.school-schedule.assign-fixed-preview'),
            $this->previewPayload($ctx['student'], $ctx['ulp'], $ctx['mon'], [
                // Только среда — якорь понедельник не покрыт.
                ['weekday' => 3, 'time_start' => '10:00', 'time_end' => '11:00'],
            ])
        )
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonPath(
                'errors.patterns.0',
                'Добавьте в шаблон строку с днём недели и временем слота, с которого открыто окно.'
            );

        $this->assertSame(0, UserTeamScheduleSlot::query()->count());
    }

    public function test_preview_ajax_returns_422_when_assignment_already_has_period(): void
    {
        $this->grantPermission('lessonPackages.view');

        $ctx = $this->previewContext(2, 30);
        $ctx['ulp']->update([
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
        ]);

        $this->postJson(
            route('admin.lesson-packages.school-schedule.assign-fixed-preview'),
            $this->previewPayload($ctx['student'], $ctx['ulp'], $ctx['mon'], [
                ['weekday' => 1, 'time_start' => '10:00', 'time_end' => '11:00'],
            ])
        )
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonPath('message', 'У этого назначения уже задан период действия.');

        $this->assertSame(0, UserTeamScheduleSlot::query()->count());
    }

    public function test_preview_ajax_validation_returns_422_with_errors(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed-preview'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'user_id',
                'user_lesson_package_id',
                'team_schedule_slot_id',
                'anchor_date',
                'patterns',
            ]);
    }

    public function test_preview_non_ajax_success_returns_json_without_db_write_not_empty_200(): void
    {
        $this->grantPermission('lessonPackages.view');

        $ctx = $this->previewContext(2, 30);

        // Preview — read-only API: non-AJAX всё равно отдаёт JSON (не пустой 200), БД не трогает.
        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(
                route('admin.lesson-packages.school-schedule.assign-fixed-preview'),
                $this->previewPayload($ctx['student'], $ctx['ulp'], $ctx['mon'], [
                    ['weekday' => 1, 'time_start' => '10:00', 'time_end' => '11:00'],
                    ['weekday' => 3, 'time_start' => '10:00', 'time_end' => '11:00'],
                ])
            );

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotSame('', (string) $response->getContent());
        $response->assertJsonStructure([
            'lessons_needed',
            'found',
            'complete',
            'title',
            'items',
        ]);
        $response->assertJsonPath('complete', true);
        $response->assertJsonPath('items.0.label', '4 мая 2026 10:00-11:00 (понедельник)');

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ctx['ulp']->id)->count());
        $ctx['ulp']->refresh();
        $this->assertNull($ctx['ulp']->starts_at);
        $this->assertNull($ctx['ulp']->ends_at);
    }

    public function test_preview_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(route('admin.lesson-packages.school-schedule.assign-fixed-preview'), [
                '_token' => csrf_token(),
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors([
                'user_id',
                'user_lesson_package_id',
                'team_schedule_slot_id',
                'anchor_date',
                'patterns',
            ]);

        $this->assertSame(0, UserTeamScheduleSlot::query()->count());
    }

    public function test_preview_non_ajax_business_error_returns_json_422_not_empty_200(): void
    {
        $this->grantPermission('lessonPackages.view');

        $ctx = $this->previewContext(2, 30);

        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(
                route('admin.lesson-packages.school-schedule.assign-fixed-preview'),
                $this->previewPayload($ctx['student'], $ctx['ulp'], $ctx['mon'], [
                    ['weekday' => 3, 'time_start' => '10:00', 'time_end' => '11:00'],
                ])
            );

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(422, $response->getStatusCode());
        $this->assertNotSame('', (string) $response->getContent());
        $response->assertJsonStructure(['message', 'errors']);
        $this->assertSame(0, UserTeamScheduleSlot::query()->count());
    }
}
