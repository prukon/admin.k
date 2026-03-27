<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;

class ContractsSyncFeatureTest extends ContractsFeatureTestCase
{
    private function makeContractForSync(array $overrides = []): Contract
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        return Contract::create(array_merge([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/source.pdf',
            'source_sha256'   => str_repeat('m', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 9001,
            'status'          => Contract::STATUS_SENT,
            'signed_pdf_path' => null,
        ], $overrides));
    }

    /** @test */
    public function status_sync_returns_403_without_contracts_sync_permission(): void
    {
        $contract = $this->makeContractForSync();

        $this->getJson('/client-contracts/' . $contract->id . '/status')
            ->assertStatus(403);
    }

    /** @test */
    public function status_sync_returns_403_for_foreign_partner_contract_even_with_sync_permission(): void
    {
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_CONTRACTS_SYNC);

        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => 1,
        ]);

        $foreignContract = Contract::create([
            'school_id'       => $this->foreignPartner->id,
            'user_id'         => $foreignStudent->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/f.pdf',
            'source_sha256'   => str_repeat('n', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 111,
            'status'          => Contract::STATUS_SENT,
        ]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->never();
        $this->app->instance(SignatureProvider::class, $provider);

        $this->getJson('/client-contracts/' . $foreignContract->id . '/status')
            ->assertStatus(403);
    }

    /** @test */
    public function status_sync_maps_api_numeric_30_to_signed_downloads_pdf_and_writes_webhook_style_events(): void
    {
        Storage::fake();
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_CONTRACTS_SYNC);

        $contract = $this->makeContractForSync(['status' => Contract::STATUS_SENT]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->once()->andReturn(['status' => 30, 'status_text' => 'Подписан']);
        $provider->shouldReceive('downloadSigned')->once()->andReturn([
            'filename' => 'signed.pdf',
            'content'  => 'PDF-BY-30',
        ]);
        $this->app->instance(SignatureProvider::class, $provider);

        $this->getJson('/client-contracts/' . $contract->id . '/status')
            ->assertStatus(200)
            ->assertJsonPath('synced', true)
            ->assertJsonPath('status', Contract::STATUS_SIGNED);

        $contract->refresh();
        $this->assertNotNull($contract->signed_pdf_path);
        Storage::assertExists($contract->signed_pdf_path);

        foreach (['webhook_document_signed', 'status_sync', 'signed_pdf_saved'] as $type) {
            $this->assertDatabaseHas('contract_events', [
                'contract_id' => $contract->id,
                'type'        => $type,
            ]);
        }
    }

    /** @test */
    public function status_sync_maps_api_20_to_opened_from_sent_and_writes_events(): void
    {
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_CONTRACTS_SYNC);

        $contract = $this->makeContractForSync(['status' => Contract::STATUS_SENT]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->once()->andReturn(['status' => '20']);
        $this->app->instance(SignatureProvider::class, $provider);

        $this->getJson('/client-contracts/' . $contract->id . '/status')
            ->assertStatus(200)
            ->assertJsonPath('synced', true)
            ->assertJsonPath('status', Contract::STATUS_OPENED);

        foreach (['webhook_document_opened', 'status_sync'] as $type) {
            $this->assertDatabaseHas('contract_events', [
                'contract_id' => $contract->id,
                'type'        => $type,
            ]);
        }
    }

    /** @test */
    public function status_sync_recovery_when_db_signed_but_pdf_missing_downloads_and_event(): void
    {
        Storage::fake();
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_CONTRACTS_SYNC);

        $contract = $this->makeContractForSync([
            'status'          => Contract::STATUS_SIGNED,
            'signed_pdf_path' => null,
        ]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->once()->andReturn(['status' => '30']);
        $provider->shouldReceive('downloadSigned')->once()->andReturn([
            'filename' => 'signed.pdf',
            'content'  => 'PDF-RECOVER',
        ]);
        $this->app->instance(SignatureProvider::class, $provider);

        $this->getJson('/client-contracts/' . $contract->id . '/status')
            ->assertStatus(200)
            ->assertJsonPath('synced', true);

        $contract->refresh();
        $this->assertNotNull($contract->signed_pdf_path);

        $this->assertDatabaseHas('contract_events', [
            'contract_id' => $contract->id,
            'type'        => 'webhook_document_signed',
        ]);
        $this->assertDatabaseHas('contract_events', [
            'contract_id' => $contract->id,
            'type'        => 'signed_pdf_saved',
        ]);
    }

    /** @test */
    public function status_sync_noop_when_remote_matches_local_returns_200_synced_false(): void
    {
        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_CONTRACTS_SYNC);

        $contract = $this->makeContractForSync(['status' => Contract::STATUS_SENT]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->once()->andReturn(['status' => '15']);
        $this->app->instance(SignatureProvider::class, $provider);

        $this->getJson('/client-contracts/' . $contract->id . '/status')
            ->assertStatus(200)
            ->assertJsonPath('synced', false)
            ->assertJsonPath('status', Contract::STATUS_SENT);
    }

    /**
     * HTML-страница договора (show) покрыта в ContractsAccessTest.
     * Здесь — JSON/файловые эндпоинты раздела при наличии contracts.view и contracts.sync,
     * без повторной компиляции Blade в другом view.compiled (иначе возможен конфликт @php helper в одном процессе).
     *
     * @test
     */
    public function authorized_user_with_view_and_sync_gets_200_on_contract_json_and_file_endpoints(): void
    {
        Storage::fake();
        Mail::fake();

        $this->grantPermissionToRoleForPartner($this->user->role_id, $this->partner->id, self::PERM_CONTRACTS_SYNC);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'name'       => 'Sync',
            'lastname'   => 'Testuser',
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/orig.pdf',
            'source_sha256'   => str_repeat('p', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 4242,
            'status'          => Contract::STATUS_SENT,
            'signed_pdf_path' => 'documents/2026/01/signed.pdf',
        ]);

        Storage::put($contract->source_pdf_path, 'ORIG');
        Storage::put($contract->signed_pdf_path, 'SIGNED');

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('getStatus')->once()->andReturn(['status' => '15']);
        $this->app->instance(SignatureProvider::class, $provider);

        $this->getJson('/client-contracts/data?draw=1&start=0&length=20')->assertStatus(200);

        $this->getJson('/client-contracts/users-search?q=Sync')->assertStatus(200);

        $this->getJson('/client-contracts/columns-settings')->assertStatus(200);

        $this->postJson('/client-contracts/check-balance')->assertStatus(200);

        $this->getJson('/client-contracts/' . $contract->id . '/status')
            ->assertStatus(200)
            ->assertJsonPath('synced', false);

        $this->postJson('/client-contracts/' . $contract->id . '/send-email', [
            'email'  => 'notify@example.test',
            'signed' => true,
        ])->assertStatus(200)->assertJsonPath('success', true);

        $this->get('/client-contracts/' . $contract->id . '/download-original')->assertStatus(200);
        $this->get('/client-contracts/' . $contract->id . '/download-signed')->assertStatus(200);
    }
}
