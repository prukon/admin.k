<?php

namespace App\Services;

use App\Models\SportType;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\MyLog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\DataTables;
use App\Services\PartnerContext; // ✅ НОВОЕ

class TeamService
{
    /** @var PartnerContext */
    protected PartnerContext $partnerContext; // ✅ НОВОЕ

    public function __construct(
        PartnerContext $partnerContext,
        private readonly TeamTrainerSyncService $teamTrainerSync,
        private readonly LocationTeamSyncService $locationTeamSync,
    ) {
        $this->partnerContext = $partnerContext;
    }

    public function store($data)
    {
        // ✅ ЗАМЕНА app('current_partner') и хардкода partner_id=1
        $partnerId = $this->partnerContext->partnerId();

        if (!$partnerId) {
            // Здесь лучше упасть, чем создавать группу "в никуда"
            throw new \RuntimeException('Текущий партнёр не определён');
        }

        $data['order_by']  = $data['order_by'] ?? 10;
        $data['partner_id'] = $partnerId;

        $weekdays = $data['weekdays'] ?? [];
        unset($data['weekdays']);

        $trainerProvided = array_key_exists('trainer_profile_id', $data);
        $trainerProfileId = $trainerProvided
            ? ($data['trainer_profile_id'] !== null && $data['trainer_profile_id'] !== ''
                ? (int) $data['trainer_profile_id']
                : null)
            : null;
        unset($data['trainer_profile_id']);

        $locationsProvided = array_key_exists('location_ids', $data);
        $locationIds = $locationsProvided ? (array) ($data['location_ids'] ?? []) : [];
        unset($data['location_ids']);

        if (array_key_exists('sport_type_id', $data)) {
            $data['sport_type_id'] = $this->resolveSportTypeIdForPartner(
                (int) $partnerId,
                $data['sport_type_id'] !== null && $data['sport_type_id'] !== ''
                    ? (int) $data['sport_type_id']
                    : null,
            );
        }

        $team = Team::create($data);

        if (!empty($weekdays) && $team->id) {
            $team->weekdays()->sync($weekdays);
        }

        if ($trainerProvided) {
            $this->teamTrainerSync->syncTrainerForTeam($team, $trainerProfileId);
        }

        if ($locationsProvided) {
            $this->locationTeamSync->syncLocationsForTeam($team, $locationIds);
        }

        return $team; // Возвращаем созданную команду
    }

    public function update($team, $data)
    {
        $weekdaysProvided = array_key_exists('weekdays', $data);
        $weekdays = $data['weekdays'] ?? [];
        unset($data['weekdays']);

        $trainerProvided = array_key_exists('trainer_profile_id', $data);
        $trainerProfileId = $trainerProvided
            ? ($data['trainer_profile_id'] !== null && $data['trainer_profile_id'] !== ''
                ? (int) $data['trainer_profile_id']
                : null)
            : null;
        unset($data['trainer_profile_id']);

        $locationsProvided = array_key_exists('location_ids', $data);
        $locationIds = $locationsProvided ? (array) ($data['location_ids'] ?? []) : [];
        unset($data['location_ids']);

        if (array_key_exists('sport_type_id', $data)) {
            $partnerId = (int) ($team->partner_id ?? $this->partnerContext->partnerId() ?? 0);
            $data['sport_type_id'] = $this->resolveSportTypeIdForPartner(
                $partnerId,
                $data['sport_type_id'] !== null && $data['sport_type_id'] !== ''
                    ? (int) $data['sport_type_id']
                    : null,
            );
        }

        // Обновляем данные команды, проверяем, что команда действительно сохранена
        if ($team->update($data) && $team->exists && $team->id) {
            if ($weekdaysProvided) {
                $team->weekdays()->sync($weekdays);
            }
            if ($trainerProvided) {
                $this->teamTrainerSync->syncTrainerForTeam($team, $trainerProfileId);
            }
            if ($locationsProvided) {
                $this->locationTeamSync->syncLocationsForTeam($team, $locationIds);
            }
        } else {
            throw new \Exception("Ошибка: команда не обновлена или team_id не существует.");
        }
    }

    public function delete($team)
    {
        $team->delete();
    }

    //    Создание группы
    public function storeWithLogging(array $data, int $authorId): Team
    {
        $team = DB::transaction(function () use ($data, $authorId) {
            // ✅ НОВОЕ: централизованный доступ к партнёру
            $partnerId = $this->partnerContext->partnerId();

            if (!$partnerId) {
                throw new \RuntimeException('Текущий партнёр не определён');
            }

            $team = $this->store($data);

            $weekdaysMap = [
                1 => 'пн',
                2 => 'вт',
                3 => 'ср',
                4 => 'чт',
                5 => 'пт',
                6 => 'сб',
                7 => 'вс',
            ];

            $weekdaysFormatted = [];
            if (isset($data['weekdays']) && is_array($data['weekdays'])) {
                $weekdaysFormatted = array_map(function ($day) use ($weekdaysMap) {
                    return $weekdaysMap[$day] ?? $day; // Если дня нет в мапе, вернётся исходное значение
                }, $data['weekdays']);
            }

            $description = sprintf(
                "Название: %s\nДлительность по умолчанию (мин): %s\nДни недели: %s\nСортировка: %s\nАктивность: %s",
                $team->title ?? '-',
                $data['default_duration_minutes'] ?? '-',
                implode(', ', $weekdaysFormatted) ?: '-',
                $data['order_by'] ?? '-',
                !empty($data['is_enabled']) ? 'Да' : 'Нет'
            );

            MyLog::create([
                'type'         => 3,
                'action'       => 31,
                'author_id'    => $authorId,
                'partner_id'   => $partnerId,
                'target_type'  => \App\Models\Team::class, // ✅
                'target_id'    => $team->id,               // ✅
                'target_label' => $team->title,            // ✅
                'description'  => $description,
                'created_at'   => Carbon::now(),
            ]);

            return $team;
        });

        return $team;
    }

    /**
     * Проверяет, что вид спорта принадлежит партнёру и активен (защита помимо FormRequest).
     */
    private function resolveSportTypeIdForPartner(int $partnerId, ?int $sportTypeId): ?int
    {
        if ($sportTypeId === null || $sportTypeId <= 0) {
            return null;
        }

        $isValid = SportType::query()
            ->whereKey($sportTypeId)
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->exists();

        if (! $isValid) {
            throw ValidationException::withMessages([
                'sport_type_id' => ['Выберите активный вид спорта из списка текущего партнёра'],
            ]);
        }

        return $sportTypeId;
    }
}