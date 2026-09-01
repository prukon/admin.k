<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SchoolLeadSource;
use App\Models\SchoolLeadStatus;
use App\Http\Requests\SubmitSchoolLeadLandingRequest;
use App\Models\District;
use App\Models\Location;
use App\Models\PartnerWidget;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Services\SchoolLeads\SchoolLeadParentMatcher;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class SchoolLeadLandingService
{
    public function __construct(
        private readonly TeamLocationAvailabilityService $teamLocationAvailability,
        private readonly SchoolLeadParentMatcher $parentMatcher,
    ) {
    }

    public function resolveActiveWidget(string $landingSlug): PartnerWidget
    {
        return PartnerWidget::query()
            ->with('partner')
            ->where('landing_slug', $landingSlug)
            ->where('is_landing_active', true)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function districtsForWidget(PartnerWidget $widget): Collection
    {
        return District::query()
            ->where('partner_id', $widget->partner_id)
            ->where('is_enabled', true)
            ->whereHas('locations', function ($query) {
                $query->where('is_enabled', true)
                    ->whereNotNull('district_id');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (District $district) => [
                'id'   => (int) $district->id,
                'name' => (string) $district->name,
            ]);
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function locationsForDistrict(PartnerWidget $widget, int $districtId): Collection
    {
        $partnerId = (int) $widget->partner_id;

        $districtExists = District::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereKey($districtId)
            ->exists();

        if (! $districtExists) {
            return collect();
        }

        return Location::query()
            ->where('partner_id', $partnerId)
            ->where('district_id', $districtId)
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Location $location) => [
                'id'   => (int) $location->id,
                'name' => (string) $location->name,
            ]);
    }

    /**
     * @return Collection<int, array{id: int, title: string}>
     */
    public function teamsForLocation(PartnerWidget $widget, int $locationId): Collection
    {
        $partnerId = (int) $widget->partner_id;

        $locationExists = Location::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereNotNull('district_id')
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

        return $query
            ->get(['id', 'title'])
            ->map(fn (Team $team) => [
                'id'    => (int) $team->id,
                'title' => (string) $team->title,
            ]);
    }

    /**
     * @return array{title: string, rows: list<array{label: string, value: string}>}|null
     */
    public function teamInfoForLanding(PartnerWidget $widget, int $locationId, int $teamId): ?array
    {
        $partnerId = (int) $widget->partner_id;

        $locationExists = Location::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereNotNull('district_id')
            ->whereKey($locationId)
            ->exists();

        if (!$locationExists) {
            return null;
        }

        $team = Team::query()
            ->with(['sportType:id,name'])
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereKey($teamId)
            ->first();

        if ($team === null) {
            return null;
        }

        if (!$this->teamLocationAvailability->isTeamAllowedAtLocation($team, $locationId)) {
            return null;
        }

        $location = Location::query()
            ->where('partner_id', $partnerId)
            ->whereKey($locationId)
            ->first(['address']);

        $rows = [
            ['label' => 'Адрес', 'value' => $this->displayValue($location?->address)],
            ['label' => 'Вид спорта', 'value' => $this->displayValue($team->sportType?->name)],
            ['label' => 'Стоимость в месяц', 'value' => $this->formatMonthPrice($team->month_price_cents)],
            ['label' => 'Период занятий', 'value' => $this->formatTrainingPeriod()],
        ];

        return [
            'title' => (string) $team->title,
            'rows'  => array_values(array_filter(
                $rows,
                static fn (array $row): bool => $row['value'] !== ''
            )),
        ];
    }

    private function displayValue(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function formatMonthPrice(mixed $cents): string
    {
        if ($cents === null || $cents === '') {
            return '';
        }

        $amountCents = (int) $cents;
        if ($amountCents < 0) {
            return '';
        }

        return Money::formatRub($amountCents, ' ₽');
    }

    private function formatTrainingPeriod(?Carbon $today = null): string
    {
        $today ??= Carbon::today();
        $year = (int) $today->year;

        if ($today->betweenIncluded(
            Carbon::create($year, 1, 1)->startOfDay(),
            Carbon::create($year, 6, 30)->endOfDay()
        )) {
            $start = Carbon::create($year, 1, 12);
            $end = Carbon::create($year, 6, 30);
        } else {
            $start = Carbon::create($year, 9, 1);
            $end = Carbon::create($year + 1, 6, 30);
        }

        return $start->format('d.m.Y') . ' — ' . $end->format('d.m.Y');
    }

    public function createFromRequest(SubmitSchoolLeadLandingRequest $request, PartnerWidget $widget): SchoolLead
    {
        $parentName = trim(implode(' ', array_filter([
            $request->string('parent_lastname')->toString(),
            $request->string('parent_firstname')->toString(),
            $request->string('parent_middlename')->toString(),
        ])));

        $teamId = $request->filled('team_id') ? (int) $request->input('team_id') : null;
        $sportTypeId = null;

        if ($teamId !== null) {
            $team = Team::query()
                ->where('partner_id', $widget->partner_id)
                ->whereKey($teamId)
                ->first(['sport_type_id']);

            if ($team?->sport_type_id !== null) {
                $sportTypeId = (int) $team->sport_type_id;
            }
        }

        $parentLastname = $request->string('parent_lastname')->toString();
        $parentFirstname = $request->string('parent_firstname')->toString();
        $parentPhone = $request->string('parent_phone')->toString();
        $parentEmail = $request->string('parent_email')->toString();

        $attributes = [
            'partner_id'             => $widget->partner_id,
            'partner_widget_id'      => $widget->id,
            'source'                 => SchoolLeadSource::Landing->value,
            'name'                   => $parentName,
            'phone'                  => $parentPhone,
            'parent_lastname'        => $parentLastname,
            'parent_firstname'       => $parentFirstname,
            'parent_middlename'      => $request->string('parent_middlename')->toString(),
            'parent_phone'           => $parentPhone,
            'parent_email'           => $parentEmail,
            'child_lastname'         => $request->string('child_lastname')->toString(),
            'child_firstname'        => $request->string('child_firstname')->toString(),
            'child_middlename'       => $request->string('child_middlename')->toString(),
            'child_birthday'         => $request->date('child_birthday'),
            'is_individual_traits'   => $request->boolean('is_individual_traits'),
            'is_on_medical_register' => $request->boolean('is_on_medical_register'),
            'is_with_disability'     => $request->boolean('is_with_disability'),
            'location_id'            => (int) $request->input('location_id'),
            'district_id'            => (int) $request->input('district_id'),
            'sport_type_id'          => $sportTypeId,
            'team_id'                => $teamId,
            'needs_contact_help'     => $request->boolean('needs_contact_help'),
            'comment'                => $request->input('comment'),
            'school_lead_status_id'  => SchoolLeadStatus::systemNewId(),
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
        ];

        $match = $this->parentMatcher->match(
            (int) $widget->partner_id,
            $parentEmail,
            $parentPhone,
            $parentLastname,
            $parentFirstname,
        );

        if ($match !== null) {
            $attributes = array_merge($attributes, $match->toLeadAttributes());
        }

        return SchoolLead::create($attributes);
    }
}
