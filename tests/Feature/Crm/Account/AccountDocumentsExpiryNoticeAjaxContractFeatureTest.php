<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX fill/generate при просрочке заполнения: 422 с текстом «Обратитесь к администратору школы».
 *
 * @see /docs/documentation/account-contract-fill.html#account-documents-expiry-notice
 */
final class AccountDocumentsExpiryNoticeAjaxContractFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_ajax_fill_expired_returns_422_with_school_admin_notice(): void
    {
        $contract = $this->expiredFillContract();

        $this->withHeaders($this->contractFillAjaxHeaders())
            ->getJson(route('account.documents.fill', $contract))
            ->assertStatus(422)
            ->assertJsonPath('message', Contract::CLIENT_FILL_EXPIRED_NOTICE)
            ->assertJsonMissingPath('html');
    }

    public function test_ajax_generate_expired_returns_422_with_school_admin_notice(): void
    {
        $contract = $this->expiredFillContract();

        $this->postJson(route('account.documents.generate', $contract), [
            'fields' => ['parent_lastname' => 'Иванов'],
        ], $this->contractFillAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('message', Contract::CLIENT_FILL_EXPIRED_NOTICE)
            ->assertJsonPath('errors.contract.0', Contract::CLIENT_FILL_EXPIRED_NOTICE);

        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
        $this->assertNull($contract->fresh()->source_pdf_path);
    }

    public function test_ajax_fill_sms_expired_returns_generic_unavailable_not_fill_notice(): void
    {
        $contract = Contract::create([
            'school_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'group_id' => null,
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'source_pdf_path' => 'documents/2026/09/sms-expired.pdf',
            'source_sha256' => str_repeat('e', 64),
            'provider' => 'podpislon',
            'status' => Contract::STATUS_EXPIRED,
        ]);

        $this->withHeaders($this->contractFillAjaxHeaders())
            ->getJson(route('account.documents.fill', $contract))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Договор недоступен для заполнения.');

        $this->assertNotSame(Contract::CLIENT_FILL_EXPIRED_NOTICE, 'Договор недоступен для заполнения.');
    }

    public function test_guest_ajax_fill_expired_is_401_and_does_not_leak_notice(): void
    {
        $contract = $this->expiredFillContract();
        Auth::logout();

        $resp = $this->withHeaders($this->contractFillAjaxHeaders())
            ->getJson(route('account.documents.fill', $contract));

        $resp->assertUnauthorized();
        $resp->assertDontSee(Contract::CLIENT_FILL_EXPIRED_NOTICE, false);
    }

    public function test_user_without_documents_view_gets_403_on_ajax_fill_expired(): void
    {
        $contract = $this->expiredFillContract();
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $resp = $this->actingAs($actor)
            ->withSession($this->accountDocumentsSession())
            ->withHeaders($this->contractFillAjaxHeaders())
            ->getJson(route('account.documents.fill', $contract));

        $resp->assertForbidden();
        $resp->assertDontSee(Contract::CLIENT_FILL_EXPIRED_NOTICE, false);
    }

    private function expiredFillContract(): Contract
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $contract->update(['fill_expires_at' => now()->subMinute()]);

        return $contract->fresh();
    }
}
