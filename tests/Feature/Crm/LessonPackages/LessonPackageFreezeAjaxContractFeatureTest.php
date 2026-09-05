<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Support\LessonPackageFreezePermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт заморозки: JSON 200/422, errors по полям, UX «модалка без чекбокса шлёт 0».
 *
 * @see LessonPackageFreezePermissionFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageFreezeAjaxContractFeatureTest extends CrmTestCase
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

    private function actingAsPackagesManagerWithoutScheduleSlots(): User
    {
        $actor = $this->createUserWithoutPermission('scheduleSlots.view', $this->partner);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        return $actor;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ajax заморозка',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1200.00',
            'freeze_enabled' => 0,
            'freeze_days' => '',
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_ajax_enable_without_schedule_slots_view_returns_422_under_freeze_field(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $response = $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload([
                'freeze_enabled' => 1,
                'freeze_days' => 7,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['freeze_enabled']])
            ->assertJsonPath(
                'errors.freeze_enabled.0',
                LessonPackageFreezePermission::DENY_ENABLE
            );
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax заморозка',
        ]);
    }

    public function test_ajax_create_without_checkbox_stores_freeze_off_and_ignores_client_days(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload([
                'name' => 'Ajax без галочки',
                'freeze_enabled' => 0,
                'freeze_days' => 90,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax без галочки',
            'freeze_enabled' => 0,
            'freeze_days' => 0,
        ]);
    }

    public function test_ajax_create_omitting_freeze_fields_stores_false(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $payload = $this->validPayload(['name' => 'Без ключа заморозки']);
        unset($payload['freeze_enabled'], $payload['freeze_days']);

        $this->postJson(route('admin.lesson-packages.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Без ключа заморозки',
            'freeze_enabled' => 0,
            'freeze_days' => 0,
        ]);
    }

    public function test_ajax_edit_payload_with_freeze_zero_does_not_reset_existing_flag_or_days(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Ajax уже включено',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 1,
            'freeze_days' => 14,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $response = $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Ajax переименован',
                'schedule_type' => 'fixed',
                'freeze_enabled' => 0,
                'freeze_days' => '',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertOk()
            ->assertJson(['success' => true]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $package->refresh();
        $this->assertSame('Ajax переименован', $package->name);
        $this->assertTrue((bool) $package->freeze_enabled);
        $this->assertSame(
            14,
            (int) $package->freeze_days,
            'JS без чекбокса шлёт 0 и пустые дни; сервер не должен сбрасывать заморозку.'
        );
    }

    public function test_ajax_manager_with_schedule_slots_view_can_enable_and_disable_freeze(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload([
                'name' => 'Включаем заморозку',
                'freeze_enabled' => 1,
                'freeze_days' => 7,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $package = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Включаем заморозку')
            ->firstOrFail();
        $this->assertTrue((bool) $package->freeze_enabled);
        $this->assertSame(7, (int) $package->freeze_days);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Включаем заморозку',
                'freeze_enabled' => 0,
                'freeze_days' => '',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertFalse((bool) $package->fresh()->freeze_enabled);
        $this->assertSame(0, (int) $package->fresh()->freeze_days);
    }

    public function test_ajax_create_with_schedule_slots_view_returns_422_under_freeze_days_when_enabled_without_days(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $response = $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload([
                'name' => 'Нет дней',
                'freeze_enabled' => 1,
                'freeze_days' => '',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['freeze_days']])
            ->assertJsonPath('errors.freeze_days.0', 'Укажите количество дней заморозки.');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Нет дней',
        ]);
    }

    public function test_ajax_create_with_freeze_on_rejects_zero_and_out_of_range_days(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload([
                'name' => 'Дни 0',
                'freeze_enabled' => 1,
                'freeze_days' => 0,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['freeze_days'])
            ->assertJsonPath(
                'errors.freeze_days.0',
                'Количество дней заморозки должно быть больше нуля.'
            );

        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload([
                'name' => 'Дни 3651',
                'freeze_enabled' => 1,
                'freeze_days' => 3651,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['freeze_days']);
    }

    public function test_ajax_create_with_freeze_off_does_not_require_days(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload([
                'name' => 'Выкл без дней',
                'freeze_enabled' => 0,
                'freeze_days' => '',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Выкл без дней',
            'freeze_enabled' => 0,
            'freeze_days' => 0,
        ]);
    }

    public function test_ajax_store_rejects_freeze_for_no_schedule_under_freeze_field(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload([
                'name' => 'Разовое с заморозкой',
                'schedule_type' => 'no_schedule',
                'duration_days' => 1,
                'lessons_count' => 1,
                'freeze_enabled' => 1,
                'freeze_days' => 7,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['freeze_enabled'])
            ->assertJsonPath(
                'errors.freeze_enabled.0',
                'Для разового занятия заморозка недоступна.'
            );

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Разовое с заморозкой',
        ]);
    }

    public function test_ajax_store_postpay_silently_forces_freeze_off_instead_of_422(): void
    {
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('lessonPackages.type.postpay');

        $this->postJson(
            route('admin.lesson-packages.store'),
            [
                'name' => 'Постоплата с заморозкой',
                'schedule_type' => 'postpay',
                'price' => '500.00',
                'freeze_enabled' => 1,
                'freeze_days' => 7,
                'auto_attendance_enabled' => 0,
            ],
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Постоплата с заморозкой',
            'freeze_enabled' => 0,
            'freeze_days' => 0,
        ]);
    }

    public function test_ajax_update_to_no_schedule_without_permission_forces_existing_freeze_off(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Был фикс с заморозкой',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 1,
            'freeze_days' => 14,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Стал разовым',
                'schedule_type' => 'no_schedule',
                'duration_days' => 1,
                'lessons_count' => 1,
                'freeze_enabled' => 0,
                'freeze_days' => '',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $package->refresh();
        $this->assertSame('no_schedule', $package->schedule_type);
        $this->assertFalse((bool) $package->freeze_enabled);
        $this->assertSame(0, (int) $package->freeze_days);
    }

    public function test_show_json_returns_freeze_fields_for_edit_modal(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Show freeze',
            'schedule_type' => 'flexible',
            'duration_days' => 45,
            'lessons_count' => 10,
            'price_cents' => 250000,
            'freeze_enabled' => 1,
            'freeze_days' => 11,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.freeze_enabled', true)
            ->assertJsonPath('lesson_package.freeze_days', 11)
            ->assertJsonStructure([
                'success',
                'lesson_package' => ['id', 'name', 'schedule_type', 'freeze_enabled', 'freeze_days'],
            ]);
    }
}
