<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreTeamScheduleSlotRequest;
use App\Http\Requests\Admin\TeamScheduleSlotOccurrenceDateRequest;
use App\Http\Requests\Admin\UpdateTeamScheduleSlotRequest;
use App\Models\Location;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Services\PartnerContext;
use App\Services\TeamLocationAvailabilityService;
use App\Services\TeamScheduleSlotMutationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TeamScheduleSlotController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly TeamLocationAvailabilityService $teamLocationAvailability,
    ) {
        parent::__construct($partnerContext);
    }

    /**
     * Вкладка «Таблица занятий» в разделе абонементов (те же данные, что и список слотов).
     */
    public function indexForLessonPackagesTab()
    {
        return view('admin.lessonPackages.index', array_merge(
            $this->slotsIndexPageData(),
            ['activeTab' => 'team-schedule-slots']
        ));
    }

    /** @return array<string, mixed> */
    private function slotsIndexPageData(): array
    {
        $partnerId = $this->requirePartnerId();

        $slots = TeamScheduleSlot::query()
            ->with(['team', 'location'])
            ->where('partner_id', $partnerId)
            ->orderBy('weekday')
            ->orderBy('time_start')
            ->paginate(50);

        $teams = Team::query()
            ->where('partner_id', $partnerId)
            ->with('locations:id')
            ->orderBy('title')
            ->get(['id', 'title']);

        $locations = Location::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get();

        $weekdays = [
            1 => 'Пн',
            2 => 'Вт',
            3 => 'Ср',
            4 => 'Чт',
            5 => 'Пт',
            6 => 'Сб',
            7 => 'Вс',
        ];

        return compact('slots', 'teams', 'locations', 'weekdays');
    }

    public function store(StoreTeamScheduleSlotRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        $data = $request->validated();

        $team = Team::query()
            ->where('partner_id', $partnerId)
            ->where('id', (int) $data['team_id'])
            ->first();

        if (!$team) {
            return response()->json(['message' => 'Группа не найдена'], 422);
        }

        $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;
        if ($locationId === 0) {
            $locationId = null;
        }
        if ($locationId) {
            $location = Location::query()
                ->where('partner_id', $partnerId)
                ->where('id', $locationId)
                ->first();

            if (!$location) {
                return response()->json(['message' => 'Локация не найдена'], 422);
            }
        }

        $teamLocationError = $this->teamLocationAvailability->assertTeamAllowedAtLocation($team, $locationId);
        if ($teamLocationError !== null) {
            return response()->json([
                'message' => 'Ошибка сохранения',
                'errors' => [
                    'team_id' => [$teamLocationError],
                ],
            ], 422);
        }

        $data['partner_id'] = $partnerId;
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? true);

        $overlapError = $this->overlapError($partnerId, null, $data);
        if ($overlapError) {
            return response()->json(['errors' => $overlapError], 422);
        }

        if ($this->identicalActiveSlotExists($partnerId, $data, null)) {
            return response()->json([
                'message' => 'Такой слот уже существует.',
                'errors' => [
                    'weekday' => ['Такой слот уже существует'],
                ],
            ], 422);
        }

        try {
            $slot = TeamScheduleSlot::create($data);
        } catch (QueryException $e) {
            $code = $e->errorInfo[1] ?? null; // MySQL error code
            if ((int) $code === 1062) {
                return response()->json([
                    'message' => 'Ошибка сохранения',
                    'errors' => [
                        'weekday' => ['Такой слот уже существует'],
                    ],
                ], 422);
            }

            return response()->json([
                'message' => 'Ошибка сохранения',
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Слот создан',
            'slot' => $slot->load(['team', 'location']),
        ]);
    }

    public function show(TeamScheduleSlot $slot)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $slot->partner_id !== $partnerId) {
            abort(404);
        }

        return response()->json([
            'id' => $slot->id,
            'team_id' => $slot->team_id,
            'location_id' => $slot->location_id,
            'weekday' => $slot->weekday,
            'time_start' => substr((string) $slot->time_start, 0, 5),
            'time_end' => substr((string) $slot->time_end, 0, 5),
            'date_start' => $slot->date_start?->format('Y-m-d'),
            'date_end' => $slot->date_end?->format('Y-m-d') === '9999-12-31' ? null : $slot->date_end?->format('Y-m-d'),
            'is_enabled' => (int) $slot->is_enabled,
        ]);
    }

    public function update(UpdateTeamScheduleSlotRequest $request, TeamScheduleSlot $slot)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $slot->partner_id !== $partnerId) {
            abort(404);
        }

        $data = $request->validated();

        $team = Team::query()
            ->where('partner_id', $partnerId)
            ->where('id', (int) $data['team_id'])
            ->first();

        if (!$team) {
            return response()->json(['message' => 'Группа не найдена'], 422);
        }

        $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;
        if ($locationId === 0) {
            $locationId = null;
        }
        if ($locationId) {
            $location = Location::query()
                ->where('partner_id', $partnerId)
                ->where('id', $locationId)
                ->first();

            if (!$location) {
                return response()->json(['message' => 'Локация не найдена'], 422);
            }
        }

        $teamLocationError = $this->teamLocationAvailability->assertTeamAllowedAtLocation($team, $locationId);
        if ($teamLocationError !== null) {
            return response()->json([
                'message' => 'Ошибка сохранения',
                'errors' => [
                    'team_id' => [$teamLocationError],
                ],
            ], 422);
        }

        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);

        $applyFrom = (string) $data['apply_changes_from'];
        unset($data['apply_changes_from']);

        $mutationService = app(TeamScheduleSlotMutationService::class);

        $slotStartYmd = $slot->date_start instanceof \Carbon\CarbonInterface
            ? $slot->date_start->format('Y-m-d')
            : Carbon::parse((string) $slot->date_start)->format('Y-m-d');

        $isSplit = Carbon::parse($applyFrom)->gt(Carbon::parse($slotStartYmd));

        if ($isSplit) {
            return $this->updateSlotWithSplit($partnerId, $slot, $data, $applyFrom, $mutationService);
        }

        if ($this->scheduleIdentityChanged($slot, $data)) {
            $mutationService->assertNoAssignmentsWhenChangingScheduleIdentity($slot);
        }

        $mutationService->assertUpdateDoesNotOrphanAssignments(
            $slot,
            (string) $data['date_start'],
            (string) $data['date_end'],
        );

        $overlapError = $this->overlapError($partnerId, (int) $slot->id, array_merge(
            ['partner_id' => $partnerId],
            $data
        ));
        if ($overlapError) {
            return response()->json(['errors' => $overlapError], 422);
        }

        if ($this->identicalActiveSlotExists($partnerId, $data, (int) $slot->id)) {
            return response()->json([
                'message' => 'Такой слот уже существует.',
                'errors' => [
                    'weekday' => ['Такой слот уже существует'],
                ],
            ], 422);
        }

        try {
            $slot->update($data);
        } catch (QueryException $e) {
            $code = $e->errorInfo[1] ?? null; // MySQL error code
            if ((int) $code === 1062) {
                return response()->json([
                    'message' => 'Ошибка сохранения',
                    'errors' => [
                        'weekday' => ['Такой слот уже существует'],
                    ],
                ], 422);
            }

            return response()->json([
                'message' => 'Ошибка сохранения',
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['message' => 'Слот обновлён']);
    }

    public function destroy(Request $request, TeamScheduleSlot $slot, TeamScheduleSlotMutationService $mutationService)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $slot->partner_id !== $partnerId) {
            abort(404);
        }

        $mutationService->softDeleteSlot($slot);

        return response()->json(['message' => 'Слот удалён']);
    }

    public function skipOccurrence(
        TeamScheduleSlotOccurrenceDateRequest $request,
        TeamScheduleSlot $slot,
        TeamScheduleSlotMutationService $mutationService,
    ) {
        $partnerId = $this->requirePartnerId();

        if ((int) $slot->partner_id !== $partnerId) {
            abort(404);
        }

        $mutationService->skipOccurrence(
            $slot,
            CarbonImmutable::createFromFormat('Y-m-d', $request->validated('occurrence_date'))->startOfDay(),
        );

        return response()->json([
            'message' => 'Занятие на выбранную дату исключено из расписания.',
        ]);
    }

    public function truncateFromOccurrenceDate(
        TeamScheduleSlotOccurrenceDateRequest $request,
        TeamScheduleSlot $slot,
        TeamScheduleSlotMutationService $mutationService,
    ) {
        $partnerId = $this->requirePartnerId();

        if ((int) $slot->partner_id !== $partnerId) {
            abort(404);
        }

        $mutationService->truncateFromOccurrenceDate(
            $slot,
            CarbonImmutable::createFromFormat('Y-m-d', $request->validated('occurrence_date'))->startOfDay(),
        );

        return response()->json([
            'message' => 'Расписание слота усечено с выбранной даты.',
        ]);
    }

    /**
     * Разделение периода слота: усечение текущей записи и создание продолжения с новыми полями.
     *
     * @param  array<string, mixed>  $data  validated update payload without apply_changes_from
     */
    private function updateSlotWithSplit(
        int $partnerId,
        TeamScheduleSlot $slot,
        array $data,
        string $applyFromYmd,
        TeamScheduleSlotMutationService $mutationService,
    ) {
        $slotStartYmd = $slot->date_start instanceof \Carbon\CarbonInterface
            ? $slot->date_start->format('Y-m-d')
            : Carbon::parse((string) $slot->date_start)->format('Y-m-d');

        $leftEndYmd = Carbon::parse($applyFromYmd)->subDay()->format('Y-m-d');

        $leftData = [
            'partner_id' => $partnerId,
            'team_id' => (int) $slot->team_id,
            'location_id' => $slot->location_id !== null ? (int) $slot->location_id : null,
            'weekday' => (int) $slot->weekday,
            'time_start' => substr((string) $slot->time_start, 0, 5),
            'time_end' => substr((string) $slot->time_end, 0, 5),
            'date_start' => $slotStartYmd,
            'date_end' => $leftEndYmd,
            'is_enabled' => (bool) $slot->is_enabled,
        ];

        $overlapLeft = $this->overlapError($partnerId, (int) $slot->id, $leftData);
        if ($overlapLeft) {
            return response()->json(['errors' => $overlapLeft], 422);
        }

        $rightData = array_merge(
            ['partner_id' => $partnerId],
            $data,
            ['date_start' => $applyFromYmd]
        );

        $overlapRight = $this->overlapError($partnerId, null, $rightData);
        if ($overlapRight) {
            return response()->json(['errors' => $overlapRight], 422);
        }

        if ($this->identicalActiveSlotExists($partnerId, $rightData, null)) {
            return response()->json([
                'message' => 'Такой слот уже существует.',
                'errors' => [
                    'weekday' => ['Такой слот уже существует'],
                ],
            ], 422);
        }

        $rightSegment = [
            'team_id' => (int) $data['team_id'],
            'location_id' => $data['location_id'] ?? null,
            'weekday' => (int) $data['weekday'],
            'time_start' => (string) $data['time_start'],
            'time_end' => (string) $data['time_end'],
            'date_end' => (string) $data['date_end'],
            'is_enabled' => (bool) $data['is_enabled'],
        ];

        try {
            $newSlot = $mutationService->splitSlotForEdit($slot, $applyFromYmd, $rightSegment);
        } catch (QueryException $e) {
            $code = $e->errorInfo[1] ?? null;
            if ((int) $code === 1062) {
                return response()->json([
                    'message' => 'Ошибка сохранения',
                    'errors' => [
                        'weekday' => ['Такой слот уже существует'],
                    ],
                ], 422);
            }

            return response()->json([
                'message' => 'Ошибка сохранения',
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Изменения сохранены: период слота разделён.',
            'slot' => $newSlot->load(['team', 'location']),
            'truncated_slot_id' => $slot->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function scheduleIdentityChanged(TeamScheduleSlot $slot, array $data): bool
    {
        $locOld = $slot->location_id !== null ? (int) $slot->location_id : null;
        $locNew = isset($data['location_id']) && $data['location_id'] !== '' && $data['location_id'] !== null
            ? (int) $data['location_id']
            : null;

        $tOldStart = substr((string) $slot->time_start, 0, 5);
        $tOldEnd = substr((string) $slot->time_end, 0, 5);

        return (int) $slot->team_id !== (int) $data['team_id']
            || (int) $slot->weekday !== (int) $data['weekday']
            || $tOldStart !== substr((string) $data['time_start'], 0, 5)
            || $tOldEnd !== substr((string) $data['time_end'], 0, 5)
            || $locOld !== $locNew;
    }

    private function identicalActiveSlotExists(int $partnerId, array $data, ?int $ignoreId): bool
    {
        $q = TeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('team_id', (int) ($data['team_id'] ?? 0))
            ->where('weekday', (int) ($data['weekday'] ?? 0))
            ->whereRaw('TIME(time_start) = TIME(?)', [(string) ($data['time_start'] ?? '')])
            ->whereRaw('TIME(time_end) = TIME(?)', [(string) ($data['time_end'] ?? '')])
            ->whereDate('date_start', (string) ($data['date_start'] ?? ''))
            ->whereDate('date_end', (string) ($data['date_end'] ?? ''));

        $loc = isset($data['location_id']) && $data['location_id'] !== '' && $data['location_id'] !== null
            ? (int) $data['location_id']
            : null;
        if ($loc !== null && $loc > 0) {
            $q->where('location_id', $loc);
        } else {
            $q->whereNull('location_id');
        }

        if ($ignoreId !== null && $ignoreId > 0) {
            $q->where('id', '!=', $ignoreId);
        }

        return $q->exists();
    }

    /**
     * Возвращает массив ошибок в формате Laravel validation errors, либо null.
     *
     * Пересечения запрещены в пределах одной локации: в одном помещении не может быть
     * двух занятий одновременно. Разные локации — параллельные слоты допустимы.
     * Слоты без локации (null) считаются одной «виртуальной» площадкой.
     */
    private function overlapError(int $partnerId, ?int $ignoreId, array $data): ?array
    {
        $weekday = (int) ($data['weekday'] ?? 0);
        $timeStart = (string) ($data['time_start'] ?? '');
        $timeEnd = (string) ($data['time_end'] ?? '');
        $dateStart = (string) ($data['date_start'] ?? '');
        $dateEnd = (string) ($data['date_end'] ?? '');
        $isEnabled = (bool) ($data['is_enabled'] ?? true);
        $locationId = isset($data['location_id']) && $data['location_id'] !== '' && $data['location_id'] !== null
            ? (int) $data['location_id']
            : null;

        if (!$isEnabled) {
            return null;
        }

        if ($weekday < 1 || $weekday > 7 || $timeStart === '' || $timeEnd === '' || $dateStart === '' || $dateEnd === '') {
            return null;
        }

        $q = TeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->where('weekday', $weekday)
            ->when($locationId !== null && $locationId > 0, function ($query) use ($locationId) {
                $query->where('location_id', $locationId);
            }, function ($query) {
                $query->whereNull('location_id');
            })
            // Пересечение по датам: startA <= endB AND endA >= startB
            ->where('date_start', '<=', $dateEnd)
            ->where('date_end', '>=', $dateStart)
            // Пересечение по времени: startA < endB AND endA > startB
            ->where('time_start', '<', $timeEnd)
            ->where('time_end', '>', $timeStart);

        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }

        $exists = $q->exists();
        if (!$exists) {
            return null;
        }

        return [
            'weekday' => ['В этом объекте слот пересекается по времени с уже существующим занятием'],
        ];
    }
}

