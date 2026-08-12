<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Support\CabinetLessonPackagePermission;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * HTTP-матрица консоли для прав cabinetPackages.*: guest / 403 / 200, JSON, изоляция.
 *
 * Новых store/update нет — non-AJAX safety-net записи не применим.
 * POST /cabinet — тот же index (FilterRequest), не пустой 200.
 *
 * @see DashboardCabinetPackagesTypeAccessFeatureTest
 * @see DashboardPackageAssignmentsAccessFeatureTest
 */
final class DashboardCabinetPackagesPageFullAccessFeatureTest extends StudentTeamPivotTestCase
{
    private Team $team;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Cabinet-Type-Access',
        ]);

        $this->student = $this->makeStudentWithTeams([$this->team], [
            'name' => 'Type',
            'lastname' => 'Access',
        ]);
        $this->grantPermissionForUser($this->student, CabinetLessonPackagePermission::FIXED);
        $this->createAssignment($this->student, 4_400, 'Доступный фикс');
    }

    public function test_guest_is_denied_on_all_cabinet_endpoints(): void
    {
        Auth::logout();

        foreach ($this->cabinetEndpointsPayload() as $item) {
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
            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_user_without_dashboard_view_gets_403_even_with_all_type_permissions(): void
    {
        $actor = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        foreach (CabinetLessonPackagePermission::ALL as $permission) {
            $this->grantPermissionForUser($actor, $permission);
        }

        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        foreach ($this->cabinetEndpointsPayload() as $item) {
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
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_student_with_type_permission_gets_200_on_all_cabinet_endpoints(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        foreach ($this->cabinetEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ($item['method'] === 'GET' && str_contains($item['url'], '/cabinet')
                    ? ['HTTP_ACCEPT' => 'text/html']
                    : ['HTTP_ACCEPT' => 'application/json'])
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "С правом: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame('', trim((string) $response->getContent()));
        }
    }

    public function test_cabinet_html_shows_package_and_is_not_blank_screen(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertIsString($html);
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('Консоль', $html);
        $this->assertStringContainsString('Назначенные абонементы', $html);
        $this->assertStringContainsString('Доступный фикс', $html);
        $this->assertStringContainsString('id="dashboard-lesson-packages"', $html);
    }

    public function test_non_ajax_post_cabinet_returns_200_and_keeps_packages_visible(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $response = $this->from(route('dashboard'))->post(route('dashboard'));

        $response->assertOk();
        $this->assertNotSame(500, $response->getStatusCode());
        $html = (string) $response->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('Доступный фикс', $html);
        $this->assertStringContainsString('Назначенные абонементы', $html);
    }

    public function test_ajax_post_cabinet_returns_html_with_packages_not_empty_payload(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $response = $this->from(route('dashboard'))
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('dashboard'));

        $response->assertOk();
        $this->assertNotSame(500, $response->getStatusCode());
        $html = (string) $response->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('Доступный фикс', $html);
        $this->assertStringContainsString('Назначенные абонементы', $html);
    }

    public function test_non_ajax_post_cabinet_with_invalid_title_redirects_with_field_error(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $response = $this->from(route('dashboard'))->post(route('dashboard'), [
            'title' => '',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors('title');
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_json_post_cabinet_with_invalid_title_returns_422_with_field_error(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->postJson(route('dashboard'), [
            'title' => '',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_unsupported_methods_on_cabinet_do_not_return_500_or_empty_200(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        foreach (['PATCH', 'PUT', 'DELETE'] as $method) {
            foreach ($this->cabinetEndpointsPayload() as $item) {
                $response = $this->call($method, $item['url']);

                $this->assertNotSame(
                    500,
                    $response->getStatusCode(),
                    "{$method} {$item['url']} → 500"
                );
                $this->assertNotSame(
                    200,
                    $response->getStatusCode(),
                    "{$method} {$item['url']} не должен давать пустой/бессмысленный 200"
                );
                $this->assertContains(
                    $response->getStatusCode(),
                    [302, 403, 404, 405, 419],
                    "{$method} {$item['url']} → {$response->getStatusCode()}"
                );
            }
        }
    }

    public function test_get_user_details_json_contract_does_not_include_lesson_packages(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $json = $this->getJson(route('getUserDetails', ['userId' => $this->student->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'user',
                'userTeam',
                'userPrice',
                'scheduleUser',
            ])
            ->json();

        $this->assertArrayNotHasKey('userLessonPackages', $json);
        $this->assertArrayNotHasKey('user_lesson_packages', $json);
        $this->assertIsArray($json['userPrice'] ?? null);
    }

    public function test_get_user_details_of_foreign_partner_student_returns_success_false_not_500(): void
    {
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title' => 'Foreign-Type',
        ]);
        $foreignStudent = $this->makeStudentWithTeams([$foreignTeam], [
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->getJson(route('getUserDetails', ['userId' => $foreignStudent->id]))
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    public function test_get_user_details_without_user_id_returns_success_false_not_500(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->getJson(route('getUserDetails'))
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    public function test_get_team_details_json_is_200_not_empty(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $json = $this->getJson(route('getTeamDetails', [
            'teamId' => $this->team->id,
            'teamName' => $this->team->title,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertNotSame('', trim((string) json_encode($json)));
        $this->assertIsArray($json['usersTeam'] ?? null);
    }

    public function test_cabinet_does_not_show_foreign_partner_assignment(): void
    {
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title' => 'Foreign-ULP',
        ]);
        $foreignStudent = $this->makeStudentWithTeams([$foreignTeam], [
            'partner_id' => $this->foreignPartner->id,
        ]);
        $this->grantPermissionForUser($foreignStudent, CabinetLessonPackagePermission::FIXED, $this->foreignPartner->id);

        $package = LessonPackage::factory()->forPartner($this->foreignPartner->id)->create([
            'name' => 'Чужой тип-абонемент',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
        ]);
        UserLessonPackage::query()->create([
            'user_id' => $foreignStudent->id,
            'lesson_package_id' => $package->id,
            'team_id' => $foreignTeam->id,
            'lessons_total' => (int) $package->lessons_count,
            'lessons_remaining' => (int) $package->lessons_count,
            'fee_amount_cents' => 999900,
            'is_paid' => false,
        ]);

        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Доступный фикс', $html);
        $this->assertStringNotContainsString('Чужой тип-абонемент', $html);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function cabinetEndpointsPayload(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('dashboard'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('getUserDetails', ['userId' => $this->student->id]),
            ],
            [
                'method' => 'GET',
                'url' => route('getTeamDetails', [
                    'teamId' => $this->team->id,
                    'teamName' => $this->team->title,
                ]),
            ],
        ];
    }

    private function createAssignment(User $student, float $feeAmount, string $packageName): UserLessonPackage
    {
        $package = LessonPackage::factory()->forPartner((int) $student->partner_id)->create([
            'name' => $packageName,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
        ]);

        return UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $this->team->id,
            'lessons_total' => (int) $package->lessons_count,
            'lessons_remaining' => (int) $package->lessons_count,
            'fee_amount_cents' => (int) round($feeAmount * 100),
            'is_paid' => false,
        ]);
    }
}
