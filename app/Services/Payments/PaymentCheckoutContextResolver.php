<?php

namespace App\Services\Payments;

use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use App\Services\Tinkoff\TbankTerminalConfig;
use App\Support\Payments\PaymentCheckoutContext;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PaymentCheckoutContextResolver
{
    public function __construct(
        private readonly PayableTeamResolver $payableTeamResolver,
        private readonly PaymentCheckoutLegalEntityPresenter $presenter,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function forUserPaymentPage(
        Partner $partner,
        User $user,
        string $paymentKind,
        ?int $monthlyTeamId,
        ?UserCustomPayment $customPayment,
        ?UserLessonPackage $lessonPackage,
        bool $hasMonthlyPeriod,
        bool $canTbankCard,
        bool $canTbankSbp,
    ): PaymentCheckoutContext {
        if (! TbankTerminalConfig::isGloballyActive() || (! $canTbankCard && ! $canTbankSbp)) {
            return PaymentCheckoutContext::withoutTbankInstrument();
        }

        $partnerId = (int) $partner->id;
        $payableType = $this->payableType($paymentKind, $hasMonthlyPeriod);
        $paymentTeamId = $this->tryResolveTeamId(
            $payableType,
            $partnerId,
            $user,
            null,
            $monthlyTeamId,
            $customPayment,
            $lessonPackage,
        );

        if ($paymentTeamId === null) {
            return new PaymentCheckoutContext(
                paymentTeamId: null,
                serviceProviderLabel: null,
                showTbankLegalEntityBlock: true,
                tbankLegalEntityReady: false,
                tbankCardAvailable: false,
                tbankSbpAvailable: false,
            );
        }

        return $this->contextForTeam(
            $partner,
            $paymentTeamId,
            $canTbankCard,
            $canTbankSbp,
        );
    }

    /**
     * @param  list<array{id: int, title: string}>  $studentTeams
     * @return array<int, array{card: bool, sbp: bool, serviceProviderLabel: ?string}>
     */
    public function clubFeeTeamCheckoutMap(
        Partner $partner,
        array $studentTeams,
        bool $canTbankCard,
        bool $canTbankSbp,
    ): array {
        $partnerId = (int) $partner->id;
        $map = [];

        foreach ($studentTeams as $teamRow) {
            $teamId = (int) ($teamRow['id'] ?? 0);
            if ($teamId <= 0) {
                continue;
            }

            $team = Team::query()
                ->whereKey($teamId)
                ->where('partner_id', $partnerId)
                ->first();

            $ready = $team !== null && $this->paymentService->isTbankAvailable($partner, $team);

            $map[$teamId] = [
                'card' => $ready && $canTbankCard,
                'sbp' => $ready && $canTbankSbp,
                'serviceProviderLabel' => $ready
                    ? $this->presenter->labelForTeam($partnerId, $team)
                    : null,
            ];
        }

        return $map;
    }

    public function clubFeeContext(
        Partner $partner,
        ?int $defaultTeamId,
        bool $requiresTeamChoice,
        bool $canTbankCard,
        bool $canTbankSbp,
    ): PaymentCheckoutContext {
        if (! TbankTerminalConfig::isGloballyActive() || (! $canTbankCard && ! $canTbankSbp)) {
            return PaymentCheckoutContext::withoutTbankInstrument();
        }

        if ($requiresTeamChoice || $defaultTeamId === null || $defaultTeamId <= 0) {
            return new PaymentCheckoutContext(
                paymentTeamId: null,
                serviceProviderLabel: null,
                showTbankLegalEntityBlock: true,
                tbankLegalEntityReady: false,
                tbankCardAvailable: false,
                tbankSbpAvailable: false,
            );
        }

        return $this->contextForTeam(
            $partner,
            $defaultTeamId,
            $canTbankCard,
            $canTbankSbp,
        );
    }

    private function contextForTeam(
        Partner $partner,
        int $paymentTeamId,
        bool $canTbankCard,
        bool $canTbankSbp,
    ): PaymentCheckoutContext {
        $partnerId = (int) $partner->id;
        $checkoutTeam = Team::query()
            ->whereKey($paymentTeamId)
            ->where('partner_id', $partnerId)
            ->first();

        $ready = $checkoutTeam !== null && $this->paymentService->isTbankAvailable($partner, $checkoutTeam);
        $label = $ready && $checkoutTeam !== null
            ? $this->presenter->labelForTeam($partnerId, $checkoutTeam)
            : null;

        return new PaymentCheckoutContext(
            paymentTeamId: $paymentTeamId,
            serviceProviderLabel: $label,
            showTbankLegalEntityBlock: true,
            tbankLegalEntityReady: $ready,
            tbankCardAvailable: $ready && $canTbankCard,
            tbankSbpAvailable: $ready && $canTbankSbp,
        );
    }

    private function payableType(string $paymentKind, bool $hasMonthlyPeriod): string
    {
        if ($paymentKind === 'custom_payment') {
            return 'custom_payment_fee';
        }

        if ($paymentKind === 'lesson_package') {
            return 'lesson_package_fee';
        }

        if ($hasMonthlyPeriod) {
            return 'monthly_fee';
        }

        return 'club_fee';
    }

    private function tryResolveTeamId(
        string $payableType,
        int $partnerId,
        User $user,
        ?int $requestTeamId,
        ?int $monthlyTeamId,
        ?UserCustomPayment $customPayment,
        ?UserLessonPackage $lessonPackage,
    ): ?int {
        try {
            if ($payableType === 'monthly_fee') {
                $teamId = (int) ($monthlyTeamId ?? 0);
                if ($teamId <= 0) {
                    return null;
                }

                return $teamId;
            }

            return $this->payableTeamResolver->resolveOrAbort(
                $payableType,
                $partnerId,
                $user,
                $requestTeamId,
                $customPayment,
                $lessonPackage,
            );
        } catch (HttpException) {
            return null;
        }
    }
}
