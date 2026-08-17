<?php

namespace Database\Seeders;

use App\Models\LessonOccurrenceStatus;
use App\Models\Partner;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\TeamScheduleSlotException;
use App\Models\TrainerProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Нагрузка журнала /schedule для партнёра «Исток» (id=1):
 * до 1000 активных учеников; в текущем месяце на слот×дату — 10–20 «Посетил»
 * плюс часть «Запись» / «Не посетил». Без родителей, цен и абонементов.
 */
class DevBulkStudentsAndJournalSeeder extends Seeder
{
    use GuardsDevSeedData;

    private const PARTNER_ID = 1;

    private const STUDENT_TARGET = 1000;

    private const ATTENDED_MIN = 10;

    private const ATTENDED_MAX = 20;

    private const EXTRA_MIN = 3;

    private const EXTRA_MAX = 8;

    private const FUTURE_SCHEDULED_MIN = 12;

    private const FUTURE_SCHEDULED_MAX = 20;

    private const EMAIL_PREFIX = 'bulk.istok.';

    private const EMAIL_DOMAIN = 'dev.kidscrm.test';

    private const INSERT_CHUNK = 200;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        if (app()->environment('testing')) {
            $this->command?->info('DevBulkStudentsAndJournalSeeder: пропуск в APP_ENV=testing');

            return;
        }

        if (! Partner::query()->whereKey(self::PARTNER_ID)->exists()) {
            $this->command?->warn('DevBulkStudentsAndJournalSeeder: нет партнёра id=1');

            return;
        }

        $userRoleId = Role::query()
            ->where('name', 'user')
            ->where('is_sistem', true)
            ->value('id');

        if ($userRoleId === null) {
            $this->command?->warn('DevBulkStudentsAndJournalSeeder: нет системной роли user');

            return;
        }

        $teamIds = Team::query()
            ->where('partner_id', self::PARTNER_ID)
            ->where('is_enabled', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($teamIds === []) {
            $this->command?->warn('DevBulkStudentsAndJournalSeeder: у партнёра 1 нет групп');

            return;
        }

        DB::connection()->disableQueryLog();

        LessonOccurrenceStatusesSeeder::ensureForPartner(self::PARTNER_ID);

        $createdStudents = $this->seedStudents((int) $userRoleId, $teamIds);
        $journalStats = $this->seedJournal((int) $userRoleId, $teamIds);

        $totalStudents = $this->studentBaseQuery((int) $userRoleId)->count();

        $this->command?->info(sprintf(
            'DevBulkStudentsAndJournalSeeder: учеников Истока %d (создано %d); журнал +%d занятий, +%d статусов',
            $totalStudents,
            $createdStudents,
            $journalStats['utss'],
            $journalStats['events'],
        ));
    }

