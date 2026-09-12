<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Native GET fill / POST generate без X-Requested-With при просрочке заполнения:
 * 302 на документы, session errors, не пустой 200 и не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountDocumentsExpiryNoticeNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_native_get_documents_shows_expiry_notices_in_html(): void
    {
        $contract = $this->expiredFillContract();

        $response = $this->get(route('account.documents.index'));

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertSee(Contract::CLIENT_FILL_EXPIRED_NOTICE, false);
        $response->assertSee(Contract::CLIENT_FILL_EXPIRED_BADGE, false);
        $response->assertDontSee('Заполнить договор', false);

        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
    }

    public function test_native_get_fill_expired_redirects_with_session_error(): void
    {
        $contract = $this->expiredFillContract();

        $response = $this->from(route('account.documents.index'))
            ->get(route('account.documents.fill', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Успех native fill не должен быть пустым 200');
        $response->assertRedirect(route('account.documents.index'));
        $response->assertSessionHasErrors(['contract']);

        $sessionErrors = session('errors');
        $this->assertNotNull($sessionErrors);
        $this->assertSame(Contract::CLIENT_FILL_EXPIRED_NOTICE, $sessionErrors->first('contract'));
    }

    public function test_native_generate_expired_redirects_with_session_error_and_does_not_write_pdf(): void
    {
        $contract = $this->expiredFillContract();

        $response = $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.generate', $contract), [
                '_token' => csrf_token(),
                'fields' => ['parent_lastname' => 'Иванов'],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Валидация не должна давать пустой/успешный 200');
        $response->assertStatus(302);
        $response->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));
        $response->assertSessionHasErrors(['contract']);

        $sessionErrors = session('errors');
        $this->assertNotNull($sessionErrors);
        $this->assertSame(Contract::CLIENT_FILL_EXPIRED_NOTICE, $sessionErrors->first('contract'));

        $contract->refresh();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertNull($contract->source_pdf_path);
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
