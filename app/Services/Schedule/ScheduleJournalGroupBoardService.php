<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\Team;
use App\Models\User;
use App\Services\Postpay\PostpayJournalService;
use App\Services\TrainerOwnTeamsScope;
use App\Support\Money;
use App\Support\Schedule\ScheduleJournalTeamFilter;
use App\Support\UserTeamQuery;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;

/**
 * Журнал /schedule: группы с вложенными учениками.
 * Размер страницы — ScheduleJournalPageLength (по умолчанию 50), один на все группы.
 * Ячейки строки ученика считаются только по группе этой строки.
 */
final class ScheduleJournalGroupBoardService
{
    public const PER_PAGE = ScheduleJournalPageLength::DEFAULT;

    /** Сколько учеников за один раз отмечает галочка дня в строке группы. */
    public const BULK_CANDIDATE_LIMIT = 100;

    public const NONE_KEY = 'none';

    public const NONE_TITLE = 'Без группы';

    public function __construct(
        private readonly ScheduleJournalMonthService $journalMonthService,
        private readonly PostpayJournalService $postpayJournal,
        private readonly JournalMonthlyPaymentStatusService $journalMonthlyPaymentStatus,
        private readonly TrainerOwnTeamsScope $ownTeams,
    ) {
    }

    /**
     * Шапки всех групп. Ученики и ячейки — только у групп из group_pages (запасной полный GET).
     *
     * @param  array<string|int, mixed>  $groupPages
     * @return list<array<string, mixed>>
     */
    public function build(
        ?User $actor,
        int $partnerId,
        ScheduleJournalTeamFilter $filter,
        string $searchQ,
        Carbon $startOfMonth,
        Carbon $endOfMonth,
        array $groupPages,
        int $perPage = self::PER_PAGE,
    ): array {
        $perPage = ScheduleJournalPageLength::normalize($perPage);
        $shells = $this->shells($actor, $partnerId, $filter, $searchQ);
        $teamIds = [];
        foreach ($shells as $shell) {
            if ($shell['team_id'] !== null) {
                $teamIds[] = (int) $shell['team_id'];
            }
        }
        $weekdaysByTeam = $this->weekdaysByTeam($teamIds);

        $pages = [];
        foreach ($groupPages as $key => $page) {
            $pageNumber = (int) $page;
            if ($pageNumber > 0) {
                $pages[(string) $key] = $pageNumber;
            }
        }

        $buckets = [];
        foreach ($shells as $shell) {
            if (! array_key_exists($shell['key'], $pages)) {
                continue;
            }
            $bucket = $this->oneBucket(
                $actor,
                $partnerId,
                $filter,
                $searchQ,
                $shell['key'],
                $pages[$shell['key']],
                $perPage,
            );
            if ($bucket !== null) {
                $buckets[] = $bucket;
            }
        }

        $hydrated = [];
        foreach ($this->hydrate($actor, $partnerId, $filter, $startOfMonth, $endOfMonth, $buckets) as $group) {
            $hydrated[(string) $group['key']] = $group;
        }

        $groups = [];
        foreach ($shells as $shell) {
            $key = $shell['key'];
            if (isset($hydrated[$key])) {
                $group = $hydrated[$key];
                $group['loaded'] = true;
                $group['users_total'] = (int) $group['users']->total();
                $groups[] = $group;

                continue;
            }

            $teamId = $shell['team_id'];
            $groups[] = [
                'key' => $key,
                'team_id' => $teamId,
                'title' => $shell['title'],
                'weekdays' => $teamId === null ? [] : ($weekdaysByTeam[(int) $teamId] ?? []),
                'users_total' => $shell['total'],
                'loaded' => false,
            ];
        }

        return $groups;
    }

    /**
     * Одна группа для смены страницы без перезагрузки всей таблицы.
     *
     * @return array<string, mixed>|null
     */
    public function buildOne(
        ?User $actor,
        int $partnerId,
        ScheduleJournalTeamFilter $filter,
        string $searchQ,
        Carbon $startOfMonth,
        Carbon $endOfMonth,
        string $groupKey,
        int $page,
        int $perPage = self::PER_PAGE,
    ): ?array {
        $perPage = ScheduleJournalPageLength::normalize($perPage);
        $bucket = $this->oneBucket($actor, $partnerId, $filter, $searchQ, $groupKey, $page, $perPage);
        if ($bucket === null) {
            return null;
        }

        $groups = $this->hydrate($actor, $partnerId, $filter, $startOfMonth, $endOfMonth, [$bucket]);

        return $groups[0] ?? null;
    }

