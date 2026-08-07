<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Enums\SchoolLeadParentMatchConfirmation;
use App\Enums\SchoolLeadParentMatchReason;
use App\Models\ParentProfile;
use App\Models\SchoolLead;
use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P2] E2E smoke: страница → маркеры модалки матча → AJAX accept/reject → DataTables без F5.
 *
 * @see \Tests\Feature\Crm\SchoolLeads\SchoolLeadLinkedStatusWorkflowFeatureTest
 */
final class SchoolLeadParentMatchWorkflowFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        $this->withoutVite();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_parent_match_accept_workflow_visible_in_datatable_without_reload(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Workflow',
            'firstname'  => 'Parent',
            'email'      => 'workflow-match@example.test',
            'phone'      => '79995554433',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'name'                   => 'Workflow Match Parent',
            'parent_lastname'        => 'Заявочный',
            'parent_firstname'       => 'Родитель',
            'parent_email'           => 'workflow-lead@example.test',
            'parent_phone'           => '+7 999 111-00-00',
            'parent_id'              => $parent->id,
            'parent_match_reason'    => SchoolLeadParentMatchReason::Email,
            'parent_match_count'     => 1,
            'parent_match_confirmed' => null,
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
            'child_lastname'         => 'Ребёнок',
            'child_firstname'        => 'Тест',
        ]);

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('id="editLeadModal"', false)
            ->assertSee('modal-xl', false)
            ->assertSee('id="leadParentMatchBanner"', false)
            ->assertSee('id="leadParentMatchAcceptBtn"', false)
            ->assertSee('id="leadParentMatchRejectBtn"', false)
            ->assertSee('acceptLeadParentMatch', false)
            ->assertSee('rejectLeadParentMatch', false)
            ->assertSee('needsParentDecision', false)
            ->assertSee('Выберите родителя', false)
            ->assertSee('parent_match_confirmed', false);

        $dataBefore = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 25,
        ]));
        $dataBefore->assertOk();
        $this->assertNotSame('', trim((string) $dataBefore->getContent()));

        $rowBefore = collect($dataBefore->json('data') ?? [])
            ->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $lead->id);
        $this->assertIsArray($rowBefore);
        $this->assertTrue($rowBefore['parent_match_needs_decision']);
        $this->assertNull($rowBefore['parent_match_confirmed']);
        $this->assertSame($parent->id, $rowBefore['matched_parent']['id'] ?? null);

        $submit = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ])->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id'  => $lead->school_lead_status_id,
            'parent_id'              => $parent->id,
            'parent_match_confirmed' => SchoolLeadParentMatchConfirmation::Accepted->value,
            'parent_lastname'        => 'Заявочный',
            'parent_firstname'       => 'Родитель',
            'parent_email'           => 'workflow-lead@example.test',
            'parent_phone'           => '+7 999 111-00-00',
        ]);

        $submit->assertOk()
            ->assertJsonPath('message', 'Изменения сохранены.')
            ->assertJsonPath('parent_match_confirmed', 'accepted')
            ->assertJsonPath('parent_match_needs_decision', false)
            ->assertJsonPath('parent_id', $parent->id);
        $this->assertNotSame('', trim((string) $submit->getContent()));

        $dataAfter = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 25,
        ]));
        $dataAfter->assertOk();

        $rowAfter = collect($dataAfter->json('data') ?? [])
            ->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $lead->id);
        $this->assertIsArray($rowAfter);
        $this->assertSame('accepted', $rowAfter['parent_match_confirmed']);
        $this->assertFalse($rowAfter['parent_match_needs_decision']);
        $this->assertSame($parent->id, (int) ($rowAfter['parent_id'] ?? 0));
        $this->assertSame('Заявочный', $rowAfter['parent_lastname']);

        $pageAfter = $this->get(route('admin.school-leads'));
        $pageAfter->assertOk();
        $this->assertNotSame('', trim((string) $pageAfter->getContent()));
        $pageAfter->assertSee('id="editLeadModal"', false)
            ->assertSee('id="leadParentMatchBanner"', false);
    }

    public function test_parent_match_reject_workflow_visible_in_datatable_without_reload(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Петров',
            'firstname'  => 'Иван',
        ]);
        ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Петров',
            'firstname'  => 'Иван',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_lastname'        => 'Петров',
            'parent_firstname'       => 'Иван',
            'parent_email'           => 'reject-workflow@example.test',
            'parent_id'              => $parent->id,
            'parent_match_reason'    => SchoolLeadParentMatchReason::Name,
            'parent_match_count'     => 2,
            'parent_match_confirmed' => null,
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk()
            ->assertSee('rejectLeadParentMatch', false)
            ->assertSee('parent_match_banner', false);

        $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ])->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id'  => $lead->school_lead_status_id,
            'parent_id'              => null,
            'parent_match_confirmed' => SchoolLeadParentMatchConfirmation::Rejected->value,
            'parent_lastname'        => 'Петров',
            'parent_firstname'       => 'Иван',
            'parent_email'           => 'reject-workflow@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('parent_match_confirmed', 'rejected')
            ->assertJsonPath('parent_id', null);

        $row = collect($this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 25,
        ]))->json('data') ?? [])
            ->first(fn ($item) => (int) ($item['id'] ?? 0) === (int) $lead->id);

        $this->assertIsArray($row);
        $this->assertSame('rejected', $row['parent_match_confirmed']);
        $this->assertNull($row['parent_id']);
        $this->assertFalse($row['parent_match_needs_decision']);
        $this->assertSame('name', $row['parent_match_reason']);
        $this->assertSame(2, $row['parent_match_count']);
        $this->assertStringContainsString('фамилии и имени', (string) ($row['parent_match_banner'] ?? ''));
        $this->assertStringContainsString('Найдено 2 совпадения', (string) ($row['parent_match_banner'] ?? ''));
    }

    public function test_parent_match_ajax_validation_keeps_page_usable_not_white_screen(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_id'              => $parent->id,
            'parent_match_reason'    => SchoolLeadParentMatchReason::Email,
            'parent_match_count'     => 1,
            'parent_match_confirmed' => null,
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ])->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id'  => $lead->school_lead_status_id,
            'parent_id'              => $parent->id,
            'parent_match_confirmed' => 'broken',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_match_confirmed']);

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('id="editLeadModal"', false)
            ->assertSee('id="leadParentMatchAcceptBtn"', false);
    }
}
