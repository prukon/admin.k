<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\SchoolLead;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Порядок колонок таблицы «Заявки»: Статус → Договор → …; без колонки «Действия» и без DELETE заявки.
 */
final class SchoolLeadsTableColumnsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_contract_column_is_placed_after_status_before_phone(): void
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $tablePos = strpos($html, 'id="leads-table"');
        $this->assertNotFalse($tablePos);
        $theadSnippet = substr($html, $tablePos, 1500);

        $statusHeaderPos = strpos($theadSnippet, 'lead-status-col-header');
        $contractHeaderPos = strpos($theadSnippet, '<th>Договор</th>');
        $phoneHeaderPos = strpos($theadSnippet, '<th>Телефон родителя</th>');

        $this->assertNotFalse($statusHeaderPos);
        $this->assertNotFalse($contractHeaderPos);
        $this->assertNotFalse($phoneHeaderPos);
        $this->assertLessThan($contractHeaderPos, $statusHeaderPos);
        $this->assertLessThan($phoneHeaderPos, $contractHeaderPos);

        $statusTogglePos = strpos($html, 'data-column-key="status"');
        $contractTogglePos = strpos($html, 'data-column-key="contract"');
        $phoneTogglePos = strpos($html, 'data-column-key="phone"');

        $this->assertNotFalse($statusTogglePos);
        $this->assertNotFalse($contractTogglePos);
        $this->assertNotFalse($phoneTogglePos);
        $this->assertLessThan($contractTogglePos, $statusTogglePos);
        $this->assertLessThan($phoneTogglePos, $contractTogglePos);

        $statusColumnPos = strpos($html, "key: 'status'");
        $contractColumnPos = strpos($html, "key: 'contract'");
        $phoneColumnPos = strpos($html, "key: 'phone'");

        $this->assertNotFalse($statusColumnPos);
        $this->assertNotFalse($contractColumnPos);
        $this->assertNotFalse($phoneColumnPos);
        $this->assertLessThan($contractColumnPos, $statusColumnPos);
        $this->assertLessThan($phoneColumnPos, $contractColumnPos);
    }

    public function test_leads_table_has_no_actions_column_or_delete_controls(): void
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertStringNotContainsString("key: 'actions'", $html);
        $this->assertStringNotContainsString('delete-lead', $html);
        $this->assertStringNotContainsString('deleteLeadModal', $html);
        $this->assertStringNotContainsString('slColActions', $html);
        $this->assertStringNotContainsString('data-column-key="actions"', $html);

        $tablePos = strpos($html, 'id="leads-table"');
        $this->assertNotFalse($tablePos);
        $this->assertStringNotContainsString('<th>Действия</th>', substr($html, $tablePos, 1500));

        $this->assertStringContainsString("linkClass: 'edit-lead'", $html);
    }

    public function test_destroy_route_is_not_registered(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('admin.school-leads.destroy'));
    }

    public function test_delete_school_lead_url_returns_405_and_does_not_soft_delete(): void
    {
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Не удалять',
            'phone'                 => '+7 900 830-30-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->deleteJson('/admin/school-leads/' . $lead->id)
            ->assertStatus(405);

        $this->assertNull($lead->fresh()->deleted_at);
        $this->assertDatabaseHas('school_leads', [
            'id'         => $lead->id,
            'deleted_at' => null,
        ]);
    }
}
