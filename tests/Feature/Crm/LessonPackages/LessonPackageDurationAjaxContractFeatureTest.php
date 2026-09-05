<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Support\LessonPackageDurationPermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт срока действия: JSON 200/422, errors.duration_days, UX пустой строки из модалки без поля.
 *
 * @see LessonPackageDurationPermissionFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageDurationAjaxContractFeatureTest extends CrmTestCase
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

    private function grantPermissionToUser(User $user, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actingAsPackagesManagerWithoutScheduleSlots(): void
    {
        $actor = $this->createUserWithoutPermission('scheduleSlots.view', $this->partner);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ajax срок',
            'schedule_type' => 'flexible',
            'lessons_count' => 8,
            'price' => '1200.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_ajax_create_without_duration_field_stores_30_and_returns_json_success(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $response = $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload(['name' => 'Ajax фон 30', 'duration_days' => '']),
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonMissingPath('errors');
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax фон 30',
            'duration_days' => LessonPackageDurationPermission::DEFAULT_CREATE_DAYS,
        ]);
    }

    public function test_ajax_edit_empty_duration_does_not_reset_existing_value_to_thirty(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Ajax уже 45',
            'schedule_type' => 'fixed',
            'duration_days' => 45,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $response = $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Ajax переименован',
                'schedule_type' => 'fixed',
                'duration_days' => '',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertOk()
            ->assertJson(['success' => true]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $package->refresh();
        $this->assertSame('Ajax переименован', $package->name);
        $this->assertSame(
            45,
            (int) $package->duration_days,
            'JS без поля шлёт пустую строку; сервер не должен подменять 45 на дефолт 30.'
        );
    }

    public function test_ajax_create_with_schedule_slots_view_returns_422_under_duration_field_when_omitted(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $response = $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload(['name' => 'Нет срока']),
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['duration_days']])
            ->assertJsonPath('errors.duration_days.0', 'Укажите длительность в днях.');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_ajax_create_with_schedule_slots_view_returns_422_when_duration_out_of_range(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload(['name' => 'Срок 0', 'duration_days' => 0]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['duration_days']);

        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload(['name' => 'Срок 3651', 'duration_days' => 3651]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['duration_days']);
    }

    public function test_ajax_manager_with_schedule_slots_view_can_save_and_change_duration(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload([
                'name' => 'Срок 14 ajax',
                'duration_days' => 14,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $package = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Срок 14 ajax')
            ->firstOrFail();
        $this->assertSame(14, (int) $package->duration_days);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Срок 14 ajax',
                'duration_days' => 21,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(21, (int) $package->fresh()->duration_days);
    }

    public function test_ajax_update_with_schedule_slots_view_returns_422_when_duration_omitted(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Update без срока',
            'schedule_type' => 'fixed',
            'duration_days' => 45,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Update без срока',
                'schedule_type' => 'fixed',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['duration_days'])
            ->assertJsonPath('errors.duration_days.0', 'Укажите длительность в днях.');

        $this->assertSame(45, (int) $package->fresh()->duration_days);
    }

    public function test_show_json_returns_duration_days_for_edit_modal(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Show duration',
            'schedule_type' => 'flexible',
            'duration_days' => 45,
            'lessons_count' => 10,
            'price_cents' => 250000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.duration_days', 45)
            ->assertJsonStructure([
                'success',
                'lesson_package' => ['id', 'name', 'schedule_type', 'duration_days'],
            ]);
    }
}
