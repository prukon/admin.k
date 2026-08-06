<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Право setPrices.packageAssignments.view: полный доступ к вкладке назначений.
 * guest → 302/401/403; без права → 403; с правами → ожидаемые статусы (не 500, не пустой 200).
 *
 * users-search общий с календарём — только lessonPackages.view (см. отдельный кейс).
 *
 * @see LessonPackagesPageFullAccessFeatureTest
 * @see LessonPackageAssignmentsHistoryPageFullAccessFeatureTest
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PackageAssignmentsPageFullAccessFeatureTest extends CrmTestCase
{
    private LessonPackage $package;

    private User $student;

    private UserLessonPackage $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
            'lastname' => 'FullAccess',
            'name' => 'Ученик',
        ]);

        $this->package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Full access assignment package',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->assignment = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 10000,
            'is_paid' => false,
            'created_by' => $this->user->id,
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

    private function grantAssignmentsAccess(User $actor): void
    {
        $this->grantPermission($actor, 'lessonPackages.view');
        $this->grantPermission($actor, 'setPrices.packageAssignments.view');
    }

    /**
     * Endpoint'ы, закрытые setPrices.packageAssignments.view (+ родитель lessonPackages.view).
     *
     * @return list<array{method: string, url: string, data?: array<string, mixed>, expect?: list<int>}>
     */
    private function guardedSectionEndpoints(): array
    {
        $disposable = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 1000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        return [
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments'),
                'expect' => [200],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                ]),
                'expect' => [200],
            ],
            [
                'method' => 'GET',
                'url' => route('logs.data.lesson-package-assignment', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                ]),
                'expect' => [200],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.teams-for-user', [
                    'user_id' => $this->student->id,
                ]),
                'expect' => [200],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.columns-settings.get'),
                'expect' => [200],
            ],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.assignments.columns-settings.save'),
                'data' => ['columns' => ['student' => true, 'actions' => true]],
                'expect' => [200],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.show', [
                    'assignment' => $this->assignment->id,
                ]),
                'expect' => [200],
            ],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.assignments.store'),
                'data' => [
                    'user_id' => $this->student->id,
                    'lesson_package_id' => $this->package->id,
                    'fee_amount' => '55.00',
                ],
                // store всегда redirect (форма create — classic POST)
                'expect' => [302],
            ],
            [
                'method' => 'PUT',
                'url' => route('admin.lesson-packages.assignments.update', [
                    'assignment' => $this->assignment->id,
                ]),
                'data' => ['fee_amount' => '120.00'],
                'expect' => [200],
            ],
            [
                'method' => 'DELETE',
                'url' => route('admin.lesson-packages.assignments.destroy', [
                    'assignment' => $disposable->id,
                ]),
                'expect' => [200],
            ],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.assignments.public-pay-link', [
                    'assignment' => $this->assignment->id,
                ]),
                // без T‑Bank → 422 (не 500)
                'expect' => [422],
            ],
        ];
    }

    public function test_guest_is_denied_on_all_package_assignments_endpoints(): void
    {
        Auth::logout();

        foreach ($this->guardedSectionEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_without_lesson_packages_view_gets_403_on_guarded_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->guardedSectionEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без lessonPackages.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_lesson_packages_view_but_without_package_assignments_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.packageAssignments.view', $this->partner);
        $this->grantPermission($actor, 'lessonPackages.view');
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->guardedSectionEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без setPrices.packageAssignments.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_users_search_available_with_only_lesson_packages_view(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.packageAssignments.view', $this->partner);
        $this->grantPermission($actor, 'lessonPackages.view');
        $this->actingAs($actor);

        $response = $this->getJson(route('admin.lesson-packages.assignments.users-search', ['q' => '']));
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertJsonStructure(['results']);
    }

    public function test_assignments_tab_hidden_on_packages_index_without_package_assignments_permission(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.packageAssignments.view', $this->partner);
        $this->grantPermission($actor, 'lessonPackages.view');
        $this->actingAs($actor);

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertDontSee('Назначение абонементов', false)
            ->assertSee('Абонементы', false);
    }

    public function test_assignments_tab_visible_on_packages_index_with_package_assignments_permission(): void
    {
        $this->grantAssignmentsAccess($this->user);

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertSee('Назначение абонементов', false);
    }

    public function test_assignments_page_renders_toolbar_and_modals_with_permissions(): void
    {
        $this->grantAssignmentsAccess($this->user);

        $page = $this->get(route('admin.lesson-packages.assignments'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));

        $page->assertSee('Назначение абонементов', false)
            ->assertSee('ulp-assignments-table', false)
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('Назначить абонемент', false)
            ->assertSee('ulpAssignmentCreateModal', false)
            ->assertSee('ulpAssignmentEditModal', false)
            ->assertSee('historyModal', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertViewHas('activeTab', 'assignments');
    }

    public function test_viewer_with_both_permissions_gets_expected_status_on_all_guarded_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.packageAssignments.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantAssignmentsAccess($actor);

        foreach ($this->guardedSectionEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);
            $expected = $item['expect'] ?? [200];

            $this->assertContains(
                $response->getStatusCode(),
                $expected,
                "С правами: {$item['method']} {$item['url']} → {$response->getStatusCode()}, body: "
                .mb_substr((string) $response->getContent(), 0, 200)
            );
            $this->assertNotSame(500, $response->getStatusCode());
            if ($response->getStatusCode() === 200) {
                $this->assertNotSame(
                    '',
                    trim((string) $response->getContent()),
                    "Пустой 200: {$item['method']} {$item['url']}"
                );
            }
        }
    }

    public function test_manual_paid_endpoint_forbidden_without_manual_paid_manage(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.manualPaid.manage', $this->partner);
        $this->grantAssignmentsAccess($actor);
        $this->actingAs($actor);

        $this->postJson(route('admin.lesson-packages.assignments.manual-paid', [
            'assignment' => $this->assignment->id,
        ]), [
            'mode' => 'paid',
            'comment' => 'Тестовый комментарий оплаты',
        ])->assertForbidden();
    }

    public function test_manual_paid_endpoint_ok_with_manual_paid_manage(): void
    {
        $this->grantAssignmentsAccess($this->user);
        $this->grantPermission($this->user, 'lessonPackages.manualPaid.manage');

        $response = $this->postJson(route('admin.lesson-packages.assignments.manual-paid', [
            'assignment' => $this->assignment->id,
        ]), [
            'mode' => 'paid',
            'comment' => 'Отмечено вручную в full-access тесте',
        ]);

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertJsonPath('success', true);
    }
}
