<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ContractsAccessTest extends ContractsFeatureTestCase
{
    private string $indexUrl = '/client-contracts';

    /** @test */
    public function guest_cannot_access_contracts_routes(): void
    {
        Auth::logout();

        $r = $this->get($this->indexUrl);
        $this->assertTrue(in_array($r->getStatusCode(), [302, 401], true));
    }

    /** @test */
    public function guest_cannot_open_contract_show_page(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/x.pdf',
            'source_sha256'   => str_repeat('q', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        Auth::logout();

        $r = $this->get('/client-contracts/' . $contract->id);
        $this->assertTrue(in_array($r->getStatusCode(), [302, 401], true));
    }

    /** @test */
    public function index_forbidden_without_contracts_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $resp = $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get($this->indexUrl);

        $resp->assertStatus(403);
    }

    /** @test */
    public function index_ok_with_contracts_view_permission(): void
    {
        $this->get($this->indexUrl)->assertStatus(200);
    }

    /** @test */
    public function show_forbidden_without_contracts_view_permission(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/y.pdf',
            'source_sha256'   => str_repeat('r', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get('/client-contracts/' . $contract->id)
            ->assertStatus(403);
    }

    /** @test */
    public function show_ok_with_contracts_view_permission(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/z.pdf',
            'source_sha256'   => str_repeat('s', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $this->get('/client-contracts/' . $contract->id)->assertStatus(200);
    }
}

