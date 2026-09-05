<?php

declare(strict_types=1);

namespace App\Services\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\LessonPackages\UserLessonPackageAutoProlongGuard;
use App\Services\Postpay\PostpayUsersPriceSync;
use App\Services\Pricing\UserPercentDiscount;
use App\Support\LessonPackagePostpayPermission;
use App\Support\SettingPricesMonth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ручной перенос абонементов с месяца M на M+1 по всем группам партнёра.
 *
 * Не использует снимок «всем как у группы»: персональные шаблоны учеников сохраняются.
 * Сумма = текущая цена шаблона × текущий % карточки. Раскладку журнала не копирует.
 */
final class MonthlyPricesProlongService
{
    public function __construct(
        private readonly UsersPriceLessonPackageSync $ulpSync,
        private readonly PostpayUsersPriceSync $postpaySync,
        private readonly UserLessonPackageAutoProlongGuard $autoProlongGuard,
    ) {
    }

    public function preview(int $partnerId, string $sourceMonth, ?User $actor): MonthlyPricesProlongReport
    {
        return $this->run($partnerId, $sourceMonth, $actor, false);
    }

    public function apply(int $partnerId, string $sourceMonth, ?User $actor): MonthlyPricesProlongReport
    {
        return $this->run($partnerId, $sourceMonth, $actor, true);
    }

    private function run(int $partnerId, string $sourceMonth, ?User $actor, bool $write): MonthlyPricesProlongReport
    {
        $targetMonth = SettingPricesMonth::nextMonth($sourceMonth);
        $report = new MonthlyPricesProlongReport(
            $sourceMonth,
            $targetMonth,
            SettingPricesMonth::toLabel($sourceMonth),
            SettingPricesMonth::toLabel($targetMonth),
        );

        $teams = Team::query()
            ->where('partner_id', $partnerId)
            ->whereNull('deleted_at')
            ->orderBy('order_by')
            ->orderBy('id')
            ->get();

        if ($teams->isEmpty()) {
            return $report;
        }

        $teamIds = $teams->pluck('id')->map(static fn ($id) => (int) $id)->all();

        /** @var Collection<int, Collection<int, User>> $studentsByTeam */
        $studentsByTeam = $this->loadEnabledStudentsByTeam($teamIds);
        $userIds = $studentsByTeam->flatten()->pluck('id')->map(static fn ($id) => (int) $id)->unique()->values()->all();

        $sourceTeamPrices = TeamPrice::query()
            ->whereIn('team_id', $teamIds)
            ->where('new_month', $sourceMonth)
            ->get()
            ->keyBy(static fn (TeamPrice $row) => (int) $row->team_id);

        $targetTeamPrices = TeamPrice::query()
            ->whereIn('team_id', $teamIds)
            ->where('new_month', $targetMonth)
            ->get()
            ->keyBy(static fn (TeamPrice $row) => (int) $row->team_id);

        $sourceUserPrices = $this->indexUserPrices(
            UserPrice::query()
                ->whereIn('team_id', $teamIds)
                ->where('new_month', $sourceMonth)
                ->when($userIds !== [], fn ($q) => $q->whereIn('user_id', $userIds))
                ->get()
        );

        $targetUserPrices = $this->indexUserPrices(
            UserPrice::query()
                ->with('userLessonPackage')
                ->whereIn('team_id', $teamIds)
                ->where('new_month', $targetMonth)
                ->when($userIds !== [], fn ($q) => $q->whereIn('user_id', $userIds))
                ->get()
        );

        $packageIds = [];
        foreach ($sourceTeamPrices as $row) {
            $id = (int) ($row->lesson_package_id ?? 0);
            if ($id > 0) {
                $packageIds[$id] = $id;
            }
        }
        foreach ($sourceUserPrices as $row) {
            $id = (int) ($row->lesson_package_id ?? 0);
            if ($id > 0) {
                $packageIds[$id] = $id;
            }
        }

        $packages = $packageIds === []
            ? collect()
            : LessonPackage::query()
                ->where('partner_id', $partnerId)
                ->whereIn('id', array_values($packageIds))
                ->get()
                ->keyBy(static fn (LessonPackage $p) => (int) $p->id);

        $blockedUserIds = $this->autoProlongGuard->blockedUserIds($userIds);
        $canPostpay = LessonPackagePostpayPermission::userCanSelect($actor);
        $actorId = $actor !== null ? (int) $actor->id : null;

        foreach ($teams as $team) {
            $teamId = (int) $team->id;
            $teamTitle = (string) $team->title;
            $this->processTeamCatalog(
                $report,
                $write,
                $teamId,
                $teamTitle,
                $targetMonth,
                $sourceTeamPrices->get($teamId),
                $targetTeamPrices->get($teamId),
                $packages,
                $canPostpay,
            );

            $students = $studentsByTeam->get($teamId, collect());
            foreach ($students as $student) {
                $this->processStudent(
                    $report,
                    $write,
                    $student,
                    $teamId,
                    $teamTitle,
                    $targetMonth,
                    $sourceUserPrices[$this->userPriceKey((int) $student->id, $teamId)] ?? null,
                    $targetUserPrices[$this->userPriceKey((int) $student->id, $teamId)] ?? null,
                    $packages,
                    $blockedUserIds,
                    $canPostpay,
                    $actorId,
                );
            }
        }

        return $report;
    }

