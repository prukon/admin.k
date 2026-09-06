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
 * [P2] UX: страница → AJAX create/show → строка и тип видны без F5;
 * edit JSON отдаёт schedule_type, даже если option в HTML скрыт.
 */
final class LessonPackageTypePermissionWorkflowFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
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

    public function test_directories_ajax_create_granted_type_appears_in_datatable_without_reload(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['no_schedule']);
        $this->withoutVite();

        $unique = 'WF-single-'.uniqid('', true);

        $page = $this->get(route('admin.directories.lesson-packages.index'));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('lessonPackageCreateModal', $html);
        $this->assertStringContainsString('title="Добавить абонемент"', $html);
        $this->assertMatchesRegularExpression(
            '/<option value="no_schedule"[^>]*>\s*Разовое занятие\s*<\/option>/u',
            $html
        );
        $this->assertStringNotContainsString('<option value="fixed">Фиксированный</option>', $html);
        $this->assertStringContainsString('reloadPackagesTable', $html);

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => $unique,
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price' => '500.00',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $package = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', $unique)
            ->firstOrFail();

        $afterCreate = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
            'name' => $unique,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json();

        $row = collect($afterCreate['data'] ?? [])->firstWhere('id', $package->id);
        $this->assertIsArray($row, 'Созданный абонемент должен появиться в DataTables без F5.');
        $this->assertSame($unique, $row['name']);
        $this->assertSame('no_schedule', $row['schedule_type']);
        $this->assertSame('Разовое занятие', $row['schedule_type_label']);
    }

    public function test_edit_show_returns_hidden_type_so_js_can_inject_option_without_page_reload(): void
    {
        $this->grantLessonPackageTypePermissions($this->user, ['fixed']);
        $this->withoutVite();

        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'WF flexible existing',
            'schedule_type' => 'flexible',
            'price_cents' => 20000,
            'lessons_count' => 8,
        ]);

        $page = $this->get(route('admin.lesson-packages.index'));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertStringNotContainsString('<option value="flexible">Предоплата</option>', $html);
        $this->assertStringContainsString('lesson-package-edit-btn', $html);
        $this->assertStringContainsString('scheduleSelect.appendChild(opt)', $html);

        $this->getJson(route('admin.lesson-packages.show', $package), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('lesson_package.schedule_type', 'flexible')
            ->assertJsonPath('lesson_package.name', 'WF flexible existing');
    }

    public function test_setting_prices_catalog_keeps_assigned_type_after_get_team_price_without_permission(): void
    {
        $this->grantPermission('setPrices.view');
        $this->withoutVite();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);

        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'WF assigned flexible',
            'schedule_type' => 'flexible',
            'price_cents' => 11100,
        ]);

        UserPrice::query()->create([
            'user_id' => $student->id,
            'team_id' => $team->id,
            'new_month' => '2026-08-01',
            'price_cents' => 11100,
            'is_paid' => false,
            'lesson_package_id' => $package->id,
        ]);

        $page = $this->get(route('admin.settingPrices.indexMenu'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));

        $json = $this->postJson(route('getTeamPrice'), [
            'teamId' => $team->id,
            'selectedDate' => 'Август 2026',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $ids = collect($json['lessonPackages'] ?? [])->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $this->assertContains((int) $package->id, $ids, 'Уже назначенный тип должен остаться в select, иначе поле опустеет.');
    }
}
