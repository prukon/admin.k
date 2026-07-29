<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Directories;

use App\Models\LessonPackage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа: дубль «Абонементы» в «Справочниках»
 * (/admin/directories/lesson-packages) и связанные CRUD API шаблонов.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class DirectoriesLessonPackagesAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dirs access пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function sectionEndpoints(?LessonPackage $package = null): array
    {
        $package ??= LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Dirs access target',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $disposable = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Dirs access disposable',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        return [
            [
                'method' => 'GET',
                'url' => route('admin.directories.lesson-packages.index'),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.data', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.columns-settings.get'),
            ],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.columns-settings.save'),
                'data' => ['columns' => ['name' => true]],
                'headers' => ['HTTP_ACCEPT' => 'application/json'],
            ],
            [
                'method' => 'GET',
                'url' => route('logs.data.lesson-package', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.store'),
                'data' => $this->validStorePayload(['name' => 'Dirs guest/denied store']),
                'headers' => ['HTTP_ACCEPT' => 'application/json'],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.show', ['lessonPackage' => $package->id]),
                'headers' => ['HTTP_ACCEPT' => 'application/json'],
            ],
            [
                'method' => 'PUT',
                'url' => route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
                'data' => $this->validStorePayload(['name' => 'Dirs guest/denied update']),
                'headers' => ['HTTP_ACCEPT' => 'application/json'],
            ],
            [
                'method' => 'DELETE',
                'url' => route('admin.lesson-packages.destroy', ['lessonPackage' => $disposable->id]),
                'headers' => ['HTTP_ACCEPT' => 'application/json'],
            ],
        ];
    }

    public function test_guest_is_denied_on_directories_lesson_packages_endpoints(): void
    {
        Auth::logout();

        foreach ($this->sectionEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? []
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_lesson_packages_view_gets_403_on_all_section_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->sectionEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? []
            );

            $allowed = $item['method'] === 'DELETE' ? [403, 404] : [403];

            $this->assertContains(
                $response->getStatusCode(),
                $allowed,
                "Без lessonPackages.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_viewer_with_lesson_packages_view_gets_200_on_page_and_crud_json(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'lessonPackages.view');

        $page = $this->get(route('admin.directories.lesson-packages.index'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()), 'Пустой 200 на HTML странице справочника');

        $store = $this->postJson(route('admin.lesson-packages.store'), $this->validStorePayload([
            'name' => 'Dirs viewer create',
        ]));
        $store->assertOk()->assertJson(['success' => true]);

        $packageId = (int) LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Dirs viewer create')
            ->value('id');
        $this->assertGreaterThan(0, $packageId);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $packageId]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.id', $packageId)
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

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $packageId]), $this->validStorePayload([
            'name' => 'Dirs viewer updated',
        ]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->deleteJson(route('admin.lesson-packages.destroy', ['lessonPackage' => $packageId]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('lesson_packages', ['id' => $packageId]);
    }

    public function test_viewer_can_access_data_columns_and_logs_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'lessonPackages.view');

        LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Dirs data row',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'name' => 'Dirs data',
            'schedule_type' => 'fixed',
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.lesson-packages.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.lesson-packages.columns-settings.save'), [
            'columns' => ['name' => true, 'actions' => false],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('logs.data.lesson-package', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }
}
