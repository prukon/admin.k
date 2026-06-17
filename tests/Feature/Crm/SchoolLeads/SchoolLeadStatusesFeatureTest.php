<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\SchoolLead;
use App\Models\SchoolLeadStatus;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Функциональные тесты настраиваемых статусов заявок школы.
 */
final class SchoolLeadStatusesFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_statuses_index_includes_system_new_status(): void
    {
        $response = $this->getJson(route('admin.school-leads.statuses.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'statuses' => [
                    '*' => [
                        'id',
                        'name',
                        'color',
                        'text_color',
                        'badge_style',
                        'sort_order',
                        'is_default_in_filter',
                        'is_system',
                        'leads_count',
                    ],
                ],
            ]);

        $statuses = collect($response->json('statuses'));
        $system = $statuses->firstWhere('is_system', true);

        $this->assertNotNull($system);
        $this->assertSame('Новый', $system['name']);
        $this->assertSame('#a0fe62', $system['color']);
        $this->assertTrue($system['is_default_in_filter']);
        $this->assertSame(SchoolLeadStatus::CODE_NEW, SchoolLeadStatus::systemNew()->code);
    }

    public function test_store_update_and_destroy_custom_status(): void
    {
        $create = $this->postJson(route('admin.school-leads.statuses.store'), [
            'name'                 => 'Перезвонить',
            'color'                => '#0d6efd',
            'sort_order'           => 20,
            'is_default_in_filter' => true,
        ]);

        $create->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status.name', 'Перезвонить');

        $statusId = (int) $create->json('status.id');

        $this->putJson(route('admin.school-leads.statuses.update', ['schoolLeadStatus' => $statusId]), [
            'name'                 => 'В работе',
            'color'                => '#ffc107',
            'sort_order'           => 25,
            'is_default_in_filter' => false,
        ])->assertOk();

        $this->assertDatabaseHas('school_lead_statuses', [
            'id'                   => $statusId,
            'partner_id'           => $this->partner->id,
            'name'                 => 'В работе',
            'is_default_in_filter' => 0,
        ]);

        $this->deleteJson(route('admin.school-leads.statuses.destroy', ['schoolLeadStatus' => $statusId]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('school_lead_statuses', ['id' => $statusId]);
    }

    public function test_store_validates_required_name(): void
    {
        $this->postJson(route('admin.school-leads.statuses.store'), [
            'color' => '#0d6efd',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_color_format(): void
    {
        $this->postJson(route('admin.school-leads.statuses.store'), [
            'name'  => 'Некорректный цвет',
            'color' => 'red',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    public function test_store_assigns_auto_sort_order_when_omitted(): void
    {
        $first = $this->postJson(route('admin.school-leads.statuses.store'), [
            'name'  => 'Первый',
            'color' => '#0d6efd',
        ])->assertOk();

        $second = $this->postJson(route('admin.school-leads.statuses.store'), [
            'name'  => 'Второй',
            'color' => '#198754',
        ])->assertOk();

        $firstOrder = (int) $first->json('status.sort_order');
        $secondOrder = (int) $second->json('status.sort_order');

        $this->assertGreaterThan($firstOrder, $secondOrder);
    }

    public function test_index_includes_leads_count_for_custom_status(): void
    {
        $status = $this->createPartnerSchoolLeadStatus(['name' => 'С заявками']);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Лид 1',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $status->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Лид 2',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $status->id,
        ]);

        $response = $this->getJson(route('admin.school-leads.statuses.index'))->assertOk();

        $item = collect($response->json('statuses'))->firstWhere('id', $status->id);
        $this->assertNotNull($item);
        $this->assertSame(2, $item['leads_count']);
    }

    public function test_cannot_delete_status_with_leads(): void
    {
        $status = $this->createPartnerSchoolLeadStatus(['name' => 'Занят']);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Лид',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $status->id,
        ]);

        $this->deleteJson(route('admin.school-leads.statuses.destroy', ['schoolLeadStatus' => $status->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_system_status_cannot_be_updated_or_deleted(): void
    {
        $systemId = SchoolLeadStatus::systemNewId();

        $this->putJson(route('admin.school-leads.statuses.update', ['schoolLeadStatus' => $systemId]), [
            'name'  => 'Хак',
            'color' => '#000000',
        ])->assertForbidden();

        $this->deleteJson(route('admin.school-leads.statuses.destroy', ['schoolLeadStatus' => $systemId]))
            ->assertForbidden();
    }

    public function test_cannot_update_foreign_partner_status(): void
    {
        $foreignStatus = SchoolLeadStatus::query()->create([
            'partner_id'           => $this->foreignPartner->id,
            'name'                 => 'Чужой статус',
            'color'                => '#0d6efd',
            'sort_order'           => 10,
            'is_default_in_filter' => false,
            'is_system'            => false,
        ]);

        $this->putJson(route('admin.school-leads.statuses.update', ['schoolLeadStatus' => $foreignStatus->id]), [
            'name'  => 'Взлом',
            'color' => '#ff0000',
        ])->assertNotFound();

        $this->deleteJson(route('admin.school-leads.statuses.destroy', ['schoolLeadStatus' => $foreignStatus->id]))
            ->assertNotFound();
    }

    public function test_index_passes_default_status_filter_ids(): void
    {
        $customDefault = $this->createPartnerSchoolLeadStatus([
            'name'                 => 'В фильтре',
            'is_default_in_filter' => true,
        ]);
        $this->createPartnerSchoolLeadStatus([
            'name'                 => 'Не в фильтре',
            'is_default_in_filter' => false,
        ]);

        $systemId = $this->schoolLeadSystemStatusId();

        $response = $this->get(route('admin.school-leads'))->assertOk();

        $defaultIds = $response->viewData('defaultStatusFilterIds');
        $this->assertContains((string) $systemId, $defaultIds);
        $this->assertContains((string) $customDefault->id, $defaultIds);
        $this->assertCount(2, $defaultIds);
    }

    public function test_index_renders_status_settings_ui(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="schoolLeadStatusesModal"', false)
            ->assertSee('id="schoolLeadStatusFormModal"', false)
            ->assertSee('id="schoolLeadStatusCreateBtn"', false)
            ->assertSee('schoolLeadStatusRoutes', false)
            ->assertSee('defaultStatusFilterIds', false)
            ->assertSee('Настройки статусов заявок', false);
    }

    public function test_datatable_returns_status_payload_fields(): void
    {
        $status = $this->createPartnerSchoolLeadStatus([
            'name'  => 'Цветной',
            'color' => '#ff5733',
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Статусный лид',
            'phone'                 => '+7 900 333-33-33',
            'school_lead_status_id' => $status->id,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $row = collect($response->json('data'))->firstWhere('name', 'Статусный лид');
        $this->assertNotNull($row);
        $this->assertSame($status->id, (int) $row['school_lead_status_id']);
        $this->assertSame('Цветной', $row['status_label']);
        $this->assertSame('#ff5733', $row['status_color']);
        $this->assertNotEmpty($row['status_badge_style']);
        $this->assertNotEmpty($row['status_text_color']);
    }

    public function test_stats_new_counts_only_system_status(): void
    {
        $customStatus = $this->createPartnerSchoolLeadStatus(['name' => 'Не новый']);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Новый 1',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Новый 2',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Другой статус',
            'phone'                 => '+7 900 333-33-33',
            'school_lead_status_id' => $customStatus->id,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $this->assertSame([
            'total' => 3,
            'new'   => 2,
        ], $response->json('stats'));
    }

    public function test_datatable_filters_by_status_ids(): void
    {
        $processingId = $this->schoolLeadProcessingStatusId();

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Новый лид',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'В обработке',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $processingId,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'       => 1,
            'start'      => 0,
            'length'     => 10,
            'status_ids' => [$processingId],
        ]))->assertOk();

        $this->assertSame(2, $response->json('recordsTotal'));
        $this->assertSame(1, $response->json('recordsFiltered'));
        $this->assertSame('В обработке', $response->json('data.0.name'));
    }

    public function test_update_lead_can_clear_status(): void
    {
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Иван',
            'phone'                 => '+7 999 123-45-67',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => '',
        ])
            ->assertOk()
            ->assertJsonPath('school_lead_status_id', null)
            ->assertJsonPath('status_label', null);

        $this->assertNull($lead->fresh()->school_lead_status_id);
    }

    public function test_update_lead_rejects_invalid_status_id(): void
    {
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Иван',
            'phone'                 => '+7 999 123-45-67',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => 999999,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['school_lead_status_id']);

        $this->assertSame(
            $this->schoolLeadSystemStatusId(),
            (int) $lead->fresh()->school_lead_status_id
        );
    }

    public function test_widget_submit_assigns_system_new_status(): void
    {
        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
        ]);

        $this->postJson(route('widget.school-lead.submit', ['widgetKey' => $widget->widget_key]), [
            'name'             => 'Виджет',
            'phone'            => '+7 999 000-00-00',
            'consent_accepted' => '1',
            'recaptcha_token'  => 'fake-token',
        ])->assertOk();

        $this->assertDatabaseHas('school_leads', [
            'partner_id'            => $widget->partner_id,
            'name'                  => 'Виджет',
            'school_lead_status_id' => SchoolLeadStatus::systemNewId(),
        ]);
    }
}
