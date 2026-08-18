<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use Illuminate\Support\Facades\Hash;
use Tests\Feature\Crm\Account\Concerns\AccountOwnPasswordUpdateTestHelpers;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net смены своего пароля: без X-Requested-With запись обновляется,
 * валидация — 302 + errors[password], не пустой 200 и не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountOwnPasswordUpdateNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_non_ajax_password_change_updates_record_and_is_not_empty_200(): void
    {
        $response = $this->from(route('account.user.edit'))
            ->put($this->passwordUpdateUrl(), [
                '_token'   => csrf_token(),
                'password' => 'non-ajax-88',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains(
            $response->getStatusCode(),
            [200, 302],
            'Успех non-AJAX должен сохранить пароль (JSON 200 или redirect 302), не пустой 200 без записи'
        );

        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
            $response->assertJsonPath('success', true);
        }

        if ($response->getStatusCode() === 302) {
            $response->assertRedirect(route('account.user.edit'));
        }

        $this->assertTrue(Hash::check('non-ajax-88', $this->user->fresh()->password));
    }

    public function test_non_ajax_empty_password_redirects_with_password_field_error(): void
    {
        $response = $this->from(route('account.user.edit'))
            ->put($this->passwordUpdateUrl(), [
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Валидация не должна давать пустой/успешный 200');
        $response->assertStatus(302);
        $response->assertRedirect(route('account.user.edit'));
        $response->assertSessionHasErrors(['password']);

        $this->assertTrue(Hash::check('current-pass-8', $this->user->fresh()->password));
    }

    public function test_non_ajax_short_password_redirects_with_password_field_error(): void
    {
        $response = $this->from(route('account.user.edit'))
            ->put($this->passwordUpdateUrl(), [
                '_token'   => csrf_token(),
                'password' => 'short7',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password']);

        $this->assertTrue(Hash::check('current-pass-8', $this->user->fresh()->password));
    }

    public function test_non_ajax_same_current_password_redirects_with_password_field_error(): void
    {
        $response = $this->from(route('account.user.edit'))
            ->put($this->passwordUpdateUrl(), [
                '_token'   => csrf_token(),
                'password' => 'current-pass-8',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Тот же пароль не должен давать успех');
        $response->assertStatus(302);
        $response->assertRedirect(route('account.user.edit'));
        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHasErrors([
            'password' => 'Новый пароль совпадает с текущим.',
        ]);

        $this->assertTrue(Hash::check('current-pass-8', $this->user->fresh()->password));
    }
}
