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

        $resp = $this->get(route('admin.cur.user.edit', $this->user));
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
            ->get(route('admin.cur.user.edit', $this->user));

        $resp->assertStatus(200);
    }

    public function test_cannot_update_foreign_partner_user(): void
    {
        $this->actingAs($this->user);

        $payload = [
            'name'     => 'NewName',
            'lastname' => 'NewLast',
        ];

        $resp = $this->withSession(['current_partner' => $this->partner->id])
            ->patchJson(route('account.user.update', $this->foreignUser), $payload);

        $resp->assertStatus(404);
    }
}