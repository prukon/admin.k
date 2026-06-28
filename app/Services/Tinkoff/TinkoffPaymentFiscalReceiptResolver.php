<?php

namespace App\Services\Tinkoff;

use App\Models\FiscalReceipt;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\TinkoffPayment;
use App\Support\FiscalReceipts\FiscalReceiptUrl;
use Illuminate\Support\Collection;

final class TinkoffPaymentFiscalReceiptResolver
{
    /**
     * @return array{
     *     income: array{status: ?string, url: ?string, has_url: bool, receipt_datetime: ?\Illuminate\Support\Carbon, hint: string},
     *     return: array{status: ?string, url: ?string, has_url: bool, receipt_datetime: ?\Illuminate\Support\Carbon, hint: string}
     * }
     */
    public function resolve(TinkoffPayment $payment): array
    {
        return [
            'income' => $this->resolveByType($payment, FiscalReceipt::TYPE_INCOME),
            'return' => $this->resolveByType($payment, FiscalReceipt::TYPE_INCOME_RETURN),
        ];
    }

    public function resolveLedgerPaymentId(TinkoffPayment $payment): ?int
    {
        return $this->resolveLedgerPaymentIdInternal($payment);
    }

    public function findLatestReceipt(TinkoffPayment $payment, string $type): ?FiscalReceipt
    {
        return $this->findLatestReceiptInternal($payment, $type);
    }

    /**
     * @return array{status: ?string, url: ?string, has_url: bool, receipt_datetime: ?\Illuminate\Support\Carbon, hint: string}
     */
    private function resolveByType(TinkoffPayment $payment, string $type): array
    {
        $receipt = $this->findLatestReceiptInternal($payment, $type);
        $url = FiscalReceiptUrl::isPublicDisplayUrl($receipt?->receipt_url)
            ? trim((string) $receipt->receipt_url)
            : null;

        return [
            'status' => $receipt?->status,
            'url' => $url,
            'has_url' => $url !== null,
            'receipt_datetime' => $receipt?->receipt_datetime,
            'hint' => $this->buildHint($type, $receipt, $url),
        ];
    }

    private function findLatestReceiptInternal(TinkoffPayment $payment, string $type): ?FiscalReceipt
    {
        $partnerId = (int) $payment->partner_id;
        $ledgerPaymentId = $this->resolveLedgerPaymentIdInternal($payment);
        $intentLinks = $this->resolvePaymentIntentLinks($payment);

        $intentIds = $intentLinks->pluck('id')->filter()->values()->all();
        $payableIds = $intentLinks->pluck('payable_id')->filter()->values()->all();

        if ($ledgerPaymentId === null && $intentIds === [] && $payableIds === []) {
            return null;
        }

        return FiscalReceipt::query()
            ->where('partner_id', $partnerId)
            ->where('type', $type)
            ->where(function ($query) use ($ledgerPaymentId, $intentIds, $payableIds) {
                if ($ledgerPaymentId !== null) {
                    $query->orWhere('payment_id', $ledgerPaymentId);
                }
                if ($intentIds !== []) {
                    $query->orWhereIn('payment_intent_id', $intentIds);
                }
                if ($payableIds !== []) {
                    $query->orWhereIn('payable_id', $payableIds);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    private function resolveLedgerPaymentIdInternal(TinkoffPayment $payment): ?int
    {
        if (! empty($payment->tinkoff_payment_id)) {
            $paymentId = Payment::query()
                ->where('partner_id', (int) $payment->partner_id)
                ->where('payment_number', (string) $payment->tinkoff_payment_id)
                ->value('id');

            if ($paymentId) {
                return (int) $paymentId;
            }
        }

        if (! empty($payment->deal_id)) {
            $paymentId = Payment::query()
                ->where('partner_id', (int) $payment->partner_id)
                ->where('deal_id', (string) $payment->deal_id)
                ->orderByDesc('id')
                ->value('id');

            if ($paymentId) {
                return (int) $paymentId;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, PaymentIntent>
     */
    private function resolvePaymentIntentLinks(TinkoffPayment $payment): Collection
    {
        $query = PaymentIntent::query()
            ->where('partner_id', (int) $payment->partner_id)
            ->where('provider', 'tbank');

        $query->where(function ($q) use ($payment) {
            $hasCondition = false;

            if (! empty($payment->order_id)) {
                $q->orWhere('tbank_order_id', (string) $payment->order_id);
                $hasCondition = true;
            }

            if (! empty($payment->tinkoff_payment_id)) {
                $bankPaymentId = (int) $payment->tinkoff_payment_id;
                $q->orWhere('tbank_payment_id', $bankPaymentId)
                    ->orWhere('provider_inv_id', $bankPaymentId);
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $q->whereRaw('1 = 0');
            }
        });

        return $query->orderByDesc('id')->get(['id', 'payable_id']);
    }

    private function buildHint(string $type, ?FiscalReceipt $receipt, ?string $url): string
    {
        if ($url !== null) {
            return $type === FiscalReceipt::TYPE_INCOME_RETURN
                ? 'Чек возврата сформирован'
                : 'Чек сформирован';
        }

        if ($receipt === null) {
            return $type === FiscalReceipt::TYPE_INCOME_RETURN
                ? 'Чек возврата не создан'
                : 'Чек не сформирован';
        }

        return match ((string) $receipt->status) {
            FiscalReceipt::STATUS_PENDING, FiscalReceipt::STATUS_QUEUED => $type === FiscalReceipt::TYPE_INCOME_RETURN
                ? 'Чек возврата ожидается (CloudKassir)'
                : 'Чек формируется (CloudKassir)',
            FiscalReceipt::STATUS_ERROR => $type === FiscalReceipt::TYPE_INCOME_RETURN
                ? 'Чек возврата: ошибка формирования'
                : 'Чек: ошибка формирования',
            FiscalReceipt::STATUS_PROCESSED => $type === FiscalReceipt::TYPE_INCOME_RETURN
                ? 'Чек возврата не сформирован'
                : 'Чек не сформирован',
            default => $type === FiscalReceipt::TYPE_INCOME_RETURN
                ? 'Чек возврата не сформирован'
                : 'Чек не сформирован',
        };
    }
}
