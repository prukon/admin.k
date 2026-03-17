<?php

namespace Tests\Feature\Crm\Account;

use Tests\Feature\Crm\CrmTestCase;

class AccountAccessTest extends CrmTestCase
{
    public function test_setpartner_sets_current_partner_from_user_partner_id_when_missing(): void
    {
        $this->actingAs($this->user);

        // Важно: в сессии нет current_partner
        $this->withSession([]);

        $resp = $this->get(route('account.user.edit'));
        $resp->assertStatus(200);

        $this->assertSame(
            $this->user->partner_id,
            session('current_partner'),
            'setPartner должен установить current_partner из user->partner_id, если в сессии пусто'
        );
    }

    public function test_account_page_ok_when_current_partner_present(): void
    {
        $this->actingAs($this->user);

        $resp = $this->withSession(['current_partner' => $this->partner->id])
            ->get(route('account.user.edit'));

        $resp->assertStatus(200);
    }

    public function test_update_ignores_foreign_current_partner_for_regular_user(): void
    {
        $this->actingAs($this->user);

        $payload = [
            // Передаём текущее имя/фамилию, чтобы не зависеть от прав account.user.name.update.
            'name'     => $this->user->name,
            'lastname' => $this->user->lastname,
        ];

        $resp = $this->withSession([
                'current_partner' => $this->foreignPartner->id,
                '2fa:passed'      => true,
            ])
            ->patchJson(route('account.user.update'), $payload);

        // Для обычного пользователя PartnerContext берёт partner_id из пользователя и игнорирует session('current_partner').
        $resp->assertStatus(200);
        $resp->assertJsonPath('success', true);
    }
}