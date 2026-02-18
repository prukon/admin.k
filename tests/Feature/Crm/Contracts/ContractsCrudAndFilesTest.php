<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ContractsCrudAndFilesTest extends ContractsFeatureTestCase
{
    /** @test */
    public function create_page_ok(): void
    {
        $this->get('/client-contracts/create')->assertStatus(200);
    }

    /** @test */
    public function store_fails_when_insufficient_balance_and_does_not_create_contract(): void
    {
        config(['billing.contract_create_fee' => 70.00]);

        $this->partner->wallet_balance = 0;
        $this->partner->save();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        Storage::fake();

        $pdf = UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf');

        $resp = $this->from('/client-contracts/create')
            ->post('/client-contracts', [
                'user_id' => $student->id,
                'pdf'     => $pdf,
            ]);

        $resp->assertStatus(302);
        $this->assertDatabaseCount('contracts', 0);
    }

    /** @test */
    public function store_creates_contract_and_decreases_partner_balance(): void
    {
        config(['billing.contract_create_fee' => 70.00]);

        $this->partner->wallet_balance = 100;
        $this->partner->save();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        Storage::fake();

        $pdf = UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf');

        $resp = $this->post('/client-contracts', [
            'user_id' => $student->id,
            'pdf'     => $pdf,
        ]);

        $resp->assertStatus(302);

        $contract = Contract::query()->firstOrFail();

        $resp->assertRedirect('/client-contracts/' . $contract->id);

        $this->assertDatabaseHas('contracts', [
            'id'        => $contract->id,
            'school_id' => $this->partner->id,
            'user_id'   => $student->id,
            'status'    => Contract::STATUS_DRAFT,
        ]);

        $this->partner->refresh();
        $this->assertSame(30.0, (float)$this->partner->wallet_balance);
    }

    /** @test */
    public function check_balance_returns_ok_true_when_enough(): void
    {
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 100;
        $this->partner->save();

        $this->postJson('/client-contracts/check-balance')
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('fee', 70);
    }

    /** @test */
    public function contract_partner_middleware_blocks_foreign_contract(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => 1,
        ]);

        $foreignContract = Contract::create([
            'school_id'       => $this->foreignPartner->id,
            'user_id'         => $foreignStudent->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/foreign.pdf',
            'source_sha256'   => str_repeat('c', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $this->get('/client-contracts/' . $foreignContract->id)->assertStatus(403);
    }

    /** @test */
    public function download_original_works_for_own_contract(): void
    {
        Storage::fake();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/source.pdf',
            'source_sha256'   => str_repeat('d', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        Storage::put($contract->source_pdf_path, 'PDF-CONTENT');

        $this->get('/client-contracts/' . $contract->id . '/download-original')
            ->assertStatus(200)
            ->assertHeader('content-disposition');
    }

    /** @test */
    public function download_signed_returns_404_when_missing(): void
    {
        Storage::fake();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/source.pdf',
            'source_sha256'   => str_repeat('e', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
            'signed_pdf_path' => null,
        ]);

        $this->get('/client-contracts/' . $contract->id . '/download-signed')
            ->assertStatus(404);
    }
}

