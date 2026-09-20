<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к полям «Для договора»: contracts.lessonPackage.bind / lessonPackages.view.
 */
final class LessonPackageContractFieldsAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantLessonPackageTypePermissions();
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
    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Access договор',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'lessons_per_week' => 2,
            'lessons_per_month' => 8,
            'lesson_duration_minutes' => 60,
            'lesson_price' => '700.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_manager_with_bind_can_open_modals_and_save_contract_fields(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('contracts.lessonPackage.bind');

        foreach ([
            route('admin.lesson-packages.index'),
            route('admin.directories.lesson-packages.index'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('id="create_contract_fields_section"', false)
                ->assertSee('id="edit_contract_fields_section"', false)
                ->assertSee('id="colLessonPackageLessonsPerWeek"', false);
        }

        $this->postJson(route('admin.lesson-packages.store'), $this->storePayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $package = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Access договор')
            ->firstOrFail();
        $this->assertSame(2, (int) $package->lessons_per_week);
        $this->assertSame(70000, (int) $package->lesson_price_cents);
    }

    public function test_manager_without_bind_still_opens_pages_but_contract_fields_are_hidden(): void
    {
        $this->grantPermission('lessonPackages.view');

        foreach ([
            route('admin.lesson-packages.index'),
            route('admin.directories.lesson-packages.index'),
        ] as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $this->assertNotSame('', trim((string) $page->getContent()));
            $page->assertDontSee('id="create_contract_fields_section"', false)
                ->assertDontSee('id="edit_contract_fields_section"', false)
                ->assertDontSee('id="create_lessons_per_week"', false)
                ->assertSee('id="create_lessons_count"', false)
                ->assertDontSee('id="colLessonPackageLessonsPerWeek"', false)
                ->assertDontSee('>Занятий в неделю<', false);
        }
    }

    public function test_data_table_returns_contract_labels_without_bind_permission(): void
    {
        $this->grantPermission('lessonPackages.view');

        $filled = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Data bind hidden',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'lessons_per_week' => 3,
            'lessons_per_month' => 12,
            'lesson_duration_minutes' => 45,
            'lesson_price_cents' => 125050,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $json = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
        ]))
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $filled->id);
        $this->assertIsArray($row);
        $this->assertSame('3', $row['lessons_per_week_label']);
        $this->assertSame('1 250,50 ₽', $row['lesson_price_label']);
    }

    public function test_superadmin_sees_contract_fields_without_explicit_bind_permission(): void
    {
        $this->asSuperadmin()->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertSee('id="create_contract_fields_section"', false)
            ->assertSee('id="edit_contract_fields_section"', false)
            ->assertSee('id="colLessonPackageLessonsPerWeek"', false);
    }
}
