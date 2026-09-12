<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX generate с телефоном: 200 JSON + маска в filled_data, 422 errors[fields.parent_phone],
 * 403/гость. UX-баг: 10 цифр autoUnmask не должны остаться в JSON/БД без маски.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountContractFillPhoneMaskAjaxContractFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_owner_ajax_generate_returns_json_and_masks_ten_digit_phone(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();

        $this->postJson(route('account.documents.generate', $contract), [
            'fields' => $this->phoneGenerateFields(),
        ], $this->contractFillAjaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['message', 'poll'])
            ->assertJsonPath('poll', false);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame('+7 (906) 247-55-08', $contract->filled_data['parent_phone'] ?? null);
        $this->assertNotSame('9062475508', $contract->filled_data['parent_phone'] ?? null);
        $this->assertNotNull($contract->source_pdf_path);
    }

    public function test_ajax_generate_with_already_masked_phone_keeps_mask(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();

        $this->postJson(route('account.documents.generate', $contract), [
            'fields' => $this->phoneGenerateFields([
                'parent_phone' => '+7 (906) 247-55-08',
            ]),
        ], $this->contractFillAjaxHeaders())
            ->assertOk();

        $this->assertSame(
            '+7 (906) 247-55-08',
            $contract->fresh()->filled_data['parent_phone'] ?? null,
        );
    }

    public function test_ajax_empty_required_phone_returns_422_under_parent_phone(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();

        $response = $this->postJson(route('account.documents.generate', $contract), [
            'fields' => $this->phoneGenerateFields([
                'parent_phone' => '',
            ]),
        ], $this->contractFillAjaxHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.parent_phone']);

        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('fields.parent_phone', $errors);
        $this->assertArrayNotHasKey('contract', $errors);
        $this->assertStringContainsString('Телефон', $errors['fields.parent_phone'][0] ?? '');
        $this->assertStringNotContainsString('Родитель:', $errors['fields.parent_phone'][0] ?? '');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertNull($contract->source_pdf_path);
    }

    public function test_user_without_documents_view_gets_403_on_ajax_generate(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $response = $this->actingAs($actor)
            ->withSession($this->accountDocumentsSession())
            ->postJson(route('account.documents.generate', $contract), [
                'fields' => $this->phoneGenerateFields(),
            ], $this->contractFillAjaxHeaders());

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertForbidden();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
    }

    public function test_guest_ajax_generate_is_denied_and_not_500(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();
        Auth::logout();

        $response = $this->postJson(route('account.documents.generate', $contract), [
            'fields' => $this->phoneGenerateFields(),
        ], $this->contractFillAjaxHeaders());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertTrue(
            $response->isRedirect() || in_array($response->getStatusCode(), [401, 403], true),
            'Гость: получено ' . $response->getStatusCode(),
        );
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
    }

    public function test_wrong_http_methods_on_generate_are_not_500_or_empty_200(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();
        $url = route('account.documents.generate', $contract);

        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->withHeaders($this->contractFillAjaxHeaders())
                ->call($method, $url, [
                    'fields' => $this->phoneGenerateFields(),
                ]);

            $this->assertNotSame(500, $response->getStatusCode(), $method . ' не 500');
            $this->assertNotSame(200, $response->getStatusCode(), $method . ' не успешный 200');
            $this->assertContains(
                $response->getStatusCode(),
                [401, 403, 404, 405, 419, 422],
                $method . ': получено ' . $response->getStatusCode(),
            );
        }

        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
    }
}
