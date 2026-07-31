<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Смена статуса у лида с привязанным клиентом + UI-маркеры модалки/tooltip «Создать клиента».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SchoolLeadLinkedStatusFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_ajax_status_update_on_linked_lead_returns_json_badge_payload(): void
    {
        $lead = $this->makeLinkedLead();
        $status = $this->createPartnerSchoolLeadStatus([
            'name'  => 'Клиент создан',
            'color' => '#198754',
        ]);

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ])->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => $status->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Изменения сохранены.')
            ->assertJsonPath('school_lead_status_id', $status->id)
            ->assertJsonPath('status_label', 'Клиент создан')
            ->assertJsonStructure([
                'message',
                'school_lead_status_id',
                'status_label',
                'status_color',
                'status_text_color',
                'status_badge_style',
            ]);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertSame($status->id, (int) $lead->fresh()->school_lead_status_id);
    }

    public function test_ajax_non_status_update_on_linked_lead_returns_422_with_school_lead_error(): void
    {
        $lead = $this->makeLinkedLead(['comment' => 'Было']);

        $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ])->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'comment' => 'Нельзя менять',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['school_lead']);

        $this->assertSame('Было', $lead->fresh()->comment);
    }

    public function test_ajax_status_plus_other_fields_on_linked_lead_returns_422(): void
    {
        $lead = $this->makeLinkedLead();
        $status = $this->createPartnerSchoolLeadStatus(['name' => 'Смешанный']);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => $status->id,
            'comment'               => 'лишнее поле',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['school_lead']);

        $this->assertSame(
            $this->schoolLeadSystemStatusId(),
            (int) $lead->fresh()->school_lead_status_id
        );
    }

    public function test_page_includes_create_client_tooltip_and_linked_status_ajax_helpers(): void
    {
        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="createClientBtnWrap"', false)
            ->assertSee('Заполните фамилию ученика, имя ученика и email родителя', false)
            ->assertSee('buildCreateClientMissingHint', false)
            ->assertSee('updateCreateClientBtnTooltip', false)
            ->assertSee('email родителя', false)
            ->assertSee('У лида с клиентом нет «Сохранить»', false)
            ->assertSee('saveLeadStatusInline(modalLeadId, newStatusId', false)
            ->assertSee('saveLeadStatusInline', false)
            ->getContent();

        $this->assertStringContainsString("type: 'PUT'", $html);
        $this->assertStringContainsString('school_lead_status_id', $html);
        $this->assertStringNotContainsString(
            '.lead-modal-status-picker .lead-status-inline-trigger {' . "\n"
            . '            pointer-events: none;',
            $html
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLinkedLead(array $overrides = []): SchoolLead
    {
        $linkedUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => (int) Role::query()->where('name', 'user')->value('id'),
        ]);

        return SchoolLead::create(array_merge([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Лид с клиентом',
            'phone'                 => '+7 900 555-55-55',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $linkedUser->id,
            'child_lastname'        => 'Иванов',
            'child_firstname'       => 'Пётр',
        ], $overrides));
    }
}