    /**
     * @param  list<int>  $teamIds
     */
    private function seedStudents(int $userRoleId, array $teamIds): int
    {
        $this->attachOrphansToTeams($userRoleId, $teamIds);

        $current = $this->studentBaseQuery($userRoleId)->count();
        $need = self::STUDENT_TARGET - $current;
        if ($need <= 0) {
            return 0;
        }

        $passwordHash = Hash::make('password');
        $faker = fake('ru_RU');
        $now = now();
        $nowStr = $now->toDateTimeString();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $nextEmailIndex = $this->nextBulkEmailIndex();
        $teamCount = count($teamIds);
        $created = 0;
        $chunk = [];

        for ($i = 0; $i < $need; $i++) {
            $n = $nextEmailIndex + $i;
            $teamId = $teamIds[$i % $teamCount];
            $chunk[] = [
                'partner_id' => self::PARTNER_ID,
                'team_id' => $teamId,
                'role_id' => $userRoleId,
                'name' => $faker->firstName(),
                'lastname' => $faker->lastName(),
                'email' => self::EMAIL_PREFIX.$n.'@'.self::EMAIL_DOMAIN,
                'phone' => null,
                'email_verified_at' => $nowStr,
                'password' => $passwordHash,
                'is_enabled' => 1,
                'start_date' => $monthStart,
                'birthday' => random_int(1, 100) <= 80 ? $faker->date() : null,
                'two_factor_enabled' => 0,
                'offer_accepted' => 1,
                'offer_accepted_at' => $nowStr,
                'has_used_school_schedule_trial' => 0,
                'remember_token' => Str::random(10),
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];

            if (count($chunk) >= self::INSERT_CHUNK) {
                $created += $this->insertStudentChunk($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $created += $this->insertStudentChunk($chunk);
        }

        return $created;
    }

    /**
     * @param  list<array<string, mixed>>  $chunk
     */
    private function insertStudentChunk(array $chunk): int
    {
        DB::table('users')->insert($chunk);

        $emails = array_column($chunk, 'email');
        $idByEmail = DB::table('users')
            ->whereIn('email', $emails)
            ->pluck('id', 'email');

        $nowStr = now()->toDateTimeString();
        $pivot = [];
        foreach ($chunk as $row) {
            $userId = $idByEmail[$row['email']] ?? null;
            if ($userId === null) {
                continue;
            }
            $pivot[] = [
                'partner_id' => self::PARTNER_ID,
                'team_id' => (int) $row['team_id'],
                'user_id' => (int) $userId,
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
        }

        if ($pivot !== []) {
            DB::table('team_user')->insertOrIgnore($pivot);
        }

        return count($chunk);
    }

    /**
     * @param  list<int>  $teamIds
     */
    private function attachOrphansToTeams(int $userRoleId, array $teamIds): void
    {
        $orphanIds = $this->studentBaseQuery($userRoleId)
            ->whereDoesntHave('teams')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($orphanIds === []) {
            return;
        }

        $nowStr = now()->toDateTimeString();
        $teamCount = count($teamIds);
        $pivot = [];

        foreach ($orphanIds as $i => $userId) {
            $teamId = $teamIds[$i % $teamCount];
            $pivot[] = [
                'partner_id' => self::PARTNER_ID,
                'team_id' => $teamId,
                'user_id' => $userId,
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
            DB::table('users')->where('id', $userId)->update([
                'team_id' => $teamId,
                'updated_at' => $nowStr,
            ]);
        }

        DB::table('team_user')->insertOrIgnore($pivot);
    }

    /**
     * @param  list<int>  $teamIds
     * @return array{utss: int, events: int}
     */
    private function seedJournal(int $userRoleId, array $teamIds): array
    {
        $statuses = LessonOccurrenceStatus::query()
            ->forPartner(self::PARTNER_ID)
            ->whereIn('code', [
                LessonOccurrenceStatus::CODE_SCHEDULED,
                LessonOccurrenceStatus::CODE_ATTENDED,
                'not_attended',
            ])
            ->get()
            ->keyBy('code');

        /** @var LessonOccurrenceStatus|null $scheduled */
        $scheduled = $statuses->get(LessonOccurrenceStatus::CODE_SCHEDULED);
        /** @var LessonOccurrenceStatus|null $attended */
        $attended = $statuses->get(LessonOccurrenceStatus::CODE_ATTENDED);
        /** @var LessonOccurrenceStatus|null $notAttended */
        $notAttended = $statuses->get('not_attended');

        if (! $scheduled || ! $attended || ! $notAttended) {
            $this->command?->warn('DevBulkStudentsAndJournalSeeder: нет системных статусов у партнёра 1');

            return ['utss' => 0, 'events' => 0];
        }

        $slots = TeamScheduleSlot::query()
            ->where('partner_id', self::PARTNER_ID)
            ->where('is_enabled', true)
            ->whereIn('team_id', $teamIds)
            ->get(['id', 'team_id', 'weekday', 'date_start', 'date_end']);

        if ($slots->isEmpty()) {
            $this->command?->warn('DevBulkStudentsAndJournalSeeder: нет слотов у партнёра 1');

            return ['utss' => 0, 'events' => 0];
        }

        $from = CarbonImmutable::now()->startOfMonth();
        $to = CarbonImmutable::now()->endOfMonth();
        $today = CarbonImmutable::now()->startOfDay();

        $studentsByTeam = $this->studentIdsByTeam($userRoleId);
        $trainerIds = TrainerProfile::query()
            ->where('partner_id', self::PARTNER_ID)
            ->where('is_enabled', true)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        $createdById = User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'superadmin']))
            ->value('id');
        $createdById = $createdById !== null ? (int) $createdById : null;

        $skipKeys = $this->exceptionSkipKeys($slots->pluck('id')->map(fn ($id) => (int) $id)->all(), $from, $to);
        $occupied = $this->occupiedOccurrenceKeys($from, $to);
        $attendedCounts = $this->statusCountsForCode($attended->id, $from, $to);
        $scheduledCounts = $this->statusCountsForCode($scheduled->id, $from, $to);

        $maxEventIdBefore = (int) (DB::table('user_lesson_occurrence_status_events')->max('id') ?? 0);

        $nowStr = now()->toDateTimeString();
        $utssBuffer = [];
        $eventBuffer = [];
        $utssInserted = 0;
        $eventsInserted = 0;

        $flush = function () use (&$utssBuffer, &$eventBuffer, &$utssInserted, &$eventsInserted): void {
            if ($utssBuffer !== []) {
                DB::table('user_team_schedule_slots')->insertOrIgnore($utssBuffer);
                $utssInserted += count($utssBuffer);
                $utssBuffer = [];
            }
            if ($eventBuffer !== []) {
                DB::table('user_lesson_occurrence_status_events')->insert($eventBuffer);
                $eventsInserted += count($eventBuffer);
                $eventBuffer = [];
            }
        };

        foreach ($slots as $slot) {
            $slotId = (int) $slot->id;
            $teamId = (int) $slot->team_id;
            $weekday = (int) $slot->weekday;
            $slotStart = CarbonImmutable::parse($slot->date_start)->startOfDay();
            $slotEnd = CarbonImmutable::parse($slot->date_end)->startOfDay();
            $teamStudents = $studentsByTeam[$teamId] ?? [];

            if ($teamStudents === []) {
                continue;
            }

            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                if ((int) $day->format('N') !== $weekday) {
                    continue;
                }
                if ($day->lt($slotStart) || $day->gt($slotEnd)) {
                    continue;
                }

                $dateYmd = $day->toDateString();
                if (isset($skipKeys[$slotId.'|'.$dateYmd])) {
                    continue;
                }

                $occKey = $slotId.'|'.$dateYmd;
                $isFuture = $day->greaterThan($today);
                $isPast = $day->lessThan($today);

                if ($isFuture) {
                    if ((int) ($scheduledCounts[$occKey] ?? 0) >= self::FUTURE_SCHEDULED_MIN) {
                        continue;
                    }
                    $plan = [
                        LessonOccurrenceStatus::CODE_SCHEDULED => random_int(
                            self::FUTURE_SCHEDULED_MIN,
                            self::FUTURE_SCHEDULED_MAX
                        ),
                    ];
                } else {
                    if ((int) ($attendedCounts[$occKey] ?? 0) >= self::ATTENDED_MIN) {
                        continue;
                    }
                    $targetAttended = random_int(self::ATTENDED_MIN, self::ATTENDED_MAX);
                    $needAttended = max(0, $targetAttended - (int) ($attendedCounts[$occKey] ?? 0));
                    $plan = [
                        LessonOccurrenceStatus::CODE_ATTENDED => $needAttended,
                        'not_attended' => $isPast ? random_int(self::EXTRA_MIN, self::EXTRA_MAX) : random_int(0, self::EXTRA_MIN),
                        LessonOccurrenceStatus::CODE_SCHEDULED => random_int(self::EXTRA_MIN, self::EXTRA_MAX),
                    ];
                }

                $available = [];
                foreach ($teamStudents as $userId) {
                    if (! isset($occupied[$userId.'|'.$slotId.'|'.$dateYmd])) {
                        $available[] = $userId;
                    }
                }
                shuffle($available);

                foreach ($plan as $code => $want) {
                    $status = match ($code) {
                        LessonOccurrenceStatus::CODE_ATTENDED => $attended,
                        'not_attended' => $notAttended,
                        default => $scheduled,
                    };
                    $taken = $this->takeFromPool($available, (int) $want);
                    $trainerId = $code === LessonOccurrenceStatus::CODE_ATTENDED && $trainerIds !== []
                        ? $trainerIds[array_rand($trainerIds)]
                        : null;

                    foreach ($taken as $userId) {
                        $occupied[$userId.'|'.$slotId.'|'.$dateYmd] = true;
                        $utssBuffer[] = [
                            'partner_id' => self::PARTNER_ID,
                            'user_id' => $userId,
                            'user_lesson_package_id' => null,
                            'team_schedule_slot_id' => $slotId,
                            'starts_at' => $dateYmd,
                            'ends_at' => $dateYmd,
                            'is_trial_lesson' => 0,
                            'created_by' => $createdById,
                            'created_at' => $nowStr,
                            'updated_at' => $nowStr,
                        ];
                        $eventBuffer[] = [
                            'partner_id' => self::PARTNER_ID,
                            'user_id' => $userId,
                            'team_schedule_slot_id' => $slotId,
                            'occurrence_date' => $dateYmd,
                            'user_lesson_package_id' => null,
                            'lesson_occurrence_status_id' => (int) $status->id,
                            'trainer_profile_id' => $trainerId,
                            'comment' => null,
                            'created_by' => $createdById,
                            'created_at' => $nowStr,
                            'updated_at' => $nowStr,
                        ];

                        if (count($utssBuffer) >= self::INSERT_CHUNK) {
                            $flush();
                        }
                    }
                }
            }
        }

        $flush();
        $this->syncTrainerPivot($maxEventIdBefore);

        return ['utss' => $utssInserted, 'events' => $eventsInserted];
    }

