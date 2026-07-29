<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonOccurrenceStatus;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт (postJson/putJson/deleteJson/getJson): JSON-структура, статусы 200/422, не пустой 200.
 *
 * @see SportTypesAjaxContractFeatureTest
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonOccurrenceStatusesAjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
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

    private function createCustom(string $title): LessonOccurrenceStatus
    {
        return LessonOccurrenceStatus::query()->create([
            'partner_id' => $this->partner->id,
            'code' => 'custom_'.bin2hex(random_bytes(4)),
            'title' => $title,
            'icon' => 'fa-solid fa-star',
            'color' => '#eeeeee',
            'is_system' => false,
            'is_active' => true,
            'consumes_lesson' => false,
            'sort_order' => 50,
        ]);
    }

    public function test_store_ajax_json_contract(): void
    {
        $response = $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Ajax Contract Status',
            'color' => '#abcdef',
            'icon' => 'fa-solid fa-star',
            'consumes_lesson' => 0,
            'is_active' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Статус создан')
            ->assertJsonPath('status.title', 'Ajax Contract Status')
            ->assertJsonStructure([
                'message',
                'status' => [
                    'id',
                    'title',
                    'color',
                    'code',
                    'partner_id',
                    'is_system',
                    'is_active',
                    'consumes_lesson',
                ],
            ]);

        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_store_validation_returns_422_with_field_errors(): void
    {
        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => '',
            'color' => 'not-hex',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'color']);
    }

    public function test_update_ajax_json_contract(): void
    {
        $status = $this->createCustom('До ajax update');

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $status->id), [
            'title' => 'После ajax update',
            'color' => '#00aa00',
            'icon' => 'fa-solid fa-bell',
            'sort_order' => 33,
            'consumes_lesson' => true,
            'is_active' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Статус обновлён')
            ->assertJsonStructure(['message']);

        $this->assertSame('После ajax update', $status->fresh()->title);
        $this->assertTrue((bool) $status->fresh()->consumes_lesson);
    }

    public function test_update_system_status_title_change_returns_422(): void
    {
        /** @var LessonOccurrenceStatus $scheduled */
        $scheduled = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'scheduled')
            ->firstOrFail();

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $scheduled->id), [
            'title' => 'Взлом системного',
            'color' => '#123456',
            'icon' => 'fa-solid fa-icons',
            'sort_order' => 15,
            'consumes_lesson' => false,
            'is_active' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_destroy_ajax_json_contract(): void
    {
        $status = $this->createCustom('На удаление ajax');

        $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $status->id))
            ->assertOk()
            ->assertJsonPath('message', 'Статус удалён');

        $this->assertDatabaseMissing('lesson_occurrence_statuses', ['id' => $status->id]);
    }

    public function test_destroy_system_status_returns_422(): void
    {
        /** @var LessonOccurrenceStatus $attended */
        $attended = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'attended')
            ->firstOrFail();

        $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $attended->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }

    public function test_columns_settings_ajax_contract(): void
    {
        $this->getJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.get'))
            ->assertOk();

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.save'), [
            'columns' => [
                'title' => true,
                'icon' => false,
                'actions' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.get'))
            ->assertOk()
            ->assertJson([
                'title' => true,
                'icon' => false,
            ]);
    }

    public function test_columns_settings_validation_returns_422(): void
    {
        $this->postJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_data_endpoint_returns_datatable_json_not_empty(): void
    {
        $response = $this->getJson(route('admin.lesson-packages.occurrence-statuses.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'id',
                        'sort_order',
                        'title',
                        'color',
                        'type_label',
                        'consumes_lesson_label',
                        'is_active_label',
                    ],
                ],
            ]);

        $this->assertGreaterThanOrEqual(5, (int) $response->json('recordsTotal'));
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_data_endpoint_filters_by_type_status_and_consumes(): void
    {
        $this->createCustom('Filter custom inactive')->update([
            'is_active' => false,
            'consumes_lesson' => true,
        ]);

        $system = $this->getJson(route('admin.lesson-packages.occurrence-statuses.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'type' => 'system',
        ]))->assertOk();

        foreach ($system->json('data') as $row) {
            $this->assertSame('Системный', $row['type_label']);
        }

        $inactive = $this->getJson(route('admin.lesson-packages.occurrence-statuses.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'inactive',
        ]))->assertOk();

        $this->assertGreaterThanOrEqual(1, (int) $inactive->json('recordsFiltered'));
        foreach ($inactive->json('data') as $row) {
            $this->assertSame(0, (int) $row['is_active']);
        }

        $consumes = $this->getJson(route('admin.lesson-packages.occurrence-statuses.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'consumes' => 'yes',
            'type' => 'custom',
        ]))->assertOk();

        $this->assertGreaterThanOrEqual(1, (int) $consumes->json('recordsFiltered'));
        foreach ($consumes->json('data') as $row) {
            $this->assertSame(1, (int) $row['consumes_lesson']);
            $this->assertSame(0, (int) $row['is_system']);
        }
    }

    public function test_logs_data_returns_datatable_json_not_empty_body(): void
    {
        $response = $this->getJson(route('logs.data.lesson-occurrence-status', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $response->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_reorder_ajax_json_contract(): void
    {
        $rows = LessonOccurrenceStatus::query()
            ->forPartner((int) $this->partner->id)
            ->ordered()
            ->get(['id', 'sort_order']);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), [
            'items' => $rows->values()->map(fn (LessonOccurrenceStatus $row, int $i) => [
                'id' => $row->id,
                'sort_order' => ($i + 1) * 10,
            ])->all(),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Порядок сохранён');
    }

    public function test_reorder_incomplete_list_returns_422(): void
    {
        $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), [
            'items' => [
                ['id' => $this->createCustom('Partial reorder')->id, 'sort_order' => 1],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_authorized_user_all_endpoints_return_expected_status_not_500(): void
    {
        $status = $this->createCustom('Matrix status');
        $disposable = $this->createCustom('Matrix disposable');

        $reorderItems = LessonOccurrenceStatus::query()
            ->forPartner((int) $this->partner->id)
            ->ordered()
            ->get(['id'])
            ->values()
            ->map(fn (LessonOccurrenceStatus $row, int $i) => [
                'id' => $row->id,
                'sort_order' => ($i + 1) * 10,
            ])
            ->all();

        $matrix = [
            ['GET', route('admin.lesson-packages.occurrence-statuses.index'), [], 200],
            ['GET', route('schedule.occurrence-statuses'), [], 200],
            ['GET', route('admin.lesson-packages.occurrence-statuses.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ]), [], 200],
            ['GET', route('admin.lesson-packages.occurrence-statuses.columns-settings.get'), [], 200],
            ['POST', route('admin.lesson-packages.occurrence-statuses.columns-settings.save'), [
                'columns' => ['title' => true],
            ], 200],
            ['GET', route('logs.data.lesson-occurrence-status', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ]), [], 200],
            ['POST', route('admin.lesson-packages.occurrence-statuses.reorder'), [
                'items' => $reorderItems,
            ], 200],
            ['POST', route('admin.lesson-packages.occurrence-statuses.store'), [
                'title' => 'Matrix store status',
                'color' => '#abcdef',
                'icon' => 'fa-solid fa-star',
                'consumes_lesson' => 0,
                'is_active' => 1,
            ], 200],
            ['PUT', route('admin.lesson-packages.occurrence-statuses.update', $status->id), [
                'title' => 'Matrix updated status',
                'color' => '#112233',
                'icon' => 'fa-solid fa-star',
                'sort_order' => 40,
                'consumes_lesson' => 0,
                'is_active' => 1,
            ], 200],
            ['DELETE', route('admin.lesson-packages.occurrence-statuses.destroy', $disposable->id), [], 200],
        ];

        foreach ($matrix as [$method, $url, $data, $expectedStatus]) {
            $response = $this->json($method, $url, $data);

            $this->assertSame(
                $expectedStatus,
                $response->getStatusCode(),
                "{$method} {$url} → {$response->getStatusCode()}, body: "
                .mb_substr((string) $response->getContent(), 0, 200)
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame('', trim((string) $response->getContent()));
        }
    }
}