    /**
     * Ученики группы, которым можно поставить занятие на дату: все страницы, не только текущая.
     * В ответе первые {@see self::BULK_CANDIDATE_LIMIT} в порядке журнала; total — сколько всего подходят.
     *
     * @return array{
     *     users: list<array{id: int, name: string, billing: string, abonement_name: string, price_label: string}>,
     *     total: int,
     *     limit: int
     * }|null
     */
    public function eligibleBulkCandidates(
        ?User $actor,
        int $partnerId,
        ScheduleJournalTeamFilter $filter,
        string $searchQ,
        string $groupKey,
        string $dateYmd,
    ): ?array {
        $bucket = $this->allStudentsInGroup($actor, $partnerId, $filter, $searchQ, $groupKey);
        if ($bucket === null) {
            return null;
        }

        $empty = [
            'users' => [],
            'total' => 0,
            'limit' => self::BULK_CANDIDATE_LIMIT,
        ];
        if ($bucket['team_id'] === null) {
            return $empty;
        }

        $users = $bucket['users'];
        $ids = $users->map(fn (User $user) => (int) $user->id)->all();
        if ($ids === []) {
            return $empty;
        }

        $teamId = (int) $bucket['team_id'];
        $rowFilter = new ScheduleJournalTeamFilter(false, [$teamId]);
        $day = Carbon::parse($dateYmd)->startOfDay();
        $monthFirst = $day->copy()->startOfMonth()->format('Y-m-d');
        $allowedTeamIds = $this->ownTeams->allowedTeamIds($actor, $partnerId);
        $occurrences = $this->journalMonthService->occurrencesByUserDate(
            $partnerId,
            $ids,
            $day,
            $day,
            $rowFilter,
            $allowedTeamIds,
        );
        $postpayByUser = $this->postpayJournal->postpayAbonementHintsByUser($ids, $monthFirst, $rowFilter);
        $postpayLocked = $this->postpayJournal->postpayLockedUserFlags($ids, $monthFirst, $rowFilter);
        $flexibleByUser = $this->journalMonthService->flexibleAssignableByUserForBillingMonth(
            $partnerId,
            $ids,
            $monthFirst,
            $rowFilter,
        );

        $eligible = [];
        foreach ($users as $user) {
            $userId = (int) $user->id;
            if (count($occurrences[$userId.'_'.$dateYmd] ?? []) > 0) {
                continue;
            }

            $name = trim((string) $user->full_name);
            if ($name === '') {
                $name = 'Без имени';
            }

            $flexItems = $flexibleByUser[$userId] ?? [];
            $remaining = 0;
            foreach ($flexItems as $item) {
                $remaining += max(0, (int) ($item['slots_remaining'] ?? 0));
            }
            if ($flexItems !== [] && $remaining > 0) {
                $picked = $this->pickFlexibleForBulkDate($flexItems, $dateYmd);
                $abonement = '';
                $feeCents = 0;
                if ($picked !== null) {
                    $abonement = trim((string) ($picked['name'] ?? ''));
                    $feeCents = (int) ($picked['fee_amount_cents'] ?? 0);
                }
                if ($abonement === '') {
                    $abonement = 'Абонемент предоплаты';
                }
                $eligible[] = [
                    'id' => $userId,
                    'name' => $name,
                    'billing' => 'prepaid',
                    'abonement_name' => $abonement,
                    'price_label' => Money::formatRub(max(0, $feeCents)).' руб',
                ];

                continue;
            }

            $postpayHints = $postpayByUser[$userId] ?? [];
            if ($postpayHints !== [] && empty($postpayLocked[$userId])) {
                $eligible[] = [
                    'id' => $userId,
                    'name' => $name,
                    'billing' => 'postpay',
                    'abonement_name' => 'Постоплата',
                    'price_label' => Money::formatRub(max(0, (int) ($postpayHints[0]['price_cents'] ?? 0))).' ₽/занятие',
                ];
            }
        }

        return [
            'users' => array_slice($eligible, 0, self::BULK_CANDIDATE_LIMIT),
            'total' => count($eligible),
            'limit' => self::BULK_CANDIDATE_LIMIT,
        ];
    }

