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
 * Фильтр «Удаленные» + отображение soft-deleted учеников на вкладке назначений.
 *
 * Регрессии UX-бага: join users без SoftDeletes + eager user со SoftDeletes →
 * имя «—» в таблице; show/update без withTrashed → 404 на модалке.
 *
 * @see LessonPackageAssignmentsUserStatusFilterFeatureTest
 * @see LessonPackageAssignmentsLessonsColumnFeatureTest
 */
final class LessonPackageAssignmentsDeletedUsersFilterFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantAssignmentsAccess(?User $actor = null): void
    {
        $roleId = $actor?->role_id ?? $this->user->role_id;
        foreach (['lessonPackages.view', 'setPrices.packageAssignments.view'] as $permissionName) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $roleId,
                'permission_id' => $this->permissionId($permissionName),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Доступ (HTTP)
    // -------------------------------------------------------------------------

    public function test_guest_cannot_open_assignments_with_deleted_users_filter(): void
    {
        Auth::logout();
        $ctx = $this->seedAliveAndDeletedAssignments();

        foreach ($this->deletedUsersEndpointCalls($ctx['deletedAssignment']) as $item) {
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
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_without_lesson_packages_view_gets_403_on_deleted_users_endpoints(): void
    {
        $ctx = $this->seedAliveAndDeletedAssignments();
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);

        foreach ($this->deletedUsersEndpointCalls($ctx['deletedAssignment']) as $item) {
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

    public function test_user_without_package_assignments_permission_gets_403_on_deleted_filter_data(): void
    {
        $this->seedAliveAndDeletedAssignments();
        $actor = $this->createUserWithoutPermission('setPrices.packageAssignments.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $this->get(route('admin.lesson-packages.assignments', [
            'filter_deleted_users' => '1',
        ]))->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'filter_deleted_users' => '1',
            ]))
            ->assertForbidden();
    }

    public function test_admin_with_permissions_can_use_deleted_users_filter_endpoints(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedAliveAndDeletedAssignments();

        foreach ($this->deletedUsersEndpointCalls($ctx['deletedAssignment']) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Admin: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame('', trim((string) $response->getContent()));
        }
    }

    public function test_superadmin_can_load_deleted_student_assignment_modal(): void
    {
        $this->asSuperadmin();
        $ctx = $this->seedAliveAndDeletedAssignments();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.show', [
                'assignment' => $ctx['deletedAssignment']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('assignment.user_is_deleted', true)
            ->assertJsonPath('assignment.user_display', 'Удалённый Ученик');
    }

    // -------------------------------------------------------------------------
    // Blade / дефолты UI
    // -------------------------------------------------------------------------

    public function test_assignments_page_renders_deleted_users_checkbox_unchecked_by_default(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();

        $html = $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('id="ulp-filter-deleted-users"', false)
            ->assertSee('name="filter_deleted_users"', false)
            ->assertSee('>Удаленные<', false)
            ->assertSee('id="ulp-modal-user-deleted-badge"', false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/id="ulp-filter-deleted-users"[^>]*\bchecked\b/',
            $html,
            'По умолчанию чекбокс «Удаленные» выключен'
        );
        $this->assertStringContainsString('badge text-bg-secondary ms-1 d-none">Удалён</span>', $html);
    }

    public function test_assignments_page_checks_deleted_filter_and_expands_panel_when_query_on(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();

        $html = $this->get(route('admin.lesson-packages.assignments', [
            'filter_deleted_users' => '1',
        ]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/class="collapse\s+show[^"]*"\s+id="ulpAssignmentsFiltersCollapse"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="ulp-filter-deleted-users"[^>]*\bchecked\b/',
            $html
        );
    }

    public function test_assignments_page_shows_deleted_user_label_in_filter_select_when_preselected(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedAliveAndDeletedAssignments();

        $html = $this->get(route('admin.lesson-packages.assignments', [
            'filter_user_id' => $ctx['deletedStudent']->id,
            'filter_deleted_users' => '1',
            'status' => '',
        ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'value="'.$ctx['deletedStudent']->id.'" selected',
            $html
        );
        $this->assertStringContainsString('Удалённый Ученик (удалён)', $html);
    }

    public function test_assignments_page_inline_js_passes_deleted_filter_and_renders_badge(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();

        $html = $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            "filter_deleted_users: \$ulpFiltersForm.find('[name=\"filter_deleted_users\"]').is(':checked') ? '1' : ''",
            $html
        );
        $this->assertStringContainsString('row.user_is_deleted', $html);
        $this->assertStringContainsString('badge text-bg-secondary ms-1">Удалён</span>', $html);
        $this->assertStringContainsString("ulp-modal-user-deleted-badge", $html);
        $this->assertStringContainsString("toggle('d-none', !a.user_is_deleted)", $html);
    }

    // -------------------------------------------------------------------------
    // Правило: по умолчанию удалённые скрыты; при filter_deleted_users=1 — видны
    // -------------------------------------------------------------------------

    public function test_assignments_data_hides_deleted_users_by_default_even_when_status_is_all(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedAliveAndDeletedAssignments();

        foreach ([
            'implicit_default' => [],
            'explicit_zero' => ['filter_deleted_users' => '0'],
            'empty_flag' => ['filter_deleted_users' => ''],
        ] as $label => $extra) {
            $ids = $this->assignmentIds(array_merge([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'status' => '',
                'filter_past_lessons' => '1',
            ], $extra));

            $this->assertContains(
                $ctx['aliveAssignment']->id,
                $ids,
                "Живой ученик должен быть в списке ({$label})"
            );
            $this->assertNotContains(
                $ctx['deletedAssignment']->id,
                $ids,
                "Удалённый ученик не должен попадать в список без фильтра ({$label})"
            );
        }
    }

    public function test_assignments_data_does_not_force_deleted_visibility_when_filter_off(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedAliveAndDeletedAssignments();

        // Негатив к правилу «если фильтр включён — показывать»: при выключенном
        // даже status=active (is_enabled=1 у soft-deleted) не раскрывает строку.
        $ids = $this->assignmentIds([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'active',
            'filter_past_lessons' => '1',
        ]);

        $this->assertNotContains($ctx['deletedAssignment']->id, $ids);
        $this->assertContains($ctx['aliveAssignment']->id, $ids);
    }

    /**
     * UX-баг (red→green): раньше в JSON было student="—" при живом ФИО в users.
     */
    public function test_assignments_data_shows_real_name_and_deleted_flag_instead_of_dash_when_filter_on(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedAliveAndDeletedAssignments();

        foreach (['1', 'true', 'on', 'yes'] as $truthy) {
            $json = $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('admin.lesson-packages.assignments.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 50,
                    'status' => '',
                    'filter_past_lessons' => '1',
                    'filter_deleted_users' => $truthy,
                ]))
                ->assertOk()
                ->assertJsonStructure([
                    'draw',
                    'recordsTotal',
                    'recordsFiltered',
                    'data' => [
                        [
                            'id',
                            'student',
                            'user_is_deleted',
                        ],
                    ],
                ])
                ->json();

            $ids = collect($json['data'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->assertContains($ctx['aliveAssignment']->id, $ids, "truthy={$truthy}");
            $this->assertContains($ctx['deletedAssignment']->id, $ids, "truthy={$truthy}");

            $deletedRow = collect($json['data'] ?? [])->firstWhere('id', $ctx['deletedAssignment']->id);
            $this->assertNotNull($deletedRow);
            $this->assertTrue((bool) $deletedRow['user_is_deleted']);
            $this->assertSame('Удалённый Ученик', $deletedRow['student']);
            $this->assertNotSame('—', $deletedRow['student']);

            $aliveRow = collect($json['data'] ?? [])->firstWhere('id', $ctx['aliveAssignment']->id);
            $this->assertNotNull($aliveRow);
            $this->assertFalse((bool) $aliveRow['user_is_deleted']);
            $this->assertSame('Живой Ученик', $aliveRow['student']);
        }
    }

    public function test_assignments_data_filter_by_deleted_user_id_works_with_deleted_filter_on(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedAliveAndDeletedAssignments();

        $ids = $this->assignmentIds([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => '',
            'filter_past_lessons' => '1',
            'filter_deleted_users' => '1',
            'filter_user_id' => $ctx['deletedStudent']->id,
        ]);

        $this->assertSame([$ctx['deletedAssignment']->id], $ids);
    }

    // -------------------------------------------------------------------------
    // Модалка / AJAX / non-AJAX (назначение удалённого ученика)
    // -------------------------------------------------------------------------

    /**
     * UX-баг (red→green): без withTrashed show давал 404 («ученик не найден»).
     */
    public function test_opening_edit_modal_for_deleted_student_assignment_returns_name_not_404(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedAliveAndDeletedAssignments();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.show', [
                'assignment' => $ctx['deletedAssignment']->id,
            ]))
            ->assertOk()
            ->assertJsonStructure([
                'assignment' => [
                    'id',
                    'user_display',
                    'user_is_deleted',
                    'fee_amount',
                ],
            ])
            ->assertJsonPath('assignment.id', $ctx['deletedAssignment']->id)
            ->assertJsonPath('assignment.user_is_deleted', true)
            ->assertJsonPath('assignment.user_display', 'Удалённый Ученик');
    }

    public function test_alive_student_assignment_show_has_user_is_deleted_false(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedAliveAndDeletedAssignments();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.show', [
                'assignment' => $ctx['aliveAssignment']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('assignment.user_is_deleted', false)
            ->assertJsonPath('assignment.user_display', 'Живой Ученик');
    }

    public function test_update_ajax_for_deleted_student_assignment_persists_fee(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedAliveAndDeletedAssignments();

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.assignments.update', [
                'assignment' => $ctx['deletedAssignment']->id,
            ]), [
                'fee_amount' => '199.50',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment.user_is_deleted', true)
            ->assertJsonPath('assignment.user_display', 'Удалённый Ученик')
            ->assertJsonPath('assignment.fee_amount', 199.5);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['deletedAssignment']->id,
            'fee_amount_cents' => 19950,
        ]);
    }

    public function test_update_ajax_validation_for_deleted_student_returns_422_field_errors(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedAliveAndDeletedAssignments();

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.assignments.update', [
                'assignment' => $ctx['deletedAssignment']->id,
            ]), [
                'fee_amount' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fee_amount']);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['deletedAssignment']->id,
            'fee_amount_cents' => 12000,
        ]);
    }

    public function test_update_non_ajax_for_deleted_student_redirects_and_persists(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedAliveAndDeletedAssignments();

        $this->from(route('admin.lesson-packages.assignments', [
            'filter_deleted_users' => '1',
            'status' => '',
        ]))
            ->put(route('admin.lesson-packages.assignments.update', [
                'assignment' => $ctx['deletedAssignment']->id,
            ]), [
                '_token' => csrf_token(),
                'fee_amount' => '88.00',
            ])
            ->assertStatus(302)
            ->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['deletedAssignment']->id,
            'fee_amount_cents' => 8800,
        ]);
    }

    public function test_lessons_endpoint_for_deleted_student_assignment_returns_200_not_404(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedAliveAndDeletedAssignments();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.lessons', [
                'assignment' => $ctx['deletedAssignment']->id,
            ]))
            ->assertOk()
            ->assertJsonStructure(['fee', 'balance', 'lessons']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function deletedUsersEndpointCalls(UserLessonPackage $deletedAssignment): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments', [
                    'filter_deleted_users' => '1',
                    'status' => '',
                ]),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                    'filter_deleted_users' => '1',
                    'status' => '',
                    'filter_past_lessons' => '1',
                ]),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.show', [
                    'assignment' => $deletedAssignment->id,
                ]),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.lesson-packages.assignments.lessons', [
                    'assignment' => $deletedAssignment->id,
                ]),
            ],
        ];
    }

    /**
     * @return array{
     *     package: LessonPackage,
     *     aliveStudent: User,
     *     deletedStudent: User,
     *     aliveAssignment: UserLessonPackage,
     *     deletedAssignment: UserLessonPackage
     * }
     */
    private function seedAliveAndDeletedAssignments(): array
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Пакет удалённые',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $aliveStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
            'lastname' => 'Живой',
            'name' => 'Ученик',
        ]);
        $deletedStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
            'lastname' => 'Удалённый',
            'name' => 'Ученик',
        ]);
        $deletedStudent->delete();

        $aliveAssignment = UserLessonPackage::query()->create([
            'user_id' => $aliveStudent->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 5,
            'fee_amount_cents' => 10000,
            'is_paid' => 0,
            'created_by' => $this->user->id,
        ]);
        $deletedAssignment = UserLessonPackage::query()->create([
            'user_id' => $deletedStudent->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 3,
            'fee_amount_cents' => 12000,
            'is_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        return [
            'package' => $package,
            'aliveStudent' => $aliveStudent,
            'deletedStudent' => $deletedStudent,
            'aliveAssignment' => $aliveAssignment,
            'deletedAssignment' => $deletedAssignment,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<int>
     */
    private function assignmentIds(array $params): array
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
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }
}
