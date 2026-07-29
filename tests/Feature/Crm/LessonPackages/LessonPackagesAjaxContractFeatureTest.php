<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт шаблонов абонементов: JSON-структура, 200/422, не пустой 200.
 *
 * @see SportTypesAjaxContractFeatureTest
 */
final class LessonPackagesAjaxContractFeatureTest extends CrmTestCase
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
            'name' => 'Ajax Contract пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 1,
        ], $overrides);
    }

    public function test_store_ajax_json_contract(): void
    {
        $response = $this->postJson(route('admin.lesson-packages.store'), $this->validPayload(), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax Contract пакет',
            'auto_attendance_enabled' => 1,
        ]);
    }

    public function test_store_validation_returns_422_with_field_errors(): void
    {
        $this->postJson(route('admin.lesson-packages.store'), [
            'schedule_type' => 'fixed',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_update_ajax_json_contract(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'До ajax update',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
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
                'name' => 'После ajax update',
                'schedule_type' => 'flexible',
                'auto_attendance_enabled' => 1,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $package->refresh();
        $this->assertSame('После ajax update', $package->name);
        $this->assertTrue((bool) $package->auto_attendance_enabled);
    }

    public function test_update_validation_returns_422_with_field_errors(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Ajax validation',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            [
                'name' => '',
                'schedule_type' => 'fixed',
                'duration_days' => 30,
                'lessons_count' => 8,
                'price' => '1000.00',
            ],
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_show_ajax_returns_entity_json_payload(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Show Ajax Package',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 12345,
            'freeze_enabled' => 1,
            'freeze_days' => 5,
            'auto_attendance_enabled' => 1,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.id', $package->id)
            ->assertJsonPath('lesson_package.name', 'Show Ajax Package')
            ->assertJsonStructure([
                'success',
                'lesson_package' => [
                    'id',
                    'name',
                    'schedule_type',
                    'duration_days',
                    'lessons_count',
                    'price_cents',
                    'price',
                    'freeze_enabled',
                    'freeze_days',
                    'auto_attendance_enabled',
                    'is_active',
                ],
            ]);
    }

    public function test_destroy_ajax_json_contract(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'На удаление ajax',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->deleteJson(route('admin.lesson-packages.destroy', ['lessonPackage' => $package->id]), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('lesson_packages', ['id' => $package->id]);
    }

    public function test_columns_settings_ajax_contract(): void
    {
        $this->getJson(route('admin.lesson-packages.columns-settings.get'))
            ->assertOk();

        $this->postJson(route('admin.lesson-packages.columns-settings.save'), [
            'columns' => [
                'name' => true,
                'schedule_type_label' => false,
                'actions' => true,
            ],
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson(route('admin.lesson-packages.columns-settings.get'))
            ->assertOk()
            ->assertJson([
                'name' => true,
                'schedule_type_label' => false,
                'actions' => true,
            ]);
    }

    public function test_columns_settings_validation_returns_422(): void
    {
        $this->postJson(route('admin.lesson-packages.columns-settings.save'), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_data_endpoint_returns_datatable_json_with_filters_not_empty(): void
    {
        LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'DT Alpha Fixed',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'DT Beta Flexible',
            'schedule_type' => 'flexible',
            'duration_days' => 14,
            'lessons_count' => 4,
            'price_cents' => 5000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $all = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]));

        $all->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        $this->assertGreaterThan(0, (int) $all->json('recordsTotal'));
        $this->assertNotSame('', trim((string) $all->getContent()));

        $row = collect($all->json('data'))->firstWhere('name', 'DT Alpha Fixed');
        $this->assertIsArray($row);
        $this->assertArrayHasKey('schedule_type_label', $row);
        $this->assertArrayHasKey('price_label', $row);
        $this->assertArrayHasKey('freeze_label', $row);
        $this->assertArrayHasKey('can_delete', $row);

        $filtered = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 2,
            'start' => 0,
            'length' => 50,
            'name' => 'Alpha',
            'schedule_type' => 'fixed',
        ]))->assertOk()->json();

        $this->assertCount(1, $filtered['data']);
        $this->assertSame('DT Alpha Fixed', $filtered['data'][0]['name']);
    }

    public function test_authorized_user_all_endpoints_return_expected_status_not_500(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Matrix package',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $disposable = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Matrix disposable',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $matrix = [
            ['GET', route('admin.lesson-packages.index'), [], 200],
            ['GET', route('admin.directories.lesson-packages.index'), [], 200],
            ['GET', route('admin.lesson-packages.data', ['draw' => 1, 'start' => 0, 'length' => 10]), [], 200],
            ['GET', route('admin.lesson-packages.columns-settings.get'), [], 200],
            ['POST', route('admin.lesson-packages.columns-settings.save'), ['columns' => ['name' => true]], 200],
            ['GET', route('logs.data.lesson-package', ['draw' => 1, 'start' => 0, 'length' => 10]), [], 200],
            ['GET', route('admin.lesson-packages.show', ['lessonPackage' => $package->id]), [], 200],
            ['POST', route('admin.lesson-packages.store'), $this->validPayload(['name' => 'Matrix store package']), 200],
            ['PUT', route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->validPayload([
                'name' => 'Matrix updated package',
            ]), 200],
            ['DELETE', route('admin.lesson-packages.destroy', ['lessonPackage' => $disposable->id]), [], 200],
        ];

        foreach ($matrix as [$method, $url, $data, $expectedStatus]) {
            $response = $this->json($method, $url, $data);

            $this->assertSame(
                $expectedStatus,
                $response->getStatusCode(),
                "{$method} {$url} → {$response->getStatusCode()}, body: " . mb_substr((string) $response->getContent(), 0, 200)
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame('', trim((string) $response->getContent()));
        }
    }
}
