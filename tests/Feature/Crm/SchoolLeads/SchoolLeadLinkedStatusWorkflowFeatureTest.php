<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P2] E2E smoke: страница лидов → модалка (маркеры) → AJAX смена статуса у лида с клиентом →
 * статус виден в DataTables без F5. Реальный браузер не используется.
 *
 * @see \Tests\Feature\Crm\Schedule\ScheduleJournalWorkflowFeatureTest
 * @see \Tests\Feature\Crm\LessonPackages\LessonPackageAssignmentEndsAtWorkflowFeatureTest
 */
final class SchoolLeadLinkedStatusWorkflowFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        $this->withoutVite();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_linked_lead_status_workflow_page_modal_submit_visible_in_datatable_without_reload(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => (int) Role::query()->where('name', 'user')->value('id'),
            'is_enabled' => 1,
            'name'       => 'Workflow',
            'lastname'   => 'Клиент',
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Workflow Parent',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $student->id,
            'child_lastname'        => 'Клиент',
            'child_firstname'       => 'Workflow',
            'parent_email'          => 'workflow-parent@example.test',
        ]);

        $targetStatus = $this->createPartnerSchoolLeadStatus([
            'name'  => 'E2E linked status',
            'color' => '#0dcaf0',
        ]);

        $page = $this->get(route('admin.school-leads'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('id="editLeadModal"', false)
            ->assertSee('id="leadModalStatusPicker"', false)
            ->assertSee('id="createClientBtn"', false)
            ->assertSee('saveLeadStatusInline', false)
            ->assertSee('У лида с клиентом нет «Сохранить»', false)
            ->assertSee('buildCreateClientMissingHint', false);

        $dataBefore = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 25,
        ]));
        $dataBefore->assertOk();
        $this->assertNotSame('', trim((string) $dataBefore->getContent()));

        $rowBefore = collect($dataBefore->json('data') ?? [])
            ->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $lead->id);
        $this->assertIsArray($rowBefore, 'Лид должен быть в DataTables до смены статуса');
        $this->assertSame($student->id, (int) ($rowBefore['user_id'] ?? 0));
        $this->assertStringContainsString('Workflow Parent', (string) ($rowBefore['name'] ?? ''));

        $submit = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ])->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => $targetStatus->id,
        ]);

        $submit->assertOk()
            ->assertJsonPath('message', 'Изменения сохранены.')
            ->assertJsonPath('school_lead_status_id', $targetStatus->id)
            ->assertJsonPath('status_label', 'E2E linked status');
        $this->assertNotSame('', trim((string) $submit->getContent()));

        // Как после dtApi.reload({ keepPage: true }) в leads.blade.php — без F5.
        $dataAfter = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 25,
        ]));
        $dataAfter->assertOk();
        $this->assertNotSame('', trim((string) $dataAfter->getContent()));

        $rowAfter = collect($dataAfter->json('data') ?? [])
            ->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $lead->id);
        $this->assertIsArray($rowAfter, 'Строка лида должна быть в DataTables после update без F5');
        $this->assertSame($targetStatus->id, (int) ($rowAfter['school_lead_status_id'] ?? 0));
        $this->assertSame('E2E linked status', $rowAfter['status_label'] ?? null);

        $pageAfter = $this->get(route('admin.school-leads'));
        $pageAfter->assertOk();
        $this->assertNotSame('', trim((string) $pageAfter->getContent()));
        $pageAfter->assertSee('id="editLeadModal"', false)
            ->assertSee('id="leadModalStatusPicker"', false);
    }

    public function test_linked_lead_status_ajax_validation_keeps_page_usable_not_white_screen(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => (int) Role::query()->where('name', 'user')->value('id'),
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Validation Parent',
            'phone'                 => '+7 900 333-33-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $student->id,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="editLeadModal"', false);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'comment' => 'forbidden on linked',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['school_lead']);

        $pageAfter = $this->get(route('admin.school-leads'));
        $pageAfter->assertOk();
        $this->assertNotSame('', trim((string) $pageAfter->getContent()));
        $pageAfter->assertSee('id="editLeadModal"', false);

        $dataAfter = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 25,
        ]))->assertOk();

        $row = collect($dataAfter->json('data') ?? [])
            ->first(fn ($item) => (int) ($item['id'] ?? 0) === (int) $lead->id);
        $this->assertIsArray($row);
        $this->assertStringContainsString('Validation Parent', (string) ($row['name'] ?? ''));
    }
}
