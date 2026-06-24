<?php

namespace App\Support\Payments;

use App\Models\Payment;
use App\Services\TeamUserSyncService;

final class PaymentTeamTitleDisplay
{
    public static function forRow(Payment $payment, TeamUserSyncService $teamUserSync): string
    {
        if ((int) ($payment->team_id ?? 0) > 0) {
            if ($payment->relationLoaded('paidTeam') && $payment->paidTeam) {
                $title = trim((string) ($payment->paidTeam->title ?? ''));

                if ($title !== '') {
                    return $title;
                }
            }

            $stored = trim((string) ($payment->team_title ?? ''));
            if ($stored !== '') {
                return $stored;
            }
        }

        $stored = trim((string) ($payment->team_title ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        if (! $payment->user) {
            return 'Без команды';
        }

        $label = $teamUserSync->teamTitlesLabel($payment->user);

        return $label !== '' ? $label : 'Без команды';
    }
}
