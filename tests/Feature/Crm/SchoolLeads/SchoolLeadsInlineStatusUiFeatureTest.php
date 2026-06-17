<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\SchoolLead;
use App\Models\SchoolLeadStatus;
use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UI и API inline-статусов на вкладке «Заявки»: цветные бейджи, кастомное меню, порядок колонок.
 */
final class SchoolLeadsInlineStatusUiFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_leads_page_renders_inline_status_picker_markup(): void
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertStringContainsString('renderLeadStatusInlineSelect', $html);
        $this->assertStringContainsString('lead-status-inline-picker', $html);
        $this->assertStringContainsString('lead-status-inline-menu', $html);
        $this->assertStringContainsString('lead-status-inline-trigger', $html);
        $this->assertStringContainsString('lead-status-inline-option', $html);
        $this->assertStringContainsString('lead-status-inline-caret', $html);
        $this->assertStringContainsString('leadStatusEditHint', $html);
        $this->assertStringContainsString('Нажмите, чтобы изменить статус', $html);
        $this->assertStringContainsString('lead-status-col-header', $html);
        $this->assertStringContainsString('buildLeadStatusMenuHtml', $html);
        $this->assertStringContainsString('getStatusBadgeStyle', $html);
        $this->assertStringContainsString('saveLeadStatusInline', $html);
        $this->assertStringNotContainsString(
            '+ \'<select class="form-select form-select-sm d-none lead-status-select\'',
            $html
        );
    }

    public function test_status_column_is_placed_after_parent_name_in_table_and_columns_menu(): void
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $parentHeaderPos = strpos($html, '<th>ФИО родителя</th>');
        $statusHeaderPos = strpos($html, 'lead-status-col-header');
        $phoneHeaderPos = strpos($html, '<th>Телефон родителя</th>');

        $this->assertNotFalse($parentHeaderPos);
        $this->assertNotFalse($statusHeaderPos);
        $this->assertNotFalse($phoneHeaderPos);
        $this->assertLessThan($statusHeaderPos, $parentHeaderPos);
        $this->assertLessThan($phoneHeaderPos, $statusHeaderPos);

        $nameTogglePos = strpos($html, 'data-column-key="name"');
        $statusTogglePos = strpos($html, 'data-column-key="status"');
        $phoneTogglePos = strpos($html, 'data-column-key="phone"');

        $this->assertNotFalse($nameTogglePos);
        $this->assertNotFalse($statusTogglePos);
        $this->assertNotFalse($phoneTogglePos);
        $this->assertLessThan($statusTogglePos, $nameTogglePos);
        $this->assertLessThan($phoneTogglePos, $statusTogglePos);

        $statusColumnPos = strpos($html, "key: 'status'");
        $phoneColumnPos = strpos($html, "key: 'phone'");
        $this->assertNotFalse($statusColumnPos);
        $this->assertNotFalse($phoneColumnPos);
        $this->assertLessThan($phoneColumnPos, $statusColumnPos);
    }

    public function test_status_settings_modal_uses_otobrazhat_column_header(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('>Отображать</th>', false)
            ->assertDontSee('>В фильтре</th>', false);
    }

    public function test_system_new_status_uses_configured_color(): void
    {
        $system = SchoolLeadStatus::systemNew();

        $this->assertSame(SchoolLeadStatus::DEFAULT_NEW_COLOR, '#a0fe62');
        $this->assertSame('#a0fe62', $system->color);
        $this->assertStringContainsString('#a0fe62', $system->badgeStyle());
    }

    public function test_statuses_index_returns_badge_style_for_system_and_custom_statuses(): void
    {
        $custom = $this->createPartnerSchoolLeadStatus([
            'name'  => 'UI цветной',
            'color' => '#ff5733',
        ]);

        $response = $this->getJson(route('admin.school-leads.statuses.index'))->assertOk();

        $system = collect($response->json('statuses'))->firstWhere('is_system', true);
        $customRow = collect($response->json('statuses'))->firstWhere('id', $custom->id);

        $this->assertNotNull($system);
        $this->assertSame('#a0fe62', $system['color']);
        $this->assertStringContainsString('#a0fe62', (string) $system['badge_style']);

        $this->assertNotNull($customRow);
        $this->assertSame('#ff5733', $customRow['color']);
        $this->assertStringContainsString('#ff5733', (string) $customRow['badge_style']);
        $this->assertNotEmpty($customRow['text_color']);
    }

    public function test_datatable_returns_colored_badge_fields_for_system_new_status(): void
    {
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Новый лид UI',
            'phone'                 => '+7 900 100-00-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $row = collect($response->json('data'))->firstWhere('name', 'Новый лид UI');
        $this->assertNotNull($row);
        $this->assertSame('#a0fe62', $row['status_color']);
        $this->assertStringContainsString('#a0fe62', (string) $row['status_badge_style']);
        $this->assertNotEmpty($row['status_text_color']);
    }

    public function test_datatable_returns_colored_badge_fields_for_custom_status(): void
    {
        $status = $this->createPartnerSchoolLeadStatus([
            'name'  => 'UI кастом',
            'color' => '#3366ff',
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Кастомный лид UI',
            'phone'                 => '+7 900 100-00-02',
            'school_lead_status_id' => $status->id,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $row = collect($response->json('data'))->firstWhere('name', 'Кастомный лид UI');
        $this->assertNotNull($row);
        $this->assertSame('#3366ff', $row['status_color']);
        $this->assertStringContainsString('#3366ff', (string) $row['status_badge_style']);
    }

    public function test_inline_status_update_returns_colored_badge_payload(): void
    {
        $status = $this->createPartnerSchoolLeadStatus([
            'name'  => 'UI inline',
            'color' => '#e83e8c',
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Inline update UI',
            'phone'                 => '+7 900 100-00-03',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => $status->id,
        ])->assertOk();

        $response
            ->assertJsonPath('school_lead_status_id', $status->id)
            ->assertJsonPath('status_label', 'UI inline')
            ->assertJsonPath('status_color', '#e83e8c');
        $this->assertStringContainsString(
            '#e83e8c',
            (string) $response->json('status_badge_style')
        );

        $this->assertSame($status->id, (int) $lead->fresh()->school_lead_status_id);
    }

    public function test_inline_status_update_to_system_new_returns_green_badge_payload(): void
    {
        $custom = $this->createPartnerSchoolLeadStatus(['name' => 'Временный']);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Вернуть в новый',
            'phone'                 => '+7 900 100-00-04',
            'school_lead_status_id' => $custom->id,
        ]);

        $response = $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ])->assertOk();

        $response
            ->assertJsonPath('status_label', 'Новый')
            ->assertJsonPath('status_color', '#a0fe62');
        $this->assertStringContainsString(
            '#a0fe62',
            (string) $response->json('status_badge_style')
        );
    }
}
