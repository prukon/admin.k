<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\District;
use App\Models\Location;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Полное покрытие объединённой модалки «Редактирование лида» и связанных сценариев.
 */
final class SchoolLeadEditModalFullFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    private function defaultRoleId(): int
    {
        return (int) Role::query()->where('is_visible', 1)->orderBy('order_by')->value('id');
    }

    public function test_edit_modal_renders_status_and_comment_in_top_section(): void
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $topFieldsPos = strpos($html, 'edit-lead-top-fields');
        $accordionPos = strpos($html, 'id="editLeadAccordion"');
        $statusPickerPos = strpos($html, 'id="leadModalStatusPicker"');
        $commentPos = strpos($html, 'id="leadComment"');

        $this->assertNotFalse($topFieldsPos);
        $this->assertNotFalse($accordionPos);
        $this->assertNotFalse($statusPickerPos);
        $this->assertNotFalse($commentPos);
        $this->assertLessThan($accordionPos, $topFieldsPos);
        $this->assertLessThan($accordionPos, $statusPickerPos);
        $this->assertLessThan($accordionPos, $commentPos);
        $this->assertStringContainsString('for="leadModalStatusTrigger">Статус</label>', $html);
        $this->assertStringContainsString('d-flex align-items-center flex-wrap gap-2', $html);
    }

    public function test_edit_modal_student_accordion_contains_group_district_and_location(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="editLeadAccordionStudent"', false)
            ->assertSee('id="leadTeam"', false)
            ->assertSee('id="leadDistrict"', false)
            ->assertSee('id="leadLocation"', false)
            ->assertSee('id="leadChildBirthday"', false)
            ->assertDontSee('editLeadAccordionGeneral', false);
    }

    public function test_edit_modal_renders_health_fields_as_checkboxes(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('js-lead-health-checkbox', false)
            ->assertSee('id="lead-is_individual_traits"', false)
            ->assertSee('type="checkbox"', false)
            ->assertSee('name="is_individual_traits"', false)
            ->assertSee('name="is_on_medical_register"', false)
            ->assertSee('name="is_with_disability"', false)
            ->assertSee('.prop(\'checked\'', false);
    }

    public function test_edit_modal_includes_inline_status_picker_and_modal_status_helpers(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('setLeadModalStatusPicker', false)
            ->assertSee('lead-modal-status-picker', false)
            ->assertSee('leadModalStatusTrigger', false)
            ->assertSee('data-field-error="school_lead_status_id"', false);
    }

    public function test_edit_modal_includes_client_created_plaque_and_contract_button_markup(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="leadClientCreatedBadge"', false)
            ->assertSee('На основании лида был создан клиент.', false)
            ->assertSee('id="leadCreateContractWrap"', false)
            ->assertSee('id="leadCreateContractBtn"', false)
            ->assertSee('type="button"', false)
            ->assertSee('setLeadModalClientInfo', false)
            ->assertSee('.not(\'#leadCreateContractBtn\')', false);
    }

    public function test_page_embeds_contract_create_modal_for_inline_workflow(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewHas('contractTemplates')
            ->assertViewHas('contractCreatePartner')
            ->assertSee('id="createContractModal"', false)
            ->assertSee('KidsCrmContractCreate.openModal', false)
            ->assertSee('openCreateContractFromLead', false)
            ->assertSee('js-open-create-contract-from-lead', false)
            ->assertSee('buildContractPreselectedUser', false);
    }

    public function test_page_without_contracts_view_omits_embedded_contract_modal(): void
    {
        $actor = $this->createUserWithoutPermission('contracts.view', $this->partner);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('schoolLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->actingAs($actor)
            ->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="editLeadModal"', false)
            ->assertDontSee('id="createContractModal"', false)
            ->assertDontSee('id="leadCreateContractBtn"', false);
    }

    public function test_datatable_row_includes_fields_required_for_edit_modal(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Группа модалки',
            'is_enabled' => true,
        ]);

        $district = District::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $location = Location::factory()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => $district->id,
            'is_enabled'  => true,
        ]);

        $customStatus = $this->createPartnerSchoolLeadStatus([
            'name'  => 'Модальный статус',
            'color' => '#6610f2',
        ]);

        SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Иванова Мария',
            'phone'                  => '+7 900 333-33-33',
            'parent_email'           => 'modal@example.com',
            'parent_lastname'        => 'Иванова',
            'parent_firstname'       => 'Мария',
            'child_lastname'         => 'Иванов',
            'child_firstname'        => 'Артём',
            'child_birthday'         => '2017-03-15',
            'team_id'                => $team->id,
            'district_id'            => $district->id,
            'location_id'            => $location->id,
            'school_lead_status_id'  => $customStatus->id,
            'comment'                => 'Комментарий модалки',
            'is_individual_traits'   => true,
            'is_on_medical_register' => false,
            'is_with_disability'     => true,
            'needs_contact_help'     => true,
        ]);

        $row = collect($this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 20,
        ]))->json('data'))->firstWhere('child_firstname', 'Артём');

        $this->assertNotNull($row);
        $this->assertSame('Комментарий модалки', $row['comment']);
        $this->assertSame('Иванова', $row['parent_lastname']);
        $this->assertSame('Мария', $row['parent_firstname']);
        $this->assertSame('Иванов', $row['child_lastname']);
        $this->assertSame('Артём', $row['child_firstname']);
        $this->assertSame('2017-03-15', $row['child_birthday_iso']);
        $this->assertSame($team->id, (int) $row['team_id']);
        $this->assertSame($district->id, (int) $row['district_id']);
        $this->assertSame($location->id, (int) $row['location_id']);
        $this->assertSame($customStatus->id, (int) $row['school_lead_status_id']);
        $this->assertSame('Модальный статус', $row['status_label']);
        $this->assertTrue($row['is_individual_traits']);
        $this->assertFalse($row['is_on_medical_register']);
        $this->assertTrue($row['is_with_disability']);
        $this->assertTrue($row['needs_contact_help']);
        $this->assertNull($row['user_id']);
    }

    public function test_datatable_linked_lead_includes_contract_create_url_for_modal(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
            'name'       => 'Клиент',
            'lastname'   => 'Из лида',
            'is_enabled' => 1,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Связанный для модалки',
            'phone'                 => '+7 900 444-44-44',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $user->id,
            'child_lastname'        => 'Из лида',
            'child_firstname'       => 'Клиент',
        ]);

        $row = collect($this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 20,
        ]))->json('data'))->firstWhere('name', 'Связанный для модалки');

        $this->assertNotNull($row);
        $this->assertSame($user->id, (int) $row['user_id']);
        $this->assertArrayNotHasKey('latest_contract', $row);
        $this->assertStringContainsString(
            'user_id=' . $user->id,
            (string) $row['create_contract_url']
        );
    }

    public function test_update_persists_health_flags_as_checkbox_values(): void
    {
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Health modal',
            'phone'                 => '+7 900 555-55-55',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'is_individual_traits'   => '1',
            'is_on_medical_register' => '1',
            'is_with_disability'     => '0',
        ])->assertOk();

        $lead->refresh();

        $this->assertTrue($lead->is_individual_traits);
        $this->assertTrue($lead->is_on_medical_register);
        $this->assertFalse($lead->is_with_disability);
    }

    public function test_update_persists_status_comment_and_student_location_fields(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Новая группа',
            'is_enabled' => true,
        ]);

        $district = District::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $location = Location::factory()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => $district->id,
            'is_enabled'  => true,
        ]);

        $newStatus = $this->createPartnerSchoolLeadStatus(['name' => 'После модалки']);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Полное обновление',
            'phone'                 => '+7 900 666-66-66',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => $newStatus->id,
            'comment'               => 'Обновлено из модалки',
            'child_lastname'        => 'Сидоров',
            'child_firstname'       => 'Илья',
            'child_birthday'        => '2016-11-20',
            'team_id'               => $team->id,
            'district_id'           => $district->id,
            'location_id'           => $location->id,
        ])->assertOk();

        $lead->refresh();

        $this->assertSame($newStatus->id, (int) $lead->school_lead_status_id);
        $this->assertSame('Обновлено из модалки', $lead->comment);
        $this->assertSame('Сидоров', $lead->child_lastname);
        $this->assertSame('Илья', $lead->child_firstname);
        $this->assertSame('2016-11-20', $lead->child_birthday?->format('Y-m-d'));
        $this->assertSame($team->id, (int) $lead->team_id);
        $this->assertSame($district->id, (int) $lead->district_id);
        $this->assertSame($location->id, (int) $lead->location_id);
    }

    public function test_inline_status_update_returns_badge_payload_for_table_and_modal(): void
    {
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Inline modal status',
            'phone'                 => '+7 900 777-77-77',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $targetStatus = $this->createPartnerSchoolLeadStatus([
            'name'  => 'Цвет модалки',
            'color' => '#fd7e14',
        ]);

        $response = $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => $targetStatus->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('school_lead_status_id', $targetStatus->id)
            ->assertJsonPath('status_label', 'Цвет модалки');

        $this->assertNotEmpty($response->json('status_badge_style'));
    }

    public function test_create_client_from_lead_links_user_and_enables_contract_flow_in_datatable(): void
    {
        $lead = SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Для клиента модалки',
            'phone'                  => '+7 900 888-88-88',
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
            'child_lastname'         => 'Модальный',
            'child_firstname'        => 'Клиент',
            'is_individual_traits'   => true,
            'is_on_medical_register' => false,
            'is_with_disability'     => false,
        ]);

        $store = $this->postJson(route('admin.user.store'), [
            'name'                   => 'Клиент',
            'lastname'               => 'Модальный',
            'role_id'                => $this->defaultRoleId(),
            'is_enabled'             => 1,
            'school_lead_id'         => $lead->id,
            'is_individual_traits'   => '1',
            'is_on_medical_register' => '0',
            'is_with_disability'     => '0',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $store->assertOk();

        $userId = (int) $store->json('user.id');
        $this->assertSame($userId, (int) $lead->fresh()->user_id);

        $row = collect($this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]))->json('data'))->firstWhere('id', $lead->id);

        $this->assertNotNull($row);
        $this->assertSame($userId, (int) $row['user_id']);
        $this->assertArrayHasKey('create_contract_url', $row);
    }

    public function test_contract_table_button_uses_javascript_opener_not_page_href(): void
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertStringContainsString('js-open-create-contract-from-lead', $html);
        $this->assertStringContainsString('openCreateContractFromLead', $html);
        $this->assertStringNotContainsString(
            'href="' . route('contracts.index', ['user_id' => '__PLACEHOLDER__']) . '"',
            str_replace('row.user_id', '__PLACEHOLDER__', $html)
        );
    }
}
