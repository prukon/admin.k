<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\User;

class ContractRevokeRefundTest extends ContractsFeatureTestCase
{
    /** @test */
    public function revoke_awaiting_client_fill_refunds_partner_balance(): void
    {
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 30;
        $this->partner->save();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $student->id,
            'group_id'                     => null,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'contract_template_version_id' => null,
            'source_pdf_path'              => null,
            'source_sha256'                => null,
            'status'                       => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at'              => now()->addDays(7),
            'provider'                     => 'podpislon',
        ]);

        $this->postJson('/client-contracts/' . $contract->id . '/revoke')
            ->assertStatus(200)
            ->assertJsonPath('status', 'revoked');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);

        $this->partner->refresh();
        $this->assertSame(100.0, (float) $this->partner->wallet_balance);
    }
}
