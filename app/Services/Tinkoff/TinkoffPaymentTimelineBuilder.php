<?php

namespace App\Services\Tinkoff;

use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class TinkoffPaymentTimelineBuilder
{
    private const PAYOUT_FINAL_STATUSES = ['COMPLETED', 'REJECTED'];

    /**
     * @param  Collection<int, TinkoffPayout>|array<int, TinkoffPayout>  $payouts
     * @return list<array{key: string, label: string, state: string, at: ?Carbon, hint: ?string}>
     */
    public function build(TinkoffPayment $payment, Collection|array $payouts = []): array
    {
        $payouts = $payouts instanceof Collection ? $payouts : collect($payouts);
        /** @var TinkoffPayout|null $latestPayout */
        $latestPayout = $payouts->sortBy('id')->last();

        $paymentStatus = strtoupper((string) $payment->status);
        $paymentFailed = in_array($paymentStatus, ['REJECTED', 'CANCELED'], true);
        $paymentConfirmed = $paymentStatus === 'CONFIRMED' || $payment->confirmed_at !== null;

        $steps = [];

        $steps[] = [
            'key' => 'payment_init',
            'label' => 'Платёжный запрос',
            'state' => 'done',
            'at' => $payment->created_at,
            'hint' => $payment->order_id ? 'Order '.$payment->order_id : null,
        ];

        $confirmState = 'pending';
        $confirmAt = null;
        $confirmHint = null;

        if ($paymentFailed) {
            $confirmState = 'failed';
            $confirmAt = $payment->canceled_at ?? $payment->updated_at;
            $confirmHint = $paymentStatus === 'CANCELED' ? 'Оплата отменена' : 'Оплата отклонена';
        } elseif ($paymentConfirmed) {
            $confirmState = 'done';
            $confirmAt = $payment->confirmed_at ?? $payment->updated_at;
            if (! empty($payment->deal_id)) {
                $confirmHint = 'Deal '.$payment->deal_id;
            } else {
                $confirmHint = 'Ожидаем DealId от банка';
            }
        } elseif (in_array($paymentStatus, ['NEW', 'FORM', 'AUTHORIZING', 'CHECKING', '3DS_CHECKING'], true)) {
            $confirmState = 'active';
            $confirmHint = 'Статус: '.$paymentStatus;
        }

        $steps[] = [
            'key' => 'payment_confirmed',
            'label' => 'Оплата подтверждена',
            'state' => $confirmState,
            'at' => $confirmAt,
            'hint' => $confirmHint,
        ];

        $payoutCreateState = 'pending';
        $payoutCreateAt = null;
        $payoutCreateHint = null;

        if ($latestPayout !== null) {
            $payoutCreateState = 'done';
            $payoutCreateAt = $latestPayout->created_at;
            $source = trim((string) ($latestPayout->source ?? ''));
            $payoutCreateHint = $source !== '' ? 'Источник: '.$source : null;
            if ($latestPayout->when_to_run && $latestPayout->when_to_run->isFuture()) {
                $payoutCreateHint = 'Запланирована на '.$latestPayout->when_to_run->format('d.m.Y H:i');
            }
        } elseif ($paymentConfirmed && ! empty($payment->deal_id) && ! $paymentFailed) {
            $payoutCreateHint = 'Выплата ещё не создана';
        }

        $steps[] = [
            'key' => 'payout_created',
            'label' => 'Создана выплата',
            'state' => $payoutCreateState,
            'at' => $payoutCreateAt,
            'hint' => $payoutCreateHint,
        ];

        $payoutCompleteState = 'pending';
        $payoutCompleteAt = null;
        $payoutCompleteHint = null;

        if ($latestPayout !== null) {
            $payoutStatus = strtoupper((string) $latestPayout->status);

            if ($payoutStatus === 'COMPLETED') {
                $payoutCompleteState = 'done';
                $payoutCompleteAt = $latestPayout->completed_at ?? $latestPayout->updated_at;
            } elseif ($payoutStatus === 'REJECTED') {
                $payoutCompleteState = 'failed';
                $payoutCompleteAt = $latestPayout->completed_at ?? $latestPayout->updated_at;
                $payoutCompleteHint = 'Выплата отклонена';
            } elseif (! in_array($payoutStatus, self::PAYOUT_FINAL_STATUSES, true)) {
                $payoutCompleteState = 'active';
                $payoutCompleteHint = 'Статус: '.$payoutStatus;
            }
        }

        $steps[] = [
            'key' => 'payout_completed',
            'label' => 'Выплата выполнена',
            'state' => $payoutCompleteState,
            'at' => $payoutCompleteAt,
            'hint' => $payoutCompleteHint,
        ];

        return $steps;
    }
}
