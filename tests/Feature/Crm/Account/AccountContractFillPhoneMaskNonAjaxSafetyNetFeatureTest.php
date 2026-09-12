<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX generate с телефоном: без X-Requested-With — 302 на документы,
 * маска в filled_data; валидация — 302 + errors[fields.parent_phone].
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountContractFillPhoneMaskNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_non_ajax_generate_redirects_and_writes_masked_phone(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();

        $response = $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.generate', $contract), [
                '_token' => csrf_token(),
                'fields' => $this->phoneGenerateFields(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Успех non-AJAX не должен быть пустым 200');
        $response->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));
        $response->assertSessionHas('success');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertNotNull($contract->source_pdf_path);
        $this->assertSame('+7 (906) 247-55-08', $contract->filled_data['parent_phone'] ?? null);
        $this->assertNotSame('9062475508', $contract->filled_data['parent_phone'] ?? null);
    }

    public function test_non_ajax_empty_phone_redirects_with_error_on_field(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();

        $response = $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.generate', $contract), [
                '_token' => csrf_token(),
                'fields' => $this->phoneGenerateFields([
                    'parent_phone' => '',
                ]),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));
        $response->assertSessionHasErrors(['fields.parent_phone']);

        $message = session('errors')?->first('fields.parent_phone');
        $this->assertIsString($message);
        $this->assertStringContainsString('Телефон', $message);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertNull($contract->source_pdf_path);
    }

    public function test_non_ajax_get_fill_redirects_to_documents_with_fill_query(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();

        $response = $this->get(route('account.documents.fill', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Native GET fill не должен отдавать голый 200 HTML');
        $response->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));
    }

    public function test_user_without_permission_non_ajax_generate_gets_403(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $response = $this->actingAs($actor)
            ->withSession($this->accountDocumentsSession())
            ->from(route('account.documents.index'))
            ->post(route('account.documents.generate', $contract), [
                '_token' => csrf_token(),
                'fields' => $this->phoneGenerateFields(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertForbidden();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
    }

    public function test_guest_non_ajax_generate_redirects_to_login(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();
        Auth::logout();

        $response = $this->post(route('account.documents.generate', $contract), [
            '_token' => csrf_token(),
            'fields' => $this->phoneGenerateFields(),
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect(route('login'));
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
    }
}
