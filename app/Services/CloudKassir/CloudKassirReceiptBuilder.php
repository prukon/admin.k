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

        $label = $this->makeLabel($payable);
        $amount = $this->normalizeMoney($fiscalReceipt->amount ?: $payable->amount);

        $payload = [
            'Inn' => (string) $partner->tax_id,
            'Type' => $this->mapType($fiscalReceipt->type),
            'InvoiceId' => $this->makeInvoiceId($fiscalReceipt, $paymentIntent, $payable),
            'AccountId' => $this->makeAccountId($paymentIntent),
            'CustomerReceipt' => [
                'Items' => [
                    [
                        'Label' => $label,
                        'Price' => $amount,
                        'Quantity' => 1,
                        'Amount' => $amount,
                        'Vat' => $this->resolveVat($partner, $payable),
                        'Method' => $this->resolveMethod($payable),
                        'Object' => $this->resolveObject($payable),
                    ],
                ],
                'TaxationSystem' => (int) $partner->taxation_system,
                'Amounts' => [
                    'Electronic' => $amount,
                ],
                'CalculationPlace' => $this->resolveCalculationPlace($partner),
                'IsInternetPayment' => true,
                'RussiaTimeZone' => (int) config('services.cloudkassir.russia_time_zone', 2),
            ],
        ];

        // На первом этапе email/phone покупателя можно не передавать.
        // Если позже захочешь отправку чека покупателю — добавим отсюда.

        $payload = $this->appendAgentDataIfNeeded($payload, $partner);

        return $payload;
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
            $month = $payable->month?->translatedFormat('F');

            if (!$month) {
                return 'Абонемент';
            }

            return 'Абонемент за ' . mb_strtolower($month);
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

    protected function resolveVat(Partner $partner, Payable $payable): ?int
    {
        // Пока берём дефолт из конфига.
        // Если позже захочешь разные ставки по партнёрам/услугам, вынесем в БД.
        $vat = config('services.cloudkassir.default_vat', null);

        if ($vat === null || $vat === '') {
            return null;
        }

        return (int) $vat;
    }

    protected function resolveMethod(Payable $payable): int
    {
        // Для твоего текущего сценария можно начать с полного расчёта.
        // Если бухгалтерски нужно как предоплата — потом переключим.
        return (int) config('services.cloudkassir.default_method', 4);
    }

    protected function resolveObject(Payable $payable): int
    {
        // У тебя сейчас это обычная услуга.
        return (int) config('services.cloudkassir.default_object', 4);
    }

    protected function normalizeMoney($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    protected function appendAgentDataIfNeeded(array $payload, Partner $partner): array
    {
        $agentEnabled = (bool) config('services.cloudkassir.agent.enabled', false);
        if (!$agentEnabled) {
            return $payload;
        }

        $agentSign = config('services.cloudkassir.agent.agent_sign');
        if ($agentSign === null || $agentSign === '') {
            return $payload;
        }

        $payload['CustomerReceipt']['AgentSign'] = (int) $agentSign;

        // Данные поставщика — вероятный минимально нужный блок для агентской схемы.
        $purveyorEnabled = (bool) config('services.cloudkassir.agent.use_purveyor_data', false);
        if ($purveyorEnabled) {
            $payload['CustomerReceipt']['Items'][0]['PurveyorData'] = [
                'Name' => (string) ($partner->organization_name ?: $partner->title),
                'Inn' => (string) $partner->tax_id,
                'Phone' => (string) ($partner->phone ?: config('services.cloudkassir.default_supplier_phone', '')),
            ];
        }

        // AgentData пока оставляем опциональным до ответа менеджера.
        $agentDataEnabled = (bool) config('services.cloudkassir.agent.use_agent_data', false);
        if ($agentDataEnabled) {
            $payload['CustomerReceipt']['Items'][0]['AgentData'] = array_filter([
                'AgentOperationName' => config('services.cloudkassir.agent.operation_name'),
                'PaymentAgentPhone' => config('services.cloudkassir.agent.payment_agent_phone'),
                'PaymentReceiverOperatorPhone' => config('services.cloudkassir.agent.payment_receiver_operator_phone'),
                'TransferOperatorPhone' => config('services.cloudkassir.agent.transfer_operator_phone'),
                'TransferOperatorName' => config('services.cloudkassir.agent.transfer_operator_name'),
                'TransferOperatorAddress' => config('services.cloudkassir.agent.transfer_operator_address'),
                'TransferOperatorInn' => config('services.cloudkassir.agent.transfer_operator_inn'),
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $payload;
    }
}