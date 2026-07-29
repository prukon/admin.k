<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для модалок шаблонов абонементов (store/update).
 */
final class LessonPackagesNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Non-AJAX пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 1,
        ], $overrides);
    }

    public function test_store_non_ajax_redirects_and_creates_package_with_auto_attendance(): void
    {
        $this->post(route('admin.lesson-packages.store'), $this->validPayload())
            ->assertRedirect(route('admin.lesson-packages.index'));

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Non-AJAX пакет',
            'schedule_type' => 'fixed',
            'auto_attendance_enabled' => 1,
        ]);
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), [
                'schedule_type' => 'fixed',
                'auto_attendance_enabled' => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name', 'duration_days', 'lessons_count', 'price']);

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => '',
        ]);
    }

    public function test_update_non_ajax_redirects_and_updates_auto_attendance_flag(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'До non-ajax',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
            'name' => 'После non-ajax',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 1,
        ])
            ->assertRedirect(route('admin.lesson-packages.index'));

        $package->refresh();
        $this->assertSame('После non-ajax', $package->name);
        $this->assertTrue((bool) $package->auto_attendance_enabled);
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Валидация non-ajax',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->from(route('admin.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
                'name' => '',
                'schedule_type' => 'fixed',
                'duration_days' => 30,
                'lessons_count' => 8,
                'price' => '1000.00',
                'freeze_enabled' => 0,
                'auto_attendance_enabled' => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertSame('Валидация non-ajax', $package->fresh()->name);
        $this->assertFalse((bool) $package->fresh()->auto_attendance_enabled);
    }

    public function test_columns_settings_non_ajax_post_saves_and_returns_json_success_not_empty_200(): void
    {
        $response = $this->post(route('admin.lesson-packages.columns-settings.save'), [
            'columns' => [
                'name' => 1,
                'actions' => 0,
            ],
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->getJson(route('admin.lesson-packages.columns-settings.get'))
            ->assertOk()
            ->assertJson([
                'name' => true,
                'actions' => false,
            ]);
    }

    public function test_columns_settings_non_ajax_validation_failure_returns_422_or_redirect_not_empty_200(): void
    {
        $response = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.columns-settings.save'), []);

        $this->assertContains(
            $response->getStatusCode(),
            [302, 422],
            'Ожидался 302 или 422, получено ' . $response->getStatusCode()
        );
        $this->assertNotSame(200, $response->getStatusCode(), 'Пустой/успешный 200 на невалидный columns-settings недопустим');
    }
}
