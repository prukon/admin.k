<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт полей «Для договора»: JSON 200/422, ошибки под полями, пустые значения → null.
 *
 * @see LessonPackageContractFieldsMarkupFeatureTest
 */
final class LessonPackageContractFieldsAjaxContractFeatureTest extends CrmTestCase
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
            'name' => 'Ajax договорные поля',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_store_without_contract_fields_saves_nulls(): void
    {
        $this->postJson(route('admin.lesson-packages.store'), $this->validPayload(), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax договорные поля',
            'lessons_per_week' => null,
            'lessons_per_month' => null,
            'lesson_duration_minutes' => null,
            'lesson_price_cents' => null,
        ]);
    }

    public function test_store_with_contract_fields_persists_values_and_cents(): void
    {
        $this->postJson(route('admin.lesson-packages.store'), $this->validPayload([
            'name' => 'Ajax договор заполнен',
            'lessons_per_week' => 3,
            'lessons_per_month' => 12,
            'lesson_duration_minutes' => 45,
            'lesson_price' => '1 250,50',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax договор заполнен',
            'lessons_per_week' => 3,
            'lessons_per_month' => 12,
            'lesson_duration_minutes' => 45,
            'lesson_price_cents' => 125050,
        ]);
    }

    public function test_store_validation_returns_422_under_each_contract_field(): void
    {
        $response = $this->postJson(route('admin.lesson-packages.store'), $this->validPayload([
            'lessons_per_week' => 0,
            'lessons_per_month' => 0,
            'lesson_duration_minutes' => 0,
            'lesson_price' => '-1',
        ]), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'lessons_per_week',
                    'lessons_per_month',
                    'lesson_duration_minutes',
                    'lesson_price',
                ],
            ])
            ->assertJsonPath('errors.lessons_per_week.0', 'Количество занятий в неделю должно быть больше нуля.')
            ->assertJsonPath('errors.lessons_per_month.0', 'Количество занятий в месяц должно быть больше нуля.')
            ->assertJsonPath('errors.lesson_duration_minutes.0', 'Длительность занятий должна быть больше нуля.')
            ->assertJsonPath('errors.lesson_price.0', 'Стоимость одного занятия не может быть отрицательной.');
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax договорные поля',
        ]);
    }

    public function test_show_returns_contract_fields_and_update_can_clear_them(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Show договор',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'lessons_per_week' => 2,
            'lessons_per_month' => 8,
            'lesson_duration_minutes' => 60,
            'lesson_price_cents' => 150000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertOk()
            ->assertJsonPath('lesson_package.lessons_per_week', 2)
            ->assertJsonPath('lesson_package.lessons_per_month', 8)
            ->assertJsonPath('lesson_package.lesson_duration_minutes', 60)
            ->assertJsonPath('lesson_package.lesson_price_cents', 150000)
            ->assertJsonPath('lesson_package.lesson_price', 1500);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Show договор очищен',
                'schedule_type' => 'flexible',
                'lessons_per_week' => '',
                'lessons_per_month' => '',
                'lesson_duration_minutes' => '',
                'lesson_price' => '',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'id' => $package->id,
            'name' => 'Show договор очищен',
            'lessons_per_week' => null,
            'lessons_per_month' => null,
            'lesson_duration_minutes' => null,
            'lesson_price_cents' => null,
        ]);
    }

    public function test_data_table_exposes_contract_field_labels(): void
    {
        $filled = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'DT договор заполнен',
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
        $empty = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'DT договор пустой',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $json = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->assertOk()->json();

        $filledRow = collect($json['data'] ?? [])->firstWhere('id', $filled->id);
        $this->assertIsArray($filledRow);
        $this->assertSame(3, $filledRow['lessons_per_week']);
        $this->assertSame('3', $filledRow['lessons_per_week_label']);
        $this->assertSame(12, $filledRow['lessons_per_month']);
        $this->assertSame('12', $filledRow['lessons_per_month_label']);
        $this->assertSame(45, $filledRow['lesson_duration_minutes']);
        $this->assertSame('45', $filledRow['lesson_duration_minutes_label']);
        $this->assertSame(125050, $filledRow['lesson_price_cents']);
        $this->assertSame('1 250,50 ₽', $filledRow['lesson_price_label']);

        $emptyRow = collect($json['data'] ?? [])->firstWhere('id', $empty->id);
        $this->assertIsArray($emptyRow);
        $this->assertNull($emptyRow['lessons_per_week']);
        $this->assertSame('—', $emptyRow['lessons_per_week_label']);
        $this->assertSame('—', $emptyRow['lessons_per_month_label']);
        $this->assertSame('—', $emptyRow['lesson_duration_minutes_label']);
        $this->assertSame('—', $emptyRow['lesson_price_label']);
    }

    public function test_store_without_bind_permission_silently_ignores_contract_fields(): void
    {
        $this->actingAsPackagesManagerWithoutBind();

        $this->postJson(route('admin.lesson-packages.store'), $this->validPayload([
            'name' => 'Ajax без bind',
            'lessons_per_week' => 0,
            'lessons_per_month' => 'abc',
            'lesson_duration_minutes' => -1,
            'lesson_price' => 'not-a-price',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax без bind',
            'lessons_per_week' => null,
            'lessons_per_month' => null,
            'lesson_duration_minutes' => null,
            'lesson_price_cents' => null,
        ]);
    }

    public function test_update_without_bind_permission_preserves_contract_fields(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Ajax preserve',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'lessons_per_week' => 2,
            'lessons_per_month' => 8,
            'lesson_duration_minutes' => 60,
            'lesson_price_cents' => 70000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->actingAsPackagesManagerWithoutBind();

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Ajax preserve updated',
                'lessons_per_week' => 9,
                'lessons_per_month' => 99,
                'lesson_duration_minutes' => 10,
                'lesson_price' => '1.00',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'id' => $package->id,
            'name' => 'Ajax preserve updated',
            'lessons_per_week' => 2,
            'lessons_per_month' => 8,
            'lesson_duration_minutes' => 60,
            'lesson_price_cents' => 70000,
        ]);
    }

    private function actingAsPackagesManagerWithoutBind(): void
    {
        $actor = $this->createUserWithoutPermission('contracts.lessonPackage.bind', $this->partner);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');
        $this->grantLessonPackageTypePermissions($actor);
        $this->grantPermissionToUser($actor, 'scheduleSlots.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermissionToUser(\App\Models\User $user, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
