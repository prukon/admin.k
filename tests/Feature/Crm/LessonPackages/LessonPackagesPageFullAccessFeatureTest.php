<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к списку шаблонов абонементов (toolbar + DataTable + logs/columns)
 * на /admin/lesson-packages и дубле в справочниках.
 *
 * @see SportTypesPageFullAccessFeatureTest
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackagesPageFullAccessFeatureTest extends CrmTestCase
{
    private LessonPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Full access package',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 150000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
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
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Full access create',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function sectionEndpoints(?LessonPackage $package = null): array
    {
        $package ??= $this->package;

        $disposable = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Full access disposable ' . uniqid('', true),
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        return [
            ['method' => 'GET', 'url' => route('admin.lesson-packages.index')],
            ['method' => 'GET', 'url' => route('admin.directories.lesson-packages.index')],
            ['method' => 'GET', 'url' => route('admin.lesson-packages.data', ['draw' => 1, 'start' => 0, 'length' => 10])],
            ['method' => 'GET', 'url' => route('admin.lesson-packages.columns-settings.get')],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.columns-settings.save'),
                'data' => ['columns' => ['name' => true, 'actions' => true]],
            ],
            ['method' => 'GET', 'url' => route('logs.data.lesson-package', ['draw' => 1, 'start' => 0, 'length' => 10])],
            ['method' => 'GET', 'url' => route('admin.lesson-packages.show', ['lessonPackage' => $package->id])],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.store'),
                'data' => $this->validPayload(['name' => 'Full access matrix store ' . uniqid('', true)]),
            ],
            [
                'method' => 'PUT',
                'url' => route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
                'data' => $this->validPayload(['name' => 'Full access matrix update']),
            ],
            [
                'method' => 'DELETE',
                'url' => route('admin.lesson-packages.destroy', ['lessonPackage' => $disposable->id]),
            ],
        ];
    }

    public function test_guest_is_denied_on_all_packages_list_endpoints(): void
    {
        Auth::logout();

        foreach ($this->sectionEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_lesson_packages_view_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->sectionEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без lessonPackages.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_index_and_directories_pages_render_toolbar_datatable_and_history_with_view(): void
    {
        $this->grantPermission($this->user, 'lessonPackages.view');

        foreach ([
            route('admin.lesson-packages.index'),
            route('admin.directories.lesson-packages.index'),
        ] as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $this->assertNotSame('', trim((string) $page->getContent()));

            $page->assertSee('lesson-packages-table', false)
                ->assertSee('payments-report-toolbar', false)
                ->assertSee('lessonPackagesFiltersCollapse', false)
                ->assertSee('lessonPackagesColumnsDropdown', false)
                ->assertSee('historyModal', false)
                ->assertSee('История', false)
                ->assertSee('Фильтры', false)
                ->assertSee('Колонки', false)
                ->assertSee('KidsCrmDataTable.create', false)
                ->assertSee('showLogModal', false)
                ->assertSee('lessonPackageCreateModal', false)
                ->assertSee('reloadPackagesTable', false);
        }
    }

    public function test_viewer_with_lesson_packages_view_gets_200_on_all_section_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'lessonPackages.view');

        foreach ($this->sectionEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "С правом: {$item['method']} {$item['url']} → {$response->getStatusCode()}, body: "
                . mb_substr((string) $response->getContent(), 0, 200)
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame('', trim((string) $response->getContent()), "Пустой 200: {$item['method']} {$item['url']}");
        }
    }

    public function test_data_endpoint_does_not_leak_foreign_partner_packages(): void
    {
        $this->grantPermission($this->user, 'lessonPackages.view');

        $foreignPartner = Partner::factory()->create();
        LessonPackage::query()->create([
            'partner_id' => $foreignPartner->id,
            'name' => 'Foreign full access package',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $names = collect($this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
        ]))->assertOk()->json('data'))->pluck('name')->all();

        $this->assertContains('Full access package', $names);
        $this->assertNotContains('Foreign full access package', $names);
    }
}
