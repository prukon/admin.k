<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\Team;
use Illuminate\Support\Facades\Auth;

/**
 * Полный доступ create/edit/delete группы с toast-успехом: гость / без права / admin,
 * чужие глаголы не 500, изоляция партнёра.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TeamsCreateEditToastFullAccessFeatureTest extends TeamsCreateEditToastTestCase
{
    public function test_guest_json_and_web_mutations_never_return_500_or_success(): void
    {
        Auth::logout();
        $team = $this->makeTeam();

        foreach ($this->mutateCalls($team) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? []
            );
            $label = $item['method'].' '.$item['url'];
            $this->assertNotSame(500, $response->getStatusCode(), $label);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$label} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_manager_without_groups_view_gets_403_on_every_create_edit_endpoint(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);
        $team = $this->makeTeam();

        foreach ($this->mutateCalls($team) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? []
            );
            $label = $item['method'].' '.$item['url'];
            $this->assertNotSame(500, $response->getStatusCode(), $label);
            $response->assertForbidden();
        }
    }

    public function test_admin_with_groups_view_can_store_update_reload_edit_json_and_delete(): void
    {
        $this->actingAsGroupsViewer();

        $title = 'Тост full store '.uniqid('', true);
        $store = $this->postJson(route('admin.team.store'), $this->teamPayload([
            'title' => $title,
        ]), $this->ajaxHeaders());
        $this->assertNotSame(500, $store->getStatusCode());
        $store->assertOk()->assertJsonPath('message', 'Группа создана успешно');
        $id = (int) $store->json('team.id');
        $this->assertGreaterThan(0, $id);

        $edit = $this->getJson(route('admin.team.edit', $id), $this->ajaxHeaders());
        $this->assertNotSame(500, $edit->getStatusCode());
        $edit->assertOk()->assertJsonPath('id', $id);
        $this->assertNotSame('', trim((string) $edit->getContent()));

        $update = $this->patchJson(route('admin.team.update', $id), $this->teamPayload([
            'title' => $title.' обновлена',
        ]), $this->ajaxHeaders());
        $this->assertNotSame(500, $update->getStatusCode());
        $update->assertOk()->assertJsonPath('message', 'Группа успешно обновлена');

        $delete = $this->deleteJson(route('admin.team.delete', $id), [], $this->ajaxHeaders());
        $this->assertNotSame(500, $delete->getStatusCode());
        $delete->assertOk();
        $this->assertTrue(Team::withTrashed()->findOrFail($id)->trashed());
    }

    public function test_unsupported_methods_on_store_update_edit_and_delete_never_return_500_or_empty_200(): void
    {
        $this->actingAsGroupsViewer();
        $team = $this->makeTeam();

        $cases = [
            ['PUT', route('admin.team.store')],
            ['PATCH', route('admin.team.store')],
            ['DELETE', route('admin.team.store')],
            ['GET', route('admin.team.update', $team->id)],
            ['POST', route('admin.team.update', $team->id)],
            ['PUT', route('admin.team.update', $team->id)],
            ['DELETE', route('admin.team.update', $team->id)],
            ['POST', route('admin.team.edit', $team->id)],
            ['PUT', route('admin.team.edit', $team->id)],
            ['PATCH', route('admin.team.edit', $team->id)],
            ['DELETE', route('admin.team.edit', $team->id)],
            ['GET', route('admin.team.delete', $team)],
            ['POST', route('admin.team.delete', $team)],
            ['PUT', route('admin.team.delete', $team)],
            ['PATCH', route('admin.team.delete', $team)],
        ];

        foreach ($cases as [$method, $url]) {
            foreach ([true, false] as $asJson) {
                $response = $asJson
                    ? $this->json($method, $url, $this->teamPayload(), $this->ajaxHeaders())
                    : $this->call($method, $url, array_merge($this->teamPayload(), [
                        '_token' => csrf_token(),
                    ]));

                $label = ($asJson ? 'JSON' : 'web')." {$method} {$url}";
                $this->assertNotSame(500, $response->getStatusCode(), "{$label} → 500");
                $this->assertContains(
                    $response->getStatusCode(),
                    [200, 302, 401, 403, 404, 405, 419, 422],
                    "{$label} → {$response->getStatusCode()}"
                );
                if ($response->getStatusCode() === 200 && in_array($method, ['GET', 'PUT', 'DELETE'], true)) {
                    $this->assertNotSame('', trim((string) $response->getContent()), "{$label} пустой 200");
                }
            }
        }
    }

    public function test_store_does_not_leak_into_other_partner(): void
    {
        $this->actingAsGroupsViewer();
        $title = 'Изоляция тост '.uniqid('', true);

        $this->postJson(route('admin.team.store'), $this->teamPayload([
            'title' => $title,
        ]), $this->ajaxHeaders())->assertOk();

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title'      => $title,
        ]);
        $this->assertDatabaseMissing('teams', [
            'partner_id' => $this->foreignPartner->id,
            'title'      => $title,
        ]);
    }

    public function test_update_foreign_partner_team_is_not_found_for_web_and_json(): void
    {
        $this->actingAsGroupsViewer();
        $foreign = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title'      => 'Чужая',
        ]);

        $this->patchJson(route('admin.team.update', $foreign->id), $this->teamPayload([
            'title' => 'Взлом',
        ]), $this->ajaxHeaders())->assertNotFound();

        $web = $this->patch(route('admin.team.update', $foreign->id), $this->teamPayload([
            'title' => 'Взлом',
        ]));
        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertNotSame(200, $web->getStatusCode());
        $this->assertSame('Чужая', $foreign->fresh()->title);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function mutateCalls(Team $team): array
    {
        $payload = $this->teamPayload([
            'title' => 'Тост mutate '.uniqid('', true),
        ]);

        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.team.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'  => 'POST',
                'url'     => route('admin.team.store'),
                'data'    => $payload,
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'GET',
                'url'     => route('admin.team.edit', $team->id),
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'PATCH',
                'url'     => route('admin.team.update', $team->id),
                'data'    => $payload,
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'DELETE',
                'url'     => route('admin.team.delete', $team),
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
        ];
    }
}
