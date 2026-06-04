<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к «Мои документы» и связанным эндпоинтам заполнения договора.
 */
class AccountDocumentsFullAccessFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
    }

    public function test_guest_cannot_access_documents_section(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);

        Auth::logout();

        $this->get(route('account.documents.index'))->assertRedirect(route('login'));
        $this->get(route('account.documents.fill', $contract))->assertRedirect(route('login'));
        $this->get(route('account.documents.requests', $contract))->assertRedirect(route('login'));
        $this->get(route('account.documents.downloadOriginal', $contract))->assertRedirect(route('login'));
        $this->get(route('account.documents.downloadSigned', $contract))->assertRedirect(route('login'));

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertUnauthorized();
    }

    public function test_user_without_documents_view_permission_gets_403(): void
    {
        $contract = $this->makeDraftContractWithPdf();

        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('account.documents.index'))
            ->assertForbidden();

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertForbidden();

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('account.documents.requests', $contract))
            ->assertForbidden();
    }

    public function test_owner_with_permission_gets_200_on_documents_pages_and_fill_json(): void
    {
        $awaiting = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
            ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
        ]);

        $draft = $this->makeDraftContractWithPdf();

        $this->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.index'))
            ->assertOk()
            ->assertViewIs('account.index')
            ->assertViewHas('activeTab', 'myDocuments');

        $this->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.index', ['fill' => $awaiting->id]))
            ->assertOk();

        $this->withSession($this->accountDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', $awaiting))
            ->assertOk()
            ->assertJsonStructure(['title', 'html', 'poll']);

        $this->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.requests', $draft))
            ->assertOk()
            ->assertJsonStructure(['requests']);

        $this->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.downloadOriginal', $draft))
            ->assertOk();
    }

    public function test_fill_json_returns_200_for_contract_owner_only(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);

        $this->withSession($this->accountDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk();

        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $this->actingAs($other)
            ->withSession($this->accountDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertNotFound();
    }

    public function test_non_ajax_fill_route_redirects_to_documents_with_fill_query(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);

        $this->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.fill', $contract))
            ->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));
    }

    private function makeDraftContractWithPdf(): Contract
    {
        $path = 'documents/full-access-' . uniqid() . '.pdf';
        Storage::disk()->put($path, '%PDF-1.4');

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $this->user->id,
            'group_id'        => null,
            'source_pdf_path' => $path,
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        ContractSignRequest::create([
            'contract_id'      => $contract->id,
            'signer_name'      => 'Тест Тест',
            'signer_phone'     => '79001234567',
            'signer_lastname'  => 'Тест',
            'signer_firstname' => 'Тест',
            'signer_middlename'=> null,
            'ttl_hours'        => 72,
            'status'           => 'sent',
        ]);

        return $contract;
    }
}
