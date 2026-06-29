<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Team;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use App\Services\Payments\PayableTeamResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesPaymentCheckoutTeam
{
    /**
     * @throws HttpException
     */
    protected function resolvePaymentTeamId(
        string $payableType,
        int $partnerId,
        User $user,
        ?int $requestTeamId,
        ?int $monthlyTeamId,
        ?UserCustomPayment $customPayment,
        ?UserLessonPackage $lessonPackage,
    ): int {
        if ($payableType === 'monthly_fee') {
            $teamId = (int) ($monthlyTeamId ?? 0);
            if ($teamId <= 0) {
                throw new HttpException(422, 'Укажите группу для оплаты.');
            }

            return $teamId;
        }

        return app(PayableTeamResolver::class)->resolveOrAbort(
            $payableType,
            $partnerId,
            $user,
            $requestTeamId,
            $customPayment,
            $lessonPackage,
        );
    }

    protected function checkoutTeamOrNull(int $paymentTeamId, int $partnerId): ?Team
    {
        if ($paymentTeamId <= 0) {
            return null;
        }

        return Team::query()
            ->whereKey($paymentTeamId)
            ->where('partner_id', $partnerId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function payableMetaWithTeam(array $meta, int $paymentTeamId): array
    {
        if ($paymentTeamId > 0) {
            $meta['team_id'] = $paymentTeamId;
        }

        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    protected function paymentIntentMetaWithTeam(string $userName, int $paymentTeamId): array
    {
        $meta = ['user_name' => $userName];
        if ($paymentTeamId > 0) {
            $meta['team_id'] = $paymentTeamId;
        }

        return $meta;
    }
}
