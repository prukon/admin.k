<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\Account\Concerns\InteractsWithFamilyAccountDocuments;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net generate договора sibling: 302 + запись / 302 + errors[fields.*].
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountDocumentsFamilyContextNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_sibling_non_ajax_generate_redirects_and_creates_pdf(): void
    {
        $contract = $this->makeRequiredPassportEmailContractFor($this->brother2);
        $this->actingAsBrother1($this->brother1->id);

        $response = $this->from(route('account.documents.index', [
            'student' => $this->brother2->id,
            'fill'    => $contract->id,
        ]))->post(route('account.documents.generate', $contract), [
            '_token' => csrf_token(),
            'fields' => [
                'parent_passport' => '4500 111222',
                'parent_email'    => 'family-nona@example.com',
            ],
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Успех non-AJAX не должен быть пустым 200');
        $response->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));
        $response->assertSessionHas('success');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame($this->brother2->id, (int) $contract->user_id);
        $this->assertNotNull($contract->source_pdf_path);
        $this->assertSame('4500 111222', $contract->filled_data['parent_passport'] ?? null);
        $this->assertSame('family-nona@example.com', $contract->filled_data['parent_email'] ?? null);
    }

    public function test_sibling_non_ajax_empty_required_fields_redirect_with_errors_on_fields(): void
    {
        $contract = $this->makeRequiredPassportEmailContractFor($this->brother2);
        $this->actingAsBrother1();

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

    public function test_user_without_permission_non_ajax_generate_on_sibling_contract_gets_403(): void
    {
        $contract = $this->makeRequiredPassportEmailContractFor($this->brother2);
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $response = $this->actingAs($actor)
            ->withSession($this->familyDocumentsSession())
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
}
