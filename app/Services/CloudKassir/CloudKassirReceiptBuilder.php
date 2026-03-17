<?php

namespace App\Services\CloudKassir;

use App\Models\FiscalReceipt;
use App\Models\Partner;
use App\Models\Payable;
use App\Models\PaymentIntent;
use RuntimeException;

class CloudKassirReceiptBuilder
{
    public function build(FiscalReceipt $fiscalReceipt): array
    {
        $fiscalReceipt->loadMissing([
            'partner',
            'payable',
            'paymentIntent',
        ]);

        /** @var Partner|null $partner */
        $partner = $fiscalReceipt->partner;

        /** @var Payable|null $payable */
        $payable = $fiscalReceipt->payable;

        /** @var PaymentIntent|null $paymentIntent */
        $paymentIntent = $fiscalReceipt->paymentIntent;

        if (!$partner) {
            throw new RuntimeException('FiscalReceipt partner not found.');
        }

        if (!$payable) {
            throw new RuntimeException('FiscalReceipt payable not found.');
        }

        if (!$partner->tax_id) {
            throw new RuntimeException("Partner #{$partner->id} has no tax_id.");
        }

        if ($partner->taxation_system === null) {
            throw new RuntimeException("Partner #{$partner->id} has no taxation_system.");
        }

        $platformInn = (string) config('services.cloudkassir.inn', '');
        if ($platformInn === '') {
            throw new RuntimeException('CLOUDKASSIR_INN is not set in .env (ИНН платформы, на который зарегистрирована касса).');
        }

        $label = $this->makeLabel($payable);
        $amount = $this->normalizeMoney($fiscalReceipt->amount ?: $payable->amount);

        $item = [
            'Label' => $label,
            'Price' => $amount,
            'Quantity' => 1,
            'Amount' => $amount,
            'Vat' => $this->resolveVat(),
            'Method' => $this->resolveMethod(),
            'Object' => $this->resolveObject(),
        ];

        $item = $this->appendAgentDataIfNeeded($item, $partner);

        return [
            'Inn' => $platformInn,
            'Type' => $this->mapType($fiscalReceipt->type),
            'InvoiceId' => $this->makeInvoiceId($fiscalReceipt, $paymentIntent, $payable),
            'AccountId' => $this->makeAccountId($paymentIntent),
            'CustomerReceipt' => [
                'Items' => [$item],
                'TaxationSystem' => (int) $partner->taxation_system,
                'Amounts' => [
                    'Electronic' => $amount,
                ],
                'CalculationPlace' => $this->resolveCalculationPlace($partner),
                'IsInternetPayment' => true,
                'RussiaTimeZone' => (int) config('services.cloudkassir.russia_time_zone', 2),
            ],
        ];
    }

    protected function mapType(string $type): string
    {
        return match ($type) {
            FiscalReceipt::TYPE_INCOME => 'Income',
            FiscalReceipt::TYPE_INCOME_RETURN => 'IncomeReturn',
            default => throw new RuntimeException("Unsupported fiscal receipt type: {$type}"),
        };
    }

    protected function makeInvoiceId(
        FiscalReceipt $fiscalReceipt,
        ?PaymentIntent $paymentIntent,
        Payable $payable
    ): string {
        if (!empty($fiscalReceipt->invoice_id)) {
            return (string) $fiscalReceipt->invoice_id;
        }

        if ($paymentIntent) {
            return 'pi_' . $paymentIntent->id;
        }

        return 'payable_' . $payable->id;
    }

    protected function makeAccountId(?PaymentIntent $paymentIntent): ?string
    {
        if (!$paymentIntent || !$paymentIntent->user_id) {
            return null;
        }

        return (string) $paymentIntent->user_id;
    }

    protected function makeLabel(Payable $payable): string
    {
        if ($payable->type === 'monthly_fee') {
            $monthNumber = $payable->month?->format('m');

            $months = [
                '01' => 'январь',
                '02' => 'февраль',
                '03' => 'март',
                '04' => 'апрель',
                '05' => 'май',
                '06' => 'июнь',
                '07' => 'июль',
                '08' => 'август',
                '09' => 'сентябрь',
                '10' => 'октябрь',
                '11' => 'ноябрь',
                '12' => 'декабрь',
            ];

            if ($monthNumber && isset($months[$monthNumber])) {
                return 'Абонемент за ' . $months[$monthNumber];
            }

            return 'Абонемент';
        }

        if ($payable->type === 'club_fee') {
            return 'Клубный взнос';
        }

        return 'Оплата услуг';
    }

    protected function resolveCalculationPlace(Partner $partner): string
    {
        if (!empty($partner->website)) {
            return (string) $partner->website;
        }

        return (string) config('app.url');
    }

    protected function resolveVat(): ?int
    {
        $vat = config('services.cloudkassir.default_vat', null);

        if ($vat === null || $vat === '') {
            return null;
        }

        return (int) $vat;
    }

    protected function resolveMethod(): int
    {
        return (int) config('services.cloudkassir.default_method', 4);
    }

    protected function resolveObject(): int
    {
        return (int) config('services.cloudkassir.default_object', 4);
    }

    protected function normalizeMoney($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    protected function appendAgentDataIfNeeded(array $item, Partner $partner): array
    {
        $agentEnabled = (bool) config('services.cloudkassir.agent.enabled', false);
        if (!$agentEnabled) {
            return $item;
        }

        $agentSign = config('services.cloudkassir.agent.agent_sign');
        if ($agentSign === null || $agentSign === '') {
            return $item;
        }

        $item['AgentSign'] = (string) $agentSign;

        $purveyorEnabled = (bool) config('services.cloudkassir.agent.use_purveyor_data', false);
        if ($purveyorEnabled) {
            $purveyorPhone = $this->normalizePhone($partner->phone);

            $item['PurveyorData'] = [
                'Name' => (string) ($partner->organization_name ?: $partner->title),
                'Inn' => (string) $partner->tax_id,
                'Phone' => $purveyorPhone,
            ];
        }

        $agentDataEnabled = (bool) config('services.cloudkassir.agent.use_agent_data', false);
        if ($agentDataEnabled) {
            $paymentAgentPhone = $this->normalizePhone(
                (string) config('services.cloudkassir.agent.payment_agent_phone', '')
            );

            if ($paymentAgentPhone === '') {
                throw new RuntimeException('CloudKassir agent phone is not configured.');
            }

            $item['AgentData'] = [
                'PaymentAgentPhone' => $paymentAgentPhone,
            ];
        }

        return $item;
    }

    protected function normalizePhone(?string $phone): string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return '';
        }

        $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';

        if ($phone !== '' && $phone[0] !== '+') {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}