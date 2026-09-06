<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net типа flexible: 302, запись в БД с кодом flexible, ошибки под полем.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageFlexibleUiLabelNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
        $this->grantLessonPackageTypePermissions($this->user, ['flexible']);
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
            'name' => 'Non-AJAX предоплата',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_store_without_ajax_header_creates_flexible_package_and_redirects(): void
    {
        $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Создан non-ajax flexible',
            ]))
            ->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Создан non-ajax flexible',
            'schedule_type' => 'flexible',
        ]);
    }

    public function test_directories_store_without_ajax_returns_to_directories_with_flexible_code(): void
    {
        $this->from(route('admin.directories.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Dirs non-ajax flexible',
            ]))
            ->assertRedirect(route('admin.directories.lesson-packages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Dirs non-ajax flexible',
            'schedule_type' => 'flexible',
        ]);
    }

    public function test_edit_save_without_ajax_keeps_flexible_code_and_redirects(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Был фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $response = $this->from(route('admin.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->validPayload([
                'name' => 'Стал предоплата',
                'schedule_type' => 'flexible',
            ]));

        $response->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $package->refresh();
        $this->assertSame('Стал предоплата', $package->name);
        $this->assertSame('flexible', (string) $package->schedule_type);
    }

    public function test_store_without_ajax_redirects_back_with_schedule_type_field_error_when_ui_label_is_sent(): void
    {
        $response = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Non-ajax русская подпись',
                'schedule_type' => 'Предоплата',
            ]));

        $response->assertStatus(302)
            ->assertSessionHasErrors(['schedule_type']);
        $this->assertSame(
            'Некорректный тип абонемента.',
            session('errors')->first('schedule_type')
        );
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Non-ajax русская подпись',
        ]);
    }

    public function test_store_without_ajax_redirects_back_when_type_is_omitted(): void
    {
        $payload = $this->validPayload(['name' => 'Non-ajax без типа']);
        unset($payload['schedule_type']);

        $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $payload)
            ->assertStatus(302)
            ->assertSessionHasErrors(['schedule_type']);

        $this->assertSame(
            'Выберите тип абонемента.',
            session('errors')->first('schedule_type')
        );
        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Non-ajax без типа',
        ]);
    }
}
