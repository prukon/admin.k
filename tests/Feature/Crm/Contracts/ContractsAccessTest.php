<?php

namespace Tests\Feature\Crm\Contracts;

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
}

