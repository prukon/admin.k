<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Enums\SchoolLeadParentMatchConfirmation;
use App\Enums\SchoolLeadParentMatchReason;
use App\Models\ParentProfile;
use App\Models\SchoolLead;
use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

final class SchoolLeadParentMatchUiContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_create_client_payload_reads_parent_fields_from_form_not_lead_snapshot(): void
    {
        $content = (string) file_get_contents(resource_path('views/admin/school-leads/tabs/leads.blade.php'));
        $start = strpos($content, 'function collectCreateClientPayload()');
        $end = strpos($content, 'function saveLeadAjax()');

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);

        $fn = substr($content, $start, $end - $start);

        $this->assertStringContainsString("$('#lead-parent-id').val()", $fn);
        $this->assertStringContainsString("$('#lead-parent-lastname').val()", $fn);
        $this->assertStringContainsString("$('#lead-parent-firstname').val()", $fn);
        $this->assertStringContainsString("$('#lead-parent-middlename').val()", $fn);
        $this->assertStringContainsString("$('#lead-parent-phone').val()", $fn);
        $this->assertStringContainsString("$('#lead-parent-email').val()", $fn);
        $this->assertStringNotContainsString('payload.parent_email', $fn);
        $this->assertStringNotContainsString('payload.parent_phone', $fn);
        $this->assertStringNotContainsString('payload.parent_lastname', $fn);
        $this->assertStringNotContainsString('snapshot.email', $fn);
        $this->assertStringNotContainsString('useSnapshotParentFields', $fn);
    }

    public function test_widget_doc_states_create_client_uses_form_parent_fields(): void
    {
        $html = (string) file_get_contents(base_path('docs/documentation/school-leads-widget.html'));

        $this->assertStringContainsString('collectCreateClientPayload', $html);
        $this->assertStringContainsString('#lead-parent-email', $html);
        $this->assertStringContainsString('не из снимка', $html);
        $this->assertStringContainsString('При сохранении лида с матчем фронт шлёт', $html);
        $this->assertStringContainsString("toggleClass('modal-xl')", $html);
        $this->assertStringContainsString('school-lead-edit-modal-width-index', $html);

        $index = (string) file_get_contents(base_path('docs/documentation/index.html'));
        $this->assertGreaterThanOrEqual(2, substr_count($index, 'school-lead-edit-modal-width-index'));
    }

    public function test_edit_modal_contains_parent_match_ui(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee("toggleClass('modal-xl'", false)
            ->assertSee('id="leadParentMatchBanner"', false)
            ->assertSee('id="leadParentMatchAcceptBtn"', false)
            ->assertSee('id="leadParentMatchRejectBtn"', false)
            ->assertSee('Сопоставлен верно', false)
            ->assertSee('Сопоставление неверно', false)
            ->assertSee('Данные из заявки', false)
            ->assertSee('id="leadParentSnapshotCol"', false)
            ->assertSee('leadParentMatchUi', false)
            ->assertSee('highlightLeadParentSnapshotMatches', false)
            ->assertSee('is-match-hit', false)
            ->assertSee('совпало', false)
            ->assertSee('Выберите родителя', false);

        $modal = (string) file_get_contents(resource_path('views/admin/school-leads/partials/edit-lead-modal.blade.php'));
        $this->assertStringContainsString('class="modal-dialog modal-dialog-scrollable"', $modal);
        $this->assertStringNotContainsString('modal-xl', $modal);
        $this->assertStringNotContainsString('modal-lg', $modal);
        $this->assertStringNotContainsString('modal-fullscreen', $modal);
    }

    public function test_datatable_includes_parent_match_payload(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Справочников',
            'firstname' => 'Алексей',
            'email' => 'dir@example.com',
            'phone' => '79991234567',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_lastname' => 'Иванов',
            'parent_firstname' => 'Иван',
            'parent_email' => 'lead@example.com',
            'parent_phone' => '+7 999 111-22-33',
            'parent_id' => $parent->id,
            'parent_match_reason' => SchoolLeadParentMatchReason::Email,
            'parent_match_count' => 1,
            'parent_match_confirmed' => null,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]));
        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $lead->id);
        $this->assertNotNull($row);
        $this->assertSame($parent->id, $row['parent_id']);
        $this->assertSame('email', $row['parent_match_reason']);
        $this->assertSame(1, $row['parent_match_count']);
        $this->assertNull($row['parent_match_confirmed']);
        $this->assertTrue($row['parent_match_needs_decision']);
        $this->assertStringContainsString('почте', (string) $row['parent_match_banner']);
        $this->assertSame($parent->id, $row['matched_parent']['id']);
        $this->assertSame('Справочников', $row['matched_parent']['lastname']);
        $this->assertSame('Иванов', $row['parent_lastname']);
    }

    public function test_update_persists_parent_match_confirmation_without_overwriting_snapshot_when_sent(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Справочников',
            'firstname' => 'Алексей',
            'email' => 'dir@example.com',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_lastname' => 'Иванов',
            'parent_firstname' => 'Иван',
            'parent_middlename' => 'Иванович',
            'parent_email' => 'lead@example.com',
            'parent_phone' => '+7 999 111-22-33',
            'parent_id' => $parent->id,
            'parent_match_reason' => SchoolLeadParentMatchReason::Email,
            'parent_match_count' => 1,
            'parent_match_confirmed' => null,
        ]);

        $response = $this->putJson(route('admin.school-leads.update', $lead), [
            'school_lead_status_id' => $lead->school_lead_status_id,
            'comment' => $lead->comment,
            'parent_id' => $parent->id,
            'parent_match_confirmed' => SchoolLeadParentMatchConfirmation::Accepted->value,
            'parent_lastname' => 'Иванов',
            'parent_firstname' => 'Иван',
            'parent_middlename' => 'Иванович',
            'parent_email' => 'lead@example.com',
            'parent_phone' => '+7 999 111-22-33',
        ]);

        $response->assertOk()
            ->assertJsonPath('parent_id', $parent->id)
            ->assertJsonPath('parent_match_confirmed', 'accepted')
            ->assertJsonPath('parent_match_needs_decision', false)
            ->assertJsonPath('parent_lastname', 'Иванов');

        $lead->refresh();
        $this->assertSame(SchoolLeadParentMatchConfirmation::Accepted, $lead->parent_match_confirmed);
        $this->assertSame($parent->id, (int) $lead->parent_id);
        $this->assertSame('Иванов', $lead->parent_lastname);
        $this->assertSame('lead@example.com', $lead->parent_email);
    }

    public function test_update_reject_clears_parent_id(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => 'dir@example.com',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_lastname' => 'Иванов',
            'parent_firstname' => 'Иван',
            'parent_email' => 'lead@example.com',
            'parent_id' => $parent->id,
            'parent_match_reason' => SchoolLeadParentMatchReason::Name,
            'parent_match_count' => 2,
            'parent_match_confirmed' => null,
        ]);

        $response = $this->putJson(route('admin.school-leads.update', $lead), [
            'school_lead_status_id' => $lead->school_lead_status_id,
            'parent_id' => null,
            'parent_match_confirmed' => SchoolLeadParentMatchConfirmation::Rejected->value,
            'parent_lastname' => 'Иванов',
            'parent_firstname' => 'Иван',
            'parent_email' => 'lead@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('parent_id', null)
            ->assertJsonPath('parent_match_confirmed', 'rejected');

        $lead->refresh();
        $this->assertNull($lead->parent_id);
        $this->assertSame(SchoolLeadParentMatchConfirmation::Rejected, $lead->parent_match_confirmed);
        $this->assertSame(SchoolLeadParentMatchReason::Name, $lead->parent_match_reason);
        $this->assertSame(2, (int) $lead->parent_match_count);
    }

    public function test_datatable_confirmed_accepted_does_not_need_decision(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => 'confirmed@example.com',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_id' => $parent->id,
            'parent_match_reason' => SchoolLeadParentMatchReason::Email,
            'parent_match_count' => 1,
            'parent_match_confirmed' => SchoolLeadParentMatchConfirmation::Accepted,
            'parent_lastname' => 'Иванов',
            'parent_firstname' => 'Иван',
            'parent_email' => 'confirmed-lead@example.com',
        ]);

        $row = collect($this->getJson(route('admin.school-leads.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->json('data'))->firstWhere('id', $lead->id);

        $this->assertIsArray($row);
        $this->assertSame('accepted', $row['parent_match_confirmed']);
        $this->assertFalse($row['parent_match_needs_decision']);
        $this->assertSame($parent->id, $row['matched_parent']['id']);
    }

    public function test_datatable_name_match_banner_includes_count_when_multiple(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Петров',
            'firstname' => 'Иван',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_id' => $parent->id,
            'parent_match_reason' => SchoolLeadParentMatchReason::Name,
            'parent_match_count' => 3,
            'parent_match_confirmed' => null,
            'parent_lastname' => 'Петров',
            'parent_firstname' => 'Иван',
        ]);

        $row = collect($this->getJson(route('admin.school-leads.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->json('data'))->firstWhere('id', $lead->id);

        $this->assertIsArray($row);
        $this->assertTrue($row['parent_match_needs_decision']);
        $this->assertStringContainsString('фамилии и имени', (string) $row['parent_match_banner']);
        $this->assertStringContainsString('Найдено 3 совпадения', (string) $row['parent_match_banner']);
    }

    public function test_soft_deleted_matched_parent_disables_needs_decision_in_payload(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => 'deleted-match@example.com',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_id' => $parent->id,
            'parent_match_reason' => SchoolLeadParentMatchReason::Email,
            'parent_match_count' => 1,
            'parent_match_confirmed' => null,
        ]);

        $parent->delete();

        $row = collect($this->getJson(route('admin.school-leads.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->json('data'))->firstWhere('id', $lead->id);

        $this->assertIsArray($row);
        $this->assertSame($parent->id, $row['parent_id']);
        $this->assertNull($row['matched_parent']);
        $this->assertFalse($row['parent_match_needs_decision']);
        $this->assertStringContainsString('почте', (string) $row['parent_match_banner']);
    }
}
