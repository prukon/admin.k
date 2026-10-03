<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Enums\AuditEvent;
use App\Models\LessonOccurrenceStatus;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\LessonPackages\UserLessonOccurrenceStatusService;
use App\Services\Postpay\PostpayJournalService;
use App\Services\Postpay\PostpayUsersPriceSync;
use App\Support\Money;
use App\Support\ScheduleOccurrenceTrainerIds;
use App\Support\UserPriceTeamMembership;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Массовая постановка занятия в пустые ячейки одной группы и одной даты.
 * Путь каждого ученика совпадает с кликом по пустой ячейке: предоплата с остатком, иначе постоплата.
 */
final class JournalBulkEmptyLessonPlacementService
{
    public function __construct(
        private readonly ScheduleJournalMonthService $journalMonthService,
        private readonly JournalFlexibleAbonementPlacementService $flexiblePlacementService,
        private readonly PostpayJournalService $postpayJournal,
        private readonly PostpayUsersPriceSync $postpaySync,
        private readonly UserLessonOccurrenceStatusService $occurrenceStatusService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  list<int>  $userIds
     * @param  list<int>  $trainerProfileIds
     * @return array{
     *     placed: list<array<string, mixed>>,
     *     failed: list<array{user_id: int, message: string}>
     * }
     */
    public function place(
        int $partnerId,
        Team $team,
        string $occurrenceDateYmd,
        array $userIds,
        LessonOccurrenceStatus $status,
        array $trainerProfileIds,
        ?int $authorId,
    ): array {
        $users = User::query()
            ->whereIn('id', $userIds)
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->withSystemRoleUser()
            ->get()
            ->keyBy(fn (User $user) => (int) $user->id);

        $billingMonth = Carbon::parse($occurrenceDateYmd)->startOfMonth()->toDateString();
        $flexibleByUser = $this->journalMonthService->flexibleAssignableByUserForBillingMonth(
            $partnerId,
            $userIds,
            $billingMonth,
            (string) $team->id,
        );

        $isAttended = (int) $status->id === (int) (LessonOccurrenceStatus::attendedIdForPartner($partnerId) ?? 0);
        $trainerName = $isAttended ? $this->trainerName($trainerProfileIds) : null;
        $appliedTrainerIds = $isAttended ? $trainerProfileIds : [];

        $placed = [];
        $failed = [];

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            $user = $users->get($userId);
            if (! $user instanceof User) {
                $failed[] = ['user_id' => $userId, 'message' => 'Ученик не найден.'];

                continue;
            }

            try {
                $result = $this->placeOne(
                    $partnerId,
                    $user,
                    $team,
                    $occurrenceDateYmd,
                    $flexibleByUser[$userId] ?? [],
                    $status,
                    $appliedTrainerIds,
                    $authorId,
                    $trainerName,
                    $isAttended,
                );
                $placed[] = $result;
            } catch (DomainException|InvalidArgumentException $e) {
                $failed[] = ['user_id' => $userId, 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                report($e);
                $failed[] = ['user_id' => $userId, 'message' => 'Не удалось поставить занятие.'];
            }
        }

        return [
            'placed' => $placed,
            'failed' => $failed,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $flexibleRows
     * @param  list<int>  $trainerProfileIds
     * @return array<string, mixed>
     */
    private function placeOne(
        int $partnerId,
        User $user,
        Team $team,
        string $occurrenceDateYmd,
        array $flexibleRows,
        LessonOccurrenceStatus $status,
        array $trainerProfileIds,
        ?int $authorId,
        ?string $trainerName,
        bool $isAttended,
    ): array {
        if (! UserPriceTeamMembership::studentBelongsToTeam($user, (int) $team->id, $partnerId)) {
            throw new InvalidArgumentException('Ученик не состоит в выбранной группе.');
        }

        if ($this->teamDayAlreadyHasLesson($partnerId, (int) $user->id, (int) $team->id, $occurrenceDateYmd)) {
            throw new InvalidArgumentException('На эту дату занятие уже стоит.');
        }

        $flexible = $this->pickFlexibleWithRemaining($flexibleRows, $occurrenceDateYmd);
        if ($flexible !== null) {
            $result = $this->placePrepaid(
                $partnerId,
                $user,
                $team,
                $flexible,
                $occurrenceDateYmd,
                $status,
                $trainerProfileIds,
                $authorId,
            );
            $result['user_id'] = (int) $user->id;
            $result['billing'] = 'prepaid';
            $result['created'] = true;
            $result['trainer_name'] = $isAttended ? $trainerName : null;
            $result['trainer_profile_ids'] = $isAttended ? $trainerProfileIds : [];

            return $result;
        }

        $postpay = $this->postpayJournal->findPostpayUserPrice((int) $user->id, (int) $team->id, $occurrenceDateYmd);
        if ($postpay !== null && ! $postpay->effective_is_paid) {
            $result = $this->placePostpay(
                $partnerId,
                $user,
                $team,
                $occurrenceDateYmd,
                $status,
                $trainerProfileIds,
                $authorId,
            );
            $result['user_id'] = (int) $user->id;
            $result['billing'] = 'postpay';
            $result['created'] = true;
            $result['trainer_name'] = $isAttended ? $trainerName : null;
            $result['trainer_profile_ids'] = $isAttended ? $trainerProfileIds : [];

            return $result;
        }

        if ($flexibleRows !== []) {
            throw new InvalidArgumentException('В абонементе не осталось занятий.');
        }

        if ($postpay !== null && $postpay->effective_is_paid) {
            throw new DomainException(PostpayJournalService::LOCKED_MESSAGE);
        }

        throw new InvalidArgumentException('На этот месяц не установлен абонемент.');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function pickFlexibleWithRemaining(array $rows, string $occurrenceDateYmd): ?array
    {
        foreach ($rows as $row) {
            if ((int) ($row['slots_remaining'] ?? 0) < 1) {
                continue;
            }
            $start = $row['starts_at'] ?? null;
            $end = $row['ends_at'] ?? null;
            if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
                continue;
            }
            if ($occurrenceDateYmd < $start || $occurrenceDateYmd > $end) {
                continue;
            }

            return $row;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $flexible
     * @param  list<int>  $trainerProfileIds
     * @return array<string, mixed>
     */
    private function placePrepaid(
        int $partnerId,
        User $user,
        Team $team,
        array $flexible,
        string $occurrenceDateYmd,
        LessonOccurrenceStatus $status,
        array $trainerProfileIds,
        ?int $authorId,
    ): array {
        $ulp = UserLessonPackage::query()
            ->with('lessonPackage')
            ->whereKey((int) $flexible['id'])
            ->first();
        if (! $ulp) {
            throw new InvalidArgumentException('Абонемент не найден.');
        }

        $occurrenceDate = CarbonImmutable::createFromFormat('Y-m-d', $occurrenceDateYmd)->startOfDay();
        $result = $this->flexiblePlacementService->place(
            $partnerId,
            $user,
            $ulp,
            $team,
            $occurrenceDate,
            $status,
            $authorId,
            $trainerProfileIds[0] ?? null,
            null,
            $trainerProfileIds,
        );

        $this->postpaySync->syncAfterOccurrenceChange(
            $partnerId,
            (int) $user->id,
            $occurrenceDateYmd,
            (int) $team->id,
        );

        $this->auditLogger->record(
            AuditEvent::ScheduleFlexibleLinked,
            AuditContext::make(sprintf(
                'Журнал: абонемент предоплаты #%d — занятие на %s; ученик: %s; статус: %s; остаток занятий: %d',
                (int) $ulp->id,
                $occurrenceDate->format('d.m.Y'),
                $user->full_name,
                $status->title,
                (int) $result['slots_remaining'],
            ))
                ->withUser($user)
                ->withTargetReference('App\Models\UserLessonPackage', (int) $ulp->id, $user->full_name)
                ->withPartnerId($partnerId)
                ->withCreatedAt(now())
        );

        return $result;
    }

    /**
     * @param  list<int>  $trainerProfileIds
     * @return array<string, mixed>
     */
    private function placePostpay(
        int $partnerId,
        User $user,
        Team $team,
        string $occurrenceDateYmd,
        LessonOccurrenceStatus $status,
        array $trainerProfileIds,
        ?int $authorId,
    ): array {
        $utssId = 0;

        DB::transaction(function () use (
            $partnerId,
            $user,
            $team,
            $occurrenceDateYmd,
            $status,
            $trainerProfileIds,
            $authorId,
            &$utssId,
        ): void {
            $utss = $this->postpayJournal->ensureOccurrence(
                $partnerId,
                $user,
                (int) $team->id,
                $occurrenceDateYmd,
                $authorId,
            );
            $this->postpayJournal->assertOccurrenceEditable((int) $user->id, (int) $team->id, $occurrenceDateYmd);

            $this->occurrenceStatusService->apply(
                $partnerId,
                (int) $user->id,
                (int) $utss->team_schedule_slot_id,
                $occurrenceDateYmd,
                null,
                $status,
                $authorId,
                $trainerProfileIds[0] ?? null,
                null,
                $trainerProfileIds,
            );

            $utssId = (int) $utss->id;
        });

        $this->postpaySync->syncAfterOccurrenceChange(
            $partnerId,
            (int) $user->id,
            $occurrenceDateYmd,
            (int) $team->id,
        );

        $formattedDate = Carbon::parse($occurrenceDateYmd)->format('d.m.Y');
        $this->auditLogger->record(
            AuditEvent::ScheduleDayUpdated,
            AuditContext::make(sprintf(
                'Дата: "%s", Имя: "%s",%sСтатус до: "%s", Статус после: "%s",%sТренер до: "%s", Тренер после: "%s",%sКомментарий: "%s"',
                $formattedDate,
                $user->full_name,
                "\n",
                'не было',
                $status->title,
                "\n",
                'Без тренера',
                $trainerProfileIds === [] ? 'Без тренера' : ($this->trainerName($trainerProfileIds) ?? 'Без тренера'),
                "\n",
                '',
            ))
                ->withUser($user)
                ->withTargetReference('App\Models\UserTeamScheduleSlot', $utssId, $user->full_name)
                ->withPartnerId($partnerId)
                ->withCreatedAt(now())
        );

        return [
            'utss_id' => $utssId,
            'occurrence_date' => $occurrenceDateYmd,
            'comment' => null,
            'package_hover' => $this->postpayHover((int) $user->id, $occurrenceDateYmd, (int) $team->id),
            'status' => [
                'id' => (int) $status->id,
                'title' => (string) $status->title,
                'icon' => $status->icon !== null && $status->icon !== '' ? (string) $status->icon : null,
                'color' => $status->color !== null && $status->color !== '' ? (string) $status->color : null,
            ],
        ];
    }

    private function teamDayAlreadyHasLesson(int $partnerId, int $userId, int $teamId, string $occurrenceDateYmd): bool
    {
        return UserTeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('user_id', $userId)
            ->whereDate('starts_at', $occurrenceDateYmd)
            ->whereHas('slot', static fn ($query) => $query->where('team_id', $teamId))
            ->exists();
    }

    private function postpayHover(int $userId, string $occurrenceDateYmd, int $teamId): string
    {
        foreach ($this->postpayJournal->postpayTeamsForDate($userId, $occurrenceDateYmd) as $team) {
            if ((int) ($team['id'] ?? 0) !== $teamId) {
                continue;
            }

            return ScheduleJournalMonthService::postpayPackageHoverLabel(
                Money::toCents($team['price_per_lesson'] ?? 0)
            );
        }

        return ScheduleJournalMonthService::postpayPackageHoverLabel(null);
    }

    /**
     * @param  list<int>  $trainerProfileIds
     */
    private function trainerName(array $trainerProfileIds): ?string
    {
        if ($trainerProfileIds === []) {
            return null;
        }

        $profiles = TrainerProfile::query()
            ->with('user')
            ->whereIn('id', $trainerProfileIds)
            ->get()
            ->sortBy(fn (TrainerProfile $profile) => array_search((int) $profile->id, $trainerProfileIds, true));

        return ScheduleOccurrenceTrainerIds::formatNames($profiles);
    }
}
