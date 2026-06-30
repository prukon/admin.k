<?php

namespace App\Support\Payments;

final readonly class PaymentCheckoutContext
{
    public function __construct(
        public ?int $paymentTeamId,
        public ?string $serviceProviderLabel,
        public bool $showTbankLegalEntityBlock,
        public bool $tbankLegalEntityReady,
        public bool $tbankCardAvailable,
        public bool $tbankSbpAvailable,
    ) {
    }

    public static function withoutTbankInstrument(): self
    {
        return new self(
            paymentTeamId: null,
            serviceProviderLabel: null,
            showTbankLegalEntityBlock: false,
            tbankLegalEntityReady: false,
            tbankCardAvailable: false,
            tbankSbpAvailable: false,
        );
    }
}
