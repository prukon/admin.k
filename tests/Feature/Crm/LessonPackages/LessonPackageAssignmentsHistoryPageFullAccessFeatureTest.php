<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к истории назначений абонементов (toolbar «История» + logs-data)
 * и связанным endpoint'ам вкладки, которые пишут/читают аудит.
 *
 * @see LessonPackagesPageFullAccessFeatureTest
 * @see LessonPackageAssignmentsAuditLogsFeatureTest
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageAssignmentsHistoryPageFullAccessFeatureTest extends CrmTestCase
{
    private User $student;

    private LessonPackage $package;

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
            'lastname' => 'FullAccess',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);

        $this->package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'History full access package',
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
            'fee_amount' => '100.00',
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

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, expect?: list<int>}>
     */
    private function historySectionEndpoints(?UserLessonPackage $assignment = null): array
    {
        $assignment ??= $this->assignment;

        $disposable = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => '50.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        return [
            ['method' => 'GET', 'url' => route('admin.lesson-packages.assignments')],
            [
                'method' => 'GET',
                'url' => route('logs.data.lesson-package-assignment', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                ]),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                ]),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.show', ['assignment' => $assignment->id]),
            ],
            [
                'method' => 'PUT',
                'url' => route('admin.lesson-packages.assignments.update', ['assignment' => $assignment->id]),
                'data' => ['fee_amount' => '120.00'],
            ],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.assignments.store'),
                'data' => [
                    'user_id' => $this->student->id,
                    'lesson_package_id' => $this->package->id,
                    'fee_amount' => '150.00',
                ],
                // HTML-форма: JSON-вызов без Accept/XHR → 302 redirect (не пустой 200).
                'expect' => [302],
            ],
            [
                'method' => 'DELETE',
                'url' => route('admin.lesson-packages.assignments.destroy', ['assignment' => $disposable->id]),
            ],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.assignments.manual-paid', ['assignment' => $assignment->id]),
                'data' => ['mode' => 'paid', 'comment' => 'Full access manual paid'],
                // без manualPaid.manage → 403; с правом → 200 (в happy-path выдаём оба).
                'expect' => [200],
            ],
        ];
    }

    public function test_guest_is_denied_on_history_section_endpoints(): void
    {
        Auth::logout();

        foreach ($this->historySectionEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_lesson_packages_view_gets_403_on_history_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->historySectionEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);

            $allowed = in_array($item['method'], ['DELETE'], true) ? [403, 404] : [403];

            $this->assertContains(
                $response->getStatusCode(),
                $allowed,
                "Без lessonPackages.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_assignments_page_renders_history_toolbar_with_view(): void
    {
        $this->grantPermission($this->user, 'lessonPackages.view');
        $this->grantPermission($this->user, 'setPrices.packageAssignments.view');

        $page = $this->get(route('admin.lesson-packages.assignments'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));

        $page->assertSee('ulp-assignments-table', false)
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false)
            ->assertSee('lesson-packages\/assignments\/logs-data', false)
            ->assertSee('Назначить абонемент', false)
            ->assertSee('KidsCrmDataTable.create', false);
    }

    public function test_viewer_with_lesson_packages_view_gets_expected_status_on_history_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'lessonPackages.view');
        $this->grantPermission($actor, 'setPrices.packageAssignments.view');
        $this->grantPermission($actor, 'lessonPackages.manualPaid.manage');

        foreach ($this->historySectionEndpoints() as $item) {
            $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);
            $expected = $item['expect'] ?? [200];

            $this->assertContains(
                $response->getStatusCode(),
                $expected,
                "С правом: {$item['method']} {$item['url']} → {$response->getStatusCode()}, body: "
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

    public function test_logs_data_does_not_leak_foreign_partner_events(): void
    {
        $this->grantPermission($this->user, 'lessonPackages.view');
        $this->grantPermission($this->user, 'setPrices.packageAssignments.view');

        $foreignPartner = Partner::factory()->create();

        DB::table('my_logs')->insert([
            [
                'partner_id' => $this->partner->id,
                'author_id' => $this->user->id,
                'event' => 'user_lesson_package.created',
                'level' => 'info',
                'description' => 'Own partner assignment history row',
                'type' => null,
                'action' => null,
                'created_at' => now(),
            ],
            [
                'partner_id' => $foreignPartner->id,
                'author_id' => $this->user->id,
                'event' => 'user_lesson_package.created',
                'level' => 'info',
                'description' => 'Foreign partner assignment history row',
                'type' => null,
                'action' => null,
                'created_at' => now(),
            ],
        ]);

        $descriptions = collect($this->getJson(route('logs.data.lesson-package-assignment', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
        ]))->assertOk()->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'Own partner assignment history row'))
        );
        $this->assertFalse(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'Foreign partner assignment history row'))
        );
    }
}
