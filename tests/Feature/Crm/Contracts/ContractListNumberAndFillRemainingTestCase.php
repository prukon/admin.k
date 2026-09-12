<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Фикстуры колонки «Номер договора» и второй строки «Срок подписания».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class ContractListNumberAndFillRemainingTestCase extends ContractsFeatureTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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
            'source_pdf_path' => 'documents/number-col/' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeTemplateContract(?Carbon $expiresAt = null, array $overrides = []): Contract
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
            'creation_mode'   => Contract::CREATION_MODE_TEMPLATE,
            'status'          => Contract::STATUS_AWAITING_CLIENT_FILL,
            'provider'        => 'podpislon',
            'fill_expires_at' => $expiresAt,
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

    protected function actingAsFillExpiresViewer(): User
    {
        $actor = $this->actingAsContractsViewer();
        $this->grantPermissionToRoleForPartner(
            (int) $actor->role_id,
            $this->partner->id,
            self::PERM_CONTRACTS_FILL_EXPIRES_AT
        );

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
    protected function dataRowFor(Contract $contract, array $query = []): ?array
    {
        $json = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('contracts.data', array_merge([
                'draw'   => 1,
                'start'  => 0,
                'length' => 50,
            ], $query)))
            ->assertOk()
            ->json('data');

        $row = collect($json)->firstWhere('id', $contract->id);

        return is_array($row) ? $row : null;
    }
}
