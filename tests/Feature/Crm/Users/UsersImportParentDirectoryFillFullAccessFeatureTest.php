<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\PartnerLegalEntity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UsersImportTestHelpers;

/**
 * [P1] Доступ к дозаписи родителя через импорт: гость / без права / viewer / admin;
 * PUT/PATCH/DELETE не 500 и не пишут в parents.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see UsersImportAccessFeatureTest
 */
final class UsersImportParentDirectoryFillFullAccessFeatureTest extends CrmTestCase
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
    private function importWriteEndpoints(?UploadedFile $file = null): array
    {
        $file ??= $this->makeFillFile('access-matrix@example.test');

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
                'headers' => $this->importAjaxHeaders(),
            ],
            [
                'method' => 'POST',
                'url' => route('admin.users.import.commit'),
                'data' => ['import_token' => '00000000-0000-4000-8000-000000000000'],
                'headers' => $this->importAjaxHeaders(),
            ],
        ];
    }

    public function test_guest_is_denied_on_import_fill_endpoints_without_500(): void
    {
        Auth::logout();

        $this->get(route('admin.user1'))->assertRedirect();

        foreach ($this->importWriteEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json'],
            );

            $this->assertNotSame(500, $response->getStatusCode(), $item['method'].' '.$item['url']);
            $this->assertNotSame(200, $response->getStatusCode(), $item['method'].' '.$item['url']);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                $item['method'].' '.$item['url']
            );
        }

        $this->assertSame(0, ParentProfile::query()->count());
    }

    public function test_user_without_users_view_gets_403_and_cannot_fill_parent(): void
    {
        $parent = $this->seedNamelessParent();
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.user1'))->assertForbidden();

        foreach ($this->importWriteEndpoints($this->makeFillFile($parent->email)) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? $this->importAjaxHeaders(),
            );
            $response->assertForbidden();
        }

        $parent->refresh();
        $this->assertNull($parent->lastname);
        $this->assertNull($parent->firstname);
    }

    public function test_viewer_without_import_gets_403_and_does_not_see_fill_memo(): void
    {
        $parent = $this->seedNamelessParent();
        $viewer = $this->createUserWithoutPermission('users.import', $this->partner);
        $this->grantUsersView($viewer);
        $this->actingAs($viewer);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $html = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="usersImportModal"', $html);
        $this->assertStringNotContainsString('>Импорт</span>', $html);
        $this->assertStringNotContainsString('дописываются</b> в пустые поля карточки', $html);
        $this->assertStringNotContainsString('initUsersImportModal', $html);

        foreach ($this->importWriteEndpoints($this->makeFillFile($parent->email)) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? $this->importAjaxHeaders(),
            );
            $this->assertSame(403, $response->getStatusCode(), $item['method'].' '.$item['url']);
        }

        $parent->refresh();
        $this->assertNull($parent->lastname);
    }

    public function test_actor_with_import_permission_can_preview_and_commit_parent_fill(): void
    {
        $parent = $this->seedNamelessParent();
        $actor = $this->createUserWithoutPermission('users.import', $this->partner);
        $this->grantUsersView($actor);
        $this->grantPermission($actor, 'users.import');
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $html = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertStringContainsString('id="usersImportModal"', $html);
        $this->assertStringContainsString('дописываются</b> в пустые поля карточки', $html);

        $modalBlade = (string) file_get_contents(resource_path('views/admin/users/_import_modal.blade.php'));
        $this->assertStringStartsWith("@can('users.import')", trim($modalBlade));
        $this->assertStringEndsWith('@endcan', trim($modalBlade));

        $preview = $this->postJson(
            route('admin.users.import.preview'),
            ['file' => $this->makeFillFile($parent->email)],
            $this->importAjaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('valid', true);

        $this->assertNotSame('', trim((string) $preview->getContent()));

        $commit = $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview->json('import_token'),
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['message', 'created', 'updated']);

        $this->assertNotSame('', trim((string) $commit->getContent()));

        $parent->refresh();
        $this->assertSame('Анзароков', $parent->lastname);
        $this->assertSame('Довлет', $parent->firstname);
        $this->assertSame('79627035846', $parent->phone);
    }

    public function test_unsupported_methods_on_import_endpoints_do_not_fill_parent(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.import');

        $parent = $this->seedNamelessParent();

        $urls = [
            route('admin.users.import.template'),
            route('admin.users.import.preview'),
            route('admin.users.import.commit'),
        ];

        foreach ($urls as $url) {
            foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->json($method, $url, [
                    'import_token' => '00000000-0000-4000-8000-000000000000',
                ]);

                $this->assertNotSame(500, $response->getStatusCode(), $method.' '.$url);
                $this->assertContains($response->getStatusCode(), [404, 405], $method.' '.$url);
            }
        }

        $getPreview = $this->getJson(route('admin.users.import.preview'), $this->importAjaxHeaders());
        $this->assertNotSame(500, $getPreview->getStatusCode());
        $this->assertContains($getPreview->getStatusCode(), [404, 405]);

        $getCommit = $this->getJson(route('admin.users.import.commit'), $this->importAjaxHeaders());
        $this->assertNotSame(500, $getCommit->getStatusCode());
        $this->assertContains($getCommit->getStatusCode(), [404, 405]);

        $parent->refresh();
        $this->assertNull($parent->lastname);
        $this->assertNull($parent->firstname);
        $this->assertSame('79627035846', $parent->phone);
    }

    public function test_preview_without_file_returns_422_errors_file_not_empty_200(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.import');

        $response = $this->postJson(route('admin.users.import.preview'), [], $this->importAjaxHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertSame(
            ['Выберите файл Excel для импорта.'],
            $response->json('errors.file')
        );
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());
    }

    private function seedNamelessParent(): ParentProfile
    {
        return ParentProfile::query()->create([
            'partner_id' => $this->partner->id,
            'lastname' => null,
            'firstname' => null,
            'middlename' => null,
            'email' => 'full-access-parent@example.test',
            'phone' => '79627035846',
        ]);
    }

    private function makeFillFile(string $parentEmail): UploadedFile
    {
        return $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Анзароков',
                'Имя ученика' => 'Идар',
                'Группа' => '',
                'Юр. лицо' => '',
                'Email родителя' => $parentEmail,
                'Фамилия родителя' => 'Анзароков',
                'Имя родителя' => 'Довлет',
                'Телефон родителя' => '',
            ]),
        ]);
    }
}
