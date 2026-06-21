<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Team;
use App\Models\User;
use App\Services\SmsRuService;
use App\Services\TeamUserSyncService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Страница GET /account-settings/user/edit: UI по роли, подписи полей, доступ и связанные эндпоинты.
 *
 * @see /docs/documentation/tests-standards.html
 * @see /docs/documentation/parents-and-family-cabinet.html
 */
final class AccountUserEditPageFeatureTest extends CrmTestCase
{
    private ?string $tempStoragePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->tempStoragePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm-storage-'
            . Str::uuid();

        File::ensureDirectoryExists($this->tempStoragePath);
        $this->app->useStoragePath($this->tempStoragePath);
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        if ($this->tempStoragePath !== null && is_dir($this->tempStoragePath)) {
            File::deleteDirectory($this->tempStoragePath);
        }

        parent::tearDown();
    }

    public function test_student_edit_page_shows_role_label_and_simplified_name_fields(): void
    {
        $this->actingAs($this->user);

        $html = (string) $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Пользователь', $html);
        $this->assertStringNotContainsString('Данные ученика', $html);
        $this->assertStringContainsString('for="lastname"', $html);
        $this->assertStringContainsString('>Фамилия*</label>', $html);
        $this->assertStringContainsString('for="name"', $html);
        $this->assertStringContainsString('>Имя*</label>', $html);
        $this->assertStringNotContainsString('Фамилия ученика', $html);
        $this->assertStringNotContainsString('Имя ученика', $html);
    }

    public function test_student_edit_page_shows_readonly_teams_block(): void
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Account-A',
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Account-B',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->user, [$teamA->id, $teamB->id]);

        $this->actingAs($this->user);

        $html = (string) $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Группы</label>', $html);
        $this->assertStringContainsString('readonly', $html);
        $this->assertStringContainsString('Изменение групп доступно только администратору CRM', $html);
        $this->assertStringNotContainsString('id="team"', $html);
        $this->assertStringNotContainsString('name="team_id"', $html);
        $this->assertStringContainsString('Account-A', $html);
        $this->assertStringContainsString('Account-B', $html);
    }

    public function test_admin_edit_page_shows_role_label_and_hides_team_field(): void
    {
        $admin = $this->createUserWithRole('admin', $this->partner, [
            'name'     => 'Admin',
            'lastname' => 'Boss',
        ]);

        $this->actingAs($admin);

        $html = (string) $this->get(route('account.user.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Администратор', $html);
        $this->assertStringNotContainsString('Данные ученика', $html);
        $this->assertStringNotContainsString('id="team"', $html);
        $this->assertStringNotContainsString('name="team_id"', $html);
        $this->assertStringNotContainsString('>Группы</label>', $html);
    }

    public function test_guest_is_redirected_from_account_user_edit_page_routes(): void
    {
        Auth::logout();

        $this->get(route('account.user.edit'))->assertRedirect(route('login'));

        $this->patchJson(route('account.user.update'), [
            'name'     => 'A',
            'lastname' => 'B',
        ])->assertUnauthorized();

        $this->putJson(route('account.user.password.update'), [
            'password' => 'password123',
        ])->assertUnauthorized();

        $this->postJson(route('account.user.avatar.store'), [])->assertUnauthorized();
        $this->deleteJson(route('account.user.avatar.destroy'))->assertUnauthorized();

        $this->postJson(route('account.user.phoneSendCode', $this->user), [
            'phone' => '79001112233',
        ])->assertUnauthorized();
    }

    public function test_account_user_edit_page_routes_forbidden_without_account_user_view(): void
    {
        $actor = $this->createUserWithoutPermission('account.user.view', $this->partner);

        $this->actingAs($actor);

        $this->get(route('account.user.edit'))->assertForbidden();

        $this->patchJson(route('account.user.update'), [
            'name'     => $actor->name,
            'lastname' => $actor->lastname,
        ], $this->jsonHeaders())->assertForbidden();

        $this->putJson(route('account.user.password.update'), [
            'password' => 'newpassword1',
        ], $this->jsonHeaders())->assertForbidden();

        $this->postJson(route('account.user.avatar.store'), [], $this->jsonHeaders())->assertForbidden();
        $this->deleteJson(route('account.user.avatar.destroy'), [], $this->jsonHeaders())->assertForbidden();
    }

    public function test_regular_user_all_account_user_edit_page_endpoints_return_200(): void
    {
        $this->actingAs($this->user);

        $this->assertAllAccountUserEditPageEndpointsReturn200($this->user);
    }

    public function test_admin_all_account_user_edit_page_endpoints_return_200(): void
    {
        $admin = $this->createUserWithRole('admin', $this->partner, [
            'name'     => 'Admin',
            'lastname' => 'Boss',
        ]);

        $this->actingAs($admin);

        $this->assertAllAccountUserEditPageEndpointsReturn200($admin);
    }

    private function assertAllAccountUserEditPageEndpointsReturn200(User $actor): void
    {
        $this->get(route('account.user.edit'))->assertOk();

        $this->patchJson(route('account.user.update'), [
            'name'     => $actor->name,
            'lastname' => $actor->lastname,
        ], $this->jsonHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->putJson(route('account.user.password.update'), [
            'password' => 'newpassword99',
        ], $this->jsonHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->post(route('account.user.avatar.store'), [
            'image_big'  => UploadedFile::fake()->image('big.jpg', 1200, 800),
            'image_crop' => UploadedFile::fake()->image('crop.jpg', 300, 300),
        ], $this->jsonHeaders())
            ->assertOk()
            ->assertJsonStructure(['message', 'image_url', 'image_crop_url', 'image', 'image_crop']);

        $this->deleteJson(route('account.user.avatar.destroy'), [], $this->jsonHeaders())
            ->assertOk();

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->andReturn(true);
        });

        $phoneDigits = '79001234567';

        $this->postJson(route('account.user.phoneSendCode', $actor), [
            'phone' => $phoneDigits,
        ], $this->jsonHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $code = '654321';
        $actor->forceFill([
            'two_factor_phone_pending'    => '+' . $phoneDigits,
            'phone_change_new_code'       => Hash::make($code),
            'phone_change_new_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->postJson(route('account.user.phoneConfirmCode', $actor), [
            'phone' => $phoneDigits,
            'code'  => $code,
        ], $this->jsonHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return [
            'Accept'           => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
