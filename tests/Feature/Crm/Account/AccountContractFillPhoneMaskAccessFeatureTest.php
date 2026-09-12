<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к fill/generate с телефонными полями: владелец 200, без права 403,
 * гость redirect/401, чужой 404, лишние HTTP-методы не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountContractFillPhoneMaskAccessFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
    }

    public function test_guest_is_denied_on_fill_and_generate(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();
        Auth::logout();

        $this->get(route('account.documents.fill', $contract))->assertRedirect(route('login'));
        $this->post(route('account.documents.generate', $contract), [
            'fields' => $this->phoneGenerateFields(),
        ])->assertRedirect(route('login'));

        $ajaxFill = $this->withHeaders($this->contractFillAjaxHeaders())
            ->getJson(route('account.documents.fill', $contract));
        $this->assertNotSame(500, $ajaxFill->getStatusCode());
        $this->assertTrue(
            $ajaxFill->isRedirect() || in_array($ajaxFill->getStatusCode(), [401, 403], true),
            'Гость fill JSON: получено ' . $ajaxFill->getStatusCode(),
        );

        $ajaxGenerate = $this->postJson(
            route('account.documents.generate', $contract),
            ['fields' => $this->phoneGenerateFields()],
            $this->contractFillAjaxHeaders(),
        );
        $this->assertNotSame(500, $ajaxGenerate->getStatusCode());
        $this->assertTrue(
            $ajaxGenerate->isRedirect() || in_array($ajaxGenerate->getStatusCode(), [401, 403], true),
            'Гость generate JSON: получено ' . $ajaxGenerate->getStatusCode(),
        );

        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
        $this->assertNull($contract->fresh()->filled_data);
    }

    public function test_user_without_documents_view_gets_403(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);
        $session = $this->accountDocumentsSession();

        $this->actingAs($actor)->withSession($session)
            ->get(route('account.documents.fill', $contract))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->post(route('account.documents.generate', $contract), [
                'fields' => $this->phoneGenerateFields(),
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('account.documents.generate', $contract), [
                'fields' => $this->phoneGenerateFields(),
            ], $this->contractFillAjaxHeaders())
            ->assertForbidden();

        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
    }

    public function test_owner_with_permission_gets_fill_json_and_can_generate(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();
        $session = $this->accountDocumentsSession();

        $this->withSession($session)
            ->get(route('account.documents.index'))
            ->assertOk();

        $this->withSession($session)
            ->withHeaders($this->contractFillAjaxHeaders())
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk()
            ->assertJsonStructure(['title', 'html', 'poll']);

        $this->flushHeaders();

        $this->withSession($session)
            ->post(route('account.documents.generate', $contract), [
                'fields' => $this->phoneGenerateFields(),
            ])
            ->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame('+7 (906) 247-55-08', $contract->filled_data['parent_phone'] ?? null);
    }

    public function test_foreign_partner_user_gets_404(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();

        $response = $this->actingAs($this->foreignUser)
            ->withSession([
                'current_partner' => $this->foreignPartner->id,
                '2fa:passed'      => true,
            ])
            ->post(route('account.documents.generate', $contract), [
                'fields' => $this->phoneGenerateFields(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertNotFound();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
    }

    public function test_same_school_stranger_gets_404(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();
        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $this->actingAs($other)
            ->withSession($this->accountDocumentsSession())
            ->post(route('account.documents.generate', $contract), [
                'fields' => $this->phoneGenerateFields(),
            ])
            ->assertNotFound();

        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->fresh()->status);
    }

    public function test_wrong_http_methods_on_generate_are_not_500_or_empty_200(): void
    {
        $contract = $this->makeRequiredPhoneFillContract();
        $url = route('account.documents.generate', $contract);
        $payload = ['fields' => $this->phoneGenerateFields()];

        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->withSession($this->accountDocumentsSession())
                ->withHeaders($this->contractFillAjaxHeaders())
                ->call($method, $url, $payload);

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