    /**
     * @param  Collection<int, LessonPackage>  $packages
     */
    private function processTeamCatalog(
        MonthlyPricesProlongReport $report,
        bool $write,
        int $teamId,
        string $teamTitle,
        string $targetMonth,
        ?TeamPrice $source,
        ?TeamPrice $target,
        Collection $packages,
        bool $canPostpay,
    ): void {
        $sourcePackageId = $this->packageId($source?->lesson_package_id);
        if ($sourcePackageId === null) {
            $report->addTeamSkip(MonthlyPricesProlongReport::REASON_EMPTY_SOURCE, $teamId, $teamTitle, false);

            return;
        }

        /** @var LessonPackage|null $package */
        $package = $packages->get($sourcePackageId);
        if (! $package) {
            $report->addTeamSkip(MonthlyPricesProlongReport::REASON_TEMPLATE_MISSING, $teamId, $teamTitle);

            return;
        }

        $catalogCents = (int) $package->price_cents;
        $targetPackageId = $this->packageId($target?->lesson_package_id);

        if ($package->isPostpay() && ! $canPostpay && $targetPackageId === null) {
            $report->addTeamSkip(MonthlyPricesProlongReport::REASON_POSTPAY_DENIED, $teamId, $teamTitle);

            return;
        }

        if ($targetPackageId !== null) {
            if ($targetPackageId === $sourcePackageId && (int) ($target?->price_cents ?? 0) === $catalogCents) {
                $report->addTeamUnchanged();

                return;
            }

            $report->addTeamSkip(MonthlyPricesProlongReport::REASON_ALREADY_SET, $teamId, $teamTitle);

            return;
        }

        $report->addTeamSet($teamId, $teamTitle, (string) $package->name, $catalogCents);

        if (! $write) {
            return;
        }

        TeamPrice::query()->updateOrCreate(
            [
                'team_id' => $teamId,
                'new_month' => $targetMonth,
            ],
            [
                'lesson_package_id' => $sourcePackageId,
                'price_cents' => $catalogCents,
            ]
        );
    }