    /**
     * @param  list<int>  $pool
     * @return list<int>
     */
    private function takeFromPool(array &$pool, int $count): array
    {
        $count = min(max(0, $count), count($pool));
        if ($count === 0) {
            return [];
        }

        return array_values(array_splice($pool, 0, $count));
    }

    /**
     * @return array<int, list<int>>
     */
    private function studentIdsByTeam(int $userRoleId): array
    {
        $rows = DB::table('team_user as tu')
            ->join('users as u', 'u.id', '=', 'tu.user_id')
            ->where('tu.partner_id', self::PARTNER_ID)
            ->where('u.partner_id', self::PARTNER_ID)
            ->where('u.role_id', $userRoleId)
            ->where('u.is_enabled', 1)
            ->whereNull('u.deleted_at')
            ->get(['tu.team_id', 'tu.user_id']);

        $byTeam = [];
        foreach ($rows as $row) {
            $byTeam[(int) $row->team_id][] = (int) $row->user_id;
        }

        return $byTeam;
    }

    /**
     * @param  list<int>  $slotIds
     * @return array<string, true>
     */
    private function exceptionSkipKeys(array $slotIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($slotIds === []) {
            return [];
        }

        $rows = TeamScheduleSlotException::query()
            ->whereIn('team_schedule_slot_id', $slotIds)
            ->whereDate('occurrence_date', '>=', $from->toDateString())
            ->whereDate('occurrence_date', '<=', $to->toDateString())
            ->get(['team_schedule_slot_id', 'occurrence_date']);

        $keys = [];
        foreach ($rows as $row) {
            $date = $row->occurrence_date instanceof \Carbon\CarbonInterface
                ? $row->occurrence_date->format('Y-m-d')
                : (string) $row->occurrence_date;
            $keys[(int) $row->team_schedule_slot_id.'|'.$date] = true;
        }

        return $keys;
    }

