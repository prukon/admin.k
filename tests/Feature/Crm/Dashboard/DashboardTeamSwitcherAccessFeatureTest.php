<?php

namespace Tests\Feature\Crm\Dashboard;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * Контроль доступа к разделу «Консоль» (/cabinet) и AJAX-ручкам dashboard.
 */
final class DashboardTeamSwitcherAccessFeatureTest extends StudentTeamPivotTestCase
{
    private User $student;

    private Team $teamA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        $this->teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Access-A',
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Access-B',
        ]);

        $this->student = $this->makeStudentWithTeams([$this->teamA, $teamB], [
            'name'     => 'Access',
            'lastname' => 'Pivot',
        ]);
    }

    public function test_guest_is_denied_on_all_dashboard_endpoints(): void
    {
        Auth::logout();

        foreach ($this->dashboardEndpointsPayload() as $item) {
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
            $this->assertNotEquals(
                500,
                $response->getStatusCode(),
                "Гость: {$item['method']} {$item['url']} не должен отдавать 500"
            );
        }
    }

    public function test_user_without_dashboard_view_gets_403_on_all_dashboard_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        foreach ($this->dashboardEndpointsPayload() as $item) {
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
                "Без dashboard.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_student_with_dashboard_view_gets_expected_status_on_all_dashboard_endpoints(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        foreach ($this->dashboardEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                $item['expected'],
                $response->getStatusCode(),
                "С правом: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotEquals(
                500,
                $response->getStatusCode(),
                "С правом: {$item['method']} {$item['url']} не должен отдавать 500"
            );
        }
    }

    public function test_get_user_details_json_contract_for_multi_team_student(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $json = $this->getJson(route('getUserDetails', ['userId' => $this->student->id]))
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'user',
                'userTeam',
                'userTeamsLabel',
                'userPrice',
                'scheduleUser',
                'formattedBirthday',
                'userFields',
                'userFieldValues',
                'allFields',
            ])
            ->json();

        $this->assertTrue($json['success']);
        $this->assertStringContainsString('Access-A', (string) ($json['userTeamsLabel'] ?? ''));
        $this->assertStringContainsString('Access-B', (string) ($json['userTeamsLabel'] ?? ''));
        $this->assertIsArray($json['userPrice']);
    }

    public function test_get_user_details_includes_team_id_on_each_price_row(): void
    {
        $this->insertUserPrice($this->student, [
            'new_month' => '2025-10-01',
            'price'     => 4500,
            'is_paid'   => 0,
        ], $this->teamA);

        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $json = $this->getJson(route('getUserDetails', ['userId' => $this->student->id]))
            ->assertOk()
            ->json();

        $this->assertTrue($json['success']);
        $this->assertNotEmpty($json['userPrice']);

        $teamIds = collect($json['userPrice'])->pluck('team_id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $this->teamA->id, $teamIds);
    }

    public function test_get_team_details_json_contract_for_multi_team_student_group(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $json = $this->getJson(route('getTeamDetails', [
            'teamId'   => $this->teamA->id,
            'teamName' => $this->teamA->title,
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'team',
                'teamWeekDayId',
                'usersTeam',
                'userWithoutTeam',
            ])
            ->json();

        $this->assertTrue($json['success']);
        $ids = collect($json['usersTeam'] ?? [])->pluck('id')->all();
        $this->assertContains($this->student->id, $ids);
    }

    public function test_get_user_details_without_user_id_returns_success_false_not_500(): void
    {
        $this->actingAs($this->student);

        $this->getJson(route('getUserDetails'))
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    public function test_get_team_details_without_team_id_for_named_team_returns_success_false_not_500(): void
    {
        $this->actingAs($this->student);

        $this->getJson(route('getTeamDetails', [
            'teamName' => $this->teamA->title,
        ]))
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    /**
     * @return list<array{
     *     method: string,
     *     url: string,
     *     expected: int,
     *     data?: array<string, mixed>,
     *     headers?: array<string, string>
     * }>
     */
    private function dashboardEndpointsPayload(): array
    {
        return [
            [
                'method'   => 'GET',
                'url'      => route('dashboard'),
                'expected' => 200,
                'headers'  => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'   => 'GET',
                'url'      => route('getUserDetails', ['userId' => $this->student->id]),
                'expected' => 200,
            ],
            [
                'method'   => 'GET',
                'url'      => route('getTeamDetails', [
                    'teamId'   => $this->teamA->id,
                    'teamName' => $this->teamA->title,
                ]),
                'expected' => 200,
            ],
        ];
    }
}
