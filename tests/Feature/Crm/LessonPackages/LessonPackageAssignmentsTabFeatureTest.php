<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTableSetting;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Вкладка «Назначение абонементов»: тулбар, фильтры, columns-settings, DataTable, AJAX-модалка, контроль доступа.
 */
final class LessonPackageAssignmentsTabFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{package: LessonPackage, student: User, assignment: UserLessonPackage}
     */
    private function seedAssignmentContext(
        string $scheduleType = 'no_schedule',
        float $fee = 100.0,
        bool $isPaid = false,
        int $lessonsRemaining = 8,
    ): array {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Тестов',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Пакет назначений',
            'schedule_type' => $scheduleType,
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 8,
            'lessons_remaining' => $lessonsRemaining,
            'fee_amount' => number_format($fee, 2, '.', ''),
            'is_paid' => $isPaid,
            'created_by' => $this->user->id,
        ]);

        return [
            'package' => $package,
            'student' => $student,
            'assignment' => $assignment,
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function assignmentsSectionEndpoints(UserLessonPackage $assignment, LessonPackage $package, User $student): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
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
                'url' => route('admin.lesson-packages.assignments.users-search', ['q' => '']),
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
                'method' => 'POST',
                'url' => route('admin.lesson-packages.assignments.store'),
                'data' => [
                    '_token' => csrf_token(),
                    'user_id' => $student->id,
                    'lesson_package_id' => $package->id,
                    'fee_amount' => '150.00',
                ],
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
                'method' => 'DELETE',
                'url' => route('admin.lesson-packages.assignments.destroy', ['assignment' => $assignment->id]),
            ],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $assignment->id]),
                'data' => [],
            ],
            [
                'method' => 'POST',
                'url' => route('admin.lesson-packages.assignments.manual-paid', ['assignment' => $assignment->id]),
                'data' => ['mode' => 'paid', 'comment' => 'Тест'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    private function attachTeamToLocation(Team $team, Location $location): void
    {
        $team->update(['location_id' => $location->id]);
    }

    // -------------------------------------------------------------------------
    // UI: тулбар, фильтры, колонки, KidsCrmDataTable
    // -------------------------------------------------------------------------

    public function test_assignments_page_renders_toolbar_filters_columns_and_datatable(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('Назначение абонементов', false)
            ->assertSee('payments-report-toolbar-action', false)
            ->assertSee('Назначить абонемент', false)
            ->assertSee('ulpAssignmentsFiltersCollapse', false)
            ->assertSee('ulp-assignments-filters', false)
            ->assertSee('ulp-filter-schedule-type', false)
            ->assertSee('ulp-filter-payment-status', false)
            ->assertSee('ulp-filter-lessons-remaining', false)
            ->assertSee('ulp-filter-user-status', false)
            ->assertSee('columnsDropdownUlpAssignments', false)
            ->assertSee('ulp-column-toggle', false)
            ->assertSee('ulp-assignments-table', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('assignments\/columns-settings', false);
    }

    public function test_assignments_page_expands_filters_when_query_has_active_filter(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.assignments', [
            'filter_payment_status' => 'unpaid',
        ]))
            ->assertOk()
            ->assertSee('id="ulpAssignmentsFiltersCollapse"', false)
            ->assertSee('collapse show mb-2 mb-md-3', false);

        $this->get(route('admin.lesson-packages.assignments', [
            'status' => 'inactive',
        ]))
            ->assertOk()
            ->assertSee('collapse show mb-2 mb-md-3', false);
    }

    public function test_assignments_page_hides_location_filter_without_locations_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertDontSee('id="ulp-filter-location"', false);
    }

    public function test_assignments_page_shows_location_filter_with_locations_view(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
            'name' => 'Объект назначений',
        ]);

        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('id="ulp-filter-location"', false)
            ->assertSee('Объект назначений', false);
    }

    // -------------------------------------------------------------------------
    // columns-settings
    // -------------------------------------------------------------------------

    public function test_assignments_columns_settings_round_trip_and_validation(): void
    {
        $this->grantPermission('lessonPackages.view');

        $columns = [
            'student' => true,
            'fee' => true,
            'paid' => false,
            'package_name' => true,
            'actions' => true,
        ];

        $this->getJson(route('admin.lesson-packages.assignments.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([]);

        $this->postJson(route('admin.lesson-packages.assignments.columns-settings.save'), [
            'columns' => $columns,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_table_settings', [
            'user_id' => $this->user->id,
            'table_key' => 'lesson_packages_assignments',
        ]);

        $this->getJson(route('admin.lesson-packages.assignments.columns-settings.get'))
            ->assertOk()
            ->assertJson($columns);

        $this->postJson(route('admin.lesson-packages.assignments.columns-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    // -------------------------------------------------------------------------
    // DataTable: фильтры и структура строк
    // -------------------------------------------------------------------------

    public function test_assignments_data_returns_expected_row_shape_without_period_column(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seedAssignmentContext();

        $json = $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) ($json['recordsFiltered'] ?? 0));
        $row = $json['data'][0] ?? [];
        $this->assertSame((int) $ctx['assignment']->id, (int) ($row['id'] ?? 0));
        $this->assertArrayHasKey('student', $row);
        $this->assertArrayHasKey('fee', $row);
        $this->assertArrayHasKey('effective_is_paid', $row);
        $this->assertArrayHasKey('balance', $row);
        $this->assertArrayHasKey('package_name', $row);
        $this->assertArrayHasKey('type_label', $row);
        $this->assertArrayHasKey('pay_link_available', $row);
    }

    public function test_assignments_data_applies_all_list_filters_including_location(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('locations.view');

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $this->attachTeamToLocation($teamA, $locA);
        $this->attachTeamToLocation($teamB, $locB);

        $studentA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'is_enabled' => 1,
        ]);
        $studentB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'is_enabled' => 1,
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($studentA, (int) $teamA->id);
        app(TeamUserSyncService::class)->attachTeamForStudent($studentB, (int) $teamB->id);

        $fixedPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс фильтр',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $flexPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий фильтр',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $matchId = (int) UserLessonPackage::query()->insertGetId([
            'user_id' => $studentA->id,
            'lesson_package_id' => $fixedPackage->id,
            'lessons_total' => 8,
            'lessons_remaining' => 3,
            'fee_amount' => '100.00',
            'is_paid' => 0,
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        UserLessonPackage::query()->insert([
            'user_id' => $studentB->id,
            'lesson_package_id' => $flexPackage->id,
            'lessons_total' => 8,
            'lessons_remaining' => 0,
            'fee_amount' => '200.00',
            'is_paid' => 1,
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $base = ['draw' => 1, 'start' => 0, 'length' => 50];

        $this->assertSame(2, (int) $this->getJson(route('admin.lesson-packages.assignments.data', $base))->json('recordsFiltered'));

        $byUser = $this->getJson(route('admin.lesson-packages.assignments.data', $base + [
            'filter_user_id' => $studentA->id,
        ]))->json();
        $this->assertSame(1, (int) ($byUser['recordsFiltered'] ?? 0));
        $this->assertSame($matchId, (int) ($byUser['data'][0]['id'] ?? 0));

        $this->assertSame(1, (int) $this->getJson(route('admin.lesson-packages.assignments.data', $base + [
            'filter_schedule_type' => 'fixed',
        ]))->json('recordsFiltered'));

        $this->assertSame(1, (int) $this->getJson(route('admin.lesson-packages.assignments.data', $base + [
            'filter_payment_status' => 'unpaid',
        ]))->json('recordsFiltered'));

        $this->assertSame(1, (int) $this->getJson(route('admin.lesson-packages.assignments.data', $base + [
            'filter_lessons_remaining' => 'has',
        ]))->json('recordsFiltered'));

        $this->assertSame(1, (int) $this->getJson(route('admin.lesson-packages.assignments.data', $base + [
            'filter_location_id' => $locA->id,
        ]))->json('recordsFiltered'));

        $this->assertSame(0, (int) $this->getJson(route('admin.lesson-packages.assignments.data', $base + [
            'filter_location_id' => $locB->id,
            'filter_user_id' => $studentA->id,
        ]))->json('recordsFiltered'));
    }

    // -------------------------------------------------------------------------
    // Контроль доступа
    // -------------------------------------------------------------------------

    public function test_assignments_section_endpoints_ok_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manualPaid.manage');

        $ctx = $this->seedAssignmentContext();
        $assignment = $ctx['assignment'];
        $package = $ctx['package'];
        $student = $ctx['student'];

        $this->get(route('admin.lesson-packages.assignments'))->assertOk();

        $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.lesson-packages.assignments.users-search', ['q' => '']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('admin.lesson-packages.assignments.columns-settings.get'))->assertOk();
        $this->postJson(route('admin.lesson-packages.assignments.columns-settings.save'), [
            'columns' => ['student' => true],
        ])->assertOk()->assertJson(['success' => true]);

        $this->getJson(route('admin.lesson-packages.assignments.show', ['assignment' => $assignment->id]))
            ->assertOk()
            ->assertJsonStructure(['assignment' => ['id', 'fee_amount', 'fee_editable']]);

        $this->putJson(route('admin.lesson-packages.assignments.update', ['assignment' => $assignment->id]), [
            'fee_amount' => '110.00',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment.fee_amount', '110.00');

        $this->postJson(route('admin.lesson-packages.assignments.manual-paid', ['assignment' => $assignment->id]), [
            'mode' => 'paid',
            'comment' => 'Наличные',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment.effective_is_paid', true);

        $this->post(route('admin.lesson-packages.assignments.store'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'fee_amount' => '99.00',
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        $freshAssignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => '50.00',
            'is_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $this->deleteJson(route('admin.lesson-packages.assignments.destroy', ['assignment' => $freshAssignment->id]))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_assignments_section_endpoints_forbidden_without_lesson_packages_view(): void
    {
        $ctx = $this->seedAssignmentContext();
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->assignmentsSectionEndpoints($ctx['assignment'], $ctx['package'], $ctx['student']) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                array_merge($item['headers'] ?? ['HTTP_ACCEPT' => 'application/json'], ['HTTP_X-Requested-With' => 'XMLHttpRequest'])
            );

            $allowed = in_array($item['method'], ['DELETE'], true) ? [403, 404] : [403];

            $this->assertContains(
                $response->getStatusCode(),
                $allowed,
                "Без lessonPackages.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_guest_is_denied_on_assignments_section_endpoints(): void
    {
        $ctx = $this->seedAssignmentContext();
        Auth::logout();

        foreach ($this->assignmentsSectionEndpoints($ctx['assignment'], $ctx['package'], $ctx['student']) as $item) {
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

    public function test_manual_paid_endpoint_forbidden_without_manual_paid_permission(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seedAssignmentContext();

        $this->postJson(route('admin.lesson-packages.assignments.manual-paid', ['assignment' => $ctx['assignment']->id]), [
            'mode' => 'paid',
            'comment' => 'Нет права',
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Backend safety-net: web POST store (форма модалки «Назначить»)
    // -------------------------------------------------------------------------

    public function test_store_assignment_non_ajax_redirects_and_creates_record(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seedAssignmentContext();
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $response = $this->post(route('admin.lesson-packages.assignments.store'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'lesson_package_id' => $ctx['package']->id,
            'fee_amount' => '333.00',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $student->id,
            'lesson_package_id' => $ctx['package']->id,
            'fee_amount' => '333.00',
        ]);
    }

    public function test_store_assignment_non_ajax_validation_failure_redirects_back_not_empty_200(): void
    {
        $this->grantPermission('lessonPackages.view');

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.assignments'));
        $response->assertSessionHasErrors(['user_id', 'lesson_package_id', 'fee_amount']);

        $this->assertSame(0, UserLessonPackage::query()->count());
    }

    // -------------------------------------------------------------------------
    // AJAX-контракт: модалка «Изменение абонемента» (show / update / destroy)
    // -------------------------------------------------------------------------

    public function test_show_assignment_ajax_contract(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seedAssignmentContext();

        $this->getJson(route('admin.lesson-packages.assignments.show', ['assignment' => $ctx['assignment']->id]), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'assignment' => [
                    'id',
                    'user_display',
                    'lesson_package_name',
                    'fee_amount',
                    'fee_editable',
                    'effective_is_paid',
                ],
            ]);
    }

    public function test_update_assignment_ajax_contract_success_and_validation(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seedAssignmentContext();

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
            ['fee_amount' => '175.25'],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment.fee_amount', '175.25');

        $paid = UserLessonPackage::query()->create([
            'user_id' => $ctx['student']->id,
            'lesson_package_id' => $ctx['package']->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => '100.00',
            'is_paid' => 1,
            'created_by' => $this->user->id,
        ]);

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $paid->id]),
            ['fee_amount' => '200.00'],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fee_amount']);
    }

    public function test_destroy_assignment_ajax_contract_success_and_business_rejection(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seedAssignmentContext(lessonsRemaining: 8);

        $this->deleteJson(
            route('admin.lesson-packages.assignments.destroy', ['assignment' => $ctx['assignment']->id]),
            [],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('user_lesson_packages', ['id' => $ctx['assignment']->id]);

        $consumed = UserLessonPackage::query()->create([
            'user_id' => $ctx['student']->id,
            'lesson_package_id' => $ctx['package']->id,
            'lessons_total' => 8,
            'lessons_remaining' => 5,
            'fee_amount' => '100.00',
            'is_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $this->deleteJson(
            route('admin.lesson-packages.assignments.destroy', ['assignment' => $consumed->id]),
            [],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('user_lesson_packages', ['id' => $consumed->id]);
    }
}
