<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Полный доступ create/edit ученика с toast-успехом: гость / без права / admin,
 * чужие глаголы не 500, изоляция партнёра.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersCreateEditToastFullAccessFeatureTest extends AdminUsersCreateEditToastTestCase
{
    public function test_guest_json_and_web_mutations_never_return_500_or_success(): void
    {
        Auth::logout();
        $student = $this->makeStudent();

        foreach ($this->mutateCalls($student) as $item) {
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

    public function test_manager_without_users_view_gets_403_on_every_create_edit_endpoint(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $student = $this->makeStudent();

        foreach ($this->mutateCalls($student) as $item) {
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

    public function test_admin_with_users_view_can_store_update_and_reload_edit_json(): void
    {
        $this->actingAsUsersViewer();

        $email = 'toast-full-store-'.uniqid('', true).'@example.test';
        $store = $this->postJson(route('admin.user.store'), $this->studentPayload([
            'email' => $email,
        ]), $this->ajaxHeaders());
        $this->assertNotSame(500, $store->getStatusCode());
        $store->assertOk()->assertJsonPath('message', 'Клиент создан успешно');
        $id = (int) $store->json('user.id');
        $this->assertGreaterThan(0, $id);

        $edit = $this->getJson(route('admin.user.edit', $id), $this->ajaxHeaders());
        $this->assertNotSame(500, $edit->getStatusCode());
        $edit->assertOk()->assertJsonPath('user.id', $id);
        $this->assertNotSame('', trim((string) $edit->getContent()));

        $update = $this->patchJson(route('admin.user.update', $id), [
            'name'       => 'Обновлён',
            'lastname'   => 'Тостов',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], $this->ajaxHeaders());
        $this->assertNotSame(500, $update->getStatusCode());
        $update->assertOk()->assertJsonPath('message', 'Клиент успешно обновлён');
    }

    public function test_unsupported_methods_on_store_update_and_edit_never_return_500_or_empty_200(): void
    {
        $this->actingAsUsersViewer();
        $student = $this->makeStudent();

        $cases = [
            ['PUT', route('admin.user.store')],
            ['PATCH', route('admin.user.store')],
            ['DELETE', route('admin.user.store')],
            ['GET', route('admin.user.update', $student->id)],
            ['POST', route('admin.user.update', $student->id)],
            ['PUT', route('admin.user.update', $student->id)],
            ['DELETE', route('admin.user.update', $student->id)],
            ['POST', route('admin.user.edit', $student->id)],
            ['PUT', route('admin.user.edit', $student->id)],
            ['PATCH', route('admin.user.edit', $student->id)],
            ['DELETE', route('admin.user.edit', $student->id)],
        ];

        foreach ($cases as [$method, $url]) {
            foreach ([true, false] as $asJson) {
                $response = $asJson
                    ? $this->json($method, $url, $this->studentPayload(), $this->ajaxHeaders())
                    : $this->call($method, $url, array_merge($this->studentPayload(), [
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

    public function test_store_to_foreign_partner_context_does_not_leak_into_other_partner(): void
    {
        $this->actingAsUsersViewer();
        $email = 'toast-isolation-'.uniqid('', true).'@example.test';

        $this->postJson(route('admin.user.store'), $this->studentPayload([
            'email' => $email,
        ]), $this->ajaxHeaders())->assertOk();

        $this->assertDatabaseHas('users', [
            'partner_id' => $this->partner->id,
            'email'      => $email,
        ]);
        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->foreignPartner->id,
            'email'      => $email,
        ]);
    }

    public function test_update_foreign_partner_student_is_not_found_for_web_and_json(): void
    {
        $this->actingAsUsersViewer();
        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Чужой',
            'lastname'   => 'Клиент',
        ]);

        $this->patchJson(route('admin.user.update', $foreign->id), [
            'name'       => 'Взлом',
            'lastname'   => 'Клиент',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], $this->ajaxHeaders())->assertNotFound();

        $web = $this->patch(route('admin.user.update', $foreign->id), [
            'name'       => 'Взлом',
            'lastname'   => 'Клиент',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ]);
        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertNotSame(200, $web->getStatusCode());
        $this->assertSame('Чужой', $foreign->fresh()->name);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function mutateCalls(User $student): array
    {
        $payload = $this->studentPayload([
            'email' => 'toast-mutate-'.uniqid('', true).'@example.test',
        ]);

        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.user1'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'  => 'POST',
                'url'     => route('admin.user.store'),
                'data'    => $payload,
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'GET',
                'url'     => route('admin.user.edit', $student->id),
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'PATCH',
                'url'     => route('admin.user.update', $student->id),
                'data'    => [
                    'name'       => 'Патч',
                    'lastname'   => 'Тостов',
                    'role_id'    => $this->studentRoleId(),
                    'is_enabled' => 1,
                ],
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
        ];
    }
}
