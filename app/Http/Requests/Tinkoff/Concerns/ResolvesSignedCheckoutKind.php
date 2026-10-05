<?php

declare(strict_types=1);

namespace App\Http\Requests\Tinkoff\Concerns;

use App\Models\User;
use App\Services\Payments\PaymentCheckoutIntent;
use App\Services\Payments\PaymentCheckoutIntentSigner;

trait ResolvesSignedCheckoutKind
{
    protected function signedCheckoutKind(): ?string
    {
        $token = trim((string) $this->input('checkout_intent', ''));
        $user = $this->user();
        if ($token === '' || ! $user instanceof User || ! app()->bound('current_partner')) {
            return null;
        }

        try {
            return app(PaymentCheckoutIntentSigner::class)
                ->open($token, $user, (int) app('current_partner')->id)
                ->kind;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function clientOutSumIsRequired(): bool
    {
        $checkoutKind = $this->signedCheckoutKind();
        if (in_array($checkoutKind, [
            PaymentCheckoutIntent::KIND_MONTHLY,
            PaymentCheckoutIntent::KIND_LESSON,
            PaymentCheckoutIntent::KIND_CUSTOM,
        ], true)) {
            return false;
        }

        $kind = (string) $this->input('payment_kind', '');
        if ($kind === 'custom_payment' || $kind === 'lesson_package') {
            return false;
        }

        return ! $this->filled('formatedPaymentDate');
    }
}
