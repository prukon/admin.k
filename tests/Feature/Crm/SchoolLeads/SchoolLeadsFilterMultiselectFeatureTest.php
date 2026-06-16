<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\SchoolLead;
use App\Models\SchoolLeadStatus;
use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фильтр статусов на странице заявок: KidsCrmFilterMultiselect (без Select2) и API status_ids[].
 */
final class SchoolLeadsFilterMultiselectFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_index_renders_filter_multiselect_component(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="sl-filter-status"', false)
            ->assertSee('js-filter-multiselect-select', false)
            ->assertSee('KidsCrmFilterMultiselectSelect2', false)
            ->assertSee('kids-crm-filter-ms__trigger', false)
            ->assertSee('kids-crm-filter-ms__panel', false)
            ->assertSee('kids-crm-filter-ms__option-check', false)
            ->assertSee('data-placeholder="Выберите статусы"', false)
            ->assertSee('KidsCrmFilterMultiselectSelect2.init', false)
            ->assertSee('KidsCrmFilterMultiselectSelect2.rebuild', false)
            ->assertSee('KidsCrmFilterMultiselectSelect2.setValues', false);
    }

    public function test_index_status_filter_lists_only_partner_statuses(): void
    {
        $custom = $this->createPartnerSchoolLeadStatus(['name' => 'ПартнёрскийФильтр']);
        SchoolLeadStatus::query()->create([
            'partner_id'           => $this->foreignPartner->id,
            'name'                 => 'ЧужойСтатусФильтр',
            'color'                => '#0d6efd',
            'sort_order'           => 50,
            'is_default_in_filter' => false,
            'is_system'            => false,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('>Новый</option>', false)
            ->assertSee('>ПартнёрскийФильтр</option>', false)
            ->assertSee('value="' . $custom->id . '"', false)
            ->assertDontSee('>ЧужойСтатусФильтр</option>', false);
    }

    public function test_index_preselects_default_statuses_in_filter_markup(): void
    {
        $customDefault = $this->createPartnerSchoolLeadStatus([
            'name'                 => 'ДефолтФильтр',
            'is_default_in_filter' => true,
        ]);
        $notDefault = $this->createPartnerSchoolLeadStatus([
            'name'                 => 'НеДефолтФильтр',
            'is_default_in_filter' => false,
        ]);

        $response = $this->get(route('admin.school-leads'))->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString(
            'value="' . $this->schoolLeadSystemStatusId() . '" selected',
            $html
        );
        $this->assertStringContainsString(
            'value="' . $customDefault->id . '" selected',
            $html
        );
        $this->assertStringNotContainsString(
            'value="' . $notDefault->id . '" selected',
            $html
        );

        $defaultIds = $response->viewData('defaultStatusFilterIds');
        $this->assertContains((string) $this->schoolLeadSystemStatusId(), $defaultIds);
        $this->assertContains((string) $customDefault->id, $defaultIds);
        $this->assertNotContains((string) $notDefault->id, $defaultIds);
    }

    public function test_index_passes_school_lead_statuses_collection_to_view(): void
    {
        $custom = $this->createPartnerSchoolLeadStatus(['name' => 'ДляВью']);

        $response = $this->get(route('admin.school-leads'))->assertOk();

        $statuses = $response->viewData('schoolLeadStatuses');
        $this->assertGreaterThanOrEqual(2, $statuses->count());
        $this->assertTrue($statuses->contains('id', $this->schoolLeadSystemStatusId()));
        $this->assertTrue($statuses->contains('id', $custom->id));
    }

    public function test_datatable_without_status_ids_returns_all_leads(): void
    {
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'БезФильтра1',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'БезФильтра2',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, $response->json('recordsFiltered'));
        $names = array_column($response->json('data'), 'name');
        $this->assertContains('БезФильтра1', $names);
        $this->assertContains('БезФильтра2', $names);
    }

    public function test_datatable_with_empty_status_ids_returns_all_leads(): void
    {
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ПустойМассив1',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ПустойМассив2',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSaleStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'       => 1,
            'start'      => 0,
            'length'     => 10,
            'status_ids' => [],
        ]));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, $response->json('recordsFiltered'));
    }

    public function test_datatable_filters_by_single_custom_status(): void
    {
        $status = $this->createPartnerSchoolLeadStatus(['name' => 'ТолькоЭтот']);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ПодходитКастом',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $status->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'НеПодходит',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'       => 1,
            'start'      => 0,
            'length'     => 10,
            'status_ids' => [$status->id],
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('ПодходитКастом', $response->json('data.0.name'));
        $this->assertSame('ТолькоЭтот', $response->json('data.0.status_label'));
    }

    public function test_datatable_ignores_zero_and_negative_status_ids(): void
    {
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ВалидныйЛид',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'       => 1,
            'start'      => 0,
            'length'     => 10,
            'status_ids' => [0, -1],
        ]));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('recordsFiltered'));
    }

    public function test_datatable_with_unknown_positive_status_id_returns_empty(): void
    {
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'НеПопадёт',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'       => 1,
            'start'      => 0,
            'length'     => 10,
            'status_ids' => [999999999],
        ]));

        $response->assertOk();
        $this->assertEquals(0, $response->json('recordsFiltered'));
    }

    public function test_datatable_filters_by_multiple_status_including_custom(): void
    {
        $custom = $this->createPartnerSchoolLeadStatus(['name' => 'МультиКастом']);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Системный',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Кастомный',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $custom->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Исключён',
            'phone'                 => '+7 900 333-33-33',
            'school_lead_status_id' => $this->schoolLeadSaleStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'       => 1,
            'start'      => 0,
            'length'     => 10,
            'status_ids' => [$this->schoolLeadSystemStatusId(), $custom->id],
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $this->assertEqualsCanonicalizing(
            ['Системный', 'Кастомный'],
            array_column($response->json('data'), 'name')
        );
    }

    public function test_datatable_status_ids_filter_combined_with_special_conditions(): void
    {
        SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'СтатусИОсобенности',
            'phone'                  => '+7 900 111-11-11',
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
            'is_individual_traits'   => true,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ТолькоСтатус',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'ДругойСтатусОсобый',
            'phone'                  => '+7 900 333-33-33',
            'school_lead_status_id'  => $this->schoolLeadProcessingStatusId(),
            'is_individual_traits'   => true,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'                   => 1,
            'start'                  => 0,
            'length'                 => 10,
            'status_ids'             => [$this->schoolLeadSystemStatusId()],
            'has_special_conditions' => '1',
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('СтатусИОсобенности', $response->json('data.0.name'));
    }
}
