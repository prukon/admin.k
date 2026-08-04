<?php

namespace Database\Seeders;

use App\Models\LessonOccurrenceStatus;
use App\Models\Partner;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserTeamScheduleSlot;
use App\Services\LessonPackages\UserLessonOccurrenceStatusService;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Демо-статусы занятий для журнала /schedule (цветные бейджи).
 * Ставится после DevLessonPackageAssignmentsSeeder — когда уже есть UTSS.
 */
class DevScheduleJournalSeeder extends Seeder
{
    use GuardsDevSeedData;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        $service = app(UserLessonOccurrenceStatusService::class);
        $today = CarbonImmutable::now()->startOfDay();
        $from = $today->startOfMonth()->subMonth();
        $to = $today->endOfMonth()->addMonth();

        Partner::query()
            ->orderBy('id')
            ->each(function (Partner $partner) use ($service, $today, $from, $to): void {
                $this->seedStatusesForPartner((int) $partner->id, $service, $today, $from, $to);
            });
    }

    private function seedStatusesForPartner(
        int $partnerId,
        UserLessonOccurrenceStatusService $service,
        CarbonImmutable $today,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): void {
        LessonOccurrenceStatusesSeeder::ensureForPartner($partnerId);

        $statuses = LessonOccurrenceStatus::query()
            ->forPartner($partnerId)
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
            $this->command?->warn("DevScheduleJournalSeeder: нет системных статусов у партнёра {$partnerId}");

            return;
        }

        $trainerIds = TrainerProfile::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $createdBy = User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'superadmin']))
            ->value('id');
        $createdById = $createdBy !== null ? (int) $createdBy : null;

        $rows = UserTeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->whereDate('starts_at', '>=', $from->toDateString())
            ->whereDate('starts_at', '<=', $to->toDateString())
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        foreach ($rows as $utss) {
            $dateYmd = CarbonImmutable::parse($utss->starts_at)->toDateString();
            $ulpId = $utss->user_lesson_package_id !== null
                ? (int) $utss->user_lesson_package_id
                : null;

            if ($this->hasStatusEvent(
                $partnerId,
                (int) $utss->user_id,
                (int) $utss->team_schedule_slot_id,
                $dateYmd,
                $ulpId,
            )) {
                continue;
            }

            $code = $this->pickStatusCode($dateYmd, $today);
            $status = match ($code) {
                LessonOccurrenceStatus::CODE_ATTENDED => $attended,
                'not_attended' => $notAttended,
                default => $scheduled,
            };

            $trainerId = $code === LessonOccurrenceStatus::CODE_ATTENDED && $trainerIds !== []
                ? $trainerIds[array_rand($trainerIds)]
                : null;

            try {
                $service->apply(
                    $partnerId,
                    (int) $utss->user_id,
                    (int) $utss->team_schedule_slot_id,
                    $dateYmd,
                    $ulpId,
                    $status,
                    $createdById,
                    $trainerId,
                );
            } catch (\DomainException $e) {
                if ($code === LessonOccurrenceStatus::CODE_SCHEDULED) {
                    Log::debug('DevScheduleJournalSeeder: scheduled skip', [
                        'utss_id' => $utss->id,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }

                try {
                    $service->apply(
                        $partnerId,
                        (int) $utss->user_id,
                        (int) $utss->team_schedule_slot_id,
                        $dateYmd,
                        $ulpId,
                        $scheduled,
                        $createdById,
                    );
                } catch (\Throwable $fallbackError) {
                    Log::debug('DevScheduleJournalSeeder: fallback scheduled skip', [
                        'utss_id' => $utss->id,
                        'error' => $fallbackError->getMessage(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::debug('DevScheduleJournalSeeder: apply skip', [
                    'utss_id' => $utss->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function hasStatusEvent(
        int $partnerId,
        int $userId,
        int $teamScheduleSlotId,
        string $dateYmd,
        ?int $ulpId,
    ): bool {
        $query = UserLessonOccurrenceStatusEvent::query()
            ->where('partner_id', $partnerId)
            ->where('user_id', $userId)
            ->where('team_schedule_slot_id', $teamScheduleSlotId)
            ->whereDate('occurrence_date', $dateYmd);

        if ($ulpId === null) {
            $query->whereNull('user_lesson_package_id');
        } else {
            $query->where('user_lesson_package_id', $ulpId);
        }

        return $query->exists();
    }

    private function pickStatusCode(string $dateYmd, CarbonImmutable $today): string
    {
        $date = CarbonImmutable::parse($dateYmd)->startOfDay();

        if ($date->greaterThan($today)) {
            return LessonOccurrenceStatus::CODE_SCHEDULED;
        }

        if ($date->equalTo($today)) {
            return random_int(0, 1) === 1
                ? LessonOccurrenceStatus::CODE_SCHEDULED
                : LessonOccurrenceStatus::CODE_ATTENDED;
        }

        $roll = random_int(1, 100);
        if ($roll <= 55) {
            return LessonOccurrenceStatus::CODE_ATTENDED;
        }
        if ($roll <= 80) {
            return 'not_attended';
        }

        return LessonOccurrenceStatus::CODE_SCHEDULED;
    }
}
