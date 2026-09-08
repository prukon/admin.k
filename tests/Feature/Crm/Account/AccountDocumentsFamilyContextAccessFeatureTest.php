<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\Account\Concerns\InteractsWithFamilyAccountDocuments;
use Tests\Feature\Crm\CrmTestCase;

/**
 * HTTP-доступ семейных документов: гость, без права, sibling, чужие методы, изоляция.
 *
 * @see /docs/documentation/account-contract-fill.html#account-documents-family-link
 */
final class AccountDocumentsFamilyContextAccessFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;
    use InteractsWithFamilyAccountDocuments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->familyDocumentsSession());
        $this->seedFamilyStudents();
    }

    public function test_guest_is_denied_on_invitation_link_and_sibling_endpoints(): void
    {
        $awaiting = $this->makeAwaitingFillContractFor($this->brother2);
        $path = $this->putPdfOnDisk('documents/family-access-draft.pdf');
        $draft = $this->makeContractFor($this->brother2, Contract::STATUS_DRAFT, [
            'source_pdf_path' => $path,
        ]);
        $url = $this->invitationDocumentsUrl($awaiting, $this->brother2);

        Auth::logout();

        $this->get($url)->assertRedirect(route('login'));
        $this->get(route('account.documents.fill', $awaiting))->assertRedirect(route('login'));
        $this->post(route('account.documents.generate', $awaiting), ['fields' => []])->assertRedirect(route('login'));
        $this->post(route('account.documents.sign', $draft), [])->assertRedirect(route('login'));
        $this->get(route('account.documents.requests', $draft))->assertRedirect(route('login'));
        $this->get(route('account.documents.downloadOriginal', $draft))->assertRedirect(route('login'));
        $this->get(route('account.documents.downloadSigned', $draft))->assertRedirect(route('login'));

        $this->withHeaders($this->contractFillAjaxHeaders())
            ->getJson(route('account.documents.fill', $awaiting))
            ->assertUnauthorized();
        $this->postJson(route('account.documents.generate', $awaiting), [
            'fields' => ['parent_lastname' => 'Гость'],
        ], $this->contractFillAjaxHeaders())
            ->assertUnauthorized();
    }

    public function test_user_without_documents_view_gets_403_on_invitation_link_and_endpoints(): void
    {
        $awaiting = $this->makeAwaitingFillContractFor($this->brother2);
        $path = $this->putPdfOnDisk('documents/family-access-403.pdf');
        $draft = $this->makeContractFor($this->brother2, Contract::STATUS_DRAFT, [
            'source_pdf_path' => $path,
        ]);
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);
        $session = $this->familyDocumentsSession();

        $this->actingAs($actor)->withSession($session);

        $this->get($this->invitationDocumentsUrl($awaiting, $this->brother2))->assertForbidden();
        $this->get(route('account.documents.index', [
            'student' => $this->brother2->id,
            'fill'    => $awaiting->id,
        ]))->assertForbidden();
        $this->get(route('account.documents.fill', $awaiting))->assertForbidden();
        $this->post(route('account.documents.generate', $awaiting), ['fields' => []])->assertForbidden();
        $this->post(route('account.documents.sign', $draft), [])->assertForbidden();
        $this->get(route('account.documents.requests', $draft))->assertForbidden();
        $this->get(route('account.documents.downloadOriginal', $draft))->assertForbidden();
        $this->get(route('account.documents.downloadSigned', $draft))->assertForbidden();

        $this->withHeaders($this->contractFillAjaxHeaders())
            ->getJson(route('account.documents.fill', $awaiting))
            ->assertForbidden();
        $this->postJson(route('account.documents.generate', $awaiting), [
            'fields' => ['parent_lastname' => 'Нет права'],
        ], $this->contractFillAjaxHeaders())
            ->assertForbidden();
    }

    public function test_sibling_with_permission_gets_200_on_documents_and_contract_endpoints(): void
    {
        $awaiting = $this->makeAwaitingFillContractFor($this->brother2);
        $original = $this->putPdfOnDisk('documents/family-access-original.pdf');
        $signed = $this->putPdfOnDisk('documents/family-access-signed.pdf');
        $draft = $this->makeContractFor($this->brother2, Contract::STATUS_DRAFT, [
            'source_pdf_path' => $original,
        ]);
        $signedContract = $this->makeContractFor($this->brother2, Contract::STATUS_SIGNED, [
            'source_pdf_path' => $original,
            'signed_pdf_path' => $signed,
        ]);

        $this->actingAsBrother1();

        $this->get($this->invitationDocumentsUrl($awaiting, $this->brother2))
            ->assertOk()
            ->assertViewIs('account.index')
            ->assertViewHas('openFillContractId', $awaiting->id);

        $this->withHeaders($this->contractFillAjaxHeaders())
            ->getJson(route('account.documents.fill', $awaiting))
            ->assertOk()
            ->assertJsonStructure(['title', 'html', 'poll']);

        $this->get(route('account.documents.requests', $draft))
            ->assertOk()
            ->assertJsonStructure(['requests']);

        $this->get(route('account.documents.downloadOriginal', $draft))->assertOk();
        $this->get(route('account.documents.downloadSigned', $signedContract))->assertOk();
    }

    public function test_foreign_partner_contract_returns_404_not_500(): void
    {
        $foreign = Contract::create([
            'school_id'       => $this->foreignPartner->id,
            'user_id'         => $this->foreignUser->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/foreign-family.pdf',
            'source_sha256'   => str_repeat('c', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_AWAITING_CLIENT_FILL,
        ]);

        $this->actingAsBrother1();

        $this->withHeaders($this->contractFillAjaxHeaders())
            ->getJson(route('account.documents.fill', $foreign))
            ->assertNotFound();

        $this->postJson(route('account.documents.generate', $foreign), [
            'fields' => ['parent_lastname' => 'Чужой'],
        ], $this->contractFillAjaxHeaders())
            ->assertNotFound();
    }

    public function test_unsupported_http_methods_on_documents_routes_are_not_500(): void
    {
        $contract = $this->makeAwaitingFillContractFor($this->brother2);
        $this->actingAsBrother1();

        foreach ([
            ['PATCH', route('account.documents.index')],
            ['DELETE', route('account.documents.index')],
            ['POST', route('account.documents.index')],
            ['PUT', route('account.documents.fill', $contract)],
            ['DELETE', route('account.documents.fill', $contract)],
            ['GET', route('account.documents.generate', $contract)],
            ['DELETE', route('account.documents.generate', $contract)],
            ['PATCH', route('account.documents.sign', $contract)],
            ['GET', route('account.documents.sign', $contract)],
        ] as [$method, $url]) {
            $response = $this->call($method, $url);
            $this->assertNotSame(
                500,
                $response->getStatusCode(),
                "{$method} {$url} не должен быть 500"
            );
            $this->assertContains(
                $response->getStatusCode(),
                [404, 405, 419, 302, 403],
                "{$method} {$url} → {$response->getStatusCode()}"
            );
        }
    }
}
