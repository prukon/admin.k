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
 * Контроль доступа к смене даты окончания назначения (модалка «Изменение абонемента»).
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see LessonPackagesPageFullAccessFeatureTest
 */
final class LessonPackageAssignmentEndsAtAccessFeatureTest extends CrmTestCase
{
    private UserLessonPackage $assignmentWithPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'EndsAt access package',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->assignmentWithPeriod = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-05-01',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => '100.00',
            'is_paid' => 0,
            'created_by' => $this->user->id,
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
    private function endsAtRelatedEndpoints(): array
    {
        $id = (int) $this->assignmentWithPeriod->id;

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
                    'ends_at' => '2026-06-01',
                ],
            ],
        ];
    }

    public function test_guest_is_denied_on_ends_at_related_endpoints(): void
    {
        Auth::logout();

        foreach ($this->endsAtRelatedEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $this->assignmentWithPeriod->id,
            'ends_at' => '2026-05-01',
        ]);
    }

    public function test_user_without_lesson_packages_view_gets_403_on_ends_at_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->endsAtRelatedEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без lessonPackages.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $this->assignmentWithPeriod->id,
            'ends_at' => '2026-05-01',
        ]);
    }

    public function test_viewer_with_lesson_packages_view_gets_expected_status_on_ends_at_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'lessonPackages.view');
        $this->grantPermission($actor, 'setPrices.packageAssignments.view');

        $page = $this->get(route('admin.lesson-packages.assignments'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('ulpAssignmentEditModal', false)
            ->assertSee('ulp-modal-period-end', false)
            ->assertSee('ulp-modal-period-end-err', false)
            ->assertSee('period_editable', false);

        $this->getJson(
            route('admin.lesson-packages.assignments.show', ['assignment' => $this->assignmentWithPeriod->id]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJsonPath('assignment.period_editable', true)
            ->assertJsonPath('assignment.period_end', '2026-05-01');

        $update = $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $this->assignmentWithPeriod->id]),
            [
                'fee_amount' => '100.00',
                'ends_at' => '2026-06-15',
            ],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment.period_end', '2026-06-15');
        $this->assertNotSame('', trim((string) $update->getContent()));

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $this->assignmentWithPeriod->id,
            'ends_at' => '2026-06-15',
        ]);
    }

    public function test_foreign_partner_cannot_update_ends_at_of_assignment(): void
    {
        $this->grantPermission($this->user, 'lessonPackages.view');
        $this->grantPermission($this->user, 'setPrices.packageAssignments.view');

        $permId = $this->permissionId('lessonPackages.view');
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->foreignUser->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->asForeignUser();

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $this->assignmentWithPeriod->id]),
            [
                'fee_amount' => '100.00',
                'ends_at' => '2026-07-01',
            ],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertForbidden();

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $this->assignmentWithPeriod->id,
            'ends_at' => '2026-05-01',
        ]);
    }
}
