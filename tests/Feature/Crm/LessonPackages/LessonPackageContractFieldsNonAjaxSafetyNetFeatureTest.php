<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net полей «Для договора»: 302, запись в БД, ошибки под полями.
 */
final class LessonPackageContractFieldsNonAjaxSafetyNetFeatureTest extends CrmTestCase
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
        $this->grantPermission('contracts.lessonPackage.bind');
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
            'name' => 'Non-AJAX договор',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_store_non_ajax_saves_contract_fields_and_redirects(): void
    {
        $this->post(route('admin.lesson-packages.store'), $this->validPayload([
            'lessons_per_week' => 2,
            'lesson_duration_minutes' => 60,
            'lesson_price' => '700.00',
        ]))->assertRedirect(route('admin.lesson-packages.index'));

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Non-AJAX договор',
            'lessons_per_week' => 2,
            'lesson_duration_minutes' => 60,
            'lesson_price_cents' => 70000,
        ]);
    }

    public function test_store_non_ajax_validation_redirects_back_with_errors_under_fields(): void
    {
        $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'lessons_per_week' => 0,
                'lesson_price' => 'abc',
            ]))
            ->assertStatus(302)
            ->assertSessionHasErrors(['lessons_per_week', 'lesson_price']);

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Non-AJAX договор',
        ]);
    }

    public function test_update_non_ajax_from_directories_clears_contract_fields(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Non-AJAX очистка',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 150000,
            'lessons_per_week' => 3,
            'lesson_duration_minutes' => 45,
            'lesson_price_cents' => 80000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->from(route('admin.directories.lesson-packages.index'))
            ->put(
                route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
                $this->validPayload([
                    'name' => 'Non-AJAX очищен',
                    'lessons_per_week' => '',
                    'lesson_duration_minutes' => '',
                    'lesson_price' => '',
                ])
            )
            ->assertRedirect(route('admin.directories.lesson-packages.index'));

        $this->assertDatabaseHas('lesson_packages', [
            'id' => $package->id,
            'name' => 'Non-AJAX очищен',
            'lessons_per_week' => null,
            'lesson_duration_minutes' => null,
            'lesson_price_cents' => null,
        ]);
    }

    public function test_store_non_ajax_without_bind_ignores_contract_fields_and_still_creates(): void
    {
        $actor = $this->createUserWithoutPermission('contracts.lessonPackage.bind', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->grantLessonPackageTypePermissions($actor);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('scheduleSlots.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->post(route('admin.lesson-packages.store'), $this->validPayload([
            'name' => 'Non-AJAX без bind',
            'lessons_per_week' => 2,
            'lesson_price' => '700.00',
        ]))->assertRedirect(route('admin.lesson-packages.index'));

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Non-AJAX без bind',
            'lessons_per_week' => null,
            'lesson_price_cents' => null,
        ]);
    }
}
