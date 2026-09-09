<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Создание клиента из заявки с договором: баланс, шаблон, юр. лицо; без договора при нулевом балансе.
 */
final class SchoolLeadCreateClientWithContractFeatureTest extends SchoolLeadCreateClientWithContractTestCase
{
    public function test_send_contract_with_insufficient_balance_does_not_create_client(): void
    {
        Mail::fake();
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->partner->wallet_balance_cents = 0;
        $this->partner->save();

        $template = $this->makeContractTemplate();
        $lead = $this->makeLead();

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wallet']);

        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(0, User::query()->where('partner_id', $this->partner->id)->where('lastname', $lead->child_lastname)->count());
        Mail::assertNothingSent();
    }

    public function test_without_contract_creates_client_when_balance_is_zero(): void
    {
        Mail::fake();
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->partner->wallet_balance_cents = 0;
        $this->partner->save();

        $lead = $this->makeLead();

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract' => 0,
            ]),
            $this->ajaxHeaders()
        );
        $response->assertOk();
        $this->assertGreaterThan(0, (int) $response->json('user.id'));
        $this->assertNull($response->json('contract_id'));

        $this->assertNotNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(0, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_send_contract_creates_client_and_charges_balance(): void
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

        $response->assertOk();
        $this->assertGreaterThan(0, (int) $response->json('user.id'));

        $userId = (int) $response->json('user.id');
        $contractId = (int) $response->json('contract_id');
        $this->assertGreaterThan(0, $contractId);
        $this->assertSame($userId, (int) $lead->fresh()->user_id);

        $contract = Contract::query()->findOrFail($contractId);
        $this->assertSame($userId, (int) $contract->user_id);
        $this->assertSame(Contract::CREATION_MODE_TEMPLATE, $contract->creation_mode);
        $this->assertSame(3000, (int) $this->partner->fresh()->wallet_balance_cents);
        $this->assertStringContainsString('70', (string) $response->json('message'));
    }

    public function test_send_contract_without_template_does_not_create_client(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $lead = $this->makeLead();

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract' => 1,
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_template_id']);

        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_send_contract_without_legal_entity_does_not_create_client(): void
    {
        Mail::fake();
        $this->actingAsLeadsUsersAndContractsViewer();

        $team = $this->makeTeamWithoutLegalEntity();
        $template = $this->makeLegalEntityTemplate();
        $lead = $this->makeLead(['team_id' => $team->id]);

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
                'team_id'              => $team->id,
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['send_contract']);

        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(10000, (int) $this->partner->fresh()->wallet_balance_cents);
        Mail::assertNothingSent();
    }

    public function test_after_insufficient_balance_admin_creates_client_without_contract_and_balance_stays(): void
    {
        Mail::fake();
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->partner->wallet_balance_cents = 0;
        $this->partner->save();

        $template = $this->makeContractTemplate();
        $lead = $this->makeLead();

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wallet']);

        $this->assertNull($lead->fresh()->user_id);

        $retry = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract' => 0,
            ]),
            $this->ajaxHeaders()
        );
        $retry->assertOk();
        $this->assertNull($retry->json('contract_id'));
        $this->assertNotNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(0, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_foreign_partner_template_does_not_create_client(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $foreignTemplate = $this->makeContractTemplate([
            'partner_id' => $this->foreignPartner->id,
            'title'      => 'Чужой шаблон',
        ]);
        $lead = $this->makeLead();

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $foreignTemplate->id,
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_template_id']);

        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(10000, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_archived_template_does_not_create_client(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $template = $this->makeContractTemplate(['is_archived' => true]);
        $lead = $this->makeLead();

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_template_id']);

        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_send_contract_without_school_lead_does_not_create_contract(): void
    {
        Mail::fake();
        $this->actingAsLeadsUsersAndContractsViewer();
        $template = $this->makeContractTemplate();

        $response = $this->postJson(route('admin.user.store'), [
            'name'                 => 'Иван',
            'lastname'             => 'БезЗаявки',
            'role_id'              => $this->studentRoleId(),
            'is_enabled'           => 1,
            'send_contract'        => 1,
            'contract_template_id' => $template->id,
        ], $this->ajaxHeaders());

        $response->assertOk();
        $this->assertGreaterThan(0, (int) $response->json('user.id'));
        $this->assertNull($response->json('contract_id'));
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(10000, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_exact_fee_balance_creates_client_and_contract(): void
    {
        Mail::fake();
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->partner->wallet_balance_cents = 7000;
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

        $response->assertOk();
        $this->assertGreaterThan(0, (int) $response->json('contract_id'));
        $this->assertNotNull($lead->fresh()->user_id);
        $this->assertSame(0, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_one_cent_below_fee_does_not_create_client(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->partner->wallet_balance_cents = 6999;
        $this->partner->save();

        $template = $this->makeContractTemplate();
        $lead = $this->makeLead();

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wallet']);

        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(6999, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_validate_only_occupied_email_does_not_create_client(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $occupied = 'validate-only-taken-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);
        $lead = $this->makeLead(['parent_email' => $occupied]);

        $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'parent_email'  => $occupied,
                'send_contract'  => 0,
                'validate_only' => 1,
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_email'])
            ->assertJsonPath('errors.parent_email.0', 'Этот адрес электронной почты уже зарегистрирован.');

        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(0, User::query()->where('partner_id', $this->partner->id)->where('lastname', $lead->child_lastname)->count());
    }

    public function test_validate_only_success_does_not_create_client_even_with_zero_balance_and_send_contract(): void
    {
        Mail::fake();
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->partner->wallet_balance_cents = 0;
        $this->partner->save();

        $template = $this->makeContractTemplate();
        $lead = $this->makeLead();

        $response = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
                'validate_only'        => 1,
            ]),
            $this->ajaxHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertArrayNotHasKey('user', $response->json() ?? []);
        $this->assertNull($lead->fresh()->user_id);
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(0, (int) $this->partner->fresh()->wallet_balance_cents);
        Mail::assertNothingSent();
    }

    public function test_validate_only_without_school_lead_id_is_ignored_and_creates_user(): void
    {
        Mail::fake();
        $this->actingAsLeadsUsersAndContractsViewer();

        $response = $this->postJson(route('admin.user.store'), [
            'name'           => 'Иван',
            'lastname'       => 'ТолькоПроверка',
            'role_id'        => $this->studentRoleId(),
            'is_enabled'     => 1,
            'validate_only'  => 1,
        ], $this->ajaxHeaders());

        $response->assertOk();
        $this->assertGreaterThan(0, (int) $response->json('user.id'));
        $this->assertNotNull(
            User::query()
                ->where('partner_id', $this->partner->id)
                ->where('lastname', 'ТолькоПроверка')
                ->first()
        );
    }
}
