<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditEvent;
use App\Exceptions\TeamScheduleSlotConflictException;
use App\Models\TeamScheduleSlot;
use App\Models\TeamScheduleSlotException;
use App\Models\UserTeamScheduleSlot;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Операции над слотом расписания: точечное исключение даты, усечение периода, проверки привязок абонементов.
 */
final class TeamScheduleSlotMutationService
{
    public const LOG_TYPE_SCHEDULE_SLOTS = 46;

    /** @deprecated Используйте {@see AuditEvent} — оставлено для обратной совместимости тестов. */
    public const LOG_ACTION_SKIP = 461;

    public function __construct(
        private readonly TeamScheduleCalendarService $calendarService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Одноразово скрыть окно слота в конкретную дату (таблица исключений).
     *
     * @throws TeamScheduleSlotConflictException
     */
    public function skipOccurrence(TeamScheduleSlot $slot, CarbonImmutable $occurrenceDate): void
    {
        $this->assertWeekdayMatches($slot, $occurrenceDate);

        if (! $this->calendarService->slotActiveOnDate($slot, $occurrenceDate)) {
            throw new TeamScheduleSlotConflictException(
                'Слот недействителен на выбранную дату.',
                []
            );
        }

        $conflicts = $this->loadConflicts(
            $slot,
            fn ($q) => $q->whereDate('starts_at', $occurrenceDate->toDateString())
        );
        if ($conflicts !== []) {
            throw new TeamScheduleSlotConflictException(
                'Нельзя отменить занятие: на эту дату есть привязки абонементов. Сначала снимите их.',
                $conflicts
            );
        }

        $existing = TeamScheduleSlotException::withTrashed()
            ->where('team_schedule_slot_id', $slot->id)
            ->whereDate('occurrence_date', $occurrenceDate->toDateString())
            ->first();

        DB::transaction(function () use ($slot, $occurrenceDate, $existing): void {
            $changed = false;
            if ($existing !== null) {
                if ($existing->trashed()) {
                    $existing->restore();
                    $changed = true;
                }
            } else {
                TeamScheduleSlotException::query()->create([
                    'partner_id' => (int) $slot->partner_id,
                    'team_schedule_slot_id' => (int) $slot->id,
                    'occurrence_date' => $occurrenceDate->toDateString(),
                ]);
                $changed = true;
            }

            if (! $changed) {
                return;
            }

            $this->recordSlotLog(
                AuditEvent::ScheduleSlotOccurrenceSkipped,
                $slot,
                sprintf(
                    'Точечное исключение даты %s для слота #%d.',
                    $occurrenceDate->toDateString(),
                    $slot->id
                ),
            );
        });
    }

    /**
     * Убрать слот с выбранной даты occurrence и все последующие повторения (только эта запись правила).
     *
     * @throws TeamScheduleSlotConflictException
     */
    public function truncateFromOccurrenceDate(TeamScheduleSlot $slot, CarbonImmutable $anchorDate): void
    {
        $this->assertWeekdayMatches($slot, $anchorDate);

        if (! $this->calendarService->slotActiveOnDate($slot, $anchorDate)) {
            throw new TeamScheduleSlotConflictException(
                'Слот недействителен на выбранную дату.',
                []
            );
        }

        $conflicts = $this->loadConflicts(
            $slot,
            fn ($q) => $q->whereDate('starts_at', '>=', $anchorDate->toDateString())
        );
        if ($conflicts !== []) {
            throw new TeamScheduleSlotConflictException(
                'Нельзя усечь расписание: есть привязки абонементов на удаляемые даты. Сначала снимите их.',
                $conflicts
            );
        }

        $oldEnd = Carbon::parse((string) $slot->date_end)->startOfDay();
        $newEnd = $anchorDate->startOfDay()->subDay();

        DB::transaction(function () use ($slot, $newEnd, $oldEnd, $anchorDate): void {
            $slotStart = Carbon::parse((string) $slot->date_start)->startOfDay();

            if ($newEnd->lt($slotStart)) {
                $slot->delete();

                $this->recordSlotLog(
                    AuditEvent::ScheduleSlotTruncated,
                    $slot,
                    sprintf(
                        'Усечение с %s: период опустел, слот #%d помечен удалённым (ранее date_end=%s).',
                        $anchorDate->toDateString(),
                        $slot->id,
                        $oldEnd->toDateString()
                    ),
                );

                return;
            }

            $slot->date_end = $newEnd->toDateString();
            $slot->save();

            TeamScheduleSlotException::query()
                ->where('team_schedule_slot_id', $slot->id)
                ->whereDate('occurrence_date', '>', $newEnd->toDateString())
                ->get()
                ->each
                ->delete();

            $this->recordSlotLog(
                AuditEvent::ScheduleSlotTruncated,
                $slot,
                sprintf(
                    'Усечение с %s: date_end %s → %s.',
                    $anchorDate->toDateString(),
                    $oldEnd->toDateString(),
                    $newEnd->toDateString()
                ),
            );
        });
    }

    /**
     * Снять слот с учёта (soft-delete), если нет привязок.
     *
     * @throws TeamScheduleSlotConflictException
     */
    public function softDeleteSlot(TeamScheduleSlot $slot): void
    {
        $conflicts = $this->loadConflicts($slot, fn ($q) => $q);
        if ($conflicts !== []) {
            throw new TeamScheduleSlotConflictException(
                'Нельзя удалить слот: есть привязки абонементов. Сначала снимите их.',
                $conflicts
            );
        }

        DB::transaction(function () use ($slot): void {
            $slot->delete();

            $this->recordSlotLog(
                AuditEvent::ScheduleSlotDeleted,
                $slot,
                sprintf('Слот #%d помечен удалённым.', $slot->id),
            );
        });
    }

    /**
     * Проверка при ручном изменении периода в форме редактирования.
     *
     * @throws TeamScheduleSlotConflictException
     */
    /**
     * Изменение дня недели, времени, группы или локации на весь период — только если нет привязок.
     *
     * @throws TeamScheduleSlotConflictException
     */
    public function assertNoAssignmentsWhenChangingScheduleIdentity(TeamScheduleSlot $slot): void
    {
        $conflicts = $this->loadConflicts($slot, fn ($q) => $q);
        if ($conflicts !== []) {
            throw new TeamScheduleSlotConflictException(
                'Нельзя изменить день недели, время, группу или локацию: на слот есть привязки абонементов. Сначала снимите их.',
                $conflicts
            );
        }
    }

    /**
     * Разделить слот по дате E: левая часть усечена до E−1, правая — новая запись с полями из формы.
     *
     * @param  array{
     *     team_id: int,
     *     location_id?: int|null,
     *     weekday: int,
     *     time_start: string,
     *     time_end: string,
     *     date_end: string,
     *     is_enabled: bool
     * }  $rightSegment
     *
     * @throws TeamScheduleSlotConflictException
     */
    public function splitSlotForEdit(TeamScheduleSlot $slot, string $applyFromYmd, array $rightSegment): TeamScheduleSlot
    {
        $e = Carbon::parse($applyFromYmd)->startOfDay();
        $slotStart = Carbon::parse((string) $slot->date_start)->startOfDay();
        $slotEnd = Carbon::parse((string) $slot->date_end)->startOfDay();

        if ($e->lte($slotStart)) {
            throw new TeamScheduleSlotConflictException(
                'Внутренняя ошибка: разделение слота возможно только если дата изменений позже начала периода.',
                []
            );
        }

        if ($e->gt($slotEnd)) {
            throw new TeamScheduleSlotConflictException(
                'Дата начала изменений не может быть позже окончания периода слота.',
                []
            );
        }

        $conflicts = $this->loadConflicts(
            $slot,
            fn ($q) => $q->whereDate('starts_at', '>=', $e->toDateString())
        );
        if ($conflicts !== []) {
            throw new TeamScheduleSlotConflictException(
                'Нельзя применить изменения с выбранной даты: на новый период уже есть привязки абонементов. Сначала снимите их.',
                $conflicts
            );
        }

        $leftEnd = $e->copy()->subDay();
        $locationId = isset($rightSegment['location_id']) && $rightSegment['location_id'] !== '' && $rightSegment['location_id'] !== null
            ? (int) $rightSegment['location_id']
            : null;

        return DB::transaction(function () use ($slot, $e, $leftEnd, $rightSegment, $locationId): TeamScheduleSlot {
            $slot->refresh();

            $oldEndStr = Carbon::parse((string) $slot->date_end)->toDateString();
            $slot->date_end = $leftEnd->toDateString();
            $slot->save();

            TeamScheduleSlotException::query()
                ->where('team_schedule_slot_id', $slot->id)
                ->whereDate('occurrence_date', '>=', $e->toDateString())
                ->get()
                ->each
                ->delete();

            $new = TeamScheduleSlot::query()->create([
                'partner_id' => (int) $slot->partner_id,
                'team_id' => (int) $rightSegment['team_id'],
                'location_id' => $locationId,
                'weekday' => (int) $rightSegment['weekday'],
                'time_start' => (string) $rightSegment['time_start'],
                'time_end' => (string) $rightSegment['time_end'],
                'date_start' => $e->toDateString(),
                'date_end' => (string) $rightSegment['date_end'],
                'is_enabled' => (bool) ($rightSegment['is_enabled'] ?? true),
            ]);

            $this->recordSlotLog(
                AuditEvent::ScheduleSlotSplitEdited,
                $new,
                sprintf(
                    'Разделение слота #%d по дате %s: левая часть до %s, новый слот #%d до %s (ранее конец периода был %s).',
                    $slot->id,
                    $e->toDateString(),
                    $leftEnd->toDateString(),
                    $new->id,
                    (string) $rightSegment['date_end'],
                    $oldEndStr
                ),
            );

            return $new;
        });
    }

    public function assertUpdateDoesNotOrphanAssignments(
        TeamScheduleSlot $slot,
        string $newDateStartYmd,
        string $newDateEndYmd,
    ): void {
        $oldStart = Carbon::parse((string) $slot->date_start)->startOfDay();
        $oldEnd = Carbon::parse((string) $slot->date_end)->startOfDay();
        $newStart = Carbon::parse($newDateStartYmd)->startOfDay();
        $newEnd = Carbon::parse($newDateEndYmd)->startOfDay();

        $conflicts = [];

        if ($newStart->gt($oldStart)) {
            $conflicts = array_merge($conflicts, $this->loadConflicts(
                $slot,
                fn ($q) => $q->whereDate('starts_at', '>=', $oldStart->toDateString())
                    ->whereDate('starts_at', '<', $newStart->toDateString())
            ));
        }

        if ($newEnd->lt($oldEnd)) {
            $conflicts = array_merge($conflicts, $this->loadConflicts(
                $slot,
                fn ($q) => $q->whereDate('starts_at', '>', $newEnd->toDateString())
                    ->whereDate('starts_at', '<=', $oldEnd->toDateString())
            ));
        }

        if ($conflicts !== []) {
            throw new TeamScheduleSlotConflictException(
                'Нельзя сохранить период: есть привязки абонементов на исключаемые даты. Сначала снимите их.',
                $conflicts
            );
        }
    }

    /**
     * @param callable(\Illuminate\Database\Eloquent\Builder): mixed $scope
     * @return list<array{
     *     user_team_schedule_slot_id: int,
     *     user_id: int,
     *     user_label: string,
     *     occurrence_date: string,
     *     user_lesson_package_id: int|null
     * }>
     */
    private function loadConflicts(TeamScheduleSlot $slot, callable $scope): array
    {
        $q = UserTeamScheduleSlot::query()
            ->with(['user:id,name,lastname'])
            ->where('team_schedule_slot_id', $slot->id);

        $scope($q);

        return $q->orderBy('starts_at')
            ->orderBy('user_id')
            ->get()
            ->map(fn (UserTeamScheduleSlot $row) => $this->mapConflictRow($row))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     user_team_schedule_slot_id: int,
     *     user_id: int,
     *     user_label: string,
     *     occurrence_date: string,
     *     user_lesson_package_id: int|null
     * }
     */
    private function mapConflictRow(UserTeamScheduleSlot $row): array
    {
        $user = $row->user;
        $label = $user
            ? trim((string) ($user->lastname ?? '').' '.(string) ($user->name ?? ''))
            : '';
        if ($label === '') {
            $label = 'Ученик #'.(int) $row->user_id;
        }

        $dateStr = $row->starts_at instanceof CarbonInterface
            ? $row->starts_at->format('Y-m-d')
            : (string) $row->starts_at;

        return [
            'user_team_schedule_slot_id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'user_label' => $label,
            'occurrence_date' => $dateStr,
            'user_lesson_package_id' => $row->user_lesson_package_id !== null ? (int) $row->user_lesson_package_id : null,
        ];
    }

    private function assertWeekdayMatches(TeamScheduleSlot $slot, CarbonImmutable $occurrenceDate): void
    {
        $dow = (int) $occurrenceDate->format('N');
        if ((int) $slot->weekday !== $dow) {
            throw new TeamScheduleSlotConflictException(
                'Дата не соответствует дню недели выбранного слота.',
                []
            );
        }
    }

    private function slotLabel(TeamScheduleSlot $slot): string
    {
        return sprintf('Слот #%d', $slot->id);
    }

    private function recordSlotLog(AuditEvent $event, TeamScheduleSlot $slot, string $description): void
    {
        $this->auditLogger->record(
            $event,
            AuditContext::make($description)
                ->withTarget($slot, $this->slotLabel($slot))
                ->withCreatedAt(now())
        );
    }
}
