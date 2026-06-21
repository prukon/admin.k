<?php

namespace Tests\Feature\Crm\StudentTeams;

use App\Models\Role;
use App\Models\Status;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Контроль доступа: страницы и API, затронутые pivot team_user, отдают 200 при наличии прав.
 */
final class StudentTeamPivotFullAccessFeatureTest extends StudentTeamPivotTestCase
{
    private Team $team;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $this->student = $this->makeStudentWithTeams([$this->team], [
            'name'     => 'FullAccess',
            'lastname' => 'Pivot',
        ]);

        $this->seedGlobalScheduleStatuses();
    }

    /**
     * @return list<array{label: string, permission: string, callback: callable}>
     */
    private function authorizedEndpointGroups(): array
    {
        $student = $this->student;
        $team = $this->team;
        $visitedStatusId = Status::globalVisitedId();
        $studentRoleId = $this->studentRoleId();

        return [
            [
                'label'      => 'schedule',
                'permission' => 'schedule.view',
                'callback'   => function () use ($student, $team, $visitedStatusId): void {
                    $this->get(route('schedule.index'))->assertOk();
                    $this->getJson(route('schedule.cell-context', [
                        'user_id' => $student->id,
                        'date'    => '2026-06-15',
                    ]))->assertOk();
                    $this->postJson(route('schedule.update'), [
                        'user_id'   => $student->id,
                        'date'      => '2026-06-15',
                        'status_id' => $visitedStatusId,
                    ])->assertOk();
                    $this->getJson(route('logs.data.schedule', ['draw' => 1]))->assertOk();
                    $this->getJson(route('user.schedule.info', $student))->assertOk();
                    $this->postJson(route('user.set.group', $student), [
                        'team_id' => $team->id,
                    ])->assertOk();
                    $this->postJson(route('user.sync.teams', $student), [
                        'team_ids' => [$team->id],
                    ])->assertOk();

                    Carbon::setTestNow('2026-06-15 12:00:00');
                    try {
                        $this->postJson(route('user.update.schedule', $student), [
                            'weekdays'  => [(int) now()->isoWeekday()],
                            'date_from' => now()->toDateString(),
                            'date_to'   => now()->toDateString(),
                        ])->assertOk();
                    } finally {
                        Carbon::setTestNow();
                    }
                },
            ],
            [
                'label'      => 'admin-users',
                'permission' => 'users.view',
                'callback'   => function () use ($student, $team, $studentRoleId): void {
                    $this->get('/admin/users')->assertOk();
                    $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertOk();
                    $this->getJson('/admin/users/' . $student->id . '/edit', [
                        'X-Requested-With' => 'XMLHttpRequest',
                    ])->assertOk();

                    $email = 'pivot-access-' . uniqid('', true) . '@example.com';
                    $this->postJson('/admin/users', [
                        'name'       => 'Access',
                        'lastname'   => 'Pivot',
                        'email'      => $email,
                        'role_id'    => $studentRoleId,
                        'team_ids'   => [$team->id],
                        'birthday'   => '2015-01-01',
                        'is_enabled' => 1,
                    ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

                    $this->patchJson('/admin/users/' . $student->id, [
                        'name'     => $student->name,
                        'lastname' => $student->lastname,
                        'team_ids' => [$team->id],
                    ])->assertOk();
                },
            ],
            [
                'label'      => 'dashboard',
                'permission' => 'dashboard.view',
                'callback'   => function () use ($student, $team): void {
                    $this->get(route('dashboard'))->assertOk();
                    $this->getJson(route('getUserDetails', ['userId' => $student->id]))->assertOk();
                    $this->getJson(route('getTeamDetails', [
                        'teamId'   => $team->id,
                        'teamName' => $team->title,
                    ]))->assertOk();
                },
            ],
            [
                'label'      => 'setting-prices-users',
                'permission' => 'setPrices.view',
                'callback'   => function (): void {
                    $this->get(route('admin.settingPrices.users'))->assertOk();
                },
            ],
            [
                'label'      => 'reports-payments',
                'permission' => 'reports.view',
                'callback'   => function () use ($student): void {
                    $this->get(route('payments'))->assertOk();
                    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                        ->getJson(route('payments.getPayments', ['filter_user_id' => $student->id]))
                        ->assertOk();
                    $this->getJson(route('reports.ltv.data', ['filter_user_id' => $student->id]))->assertOk();
                },
            ],
            [
                'label'      => 'contracts-lookups',
                'permission' => 'contracts.view',
                'callback'   => function () use ($student): void {
                    $this->get(route('contracts.index'))->assertOk();
                    $this->getJson(route('contracts.users.search', ['q' => 'FullAccess']))->assertOk();
                    $this->getJson(route('contracts.user.group', ['user_id' => $student->id]))->assertOk();
                },
            ],
            [
                'label'      => 'chat-users',
                'permission' => 'messages.view',
                'callback'   => function (): void {
                    $this->get(route('chat.index'))->assertOk();
                    $this->getJson('/chat/api/users')->assertOk();
                },
            ],
            [
                'label'      => 'my-group',
                'permission' => 'myGroup.view',
                'callback'   => function () use ($student): void {
                    $this->actingAs($student);
                    session(['current_partner' => $this->partner->id, '2fa:passed' => true]);

                    $this->get(route('my-group.index'))->assertOk();
                    $this->getJson(route('my-group.data'))->assertOk();

                    $this->actingAs($this->user);
                    session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
                },
            ],
            [
                'label'      => 'account-user',
                'permission' => 'account.user.view',
                'callback'   => function () use ($student): void {
                    $this->actingAs($student);
                    session(['current_partner' => $this->partner->id, '2fa:passed' => true]);

                    $this->get(route('account.user.edit'))->assertOk();

                    $this->actingAs($this->user);
                    session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
                },
            ],
        ];
    }

    public function test_guest_is_denied_on_student_team_pivot_endpoints(): void
    {
        Auth::logout();

        $this->get(route('schedule.index'))->assertRedirect();
        $this->postJson(route('user.sync.teams', $this->student), ['team_ids' => []])->assertUnauthorized();
        $this->getJson('/admin/users/data?draw=1')->assertUnauthorized();
        $this->getJson(route('getUserDetails', ['userId' => $this->student->id]))->assertUnauthorized();
        $this->get(route('my-group.index'))->assertRedirect();
    }

    public function test_user_without_permissions_gets_403_on_student_team_pivot_endpoints(): void
    {
        foreach ($this->authorizedEndpointGroups() as $group) {
            $actor = $this->createUserWithoutPermission($group['permission'], $this->partner);
            $this->actingAs($actor);
            session(['current_partner' => $this->partner->id, '2fa:passed' => true]);

            try {
                match ($group['label']) {
                    'schedule' => $this->assertScheduleForbidden(),
                    'admin-users' => $this->assertAdminUsersForbidden(),
                    'dashboard' => $this->assertDashboardForbidden(),
                    'setting-prices-users' => $this->get(route('admin.settingPrices.users'))->assertForbidden(),
                    'reports-payments' => $this->assertReportsForbidden(),
                    'contracts-lookups' => $this->assertContractsForbidden(),
                    'chat-users' => $this->assertChatForbidden(),
                    'my-group' => $this->assertMyGroupForbidden(),
                    'account-user' => $this->assertAccountForbidden(),
                    default => $this->fail('Unknown group: ' . $group['label']),
                };
            } catch (\Throwable $e) {
                $this->fail(sprintf('403 check failed for group "%s": %s', $group['label'], $e->getMessage()));
            }
        }
    }

    public function test_authorized_user_all_student_team_pivot_endpoints_return_ok(): void
    {
        foreach ($this->authorizedEndpointGroups() as $group) {
            $actor = $this->createUserWithoutPermission($group['permission'], $this->partner);
            $this->grantPermissionForUser($actor, $group['permission']);
            $this->actingAs($actor);
            session(['current_partner' => $this->partner->id, '2fa:passed' => true]);

            try {
                ($group['callback'])();
            } catch (\Throwable $e) {
                $this->fail(sprintf('200 check failed for group "%s": %s', $group['label'], $e->getMessage()));
            }
        }
    }

    public function test_schedule_journal_page_contains_team_pivot_ui_markers(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $this->grantPermissionForUser($actor, 'schedule.view');
        $this->actingAs($actor);
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('id="chooseGroupModal"', false)
            ->assertSee('id="journalUserTeamIds"', false)
            ->assertSee('data-team-ids=', false)
            ->assertSee($this->student->full_name, false);
    }

    private function assertScheduleForbidden(): void
    {
        $this->get(route('schedule.index'))->assertForbidden();
        $this->getJson(route('schedule.cell-context', [
            'user_id' => $this->student->id,
            'date'    => '2026-06-15',
        ]))->assertForbidden();
        $this->postJson(route('user.sync.teams', $this->student), ['team_ids' => []])->assertForbidden();
        $this->getJson(route('user.schedule.info', $this->student))->assertForbidden();
    }

    private function assertAdminUsersForbidden(): void
    {
        $this->get('/admin/users')->assertForbidden();
        $this->getJson('/admin/users/data?draw=1')->assertForbidden();
        $this->getJson('/admin/users/' . $this->student->id . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertForbidden();
    }

    private function assertDashboardForbidden(): void
    {
        $this->get(route('dashboard'))->assertForbidden();
        $this->getJson(route('getUserDetails', ['userId' => $this->student->id]))->assertForbidden();
        $this->getJson(route('getTeamDetails', [
            'teamId'   => $this->team->id,
            'teamName' => $this->team->title,
        ]))->assertForbidden();
    }

    private function assertReportsForbidden(): void
    {
        $this->get(route('payments'))->assertForbidden();
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('payments.getPayments'))
            ->assertForbidden();
        $this->getJson(route('reports.ltv.data'))->assertForbidden();
    }

    private function assertContractsForbidden(): void
    {
        $this->get(route('contracts.index'))->assertForbidden();
        $this->getJson(route('contracts.user.group', ['user_id' => $this->student->id]))->assertForbidden();
    }

    private function assertChatForbidden(): void
    {
        $this->get(route('chat.index'))->assertForbidden();
        $this->getJson('/chat/api/users')->assertForbidden();
    }

    private function assertMyGroupForbidden(): void
    {
        $denied = $this->createUserWithoutPermission('myGroup.view', $this->partner);
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($denied, [$this->team->id]);

        $this->actingAs($denied);
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('my-group.index'))->assertForbidden();
        $this->getJson(route('my-group.data'))->assertForbidden();

        $this->actingAs($this->user);
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
    }

    private function assertAccountForbidden(): void
    {
        $denied = $this->createUserWithoutPermission('account.user.view', $this->partner);
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($denied, [$this->team->id]);

        $this->actingAs($denied);
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('account.user.edit'))->assertForbidden();

        $this->actingAs($this->user);
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
    }
}
