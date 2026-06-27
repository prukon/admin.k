<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\PartnerLegalEntity;
use App\Models\SportType;
use App\Models\Team;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        private readonly TeamLocationSyncService $teamLocationSync,
        private readonly AuditLogger $auditLogger,
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

        $locationProvided = array_key_exists('location_id', $data);
        $locationId = $locationProvided
            ? $this->teamLocationSync->resolveLocationIdForTeam((int) $partnerId, $data['location_id'] ?? null)
            : null;
        unset($data['location_id']);

        if ($locationProvided) {
            $data['location_id'] = $locationId;
        }

        if (array_key_exists('sport_type_id', $data)) {
            $data['sport_type_id'] = $this->resolveSportTypeIdForPartner(
                (int) $partnerId,
                $data['sport_type_id'] !== null && $data['sport_type_id'] !== ''
                    ? (int) $data['sport_type_id']
                    : null,
            );
        }

        if (array_key_exists('legal_entity_id', $data)) {
            $data['legal_entity_id'] = $this->resolveLegalEntityIdForPartner(
                (int) $partnerId,
                $data['legal_entity_id'] !== null && $data['legal_entity_id'] !== ''
                    ? (int) $data['legal_entity_id']
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

        $partnerId = (int) ($team->partner_id ?? $this->partnerContext->partnerId() ?? 0);

        $locationProvided = array_key_exists('location_id', $data);
        $locationId = $locationProvided
            ? $this->teamLocationSync->resolveLocationIdForTeam($partnerId, $data['location_id'] ?? null)
            : null;
        unset($data['location_id']);

        if ($locationProvided) {
            $data['location_id'] = $locationId;
        }

        if (array_key_exists('sport_type_id', $data)) {
            $data['sport_type_id'] = $this->resolveSportTypeIdForPartner(
                $partnerId,
                $data['sport_type_id'] !== null && $data['sport_type_id'] !== ''
                    ? (int) $data['sport_type_id']
                    : null,
            );
        }

        if (array_key_exists('legal_entity_id', $data)) {
            $data['legal_entity_id'] = $this->resolveLegalEntityIdForPartner(
                $partnerId,
                $data['legal_entity_id'] !== null && $data['legal_entity_id'] !== ''
                    ? (int) $data['legal_entity_id']
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
                "Название: %s\nДлительность по умолчанию (мин): %s\nСтоимость в месяц: %s\nДни недели: %s\nСортировка: %s\nАктивность: %s",
                $team->title ?? '-',
                $data['default_duration_minutes'] ?? '-',
                $data['month_price'] ?? '-',
                implode(', ', $weekdaysFormatted) ?: '-',
                $data['order_by'] ?? '-',
                !empty($data['is_enabled']) ? 'Да' : 'Нет'
            );

            $this->auditLogger->record(
                AuditEvent::TeamCreated,
                AuditContext::make($description)
                    ->withTarget($team, $team->title)
                    ->withAuthorId($authorId)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(Carbon::now())
            );

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

    private function resolveLegalEntityIdForPartner(int $partnerId, ?int $legalEntityId): ?int
    {
        if ($legalEntityId === null || $legalEntityId <= 0) {
            return null;
        }

        $isValid = PartnerLegalEntity::query()
            ->whereKey($legalEntityId)
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->exists();

        if (! $isValid) {
            throw ValidationException::withMessages([
                'legal_entity_id' => ['Выберите активное юр. лицо из списка текущего партнёра'],
            ]);
        }

        return $legalEntityId;
    }
}