<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreTeamScheduleSlotRequest;
use App\Http\Requests\Admin\UpdateTeamScheduleSlotRequest;
use App\Models\Location;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Services\PartnerContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class TeamScheduleSlotController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    public function index()
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
            ->orderBy('title')
            ->get();

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

        return view('admin.teamScheduleSlots.index', compact('slots', 'teams', 'locations', 'weekdays'));
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
        if ($locationId) {
            $location = Location::query()
                ->where('partner_id', $partnerId)
                ->where('id', $locationId)
                ->first();

            if (!$location) {
                return response()->json(['message' => 'Локация не найдена'], 422);
            }
        }

        $data['partner_id'] = $partnerId;
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? true);

        $overlapError = $this->overlapError($partnerId, null, $data);
        if ($overlapError) {
            return response()->json(['errors' => $overlapError], 422);
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
        if ($locationId) {
            $location = Location::query()
                ->where('partner_id', $partnerId)
                ->where('id', $locationId)
                ->first();

            if (!$location) {
                return response()->json(['message' => 'Локация не найдена'], 422);
            }
        }

        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);

        $overlapError = $this->overlapError($partnerId, (int) $slot->id, array_merge(
            ['partner_id' => $partnerId],
            $data
        ));
        if ($overlapError) {
            return response()->json(['errors' => $overlapError], 422);
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

    public function destroy(Request $request, TeamScheduleSlot $slot)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $slot->partner_id !== $partnerId) {
            abort(404);
        }

        $slot->delete();

        return response()->json(['message' => 'Слот удалён']);
    }

    /**
     * Возвращает массив ошибок в формате Laravel validation errors, либо null.
     *
     * Запрещаем любые пересечения по времени/дате в рамках партнёра и дня недели,
     * вне зависимости от группы/локации.
     */
    private function overlapError(int $partnerId, ?int $ignoreId, array $data): ?array
    {
        $weekday = (int) ($data['weekday'] ?? 0);
        $timeStart = (string) ($data['time_start'] ?? '');
        $timeEnd = (string) ($data['time_end'] ?? '');
        $dateStart = (string) ($data['date_start'] ?? '');
        $dateEnd = (string) ($data['date_end'] ?? '');
        $isEnabled = (bool) ($data['is_enabled'] ?? true);

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
            'weekday' => ['Слот пересекается с уже существующим расписанием школы'],
        ];
    }
}

