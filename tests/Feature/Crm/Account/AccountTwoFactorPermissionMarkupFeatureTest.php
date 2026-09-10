<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

/**
 * Blade/разметка SMS-2FA в ЛК: @can, hidden/checked/disabled, правило force_2fa_admins.
 *
 * UX-баг до фикса: locked-состояние смотрело на role_id === 10, а seeded admin имеет id=2,
 * поэтому чекбокс админа никогда не блокировался при глобалке.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html#account-two-factor
 */
final class AccountTwoFactorPermissionMarkupFeatureTest extends AccountTwoFactorPermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->grantTwoFactorToBaseRoles();
    }
    public function test_first_open_with_two_factor_off_shows_unchecked_checkbox_and_hidden_zero(): void
    {
        $this->actingAs($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $block = $this->extractTwoFactorBlock($html);
        $checkbox = $this->twoFactorCheckboxTag($block);

        $this->assertStringContainsString('name="two_factor_enabled"', $block);
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="two_factor_enabled" value="0">[\s\S]*id="two_factor_enabled"/',
            $block,
            'Hidden 0 должен идти до чекбокса, иначе снятие галочки не отправит выключение'
        );
        $this->assertStringContainsString('name="two_factor_enabled"', $checkbox);
        $this->assertStringContainsString('value="1"', $checkbox);
        $this->assertStringNotContainsString('checked', $checkbox);
        $this->assertStringNotContainsString('disabled', $checkbox);
        $this->assertStringContainsString('Выключена', $block);
        $this->assertStringNotContainsString('обязательна для администраторов', $block);
    }

    public function test_first_open_with_two_factor_on_shows_checked_checkbox(): void
    {
        $this->actingAs($this->user);
        $this->withVerifiedPhone($this->user);
        $this->user->forceFill(['two_factor_enabled' => 1])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $block = $this->extractTwoFactorBlock($html);
        $checkbox = $this->twoFactorCheckboxTag($block);

        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="two_factor_enabled" value="0">[\s\S]*id="two_factor_enabled"/',
            $block
        );
        $this->assertStringContainsString('checked', $checkbox);
        $this->assertStringNotContainsString('disabled', $checkbox);
        $this->assertStringContainsString('Включена', $block);
        $this->assertStringNotContainsString('обязательна для администраторов', $block);
    }

    public function test_forced_admin_two_factor_checkbox_is_locked_on_when_global_setting_is_on(): void
    {
        $this->asAdmin();
        $this->enableForce2faAdmins();
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $block = $this->extractTwoFactorBlock($html);
        $checkbox = $this->twoFactorCheckboxTag($block);

        $this->assertStringContainsString('<input type="hidden" name="two_factor_enabled" value="1">', $block);
        $this->assertStringNotContainsString('value="0"', $block);
        $this->assertStringContainsString('checked', $checkbox);
        $this->assertStringContainsString('disabled', $checkbox);
        $this->assertStringNotContainsString('name="two_factor_enabled"', $checkbox);
        $this->assertStringContainsString('обязательна для администраторов', $block);
        $this->assertSame(
            1,
            substr_count($block, 'name="two_factor_enabled"'),
            'При принудительной 2FA на сервер уходит только hidden=1, не чекбокс'
        );
    }

    public function test_admin_without_global_force_keeps_toggle_unlocked(): void
    {
        $this->asAdmin();
        $this->disableForce2faAdmins();
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $block = $this->extractTwoFactorBlock($html);
        $checkbox = $this->twoFactorCheckboxTag($block);

        $this->assertStringContainsString('value="0"', $block);
        $this->assertStringNotContainsString('disabled', $checkbox);
        $this->assertStringNotContainsString('обязательна для администраторов', $block);
        $this->assertStringContainsString('name="two_factor_enabled"', $checkbox);
    }

    public function test_student_does_not_get_forced_admin_lock_when_global_setting_is_on(): void
    {
        $this->actingAs($this->user);
        $this->enableForce2faAdmins();
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $block = $this->extractTwoFactorBlock($html);
        $checkbox = $this->twoFactorCheckboxTag($block);

        $this->assertStringNotContainsString('обязательна для администраторов', $block);
        $this->assertStringNotContainsString('disabled', $checkbox);
        $this->assertStringContainsString('<input type="hidden" name="two_factor_enabled" value="0">', $block);
        $this->assertStringNotContainsString('checked', $checkbox);
    }

    public function test_trainer_does_not_get_forced_admin_lock_when_global_setting_is_on(): void
    {
        $this->enableForce2faAdmins();
        $trainer = $this->createUserWithRole('trainer', $this->partner);
        $trainer->forceFill(['two_factor_enabled' => 0])->save();
        $this->actingAs($trainer);

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();
        $block = $this->extractTwoFactorBlock($html);
        $checkbox = $this->twoFactorCheckboxTag($block);

        $this->assertStringNotContainsString('обязательна для администраторов', $block);
        $this->assertStringNotContainsString('disabled', $checkbox);
    }

    public function test_reopening_edit_page_does_not_force_two_factor_on_for_student(): void
    {
        $this->actingAs($this->user);
        $this->user->forceFill(['two_factor_enabled' => 0])->save();

        $first = $this->twoFactorCheckboxTag(
            $this->get(route('account.user.edit'))->assertOk()->getContent()
        );
        $second = $this->twoFactorCheckboxTag(
            $this->get(route('account.user.edit'))->assertOk()->getContent()
        );

        $this->assertStringNotContainsString('checked', $first);
        $this->assertStringNotContainsString('checked', $second);
    }
}
