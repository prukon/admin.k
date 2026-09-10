<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX: show и DataTables отдают одни и те же занятий/срок; 422 под полем lessons_count.
 *
 * @see LessonPackagesAjaxContractFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageEditFillDefaultsAjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
        $this->grantLessonPackageTypePermissions();
        $this->grantPermission('scheduleSlots.view');
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

    private function makeFlexibleTwelve(string $name = 'Ajax 12 занятий'): LessonPackage
    {
        return LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => $name,
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 12,
            'price_cents' => 1680000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ajax 12 занятий',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 12,
            'price' => '16800.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_show_json_returns_twelve_lessons_matching_table_row_for_edit_modal(): void
    {
        $package = $this->makeFlexibleTwelve();

        $show = $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $show->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.id', $package->id)
            ->assertJsonPath('lesson_package.lessons_count', 12)
            ->assertJsonPath('lesson_package.duration_days', 120)
            ->assertJsonStructure([
                'success',
                'lesson_package' => ['id', 'name', 'schedule_type', 'duration_days', 'lessons_count', 'price'],
            ]);
        $this->assertNotSame('', trim((string) $show->getContent()));
        $this->assertNotSame(500, $show->getStatusCode());

        $table = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
            'name' => 'Ajax 12 занятий',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json();

        $row = collect($table['data'] ?? [])->firstWhere('id', $package->id);
        $this->assertIsArray($row, 'Таблица должна отдавать ту же строку, что и show.');
        $this->assertSame(
            (int) $show->json('lesson_package.lessons_count'),
            (int) $row['lessons_count'],
            'Таблица и модалка не должны расходиться по числу занятий.'
        );
        $this->assertSame(
            (int) $show->json('lesson_package.duration_days'),
            (int) $row['duration_days']
        );
    }

    public function test_ajax_update_keeps_twelve_lessons_when_client_sends_show_values(): void
    {
        $package = $this->makeFlexibleTwelve();

        $response = $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload(['name' => 'Ajax 12 переименован']),
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonMissingPath('errors');
        $this->assertNotSame('', trim((string) $response->getContent()));

        $package->refresh();
        $this->assertSame('Ajax 12 переименован', $package->name);
        $this->assertSame(12, (int) $package->lessons_count);
        $this->assertSame(120, (int) $package->duration_days);
    }

    public function test_ajax_update_returns_422_under_lessons_count_when_omitted(): void
    {
        $package = $this->makeFlexibleTwelve();

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Без занятий',
                'lessons_count' => '',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['lessons_count']])
            ->assertJsonPath('errors.lessons_count.0', 'Укажите количество занятий.');

        $this->assertSame(12, (int) $package->fresh()->lessons_count);
    }

    public function test_ajax_update_returns_422_when_lessons_count_out_of_range(): void
    {
        $package = $this->makeFlexibleTwelve();

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload(['lessons_count' => 0]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lessons_count']);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload(['lessons_count' => 1001]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lessons_count']);

        $this->assertSame(12, (int) $package->fresh()->lessons_count);
    }

    public function test_ajax_create_stores_twelve_lessons_not_forced_eight(): void
    {
        $response = $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload(['name' => 'Ajax create 12']),
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax create 12',
            'lessons_count' => 12,
            'duration_days' => 120,
        ]);
    }
}