    /**
     * @param  Collection<int, LessonPackage>  $packages
     * @param  array<int, true>  $blockedUserIds
     */
    private function processStudent(
        MonthlyPricesProlongReport $report,
        bool $write,
        User $student,
        int $teamId,
        string $teamTitle,
        string $targetMonth,
        ?UserPrice $source,
        ?UserPrice $target,
        Collection $packages,
        array $blockedUserIds,
        bool $canPostpay,
        ?int $actorId,
    ): void {
        $userId = (int) $student->id;
        $userName = trim((string) $student->lastname.' '.(string) $student->name);

        $sourcePackageId = $this->packageId($source?->lesson_package_id);
        if ($sourcePackageId === null) {
            $report->addStudentSkip(
                MonthlyPricesProlongReport::REASON_EMPTY_SOURCE,
                $userId,
                $userName,
                $teamId,
                $teamTitle,
                false,
            );

            return;
        }

        /** @var LessonPackage|null $package */
        $package = $packages->get($sourcePackageId);
        if (! $package) {
            $report->addStudentSkip(
                MonthlyPricesProlongReport::REASON_TEMPLATE_MISSING,
                $userId,
                $userName,
                $teamId,
                $teamTitle,
            );

            return;
        }

        if ($target !== null && $target->effective_is_paid) {
            $report->addStudentSkip(
                MonthlyPricesProlongReport::REASON_ALREADY_PAID,
                $userId,
                $userName,
                $teamId,
                $teamTitle,
            );

            return;
        }

        $targetUlp = $target?->userLessonPackage;
        $targetLaidOut = $targetUlp !== null && $targetUlp->isLaidOutInSchedule();
        $targetPackageId = $this->packageId($target?->lesson_package_id);
        $targetHasCharge = $targetPackageId !== null || (int) ($target?->price_cents ?? 0) > 0;

        $catalogCents = (int) $package->price_cents;
        $isPostpay = $package->isPostpay();
        $payableCents = $isPostpay
            ? (int) ($target?->price_cents ?? 0)
            : UserPercentDiscount::payableCentsForUser($catalogCents, $student);
        $snap = UserPercentDiscount::snapshotFromUser($student);

        if ($targetHasCharge) {
            if ($targetLaidOut && $targetPackageId !== null && $targetPackageId !== $sourcePackageId) {
                $report->addStudentSkip(
                    MonthlyPricesProlongReport::REASON_LAID_OUT,
                    $userId,
                    $userName,
                    $teamId,
                    $teamTitle,
                );

                return;
            }

            $samePackage = $targetPackageId === $sourcePackageId;
            $sameAmount = $isPostpay || (int) ($target?->price_cents ?? 0) === $payableCents;
            if ($samePackage && $sameAmount) {
                $report->addStudentUnchanged();

                return;
            }

            $report->addStudentSkip(
                MonthlyPricesProlongReport::REASON_ALREADY_SET,
                $userId,
                $userName,
                $teamId,
                $teamTitle,
            );

            return;
        }

        if ($targetLaidOut) {
            $report->addStudentSkip(
                MonthlyPricesProlongReport::REASON_LAID_OUT,
                $userId,
                $userName,
                $teamId,
                $teamTitle,
            );

            return;
        }

        if ($isPostpay && ! $canPostpay) {
            $report->addStudentSkip(
                MonthlyPricesProlongReport::REASON_POSTPAY_DENIED,
                $userId,
                $userName,
                $teamId,
                $teamTitle,
            );

            return;
        }

        if (isset($blockedUserIds[$userId])) {
            $report->addStudentSkip(
                MonthlyPricesProlongReport::REASON_AUTO_PROLONG,
                $userId,
                $userName,
                $teamId,
                $teamTitle,
                true,
                UserLessonPackageAutoProlongGuard::BLOCK_REASON,
            );

            return;
        }

        if (! $write) {
            $report->addStudentCreate($userId, $userName, $teamId, $teamTitle, (string) $package->name, $payableCents);

            return;
        }

        try {
            $written = DB::transaction(function () use ($student, $teamId, $targetMonth, $package, $snap, $payableCents, $isPostpay, $actorId) {
                return $this->writeStudentRow(
                    $student,
                    $teamId,
                    $targetMonth,
                    $package,
                    $snap,
                    $payableCents,
                    $isPostpay,
                    $actorId,
                );
            });
        } catch (UsersPriceLessonPackageSyncException $e) {
            $report->addStudentError($e->getMessage(), $userId, $userName, $teamId, $teamTitle);

            return;
        } catch (Throwable $e) {
            Log::error('MonthlyPricesProlongService: не удалось записать ученика', [
                'user_id' => $userId,
                'team_id' => $teamId,
                'target_month' => $targetMonth,
                'error' => $e->getMessage(),
            ]);
            $report->addStudentError(
                'Не удалось сохранить начисление.',
                $userId,
                $userName,
                $teamId,
                $teamTitle,
            );

            return;
        }

        if ($written === 'ok') {
            $report->addStudentCreate($userId, $userName, $teamId, $teamTitle, (string) $package->name, $payableCents);

            return;
        }

        if ($written === 'paid') {
            $report->addStudentSkip(
                MonthlyPricesProlongReport::REASON_ALREADY_PAID,
                $userId,
                $userName,
                $teamId,
                $teamTitle,
            );

            return;
        }

        $report->addStudentSkip(
            MonthlyPricesProlongReport::REASON_ALREADY_SET,
            $userId,
            $userName,
            $teamId,
            $teamTitle,
        );
    }

