<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

/**
 * Колонка «Срок подписания» и пункт «Колонки» только при contracts.fillExpiresAt.view.
 */
final class ContractListFillExpiresAtUxFeatureTest extends ContractsFeatureTestCase
{
    public function test_index_hides_fill_expires_column_and_columns_toggle_without_permission(): void
    {
        $html = $this->get(route('contracts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<th>Обновлён</th>', $html);
        $this->assertStringContainsString('for="colUpdatedAt">Обновлён</label>', $html);
        $this->assertStringNotContainsString('<th>Срок подписания</th>', $html);
        $this->assertStringNotContainsString('data-column-key="fill_expires_at"', $html);
        $this->assertStringNotContainsString('id="colFillExpiresAt"', $html);
        $this->assertStringContainsString('const canSeeFillExpiresAt = false;', $html);
        $this->assertStringContainsString('when: canSeeFillExpiresAt', $html);
        $this->assertStringContainsString("order: [[8, 'desc']]", $html);
    }

    public function test_index_shows_fill_expires_column_and_columns_toggle_with_permission(): void
    {
        $this->grantPermissionToRoleForPartner(
            $this->user->role_id,
            $this->partner->id,
            self::PERM_CONTRACTS_FILL_EXPIRES_AT
        );

        $html = $this->get(route('contracts.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<th>Обновлён<\/th>\s*<th>Срок подписания<\/th>\s*<th>Действия<\/th>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-column-key="updated_at"[^>]*>[\s\S]*data-column-key="fill_expires_at"[^>]*>[\s\S]*data-column-key="actions"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/class="form-check-input column-toggle"[^>]*data-column-key="fill_expires_at"[^>]*checked/',
            $html
        );
        $this->assertStringContainsString('for="colFillExpiresAt">Срок подписания</label>', $html);
        $this->assertStringContainsString('const canSeeFillExpiresAt = true;', $html);
        $this->assertStringContainsString('fill_expires_at: canSeeFillExpiresAt', $html);
        $this->assertStringContainsString("key: 'fill_expires_at'", $html);
        $this->assertStringContainsString('when: canSeeFillExpiresAt', $html);
        $this->assertStringContainsString("order: [[8, 'desc']]", $html);
    }

    public function test_admin_without_permission_does_not_see_fill_expires_column(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $html = $this->get(route('contracts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<th>Срок подписания</th>', $html);
        $this->assertStringNotContainsString('id="colFillExpiresAt"', $html);
        $this->assertStringContainsString('const canSeeFillExpiresAt = false;', $html);
    }

    public function test_trainer_with_contracts_view_does_not_see_fill_expires_column(): void
    {
        $trainer = $this->createUserWithRole('trainer');
        $this->grantPermissionToRoleForPartner(
            (int) $trainer->role_id,
            $this->partner->id,
            self::PERM_CONTRACTS_VIEW
        );

        $this->actingAs($trainer)
            ->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed'      => true,
            ]);

        $html = $this->get(route('contracts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<th>Срок подписания</th>', $html);
        $this->assertStringNotContainsString('id="colFillExpiresAt"', $html);
    }

    public function test_superadmin_sees_fill_expires_column_without_role_assignment(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $html = $this->get(route('contracts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<th>Срок подписания</th>', $html);
        $this->assertStringContainsString('id="colFillExpiresAt"', $html);
        $this->assertStringContainsString('const canSeeFillExpiresAt = true;', $html);
    }

    public function test_foreign_partner_user_does_not_see_current_school_column_toggle_leak(): void
    {
        $this->grantPermissionToRoleForPartner(
            $this->user->role_id,
            $this->partner->id,
            self::PERM_CONTRACTS_FILL_EXPIRES_AT
        );
        $this->grantPermissionToRoleForPartner(
            $this->foreignUser->role_id,
            $this->foreignPartner->id,
            self::PERM_CONTRACTS_VIEW
        );

        $this->actingAs($this->foreignUser)
            ->withSession([
                'current_partner' => $this->foreignPartner->id,
                '2fa:passed'      => true,
            ]);

        $html = $this->get(route('contracts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<th>Срок подписания</th>', $html);
        $this->assertStringNotContainsString('id="colFillExpiresAt"', $html);
    }
}
