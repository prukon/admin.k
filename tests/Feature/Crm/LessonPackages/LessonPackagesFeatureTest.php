<?php

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\LessonPackageTimeSlot;
use App\Models\UserLessonPackage;
use App\Models\UserLessonPackageFreeze;
use App\Models\UserLessonPackageTimeSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Tests\Feature\Crm\CrmTestCase;

final class LessonPackagesFeatureTest extends CrmTestCase
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

    public function test_index_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('lessonPackages.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.lesson-packages.index'))
            ->assertStatus(403);
    }

    public function test_index_ok_with_view_permission(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertSee('Абонементы');
    }

    public function test_index_ui_hides_manage_controls_for_view_only(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertDontSee('Добавить абонемент')
            ->assertDontSee('Редактировать');
    }

    public function test_index_ui_shows_modals_and_controls_for_manage(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manage');

        LessonPackage::query()->create([
            'name' => 'Пакет',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertSee('Добавить абонемент')
            ->assertSee('Редактировать')
            ->assertSee('lessonPackageCreateModal')
            ->assertSee('lessonPackageEditModal');
    }

    public function test_store_denied_without_manage_permission(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Тест',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
            'time_slots' => [
                ['weekday' => 1, 'time_start' => '18:00', 'time_end' => '19:00'],
            ],
        ])->assertStatus(403);
    }

    public function test_store_fixed_creates_package_and_slots(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manage');

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.50',
            'freeze_enabled' => 1,
            'freeze_days' => 7,
            'time_slots' => [
                ['weekday' => 1, 'time_start' => '18:00', 'time_end' => '19:00'],
                ['weekday' => 3, 'time_start' => '17:00', 'time_end' => '18:00'],
            ],
        ])->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'name' => 'Фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 150050,
            'freeze_enabled' => 1,
            'freeze_days' => 7,
        ]);

        $packageId = (int) LessonPackage::query()->where('name', 'Фикс')->value('id');
        $this->assertGreaterThan(0, $packageId);

        $this->assertDatabaseHas('lesson_package_time_slots', [
            'lesson_package_id' => $packageId,
            'weekday' => 1,
            'time_start' => '18:00:00',
            'time_end' => '19:00:00',
        ]);
        $this->assertDatabaseHas('lesson_package_time_slots', [
            'lesson_package_id' => $packageId,
            'weekday' => 3,
            'time_start' => '17:00:00',
            'time_end' => '18:00:00',
        ]);
    }

    public function test_store_flexible_creates_package_without_slots(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manage');

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Гибкий',
            'schedule_type' => 'flexible',
            'duration_days' => 60,
            'lessons_count' => 12,
            'price' => '2000',
            'freeze_enabled' => 0,
        ])->assertOk()
            ->assertJson(['success' => true]);

        $packageId = (int) LessonPackage::query()->where('name', 'Гибкий')->value('id');
        $this->assertGreaterThan(0, $packageId);

        $this->assertDatabaseHas('lesson_packages', [
            'id' => $packageId,
            'schedule_type' => 'flexible',
        ]);
        $this->assertDatabaseMissing('lesson_package_time_slots', [
            'lesson_package_id' => $packageId,
        ]);
    }

    public function test_store_fixed_validation_requires_slots(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manage');

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Без слотов',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
            'time_slots' => [],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['time_slots']);
    }

    public function test_store_freeze_enabled_requires_freeze_days(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manage');

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Заморозка',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price' => '100.00',
            'freeze_enabled' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['freeze_days']);
    }

    public function test_show_returns_json_with_slots(): void
    {
        $this->grantPermission('lessonPackages.view');

        $lp = LessonPackage::query()->create([
            'name' => 'Пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        LessonPackageTimeSlot::query()->create([
            'lesson_package_id' => $lp->id,
            'weekday' => 1,
            'time_start' => '18:00',
            'time_end' => '19:00',
        ]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $lp->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.id', (int) $lp->id)
            ->assertJsonPath('lesson_package.schedule_type', 'fixed')
            ->assertJsonPath('lesson_package.time_slots.0.weekday', 1)
            ->assertJsonPath('lesson_package.time_slots.0.time_start', '18:00')
            ->assertJsonPath('lesson_package.time_slots.0.time_end', '19:00');
    }

    public function test_update_denied_without_manage_permission(): void
    {
        $this->grantPermission('lessonPackages.view');

        $lp = LessonPackage::query()->create([
            'name' => 'Пакет',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $lp->id]), [
            'name' => 'Пакет 2',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price' => '100.00',
            'freeze_enabled' => 0,
        ])->assertStatus(403);
    }

    public function test_update_rebuilds_slots_for_fixed_package(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manage');

        $lp = LessonPackage::query()->create([
            'name' => 'Пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        LessonPackageTimeSlot::query()->create([
            'lesson_package_id' => $lp->id,
            'weekday' => 1,
            'time_start' => '18:00',
            'time_end' => '19:00',
        ]);
        LessonPackageTimeSlot::query()->create([
            'lesson_package_id' => $lp->id,
            'weekday' => 3,
            'time_start' => '17:00',
            'time_end' => '18:00',
        ]);

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $lp->id]), [
            'name' => 'Пакет (обновлён)',
            'schedule_type' => 'fixed',
            'duration_days' => 60,
            'lessons_count' => 12,
            'price' => '2500.00',
            'freeze_enabled' => 1,
            'freeze_days' => 14,
            'time_slots' => [
                ['weekday' => 5, 'time_start' => '16:30', 'time_end' => '17:30'],
            ],
        ])->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'id' => $lp->id,
            'name' => 'Пакет (обновлён)',
            'duration_days' => 60,
            'lessons_count' => 12,
            'price_cents' => 250000,
            'freeze_enabled' => 1,
            'freeze_days' => 14,
        ]);

        $this->assertDatabaseMissing('lesson_package_time_slots', [
            'lesson_package_id' => $lp->id,
            'weekday' => 1,
            'time_start' => '18:00:00',
            'time_end' => '19:00:00',
        ]);
        $this->assertDatabaseMissing('lesson_package_time_slots', [
            'lesson_package_id' => $lp->id,
            'weekday' => 3,
            'time_start' => '17:00:00',
            'time_end' => '18:00:00',
        ]);
        $this->assertDatabaseHas('lesson_package_time_slots', [
            'lesson_package_id' => $lp->id,
            'weekday' => 5,
            'time_start' => '16:30:00',
            'time_end' => '17:30:00',
        ]);
    }

    public function test_store_duplicate_slot_returns_422_with_time_slots_error(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manage');

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Дубли',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
            'time_slots' => [
                ['weekday' => 1, 'time_start' => '18:00', 'time_end' => '19:00'],
                ['weekday' => 1, 'time_start' => '18:00', 'time_end' => '19:00'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['time_slots']);
    }

    public function test_store_time_end_must_be_after_time_start(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manage');

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Время',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
            'time_slots' => [
                ['weekday' => 1, 'time_start' => '19:00', 'time_end' => '18:00'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['time_slots.0.time_end']);
    }

    public function test_admin_role_has_access_to_lesson_packages_endpoints_by_default(): void
    {
        $this->asAdmin();

        // index (page) should be accessible
        $this->get(route('admin.lesson-packages.index'))->assertOk();

        // store should be accessible and return 200 JSON
        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Админ пакет',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price' => '100.00',
            'freeze_enabled' => 0,
        ])->assertOk()->assertJson(['success' => true]);

        $lpId = (int) LessonPackage::query()->where('name', 'Админ пакет')->value('id');
        $this->assertGreaterThan(0, $lpId);

        // show should be accessible
        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $lpId]))
            ->assertOk()
            ->assertJsonPath('lesson_package.id', $lpId);

        // update should be accessible
        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $lpId]), [
            'name' => 'Админ пакет 2',
            'schedule_type' => 'no_schedule',
            'duration_days' => 60,
            'lessons_count' => 3,
            'price' => '200.00',
            'freeze_enabled' => 1,
            'freeze_days' => 10,
        ])->assertOk()->assertJson(['success' => true]);
    }

    public function test_assignments_tab_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('lessonPackages.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.lesson-packages.assignments'))
            ->assertStatus(403);
    }

    public function test_assignments_tab_ok_with_view_permission(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('Назначение абонементов');
    }

    public function test_assignments_tab_hides_form_for_view_only(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertDontSee('Назначить');
    }

    public function test_assignments_tab_shows_form_for_manage(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manage');

        LessonPackage::query()->create([
            'name' => 'Пакет',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('Назначить')
            ->assertSee('ulp_user_id')
            ->assertSee('ulp_lesson_package_id');
    }

    public function test_store_assignment_creates_user_lesson_package_and_sets_remaining(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manage');

        $package = LessonPackage::query()->create([
            'name' => 'Пакет 8',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->post(route('admin.lesson-packages.assignments.store'), [
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-05-01',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
        ]);
    }

    public function test_store_assignment_flexible_saves_slots_when_provided(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manage');

        $package = LessonPackage::query()->create([
            'name' => 'Гибкий',
            'schedule_type' => 'flexible',
            'duration_days' => 60,
            'lessons_count' => 12,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->post(route('admin.lesson-packages.assignments.store'), [
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'time_slots' => [
                ['weekday' => 1, 'time_start' => '18:00', 'time_end' => '19:00'],
                ['weekday' => 3, 'time_start' => '17:00', 'time_end' => '18:00'],
            ],
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        $ulpId = (int) UserLessonPackage::query()->where('lesson_package_id', $package->id)->value('id');
        $this->assertGreaterThan(0, $ulpId);

        $this->assertDatabaseHas('user_lesson_package_time_slots', [
            'user_lesson_package_id' => $ulpId,
            'weekday' => 1,
            'time_start' => '18:00:00',
            'time_end' => '19:00:00',
        ]);
        $this->assertDatabaseHas('user_lesson_package_time_slots', [
            'user_lesson_package_id' => $ulpId,
            'weekday' => 3,
            'time_start' => '17:00:00',
            'time_end' => '18:00:00',
        ]);
    }

    public function test_store_assignment_fixed_ignores_slots_payload(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manage');

        $package = LessonPackage::query()->create([
            'name' => 'Фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->post(route('admin.lesson-packages.assignments.store'), [
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'time_slots' => [
                ['weekday' => 1, 'time_start' => '18:00', 'time_end' => '19:00'],
            ],
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        $ulpId = (int) UserLessonPackage::query()->where('lesson_package_id', $package->id)->value('id');
        $this->assertGreaterThan(0, $ulpId);

        $this->assertDatabaseMissing('user_lesson_package_time_slots', [
            'user_lesson_package_id' => $ulpId,
        ]);
    }

    public function test_assignments_users_search_returns_only_current_partner_users(): void
    {
        $this->grantPermission('lessonPackages.view');

        $response = $this->getJson(route('admin.lesson-packages.assignments.users-search', ['q' => $this->user->lastname]));
        $response->assertOk();

        $json = $response->json();
        $this->assertIsArray($json['results'] ?? null);

        $ids = array_map(fn ($r) => (int) ($r['id'] ?? 0), $json['results'] ?? []);
        $this->assertContains((int) $this->user->id, $ids);
        $this->assertNotContains((int) $this->foreignUser->id, $ids);
    }

    public function test_store_assignment_denied_without_manage_permission(): void
    {
        $this->grantPermission('lessonPackages.view');

        $package = LessonPackage::query()->create([
            'name' => 'Пакет 8',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->post(route('admin.lesson-packages.assignments.store'), [
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
        ])->assertStatus(403);
    }

    public function test_admin_role_has_access_to_assignments_tab_and_endpoints_with_200_final_response(): void
    {
        $this->asAdmin();

        // вкладка (page)
        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('Назначение абонементов');

        // users-search (JSON)
        $this->getJson(route('admin.lesson-packages.assignments.users-search', ['q' => '']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        // store: web-форма делает redirect → проверяем финальный 200 через followRedirects
        $package = LessonPackage::query()->create([
            'name' => 'Админ пакет 8',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->post(route('admin.lesson-packages.assignments.store'), [
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        // финальная страница после redirect должна открываться (200)
        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('Абонемент назначен ученику');
    }

    public function test_can_create_freeze_for_fixed_slot_and_prevent_duplicate(): void
    {
        $lp = LessonPackage::query()->create([
            'name' => 'Фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 1,
            'freeze_days' => 7,
            'is_active' => 1,
        ]);

        $slot = LessonPackageTimeSlot::query()->create([
            'lesson_package_id' => $lp->id,
            'weekday' => 1,
            'time_start' => '18:00',
            'time_end' => '19:00',
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'lesson_package_id' => $lp->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-05-01',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'created_by' => $this->user->id,
        ]);

        UserLessonPackageFreeze::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'date' => '2026-04-07',
            'lesson_package_time_slot_id' => $slot->id,
            'created_by' => $this->user->id,
            'reason' => 'test',
        ]);

        $this->assertDatabaseHas('user_lesson_package_freezes', [
            'user_lesson_package_id' => $ulp->id,
            'date' => '2026-04-07',
            'lesson_package_time_slot_id' => $slot->id,
        ]);

        $this->expectException(QueryException::class);
        UserLessonPackageFreeze::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'date' => '2026-04-07',
            'lesson_package_time_slot_id' => $slot->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_can_create_freeze_for_flexible_slot_and_prevent_duplicate(): void
    {
        $lp = LessonPackage::query()->create([
            'name' => 'Гибкий',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 1,
            'freeze_days' => 7,
            'is_active' => 1,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'lesson_package_id' => $lp->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-05-01',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'created_by' => $this->user->id,
        ]);

        $slot = UserLessonPackageTimeSlot::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'weekday' => 2,
            'time_start' => '18:00',
            'time_end' => '19:00',
        ]);

        UserLessonPackageFreeze::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'date' => '2026-04-08',
            'user_lesson_package_time_slot_id' => $slot->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('user_lesson_package_freezes', [
            'user_lesson_package_id' => $ulp->id,
            'date' => '2026-04-08',
            'user_lesson_package_time_slot_id' => $slot->id,
        ]);

        $this->expectException(QueryException::class);
        UserLessonPackageFreeze::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'date' => '2026-04-08',
            'user_lesson_package_time_slot_id' => $slot->id,
            'created_by' => $this->user->id,
        ]);
    }
}

