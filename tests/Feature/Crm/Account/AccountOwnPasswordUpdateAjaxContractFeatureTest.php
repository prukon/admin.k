<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Crm\Account\Concerns\AccountOwnPasswordUpdateTestHelpers;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX JSON-контракт смены своего пароля: 200/422 errors.password, повтор текущего — UX-баг прода.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountOwnPasswordUpdateAjaxContractFeatureTest extends CrmTestCase
{
    use AccountOwnPasswordUpdateTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->user);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
        $this->setKnownPassword($this->user);
    }

    public function test_ajax_password_change_returns_success_json_and_writes_audit(): void
    {
        $response = $this->putJson($this->passwordUpdateUrl(), [
            'password' => 'new-secure-88',
        ], $this->ajaxHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('errors', $payload);

        $this->assertTrue(Hash::check('new-secure-88', $this->user->fresh()->password));

        $log = MyLog::query()
            ->where('target_type', User::class)
            ->where('target_id', $this->user->id)
            ->where('event', AuditEvent::UserPasswordChanged->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('изменил пароль', (string) $log->description);
    }

    public function test_ajax_empty_password_returns_422_with_password_field_error(): void
    {
        $this->putJson($this->passwordUpdateUrl(), [], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('current-pass-8', $this->user->fresh()->password));
    }

    public function test_ajax_short_password_returns_422_with_password_field_error(): void
    {
        $this->putJson($this->passwordUpdateUrl(), [
            'password' => 'short7',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('current-pass-8', $this->user->fresh()->password));
    }

    public function test_ajax_too_long_password_returns_422_with_password_field_error(): void
    {
        $this->putJson($this->passwordUpdateUrl(), [
            'password' => str_repeat('a', 256),
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('current-pass-8', $this->user->fresh()->password));
    }

    public function test_ajax_same_current_password_returns_422_like_the_production_ux_bug(): void
    {
        $this->putJson($this->passwordUpdateUrl(), [
            'password' => 'current-pass-8',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', 'Новый пароль совпадает с текущим.');

        $this->assertTrue(Hash::check('current-pass-8', $this->user->fresh()->password));

        $this->assertNull(
            MyLog::query()
                ->where('target_type', User::class)
                ->where('target_id', $this->user->id)
                ->where('event', AuditEvent::UserPasswordChanged->value)
                ->first()
        );
    }

    public function test_repeating_just_changed_password_returns_422_like_the_production_ux_bug(): void
    {
        $url = $this->passwordUpdateUrl();

        $this->putJson($url, [
            'password' => 'first-pass-88',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->putJson($url, [
            'password' => 'first-pass-88',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', 'Новый пароль совпадает с текущим.');

        $this->assertTrue(Hash::check('first-pass-88', $this->user->fresh()->password));
    }

    public function test_after_same_password_rejection_a_different_password_still_saves(): void
    {
        $url = $this->passwordUpdateUrl();

        $this->putJson($url, ['password' => 'current-pass-8'], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->putJson($url, [
            'password' => 'second-pass-9',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('second-pass-9', $this->user->fresh()->password));
        $this->assertFalse(Hash::check('current-pass-8', $this->user->fresh()->password));
    }
}
