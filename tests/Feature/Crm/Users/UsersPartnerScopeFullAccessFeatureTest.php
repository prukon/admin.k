<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Partner;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Раздел «Пользователи»: partner-scope (STRICT_CURRENT), доступ к странице и endpoint’ам,
 * изоляция партнёров на CRUD, аватар, пароль.
 */
final class UsersPartnerScopeFullAccessFeatureTest extends CrmTestCase
{
    private ?string $tempStoragePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->tempStoragePath !== null && is_dir($this->tempStoragePath)) {
            File::deleteDirectory($this->tempStoragePath);
        }

        parent::tearDown();
    }

    // --- Доступ: гость и permission ---

    public function test_guest_cannot_access_any_users_section_endpoint(): void
    {
        Auth::logout();

        $localUser = User::factory()->create(['partner_id' => $this->partner->id]);

        foreach ($this->allSectionRoutesPayload($localUser->id) as $item) {
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
        }
    }

    public function test_user_without_users_view_gets_403_on_all_section_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);

        $peer = User::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $roleId = $this->studentRoleId();

        foreach ($this->allSectionRoutesPayload($peer->id, $team->id, $roleId) as $item) {
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
                "Без users.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_users_view_all_section_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->grantUsersView($actor);
        $this->grantUsersPasswordUpdate($actor);
        $this->actingAs($actor);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $roleId = $this->studentRoleId();
        $existingField = UserField::factory()
            ->forPartner($this->partner->id)
            ->withSlug('scope_access_field')
            ->create(['name' => 'Scope field']);

        $this->get(route('admin.user1'))->assertOk()->assertViewHas('activeTab', 'users');

        $store = $this->postJson(route('admin.user.store'), [
            'name'       => 'Scope',
            'lastname'   => 'Access',
            'email'      => 'scope-access-' . uniqid('', true) . '@example.test',
            'role_id'    => $roleId,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $userId = (int) $store->json('user.id');
        $this->assertGreaterThan(0, $userId);

        foreach ($this->allSectionRoutesPayload($userId, $team->id, $roleId, $existingField->id) as $item) {
            if ($item['method'] === 'POST' && str_contains($item['url'], '/avatar')) {
                continue;
            }
            if ($item['method'] === 'DELETE' && str_contains($item['url'], '/avatar')) {
                continue;
            }
            if ($item['method'] === 'DELETE' && str_contains($item['url'], '/admin/user/')) {
                continue;
            }

            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                $item['server'] ?? [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "С users.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        $this->setupPublicStorageFake();

        $target = User::factory()->create([
            'partner_id' => $this->partner->id,
            'image'      => 'scope-avatar.jpg',
            'image_crop' => 'scope-crop.jpg',
        ]);
        Storage::disk('public')->put('avatars/scope-avatar.jpg', 'x');
        Storage::disk('public')->put('avatars/scope-crop.jpg', 'x');

        $this->deleteJson("/admin/users/{$target->id}/avatar", [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $uploadTarget = User::factory()->create(['partner_id' => $this->partner->id]);
        $this->postJson("/admin/users/{$uploadTarget->id}/avatar", [
            'image_big'  => UploadedFile::fake()->image('big.jpg', 800, 800)->size(400),
            'image_crop' => UploadedFile::fake()->image('crop.jpg', 300, 300)->size(300),
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->grantUsersPasswordUpdate($actor);
        $pwdTarget = User::factory()->create([
            'partner_id' => $this->partner->id,
            'password'   => Hash::make('old-scope-pass'),
        ]);
        $this->postJson('/admin/user/' . $pwdTarget->id . '/update-password', [
            'password' => 'new-scope-pass-9',
        ])->assertOk();

        $this->deleteJson('/admin/user/' . $userId)->assertOk();
    }

    // --- Изоляция: DataTables и CRUD ---

    public function test_users_data_excludes_users_of_foreign_partner(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $local = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'LocalScopeUser',
        ]);
        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'name'       => 'ForeignScopeUser',
        ]);

        $json = $this->getJson('/admin/users/data?draw=1&start=0&length=500')->json();
        $ids = collect($json['data'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($local->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_users_data_filter_by_foreign_user_id_returns_empty_rowset(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $json = $this->getJson('/admin/users/data?draw=1&start=0&length=10&id=' . $this->foreignUser->id)->json();

        $this->assertSame(0, (int) ($json['recordsFiltered'] ?? -1));
        $ids = collect($json['data'] ?? [])->pluck('id')->all();
        $this->assertNotContains($this->foreignUser->id, $ids);
    }

    public function test_scoped_endpoints_return_404_for_foreign_user(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $foreignId = $this->foreignUser->id;

        $this->getJson(route('admin.user.edit', ['user' => $foreignId]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertNotFound();

        $this->patchJson(route('admin.user.update', ['user' => $foreignId]), [
            'name'     => 'X',
            'lastname' => 'Y',
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertNotFound();

        $this->deleteJson('/admin/user/' . $foreignId)->assertNotFound();

        $this->setupPublicStorageFake();
        $this->deleteJson("/admin/users/{$foreignId}/avatar", [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertNotFound();

        $this->postJson("/admin/users/{$foreignId}/avatar", [
            'image_big'  => UploadedFile::fake()->image('big.jpg'),
            'image_crop' => UploadedFile::fake()->image('crop.jpg'),
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertNotFound();

        $this->grantUsersPasswordUpdate($this->user);
        $this->postJson('/admin/user/' . $foreignId . '/update-password', [
            'password' => 'hacked-pass-1',
        ])->assertNotFound();

        $this->foreignUser->refresh();
        $this->assertNotSame('X', $this->foreignUser->name);
    }

    public function test_superadmin_with_current_partner_cannot_access_foreign_user_on_scoped_endpoints(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $foreignId = $this->foreignUser->id;

        $this->getJson(route('admin.user.edit', ['user' => $foreignId]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertNotFound();

        $this->patchJson(route('admin.user.update', ['user' => $foreignId]), [
            'name'     => 'Hack',
            'lastname' => 'Super',
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertNotFound();

        $this->deleteJson('/admin/user/' . $foreignId)->assertNotFound();

        $json = $this->getJson('/admin/users/data?draw=1&start=0&length=500')->json();
        $ids = collect($json['data'] ?? [])->pluck('id')->all();
        $this->assertNotContains($foreignId, $ids);
    }

    public function test_own_partner_crud_avatar_and_password_succeed_for_authorized_admin(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantUsersPasswordUpdate($this->user);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $roleId = $this->studentRoleId();

        $store = $this->postJson(route('admin.user.store'), [
            'name'       => 'Own',
            'lastname'   => 'Partner',
            'email'      => 'own-partner-' . uniqid('', true) . '@example.test',
            'role_id'    => $roleId,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $userId = (int) $store->json('user.id');

        $this->getJson(route('admin.user.edit', $userId), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()->assertJsonPath('user.id', $userId);

        $this->patchJson(route('admin.user.update', $userId), [
            'name'     => 'Own',
            'lastname' => 'Updated',
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertSame('Updated', User::findOrFail($userId)->lastname);

        $this->setupPublicStorageFake();
        $this->postJson("/admin/users/{$userId}/avatar", [
            'image_big'  => UploadedFile::fake()->image('big.jpg', 600, 600)->size(400),
            'image_crop' => UploadedFile::fake()->image('crop.jpg', 200, 200)->size(300),
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $user = User::findOrFail($userId);
        $this->assertNotNull($user->image);
        $this->assertNotNull($user->image_crop);

        $this->deleteJson("/admin/users/{$userId}/avatar", [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $user->refresh();
        $this->assertNull($user->image);
        $this->assertNull($user->image_crop);

        $user->update(['password' => Hash::make('before-pass')]);
        $this->postJson('/admin/user/' . $userId . '/update-password', [
            'password' => 'after-pass-99',
        ])->assertOk();
        $this->assertTrue(Hash::check('after-pass-99', User::findOrFail($userId)->password));

        $this->deleteJson('/admin/user/' . $userId)->assertOk();
        $this->assertSoftDeleted('users', ['id' => $userId]);
    }

    public function test_admin_acting_in_foreign_partner_session_sees_only_foreign_users_in_data(): void
    {
        $localMarker = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'HomeMarker',
        ]);
        $foreignMarker = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'name'       => 'ForeignMarker',
        ]);

        $foreignAdmin = $this->createUserWithRole('admin', $this->foreignPartner);
        $this->grantUsersView($foreignAdmin, $this->foreignPartner);
        $this->actingAs($foreignAdmin);
        $this->withSession(['current_partner' => $this->foreignPartner->id]);

        $ids = collect(
            $this->getJson('/admin/users/data?draw=1&start=0&length=500')->json('data') ?? []
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($foreignMarker->id, $ids);
        $this->assertNotContains($localMarker->id, $ids);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>, server?: array<string, string>}>
     */
    private function allSectionRoutesPayload(int $userId, ?int $teamId = null, ?int $roleId = null, ?int $userFieldId = null): array
    {
        $teamId ??= (int) Team::factory()->create(['partner_id' => $this->partner->id])->id;
        $roleId ??= $this->studentRoleId();
        $userFieldId ??= UserField::factory()
            ->forPartner($this->partner->id)
            ->withSlug('route_payload_field')
            ->create(['name' => 'Route field'])
            ->id;

        return [
            ['method' => 'GET', 'url' => route('admin.user1')],
            [
                'method'  => 'GET',
                'url'     => '/admin/users/data?draw=1&start=0&length=10',
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('admin.users.table-settings.get'),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'POST',
                'url'     => route('admin.users.table-settings.save'),
                'data'    => ['columns' => ['name' => true, 'email' => false]],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('logs.data.user', ['draw' => 1, 'start' => 0, 'length' => 10]),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'POST',
                'url'     => route('admin.field.store'),
                'data'    => [
                    'fields' => [
                        [
                            'id'         => $userFieldId,
                            'name'       => 'Route field',
                            'field_type' => 'string',
                            'roles'      => [],
                        ],
                    ],
                ],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'POST',
                'url'     => route('admin.user.store'),
                'data'    => [
                    'name'       => 'Ep',
                    'lastname'   => 'Smoke',
                    'email'      => 'ep-' . uniqid('', true) . '@example.test',
                    'role_id'    => $roleId,
                    'team_id'    => $teamId,
                    'is_enabled' => 1,
                ],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('admin.user.edit', ['user' => $userId]),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'PATCH',
                'url'     => route('admin.user.update', ['user' => $userId]),
                'data'    => ['name' => 'Ep', 'lastname' => 'Patched'],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'DELETE',
                'url'     => '/admin/users/' . $userId . '/avatar',
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'POST',
                'url'     => '/admin/users/' . $userId . '/avatar',
                'data'    => [],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'POST',
                'url'     => '/admin/user/' . $userId . '/update-password',
                'data'    => ['password' => 'smoke-pass-88'],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'DELETE',
                'url'     => '/admin/user/' . $userId,
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
        ];
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    private function grantUsersView(User $user, ?Partner $partner = null): void
    {
        $partner ??= $this->partner;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantUsersPasswordUpdate(User $user, ?Partner $partner = null): void
    {
        $partner ??= $this->partner;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId('users.password.update'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function setupPublicStorageFake(): void
    {
        $this->tempStoragePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm-storage-'
            . Str::uuid();

        File::ensureDirectoryExists($this->tempStoragePath);
        $this->app->useStoragePath($this->tempStoragePath);
        Storage::fake('public');
    }
}
