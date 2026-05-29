<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SchoolLeadSource;
use App\Enums\SchoolLeadStatus;
use App\Http\Requests\SubmitSchoolLeadLandingRequest;
use App\Models\Location;
use App\Models\PartnerWidget;
use App\Models\SchoolLead;
use App\Models\SportType;
use App\Models\Team;
use Illuminate\Support\Collection;

final class SchoolLeadLandingService
{
    public function __construct(
        private readonly TeamLocationAvailabilityService $teamLocationAvailability,
    ) {
    }

    public function resolveActiveWidget(string $landingKey): PartnerWidget
    {
        return PartnerWidget::query()
            ->with('partner')
            ->where('landing_key', $landingKey)
            ->where('is_landing_active', true)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function locationsForWidget(PartnerWidget $widget): Collection
    {
        return Location::query()
            ->where('partner_id', $widget->partner_id)
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Location $location) => [
                'id'   => (int) $location->id,
                'name' => (string) $location->name,
            ]);
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function sportTypesForWidget(PartnerWidget $widget): Collection
    {
        return SportType::query()
            ->where('partner_id', $widget->partner_id)
            ->where('is_enabled', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SportType $sportType) => [
                'id'   => (int) $sportType->id,
                'name' => (string) $sportType->name,
            ]);
    }

    /**
     * @return Collection<int, array{id: int, title: string}>
     */
    public function teamsForLocation(PartnerWidget $widget, int $locationId, ?int $sportTypeId = null): Collection
    {
        $partnerId = (int) $widget->partner_id;

        $locationExists = Location::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereKey($locationId)
            ->exists();

        if (!$locationExists) {
            return collect();
        }

        $query = Team::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->orderBy('title');

        $this->teamLocationAvailability->scopeAvailableForLocation($query, $locationId);

        if ($sportTypeId !== null && $sportTypeId > 0) {
            $query->where('sport_type_id', $sportTypeId);
        }

        return $query
            ->get(['id', 'title'])
            ->map(fn (Team $team) => [
                'id'    => (int) $team->id,
                'title' => (string) $team->title,
            ]);
    }

    public function createFromRequest(SubmitSchoolLeadLandingRequest $request, PartnerWidget $widget): SchoolLead
    {
        $parentName = trim(implode(' ', array_filter([
            $request->string('parent_lastname')->toString(),
            $request->string('parent_firstname')->toString(),
            $request->string('parent_middlename')->toString(),
        ])));

        return SchoolLead::create([
            'partner_id'             => $widget->partner_id,
            'partner_widget_id'      => $widget->id,
            'source'                 => SchoolLeadSource::Landing->value,
            'name'                   => $parentName,
            'phone'                  => $request->string('parent_phone')->toString(),
            'parent_lastname'        => $request->string('parent_lastname')->toString(),
            'parent_firstname'       => $request->string('parent_firstname')->toString(),
            'parent_middlename'      => $request->string('parent_middlename')->toString(),
            'parent_phone'           => $request->string('parent_phone')->toString(),
            'parent_email'           => $request->string('parent_email')->toString(),
            'child_lastname'         => $request->string('child_lastname')->toString(),
            'child_firstname'        => $request->string('child_firstname')->toString(),
            'child_middlename'       => $request->string('child_middlename')->toString(),
            'child_birthday'         => $request->date('child_birthday'),
            'is_individual_traits'   => $request->boolean('is_individual_traits'),
            'is_on_medical_register' => $request->boolean('is_on_medical_register'),
            'is_with_disability'     => $request->boolean('is_with_disability'),
            'location_id'            => (int) $request->input('location_id'),
            'sport_type_id'          => $request->filled('sport_type_id') ? (int) $request->input('sport_type_id') : null,
            'team_id'                => $request->filled('team_id') ? (int) $request->input('team_id') : null,
            'needs_contact_help'     => $request->boolean('needs_contact_help'),
            'comment'                => $request->input('comment'),
            'status'                 => SchoolLeadStatus::New->value,
            'utm_source'             => $request->input('utm_source'),
            'utm_medium'             => $request->input('utm_medium'),
            'utm_campaign'           => $request->input('utm_campaign'),
            'utm_content'            => $request->input('utm_content'),
            'utm_term'               => $request->input('utm_term'),
            'page_url'               => $request->input('page_url'),
            'referrer'               => $request->input('referrer'),
            'consent_accepted_at'    => now(),
            'policy_url'             => null,
            'ip'                     => $request->ip(),
            'user_agent'             => $request->userAgent(),
        ]);
    }
}
