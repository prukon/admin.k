<?php

namespace App\Services\Tinkoff;

use App\Models\FiscalReceipt;
use App\Models\Refund;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Support\FiscalReceipts\FiscalReceiptUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class TinkoffPaymentTimelineBuilder
{
    private const PAYOUT_FINAL_STATUSES = ['COMPLETED', 'REJECTED'];

    public function __construct(
        private readonly TinkoffPaymentFiscalReceiptResolver $fiscalReceiptResolver,
    ) {}

    /**
     * @param  Collection<int, TinkoffPayout>|array<int, TinkoffPayout>  $payouts
     * @return list<array{key: string, label: string, state: string, at: ?Carbon, hint: ?string, url: ?string}>
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
            'url' => null,
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
            'url' => null,
        ];

        $incomeReceipt = $this->fiscalReceiptResolver->findLatestReceipt($payment, FiscalReceipt::TYPE_INCOME);
        $steps[] = $this->buildFiscalReceiptStep(
            'fiscal_income',
            'Чек оплаты',
            $incomeReceipt,
            $paymentConfirmed && ! $paymentFailed,
        );

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
            'url' => null,
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
            'url' => null,
        ];

        $latestRefund = $this->findLatestRefund($payment);
        $returnReceipt = $this->fiscalReceiptResolver->findLatestReceipt($payment, FiscalReceipt::TYPE_INCOME_RETURN);

        if ($latestRefund !== null) {
            $steps[] = $this->buildRefundStep($latestRefund);
            $steps[] = $this->buildFiscalReceiptStep(
                'fiscal_return',
                'Чек возврата',
                $returnReceipt,
                true,
            );
        }

        return $steps;
    }

    /**
     * @return array{key: string, label: string, state: string, at: ?Carbon, hint: ?string, url: ?string}
     */
    private function buildFiscalReceiptStep(
        string $key,
        string $label,
        ?FiscalReceipt $receipt,
        bool $prerequisiteMet,
    ): array {
        $url = FiscalReceiptUrl::isPublicDisplayUrl($receipt?->receipt_url)
            ? trim((string) $receipt->receipt_url)
            : null;

        if (! $prerequisiteMet) {
            return [
                'key' => $key,
                'label' => $label,
                'state' => 'pending',
                'at' => null,
                'hint' => 'После подтверждения оплаты',
                'url' => null,
            ];
        }

        if ($url !== null) {
            return [
                'key' => $key,
                'label' => $label,
                'state' => 'done',
                'at' => $receipt?->receipt_datetime ?? $receipt?->processed_at,
                'hint' => $key === 'fiscal_return' ? 'Открыть чек возврата' : 'Открыть чек',
                'url' => $url,
            ];
        }

        if ($receipt === null) {
            return [
                'key' => $key,
                'label' => $label,
                'state' => 'pending',
                'at' => null,
                'hint' => 'Чек не сформирован',
                'url' => null,
            ];
        }

        return match ((string) $receipt->status) {
            FiscalReceipt::STATUS_PENDING, FiscalReceipt::STATUS_QUEUED => [
                'key' => $key,
                'label' => $label,
                'state' => 'active',
                'at' => $receipt->queued_at ?? $receipt->created_at,
                'hint' => 'Формируется (CloudKassir)',
                'url' => null,
            ],
            FiscalReceipt::STATUS_ERROR => [
                'key' => $key,
                'label' => $label,
                'state' => 'failed',
                'at' => $receipt->failed_at ?? $receipt->updated_at,
                'hint' => trim((string) ($receipt->error_message ?? '')) !== ''
                    ? (string) $receipt->error_message
                    : 'Ошибка формирования',
                'url' => null,
            ],
            default => [
                'key' => $key,
                'label' => $label,
                'state' => 'pending',
                'at' => $receipt->processed_at,
                'hint' => 'Чек не сформирован',
                'url' => null,
            ],
        };
    }

    /**
     * @return array{key: string, label: string, state: string, at: ?Carbon, hint: ?string, url: ?string}
     */
    private function buildRefundStep(Refund $refund): array
    {
        $status = (string) $refund->status;

        return match ($status) {
            'succeeded' => [
                'key' => 'refund',
                'label' => 'Возврат',
                'state' => 'done',
                'at' => $refund->processed_at ?? $refund->updated_at,
                'hint' => 'Возврат выполнен в T‑Bank',
                'url' => null,
            ],
            'pending' => [
                'key' => 'refund',
                'label' => 'Возврат',
                'state' => 'active',
                'at' => $refund->created_at,
                'hint' => 'Возврат в обработке',
                'url' => null,
            ],
            'failed' => [
                'key' => 'refund',
                'label' => 'Возврат',
                'state' => 'failed',
                'at' => $refund->updated_at,
                'hint' => 'Возврат не выполнен',
                'url' => null,
            ],
            default => [
                'key' => 'refund',
                'label' => 'Возврат',
                'state' => 'active',
                'at' => $refund->created_at,
                'hint' => 'Статус: '.$status,
                'url' => null,
            ],
        };
    }

    private function findLatestRefund(TinkoffPayment $payment): ?Refund
    {
        $ledgerPaymentId = $this->fiscalReceiptResolver->resolveLedgerPaymentId($payment);
        if ($ledgerPaymentId === null) {
            return null;
        }

        return Refund::query()
            ->where('payment_id', $ledgerPaymentId)
            ->orderByDesc('id')
            ->first();
    }
}
