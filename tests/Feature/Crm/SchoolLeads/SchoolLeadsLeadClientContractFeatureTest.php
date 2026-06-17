<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Contract;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use App\Services\SchoolLeads\LatestUserContractLookup;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Сценарии «лид → клиент → договор» на странице «Заявки с сайта».
 */
final class SchoolLeadsLeadClientContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    private function defaultRoleId(): int
    {
        return (int) Role::query()->where('is_visible', 1)->orderBy('order_by')->value('id');
    }

    public function test_index_renders_contract_column_and_workflow_controls(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewHas('canCreateUserFromLead', true)
            ->assertViewHas('canViewContracts', true)
            ->assertSee('Договор</th>', false)
            ->assertSee('id="slColContract"', false)
            ->assertSee('id="createClientBtn"', false)
            ->assertSee('Создать договор', false)
            ->assertSee('id="editLeadModal"', false)
            ->assertDontSee('create-user-from-lead', false);
    }

    public function test_datatable_contract_column_states_for_lead_without_user(): void
    {
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Только лид',
            'phone'      => '+7 900 300-00-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('name', 'Только лид');
        $this->assertNotNull($row);
        $this->assertNull($row['user_id']);
        $this->assertArrayNotHasKey('latest_contract', $row);
        $this->assertArrayNotHasKey('create_contract_url', $row);
    }

    public function test_datatable_signed_contract_uses_status_label_in_link_text(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/signed.pdf',
            'source_sha256'   => str_repeat('c', 64),
            'status'          => Contract::STATUS_SIGNED,
        ]);

        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Подписанный',
            'phone'      => '+7 900 400-00-04',
            'school_lead_status_id' => $this->schoolLeadSaleStatusId(),
            'user_id'    => $user->id,
        ]);

        $row = collect($this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->json('data'))->firstWhere('name', 'Подписанный');

        $this->assertNotNull($row);
        $this->assertSame(
            app(LatestUserContractLookup::class)->formatActionLabel($contract),
            $row['latest_contract']['label']
        );
        $this->assertStringContainsString('(Подписано)', $row['latest_contract']['label']);
    }

    public function test_create_user_from_lead_then_contract_create_url_available(): void
    {
        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Новый клиент',
            'phone'      => '+7 900 500-00-05',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $store = $this->postJson(route('admin.user.store'), [
            'name'           => 'Новый клиент',
            'lastname'       => 'Из лида',
            'role_id'        => $this->defaultRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $store->assertOk();
        $userId = (int) $store->json('user.id');

        $row = collect($this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]))->json('data'))->firstWhere('id', $lead->id);

        $this->assertNotNull($row);
        $this->assertSame($userId, (int) $row['user_id']);
        $this->assertArrayNotHasKey('latest_contract', $row);
        $this->assertStringContainsString(
            route('contracts.index', ['user_id' => $userId]),
            (string) $row['create_contract_url']
        );

        $this->get($row['create_contract_url'])
            ->assertOk()
            ->assertViewHas('preselectedUser', fn ($pre) => is_array($pre) && (int) $pre['id'] === $userId)
            ->assertViewHas('shouldOpenCreateModal', true);
    }

    public function test_store_rejects_school_lead_from_foreign_partner(): void
    {
        $foreignLead = SchoolLead::create([
            'partner_id' => $this->foreignPartner->id,
            'name'       => 'Чужой лид',
            'phone'      => '+7 900 600-00-06',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'           => 'Тест',
            'lastname'       => 'Тестов',
            'role_id'        => $this->defaultRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $foreignLead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['school_lead_id']);
    }

    public function test_columns_settings_support_contract_column_key(): void
    {
        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => [
                'name'     => true,
                'phone'    => true,
                'contract' => false,
                'actions'  => true,
            ],
        ])->assertOk();

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('contract', false);
    }

    public function test_datatable_contract_link_points_to_contract_show_route(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/show.pdf',
            'source_sha256'   => str_repeat('d', 64),
            'status'          => Contract::STATUS_SENT,
        ]);

        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Ссылка на договор',
            'phone'      => '+7 900 700-00-07',
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
            'user_id'    => $user->id,
        ]);

        $row = collect($this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->json('data'))->firstWhere('name', 'Ссылка на договор');

        $this->assertNotNull($row);
        $this->assertSame(
            route('contracts.show', ['contract' => $contract->id]),
            $row['latest_contract']['url'] ?? null
        );
    }
}
