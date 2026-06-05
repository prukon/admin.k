<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа к странице «Мои документы» и всем связанным эндпоинтам:
 * авторизованный владелец с правом account.documents.view получает успешные ответы.
 */
class AccountDocumentsPageAccessFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        config(['queue.default' => 'sync']);
    }

    public function test_guest_is_redirected_from_documents_page_and_endpoints(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);

        Auth::logout();

        $this->get(route('account.documents.index'))->assertRedirect(route('login'));
        $this->get(route('account.documents.index', ['fill' => $contract->id]))->assertRedirect(route('login'));
        $this->get(route('account.documents.fill', $contract))->assertRedirect(route('login'));
        $this->post(route('account.documents.generate', $contract), ['fields' => []])->assertRedirect(route('login'));
        $this->get(route('account.documents.requests', $contract))->assertRedirect(route('login'));

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertUnauthorized();
    }

    public function test_user_without_documents_view_permission_gets_403_on_all_endpoints(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $draft = $this->makeDraftContractWithPdf();

        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session);

        $this->get(route('account.documents.index'))->assertForbidden();
        $this->get(route('account.documents.index', ['fill' => $contract->id]))->assertForbidden();
        $this->get(route('account.documents.fill', $contract))->assertForbidden();
        $this->post(route('account.documents.generate', $contract), ['fields' => []])->assertForbidden();
        $this->post(route('account.documents.sign', $draft), [])->assertForbidden();
        $this->get(route('account.documents.requests', $draft))->assertForbidden();
        $this->get(route('account.documents.downloadOriginal', $draft))->assertForbidden();
        $this->get(route('account.documents.downloadSigned', $draft))->assertForbidden();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', ['contract' => $contract, 'poll' => 1]))
            ->assertForbidden();
    }

    public function test_authorized_owner_gets_200_on_documents_index_and_fill_query(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);

        $session = $this->accountDocumentsSession();

        $this->withSession($session)
            ->get(route('account.documents.index'))
            ->assertOk()
            ->assertViewIs('account.index')
            ->assertViewHas('activeTab', 'myDocuments');

        $this->withSession($session)
            ->get(route('account.documents.index', ['fill' => $contract->id]))
            ->assertOk()
            ->assertViewHas('openFillContractId', $contract->id);
    }

    public function test_authorized_owner_gets_200_on_fill_json_and_poll_fill_json(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true],
            ['key' => 'parent_phone', 'label' => 'Телефон', 'required' => true],
        ]);

        $session = $this->accountDocumentsSession();
        $headers = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

        $this->withSession($session)
            ->withHeaders($headers)
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk()
            ->assertJsonStructure(['title', 'html', 'poll'])
            ->assertJsonPath('poll', false);

        $contract->update([
            'filled_data' => ['_generation_error' => 'Ошибка для poll-теста.'],
        ]);

        $this->withSession($session)
            ->withHeaders($headers)
            ->getJson(route('account.documents.fill', ['contract' => $contract, 'poll' => 1]))
            ->assertOk()
            ->assertJsonStructure(['title', 'html', 'poll']);
    }

    public function test_authorized_owner_gets_200_on_fill_json_while_pdf_is_generating(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $contract->update(['status' => Contract::STATUS_GENERATING_PDF]);

        $this->withSession($this->accountDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', ['contract' => $contract, 'poll' => 1]))
            ->assertOk()
            ->assertJsonPath('poll', true);
    }

    public function test_authorized_owner_gets_200_on_fill_json_after_draft_generated(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
            ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
        ]);

        $this->withSession($this->accountDocumentsSession())
            ->post(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname'  => 'Иванов',
                    'parent_firstname' => 'Иван',
                ],
            ])
            ->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));

        $contract->refresh();

        $html = (string) $this->withSession($this->accountDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('contract-fill-sign-block', $html);
        $this->assertStringContainsString(route('account.documents.downloadOriginal', $contract), $html);
    }

    public function test_authorized_owner_gets_200_on_requests_and_download_endpoints(): void
    {
        $draft = $this->makeDraftContractWithPdf();
        $signed = $this->makeSignedContractWithPdf();

        $session = $this->accountDocumentsSession();

        $this->withSession($session)
            ->get(route('account.documents.requests', $draft))
            ->assertOk()
            ->assertJsonStructure(['requests']);

        $this->withSession($session)
            ->get(route('account.documents.downloadOriginal', $draft))
            ->assertOk();

        $this->withSession($session)
            ->get(route('account.documents.downloadSigned', $signed))
            ->assertOk();
    }

    public function test_authorized_owner_full_workflow_all_endpoints_return_success(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true],
            ['key' => 'parent_phone', 'label' => 'Телефон', 'required' => true],
        ]);

        $session = $this->accountDocumentsSession();
        $ajaxHeaders = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

        $this->withSession($session)->get(route('account.documents.index'))->assertOk();
        $this->withSession($session)->get(route('account.documents.index', ['fill' => $contract->id]))->assertOk();

        $this->withSession($session)
            ->withHeaders($ajaxHeaders)
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk();

        $this->withSession($session)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname'  => 'Иванов',
                    'parent_firstname' => 'Иван',
                    'parent_phone'     => '79001112233',
                ],
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'poll']);

        $contract->refresh();

        $this->withSession($session)
            ->withHeaders($ajaxHeaders)
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk();

        $this->withSession($session)
            ->get(route('account.documents.requests', $contract))
            ->assertOk();

        $this->withSession($session)
            ->get(route('account.documents.downloadOriginal', $contract))
            ->assertOk();

        Http::fake(['*' => Http::response([['status' => 15]], 200)]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')->once()->andReturnUsing(function (Contract $c) {
            $c->provider_doc_id = 'pkg-page-access';
            $c->save();

            return ['ok' => true];
        });
        $this->app->instance(SignatureProvider::class, $provider);

        $this->withSession($session)
            ->post(route('account.documents.sign', $contract), [
                'signer_lastname'   => 'Иванов',
                'signer_firstname'  => 'Иван',
                'signer_middlename' => 'Иванович',
                'signer_phone'      => '+7 (900) 111-22-33',
            ])
            ->assertRedirect(route('account.documents.index'))
            ->assertSessionHas('success');
    }

    public function test_non_owner_gets_404_on_all_contract_specific_endpoints(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $draft = $this->makeDraftContractWithPdf();

        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];
        $headers = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

        $this->actingAs($other)->withSession($session)
            ->withHeaders($headers)
            ->getJson(route('account.documents.fill', $contract))
            ->assertNotFound();

        $this->actingAs($other)->withSession($session)
            ->withHeaders($headers)
            ->getJson(route('account.documents.fill', ['contract' => $contract, 'poll' => 1]))
            ->assertNotFound();

        $this->actingAs($other)->withSession($session)
            ->post(route('account.documents.generate', $contract), [
                'fields' => ['parent_lastname' => 'Чужой'],
            ])
            ->assertNotFound();

        $this->actingAs($other)->withSession($session)
            ->post(route('account.documents.sign', $draft), [
                'signer_lastname'  => 'Чужой',
                'signer_firstname' => 'Пользователь',
                'signer_phone'     => '+7 (900) 000-00-00',
            ])
            ->assertNotFound();

        $this->actingAs($other)->withSession($session)
            ->get(route('account.documents.requests', $contract))
            ->assertNotFound();

        $this->actingAs($other)->withSession($session)
            ->get(route('account.documents.downloadOriginal', $draft))
            ->assertNotFound();
    }

    private function makeDraftContractWithPdf(): Contract
    {
        $path = 'documents/page-access-draft-' . uniqid() . '.pdf';
        Storage::disk()->put($path, '%PDF-1.4');

        $contract = Contract::create([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $this->user->id,
            'group_id'                     => null,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'source_pdf_path'              => $path,
            'source_sha256'                => str_repeat('a', 64),
            'provider'                     => 'podpislon',
            'status'                       => Contract::STATUS_DRAFT,
        ]);

        ContractSignRequest::create([
            'contract_id'       => $contract->id,
            'signer_name'       => 'Тест Тест',
            'signer_phone'      => '79001234567',
            'signer_lastname'   => 'Тест',
            'signer_firstname'  => 'Тест',
            'signer_middlename' => null,
            'ttl_hours'         => 72,
            'status'            => 'sent',
        ]);

        return $contract;
    }

    private function makeSignedContractWithPdf(): Contract
    {
        $originalPath = 'documents/page-access-original-' . uniqid() . '.pdf';
        $signedPath = 'documents/page-access-signed-' . uniqid() . '.pdf';
        Storage::disk()->put($originalPath, '%PDF-1.4');
        Storage::disk()->put($signedPath, '%PDF-1.4 signed');

        return Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $this->user->id,
            'group_id'        => null,
            'source_pdf_path' => $originalPath,
            'signed_pdf_path' => $signedPath,
            'source_sha256'   => str_repeat('b', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_SIGNED,
        ]);
    }
}
