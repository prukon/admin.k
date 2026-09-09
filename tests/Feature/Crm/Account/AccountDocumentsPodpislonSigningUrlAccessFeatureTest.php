<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;
use Monolog\Handler\NullHandler;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\Account\Concerns\InteractsWithFamilyAccountDocuments;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Кабинет: гость/без права/тренер/sibling и чужие HTTP-методы для ссылки из SMS.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountDocumentsPodpislonSigningUrlAccessFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;
    use InteractsWithFamilyAccountDocuments;

    private const SAMPLE_URL = 'https://podpislon.ru/sign/pack/971890/9f1d11872060';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'logging.channels.podpislon' => [
                'driver' => 'monolog',
                'handler' => NullHandler::class,
            ],
        ]);
        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_guest_json_sign_is_401_and_does_not_save_url(): void
    {
        $this->seedPodpislonLegalEntity();
        $contract = $this->makeSentCabinetContract([
            'status' => Contract::STATUS_DRAFT,
            'provider_doc_id' => null,
            'provider_signing_url' => null,
        ]);

        Auth::logout();

        $this->withHeaders($this->contractFillAjaxHeaders())
            ->postJson(route('account.documents.sign', $contract), [
                'signer_lastname' => 'Петров',
                'signer_firstname' => 'Пётр',
                'signer_phone' => '+7 (900) 111-22-33',
            ])
            ->assertUnauthorized();

        $this->assertNull($contract->fresh()->provider_signing_url);
        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
    }

    public function test_trainer_gets_403_and_does_not_see_sms_link(): void
    {
        $this->makeSentCabinetContract();
        $trainer = $this->createUserWithRole('trainer');

        $resp = $this->actingAs($trainer)
            ->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.index'));

        $resp->assertForbidden();
        $resp->assertDontSee(self::SAMPLE_URL, false);
    }

    public function test_sibling_sees_brother_sms_link_when_that_child_is_active(): void
    {
        $this->seedFamilyStudents();
        $this->makeContractFor($this->brother2, Contract::STATUS_SENT, [
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
        ]);

        $html = $this->actingAsBrother1($this->brother2->id)
            ->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(self::SAMPLE_URL, $html);
        $this->assertStringContainsString('Открыть ссылку из SMS', $html);
    }

    public function test_parent_does_not_see_other_child_sms_link_when_another_child_is_active(): void
    {
        $this->seedFamilyStudents();
        $this->makeContractFor($this->brother2, Contract::STATUS_SENT, [
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
        ]);

        $html = $this->actingAsBrother1($this->brother1->id)
            ->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(self::SAMPLE_URL, $html);
        $this->assertStringNotContainsString('Открыть ссылку из SMS', $html);
    }

    public function test_wrong_http_methods_on_documents_index_and_sign_are_not_500(): void
    {
        $contract = $this->makeSentCabinetContract();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('account.documents.index'));
            $this->assertNotSame(500, $json->getStatusCode(), 'JSON index '.$method);
            $this->assertNotSame(200, $json->getStatusCode(), 'JSON index '.$method);
            $this->assertContains($json->getStatusCode(), [404, 405], 'JSON index '.$method);
        }

        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('account.documents.sign', $contract));
            $this->assertNotSame(500, $json->getStatusCode(), 'JSON sign '.$method);
            $this->assertNotSame(200, $json->getStatusCode(), 'JSON sign '.$method);
            $this->assertContains($json->getStatusCode(), [404, 405], 'JSON sign '.$method);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSentCabinetContract(array $overrides = []): Contract
    {
        return Contract::create(array_merge([
            'school_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'group_id' => null,
            'source_pdf_path' => 'documents/2026/09/account-access.pdf',
            'source_sha256' => str_repeat('c', 64),
            'provider' => 'podpislon',
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
            'status' => Contract::STATUS_SENT,
        ], $overrides));
    }
}
