<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonOccurrenceStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа к /admin/lesson-packages/occurrence-statuses и /schedule/occurrence-statuses
 * и ко всем endpoint'ам раздела (data, columns-settings, logs-data, CRUD, reorder).
 *
 * Gate: lessonOccurrenceStatuses.manage = schedule.view OR lessonPackages.view.
 *
 * @see SportTypesPageFullAccessFeatureTest
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonOccurrenceStatusesPageFullAccessFeatureTest extends CrmTestCase
{
    private LessonOccurrenceStatus $customStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $this->customStatus = LessonOccurrenceStatus::query()->create([
            'partner_id' => $this->partner->id,
            'code' => 'custom_'.bin2hex(random_bytes(4)),
            'title' => 'Full access custom',
            'icon' => 'fa-solid fa-star',
            'color' => '#abcdef',
            'is_system' => false,
            'is_active' => true,
            'consumes_lesson' => false,
            'sort_order' => 50,
        ]);
    }

    private function grantPermissionForUser(User $user, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeCustomRoleUser(): User
    {
        $role = Role::query()->create([
            'name' => 'los-access-'.uniqid(),
            'label' => 'LOS access role',
            'is_sistem' => 0,
            'is_visible' => 0,
            'order_by' => 0,
        ]);

        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $role->id,
            'is_enabled' => 1,
        ]);
    }

    public function test_index_page_returns_200_with_lesson_packages_view_and_toolbar_markers(): void
    {
        $this->grantPermissionForUser($this->user, 'lessonPackages.view');

        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))
            ->assertOk()
            ->assertSee('Статусы занятий', false)
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('losReportFiltersCollapse', false)
            ->assertSee('losColumnsDropdown', false)
            ->assertSee('id="los-statuses-table"', false)
            ->assertSee('historyModal', false)
            ->assertSee('>История</span>', false)
            ->assertSee('>Фильтры</span>', false)
            ->assertSee('>Колонки</span>', false)
            ->assertSee('showLogModal', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('losCreateModal', false);
    }

    public function test_schedule_tab_returns_200_with_schedule_view_and_toolbar_markers(): void
    {
        $this->grantPermissionForUser($this->user, 'schedule.view');

        $this->get(route('schedule.occurrence-statuses'))
            ->assertOk()
            ->assertSee('Статусы занятий', false)
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('id="los-statuses-table"', false)
            ->assertSee('historyModal', false)
            ->assertSee('KidsCrmDataTable.create', false);
    }

    public function test_all_section_endpoints_return_expected_status_for_admin_with_manage_gate(): void
    {
        $this->grantPermissionForUser($this->user, 'lessonPackages.view');

        $disposable = LessonOccurrenceStatus::query()->create([
            'partner_id' => $this->partner->id,
            'code' => 'custom_'.bin2hex(random_bytes(4)),
            'title' => 'Disposable delete',
            'icon' => 'fa-solid fa-star',
            'color' => '#112233',
            'is_system' => false,
            'is_active' => true,
            'consumes_lesson' => false,
            'sort_order' => 99,
        ]);

        $matrix = [
            ['GET', route('admin.lesson-packages.occurrence-statuses.index'), [], 200],
            ['GET', route('schedule.occurrence-statuses'), [], 200],
            ['GET', route('admin.lesson-packages.occurrence-statuses.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ]), [], 200],
            ['GET', route('admin.lesson-packages.occurrence-statuses.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'title' => 'Full access',
                'type' => 'custom',
                'status' => 'active',
                'consumes' => 'no',
            ]), [], 200],
            ['GET', route('admin.lesson-packages.occurrence-statuses.columns-settings.get'), [], 200],
            ['POST', route('admin.lesson-packages.occurrence-statuses.columns-settings.save'), [
                'columns' => [
                    'sort_order' => true,
                    'title' => true,
                    'icon' => true,
                    'type_label' => true,
                    'consumes_lesson_label' => true,
                    'is_active_label' => true,
                    'actions' => true,
                ],
            ], 200],
            ['GET', route('logs.data.lesson-occurrence-status', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ]), [], 200],
            ['POST', route('admin.lesson-packages.occurrence-statuses.store'), [
                'title' => 'Created via full access',
                'color' => '#abcdef',
                'icon' => 'fa-solid fa-star',
                'consumes_lesson' => 0,
                'is_active' => 1,
            ], 200],
            ['PUT', route('admin.lesson-packages.occurrence-statuses.update', $this->customStatus->id), [
                'title' => 'Full access custom updated',
                'color' => '#445566',
                'icon' => 'fa-solid fa-bell',
                'sort_order' => 51,
                'consumes_lesson' => 1,
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
                .mb_substr((string) $response->getContent(), 0, 250)
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame('', trim((string) $response->getContent()), "Пустой ответ: {$method} {$url}");
        }

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

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), [
            'items' => $reorderItems,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Порядок сохранён');
    }

    public function test_user_with_only_schedule_view_can_access_all_section_endpoints(): void
    {
        $actor = $this->makeCustomRoleUser();
        $this->grantPermissionForUser($actor, 'schedule.view');
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('schedule.occurrence-statuses'))->assertOk();
        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))->assertOk();

        $this->getJson(route('admin.lesson-packages.occurrence-statuses.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.get'))
            ->assertOk();

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.save'), [
            'columns' => ['title' => true],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('logs.data.lesson-occurrence-status', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertOk();

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Schedule view create',
            'color' => '#123456',
            'icon' => 'fa-solid fa-star',
            'consumes_lesson' => 0,
            'is_active' => 1,
        ])->assertOk();
    }

    public function test_user_with_only_lesson_packages_view_can_access_all_section_endpoints(): void
    {
        $actor = $this->makeCustomRoleUser();
        $this->grantPermissionForUser($actor, 'lessonPackages.view');
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))
            ->assertOk()
            ->assertSee('id="los-statuses-table"', false);

        $this->getJson(route('admin.lesson-packages.occurrence-statuses.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertOk();

        $this->getJson(route('logs.data.lesson-occurrence-status', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertOk();

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $this->customStatus->id), [
            'title' => 'LP view update',
            'color' => '#654321',
            'icon' => 'fa-solid fa-star',
            'sort_order' => 52,
            'consumes_lesson' => 0,
            'is_active' => 1,
        ])->assertOk();
    }

    public function test_index_and_read_endpoints_return_403_without_manage_gate(): void
    {
        $actor = $this->makeCustomRoleUser();
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $endpoints = [
            fn () => $this->get(route('admin.lesson-packages.occurrence-statuses.index')),
            fn () => $this->get(route('schedule.occurrence-statuses')),
            fn () => $this->getJson(route('admin.lesson-packages.occurrence-statuses.data', ['draw' => 1])),
            fn () => $this->getJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.get')),
            fn () => $this->postJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.save'), [
                'columns' => ['title' => true],
            ]),
            fn () => $this->getJson(route('logs.data.lesson-occurrence-status', ['draw' => 1])),
            fn () => $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
                'title' => 'Forbidden',
                'color' => '#111111',
                'consumes_lesson' => 0,
            ]),
            fn () => $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $this->customStatus->id), [
                'title' => 'Forbidden',
                'color' => '#111111',
                'sort_order' => 1,
                'consumes_lesson' => 0,
            ]),
            fn () => $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $this->customStatus->id)),
            fn () => $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), [
                'items' => [['id' => $this->customStatus->id, 'sort_order' => 1]],
            ]),
        ];

        foreach ($endpoints as $call) {
            $response = $call();
            $this->assertSame(
                403,
                $response->getStatusCode(),
                'Ожидался 403, получено '.$response->getStatusCode()
            );
        }
    }

    public function test_guest_cannot_access_any_section_endpoint(): void
    {
        Auth::logout();

        $endpoints = [
            fn () => $this->get(route('admin.lesson-packages.occurrence-statuses.index')),
            fn () => $this->get(route('schedule.occurrence-statuses')),
            fn () => $this->getJson(route('admin.lesson-packages.occurrence-statuses.data', ['draw' => 1])),
            fn () => $this->getJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.get')),
            fn () => $this->postJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.save'), [
                'columns' => ['title' => true],
            ]),
            fn () => $this->getJson(route('logs.data.lesson-occurrence-status', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ])),
            fn () => $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
                'title' => 'Guest',
                'color' => '#111111',
                'consumes_lesson' => 0,
            ]),
            fn () => $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $this->customStatus->id), [
                'title' => 'Guest',
                'color' => '#111111',
                'sort_order' => 1,
                'consumes_lesson' => 0,
            ]),
            fn () => $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $this->customStatus->id)),
            fn () => $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), [
                'items' => [['id' => $this->customStatus->id, 'sort_order' => 1]],
            ]),
        ];

        foreach ($endpoints as $call) {
            $status = $call()->getStatusCode();
            $this->assertContains(
                $status,
                [302, 401, 403],
                'Unexpected guest status: '.$status
            );
        }
    }
}
