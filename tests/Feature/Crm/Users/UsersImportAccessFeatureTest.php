<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\PartnerLegalEntity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UsersImportTestHelpers;

/**
 * Импорт учеников: доступ к странице и endpoint'ам (guest / users.view / users.import).
 */
final class UsersImportAccessFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;
    use UsersImportTestHelpers;

    private PartnerLegalEntity $legalEntity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->requirePhpSpreadsheet();
        $this->legalEntity = $this->createImportLegalEntity();
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function importEndpointsPayload(?UploadedFile $file = null): array
    {
        $file ??= $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Email ученика' => 'access-matrix-' . uniqid('', true) . '@example.test',
            ]),
        ]);

        return [
            [
                'method' => 'GET',
                'url' => route('admin.users.import.template'),
                'headers' => ['HTTP_ACCEPT' => 'application/octet-stream'],
            ],
            [
                'method' => 'POST',
                'url' => route('admin.users.import.preview'),
                'data' => ['file' => $file],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
            [
                'method' => 'POST',
                'url' => route('admin.users.import.commit'),
                'data' => ['import_token' => '00000000-0000-4000-8000-000000000000'],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            ],
        ];
    }

    public function test_guest_cannot_access_users_page_and_import_endpoints(): void
    {
        Auth::logout();

        $this->get(route('admin.user1'))->assertRedirect();

        foreach ($this->importEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json'],
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_users_view_gets_403_on_page_and_import_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('admin.user1'))->assertForbidden();

        foreach ($this->importEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json'],
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без users.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_users_view_but_without_users_import_gets_403_on_import_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('users.import', $this->partner);
        $this->grantUsersView($actor);
        $this->actingAs($actor);

        $html = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="usersImportModal"', $html);
        $this->assertStringNotContainsString('>Импорт</span>', $html);

        foreach ($this->importEndpointsPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json'],
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без users.import: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_users_view_and_users_import_can_access_page_and_import_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('users.import', $this->partner);
        $this->grantUsersView($actor);
        $this->grantPermission($actor, 'users.import');
        $this->actingAs($actor);

        $html = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertStringContainsString('id="usersImportModal"', $html);
        $this->assertStringContainsString('>Импорт</span>', $html);
        $this->assertStringContainsString('id="users-import-check-btn"', $html);
        $this->assertStringContainsString('id="users-import-commit-btn"', $html);
        $this->assertStringContainsString('id="users-import-step-success"', $html);

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Email ученика' => 'access-ok-' . uniqid('', true) . '@example.test',
            ]),
        ]);

        $this->get(route('admin.users.import.template'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition');

        $preview = $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('valid', true);

        $this->assertNotSame('', trim((string) $preview->getContent()));

        $token = (string) $preview->json('import_token');
        $this->assertNotSame('', $token);

        $commit = $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $token,
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['message', 'created', 'updated']);

        $this->assertNotSame('', trim((string) $commit->getContent()));
        $this->assertNotSame(500, $commit->getStatusCode());
    }

    public function test_import_endpoints_never_return_empty_200_or_500_for_authorized_actor(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.import');

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Email ученика' => 'matrix-' . uniqid('', true) . '@example.test',
            ]),
        ]);

        $preview = $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertOk();

        $matrix = [
            ['GET', route('admin.users.import.template'), [], 200],
            ['POST', route('admin.users.import.commit'), ['import_token' => (string) $preview->json('import_token')], 200],
        ];

        foreach ($matrix as [$method, $url, $data, $expectedStatus]) {
            $response = $this->call(
                $method,
                $url,
                $data,
                [],
                [],
                $this->importAjaxHeaders(),
            );

            $this->assertSame(
                $expectedStatus,
                $response->getStatusCode(),
                "{$method} {$url} → {$response->getStatusCode()}, body: " . mb_substr((string) $response->getContent(), 0, 200)
            );
            $this->assertNotSame(500, $response->getStatusCode());

            if ($method !== 'GET' || ! str_contains($url, '/import/template')) {
                $this->assertNotSame('', trim((string) $response->getContent()));
            } else {
                $response->assertHeader('content-disposition');
            }
        }
    }
}
