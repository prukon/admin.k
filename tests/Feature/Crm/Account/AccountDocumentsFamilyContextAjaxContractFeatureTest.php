<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\Account\Concerns\InteractsWithFamilyAccountDocuments;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX generate/fill договора sibling: 200 JSON, 422 errors[fields.*], 403/гость.
 * UX-баг: generate не должен быть 403 только потому что вход под братом.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountDocumentsFamilyContextAjaxContractFeatureTest extends CrmTestCase
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

    public function test_sibling_ajax_generate_saves_contract_and_returns_json_message(): void
    {
        $contract = $this->makeRequiredPassportEmailContractFor($this->brother2);
        $this->actingAsBrother1($this->brother1->id);

        $this->postJson(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_passport' => '4010 654321',
                'parent_email'    => 'sibling@example.com',
            ],
        ], $this->contractFillAjaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['message', 'poll'])
            ->assertJsonPath('poll', false);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame($this->brother2->id, (int) $contract->user_id);
        $this->assertSame('4010 654321', $contract->filled_data['parent_passport'] ?? null);
        $this->assertSame('sibling@example.com', $contract->filled_data['parent_email'] ?? null);
        $this->assertNotNull($contract->source_pdf_path);
    }

    public function test_sibling_ajax_empty_required_fields_return_422_under_each_field(): void
    {
        $contract = $this->makeRequiredPassportEmailContractFor($this->brother2);
        $this->actingAsBrother1();

        $response = $this->postJson(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_passport' => '',
                'parent_email'    => '',
            ],
        ], $this->contractFillAjaxHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.parent_passport', 'fields.parent_email']);

        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('fields.parent_passport', $errors);
        $this->assertArrayHasKey('fields.parent_email', $errors);
        $this->assertStringContainsString('Паспорт (серия и номер)', $errors['fields.parent_passport'][0] ?? '');
        $this->assertStringContainsString('Email', $errors['fields.parent_email'][0] ?? '');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertNull($contract->source_pdf_path);
    }

    public function test_sibling_fill_html_has_error_slots_for_fields(): void
    {
        $contract = $this->makeRequiredPassportEmailContractFor($this->brother2);
        $this->actingAsBrother1();

        $html = (string) $this->withHeaders($this->contractFillAjaxHeaders())
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('class="contract-fill-form" novalidate', $html);
        $this->assertStringContainsString('data-error-for="fields.parent_passport"', $html);
        $this->assertStringContainsString('data-error-for="fields.parent_email"', $html);
    }

    public function test_user_without_permission_ajax_generate_on_sibling_contract_gets_403(): void
    {
        $contract = $this->makeRequiredPassportEmailContractFor($this->brother2);
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $response = $this->actingAs($actor)
            ->withSession($this->familyDocumentsSession())
            ->postJson(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_passport' => '4010 111111',
                    'parent_email'    => 'no-right@example.com',
                ],
            ], $this->contractFillAjaxHeaders());

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertForbidden();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
    }

    public function test_guest_ajax_generate_on_sibling_contract_is_denied_and_not_500(): void
    {
        $contract = $this->makeRequiredPassportEmailContractFor($this->brother2);
        Auth::logout();

        $response = $this->postJson(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_passport' => '4010 111111',
                'parent_email'    => 'guest@example.com',
            ],
        ], $this->contractFillAjaxHeaders());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertTrue(
            $response->isRedirect() || in_array($response->getStatusCode(), [401, 403], true),
            'Гость: redirect/401/403, получено ' . $response->getStatusCode(),
        );
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
    }
}
