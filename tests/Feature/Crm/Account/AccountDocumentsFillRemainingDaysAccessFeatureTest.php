<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Гость / без права / тренер / чужая школа не видят срок подписания на «Мои документы».
 *
 * @see /docs/documentation/account-contract-fill.html#account-documents-fill-remaining-days
 */
final class AccountDocumentsFillRemainingDaysAccessFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_is_redirected_and_does_not_see_remaining_days(): void
    {
        $this->seedLiveFillContract();

        Auth::logout();

        $resp = $this->get(route('account.documents.index'));
        $resp->assertRedirect(route('login'));
        $resp->assertDontSee(Contract::CLIENT_FILL_REMAINING_CAPTION, false);
        $resp->assertDontSee('Осталось 6 дней', false);
        $resp->assertDontSee(Contract::CLIENT_FILL_REMAINING_LAST_DAY, false);
    }

    public function test_user_without_documents_view_gets_403_without_remaining_days(): void
    {
        $this->seedLiveFillContract();

        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $resp = $this->actingAs($actor)
            ->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.index'));

        $resp->assertForbidden();
        $resp->assertDontSee(Contract::CLIENT_FILL_REMAINING_CAPTION, false);
        $resp->assertDontSee('Осталось 6 дней', false);
    }

    public function test_trainer_gets_403_without_remaining_days(): void
    {
        $this->seedLiveFillContract();
        $trainer = $this->createUserWithRole('trainer');

        $resp = $this->actingAs($trainer)
            ->withSession($this->accountDocumentsSession())
            ->get(route('account.documents.index'));

        $resp->assertForbidden();
        $resp->assertDontSee(Contract::CLIENT_FILL_REMAINING_CAPTION, false);
        $resp->assertDontSee('Осталось 6 дней', false);
    }

    public function test_foreign_school_does_not_see_this_school_remaining_days(): void
    {
        $this->seedLiveFillContract();

        $html = $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true])
            ->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(Contract::CLIENT_FILL_REMAINING_CAPTION, $html);
        $this->assertStringNotContainsString('Осталось 6 дней', $html);
    }

    public function test_authorized_owner_sees_remaining_days(): void
    {
        $this->seedLiveFillContract();

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(Contract::CLIENT_FILL_REMAINING_CAPTION, $html);
        $this->assertStringContainsString('Осталось 6 дней', $html);
    }

    public function test_unsupported_http_methods_on_documents_index_are_not_500(): void
    {
        $this->seedLiveFillContract();

        foreach (['PATCH', 'DELETE', 'POST', 'PUT'] as $method) {
            $url = route('account.documents.index');
            $response = $this->call($method, $url);
            $this->assertNotSame(500, $response->getStatusCode(), "{$method} {$url} не должен быть 500");
            $this->assertContains(
                $response->getStatusCode(),
                [404, 405, 419, 302, 403],
                "{$method} {$url} → {$response->getStatusCode()}"
            );
            $response->assertDontSee(Contract::CLIENT_FILL_REMAINING_CAPTION, false);
            $response->assertDontSee('Осталось 6 дней', false);
        }
    }

    private function seedLiveFillContract(): Contract
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $contract->update([
            'fill_expires_at' => Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow'),
        ]);

        return $contract->fresh();
    }
}