    /**
     * @return list<array{key: string, team_id: ?int, title: string, total: int}>
     */
    private function shells(
        ?User $actor,
        int $partnerId,
        ScheduleJournalTeamFilter $filter,
        string $searchQ,
    ): array {
        $shells = [];

        foreach ($this->candidateTeams($actor, $partnerId, $filter) as $team) {
            $total = $this->countStudents(
                $this->studentsForTeam($actor, $partnerId, $filter, $searchQ, (int) $team->id)
            );
            if ($filter->isAll() && $total < 1) {
                continue;
            }
            $shells[] = [
                'key' => (string) $team->id,
                'team_id' => (int) $team->id,
                'title' => (string) $team->title,
                'total' => $total,
            ];
        }

        if ($filter->isAll() || $filter->includeNone) {
            $total = $this->countStudents(
                $this->studentsWithoutTeam($actor, $partnerId, $filter, $searchQ)
            );
            if ($total > 0 || $filter->includeNone) {
                $shells[] = [
                    'key' => self::NONE_KEY,
                    'team_id' => null,
                    'title' => self::NONE_TITLE,
                    'total' => $total,
                ];
            }
        }

        return $shells;
    }

    /**
     * @return array{key: string, team_id: ?int, title: string, users: LengthAwarePaginator}|null
     */
    private function oneBucket(
        ?User $actor,
        int $partnerId,
        ScheduleJournalTeamFilter $filter,
        string $searchQ,
        string $groupKey,
        int $page,
        int $perPage,
    ): ?array {
        $page = max(1, $page);
        $perPage = ScheduleJournalPageLength::normalize($perPage);

        if ($groupKey === self::NONE_KEY) {
            if (! $filter->isAll() && ! $filter->includeNone) {
                return null;
            }

            return [
                'key' => self::NONE_KEY,
                'team_id' => null,
                'title' => self::NONE_TITLE,
                'users' => $this->paginate(
                    $this->studentsWithoutTeam($actor, $partnerId, $filter, $searchQ),
                    self::NONE_KEY,
                    $page,
                    $perPage,
                ),
            ];
        }

        if (! ctype_digit($groupKey) || (int) $groupKey < 1) {
            return null;
        }

        $teamId = (int) $groupKey;
        if (! $filter->isAll() && ! in_array($teamId, $filter->teamIds, true)) {
            return null;
        }
        if (! $this->ownTeams->allowsTeamId($actor, $partnerId, $teamId)) {
            return null;
        }

        $team = Team::query()
            ->where('partner_id', $partnerId)
            ->whereKey($teamId)
            ->first(['id', 'title', 'is_enabled']);
        if ($team === null) {
            return null;
        }
        if ($filter->isAll() && ! $team->is_enabled) {
            return null;
        }

        return [
            'key' => (string) $team->id,
            'team_id' => (int) $team->id,
            'title' => (string) $team->title,
            'users' => $this->paginate(
                $this->studentsForTeam($actor, $partnerId, $filter, $searchQ, $teamId),
                (string) $team->id,
                $page,
                $perPage,
            ),
        ];
    }

