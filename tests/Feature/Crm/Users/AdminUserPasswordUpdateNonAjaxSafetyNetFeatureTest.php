<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use Illuminate\Support\Facades\Hash;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\AdminUserPasswordUpdateTestHelpers;

/**
 * Non-AJAX safety-net смены пароля: без X-Requested-With запись обновляется,
 * валидация — 302 + errors[password], не пустой 200 и не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUserPasswordUpdateNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use AdminUserPasswordUpdateTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureArrayCacheForThrottle();

        $this->asAdmin();
        $this->grantPasswordChangeAccess($this->user);
        $this->grantStaffSectionAccess($this->user);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_non_ajax_password_change_updates_record_and_is_not_empty_200(): void
    {
        $target = $this->makePasswordTarget();

        $response = $this->from(route('admin.administrators.index'))
            ->post($this->passwordUpdateUrl($target), [
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
            $response->assertRedirect(route('admin.administrators.index'));
        }

        $target->refresh();
        $this->assertTrue(Hash::check('non-ajax-88', $target->password));
    }

    public function test_non_ajax_empty_password_redirects_with_password_field_error(): void
    {
        $target = $this->makePasswordTarget();

        $response = $this->from(route('admin.administrators.index'))
            ->post($this->passwordUpdateUrl($target), [
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Валидация не должна давать пустой/успешный 200');
        $response->assertStatus(302);
        $response->assertRedirect(route('admin.administrators.index'));
        $response->assertSessionHasErrors(['password']);

        $target->refresh();
        $this->assertTrue(Hash::check('current-pass-8', $target->password));
    }

    public function test_non_ajax_short_password_redirects_with_password_field_error(): void
    {
        $target = $this->makePasswordTarget();

        $response = $this->from(route('admin.administrators.index'))
            ->post($this->passwordUpdateUrl($target), [
                '_token'   => csrf_token(),
                'password' => 'short7',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password']);
    }
}
