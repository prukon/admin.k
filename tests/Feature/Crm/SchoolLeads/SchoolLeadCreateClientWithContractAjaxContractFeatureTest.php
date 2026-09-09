<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * AJAX-контракт POST /admin/users при создании клиента из заявки с договором.
 */
final class SchoolLeadCreateClientWithContractAjaxContractFeatureTest extends SchoolLeadCreateClientWithContractTestCase
{
    public function test_ajax_send_contract_returns_user_and_contract_id(): void
    {
        Mail::fake();
        $this->actingAsLeadsUsersAndContractsViewer();
        $template = $this->makeContractTemplate();
        $lead = $this->makeLead();

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => ['id'],
                'welcome_email_sent',
                'contract_id',
            ]);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertGreaterThan(0, (int) $response->json('contract_id'));
        $this->assertSame((int) $response->json('user.id'), (int) $lead->fresh()->user_id);
        $this->assertSame(1, Contract::query()->count());
    }

    public function test_ajax_insufficient_balance_returns_422_wallet_message(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->partner->wallet_balance_cents = 500;
        $this->partner->save();

        $template = $this->makeContractTemplate();
        $lead = $this->makeLead();

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['wallet']);

        $message = (string) ($response->json('errors.wallet.0') ?? '');
        $this->assertNotSame('', $message);
        $this->assertStringContainsString('Недостаточно средств', $message);
        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_ajax_without_contract_does_not_require_template(): void
    {
        Mail::fake();
        $this->actingAsLeadsUsersAndContractsViewer();
        $lead = $this->makeLead();

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract' => 0,
            ]),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('contract_id', null);

        $this->assertNotNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_ajax_foreign_template_returns_422_on_contract_template_id(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $foreignTemplate = $this->makeContractTemplate([
            'partner_id' => $this->foreignPartner->id,
        ]);
        $lead = $this->makeLead();

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $foreignTemplate->id,
            ]),
            $this->ajaxHeaders()
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contract_template_id']);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame('', (string) ($response->json('errors.contract_template_id.0') ?? ''));
        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_ajax_occupied_email_with_send_contract_returns_parent_email_error_not_wallet(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $occupied = 'ajax-taken-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);
        $template = $this->makeContractTemplate();
        $lead = $this->makeLead(['parent_email' => $occupied]);

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_email'        => $occupied,
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_email']);
        $this->assertArrayNotHasKey('wallet', $response->json('errors') ?? []);
        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(10000, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_ajax_validate_only_occupied_email_returns_parent_email_and_does_not_create(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $occupied = 'ajax-validate-taken-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);
        $lead = $this->makeLead(['parent_email' => $occupied]);

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_email'  => $occupied,
                'send_contract'  => 0,
                'validate_only' => 1,
            ]),
            $this->ajaxHeaders()
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_email'])
            ->assertJsonPath('errors.parent_email.0', 'Этот адрес электронной почты уже зарегистрирован.');
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_ajax_validate_only_success_returns_ok_and_does_not_create(): void
    {
        Mail::fake();
        $this->actingAsLeadsUsersAndContractsViewer();
        $lead = $this->makeLead();

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'  => 0,
                'validate_only' => 1,
            ]),
            $this->ajaxHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNull($response->json('user'));
        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
        Mail::assertNothingSent();
    }

    public function test_ajax_validate_only_sibling_same_parent_returns_ok_and_does_not_create(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $email = 'ajax-validate-sib-'.uniqid('', true).'@example.test';
        $sibling = $this->makeOccupiedStudentLogin($email);
        $lead = $this->makeLead(['parent_email' => $email]);

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_id'      => $sibling->parent_id,
                'parent_email'   => $email,
                'send_contract'  => 0,
                'validate_only' => 1,
            ]),
            $this->ajaxHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(
            1,
            User::query()->where('partner_id', $this->partner->id)->where('email', $email)->count()
        );
    }
}