    /**
     * @return array<string, true>
     */
    private function occupiedOccurrenceKeys(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('user_team_schedule_slots')
            ->where('partner_id', self::PARTNER_ID)
            ->whereDate('starts_at', '>=', $from->toDateString())
            ->whereDate('starts_at', '<=', $to->toDateString())
            ->get(['user_id', 'team_schedule_slot_id', 'starts_at']);

        $keys = [];
        foreach ($rows as $row) {
            $date = substr((string) $row->starts_at, 0, 10);
            $keys[(int) $row->user_id.'|'.(int) $row->team_schedule_slot_id.'|'.$date] = true;
        }

        return $keys;
    }

    /**
     * @return array<string, int>
     */
    private function statusCountsForCode(int $statusId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('user_lesson_occurrence_status_events')
            ->select('team_schedule_slot_id', 'occurrence_date', DB::raw('COUNT(DISTINCT user_id) as c'))
            ->where('partner_id', self::PARTNER_ID)
            ->where('lesson_occurrence_status_id', $statusId)
            ->whereDate('occurrence_date', '>=', $from->toDateString())
            ->whereDate('occurrence_date', '<=', $to->toDateString())
            ->groupBy('team_schedule_slot_id', 'occurrence_date')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $date = substr((string) $row->occurrence_date, 0, 10);
            $counts[(int) $row->team_schedule_slot_id.'|'.$date] = (int) $row->c;
        }

        return $counts;
    }

    private function syncTrainerPivot(int $maxEventIdBefore): void
    {
        $rows = DB::table('user_lesson_occurrence_status_events')
            ->where('id', '>', $maxEventIdBefore)
            ->where('partner_id', self::PARTNER_ID)
            ->whereNotNull('trainer_profile_id')
            ->get(['id', 'trainer_profile_id']);

        $insert = [];
        foreach ($rows as $row) {
            $insert[] = [
                'user_lesson_occurrence_status_event_id' => (int) $row->id,
                'trainer_profile_id' => (int) $row->trainer_profile_id,
            ];
            if (count($insert) >= self::INSERT_CHUNK) {
                DB::table('user_lesson_occurrence_status_event_trainers')->insertOrIgnore($insert);
                $insert = [];
            }
        }

        if ($insert !== []) {
            DB::table('user_lesson_occurrence_status_event_trainers')->insertOrIgnore($insert);
        }
    }

    private function studentBaseQuery(int $userRoleId)
    {
        return User::query()
            ->where('partner_id', self::PARTNER_ID)
            ->where('is_enabled', 1)
            ->where('role_id', $userRoleId);
    }

    private function nextBulkEmailIndex(): int
    {
        $emails = DB::table('users')
            ->where('email', 'like', self::EMAIL_PREFIX.'%@'.self::EMAIL_DOMAIN)
            ->pluck('email');

        $max = 0;
        foreach ($emails as $email) {
            if (preg_match('/^'.preg_quote(self::EMAIL_PREFIX, '/').'(\d+)@/', (string) $email, $m) === 1) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }
}
