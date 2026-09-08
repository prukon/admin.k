<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\SchoolLead;
use App\Models\Team;
use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX: GET data без X-Requested-With остаётся JSON 200; PUT без AJAX — 302 и запись в БД.
 */
final class SchoolLeadsFilterSelect2NonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_datatable_without_ajax_header_returns_json_for_several_team_ids(): void
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'NonaСекцияА',
            'is_enabled' => true,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'NonaСекцияБ',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'NonaЛидА',
            'phone'                 => '+7 900 631-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamA->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'NonaЛидБ',
            'phone'                 => '+7 900 631-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamB->id,
        ]);

        $response = $this->get(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'team_ids' => [$teamA->id, $teamB->id],
        ]));

        $response->assertOk();
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $this->assertEqualsCanonicalizing(
            ['NonaЛидА', 'NonaЛидБ'],
            array_column($response->json('data'), 'name')
        );
    }

    public function test_put_lead_team_without_ajax_header_redirects_and_saves(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'NonaГруппа',
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'NonaСохранить',
            'phone'                 => '+7 900 632-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'team_id' => $team->id,
            ]);

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame($team->id, (int) $lead->fresh()->team_id);
    }

    public function test_put_invalid_team_without_ajax_header_redirects_back_with_team_id_error(): void
    {
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title'      => 'NonaЧужая',
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'NonaОшибкаГруппы',
            'phone'                 => '+7 900 633-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'team_id' => $foreignTeam->id,
            ]);

        $response->assertRedirect(route('admin.school-leads'));
        $response->assertSessionHasErrors(['team_id']);
        $this->assertNull($lead->fresh()->team_id);
    }
}
