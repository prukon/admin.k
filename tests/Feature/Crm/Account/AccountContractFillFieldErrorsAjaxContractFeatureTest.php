<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Services\Contracts\ContractTemplatePrefillSources;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт generate в модалке заполнения: 200 JSON, 422 errors[fields.*] под полями,
 * 403/гость, чужие методы не 500. UX-баг: 422 не должен быть пустым 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountContractFillFieldErrorsAjaxContractFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_owner_ajax_generate_returns_json_message_and_saves_contract(): void
    {
        $contract = $this->makeRequiredPassportEmailContract();

        $this->postJson(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_passport' => '4010 654321',
                'parent_email'    => 'owner@example.com',
            ],
        ], $this->contractFillAjaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['message', 'poll'])
            ->assertJsonPath('poll', false);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame('4010 654321', $contract->filled_data['parent_passport'] ?? null);
        $this->assertSame('owner@example.com', $contract->filled_data['parent_email'] ?? null);
        $this->assertNotNull($contract->source_pdf_path);
    }

    public function test_ajax_empty_required_fields_return_422_errors_under_each_field(): void
    {
        $contract = $this->makeRequiredPassportEmailContract();

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
        $this->assertArrayNotHasKey('contract', $errors);

        $passportMessage = $errors['fields.parent_passport'][0] ?? '';
        $emailMessage = $errors['fields.parent_email'][0] ?? '';
        $this->assertStringContainsString('Паспорт (серия и номер)', $passportMessage);
        $this->assertStringContainsString('Email', $emailMessage);
        $this->assertStringNotContainsString('Родитель:', $passportMessage);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertNull($contract->source_pdf_path);
    }

    public function test_ajax_invalid_date_returns_422_under_birthday_field(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'child_birthday', 'label' => 'Ребёнок: дата рождения', 'required' => true],
            ],
            ['parent_full_name', 'child_birthday'],
        );

        $this->postJson(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname' => 'Иванов',
                'child_birthday'  => now()->addDay()->format('Y-m-d'),
            ],
        ], $this->contractFillAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.child_birthday']);
    }

    public function test_fill_html_has_laravel_error_slots_and_no_html5_required(): void
    {
        $html = $this->getContractFillModalHtml($this->makeRequiredPassportEmailContract());

        $this->assertStringContainsString('class="contract-fill-form" novalidate', $html);
        $this->assertStringContainsString('data-error-for="fields.parent_passport"', $html);
        $this->assertStringContainsString('data-error-for="fields.parent_email"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/name="fields\[parent_email\]"[^>]*\srequired/u',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="fields\[parent_passport\]"[^>]*\srequired/u',
            $html,
        );
        $this->assertStringNotContainsString('<ul class="mb-0">', $html);
    }

    public function test_user_without_documents_view_gets_403_on_ajax_generate(): void
    {
        $contract = $this->makeRequiredPassportEmailContract();
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $response = $this->actingAs($actor)
            ->withSession($this->accountDocumentsSession())
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

    public function test_guest_ajax_generate_is_denied_and_not_500(): void
    {
        $contract = $this->makeRequiredPassportEmailContract();
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

    public function test_wrong_http_methods_on_generate_are_not_500_or_empty_200(): void
    {
        $contract = $this->makeRequiredPassportEmailContract();
        $url = route('account.documents.generate', $contract);

        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->withHeaders($this->contractFillAjaxHeaders())
                ->call($method, $url, [
                    'fields' => [
                        'parent_passport' => '4010 111111',
                        'parent_email'    => 'method@example.com',
                    ],
                ]);

            $this->assertNotSame(500, $response->getStatusCode(), $method . ' generate не должен быть 500');
            $this->assertNotSame(
                200,
                $response->getStatusCode(),
                $method . ' generate не должен быть успешным 200',
            );
            $this->assertContains(
                $response->getStatusCode(),
                [401, 403, 404, 405, 419, 422],
                $method . ' generate: отказ, получено ' . $response->getStatusCode(),
            );
        }

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
