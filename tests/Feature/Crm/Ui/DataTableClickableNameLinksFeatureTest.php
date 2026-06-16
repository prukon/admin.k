<?php

namespace Tests\Feature\Crm\Ui;

use App\Models\Contract;
use App\Models\District;
use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\SportType;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Crm\Contracts\ContractsFeatureTestCase;

/**
 * Кликабельные названия в DataTables: UI (type:link, классы, обработчики) и API,
 * которые вызываются при клике (модалка / переход на карточку).
 */
final class DataTableClickableNameLinksFeatureTest extends ContractsFeatureTestCase
{
    private function grantPermission(string $permissionName): void
    {
        $this->grantPermissionToRoleForPartner(
            $this->user->role_id,
            $this->partner->id,
            $permissionName,
        );
    }

    public function test_districts_index_renders_clickable_name_link_and_show_json_returns_200(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Link District',
        ]);

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee("key: 'name'", false)
            ->assertSee("type: 'link'", false)
            ->assertSee("linkClass: 'js-district-edit'", false)
            ->assertSee("'.js-district-edit'", false);

        $this->getJson(route('admin.districts.show', $district->id))
            ->assertOk()
            ->assertJsonPath('id', $district->id)
            ->assertJsonPath('name', 'Link District');
    }

    public function test_locations_index_renders_clickable_name_link_when_manage_allowed_and_show_json_returns_200(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Link Location',
        ]);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('const canManageLocations = true', false)
            ->assertSee("linkClass: 'js-location-edit'", false)
            ->assertSee("'.js-location-edit'", false);

        $this->getJson(route('admin.locations.show', $location->id))
            ->assertOk()
            ->assertJsonPath('id', $location->id)
            ->assertJsonPath('name', 'Link Location');
    }

    public function test_locations_index_uses_text_name_column_when_manage_not_allowed(): void
    {
        $this->grantPermission('locations.view');

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('const canManageLocations = false', false)
            ->assertSee("canManageLocations ? 'link' : 'text'", false);
    }

    public function test_sport_types_index_renders_clickable_name_link_when_manage_allowed_and_show_json_returns_200(): void
    {
        $this->grantPermission('sport_types.view');
        $this->grantPermission('sport_types.manage');

        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Link Sport',
        ]);

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('const canManageSportTypes = true', false)
            ->assertSee("linkClass: 'js-sport-type-edit'", false)
            ->assertSee("'.js-sport-type-edit'", false);

        $this->getJson(route('admin.sport-types.show', $sportType->id))
            ->assertOk()
            ->assertJsonPath('id', $sportType->id)
            ->assertJsonPath('name', 'Link Sport');
    }

    public function test_sport_types_index_uses_text_name_column_when_manage_not_allowed(): void
    {
        $this->grantPermission('sport_types.view');

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('const canManageSportTypes = false', false)
            ->assertSee("canManageSportTypes ? 'link' : 'text-long'", false);
    }

    public function test_teams_index_renders_clickable_title_link_and_edit_json_returns_200(): void
    {
        $this->grantPermission('groups.view');

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Link Team',
        ]);

        $this->get('/admin/teams')
            ->assertOk()
            ->assertSee("key: 'title'", false)
            ->assertSee("linkClass: 'edit-team-link'", false)
            ->assertSee('.edit-team-link', false);

        $this->getJson(route('admin.team.edit', $team->id))
            ->assertOk()
            ->assertJsonPath('id', $team->id)
            ->assertJsonPath('title', 'Link Team');
    }

    public function test_school_leads_index_renders_clickable_parent_name_and_update_json_returns_200(): void
    {
        $this->grantPermission('schoolLeads.view');
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Parent Link',
            'phone'                 => '+7 900 555-55-55',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee("key: 'name'", false)
            ->assertSee("linkClass: 'edit-lead'", false)
            ->assertSee("'.edit-lead'", false)
            ->assertSee('id="editLeadModal"', false);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
            'comment'               => 'Via name link modal',
        ])
            ->assertOk();
    }

    public function test_contracts_index_renders_nav_link_on_user_name_and_show_page_returns_200(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'ContractNav',
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => $student->team_id,
            'source_pdf_path' => 'documents/nav/' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('b', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        Storage::put($contract->source_pdf_path, '%PDF-test');

        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertSee("key: 'user_name'", false)
            ->assertSee("linkClass: 'js-dt-nav-link'", false)
            ->assertSee('data-href="/client-contracts/', false)
            ->assertSee('bindNavLinks', false);

        $json = $this->getJson(route('contracts.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->json();

        $row = collect($json['data'])->firstWhere('id', $contract->id);
        $this->assertNotNull($row);
        $this->assertSame('ContractNav', $row['user_name']);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertViewIs('contracts.show');
    }

    public function test_contract_templates_index_renders_clickable_title_and_edit_json_returns_200(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'Link Template']);

        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertSee("linkClass: 'js-contract-template-edit-link'", false)
            ->assertSee('data-template-id', false)
            ->assertSee('ContractTemplateEditModal', false)
            ->assertSee('data-edit-show-url-template', false)
            ->assertSee('id="editContractTemplateModal"', false);

        $this->getJson(route('contract-templates.edit', $template))
            ->assertOk()
            ->assertJsonPath('id', $template->id)
            ->assertJsonPath('title', 'Link Template')
            ->assertJsonStructure(['update_url', 'html']);
    }
}
