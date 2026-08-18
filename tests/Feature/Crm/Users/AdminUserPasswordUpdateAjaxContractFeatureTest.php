<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\AdminUserPasswordUpdateTestHelpers;

/**
 * AJAX JSON-контракт смены пароля: 200/422, errors по полям, повтор того же пароля.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUserPasswordUpdateAjaxContractFeatureTest extends CrmTestCase
{
    use AdminUserPasswordUpdateTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureArrayCacheForThrottle();

        $this->asAdmin();
        $this->grantPasswordChangeAccess($this->user);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_ajax_password_change_returns_success_json_and_writes_audit(): void
    {
        $target = $this->makePasswordTarget();

        $this->postJson($this->passwordUpdateUrl($target), [
            'password' => 'new-secure-88',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $target->refresh();
        $this->assertTrue(Hash::check('new-secure-88', $target->password));

        $log = MyLog::query()
            ->where('target_type', User::class)
            ->where('target_id', $target->id)
            ->where('event', AuditEvent::UserPasswordChanged->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Пароль пользователя', (string) $log->description);
    }

    public function test_ajax_empty_password_returns_422_with_password_field_error(): void
    {
        $target = $this->makePasswordTarget();

        $this->postJson($this->passwordUpdateUrl($target), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $target->refresh();
        $this->assertTrue(Hash::check('current-pass-8', $target->password));
    }

    public function test_ajax_short_password_returns_422_with_password_field_error(): void
    {
        $target = $this->makePasswordTarget();

        $this->postJson($this->passwordUpdateUrl($target), [
            'password' => 'short7',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $target->refresh();
        $this->assertTrue(Hash::check('current-pass-8', $target->password));
    }

    public function test_ajax_too_long_password_returns_422_with_password_field_error(): void
    {
        $target = $this->makePasswordTarget();

        $this->postJson($this->passwordUpdateUrl($target), [
            'password' => str_repeat('a', 256),
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_ajax_same_current_password_returns_422_with_message_and_does_not_log(): void
    {
        $target = $this->makePasswordTarget();

        $this->postJson($this->passwordUpdateUrl($target), [
            'password' => 'current-pass-8',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Новый пароль совпадает с текущим.');

        $target->refresh();
        $this->assertTrue(Hash::check('current-pass-8', $target->password));

        $this->assertNull(
            MyLog::query()
                ->where('target_type', User::class)
                ->where('target_id', $target->id)
                ->where('event', AuditEvent::UserPasswordChanged->value)
                ->first()
        );
    }

    public function test_repeating_just_changed_password_returns_422_like_the_production_ux_bug(): void
    {
        $target = $this->makePasswordTarget();
        $url = $this->passwordUpdateUrl($target);

        $this->postJson($url, [
            'password' => 'first-pass-88',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson($url, [
            'password' => 'first-pass-88',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Новый пароль совпадает с текущим.');

        $target->refresh();
        $this->assertTrue(Hash::check('first-pass-88', $target->password));
    }

    public function test_after_same_password_rejection_a_different_password_still_saves(): void
    {
        $target = $this->makePasswordTarget();
        $url = $this->passwordUpdateUrl($target);

        $this->postJson($url, ['password' => 'first-pass-88'])->assertOk();
        $this->postJson($url, ['password' => 'first-pass-88'])->assertStatus(422);

        $this->postJson($url, [
            'password' => 'second-pass-9',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $target->refresh();
        $this->assertTrue(Hash::check('second-pass-9', $target->password));
        $this->assertFalse(Hash::check('first-pass-88', $target->password));
    }
}
