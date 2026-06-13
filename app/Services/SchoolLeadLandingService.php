<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SchoolLeadSource;
use App\Enums\SchoolLeadStatus;
use App\Http\Requests\SubmitSchoolLeadLandingRequest;
use App\Models\District;
use App\Models\Location;
use App\Models\PartnerWidget;
use App\Models\SchoolLead;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class SchoolLeadLandingService
{
    public function __construct(
        private readonly TeamLocationAvailabilityService $teamLocationAvailability,
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
            ->with([
                'sportType:id,name',
                'weekdays' => fn ($query) => $query->orderBy('weekdays.id'),
            ])
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

        $weekdaysCount = $team->weekdays->count();
        $scheduleLabel = $team->weekdays
            ->pluck('title')
            ->filter(fn ($title) => trim((string) $title) !== '')
            ->implode(', ');

        return [
            'title' => (string) $team->title,
            'rows'  => [
                ['label' => 'Тренировочная база', 'value' => $this->displayValue($team->training_base)],
                ['label' => 'Адрес', 'value' => $this->displayValue($team->address)],
                ['label' => 'Вид спорта', 'value' => $this->displayValue($team->sportType?->name)],
                ['label' => 'Стоимость в месяц', 'value' => $this->formatMonthPrice($team->month_price)],
                ['label' => 'Занятий в неделю', 'value' => $weekdaysCount > 0 ? (string) $weekdaysCount : '—'],
                ['label' => 'Занятий в месяц', 'value' => $weekdaysCount > 0 ? (string) ($weekdaysCount * 4) : '—'],
                ['label' => 'Продолжительность занятия', 'value' => $this->formatDuration($team->default_duration_minutes)],
                ['label' => 'Период занятий', 'value' => $this->formatTrainingPeriod()],
                ['label' => 'Расписание занятий', 'value' => $scheduleLabel !== '' ? $scheduleLabel : '—'],
            ],
        ];
    }

    private function displayValue(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : '—';
    }

    private function formatMonthPrice(mixed $price): string
    {
        if ($price === null || $price === '') {
            return '—';
        }

        $amount = (int) $price;
        if ($amount < 0) {
            return '—';
        }

        return number_format($amount, 0, ',', ' ') . ' ₽';
    }

    private function formatDuration(mixed $minutes): string
    {
        $minutes = (int) $minutes;
        if ($minutes <= 0) {
            return '—';
        }

        if ($minutes % 60 === 0) {
            $hours = (int) ($minutes / 60);

            return $hours === 1 ? '1 час' : $hours . ' ч';
        }

        if ($minutes > 60) {
            $hours = intdiv($minutes, 60);
            $rest = $minutes % 60;

            return $hours . ' ч ' . $rest . ' мин';
        }

        return $minutes . ' мин';
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
            'district_id'            => (int) $request->input('district_id'),
            'sport_type_id'          => $sportTypeId,
            'team_id'                => $teamId,
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
