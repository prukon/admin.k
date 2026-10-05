<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\Concerns;

use App\Models\User;
use App\Services\Payments\PaymentCheckoutIntent;
use App\Services\Payments\PaymentCheckoutIntentSigner;

trait SignsPaymentCheckout
{
    protected function signMonthlyCheckout(User $actor, string $month, ?int $teamId = null, ?User $student = null): string
    {
        $student ??= $actor;

        return app(PaymentCheckoutIntentSigner::class)->issue(
            PaymentCheckoutIntent::KIND_MONTHLY,
            (int) $this->partner->id,
            (int) $actor->id,
            (int) $student->id,
            month: $month,
            teamId: $teamId,
        );
    }

    protected function signLessonCheckout(User $actor, int $lessonPackageId, ?User $student = null): string
    {
        $student ??= $actor;

        return app(PaymentCheckoutIntentSigner::class)->issue(
            PaymentCheckoutIntent::KIND_LESSON,
            (int) $this->partner->id,
            (int) $actor->id,
            (int) $student->id,
            userLessonPackageId: $lessonPackageId,
        );
    }

    protected function signCustomCheckout(User $actor, int $customPaymentId, ?User $student = null): string
    {
        $student ??= $actor;

        return app(PaymentCheckoutIntentSigner::class)->issue(
            PaymentCheckoutIntent::KIND_CUSTOM,
            (int) $this->partner->id,
            (int) $actor->id,
            (int) $student->id,
            customPaymentId: $customPaymentId,
        );
    }

    protected function signClubCheckout(User $actor): string
    {
        return app(PaymentCheckoutIntentSigner::class)->issue(
            PaymentCheckoutIntent::KIND_CLUB,
            (int) $this->partner->id,
            (int) $actor->id,
            (int) $actor->id,
        );
    }
}
