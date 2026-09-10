<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

/**
 * С правом account.user.two_factor.update: блок в ЛК и смена 2FA на PATCH.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html#account-two-factor
 */
final class AccountTwoFactorPermissionFullAccessFeatureTest extends AccountTwoFactorPermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->grantTwoFactorToBaseRoles();
    }
    public function test_gate_allows_with_permission(): void
    {
        $this->assertTrue(\Gate::forUser($this->user)->allows(self::PERMISSION));
    }

    public function test_student_edit_page_shows_two_factor_with_permission(): void
    {
        $this->actingAs($this->user);

        $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee(self::LABEL, false)
            ->assertSee('id="two_factor_enabled"', false);
    }

    public function test_admin_edit_page_shows_two_factor_with_permission(): void
    {
        $this->asAdmin();

        $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee(self::LABEL, false)
            ->assertSee('id="two_factor_enabled"', false);
    }

    public function test_trainer_edit_page_shows_two_factor_with_permission(): void
    {
        $trainer = $this->createUserWithRole('trainer', $this->partner);
        $this->actingAs($trainer);

        $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee(self::LABEL, false);
    }

    public function test_student_can_enable_two_factor_with_permission(): void
    {
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $this->patchAccountJson($this->accountPayload($this->user, [
            'two_factor_enabled' => true,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_student_can_disable_two_factor_with_permission(): void
    {
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 1])->save();

        $this->patchAccountJson($this->accountPayload($this->user, [
            'two_factor_enabled' => false,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_admin_can_enable_two_factor_with_permission(): void
    {
        $this->asAdmin();
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $this->patchAccountJson($this->accountPayload($this->user, [
            'two_factor_enabled' => true,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_revoking_user_role_does_not_hide_two_factor_for_admin(): void
    {
        $this->revokePermissionFromRole('user');
        $this->asAdmin();

        $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee(self::LABEL, false);
    }

    public function test_admin_cannot_turn_off_two_factor_when_global_force_is_on(): void
    {
        $this->asAdmin();
        $this->enableForce2faAdmins();
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $this->patchAccountJson($this->accountPayload($this->user, [
            'two_factor_enabled' => false,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, (int) $this->user->fresh()->two_factor_enabled);
    }

    public function test_student_can_turn_off_two_factor_even_when_global_force_is_on(): void
    {
        $this->actingAs($this->user);
        $this->enableForce2faAdmins();
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 1])->save();

        $this->patchAccountJson($this->accountPayload($this->user, [
            'two_factor_enabled' => false,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, (int) $this->user->fresh()->two_factor_enabled);
    }
}