    /**
     * @param  array{discount_percent: int|null, discount_comment: string|null}  $snap
     * @return 'ok'|'paid'|'set'
     */
    private function writeStudentRow(
        User $student,
        int $teamId,
        string $targetMonth,
        LessonPackage $package,
        array $snap,
        int $payableCents,
        bool $isPostpay,
        ?int $actorId,
    ): string {
        $row = UserPrice::query()
            ->where('user_id', (int) $student->id)
            ->where('team_id', $teamId)
            ->where('new_month', $targetMonth)
            ->lockForUpdate()
            ->first();

        if ($row !== null && $row->effective_is_paid) {
            return 'paid';
        }

        $existingPackageId = $this->packageId($row?->lesson_package_id);
        if ($existingPackageId !== null || (int) ($row?->price_cents ?? 0) > 0) {
            return 'set';
        }

        if ($row === null) {
            $row = UserPrice::query()->create([
                'user_id' => (int) $student->id,
                'team_id' => $teamId,
                'new_month' => $targetMonth,
                'price_cents' => $isPostpay ? 0 : $payableCents,
                'lesson_package_id' => (int) $package->id,
                'is_paid' => false,
                'discount_percent' => $snap['discount_percent'],
                'discount_comment' => $snap['discount_comment'],
            ]);
        } else {
            $row->fill([
                'lesson_package_id' => (int) $package->id,
                'price_cents' => $isPostpay ? 0 : $payableCents,
                'discount_percent' => $snap['discount_percent'],
                'discount_comment' => $snap['discount_comment'],
            ]);
            $row->save();
        }

        $row->setRelation('lessonPackage', $package);

        if ($isPostpay) {
            $this->postpaySync->applyPackageToRow($row, $package);
        }

        $this->ulpSync->syncForUserPrice($row, $actorId);

        return 'ok';
    }

    /**
     * @param  list<int>  $teamIds
     * @return Collection<int, Collection<int, User>>
     */
    private function loadEnabledStudentsByTeam(array $teamIds): Collection
    {
        $teams = Team::query()
            ->whereIn('id', $teamIds)
            ->with(['students' => static function ($q) {
                $q->where('users.is_enabled', 1);
            }])
            ->get();

        $map = collect();
        foreach ($teams as $team) {
            $map->put((int) $team->id, $team->students);
        }

        return $map;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, UserPrice>  $rows
     * @return array<string, UserPrice>
     */
    private function indexUserPrices(Collection $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[$this->userPriceKey((int) $row->user_id, (int) $row->team_id)] = $row;
        }

        return $out;
    }

    private function userPriceKey(int $userId, int $teamId): string
    {
        return $userId.':'.$teamId;
    }

    private function packageId(mixed $value): ?int
    {
        $id = (int) ($value ?? 0);

        return $id > 0 ? $id : null;
    }
}
