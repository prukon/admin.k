<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\User;

abstract class ContractAnnulAfterSendTestCase extends ContractsFeatureTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeAnnulContract(array $overrides = []): Contract
    {
        $student = $overrides['user'] ?? User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        unset($overrides['user']);

        return Contract::create(array_merge([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'creation_mode'   => Contract::CREATION_MODE_TEMPLATE,
            'source_pdf_path' => 'documents/annul/' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-annul-' . uniqid('', true),
            'status'          => Contract::STATUS_SENT,
        ], $overrides));
    }

    protected function actingAsContractsViewer(): User
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_VIEW);
        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        return $actor;
    }
}
