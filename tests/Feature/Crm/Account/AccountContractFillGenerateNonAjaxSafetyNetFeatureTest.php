<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Services\Contracts\ContractTemplatePrefillSources;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net generate: без X-Requested-With — 302 на документы,
 * запись обновлена; валидация — 302 + errors[fields.*], не пустой 200 и не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountContractFillGenerateNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_non_ajax_generate_redirects_and_creates_pdf(): void
    {
        $contract = $this->makeRequiredPassportEmailContract();

        $response = $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.generate', $contract), [
                '_token' => csrf_token(),
                'fields' => [
                    'parent_passport' => '4500 111222',
                    'parent_email'    => 'nona-jax@example.com',
                ],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Успех non-AJAX не должен быть пустым 200');
        $response->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));
        $response->assertSessionHas('success');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertNotNull($contract->source_pdf_path);
        $this->assertSame('4500 111222', $contract->filled_data['parent_passport'] ?? null);
        $this->assertSame('nona-jax@example.com', $contract->filled_data['parent_email'] ?? null);
    }

    public function test_non_ajax_empty_required_fields_redirect_with_errors_on_fields(): void
    {
        $contract = $this->makeRequiredPassportEmailContract();

        $response = $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.generate', $contract), [
                '_token' => csrf_token(),
                'fields' => [
                    'parent_passport' => '',
                    'parent_email'    => '',
                ],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Валидация не должна давать пустой/успешный 200');
        $response->assertStatus(302);
        $response->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));
        $response->assertSessionHasErrors(['fields.parent_passport', 'fields.parent_email']);

        $sessionErrors = session('errors');
        $this->assertNotNull($sessionErrors);
        $passportMessage = $sessionErrors->first('fields.parent_passport');
        $this->assertIsString($passportMessage);
        $this->assertStringContainsString('Паспорт (серия и номер)', $passportMessage);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertNull($contract->source_pdf_path);
    }

    public function test_user_without_permission_non_ajax_generate_gets_403(): void
    {
        $contract = $this->makeRequiredPassportEmailContract();
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $response = $this->actingAs($actor)
            ->withSession($this->accountDocumentsSession())
            ->from(route('account.documents.index'))
            ->post(route('account.documents.generate', $contract), [
                '_token' => csrf_token(),
                'fields' => [
                    'parent_passport' => '4010 000000',
                    'parent_email'    => 'forbidden@example.com',
                ],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertForbidden();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
    }

    private function makeRequiredPassportEmailContract(): Contract
    {
        return $this->makeAwaitingFillContract(
            [
                [
                    'key'            => 'parent_passport',
                    'label'          => 'Родитель: паспорт',
                    'required'       => true,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_PASSPORT,
                ],
                [
                    'key'            => 'parent_email',
                    'label'          => 'Родитель: email',
                    'required'       => true,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_EMAIL,
                ],
            ],
            ['parent_passport', 'parent_email'],
        );
    }
}
