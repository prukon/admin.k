<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт фильтров и модалки: GET data JSON, PUT 200/422 errors[field].
 */
final class SchoolLeadsFilterSelect2AjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_ACCEPT'           => 'application/json',
        ];
    }

    private function grantLocationsView(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('locations.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_ajax_datatable_with_several_team_ids_returns_json_for_any_match(): void
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'AjaxСекцияА',
            'is_enabled' => true,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'AjaxСекцияБ',
            'is_enabled' => true,
        ]);
        $teamC = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'AjaxСекцияВ',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'AjaxЛидА',
            'phone'                 => '+7 900 621-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamA->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'AjaxЛидБ',
            'phone'                 => '+7 900 621-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamB->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'AjaxЛидВ',
            'phone'                 => '+7 900 621-33-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamC->id,
        ]);

        $response = $this->getJson(
            route('admin.school-leads.data', [
                'draw'     => 1,
                'start'    => 0,
                'length'   => 10,
                'team_ids' => [$teamA->id, $teamB->id],
            ]),
            $this->ajaxHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'stats' => ['total', 'new'],
                'data',
            ]);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $this->assertEqualsCanonicalizing(
            ['AjaxЛидА', 'AjaxЛидБ'],
            array_column($response->json('data'), 'name')
        );
    }

    public function test_ajax_datatable_with_several_location_ids_returns_json_for_any_match(): void
    {
        $this->grantLocationsView();

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'AjaxОбъектА',
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'AjaxОбъектБ',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'AjaxОбъектЛидА',
            'phone'                 => '+7 900 622-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id'           => $locA->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'AjaxОбъектЛидБ',
            'phone'                 => '+7 900 622-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id'           => $locB->id,
        ]);

        $response = $this->getJson(
            route('admin.school-leads.data', [
                'draw'         => 1,
                'start'        => 0,
                'length'       => 10,
                'location_ids' => [$locA->id, $locB->id],
            ]),
            $this->ajaxHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 2);
        $this->assertEqualsCanonicalizing(
            ['AjaxОбъектЛидА', 'AjaxОбъектЛидБ'],
            array_column($response->json('data'), 'name')
        );
    }

    public function test_ajax_update_saves_single_team_and_returns_message(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'AjaxГруппаЛид',
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'AjaxСохранитьГруппу',
            'phone'                 => '+7 900 623-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->putJson(
            route('admin.school-leads.update', ['schoolLead' => $lead->id]),
            ['team_id' => $team->id],
            $this->ajaxHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Изменения сохранены.')
            ->assertJsonPath('team_id', $team->id);
        $this->assertSame($team->id, (int) $lead->fresh()->team_id);
    }

    public function test_ajax_update_rejects_foreign_team_with_errors_team_id(): void
    {
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title'      => 'ЧужаяГруппаAjax',
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'AjaxЧужаяГруппа',
            'phone'                 => '+7 900 624-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(
            route('admin.school-leads.update', ['schoolLead' => $lead->id]),
            ['team_id' => $foreignTeam->id],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $this->assertNull($lead->fresh()->team_id);
    }

    public function test_ajax_update_rejects_foreign_location_with_errors_location_id(): void
    {
        $this->grantLocationsView();

        $foreignLoc = Location::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'AjaxЧужойОбъект',
            'phone'                 => '+7 900 625-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(
            route('admin.school-leads.update', ['schoolLead' => $lead->id]),
            ['location_id' => $foreignLoc->id],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }
}
