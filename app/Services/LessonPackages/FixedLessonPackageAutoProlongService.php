<?php

declare(strict_types=1);

namespace App\Services\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\TeamScheduleCalendarService;
use App\Services\UserLessonPackageCalendarPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Автопролонгация классического fixed-назначения при достижении lessons_remaining = 0.
 */
final class FixedLessonPackageAutoProlongService
{
    private const NEXT_ANCHOR_SEARCH_DAYS = 400;

    public function __construct(
        private readonly TeamScheduleCalendarService $calendarService,
        private readonly UserLessonPackageCalendarPeriodService $calendarPeriodService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Вызывается после коммита списания, когда остаток впервые стал 0.
     */
    public function tryProlongAfterExhaustion(int $userLessonPackageId): void
    {
        try {
            $this->prolongOrDisable($userLessonPackageId);
        } catch (Throwable $e) {
            report($e);
            Log::error('FixedLessonPackageAutoProlongService failed unexpectedly', [
                'user_lesson_package_id' => $userLessonPackageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function prolongOrDisable(int $userLessonPackageId): void
    {
        /** @var UserLessonPackage|null $source */
        $source = UserLessonPackage::query()
            ->with(['lessonPackage', 'user:id,name,lastname,partner_id'])
            ->whereKey($userLessonPackageId)
            ->first();

        if ($source === null || ! $source->auto_prolong_enabled) {
            return;
        }

        if ((int) $source->lessons_remaining !== 0) {
            return;
        }

        $package = $source->lessonPackage;
        if ($package === null
            || (string) $package->schedule_type !== 'fixed'
            || $source->isFromSettingPrices()
        ) {
            $this->failAndDisable(
                $source,
                'Автопролонгация недоступна для этого типа назначения.',
            );

            return;
        }

        if ((int) $package->lessons_count < 1 || (int) $package->duration_days < 1) {
            $this->failAndDisable(
                $source,
                'У шаблона абонемента не задан объём занятий или срок действия.',
            );

            return;
        }

        $partnerId = (int) ($source->user?->partner_id ?? 0);
        if ($partnerId < 1) {
            $this->failAndDisable($source, 'Не удалось определить партнёра ученика.');

            return;
        }

        $slotRows = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', (int) $source->id)
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get(['id', 'team_schedule_slot_id', 'starts_at']);

        if ($slotRows->isEmpty()) {
            $this->failAndDisable(
                $source,
                'Нет записей в календаре для продолжения автопролонгации.',
            );

            return;
        }

        $lastDateRaw = (string) $slotRows->max('starts_at');
        $lastDate = CarbonImmutable::parse($lastDateRaw)->startOfDay();

        $slotIds = $slotRows->pluck('team_schedule_slot_id')->unique()->filter()->map(fn ($id) => (int) $id)->values();
        /** @var Collection<int, TeamScheduleSlot> $slots */
        $slots = TeamScheduleSlot::query()
            ->whereIn('id', $slotIds->all())
            ->where('partner_id', $partnerId)
            ->get()
            ->keyBy('id');

        if ($slots->isEmpty()) {
            $this->failAndDisable(
                $source,
                'Слоты текущего назначения не найдены в расписании школы.',
            );

            return;
        }

        $patterns = $this->patternsFromSlots($slots->values());
        if ($patterns->isEmpty()) {
            $this->failAndDisable($source, 'Не удалось восстановить шаблон слотов для автопролонгации.');

            return;
        }

        $teamIds = $slots->pluck('team_id')->unique()->filter()->map(fn ($id) => (int) $id)->values();
        if ($teamIds->count() !== 1) {
            $this->failAndDisable(
                $source,
                'Автопролонгация невозможна: занятия текущего назначения относятся к разным группам.',
            );

            return;
        }
        $teamId = (int) $teamIds->first();

        $locationIds = $slots->pluck('location_id')
            ->map(fn ($id) => $id !== null ? (int) $id : null)
            ->unique()
            ->values();
        $locationFilter = null;
        if ($locationIds->count() === 1 && $locationIds->first() !== null && (int) $locationIds->first() > 0) {
            $locationFilter = (int) $locationIds->first();
        }

        $next = $this->findNextAnchorAfter(
            $partnerId,
            $lastDate,
            $patterns,
            $teamId,
            $locationFilter,
        );

        if ($next === null) {
            $this->failAndDisable(
                $source,
                'Не найден следующий свободный слот для автопролонгации после '.$lastDate->format('d.m.Y').'.',
            );

            return;
        }

        /** @var CarbonImmutable $anchorDate */
        $anchorDate = $next['date'];
        /** @var TeamScheduleSlot $anchorSlot */
        $anchorSlot = $next['slot'];
        $lessonsNeeded = (int) $package->lessons_count;

        try {
            $createdNewId = null;

            DB::transaction(function () use (
                $source,
                $package,
                $partnerId,
                $teamId,
                $anchorDate,
                $anchorSlot,
                $patterns,
                $locationFilter,
                $lessonsNeeded,
                &$createdNewId,
            ) {
                /** @var UserLessonPackage $locked */
                $locked = UserLessonPackage::query()
                    ->whereKey((int) $source->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->auto_prolong_enabled || (int) $locked->lessons_remaining !== 0) {
                    return;
                }

                if ($locked->auto_prolonged_to_id !== null) {
                    return;
                }

                $newUlp = UserLessonPackage::query()->create([
                    'user_id' => (int) $locked->user_id,
                    'team_id' => $locked->team_id !== null ? (int) $locked->team_id : $teamId,
                    'lesson_package_id' => (int) $package->id,
                    'starts_at' => null,
                    'ends_at' => null,
                    'lessons_total' => $lessonsNeeded,
                    'lessons_remaining' => $lessonsNeeded,
                    'fee_amount_cents' => (int) ($locked->fee_amount_cents ?? 0),
                    'is_paid' => false,
                    'created_by' => null,
                    'auto_prolong_enabled' => false,
                    'auto_prolonged_from_id' => (int) $locked->id,
                ]);

                $this->calendarPeriodService->applyFirstCalendarAnchor($newUlp, $anchorDate);
                $newUlp->refresh();

                $periodEnd = CarbonImmutable::parse($newUlp->ends_at->format('Y-m-d'))->startOfDay();

                $this->calendarService->assertEveryFixedPatternOccurrenceResolvableInPeriod(
                    $anchorSlot,
                    $anchorDate,
                    $periodEnd,
                    $patterns,
                    $partnerId,
                    $teamId,
                    $locationFilter
                );

                $chain = $this->calendarService->buildFixedOccurrenceChain(
                    $partnerId,
                    $anchorDate,
                    $anchorSlot,
                    $patterns,
                    $lessonsNeeded,
                    $periodEnd,
                    $locationFilter
                );

                $this->calendarService->assertFixedChainHasNoTimeOverlapWithExistingUserLessons(
                    (int) $locked->user_id,
                    $partnerId,
                    $chain
                );

                foreach ($chain as $item) {
                    /** @var CarbonImmutable $date */
                    $date = $item['date'];
                    /** @var TeamScheduleSlot $slot */
                    $slot = $item['slot'];

                    UserTeamScheduleSlot::query()->create([
                        'partner_id' => $partnerId,
                        'user_id' => (int) $locked->user_id,
                        'user_lesson_package_id' => (int) $newUlp->id,
                        'team_schedule_slot_id' => (int) $slot->id,
                        'starts_at' => $date->toDateString(),
                        'ends_at' => $periodEnd->toDateString(),
                        'created_by' => null,
                    ]);
                }

                $linkedCount = count($chain);

                $newUlp->forceFill([
                    'auto_prolong_enabled' => true,
                ])->save();

                $locked->forceFill([
                    'auto_prolong_enabled' => false,
                    'auto_prolonged_to_id' => (int) $newUlp->id,
                ])->save();

                $createdNewId = [
                    'old_id' => (int) $locked->id,
                    'new_id' => (int) $newUlp->id,
                    'linked' => $linkedCount,
                    'user_id' => (int) $locked->user_id,
                ];
            });

            if (is_array($createdNewId)) {
                $this->recordSuccessAudit(
                    (int) $createdNewId['old_id'],
                    (int) $createdNewId['new_id'],
                    (int) $createdNewId['user_id'],
                    $partnerId,
                    (int) $createdNewId['linked'],
                );
            }
        } catch (InvalidArgumentException|Throwable $e) {
            $msg = trim($e->getMessage());
            if ($msg === '') {
                $msg = 'Не удалось выполнить автопролонгацию.';
            }

            $fresh = UserLessonPackage::query()->whereKey((int) $source->id)->first();
            $this->failAndDisable($fresh ?? $source, $msg);
        }
    }

    /**
     * @param  Collection<int, TeamScheduleSlot>  $slots
     * @return Collection<int, object{weekday: int, time_start: string, time_end: string}>
     */
    private function patternsFromSlots(Collection $slots): Collection
    {
        $seen = [];
        $patterns = collect();

        foreach ($slots as $slot) {
            $weekday = (int) $slot->weekday;
            $ts = substr((string) $slot->time_start, 0, 5);
            $te = substr((string) $slot->time_end, 0, 5);
            $key = $weekday.'|'.$ts.'|'.$te;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $patterns->push((object) [
                'weekday' => $weekday,
                'time_start' => $ts,
                'time_end' => $te,
            ]);
        }

        return $patterns;
    }

    /**
     * @param  Collection<int, object{weekday: int, time_start: string, time_end: string}>  $patterns
     * @return array{date: CarbonImmutable, slot: TeamScheduleSlot}|null
     */
    private function findNextAnchorAfter(
        int $partnerId,
        CarbonImmutable $lastOccupiedDate,
        Collection $patterns,
        int $teamId,
        ?int $locationFilter,
    ): ?array {
        $start = $lastOccupiedDate->addDay()->startOfDay();
        $end = $start->addDays(self::NEXT_ANCHOR_SEARCH_DAYS);

        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $dow = (int) $d->format('N');
            $dayCandidates = [];

            foreach ($patterns as $pattern) {
                if ((int) $pattern->weekday !== $dow) {
                    continue;
                }

                $slot = $this->calendarService->findMatchingTeamSlotForPatternOnDay(
                    $partnerId,
                    $d,
                    $pattern,
                    $teamId,
                    $locationFilter
                );

                if ($slot === null) {
                    continue;
                }

                if (! $this->calendarService->slotActiveOnDate($slot, $d)) {
                    continue;
                }

                if ($this->calendarService->isOccurrenceSkipped((int) $slot->id, $d)) {
                    continue;
                }

                $dayCandidates[] = [
                    'date' => $d,
                    'slot' => $slot,
                    'time' => substr((string) $slot->time_start, 0, 5),
                ];
            }

            if ($dayCandidates === []) {
                continue;
            }

            usort($dayCandidates, static fn (array $a, array $b): int => $a['time'] <=> $b['time']);

            return [
                'date' => $dayCandidates[0]['date'],
                'slot' => $dayCandidates[0]['slot'],
            ];
        }

        return null;
    }

    private function failAndDisable(UserLessonPackage $source, string $reason): void
    {
        DB::transaction(function () use ($source, $reason) {
            /** @var UserLessonPackage|null $locked */
            $locked = UserLessonPackage::query()
                ->whereKey((int) $source->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $locked->auto_prolong_enabled) {
                return;
            }

            $locked->forceFill(['auto_prolong_enabled' => false])->save();

            $partnerId = (int) (
                $locked->relationLoaded('user')
                    ? ($locked->user?->partner_id ?? 0)
                    : (User::query()->whereKey((int) $locked->user_id)->value('partner_id') ?? 0)
            );

            $this->auditLogger->record(
                AuditEvent::UserLessonPackageAutoProlongFailed,
                AuditContext::make(
                    'Автопролонгация не выполнена, флаг снят.'."\n"
                    .'Назначение #'.$locked->id."\n"
                    .'Причина: '.$reason
                )
                    ->withTarget($locked, 'Назначение #'.$locked->id)
                    ->withUserId((int) $locked->user_id)
                    ->withPartnerId($partnerId > 0 ? $partnerId : null)
                    ->withAuthorId(null)
            );
        });
    }

    private function recordSuccessAudit(
        int $oldId,
        int $newId,
        int $userId,
        int $partnerId,
        int $linkedCount,
    ): void {
        $new = UserLessonPackage::query()->whereKey($newId)->first();

        $context = AuditContext::make(
            'Автопролонгация фиксированного абонемента.'."\n"
            .'Исходное назначение #'.$oldId.' → новое #'.$newId."\n"
            .'Записей в календаре: '.$linkedCount
        )
            ->withUserId($userId)
            ->withPartnerId($partnerId)
            ->withAuthorId(null);

        if ($new !== null) {
            $context = $context->withTarget($new, 'Назначение #'.$newId);
        } else {
            $context = $context->withTargetReference(
                UserLessonPackage::class,
                $newId,
                'Назначение #'.$newId
            );
        }

        $this->auditLogger->record(AuditEvent::UserLessonPackageAutoProlonged, $context);
    }
}
