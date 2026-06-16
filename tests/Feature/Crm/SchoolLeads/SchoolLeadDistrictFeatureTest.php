<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Mail\NewSchoolLeadSubmission;
use App\Models\District;
use App\Models\Location;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;

final class SchoolLeadDistrictFeatureTest extends CrmTestCase
{
    use ProvidesSchoolLeadLandingFixtures {
        setUpSchoolLeadLandingFixtures as protected setUpLandingFixturesForPartner;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantSchoolLeadsView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('schoolLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function actorWithSchoolLeadsOnly(): User
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantSchoolLeadsView($actor);

        return $actor;
    }

    public function test_school_leads_page_shows_district_ui_with_districts_view(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Центральный',
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewHas('canViewDistricts', true)
            ->assertViewHas('activeDistricts')
            ->assertSee('id="sl-filter-district"', false)
            ->assertSee('Центральный', false)
            ->assertSee('id="leadDistrict"', false)
            ->assertSee('data-column-key="district"', false);
    }

    public function test_school_leads_page_hides_district_ui_without_districts_view(): void
    {
        $this->actorWithSchoolLeadsOnly();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewHas('canViewDistricts', false)
            ->assertDontSee('id="leadDistrict"', false)
            ->assertDontSee('id="sl-filter-district"', false)
            ->assertDontSee('data-column-key="district"', false);
    }

    public function test_datatable_returns_district_fields_and_filters_by_district(): void
    {
        $this->grantPermission('districts.view');

        $districtA = District::factory()->forPartner((int) $this->partner->id)->create(['name' => 'Район A']);
        $districtB = District::factory()->forPartner((int) $this->partner->id)->create(['name' => 'Район B']);

        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Без района',
            'phone'       => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'district_id' => null,
        ]);
        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'В A',
            'phone'       => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'district_id' => $districtA->id,
        ]);
        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'В B',
            'phone'       => '+7 900 333-33-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'district_id' => $districtB->id,
        ]);

        $all = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));
        $all->assertOk();
        $this->assertEquals(3, $all->json('recordsTotal'));
        $this->assertArrayHasKey('district_name', $all->json('data.0'));
        $this->assertArrayHasKey('district_id', $all->json('data.0'));

        $withoutDistrict = $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'district_id' => 'none',
        ]));
        $withoutDistrict->assertOk();
        $this->assertEquals(1, $withoutDistrict->json('recordsFiltered'));
        $this->assertSame('Без района', $withoutDistrict->json('data.0.name'));

        $inDistrictA = $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'district_id' => (string) $districtA->id,
        ]));
        $inDistrictA->assertOk();
        $this->assertEquals(1, $inDistrictA->json('recordsFiltered'));
        $this->assertSame('В A', $inDistrictA->json('data.0.name'));
        $this->assertSame('Район A', $inDistrictA->json('data.0.district_name'));
    }

    public function test_datatable_search_by_district_name(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'УникальныйРайонXYZ',
        ]);

        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Найти по району',
            'phone'       => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'district_id' => $district->id,
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Не попадёт',
            'phone'      => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
            'search' => ['value' => 'УникальныйРайонXYZ'],
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('Найти по району', $response->json('data.0.name'));
    }

    public function test_datatable_sorts_by_district_name(): void
    {
        $this->grantPermission('districts.view');

        $districtZ = District::factory()->forPartner((int) $this->partner->id)->create(['name' => 'Я-район']);
        $districtA = District::factory()->forPartner((int) $this->partner->id)->create(['name' => 'А-район']);

        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Z-lead',
            'phone'       => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'district_id' => $districtZ->id,
        ]);
        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'A-lead',
            'phone'       => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'district_id' => $districtA->id,
        ]);

        $columns = [
            ['data' => 'id'],
            ['data' => 'name'],
            ['data' => 'phone'],
            ['data' => 'district_name'],
            ['data' => 'utm_summary'],
            ['data' => 'page_url'],
            ['data' => 'status_label'],
            ['data' => 'comment'],
        ];

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'order'   => [['column' => 3, 'dir' => 'asc']],
            'columns' => $columns,
        ]));

        $response->assertOk();
        $names = array_column($response->json('data'), 'district_name');
        $this->assertSame(['А-район', 'Я-район'], $names);
    }

    public function test_datatable_ignores_district_filter_without_districts_view(): void
    {
        $this->actorWithSchoolLeadsOnly();

        $district = District::factory()->forPartner((int) $this->partner->id)->create();

        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'С районом',
            'phone'       => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'district_id' => $district->id,
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Без района',
            'phone'      => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'district_id' => (string) $district->id,
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $firstRow = $response->json('data.0');
        $this->assertIsArray($firstRow);
        $this->assertArrayNotHasKey('district_name', $firstRow);
        $this->assertArrayNotHasKey('district_id', $firstRow);
    }

    public function test_update_school_lead_district(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Центральный',
        ]);

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Иван',
            'phone'      => '+7 999 123-45-67',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'district_id' => $district->id,
        ])
            ->assertOk()
            ->assertJson([
                'district_id'   => $district->id,
                'district_name' => 'Центральный',
            ]);

        $lead->refresh();
        $this->assertEquals($district->id, $lead->district_id);
    }

    public function test_update_rejects_location_from_another_district(): void
    {
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $districtA = District::factory()->forPartner((int) $this->partner->id)->create(['name' => 'A']);
        $districtB = District::factory()->forPartner((int) $this->partner->id)->create(['name' => 'B']);

        $locationB = Location::factory()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => $districtB->id,
            'is_enabled'  => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Иван',
            'phone'      => '+7 999 123-45-67',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'district_id' => $districtA->id,
            'location_id' => $locationB->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_update_rejects_district_mismatch_with_existing_location(): void
    {
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $districtA = District::factory()->forPartner((int) $this->partner->id)->create(['name' => 'A']);
        $districtB = District::factory()->forPartner((int) $this->partner->id)->create(['name' => 'B']);

        $locationA = Location::factory()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => $districtA->id,
            'is_enabled'  => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Иван',
            'phone'       => '+7 999 123-45-67',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $locationA->id,
            'district_id' => $districtA->id,
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'district_id' => $districtB->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['district_id']);
    }

    public function test_landing_submit_stores_district_id_and_notification_includes_district(): void
    {
        Mail::fake();
        config(['services.telegram.bot_token' => 'test-bot-token']);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->setUpLandingFixturesForPartner([
            'title'                         => 'Школа тест',
            'school_leads_telegram_chat_id' => '-100123456789',
        ]);

        User::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'role_id'    => Role::where('name', 'admin')->value('id'),
            'email'      => 'district-lead-admin@example.com',
        ]);

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload()
        )->assertOk();

        $this->assertDatabaseHas('school_leads', [
            'partner_id'  => $this->landingPartner->id,
            'district_id' => $this->landingDistrict->id,
            'location_id' => $this->landingLocation->id,
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org')
                && str_contains($request['text'], 'Центральный')
                && str_contains($request['text'], 'Школа «Радуга»');
        });

        Mail::assertSent(NewSchoolLeadSubmission::class, function (NewSchoolLeadSubmission $mail) {
            if (! $mail->hasTo('district-lead-admin@example.com')) {
                return false;
            }

            $html = $mail->render();

            return str_contains($html, 'Район:')
                && str_contains($html, 'Центральный')
                && str_contains($html, 'Объект:')
                && str_contains($html, 'Школа «Радуга»');
        });
    }
}
