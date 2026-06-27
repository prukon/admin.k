<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фильтр «Активность ученика» (status) на вкладке «Назначение абонементов».
 *
 * Покрывает логику фильтрации, UI и контроль доступа (guest / без lessonPackages.view / admin / superadmin).
 */
final class LessonPackageAssignmentsUserStatusFilterFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Контроль доступа
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_assignments_user_status_endpoints(): void
    {
        Auth::logout();
        $ctx = $this->seedActiveInactiveAssignments();

        foreach ($this->allAssignmentsUserStatusEndpointCalls($ctx['activeAssignment']) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_lesson_packages_view_gets_403_on_user_status_endpoints(): void
    {
        $ctx = $this->seedActiveInactiveAssignments();
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);

        foreach ($this->allAssignmentsUserStatusEndpointCalls($ctx['activeAssignment']) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без lessonPackages.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_admin_all_assignments_user_status_endpoints_return_200(): void
    {
        $this->asAdmin();
        $this->seedActiveInactiveAssignments();

        $this->assertAllAssignmentsUserStatusEndpointsReturn200();
    }

    public function test_superadmin_all_assignments_user_status_endpoints_return_200(): void
    {
        $this->asSuperadmin();
        $this->seedActiveInactiveAssignments();

        $this->assertAllAssignmentsUserStatusEndpointsReturn200();
    }

    public function test_all_user_status_filter_param_variants_return_200_for_assignments_section(): void
    {
        $this->asAdmin();
        $this->seedActiveInactiveAssignments();

        foreach ($this->userStatusFilterVariants() as $label => $statusParams) {
            $this->assertAssignmentsEndpointsOkWithStatus($statusParams, $label);
        }
    }

    public function test_guest_and_unauthorized_cannot_access_assignments_page_with_user_status_query_params(): void
    {
        $params = ['status' => 'inactive'];

        Auth::logout();
        $this->get(route('admin.lesson-packages.assignments', $params))->assertRedirect();

        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('admin.lesson-packages.assignments', $params))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // UI
    // -------------------------------------------------------------------------

    public function test_assignments_page_renders_user_status_filter_with_active_selected_by_default(): void
    {
        $this->asAdmin();

        $html = $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ulp-filter-user-status', $html);
        $this->assertStringContainsString('Активность ученика', $html);
        $this->assertStringContainsString(
            '<option value="active" selected',
            $html,
            'По умолчанию выбран «Только активные»'
        );
        $this->assertStringContainsString("defaultFilterUserStatus = 'active'", $html);
    }

    public function test_assignments_page_expands_filters_when_status_is_not_active(): void
    {
        $this->asAdmin();

        $this->get(route('admin.lesson-packages.assignments', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('id="ulpAssignmentsFiltersCollapse"', false)
            ->assertSee('collapse show mb-2 mb-md-3', false);

        $this->get(route('admin.lesson-packages.assignments', ['status' => '']))
            ->assertOk()
            ->assertSee('collapse show mb-2 mb-md-3', false);
    }

    // -------------------------------------------------------------------------
    // Логика фильтрации DataTable
    // -------------------------------------------------------------------------

    public function test_assignments_data_user_status_filters_records(): void
    {
        $this->asAdmin();
        $ctx = $this->seedActiveInactiveAssignments();
        $base = ['draw' => 1, 'start' => 0, 'length' => 50];

        $defaultActive = $this->assignmentsDataRows($base);
        $this->assertSame([$ctx['activeAssignment']->id], $defaultActive);

        $explicitActive = $this->assignmentsDataRows($base + ['status' => 'active']);
        $this->assertSame([$ctx['activeAssignment']->id], $explicitActive);

        $inactiveOnly = $this->assignmentsDataRows($base + ['status' => 'inactive']);
        $this->assertSame([$ctx['inactiveAssignment']->id], $inactiveOnly);

        $allStudents = $this->assignmentsDataRows($base + ['status' => '']);
        sort($allStudents);
        $expected = [$ctx['activeAssignment']->id, $ctx['inactiveAssignment']->id];
        sort($expected);
        $this->assertSame($expected, $allStudents);
    }

    public function test_assignments_data_default_matches_explicit_active(): void
    {
        $this->asAdmin();
        $this->seedActiveInactiveAssignments();
        $base = ['draw' => 1, 'start' => 0, 'length' => 50];

        $defaultFiltered = (int) $this->getJson(route('admin.lesson-packages.assignments.data', $base))
            ->assertOk()
            ->json('recordsFiltered');

        $explicitActiveFiltered = (int) $this->getJson(route('admin.lesson-packages.assignments.data', $base + [
            'status' => 'active',
        ]))
            ->assertOk()
            ->json('recordsFiltered');

        $this->assertSame($defaultFiltered, $explicitActiveFiltered);
        $this->assertSame(1, $defaultFiltered);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertAllAssignmentsUserStatusEndpointsReturn200(): void
    {
        foreach ($this->userStatusFilterVariants() as $label => $statusParams) {
            $this->assertAssignmentsEndpointsOkWithStatus($statusParams, $label);
        }
    }

    /**
     * @param  array<string, string>  $statusParams
     */
    private function assertAssignmentsEndpointsOkWithStatus(array $statusParams, string $label): void
    {
        $this->get(route('admin.lesson-packages.assignments', $statusParams))
            ->assertOk()
            ->assertSee('Назначение абонементов', false);

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.data', array_merge([
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ], $statusParams)))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.lesson-packages.assignments.users-search', array_merge(['q' => ''], $statusParams)))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('admin.lesson-packages.assignments.columns-settings.get'))
            ->assertOk();

        $this->postJson(route('admin.lesson-packages.assignments.columns-settings.save'), [
            'columns' => ['student' => true],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allAssignmentsUserStatusEndpointCalls(UserLessonPackage $assignment): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments', ['status' => 'active']),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                    'status' => 'active',
                ]),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.users-search', ['q' => '', 'status' => 'active']),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.columns-settings.get'),
            ],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.assignments.columns-settings.save'),
                'data' => ['columns' => ['student' => true]],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.show', ['assignment' => $assignment->id]),
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function userStatusFilterVariants(): array
    {
        return [
            'implicit_default' => [],
            'default_active' => ['status' => 'active'],
            'inactive' => ['status' => 'inactive'],
            'all_students' => ['status' => ''],
        ];
    }

    /**
     * @return array{
     *     package: LessonPackage,
     *     activeStudent: User,
     *     inactiveStudent: User,
     *     activeAssignment: UserLessonPackage,
     *     inactiveAssignment: UserLessonPackage
     * }
     */
    private function seedActiveInactiveAssignments(): array
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Пакет активность',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $activeStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $inactiveStudent = User::factory()->disabled()->create([
            'partner_id' => $this->partner->id,
        ]);

        $activeAssignment = UserLessonPackage::query()->create([
            'user_id' => $activeStudent->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 5,
            'fee_amount' => '100.00',
            'is_paid' => 0,
            'created_by' => $this->user->id,
        ]);
        $inactiveAssignment = UserLessonPackage::query()->create([
            'user_id' => $inactiveStudent->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 2,
            'fee_amount' => '150.00',
            'is_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        return [
            'package' => $package,
            'activeStudent' => $activeStudent,
            'inactiveStudent' => $inactiveStudent,
            'activeAssignment' => $activeAssignment,
            'inactiveAssignment' => $inactiveAssignment,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<int>
     */
    private function assignmentsDataRows(array $params): array
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.data', $params))
            ->assertOk()
            ->json();

        return collect($json['data'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }
}
