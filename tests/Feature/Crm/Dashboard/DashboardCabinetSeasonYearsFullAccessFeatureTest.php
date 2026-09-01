<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Dashboard\Concerns\InteractsWithCabinetSeasonYears;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * HTTP-матрица /cabinet для шапок сезонов 2026–2027.
 * Новых store нет — FilterRequest на POST /cabinet: 302/422 по title.
 */
final class DashboardCabinetSeasonYearsFullAccessFeatureTest extends StudentTeamPivotTestCase
{
    use InteractsWithCabinetSeasonYears;

    private Team $team;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'Europe/Moscow'));

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Season-Years-Access',
        ]);

        $this->student = $this->makeStudentWithTeams([$this->team], [
            'name'     => 'Сезон',
            'lastname' => 'Родитель',
        ]);
        $this->insertUserPrice($this->student, [
            'new_month' => '2026-09-01',
            'price'     => 4_500,
            'is_paid'   => 0,
        ], $this->team);
    }

    public function test_guest_is_denied_on_all_cabinet_season_endpoints(): void
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

    public function test_user_without_dashboard_view_gets_403_on_all_cabinet_season_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('dashboard.view', $this->partner);
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

    public function test_parent_with_permission_gets_200_and_season_2026_2027_on_get_and_post(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $get = $this->get(route('dashboard'));
        $get->assertOk();
        $this->assertNotSame('', trim((string) $get->getContent()));
        $this->assertChargeHasJsSeasonCell((string) $get->getContent(), '2026-09-01');

        $post = $this->from(route('dashboard'))->post(route('dashboard'));
        $post->assertOk();
        $this->assertNotSame(500, $post->getStatusCode());
        $this->assertChargeHasJsSeasonCell((string) $post->getContent(), '2026-09-01');

        $ajax = $this->from(route('dashboard'))
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('dashboard'));
        $ajax->assertOk();
        $this->assertChargeHasJsSeasonCell((string) $ajax->getContent(), '2026-09-01');
    }

    public function test_student_gets_200_on_json_helpers_without_empty_payload(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $details = $this->getJson(route('getUserDetails', ['userId' => $this->student->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'user', 'userPrice', 'scheduleUser'])
            ->json();

        $this->assertIsArray($details['userPrice']);
        $this->assertContains('2026-09-01', $this->priceMonthKeys($details['userPrice']));

        $team = $this->getJson(route('getTeamDetails', [
            'teamId'   => $this->team->id,
            'teamName' => $this->team->title,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertNotSame('', trim((string) json_encode($team)));
        $this->assertIsArray($team['usersTeam'] ?? null);
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

    public function test_get_user_details_does_not_leak_foreign_september_2026_price(): void
    {
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title'      => 'Foreign-Season',
        ]);
        $foreignStudent = $this->makeStudentWithTeams([$foreignTeam], [
            'partner_id' => $this->foreignPartner->id,
        ]);
        $this->insertUserPrice($foreignStudent, [
            'new_month' => '2026-09-01',
            'price'     => 77_000,
            'is_paid'   => 0,
        ], $foreignTeam);

        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->getJson(route('getUserDetails', ['userId' => $foreignStudent->id]))
            ->assertOk()
            ->assertJson(['success' => false])
            ->assertJsonMissingPath('userPrice');

        $ownHtml = (string) $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('77000', $ownHtml);
        $this->assertChargeHasJsSeasonCell($ownHtml, '2026-09-01');
    }

    public function test_get_user_details_without_user_id_returns_success_false_not_500(): void
    {
        $this->actingAs($this->student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->getJson(route('getUserDetails'))
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function cabinetEndpointsPayload(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('dashboard'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('getUserDetails', ['userId' => $this->student->id]),
            ],
            [
                'method' => 'GET',
                'url'    => route('getTeamDetails', [
                    'teamId'   => $this->team->id,
                    'teamName' => $this->team->title,
                ]),
            ],
        ];
    }
}
