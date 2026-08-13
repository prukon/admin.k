<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\User;
use App\Services\Users\FamilyStudentContextService;

/**
 * Плательщик кабинета: права остаются у actor, начисление — у activeStudent.
 * Клубный взнос пока без семейного контекста: student = actor.
 */
final class FamilyPaymentPayerResolver
{
    public function __construct(
        private readonly FamilyStudentContextService $familyContext,
    ) {
    }

    public function resolve(?User $actor = null): FamilyPaymentPayer
    {
        $actor ??= auth()->user();
        abort_unless($actor instanceof User, 401);

        $student = $this->familyContext->activeStudent($actor);

        if ((int) $student->id !== (int) $actor->id
            && ! $this->familyContext->canAccessStudent($actor, (int) $student->id)
        ) {
            abort(403, 'Нет доступа к выбранному ученику.');
        }

        return new FamilyPaymentPayer($actor, $student);
    }

    public function forPayableType(?User $actor, string $paymentKind, bool $hasMonthlyPeriod): FamilyPaymentPayer
    {
        $resolved = $this->resolve($actor);

        $useActiveStudent = $paymentKind === 'custom_payment'
            || $paymentKind === 'lesson_package'
            || $hasMonthlyPeriod;

        if ($useActiveStudent) {
            return $resolved;
        }

        return new FamilyPaymentPayer($resolved->actor, $resolved->actor);
    }
}
