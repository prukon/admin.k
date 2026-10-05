<?php

declare(strict_types=1);

namespace App\Services\PartnerWallet;

use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use App\Models\PartnerWalletInvoice;
use App\Models\User;
use App\Support\Money;
use App\Support\RubAmountInWords;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PartnerWalletInvoiceService
{
    public const NO_LEGAL_ENTITY_MESSAGE = 'У школы нет включённого юрлица. Счёт выставить нельзя.';

    /**
     * Включённое юрлицо покупателя: сначала основное, иначе самое раннее.
     */
    public function buyer(Partner $partner): ?PartnerLegalEntity
    {
        return $partner->legalEntities()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function issue(Partner $partner, User $user, float $amountRub): PartnerWalletInvoice
    {
        $buyer = $this->buyer($partner);
        if ($buyer === null) {
            throw ValidationException::withMessages([
                'payment_method' => self::NO_LEGAL_ENTITY_MESSAGE,
            ]);
        }

        $seller = $this->sellerSnapshot();
        $issuedOn = now()->toDateString();
        $itemName = (string) config('platform_invoice.item_name');
        $vatNote = (string) config('platform_invoice.vat_note');

        return DB::transaction(function () use ($partner, $user, $buyer, $seller, $amountRub, $issuedOn, $itemName, $vatNote) {
            $invoice = PartnerWalletInvoice::query()->create([
                'partner_id' => (int) $partner->id,
                'user_id' => (int) $user->id,
                'legal_entity_id' => (int) $buyer->id,
                'number' => 'd'.bin2hex(random_bytes(8)),
                'amount_cents' => Money::toCentsOrFail($amountRub),
                'currency' => 'RUB',
                'item_name' => $itemName,
                'payment_purpose' => '',
                'vat_note' => $vatNote,
                'issued_on' => $issuedOn,
                'buyer' => $this->buyerSnapshot($buyer),
                'seller' => $seller,
            ]);

            $number = (string) $invoice->id;
            $date = \Illuminate\Support\Carbon::parse($issuedOn)->format('d.m.Y');
            $invoice->number = $number;
            $invoice->payment_purpose = 'Пополнение баланса KidsCRM. Оплата по счёту № '.$number
                .' от '.$date.'. НДС не облагается.';
            $invoice->save();

            return $invoice;
        });
    }

    public function amountInWords(PartnerWalletInvoice $invoice): string
    {
        return RubAmountInWords::spell((int) $invoice->amount_cents);
    }

    /**
     * @return array{name: string, inn: string, kpp: string, address: string}
     */
    private function buyerSnapshot(PartnerLegalEntity $entity): array
    {
        $address = array_values(array_filter([
            trim((string) ($entity->zip ?? '')),
            trim((string) ($entity->city ?? '')),
            trim((string) ($entity->address ?? '')),
        ], static fn (string $part): bool => $part !== ''));

        return [
            'name' => $entity->displayTitle(),
            'inn' => trim((string) ($entity->tax_id ?? '')),
            'kpp' => trim((string) ($entity->kpp ?? '')),
            'address' => implode(', ', $address),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sellerSnapshot(): array
    {
        $seller = config('platform_invoice.seller');
        if (! is_array($seller)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Реквизиты продавца для счёта не настроены.',
            ]);
        }

        $snapshot = [];
        foreach (['name', 'inn', 'ogrnip', 'address', 'sign_name', 'bank_name', 'bank_inn', 'bik', 'corr_account', 'account'] as $key) {
            $value = trim((string) ($seller[$key] ?? ''));
            if ($value === '' && $key !== 'address') {
                throw ValidationException::withMessages([
                    'payment_method' => 'Реквизиты продавца для счёта не настроены.',
                ]);
            }
            $snapshot[$key] = $value;
        }

        return $snapshot;
    }
}
