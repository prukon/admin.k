<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Directories;

use App\Models\LessonPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Функционал дубля «Абонементы» в разделе «Справочники».
 * Inline JS модалок — BladeInlineJsSyntaxTest (admin/lessonPackages/tabs/packages.blade.php).
 */
final class DirectoriesLessonPackagesFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    /** @param list<string> $permissionNames */
    private function createUserWithPermissions(array $permissionNames): User
    {
        $permissionNames = $this->withDirectoriesMenuPermission($permissionNames);

        $now = now();
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'test_dirs_lp_' . strtolower(\Illuminate\Support\Str::random(8)),
            'label' => 'Test Directories Lesson Packages',
            'is_sistem' => 0,
            'order_by' => 0,
            'is_visible' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($permissionNames as $permissionName) {
            DB::table('permission_role')->insert([
                'partner_id' => $this->partner->id,
                'role_id' => $roleId,
                'permission_id' => $this->permissionId($permissionName),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
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
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dirs feature пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 1,
        ], $overrides);
    }

    public function test_page_renders_directories_shell_with_packages_partial_and_active_tab(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('groups.view');

        LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Пакет в справочниках',
            'schedule_type' => 'flexible',
            'duration_days' => 45,
            'lessons_count' => 10,
            'price_cents' => 250000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $response = $this->get(route('admin.directories.lesson-packages.index'));

        $response->assertOk()
            ->assertViewIs('admin.directories.lesson-packages')
            ->assertSee('Справочники', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee(route('admin.directories.lesson-packages.index'), false)
            ->assertSee('>Абонементы</a>', false)
            ->assertSee('Добавить абонемент', false)
            ->assertSee('lessonPackageCreateModal', false)
            ->assertSee('lessonPackageEditModal', false)
            ->assertSee('lessonPackageDeleteModal', false)
            ->assertSee('Пакет в справочниках', false);

        $html = (string) $response->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('<h4 class="pt-3 pb-3 text-start">Справочники</h4>', $html);
        $this->assertStringNotContainsString('<h4 class="pt-3 pb-3 text-start">Расписание школы</h4>', $html);

        if (! preg_match('/<ul[^>]*id="directoriesSectionTabs"[^>]*>(.*?)<\/ul>/s', $html, $matches)) {
            $this->fail('directoriesSectionTabs not found');
        }

        $tabs = $matches[1];
        $this->assertMatchesRegularExpression(
            '/class="[^"]*nav-link[^"]*\bactive\b[^"]*"[^>]*href="[^"]*directories\/lesson-packages/',
            $tabs,
            'Вкладка «Абонементы» должна быть active'
        );
        $this->assertLessThan(
            (int) strpos($tabs, '>Абонементы</a>'),
            (int) strpos($tabs, '>Группы</a>'),
            '«Абонементы» должны быть после «Группы»'
        );
    }

    public function test_teams_page_shows_lesson_packages_tab_when_permission_granted(): void
    {
        $actor = $this->createUserWithPermissions(['groups.view', 'lessonPackages.view']);
        $this->actingAs($actor);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee(route('admin.directories.lesson-packages.index'), false)
            ->assertSee('>Абонементы</a>', false);
    }

    public function test_teams_page_hides_lesson_packages_tab_without_permission(): void
    {
        $actor = $this->createUserWithPermissions(['groups.view']);
        $this->actingAs($actor);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('>Группы</a>', false)
            ->assertDontSee('>Абонементы</a>', false)
            ->assertDontSee(route('admin.directories.lesson-packages.index'), false);
    }

    public function test_ajax_store_returns_json_contract_and_persists(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->postJson(route('admin.lesson-packages.store'), $this->validPayload([
            'name' => 'Dirs AJAX create',
        ]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Dirs AJAX create',
            'schedule_type' => 'fixed',
            'auto_attendance_enabled' => 1,
        ]);
    }

    public function test_ajax_store_validation_failure_returns_422_with_errors_not_empty_200(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->postJson(route('admin.lesson-packages.store'), [
            'schedule_type' => 'fixed',
            'auto_attendance_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => '',
        ]);
    }

    public function test_ajax_update_and_show_return_json_contract(): void
    {
        $this->grantPermission('lessonPackages.view');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Dirs AJAX before',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->validPayload([
            'name' => 'Dirs AJAX after',
            'schedule_type' => 'flexible',
            'auto_attendance_enabled' => 1,
        ]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertOk()
            ->assertJsonPath('lesson_package.name', 'Dirs AJAX after')
            ->assertJsonPath('lesson_package.auto_attendance_enabled', true);

        $package->refresh();
        $this->assertSame('Dirs AJAX after', $package->name);
        $this->assertTrue((bool) $package->auto_attendance_enabled);
    }

    public function test_ajax_update_validation_failure_returns_422_with_field_errors(): void
    {
        $this->grantPermission('lessonPackages.view');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Dirs AJAX validation',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
            'name' => '',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertSame('Dirs AJAX validation', $package->fresh()->name);
    }

    public function test_ajax_destroy_returns_json_and_deletes_unused_package(): void
    {
        $this->grantPermission('lessonPackages.view');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Dirs AJAX delete',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 5000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->deleteJson(route('admin.lesson-packages.destroy', ['lessonPackage' => $package->id]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('lesson_packages', ['id' => $package->id]);
    }

    public function test_old_school_schedule_packages_page_still_available(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertViewIs('admin.lessonPackages.index')
            ->assertSee('Расписание школы', false)
            ->assertSee('Абонементы', false);
    }
}
