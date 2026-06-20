<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Contract;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Столбец «Действия» на вкладке «Заявки»: скрытие кнопки редактирования после создания клиента.
 */
final class SchoolLeadsActionsColumnFeatureTest extends CrmTestCase
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

    /**
     * @return non-empty-string
     */
    private function actionsColumnRenderSnippet(): string
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $actionsColumnPos = strpos($html, "key: 'actions'");

        $this->assertNotFalse($actionsColumnPos);

        return substr($html, $actionsColumnPos, 900);
    }

    public function test_page_actions_column_hides_edit_button_when_user_id_is_set(): void
    {
        $snippet = $this->actionsColumnRenderSnippet();

        $this->assertStringContainsString('if (!row.user_id)', $snippet);
        $this->assertStringContainsString('edit-lead', $snippet);
        $this->assertStringContainsString('delete-lead', $snippet);
        $this->assertStringContainsString("title=\"Редактировать\"", $snippet);
    }

    public function test_page_actions_column_always_renders_delete_button_outside_user_id_check(): void
    {
        $snippet = $this->actionsColumnRenderSnippet();

        $userIdCheckPos = strpos($snippet, 'if (!row.user_id)');
        $deleteButtonPos = strpos($snippet, 'delete-lead');

        $this->assertNotFalse($userIdCheckPos);
        $this->assertNotFalse($deleteButtonPos);
        $this->assertGreaterThan($userIdCheckPos, $deleteButtonPos);
    }

    public function test_page_name_column_still_uses_edit_lead_link_for_viewing_client_lead(): void
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $nameColumnPos = strpos($html, "key: 'name'");
        $statusColumnPos = strpos($html, "key: 'status'");

        $this->assertNotFalse($nameColumnPos);
        $this->assertNotFalse($statusColumnPos);

        $nameColumnSnippet = substr($html, $nameColumnPos, $statusColumnPos - $nameColumnPos);

        $this->assertStringContainsString("linkClass: 'edit-lead'", $nameColumnSnippet);
        $this->assertStringNotContainsString('if (!row.user_id)', $nameColumnSnippet);
    }

    public function test_datatable_lead_without_client_returns_null_user_id(): void
    {
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Без клиента actions',
            'phone'                 => '+7 900 810-10-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $row = $this->fetchDatatableRowByName('Без клиента actions');

        $this->assertNotNull($row);
        $this->assertNull($row['user_id']);
    }

    public function test_datatable_lead_with_client_without_contract_returns_user_id(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Клиент без договора actions',
            'phone'                 => '+7 900 810-10-02',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $user->id,
        ]);

        $row = $this->fetchDatatableRowByName('Клиент без договора actions');

        $this->assertNotNull($row);
        $this->assertSame($user->id, (int) $row['user_id']);
        $this->assertArrayHasKey('create_contract_url', $row);
        $this->assertArrayNotHasKey('latest_contract', $row);
    }

    public function test_datatable_lead_with_client_and_contract_returns_user_id(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/actions-column.pdf',
            'source_sha256'   => str_repeat('e', 64),
            'status'          => Contract::STATUS_SENT,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Клиент с договором actions',
            'phone'                 => '+7 900 810-10-03',
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
            'user_id'               => $user->id,
        ]);

        $row = $this->fetchDatatableRowByName('Клиент с договором actions');

        $this->assertNotNull($row);
        $this->assertSame($user->id, (int) $row['user_id']);
        $this->assertArrayHasKey('latest_contract', $row);
        $this->assertArrayNotHasKey('create_contract_url', $row);
    }

    public function test_datatable_mixed_leads_expose_user_id_only_when_client_exists(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Смешанный без клиента',
            'phone'                 => '+7 900 810-10-04',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Смешанный с клиентом',
            'phone'                 => '+7 900 810-10-05',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $user->id,
        ]);

        $withoutClient = $this->fetchDatatableRowByName('Смешанный без клиента');
        $withClient = $this->fetchDatatableRowByName('Смешанный с клиентом');

        $this->assertNotNull($withoutClient);
        $this->assertNotNull($withClient);
        $this->assertNull($withoutClient['user_id']);
        $this->assertSame($user->id, (int) $withClient['user_id']);
    }

    public function test_create_client_from_lead_sets_user_id_for_actions_column_logic(): void
    {
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Workflow actions column',
            'phone'                 => '+7 900 810-10-06',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'child_lastname'        => 'Actions',
            'child_firstname'       => 'Column',
        ]);

        $before = $this->fetchDatatableRowById($lead->id);
        $this->assertNotNull($before);
        $this->assertNull($before['user_id']);

        $store = $this->postJson(route('admin.user.store'), [
            'name'           => 'Column',
            'lastname'       => 'Actions',
            'role_id'        => $this->defaultRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $store->assertOk();

        $userId = (int) $store->json('user.id');
        $this->assertSame($userId, (int) $lead->fresh()->user_id);

        $after = $this->fetchDatatableRowById($lead->id);
        $this->assertNotNull($after);
        $this->assertSame($userId, (int) $after['user_id']);
        $this->assertArrayHasKey('create_contract_url', $after);
    }

    public function test_datatable_lead_with_client_without_contracts_view_still_returns_user_id(): void
    {
        $denied = $this->createUserWithoutPermission('contracts.view', $this->partner);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $denied->role_id,
            'permission_id' => $this->permissionId('schoolLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Клиент без contracts.view actions',
            'phone'                 => '+7 900 810-10-07',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $user->id,
        ]);

        $this->actingAs($denied)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $row = $this->fetchDatatableRowByName('Клиент без contracts.view actions');

        $this->assertNotNull($row);
        $this->assertSame($user->id, (int) $row['user_id']);
        $this->assertArrayNotHasKey('create_contract_url', $row);
        $this->assertArrayNotHasKey('latest_contract', $row);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchDatatableRowByName(string $name): ?array
    {
        return collect($this->fetchDatatableRows())->firstWhere('name', $name);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchDatatableRowById(int $id): ?array
    {
        return collect($this->fetchDatatableRows())->firstWhere('id', $id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchDatatableRows(): array
    {
        return $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 100,
        ]))
            ->assertOk()
            ->json('data');
    }
}