    /**
     * Все ученики группы, без страницы. null — группы нет в текущем фильтре.
     *
     * @return array{key: string, team_id: ?int, users: \Illuminate\Support\Collection<int, User>}|null
     */
    private function allStudentsInGroup(
        ?User $actor,
        int $partnerId,
        ScheduleJournalTeamFilter $filter,
        string $searchQ,
        string $groupKey,
    ): ?array {
        if ($groupKey === self::NONE_KEY) {
            if (! $filter->isAll() && ! $filter->includeNone) {
                return null;
            }

            return [
                'key' => self::NONE_KEY,
                'team_id' => null,
                'users' => $this->studentsWithoutTeam($actor, $partnerId, $filter, $searchQ)->get(),
            ];
        }

        if (! ctype_digit($groupKey) || (int) $groupKey < 1) {
            return null;
        }

        $teamId = (int) $groupKey;
        if (! $filter->isAll() && ! in_array($teamId, $filter->teamIds, true)) {
            return null;
        }
        if (! $this->ownTeams->allowsTeamId($actor, $partnerId, $teamId)) {
            return null;
        }

        $team = Team::query()
            ->where('partner_id', $partnerId)
            ->whereKey($teamId)
            ->first(['id', 'title', 'is_enabled']);
        if ($team === null) {
            return null;
        }
        if ($filter->isAll() && ! $team->is_enabled) {
            return null;
        }

        return [
            'key' => (string) $team->id,
            'team_id' => (int) $team->id,
            'users' => $this->studentsForTeam($actor, $partnerId, $filter, $searchQ, $teamId)->get(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function pickFlexibleForBulkDate(array $items, string $date): ?array
    {
        foreach ($items as $item) {
            if ((int) ($item['slots_remaining'] ?? 0) < 1) {
                continue;
            }
            if (! $this->flexibleCoversBulkDate($item, $date)) {
                continue;
            }

            return $item;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function flexibleCoversBulkDate(array $item, string $date): bool
    {
        $start = (string) ($item['starts_at'] ?? '');
        $end = (string) ($item['ends_at'] ?? '');
        if ($start === '' || $end === '') {
            return true;
        }

        return $date >= $start && $date <= $end;
    }

    /**
     * @param  list<array{key: string, team_id: ?int, title: string, users: LengthAwarePaginator}>  $buckets
     * @return list<array<string, mixed>>
     */
    private function hydrate(
        ?User $actor,
        int $partnerId,
        ScheduleJournalTeamFilter $filter,
        Carbon $startOfMonth,
        Carbon $endOfMonth,
        array $buckets,
    ): array {
        $monthFirst = $startOfMonth->format('Y-m-d');
        $allowedTeamIds = $this->ownTeams->allowedTeamIds($actor, $partnerId);

        $teamIds = [];
        $teamUserIds = [];
        $allUserIds = [];
        foreach ($buckets as $bucket) {
            $ids = $bucket['users']->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($ids as $id) {
                $allUserIds[$id] = $id;
            }
            if ($bucket['team_id'] !== null) {
                $teamIds[] = (int) $bucket['team_id'];
                $teamUserIds[(int) $bucket['team_id']] = $ids;
            }
        }
        $allUserIds = array_values($allUserIds);

        $occurrenceUserIds = [];
        foreach ($teamUserIds as $ids) {
            foreach ($ids as $id) {
                $occurrenceUserIds[$id] = $id;
            }
        }
        $occurrences = $occurrenceUserIds === []
            ? []
            : $this->journalMonthService->occurrencesByUserDate(
                $partnerId,
                array_values($occurrenceUserIds),
                $startOfMonth,
                $endOfMonth,
                $filter,
                $allowedTeamIds,
            );
        $occurrencesByTeam = [];
        foreach ($occurrences as $key => $items) {
            foreach ($items as $item) {
                $teamId = (int) ($item['team_id'] ?? 0);
                if ($teamId < 1) {
                    continue;
                }
                $occurrencesByTeam[$teamId][$key][] = $item;
            }
        }

        $fixedByUser = $this->journalMonthService->fixedAssignmentsByUser($partnerId, $allUserIds);
        $weekdaysByTeam = $this->weekdaysByTeam($teamIds);

        $groups = [];
        foreach ($buckets as $bucket) {
            $ids = $bucket['users']->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $teamId = $bucket['team_id'];
            if ($teamId === null) {
                $groupOccurrences = [];
                $rowFilter = new ScheduleJournalTeamFilter(true, []);
                $postpayByUser = [];
                $postpayLocked = [];
                $flexibleByUser = [];
                $payments = $this->journalMonthlyPaymentStatus->statusesByUser(
                    $partnerId,
                    $ids,
                    $monthFirst,
                    $rowFilter,
                );
            } else {
                $groupOccurrences = $this->occurrencesForUsers($occurrencesByTeam[$teamId] ?? [], $ids);
                $rowFilter = new ScheduleJournalTeamFilter(false, [$teamId]);
                $postpayByUser = $this->postpayJournal->postpayAbonementHintsByUser($ids, $monthFirst, $rowFilter);
                $postpayLocked = $this->postpayJournal->postpayLockedUserFlags($ids, $monthFirst, $rowFilter);
                $flexibleByUser = $this->journalMonthService->flexibleAssignableByUserForBillingMonth(
                    $partnerId,
                    $ids,
                    $monthFirst,
                    $rowFilter,
                );
                $payments = $this->journalMonthlyPaymentStatus->statusesByUser(
                    $partnerId,
                    $ids,
                    $monthFirst,
                    $rowFilter,
                );
            }

            $postpayUsers = [];
            foreach (array_keys($postpayByUser) as $uid) {
                $postpayUsers[(int) $uid] = true;
            }
            $flexibleUsers = [];
            foreach ($flexibleByUser as $uid => $assignments) {
                if ($assignments !== []) {
                    $flexibleUsers[(int) $uid] = true;
                }
            }

            $groups[] = [
                'key' => $bucket['key'],
                'team_id' => $teamId,
                'title' => $bucket['title'],
                'weekdays' => $teamId === null ? [] : ($weekdaysByTeam[$teamId] ?? []),
                'users' => $bucket['users'],
                'occurrences' => $groupOccurrences,
                'assignments' => $this->assignmentsForGroup($fixedByUser, $teamId, $ids),
                'payments' => $payments,
                'consuming' => $this->journalMonthService->consumingCountsByUser($groupOccurrences),
                'postpayByUser' => $postpayByUser,
                'postpayUsers' => $postpayUsers,
                'postpayLocked' => $postpayLocked,
                'flexibleByUser' => $flexibleByUser,
                'flexibleUsers' => $flexibleUsers,
            ];
        }

        return $groups;
    }

    /**
     * @param  list<int>  $teamIds
     * @return array<int, list<int>>
     */
    private function weekdaysByTeam(array $teamIds): array
    {
        if ($teamIds === []) {
            return [];
        }

        $map = [];
        $rows = DB::table('team_weekdays')
            ->whereIn('team_id', $teamIds)
            ->get(['team_id', 'weekday_id']);
        foreach ($rows as $row) {
            $map[(int) $row->team_id][] = (int) $row->weekday_id;
        }

        return $map;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $occurrences
     * @param  list<int>  $userIds
     * @return array<string, list<array<string, mixed>>>
     */
    private function occurrencesForUsers(array $occurrences, array $userIds): array
    {
        if ($userIds === [] || $occurrences === []) {
            return [];
        }

        $want = array_flip($userIds);
        $out = [];
        foreach ($occurrences as $key => $items) {
            $userId = (int) strtok((string) $key, '_');
            if (! isset($want[$userId])) {
                continue;
            }
            $out[$key] = $items;
        }

        return $out;
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $byUser
     * @param  list<int>  $userIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function assignmentsForGroup(array $byUser, ?int $teamId, array $userIds): array
    {
        $out = [];
        foreach ($userIds as $userId) {
            $rows = $byUser[$userId] ?? [];
            $out[$userId] = array_values(array_filter(
                $rows,
                static function (array $row) use ($teamId): bool {
                    $rowTeam = (int) ($row['team_id'] ?? 0);

                    return $teamId === null ? $rowTeam < 1 : $rowTeam === $teamId;
                }
            ));
        }

        return $out;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Team>
     */
    private function candidateTeams(?User $actor, int $partnerId, ScheduleJournalTeamFilter $filter)
    {
        $query = Team::query()
            ->where('partner_id', $partnerId)
            ->orderBy('order_by')
            ->orderBy('title')
            ->orderBy('id');

        if ($filter->isAll()) {
            $query->where('is_enabled', 1);
            $this->ownTeams->restrictTeamsQuery($query, $actor, $partnerId);
        } elseif ($filter->teamIds !== []) {
            $query->whereIn('id', $filter->teamIds);
        } else {
            return collect();
        }

        return $query->get(['id', 'title', 'order_by', 'is_enabled']);
    }

    private function studentsForTeam(
        ?User $actor,
        int $partnerId,
        ScheduleJournalTeamFilter $filter,
        string $searchQ,
        int $teamId,
    ): Builder {
        $query = $this->newStudentQuery($actor, $partnerId, $filter);
        UserTeamQuery::applyStudentInTeamExists($query, $partnerId, $teamId);
        $this->applySearch($query, $searchQ);

        return $query;
    }

    private function studentsWithoutTeam(
        ?User $actor,
        int $partnerId,
        ScheduleJournalTeamFilter $filter,
        string $searchQ,
    ): Builder {
        $query = $this->newStudentQuery($actor, $partnerId, $filter);
        UserTeamQuery::applyStudentTeamFilter($query, $partnerId, 'none');
        $this->applySearch($query, $searchQ);

        return $query;
    }

    private function newStudentQuery(?User $actor, int $partnerId, ScheduleJournalTeamFilter $filter): Builder
    {
        $query = User::query()
            ->select(['id', 'name', 'lastname', 'partner_id', 'role_id', 'is_enabled'])
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->withSystemRoleUser();

        if ($filter->isAll()) {
            $this->ownTeams->restrictStudentsQuery($query, $actor, $partnerId);
        }

        return $query
            ->orderBy('lastname')
            ->orderBy('name')
            ->orderBy('id');
    }

    private function applySearch(Builder $query, string $searchQ): void
    {
        if ($searchQ === '') {
            return;
        }

        $like = '%'.$searchQ.'%';
        $query->where(function ($q) use ($like) {
            $q->where('lastname', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhereRaw(
                    "CONCAT(IFNULL(lastname, ''), ' ', IFNULL(name, '')) LIKE ?",
                    [$like]
                );
        });
    }

    private function countStudents(Builder $query): int
    {
        return (int) (clone $query)->count();
    }

    private function paginate(Builder $query, string $key, int $page, int $perPage): LengthAwarePaginator
    {
        $perPage = ScheduleJournalPageLength::normalize($perPage);
        $page = max(1, $page);
        $total = (clone $query)->count();
        $items = (clone $query)->forPage($page, $perPage)->get();
        $paginator = new Paginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'pageName' => 'group_pages['.$key.']',
        ]);
        $paginator->appends(request()->except('page'));

        return $paginator;
    }
}
