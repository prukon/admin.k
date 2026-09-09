<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * HTTP-матрица селекта «ФИО»: гость / 403 / 200, изоляция, PATCH/DELETE не пустой 200.
 */
final class DashboardCabinetFioSelectFullAccessFeatureTest extends DashboardCabinetFioSelectTestCase
{
    public function test_guest_is_denied_on_all_fio_select_endpoints(): void
    {
        Auth::logout();

        foreach ($this->fioSelectEndpointsPayload() as $item) {
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

    public function test_user_without_dashboard_view_gets_403_on_all_fio_select_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        foreach ($this->fioSelectEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(403, $response->getStatusCode(), "{$item['method']} {$item['url']}");
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_admin_gets_200_on_all_fio_select_endpoints_with_non_empty_body(): void
    {
        $this->actingAsCrmAdmin();

        foreach ($this->fioSelectEndpointsPayload() as $item) {
            $headers = $item['headers'] ?? (
                $item['method'] === 'GET' && str_contains($item['url'], '/cabinet')
                    ? ['HTTP_ACCEPT' => 'text/html']
                    : ['HTTP_ACCEPT' => 'application/json']
            );

            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $headers
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

    public function test_unsupported_methods_on_cabinet_and_details_are_not_empty_200(): void
    {
        $this->actingAsCrmAdmin();

        foreach (['PATCH', 'PUT', 'DELETE'] as $method) {
            foreach ($this->fioSelectEndpointsPayload() as $item) {
                $response = $this->call($method, $item['url']);

                $this->assertNotSame(500, $response->getStatusCode(), "{$method} {$item['url']} → 500");
                $this->assertNotSame(200, $response->getStatusCode(), "{$method} {$item['url']} не должен давать 200");
                $this->assertContains(
                    $response->getStatusCode(),
                    [302, 403, 404, 405, 419],
                    "{$method} {$item['url']} → {$response->getStatusCode()}"
                );
            }
        }
    }

    public function test_user_without_partner_is_logged_out_from_cabinet(): void
    {
        $actor = User::factory()->create(['partner_id' => null]);
        $this->actingAs($actor)->withSession([]);

        $this->get(route('dashboard'))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'Ваша организация недоступна.',
            ]);
        $this->assertGuest();
    }

    public function test_get_user_details_does_not_leak_foreign_partner_student(): void
    {
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);
        $foreignStudent = $this->makeStudentWithTeams([$foreignTeam], [
            'partner_id' => $this->foreignPartner->id,
            'lastname' => 'ForeignFio',
        ]);

        $this->actingAsCrmAdmin();

        $this->getJson(route('getUserDetails', ['userId' => $foreignStudent->id]))
            ->assertOk()
            ->assertJson(['success' => false])
            ->assertJsonMissingPath('user');

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-user-id="'.$foreignStudent->id.'"', $html);
        $this->assertStringNotContainsString('ForeignFio', $html);
    }

    public function test_get_team_details_does_not_leak_foreign_partner_team(): void
    {
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title' => 'Foreign-Fio-Team',
        ]);

        $this->actingAsCrmAdmin();

        $this->getJson(route('getTeamDetails', [
            'teamId' => $foreignTeam->id,
            'teamName' => $foreignTeam->title,
        ]))
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    public function test_superadmin_gets_200_and_fio_select_still_excludes_staff(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('id="single-select-user"', $html);
        $this->assertStringContainsString('data-user-id="'.$this->studentInTeam->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->adminStaff->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->user->id.'"', $html);

        $json = $this->getJson(route('getTeamDetails', ['teamName' => 'all']))
            ->assertOk()
            ->json();
        $this->assertTeamDetailsPayloadOnlyEnabledStudents($json);
        $this->assertStaffAndDisabledNotInIds($this->idsFromPayload($json['usersTeam']));
        $this->assertNotContains((int) $this->user->id, $this->idsFromPayload($json['usersTeam']));
    }
}
