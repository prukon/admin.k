<?php

namespace Database\Seeders;

use App\Models\FiscalReceipt;
use App\Models\PartnerLegalEntity;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Models\User;
use App\Services\PartnerLegalEntities\LegalEntityResolver;
use Carbon\Carbon;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevTbankHistorySeeder extends Seeder
{
    use GuardsDevSeedData;

    /** @var list<int> */
    private const DEV_PARTNER_IDS = [1, 2, 3];

    private const MAX_FROM_INTENTS = 40;

    /** @var int Слотов детерминированных платежей на партнёра (dev-seed-tp-{partner}-{slot}). */
    private const STANDALONE_SLOTS_PER_PARTNER = 4;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        if (DB::table('partners')->whereIn('id', self::DEV_PARTNER_IDS)->count() !== count(self::DEV_PARTNER_IDS)) {
            $this->command?->warn('[DevTbankHistorySeeder] Ожидаются партнёры с id 1–3 — пропуск.');

            return;
        }

        $resolver = app(LegalEntityResolver::class);
        $this->seedFromPaidPaymentIntents($resolver);
        $this->seedStandaloneHistory();
    }

    private function seedFromPaidPaymentIntents(LegalEntityResolver $resolver): int
    {
        /** @var Collection<int, PaymentIntent> $intents */
        $intents = PaymentIntent::query()
            ->where('provider', 'tbank')
            ->where('status', 'paid')
            ->whereIn('partner_id', self::DEV_PARTNER_IDS)
            ->whereNotNull('tbank_order_id')
            ->where('tbank_order_id', '!=', '')
            ->with(['payable'])
            ->orderByDesc('id')
            ->limit(self::MAX_FROM_INTENTS)
            ->get();

        $created = 0;

        foreach ($intents as $index => $intent) {
            $orderId = trim((string) $intent->tbank_order_id);
            if ($orderId === '') {
                continue;
            }

            if (TinkoffPayment::query()->where('order_id', $orderId)->exists()) {
                continue;
            }

            $partnerId = (int) $intent->partner_id;
            $user = $intent->user_id ? User::query()->find((int) $intent->user_id) : null;
            $payable = $intent->payable;
            $legalEntityId = $this->resolveLegalEntityId($resolver, $payable, $user, $partnerId);

            $amountCents = $this->amountToCents($intent->out_sum ?? $payable?->amount ?? 0);
            $method = $this->mapPaymentMethod((string) ($intent->payment_method ?? ''));

            $payment = TinkoffPayment::query()->create([
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'legal_entity_id' => $legalEntityId,
                'amount' => $amountCents,
                'method' => $method,
                'status' => 'CONFIRMED',
                'tinkoff_payment_id' => $this->devTbankPaymentId($intent),
                'deal_id' => 'dev-deal-intent-' . $intent->id,
                'payment_url' => null,
                'payload' => ['dev_seed' => true, 'payment_intent_id' => $intent->id],
                'confirmed_at' => $intent->paid_at ?? $intent->updated_at ?? now(),
                'canceled_at' => null,
            ]);

            ++$created;

            $this->maybeSeedPayout($payment, $index);
            $this->maybeSeedFiscalReceipt($payment, $intent, $payable, $legalEntityId, $index);
        }

        return $created;
    }

    private function seedStandaloneHistory(): void
    {
        if ($this->standaloneBatchComplete()) {
            return;
        }

        $entities = PartnerLegalEntity::query()
            ->whereIn('partner_id', self::DEV_PARTNER_IDS)
            ->where('is_enabled', true)
            ->whereNotNull('tinkoff_shop_code')
            ->where('tinkoff_shop_code', '!=', '')
            ->orderBy('id')
            ->get()
            ->groupBy('partner_id');

        if ($entities->isEmpty()) {
            return;
        }

        $methods = ['card', 'sbp', 'tpay'];
        $globalIndex = 0;

        foreach (self::DEV_PARTNER_IDS as $partnerId) {
            $partnerEntities = $entities->get($partnerId);

            if ($partnerEntities === null || $partnerEntities->isEmpty()) {
                continue;
            }

            for ($slot = 1; $slot <= self::STANDALONE_SLOTS_PER_PARTNER; ++$slot) {
                $orderId = 'dev-seed-tp-' . $partnerId . '-' . $slot;

                if (TinkoffPayment::query()->where('order_id', $orderId)->exists()) {
                    ++$globalIndex;

                    continue;
                }

                /** @var PartnerLegalEntity $entity */
                $entity = $partnerEntities->values()[($slot - 1) % $partnerEntities->count()];

                $amountCents = 50_000 + ($globalIndex * 17_500);

                $payment = TinkoffPayment::query()->create([
                    'order_id' => $orderId,
                    'partner_id' => $partnerId,
                    'legal_entity_id' => (int) $entity->id,
                    'amount' => $amountCents,
                    'method' => $methods[$globalIndex % count($methods)],
                    'status' => 'CONFIRMED',
                    'tinkoff_payment_id' => (string) (910_000_000 + ($partnerId * 10) + $slot),
                    'deal_id' => 'dev-deal-standalone-' . $partnerId . '-' . $slot,
                    'payload' => ['dev_seed' => true, 'standalone' => true],
                    'confirmed_at' => Carbon::now()->subDays($slot + $partnerId),
                ]);

                $this->maybeSeedPayout($payment, $globalIndex + 100);
                $this->maybeSeedStandaloneFiscalReceipt($payment, $entity, $globalIndex);

                ++$globalIndex;
            }
        }
    }

    private function standaloneBatchComplete(): bool
    {
        foreach (self::DEV_PARTNER_IDS as $partnerId) {
            for ($slot = 1; $slot <= self::STANDALONE_SLOTS_PER_PARTNER; ++$slot) {
                if (! TinkoffPayment::query()->where('order_id', 'dev-seed-tp-' . $partnerId . '-' . $slot)->exists()) {
                    return false;
                }
            }
        }

        return true;
    }

    private function maybeSeedPayout(TinkoffPayment $payment, int $index): void
    {
        if (TinkoffPayout::query()->where('payment_id', (int) $payment->id)->exists()) {
            return;
        }

        // ~55% COMPLETED, ~15% REJECTED, остальное — без выплаты (ожидает ручной).
        if ($index % 5 === 4) {
            return;
        }

        $factory = \Database\Factories\TinkoffPayoutFactory::new()->forPayment($payment);

        if ($index % 7 === 6) {
            $factory->rejected()->autoSource()->create();

            return;
        }

        $factory->completed()->create([
            'source' => $index % 2 === 0 ? 'auto' : 'manual',
        ]);
    }

    private function maybeSeedFiscalReceipt(
        TinkoffPayment $payment,
        PaymentIntent $intent,
        ?Payable $payable,
        ?int $legalEntityId,
        int $index,
    ): void {
        if ($index % 2 !== 0) {
            return;
        }

        $idempotencyKey = 'income:pi:' . $intent->id;

        if (FiscalReceipt::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        $factory = \Database\Factories\FiscalReceiptFactory::new()
            ->forPaymentIntent($intent, $payable);

        if ($index % 9 === 8) {
            $factory->errored()->create([
                'legal_entity_id' => $legalEntityId,
            ]);

            return;
        }

        $factory->processed()->create([
            'legal_entity_id' => $legalEntityId,
        ]);
    }

    private function maybeSeedStandaloneFiscalReceipt(TinkoffPayment $payment, PartnerLegalEntity $entity, int $index): void
    {
        if ($index % 3 !== 0) {
            return;
        }

        $idempotencyKey = 'income:dev-tp:' . $payment->id;

        if (FiscalReceipt::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        \Database\Factories\FiscalReceiptFactory::new()
            ->forPartner((int) $payment->partner_id)
            ->processed()
            ->create([
                'legal_entity_id' => (int) $entity->id,
                'amount' => number_format($payment->amount / 100, 2, '.', ''),
                'invoice_id' => 'tp_' . $payment->id,
                'idempotency_key' => $idempotencyKey,
            ]);
    }

    private function resolveLegalEntityId(
        LegalEntityResolver $resolver,
        ?Payable $payable,
        ?User $user,
        int $partnerId,
    ): ?int {
        if ($payable !== null) {
            $entityId = $resolver->resolveLegalEntityId($resolver->forPayable($payable, $user));
            if ($entityId !== null) {
                return $entityId;
            }
        }

        return PartnerLegalEntity::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');
    }

    private function mapPaymentMethod(string $paymentMethod): ?string
    {
        return match ($paymentMethod) {
            'card' => 'card',
            'sbp_qr', 'sbp' => 'sbp',
            'tpay' => 'tpay',
            default => null,
        };
    }

    private function amountToCents(mixed $amount): int
    {
        if (is_numeric($amount)) {
            return (int) round(((float) $amount) * 100);
        }

        return 50_000;
    }

    private function devTbankPaymentId(PaymentIntent $intent): string
    {
        $fromIntent = (int) ($intent->tbank_payment_id ?? 0);
        if ($fromIntent > 0) {
            return (string) $fromIntent;
        }

        return (string) (820_000_000 + (int) $intent->id);
    }
}
