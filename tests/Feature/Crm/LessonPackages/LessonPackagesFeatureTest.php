<?php

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
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

    public function test_index_ui_shows_modals_and_controls_with_view_permission(): void
    {
        $this->grantPermission('lessonPackages.view');

        LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
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
            ->assertSee('Изменить')
            ->assertSee('lessonPackageCreateModal')
            ->assertSee('lessonPackageEditModal');
    }

    public function test_store_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('lessonPackages.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Тест',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
        ])->assertStatus(403);
    }

    public function test_store_fixed_creates_package_without_template_slots(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.50',
            'freeze_enabled' => 1,
            'freeze_days' => 7,
        ])->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 150050,
            'freeze_enabled' => 1,
            'freeze_days' => 7,
        ]);
    }

    public function test_store_flexible_creates_package_without_slots(): void
    {
        $this->grantPermission('lessonPackages.view');
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
            'partner_id' => $this->partner->id,
            'schedule_type' => 'flexible',
        ]);
    }

    public function test_store_freeze_enabled_requires_freeze_days(): void
    {
        $this->grantPermission('lessonPackages.view');
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

    public function test_show_returns_json_with_empty_time_slots_for_fixed(): void
    {
        $this->grantPermission('lessonPackages.view');

        $lp = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $lp->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.id', (int) $lp->id)
            ->assertJsonPath('lesson_package.schedule_type', 'fixed')
            ->assertJsonPath('lesson_package.time_slots', []);
    }

    public function test_update_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('lessonPackages.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $lp = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
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

    public function test_update_fixed_package_without_template_slots(): void
    {
        $this->grantPermission('lessonPackages.view');
        $lp = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $lp->id]), [
            'name' => 'Пакет (обновлён)',
            'schedule_type' => 'fixed',
            'duration_days' => 60,
            'lessons_count' => 12,
            'price' => '2500.00',
            'freeze_enabled' => 1,
            'freeze_days' => 14,
        ])->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'id' => $lp->id,
            'partner_id' => $this->partner->id,
            'name' => 'Пакет (обновлён)',
            'duration_days' => 60,
            'lessons_count' => 12,
            'price_cents' => 250000,
            'freeze_enabled' => 1,
            'freeze_days' => 14,
        ]);
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
            'duration_days' => 1,
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
            'duration_days' => 1,
            'lessons_count' => 1,
            'price' => '200.00',
            'freeze_enabled' => 0,
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

    public function test_assignments_datatable_data_returns_server_side_json(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data',
            ]);
    }

    public function test_assignments_tab_ok_with_view_permission(): void
    {
        $this->grantPermission('lessonPackages.view');

        LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
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
            ->assertSee('Назначение абонементов')
            ->assertSee('Назначить')
            ->assertSee('ulp_user_id')
            ->assertSee('ulp_lesson_package_id')
            ->assertSee('ulp-assignments-table')
            ->assertSee('ulpAssignmentsFiltersCollapse', false)
            ->assertSee('columnsDropdownUlpAssignments', false)
            ->assertSee('KidsCrmDataTable.create', false);
    }

    public function test_assignments_columns_settings_save_and_get(): void
    {
        $this->grantPermission('lessonPackages.view');

        $payload = [
            'columns' => [
                'student' => true,
                'package_name' => false,
                'actions' => true,
            ],
        ];

        $this->postJson(route('admin.lesson-packages.assignments.columns-settings.save'), $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_table_settings', [
            'user_id' => $this->user->id,
            'table_key' => 'lesson_packages_assignments',
        ]);

        $this->getJson(route('admin.lesson-packages.assignments.columns-settings.get'))
            ->assertOk()
            ->assertJson([
                'student' => true,
                'package_name' => false,
                'actions' => true,
            ]);
    }

    public function test_assignments_data_applies_list_filters(): void
    {
        $this->grantPermission('lessonPackages.view');

        $studentA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванов',
            'name' => 'А',
        ]);
        $studentB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Петров',
            'name' => 'Б',
        ]);

        $fixedPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $flexPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий пакет',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $matchId = (int) UserLessonPackage::query()->insertGetId([
            'user_id' => $studentA->id,
            'lesson_package_id' => $fixedPackage->id,
            'lessons_total' => 8,
            'lessons_remaining' => 3,
            'fee_amount' => '100.00',
            'is_paid' => 0,
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        UserLessonPackage::query()->insert([
            'user_id' => $studentB->id,
            'lesson_package_id' => $flexPackage->id,
            'lessons_total' => 8,
            'lessons_remaining' => 0,
            'fee_amount' => '200.00',
            'is_paid' => 1,
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $base = [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ];

        $all = $this->getJson(route('admin.lesson-packages.assignments.data', $base))
            ->assertOk()
            ->json();
        $this->assertSame(2, (int) ($all['recordsFiltered'] ?? 0));

        $byUser = $this->getJson(route('admin.lesson-packages.assignments.data', $base + [
            'filter_user_id' => $studentA->id,
        ]))->assertOk()->json();
        $this->assertSame(1, (int) ($byUser['recordsFiltered'] ?? 0));
        $this->assertSame($matchId, (int) ($byUser['data'][0]['id'] ?? 0));

        $byType = $this->getJson(route('admin.lesson-packages.assignments.data', $base + [
            'filter_schedule_type' => 'fixed',
        ]))->assertOk()->json();
        $this->assertSame(1, (int) ($byType['recordsFiltered'] ?? 0));

        $byPaid = $this->getJson(route('admin.lesson-packages.assignments.data', $base + [
            'filter_payment_status' => 'unpaid',
        ]))->assertOk()->json();
        $this->assertSame(1, (int) ($byPaid['recordsFiltered'] ?? 0));

        $byBalance = $this->getJson(route('admin.lesson-packages.assignments.data', $base + [
            'filter_lessons_remaining' => 'has',
        ]))->assertOk()->json();
        $this->assertSame(1, (int) ($byBalance['recordsFiltered'] ?? 0));
    }

    public function test_store_assignment_creates_user_lesson_package_and_sets_remaining(): void
    {
        $this->grantPermission('lessonPackages.view');
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
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
            'fee_amount' => '100.00',
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => '100.00',
            'is_paid' => 0,
        ]);
    }

    public function test_store_assignment_flexible_creates_without_assignment_time_slots(): void
    {
        $this->grantPermission('lessonPackages.view');
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
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
            'fee_amount' => '100.00',
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        $ulpId = (int) UserLessonPackage::query()->where('lesson_package_id', $package->id)->value('id');
        $this->assertGreaterThan(0, $ulpId);

        $this->assertDatabaseMissing('user_lesson_package_time_slots', [
            'user_lesson_package_id' => $ulpId,
        ]);
    }

    public function test_store_assignment_fixed_ignores_slots_payload(): void
    {
        $this->grantPermission('lessonPackages.view');
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
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
            'fee_amount' => '100.00',
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

    public function test_store_assignment_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('lessonPackages.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
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
            'fee_amount' => '100.00',
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
            'partner_id' => $this->partner->id,
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
            'fee_amount' => '100.00',
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        // финальная страница после redirect должна открываться (200)
        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('Абонемент назначен ученику');
    }

    public function test_can_create_freeze_for_fixed_slot_and_prevent_duplicate(): void
    {
        $lp = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 1,
            'freeze_days' => 7,
            'is_active' => 1,
        ]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $tss = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '18:00',
            'time_end' => '19:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
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
            'team_schedule_slot_id' => $tss->id,
            'created_by' => $this->user->id,
            'reason' => 'test',
        ]);

        $this->assertDatabaseHas('user_lesson_package_freezes', [
            'user_lesson_package_id' => $ulp->id,
            'date' => '2026-04-07',
            'team_schedule_slot_id' => $tss->id,
        ]);

        $this->expectException(QueryException::class);
        UserLessonPackageFreeze::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'date' => '2026-04-07',
            'team_schedule_slot_id' => $tss->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_can_create_freeze_for_flexible_slot_and_prevent_duplicate(): void
    {
        $lp = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
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

    public function test_destroy_removes_package_when_no_assignments(): void
    {
        $this->grantPermission('lessonPackages.view');

        $lp = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'На удаление',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 100,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->deleteJson(route('admin.lesson-packages.destroy', ['lessonPackage' => $lp->id]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('lesson_packages', ['id' => $lp->id]);
    }

    public function test_destroy_denied_when_partner_assignment_exists(): void
    {
        $this->grantPermission('lessonPackages.view');

        $lp = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'С назначением',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'lesson_package_id' => $lp->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-05-01',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => '100.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->deleteJson(route('admin.lesson-packages.destroy', ['lessonPackage' => $lp->id]))
            ->assertStatus(422);

        $this->assertDatabaseHas('lesson_packages', ['id' => $lp->id]);
    }

    public function test_show_returns_404_for_foreign_partner_package(): void
    {
        $this->grantPermission('lessonPackages.view');

        $lp = LessonPackage::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой пакет',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 100,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $lp->id]))
            ->assertNotFound();
    }
}

