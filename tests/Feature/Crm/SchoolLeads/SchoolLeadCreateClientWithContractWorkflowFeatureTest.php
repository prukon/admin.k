<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Contract;

/**
 * [P2] Страница заявок → 422 кошелёк → без договора → клиент в DataTables без F5.
 */
final class SchoolLeadCreateClientWithContractWorkflowFeatureTest extends SchoolLeadCreateClientWithContractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_wallet_error_then_without_contract_shows_client_in_datatable_without_reload(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->partner->wallet_balance_cents = 0;
        $this->partner->save();

        $template = $this->makeContractTemplate(['title' => 'Воркфлоу шаблон']);
        $lead = $this->makeLead([
            'child_lastname'  => 'Воркфлоу',
            'child_firstname' => 'Клиент',
        ]);

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('id="editLeadModal"', false)
            ->assertSee('id="createClientBtn"', false)
            ->assertSee('id="createLeadClientChoiceModal"', false)
            ->assertSee('id="leadCreateClientModeWithContract"', false)
            ->assertSee('Воркфлоу шаблон', false)
            ->assertSee('id="kidsMainToast"', false);

        $fail = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'name'                 => 'Клиент',
                'lastname'             => 'Воркфлоу',
                'send_contract'        => 1,
                'contract_template_id' => $template->id,
            ]),
            $this->ajaxHeaders()
        );
        $fail->assertStatus(422)
            ->assertJsonValidationErrors(['wallet']);
        $this->assertNull($lead->fresh()->user_id);

        $store = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'name'          => 'Клиент',
                'lastname'      => 'Воркфлоу',
                'send_contract' => 0,
            ]),
            $this->ajaxHeaders()
        );
        $store->assertOk();
        $userId = (int) $store->json('user.id');
        $this->assertNull($store->json('contract_id'));

        $data = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 25,
        ]));
        $data->assertOk();
        $this->assertNotSame('', trim((string) $data->getContent()));

        $row = collect($data->json('data') ?? [])
            ->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $lead->id);
        $this->assertIsArray($row);
        $this->assertSame($userId, (int) $row['user_id']);
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(0, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_validate_only_occupied_email_then_free_email_then_create_shows_in_datatable(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $occupied = 'workflow-taken-'.uniqid('', true).'@example.test';
        $this->makeOccupiedStudentLogin($occupied);
        $free = 'workflow-free-'.uniqid('', true).'@example.test';

        $lead = $this->makeLead([
            'child_lastname'  => 'ВоркфлоуПроверка',
            'child_firstname' => 'Клиент',
            'parent_email'   => $occupied,
        ]);

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk()
            ->assertSee('id="editLeadModal"', false)
            ->assertSee('id="createClientBtn"', false)
            ->assertSee('id="createLeadClientChoiceModal"', false)
            ->assertSee('id="kidsMainToast"', false);

        $occupiedCheck = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'name'          => 'Клиент',
                'lastname'      => 'ВоркфлоуПроверка',
                'parent_email'  => $occupied,
                'send_contract'  => 0,
                'validate_only' => 1,
            ]),
            $this->ajaxHeaders()
        );
        $occupiedCheck->assertStatus(422)
            ->assertJsonValidationErrors(['parent_email'])
            ->assertJsonPath('errors.parent_email.0', 'Этот адрес электронной почты уже зарегистрирован.');
        $this->assertNull($lead->fresh()->user_id);

        $okCheck = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'name'          => 'Клиент',
                'lastname'      => 'ВоркфлоуПроверка',
                'parent_email'  => $free,
                'send_contract'  => 0,
                'validate_only' => 1,
            ]),
            $this->ajaxHeaders()
        );
        $okCheck->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertNull($lead->fresh()->user_id);

        $store = $this->postJson(
            route('admin.user.store'),
            $this->createClientPayload($lead, [
                'name'          => 'Клиент',
                'lastname'      => 'ВоркфлоуПроверка',
                'parent_email'  => $free,
                'send_contract' => 0,
            ]),
            $this->ajaxHeaders()
        );
        $store->assertOk();
        $userId = (int) $store->json('user.id');
        $this->assertGreaterThan(0, $userId);

        $data = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 25,
        ]));
        $data->assertOk();
        $row = collect($data->json('data') ?? [])
            ->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $lead->id);
        $this->assertIsArray($row);
        $this->assertSame($userId, (int) $row['user_id']);
    }
}
