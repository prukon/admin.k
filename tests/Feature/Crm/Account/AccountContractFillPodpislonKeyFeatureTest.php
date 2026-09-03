<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\PartnerLegalEntity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Подпись из кабинета: без ключа юрлица — redirect с errors.podpislon_api_key, договор не failed.
 *
 * @see /docs/documentation/account-contract-fill.html
 * @see /docs/documentation/contracts.html §3.1
 */
final class AccountContractFillPodpislonKeyFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_parent_sign_without_podpislon_key_redirects_back_with_field_error(): void
    {
        Config::set('services.podpislon.key', 'ENV-FALLBACK-MUST-NOT-BE-USED');
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП Кабинет без ключа',
            'is_enabled' => true,
        ]);

        $contract = $this->makeDraftReadyToSign();

        $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.sign', $contract), [
                'signer_lastname' => 'Петров',
                'signer_firstname' => 'Пётр',
                'signer_phone' => '+7 (900) 111-22-33',
            ])
            ->assertRedirect(route('account.documents.index', ['fill' => $contract->id]))
            ->assertSessionHasErrors(['podpislon_api_key']);

        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
        $this->assertSame(0, $contract->signRequests()->count());
        Http::assertNothingSent();
    }

    public function test_guest_cannot_sign_contract_from_cabinet(): void
    {
        $this->seedPodpislonLegalEntity();
        $contract = $this->makeDraftReadyToSign();

        Auth::logout();

        $response = $this->post(route('account.documents.sign', $contract), [
            'signer_lastname' => 'Петров',
            'signer_firstname' => 'Пётр',
            'signer_phone' => '+7 (900) 111-22-33',
        ]);

        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
    }

    private function makeDraftReadyToSign(): Contract
    {
        $contract = $this->makeAwaitingFillContract([
            [
                'key' => 'parent_full_name',
                'label' => 'ФИО родителя',
                'required' => true,
            ],
        ]);

        $contract->update([
            'status' => Contract::STATUS_DRAFT,
            'source_pdf_path' => 'documents/cabinet-sign.pdf',
            'source_sha256' => str_repeat('b', 64),
        ]);
        Storage::disk()->put($contract->source_pdf_path, '%PDF-1.4');

        return $contract;
    }
}
