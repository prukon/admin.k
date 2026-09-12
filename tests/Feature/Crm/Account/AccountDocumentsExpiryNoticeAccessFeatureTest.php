<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Гость / без права / тренер / чужая школа не видят тексты просрочки на «Мои документы».
 *
 * @see /docs/documentation/account-contract-fill.html#account-documents-expiry-notice
 */
final class AccountDocumentsExpiryNoticeAccessFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    private const SAMPLE_URL = 'https://podpislon.ru/sign/pack/971890/9f1d11872060';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_guest_is_redirected_and_does_not_see_expiry_notices(): void
    {
        $this->seedExpiredContracts();

        Auth::logout();

        $resp = $this->get(route('account.documents.index'));
        $resp->assertRedirect(route('login'));
        $resp->assertDontSee(Contract::CLIENT_FILL_EXPIRED_NOTICE, false);
        $resp->assertDontSee(Contract::CLIENT_SMS_EXPIRED_NOTICE, false);
        $resp->assertDontSee(Contract::CLIENT_FILL_EXPIRED_BADGE, false);
        $resp->assertDontSee(self::SAMPLE_URL, false);
    }

    public function test_user_without_documents_view_gets_403_without_notices(): void
    {
        $this->seedExpiredContracts();

        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $resp = $this->actingAs($actor)
            ->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.index'));

        $resp->assertForbidden();
        $resp->assertDontSee(Contract::CLIENT_FILL_EXPIRED_NOTICE, false);
        $resp->assertDontSee(Contract::CLIENT_SMS_EXPIRED_NOTICE, false);
        $resp->assertDontSee(self::SAMPLE_URL, false);
    }

    public function test_trainer_gets_403_without_notices(): void
    {
        $this->seedExpiredContracts();
        $trainer = $this->createUserWithRole('trainer');

        $resp = $this->actingAs($trainer)
            ->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.index'));

        $resp->assertForbidden();
        $resp->assertDontSee(Contract::CLIENT_FILL_EXPIRED_NOTICE, false);
        $resp->assertDontSee(Contract::CLIENT_SMS_EXPIRED_NOTICE, false);
        $resp->assertDontSee(self::SAMPLE_URL, false);
    }

    public function test_foreign_school_does_not_see_this_school_expiry_notices(): void
    {
        $this->seedExpiredContracts();

        $html = $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true])
            ->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(Contract::CLIENT_FILL_EXPIRED_NOTICE, $html);
        $this->assertStringNotContainsString(Contract::CLIENT_SMS_EXPIRED_NOTICE, $html);
        $this->assertStringNotContainsString(self::SAMPLE_URL, $html);
    }

    public function test_authorized_owner_sees_fill_and_sms_expiry_notices(): void
    {
        $this->seedExpiredContracts();

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(Contract::CLIENT_FILL_EXPIRED_NOTICE, $html);
        $this->assertStringContainsString(Contract::CLIENT_SMS_EXPIRED_NOTICE, $html);
        $this->assertStringContainsString(Contract::CLIENT_FILL_EXPIRED_BADGE, $html);
        $this->assertStringNotContainsString(self::SAMPLE_URL, $html);
    }

    public function test_unsupported_http_methods_on_documents_index_are_not_500(): void
    {
        $this->seedExpiredContracts();

        foreach (['PATCH', 'DELETE', 'POST', 'PUT'] as $method) {
            $url = route('account.documents.index');
            $response = $this->call($method, $url);
            $this->assertNotSame(500, $response->getStatusCode(), "{$method} {$url} не должен быть 500");
            $this->assertContains(
                $response->getStatusCode(),
                [404, 405, 419, 302, 403],
                "{$method} {$url} → {$response->getStatusCode()}"
            );
            $response->assertDontSee(Contract::CLIENT_FILL_EXPIRED_NOTICE, false);
            $response->assertDontSee(Contract::CLIENT_SMS_EXPIRED_NOTICE, false);
        }
    }

    private function seedExpiredContracts(): void
    {
        $fill = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $fill->update(['fill_expires_at' => now()->subMinute()]);

        Contract::create([
            'school_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'group_id' => null,
            'source_pdf_path' => 'documents/2026/09/expired-sms.pdf',
            'source_sha256' => str_repeat('d', 64),
            'provider' => 'podpislon',
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
            'status' => Contract::STATUS_EXPIRED,
        ]);
    }
}
