<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\Account\Concerns\InteractsWithFamilyAccountDocuments;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Гость / без права / тренер / чужая школа не видят кнопку повторной SMS и не вызывают endpoint.
 *
 * @see /docs/documentation/account-contract-fill.html#account-documents-resend-sms
 */
final class AccountDocumentsResendSmsAccessFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;
    use InteractsWithFamilyAccountDocuments;

    private const SAMPLE_URL = 'https://podpislon.ru/sign/pack/971890/9f1d11872060';

    private const SIGNER_PHONE = '79001112233';

    private const MASKED_PHONE = '+7 (900) ***-**-33';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_guest_is_redirected_and_does_not_see_resend_ui(): void
    {
        $contract = $this->seedResendableContract();

        Auth::logout();

        $resp = $this->get(route('account.documents.index'));
        $resp->assertRedirect(route('login'));
        $resp->assertDontSee(Contract::CLIENT_RESEND_SMS_BUTTON, false);
        $resp->assertDontSee(self::MASKED_PHONE, false);
        $resp->assertDontSee(self::SIGNER_PHONE, false);

        $this->post(route('account.documents.resendSms', $contract), [])
            ->assertRedirect(route('login'));

        $this->withHeaders($this->contractFillAjaxHeaders())
            ->postJson(route('account.documents.resendSms', $contract), [])
            ->assertUnauthorized();
    }

    public function test_user_without_documents_view_gets_403_without_resend_ui(): void
    {
        $contract = $this->seedResendableContract();
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $resp = $this->actingAs($actor)
            ->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.index'));

        $resp->assertForbidden();
        $resp->assertDontSee(Contract::CLIENT_RESEND_SMS_BUTTON, false);
        $resp->assertDontSee(self::MASKED_PHONE, false);

        $this->actingAs($actor)
            ->withSession($this->accountDocumentsSession())
            ->post(route('account.documents.resendSms', $contract), [])
            ->assertForbidden();
    }

    public function test_trainer_gets_403_without_resend_ui(): void
    {
        $contract = $this->seedResendableContract();
        $trainer = $this->createUserWithRole('trainer');

        $resp = $this->actingAs($trainer)
            ->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.index'));

        $resp->assertForbidden();
        $resp->assertDontSee(Contract::CLIENT_RESEND_SMS_BUTTON, false);
        $resp->assertDontSee(self::MASKED_PHONE, false);

        $this->actingAs($trainer)
            ->withSession($this->accountDocumentsSession())
            ->post(route('account.documents.resendSms', $contract), [])
            ->assertForbidden();
    }

    public function test_foreign_school_does_not_see_this_school_resend_ui(): void
    {
        $contract = $this->seedResendableContract();

        $html = $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true])
            ->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(Contract::CLIENT_RESEND_SMS_BUTTON, $html);
        $this->assertStringNotContainsString(self::MASKED_PHONE, $html);
        $this->assertStringNotContainsString(self::SIGNER_PHONE, $html);

        $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true])
            ->post(route('account.documents.resendSms', $contract), [])
            ->assertNotFound();
    }

    public function test_authorized_owner_sees_resend_button(): void
    {
        $this->seedResendableContract();

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(Contract::CLIENT_RESEND_SMS_BUTTON, $html);
        $this->assertStringContainsString('SMS уйдёт на '.self::MASKED_PHONE, $html);
        $this->assertStringNotContainsString(self::SIGNER_PHONE, $html);
    }

    public function test_sibling_sees_resend_button_for_brother_contract(): void
    {
        $this->seedFamilyStudents();
        $contract = $this->makeContractFor($this->brother2, Contract::STATUS_SENT, [
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
        ]);
        $this->attachSignRequest($contract);

        $html = $this->actingAsBrother1($this->brother2->id)
            ->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(Contract::CLIENT_RESEND_SMS_BUTTON, $html);
        $this->assertStringContainsString(self::MASKED_PHONE, $html);
        $this->assertStringNotContainsString(self::SIGNER_PHONE, $html);
    }

    public function test_sibling_does_not_see_brother_resend_when_other_child_is_active(): void
    {
        $this->seedFamilyStudents();
        $contract = $this->makeContractFor($this->brother2, Contract::STATUS_SENT, [
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
        ]);
        $this->attachSignRequest($contract);

        $html = $this->actingAsBrother1($this->brother1->id)
            ->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(Contract::CLIENT_RESEND_SMS_BUTTON, $html);
        $this->assertStringNotContainsString(self::MASKED_PHONE, $html);
        $this->assertStringNotContainsString('data-id="'.$contract->id.'"', $html);
    }

    public function test_classmate_with_permission_gets_404_on_resend(): void
    {
        $contract = $this->seedResendableContract();
        $classmate = $this->createUserWithRole('user');

        $this->actingAs($classmate)
            ->withSession($this->accountDocumentsSession())
            ->post(route('account.documents.resendSms', $contract), [])
            ->assertNotFound();

        $this->actingAs($classmate)
            ->withSession($this->accountDocumentsSession())
            ->postJson(route('account.documents.resendSms', $contract), [], $this->contractFillAjaxHeaders())
            ->assertNotFound();
    }

    public function test_json_unsupported_methods_on_resend_sms_are_not_500_or_empty_200(): void
    {
        $contract = $this->seedResendableContract();

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $url = route('account.documents.resendSms', $contract);
            $response = $this->json($method, $url, [], $this->contractFillAjaxHeaders());
            $this->assertNotSame(500, $response->getStatusCode(), "JSON {$method} {$url} не должен быть 500");
            $this->assertContains(
                $response->getStatusCode(),
                [401, 403, 404, 405, 419, 422],
                "JSON {$method} {$url} → {$response->getStatusCode()}"
            );
            if ($response->getStatusCode() === 200) {
                $this->fail("JSON {$method} не должен быть пустым/успешным 200");
            }
        }
    }

    public function test_unsupported_http_methods_on_resend_sms_are_not_500(): void
    {
        $contract = $this->seedResendableContract();

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $url = route('account.documents.resendSms', $contract);
            $response = $this->call($method, $url);
            $this->assertNotSame(500, $response->getStatusCode(), "{$method} {$url} не должен быть 500");
            $this->assertContains(
                $response->getStatusCode(),
                [404, 405, 419, 302, 403],
                "{$method} {$url} → {$response->getStatusCode()}"
            );
        }
    }

    private function seedResendableContract(): Contract
    {
        $contract = Contract::create([
            'school_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'group_id' => null,
            'source_pdf_path' => 'documents/2026/09/resend-access.pdf',
            'source_sha256' => str_repeat('d', 64),
            'provider' => 'podpislon',
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
            'status' => Contract::STATUS_SENT,
        ]);
        $this->attachSignRequest($contract);

        return $contract;
    }

    private function attachSignRequest(Contract $contract): ContractSignRequest
    {
        return ContractSignRequest::create([
            'contract_id' => $contract->id,
            'signer_name' => 'Иванов Иван Иванович',
            'signer_lastname' => 'Иванов',
            'signer_firstname' => 'Иван',
            'signer_middlename' => 'Иванович',
            'signer_phone' => self::SIGNER_PHONE,
            'ttl_hours' => 72,
            'status' => 'sent',
        ]);
    }
}
