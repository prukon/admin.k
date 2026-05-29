<?php

namespace Tests\Feature\Public\Concerns;

use App\Models\Location;
use App\Models\Partner;
use App\Models\PartnerWidget;
use App\Models\Team;
use App\Services\LocationTeamSyncService;
use App\Services\PartnerWidgetService;
use Database\Seeders\PermissionGroupsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Http;

trait ProvidesSchoolLeadLandingFixtures
{
    protected function setUpProvidesSchoolLeadLandingFixtures(): void
    {
        $this->withoutMiddleware([
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);
    }

    protected Partner $landingPartner;

    protected PartnerWidget $landingWidget;

    protected Location $landingLocation;

    protected Team $landingTeam;

    protected function setUpSchoolLeadLandingFixtures(
        array $partnerAttributes = [],
    ): void {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionGroupsSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->landingPartner = Partner::factory()->create(array_merge([
            'title' => 'Детская школа «Радуга»',
        ], $partnerAttributes));

        $this->landingWidget = app(PartnerWidgetService::class)
            ->ensureForPartner((int) $this->landingPartner->id);

        $this->landingWidget->update(['landing_slug' => 'raduga-test']);
        $this->landingWidget->refresh();

        $this->landingLocation = Location::query()->create([
            'partner_id' => $this->landingPartner->id,
            'name'       => 'Центральный',
            'is_enabled' => true,
        ]);

        $this->landingTeam = Team::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'title'      => 'Плавание',
            'is_enabled' => true,
        ]);

        app(LocationTeamSyncService::class)->syncTeamsForLocation(
            $this->landingLocation,
            [(int) $this->landingTeam->id],
        );
    }

    protected function fakeRecaptchaSuccess(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
        ]);
    }

    protected function fakeRecaptchaLowScore(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.1,
            ], 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validLandingPayload(array $overrides = []): array
    {
        return array_merge([
            'parent_lastname'   => 'Иванов',
            'parent_firstname'  => 'Иван',
            'parent_middlename' => 'Иванович',
            'parent_phone'      => '+7 999 111-22-33',
            'parent_email'      => 'parent@example.com',
            'child_lastname'    => 'Иванов',
            'child_firstname'   => 'Пётр',
            'child_middlename'  => 'Иванович',
            'child_birthday'    => '2018-05-10',
            'location_id'       => $this->landingLocation->id,
            'team_id'           => $this->landingTeam->id,
            'consent_accepted'  => '1',
            'recaptcha_token'   => 'fake-token',
        ], $overrides);
    }
}
