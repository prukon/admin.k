<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\Payments\UserPricePublicPayService;
use App\Services\Postpay\PostpayAmountCalculator;
use App\Services\SettingPrices\UsersPriceLessonPackageSync;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Пересчёт неоплаченных users_prices при смене процента персональной скидки.
 * Ручная сумма (не совпадает с формулой снимка) не трогается.
 */
final class UserUnpaidPriceDiscountRecalc
{
    public function __construct(
        private readonly PostpayAmountCalculator $postpayCalculator,
        private readonly UsersPriceLessonPackageSync $lessonPackageSync,
        private readonly UserPricePublicPayService $publicPay,
    ) {
    }

    /**
     * @return list<array{
     *     id: int,
     *     month_label: string,
     *     team_title: string,
     *     kind_label: string,
     *     current_label: string,
     *     new_label: string
     * }>
     */
    public function preview(User $user, int $newPercent): array
    {
        $rows = [];
        foreach ($this->candidates($user) as $row) {
            $change = $this->changeFor($row, $newPercent);
            if ($change === null) {
                continue;
            }

            $rows[] = [
                'id' => (int) $row->id,
                'month_label' => $this->monthLabel($row),
                'team_title' => (string) ($row->team?->title ?? ''),
                'kind_label' => $change['kind_label'],
                'current_label' => Money::formatRub($change['current_cents']).' ₽',
                'new_label' => Money::formatRub($change['new_cents']).' ₽',
            ];
        }

        return $rows;
    }

    public function apply(User $user, ?int $actorId = null): int
    {
        $percent = UserPercentDiscount::percent($user);
        $comment = UserPercentDiscount::comment($user);
        $count = 0;

        foreach ($this->candidates($user) as $row) {
            $change = $this->changeFor($row, $percent);
            if ($change === null) {
                continue;
            }

            $row->price_cents = $change['new_cents'];
            $row->discount_percent = $percent >= 1 ? $percent : null;
            $row->discount_comment = $percent >= 1 ? $comment : null;
            $row->save();

            $package = $row->lessonPackage;
            if ($package && ! $package->isPostpay()) {
                $this->lessonPackageSync->syncForUserPrice($row, $actorId);
            }

            $this->publicPay->invalidateActivePaymentAfterAmountChange($row->fresh() ?? $row);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<UserPrice>
     */
    private function candidates(User $user): array
    {
        return UserPrice::query()
            ->where('user_id', (int) $user->id)
            ->whereNotNull('lesson_package_id')
            ->with(['lessonPackage', 'team'])
            ->orderBy('new_month')
            ->orderBy('team_id')
            ->get()
            ->filter(static fn (UserPrice $row) => ! $row->effective_is_paid)
            ->values()
            ->all();
    }

    /**
     * @return array{kind_label: string, current_cents: int, new_cents: int}|null
     */
    private function changeFor(UserPrice $row, int $newPercent): ?array
    {
        $package = $row->lessonPackage;
        if (! $package instanceof LessonPackage) {
            return null;
        }

        $currentCents = (int) ($row->price_cents ?? 0);
        $snapshotPercent = (int) ($row->discount_percent ?? 0);
        if ($snapshotPercent < 1) {
            $snapshotPercent = 0;
        }

        if ($package->isPostpay()) {
            $calc = $this->postpayCalculator->forUserPrice($row, $package);
            $grossCents = $calc['visits'] * $calc['price_per_lesson_cents'];
            $expectedCents = $snapshotPercent >= 1
                ? Money::payableAfterDiscountCents($grossCents, $snapshotPercent)
                : $grossCents;
            if ($currentCents !== $expectedCents) {
                return null;
            }

            $newCents = $newPercent >= 1
                ? Money::payableAfterDiscountCents($grossCents, $newPercent)
                : $grossCents;
            if ($newCents === $currentCents) {
                return null;
            }

            return [
                'kind_label' => 'Постоплата',
                'current_cents' => $currentCents,
                'new_cents' => $newCents,
            ];
        }

        if (! in_array((string) $package->schedule_type, LessonPackage::ASSIGNMENT_SCHEDULE_TYPES, true)) {
            return null;
        }

        $catalogCents = (int) $package->price_cents;
        $expectedCents = $snapshotPercent >= 1
            ? Money::payableAfterDiscountCents($catalogCents, $snapshotPercent)
            : $catalogCents;
        if ($currentCents !== $expectedCents) {
            return null;
        }

        $newCents = $newPercent >= 1
            ? Money::payableAfterDiscountCents($catalogCents, $newPercent)
            : $catalogCents;
        if ($newCents === $currentCents) {
            return null;
        }

        return [
            'kind_label' => 'Предоплата',
            'current_cents' => $currentCents,
            'new_cents' => $newCents,
        ];
    }

    private function monthLabel(UserPrice $row): string
    {
        $raw = (string) $row->new_month;
        if ($raw === '') {
            return '';
        }

        return Str::ucfirst(Carbon::parse($raw)->locale('ru')->translatedFormat('F Y'));
    }
}
