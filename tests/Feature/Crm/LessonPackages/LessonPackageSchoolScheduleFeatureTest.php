<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class LessonPackageSchoolScheduleFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
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

    public function test_school_schedule_denied_without_lesson_packages_view(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.lesson-packages.school-schedule'))->assertStatus(403);
    }

    public function test_school_schedule_ok_and_week_json(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('Расписание школы', false);

        $res = $this->getJson(route('admin.lesson-packages.school-schedule.week', ['week' => '2026-05-04']))
            ->assertOk()
            ->assertJsonStructure([
                'week_start',
                'occurrences',
            ]);

        $json = $res->json();
        $this->assertIsArray($json['occurrences']);
        foreach ($json['occurrences'] as $occurrence) {
            $this->assertArrayHasKey('registrations', $occurrence);
            $this->assertIsArray($occurrence['registrations']);
        }
    }

    public function test_flexible_users_search_denied_without_lesson_packages_view(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-users-search'))
            ->assertForbidden();
    }

    public function test_flexible_users_search_only_flexible_with_remaining_and_joins_labels(): void
    {
        $this->grantPermission('lessonPackages.view');

        $roleId = $this->roleId('user');

        $studentOk = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
            'name' => 'Петя',
            'lastname' => 'Иванов',
            'is_enabled' => 1,
        ]);

        $studentFixedOnly = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
            'name' => 'Олег',
            'lastname' => 'Фиксов',
            'is_enabled' => 1,
        ]);

        $flexA = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий А',
            'schedule_type' => 'flexible',
            'duration_days' => 60,
            'lessons_count' => 10,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $flexB = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий Б',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 5,
            'price_cents' => 500,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $fixedPkg = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс только',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 2000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        UserLessonPackage::query()->create([
            'user_id' => $studentOk->id,
            'lesson_package_id' => $flexA->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 10,
            'lessons_remaining' => 4,
            'created_by' => $this->user->id,
        ]);

        UserLessonPackage::query()->create([
            'user_id' => $studentOk->id,
            'lesson_package_id' => $flexB->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 5,
            'lessons_remaining' => 1,
            'created_by' => $this->user->id,
        ]);

        UserLessonPackage::query()->create([
            'user_id' => $studentFixedOnly->id,
            'lesson_package_id' => $fixedPkg->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'created_by' => $this->user->id,
        ]);

        $empty = $this->getJson(route('admin.lesson-packages.school-schedule.flexible-users-search', ['q' => '']))
            ->assertOk()
            ->json('results');

        $ids = array_column($empty, 'id');
        $this->assertContains($studentOk->id, $ids);
        $this->assertNotContains($studentFixedOnly->id, $ids);

        $row = collect($empty)->firstWhere('id', $studentOk->id);
        $this->assertIsArray($row);
        $this->assertStringContainsString('Иванов Петя', (string) $row['text']);
        $this->assertStringContainsString('Гибкий А — 4 з.', (string) $row['text']);
        $this->assertStringContainsString('Гибкий Б — 1 з.', (string) $row['text']);

        $filtered = $this->getJson(route('admin.lesson-packages.school-schedule.flexible-users-search', ['q' => 'Иванов']))
            ->assertOk()
            ->json('results');
        $this->assertCount(1, $filtered);
        $this->assertSame($studentOk->id, $filtered[0]['id']);
    }
}
