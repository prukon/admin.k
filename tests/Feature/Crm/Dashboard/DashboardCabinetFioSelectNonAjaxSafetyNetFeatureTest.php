<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

/**
 * Native GET без X-Requested-With: JSON-ручки не белая страница;
 * мутации URI не сырой 200. POST /cabinet — тот же index.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class DashboardCabinetFioSelectNonAjaxSafetyNetFeatureTest extends DashboardCabinetFioSelectTestCase
{
    public function test_native_get_user_details_returns_json_not_blank_html(): void
    {
        $this->actingAsCrmAdmin();

        $response = $this->from(route('dashboard'))
            ->get(route('getUserDetails', ['userId' => $this->studentInTeam->id]));

        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $this->studentInTeam->id);
        $this->assertStringContainsString(
            'json',
            strtolower((string) $response->headers->get('content-type'))
        );
        $this->assertStringNotContainsString('<html', strtolower((string) $response->getContent()));
    }

    public function test_native_get_user_details_for_admin_returns_json_success_false(): void
    {
        $this->actingAsCrmAdmin();

        $response = $this->from(route('dashboard'))
            ->get(route('getUserDetails', ['userId' => $this->adminStaff->id]));

        $response
            ->assertOk()
            ->assertJson(['success' => false]);
        $this->assertStringNotContainsString('<html', strtolower((string) $response->getContent()));
    }

    public function test_native_get_team_details_all_returns_json_students_only(): void
    {
        $this->actingAsCrmAdmin();

        $response = $this->from(route('dashboard'))
            ->get(route('getTeamDetails', ['teamName' => 'all']));

        $this->assertNotSame(500, $response->getStatusCode());
        $json = $response->assertOk()->json();
        $this->assertTeamDetailsPayloadOnlyEnabledStudents($json);
        $this->assertStaffAndDisabledNotInIds($this->idsFromPayload($json['usersTeam']));
        $this->assertStringNotContainsString('<html', strtolower((string) $response->getContent()));
    }

    public function test_native_post_cabinet_keeps_fio_select_filtered(): void
    {
        $this->actingAsCrmAdmin();

        $response = $this->from(route('dashboard'))->post(route('dashboard'));

        $response->assertOk();
        $this->assertNotSame(500, $response->getStatusCode());
        $html = (string) $response->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="single-select-user"', $html);
        $this->assertStringContainsString('data-user-id="'.$this->studentInTeam->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->adminStaff->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->trainerStaff->id.'"', $html);
    }

    public function test_native_post_cabinet_with_invalid_title_redirects_with_field_error(): void
    {
        $this->actingAsCrmAdmin();

        $response = $this->from(route('dashboard'))->post(route('dashboard'), [
            'title' => ['not-a-string'],
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors(['title']);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_mutating_json_endpoints_are_not_empty_200(): void
    {
        $this->actingAsCrmAdmin();

        $urls = [
            route('getUserDetails', ['userId' => $this->studentInTeam->id]),
            route('getTeamDetails', ['teamName' => 'all']),
            route('getTeamDetails', [
                'teamId' => $this->team->id,
                'teamName' => $this->team->title,
            ]),
        ];

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            foreach ($urls as $url) {
                $html = $this->from(route('dashboard'))->call($method, $url);
                $this->assertNotSame(500, $html->getStatusCode(), $method.' '.$url.' HTML не 500');
                $this->assertNotSame(200, $html->getStatusCode(), $method.' '.$url.' HTML не пустой 200');
                $this->assertContains(
                    $html->getStatusCode(),
                    [404, 405, 419],
                    $method.' '.$url.' HTML → '.$html->getStatusCode()
                );

                $json = $this->json($method, $url, [], $this->ajaxHeaders());
                $this->assertNotSame(500, $json->getStatusCode(), $method.' '.$url.' JSON не 500');
                $this->assertNotSame(200, $json->getStatusCode(), $method.' '.$url.' JSON не пустой 200');
                $this->assertContains(
                    $json->getStatusCode(),
                    [404, 405, 419],
                    $method.' '.$url.' JSON → '.$json->getStatusCode()
                );
            }
        }
    }
}
