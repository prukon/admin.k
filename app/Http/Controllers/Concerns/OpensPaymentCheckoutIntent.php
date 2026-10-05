<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\Payments\FamilyPaymentPayer;
use App\Services\Payments\PaymentCheckoutIntent;
use App\Services\Payments\PaymentCheckoutIntentException;
use App\Services\Payments\PaymentCheckoutIntentSigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait OpensPaymentCheckoutIntent
{
    /**
     * @return array{
     *     intent: PaymentCheckoutIntent,
     *     payer: FamilyPaymentPayer,
     *     paymentKind: string,
     *     hasMonthly: bool,
     *     rawFmt: ?string,
     *     userPeriodPriceId: ?int,
     *     userLessonPackageId: ?int
     * }|RedirectResponse
     */
    protected function beginPaymentCheckout(Request $request, int $partnerId): array|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return back()->withErrors([
                'checkout_intent' => PaymentCheckoutIntentSigner::MESSAGE_REOPEN,
            ]);
        }

        try {
            $intent = app(PaymentCheckoutIntentSigner::class)->open(
                (string) $request->input('checkout_intent', ''),
                $actor,
                $partnerId,
            );
        } catch (PaymentCheckoutIntentException $e) {
            return back()->withErrors(['checkout_intent' => $e->getMessage()]);
        }

        $student = User::query()
            ->whereKey($intent->studentUserId)
            ->where('partner_id', $partnerId)
            ->first();
        if (! $student instanceof User) {
            return back()->withErrors([
                'checkout_intent' => PaymentCheckoutIntentSigner::MESSAGE_REOPEN,
            ]);
        }

        return [
            'intent' => $intent,
            'payer' => new FamilyPaymentPayer($actor, $student),
            'paymentKind' => $intent->requestPaymentKind(),
            'hasMonthly' => $intent->isMonthly(),
            'rawFmt' => $intent->month,
            'userPeriodPriceId' => $intent->customPaymentId,
            'userLessonPackageId' => $intent->userLessonPackageId,
        ];
    }
}
