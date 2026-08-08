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
 * Доступ к эндпоинтам автопролонгации назначений.
 *
 * @see LessonPackageAssignmentEndsAtAccessFeatureTest
 */
final class FixedLessonPackageAutoProlongAccessFeatureTest extends CrmTestCase
{
    private UserLessonPackage $fixedAssignment;

    private LessonPackage $fixedPackage;

    private User $student;

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
            'lastname' => 'Access',
            'name' => 'Пролонг',
        ]);

        $this->fixedPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Access fixed package',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->fixedAssignment = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->fixedPackage->id,
            'starts_at' => '2026-05-04',
            'ends_at' => '2026-06-03',
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount_cents' => 10000,
            'is_paid' => false,
            'created_by' => $this->user->id,
            'auto_prolong_enabled' => false,
        ]);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => (int) $actor->partner_id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function autoProlongRelatedEndpoints(): array
    {
        $id = (int) $this->fixedAssignment->id;

        return [
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments'),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.show', ['assignment' => $id]),
            ],
            [
                'method' => 'PUT',
                'url' => route('admin.lesson-packages.assignments.update', ['assignment' => $id]),
                'data' => [
                    'fee_amount' => '100.00',
                    'ends_at' => '2026-06-03',
                    'auto_prolong_enabled' => true,
                ],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.users-search', ['q' => 'Access']),
            ],
        ];
    }

    public function test_guest_is_denied_on_auto_prolong_related_endpoints(): void
    {
        Auth::logout();

        foreach ($this->autoProlongRelatedEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $this->fixedAssignment->id,
            'auto_prolong_enabled' => 0,
        ]);
    }

    public function test_manager_without_package_assignments_permission_gets_403_on_auto_prolong_update(): void
    {
        $denied = $this->createUserWithoutPermission('setPrices.packageAssignments.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($denied, 'lessonPackages.view');

        $response = $this->putJson(
            route('admin.lesson-packages.assignments.update', $this->fixedAssignment),
            [
                'fee_amount' => '100.00',
                'ends_at' => '2026-06-03',
                'auto_prolong_enabled' => true,
            ],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $this->fixedAssignment->id,
            'auto_prolong_enabled' => 0,
        ]);
    }

    public function test_manager_with_permissions_can_open_assignments_page_and_toggle_auto_prolong(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'lessonPackages.view');
        $this->grantPermission($actor, 'setPrices.packageAssignments.view');

        $page = $this->get(route('admin.lesson-packages.assignments'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('ulp_auto_prolong_wrap', false)
            ->assertSee('ulp-modal-auto-prolong', false)
            ->assertSee('blocked_reason', false)
            ->assertSee('syncAutoProlongVisibility', false);

        $show = $this->getJson(
            route('admin.lesson-packages.assignments.show', $this->fixedAssignment),
            ['X-Requested-With' => 'XMLHttpRequest']
        );
        $show->assertOk()
            ->assertJsonPath('assignment.auto_prolong_editable', true)
            ->assertJsonPath('assignment.auto_prolong_enabled', false);

        $update = $this->putJson(
            route('admin.lesson-packages.assignments.update', $this->fixedAssignment),
            [
                'fee_amount' => '100.00',
                'ends_at' => '2026-06-03',
                'auto_prolong_enabled' => true,
            ],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment.auto_prolong_enabled', true);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $this->fixedAssignment->id,
            'auto_prolong_enabled' => 1,
        ]);
    }
}
