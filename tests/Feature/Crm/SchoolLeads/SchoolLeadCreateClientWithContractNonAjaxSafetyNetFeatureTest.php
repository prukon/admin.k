<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Contract;
use App\Models\User;

/**
 * Non-AJAX: нехватка баланса не создаёт клиента; без договора — 302 и клиент в БД.
 */
final class SchoolLeadCreateClientWithContractNonAjaxSafetyNetFeatureTest extends SchoolLeadCreateClientWithContractTestCase
{
    public function test_non_ajax_insufficient_balance_redirects_without_client(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->partner->wallet_balance_cents = 0;
        $this->partner->save();

        $template = $this->makeContractTemplate();
        $lead = $this->makeLead();

        $response = $this->from(route('admin.school-leads'))
            ->post(route('admin.user.store'), $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]));

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertSessionHasErrors(['wallet']);
        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_non_ajax_without_contract_redirects_and_creates_client(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->partner->wallet_balance_cents = 0;
        $this->partner->save();

        $lead = $this->makeLead();

        $response = $this->from(route('admin.school-leads'))
            ->post(route('admin.user.store'), $this->createClientPayload($lead, [
                'send_contract' => 0,
            ]));

        $response->assertRedirect(route('admin.user1'));
        $this->assertNotSame(200, $response->getStatusCode());

        $user = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', $lead->child_lastname)
            ->first();
        $this->assertNotNull($user);
        $this->assertSame($user->id, (int) $lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_non_ajax_with_contract_redirects_and_creates_client_and_contract(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $template = $this->makeContractTemplate();
        $lead = $this->makeLead();

        $response = $this->from(route('admin.school-leads'))
            ->post(route('admin.user.store'), $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]));

        $response->assertRedirect(route('admin.user1'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $user = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', $lead->child_lastname)
            ->first();
        $this->assertNotNull($user);
        $this->assertSame($user->id, (int) $lead->fresh()->user_id);
        $this->assertSame(1, Contract::query()->where('user_id', $user->id)->count());
        $this->assertSame(3000, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_non_ajax_missing_template_redirects_with_contract_template_id_error(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $lead = $this->makeLead();

        $response = $this->from(route('admin.school-leads'))
            ->post(route('admin.user.store'), $this->createClientPayload($lead, [
                'send_contract' => 1,
            ]));

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertSessionHasErrors(['contract_template_id']);
        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_non_ajax_after_wallet_error_without_contract_creates_client(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->partner->wallet_balance_cents = 0;
        $this->partner->save();

        $template = $this->makeContractTemplate();
        $lead = $this->makeLead();

        $fail = $this->from(route('admin.school-leads'))
            ->post(route('admin.user.store'), $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]));
        $fail->assertRedirect(route('admin.school-leads'));
        $fail->assertSessionHasErrors(['wallet']);
        $this->assertNull($lead->fresh()->user_id);

        $ok = $this->from(route('admin.school-leads'))
            ->post(route('admin.user.store'), $this->createClientPayload($lead, [
                'send_contract' => 0,
            ]));
        $ok->assertRedirect(route('admin.user1'));
        $this->assertNotNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(0, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_non_ajax_validate_only_redirects_without_creating_client(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $lead = $this->makeLead();

        $response = $this->from(route('admin.school-leads'))
            ->post(route('admin.user.store'), $this->createClientPayload($lead, [
                'send_contract'  => 0,
                'validate_only' => 1,
            ]));

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(
            0,
            User::query()->where('partner_id', $this->partner->id)->where('lastname', $lead->child_lastname)->count()
        );
    }
}
