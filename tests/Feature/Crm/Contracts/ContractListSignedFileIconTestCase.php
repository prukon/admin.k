<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Общие фикстуры колонки «Договор» (иконка PDF) на /client-contracts.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class ContractListSignedFileIconTestCase extends ContractsFeatureTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeListContract(array $overrides = []): Contract
    {
        $schoolId = (int) ($overrides['school_id'] ?? $this->partner->id);
        $student = $overrides['user'] ?? User::factory()->create([
            'partner_id' => $schoolId,
            'is_enabled' => 1,
        ]);
        unset($overrides['user']);

        return Contract::create(array_merge([
            'school_id'       => $schoolId,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/signed-icon/' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeContractWithSignedFile(array $overrides = []): Contract
    {
        $path = $overrides['signed_pdf_path'] ?? ('documents/signed-icon/' . uniqid('', true) . '-signed.pdf');
        $contract = $this->makeListContract(array_merge([
            'status'          => Contract::STATUS_SIGNED,
            'signed_pdf_path' => $path,
        ], $overrides));

        Storage::put($contract->signed_pdf_path, '%PDF-signed-icon');

        return $contract;
    }

    protected function actingAsContractsViewer(): User
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_VIEW);
        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        return $actor;
    }

    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function dataRowFor(Contract $contract): ?array
    {
        $json = $this->getJson(route('contracts.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]), $this->ajaxHeaders())
            ->assertOk()
            ->json('data');

        $row = collect($json)->firstWhere('id', $contract->id);

        return is_array($row) ? $row : null;
    }
}
