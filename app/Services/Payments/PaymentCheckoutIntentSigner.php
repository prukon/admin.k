<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\User;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Support\Facades\Crypt;

/**
 * Выпускает и проверяет контекст страницы оплаты (APP_KEY, срок 12 часов).
 */
final class PaymentCheckoutIntentSigner
{
    public const TTL_SECONDS = 12 * 3600;

    public const MESSAGE_REOPEN = 'Откройте страницу оплаты заново.';

    public const MESSAGE_STALE = 'Страница оплаты устарела. Откройте её заново.';

    public const MESSAGE_UNKNOWN = 'Не удалось определить оплату. Откройте страницу оплаты заново.';

    public const MESSAGE_CLUB_FORBIDDEN = 'Оплата клубного взноса недоступна.';

    public const MESSAGE_STUDENT = 'Нет доступа к выбранному ученику.';

    public function issue(
        string $kind,
        int $partnerId,
        int $actorUserId,
        int $studentUserId,
        ?string $month = null,
        ?int $teamId = null,
        ?int $userLessonPackageId = null,
        ?int $customPaymentId = null,
    ): string {
        $intent = new PaymentCheckoutIntent(
            kind: $kind,
            partnerId: $partnerId,
            actorUserId: $actorUserId,
            studentUserId: $studentUserId,
            month: $month,
            teamId: $teamId !== null && $teamId > 0 ? $teamId : null,
            userLessonPackageId: $userLessonPackageId !== null && $userLessonPackageId > 0 ? $userLessonPackageId : null,
            customPaymentId: $customPaymentId !== null && $customPaymentId > 0 ? $customPaymentId : null,
            expiresAt: time() + self::TTL_SECONDS,
        );

        return Crypt::encryptString(json_encode($this->payload($intent), JSON_UNESCAPED_UNICODE));
    }

    public function open(string $token, User $actor, int $partnerId): PaymentCheckoutIntent
    {
        $token = trim($token);
        if ($token === '') {
            throw new PaymentCheckoutIntentException(self::MESSAGE_REOPEN);
        }

        try {
            $json = Crypt::decryptString($token);
        } catch (\Throwable) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_STALE);
        }

        $data = json_decode($json, true);
        if (! is_array($data) || (int) ($data['v'] ?? 0) !== PaymentCheckoutIntent::VERSION) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_STALE);
        }

        $expiresAt = (int) ($data['exp'] ?? 0);
        if ($expiresAt < time()) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_STALE);
        }

        if ((int) ($data['partner_id'] ?? 0) !== $partnerId || (int) ($data['actor_user_id'] ?? 0) !== (int) $actor->id) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_STALE);
        }

        $kind = (string) ($data['kind'] ?? '');
        if (! in_array($kind, [
            PaymentCheckoutIntent::KIND_MONTHLY,
            PaymentCheckoutIntent::KIND_LESSON,
            PaymentCheckoutIntent::KIND_CUSTOM,
            PaymentCheckoutIntent::KIND_CLUB,
        ], true)) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_UNKNOWN);
        }

        if ($kind === PaymentCheckoutIntent::KIND_CLUB && ! $actor->can('payment.clubfee')) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_CLUB_FORBIDDEN);
        }

        $studentId = (int) ($data['student_user_id'] ?? 0);
        if ($studentId <= 0) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_REOPEN);
        }

        if ($studentId !== (int) $actor->id
            && ! app(FamilyStudentContextService::class)->canAccessStudent($actor, $studentId)
        ) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_STUDENT);
        }

        $student = User::query()
            ->whereKey($studentId)
            ->where('partner_id', $partnerId)
            ->first();
        if (! $student instanceof User) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_REOPEN);
        }

        $month = isset($data['month']) && is_string($data['month']) && $data['month'] !== ''
            ? $data['month']
            : null;
        $teamId = isset($data['team_id']) && (int) $data['team_id'] > 0 ? (int) $data['team_id'] : null;
        $lessonId = isset($data['user_lesson_package_id']) && (int) $data['user_lesson_package_id'] > 0
            ? (int) $data['user_lesson_package_id']
            : null;
        $customId = isset($data['custom_payment_id']) && (int) $data['custom_payment_id'] > 0
            ? (int) $data['custom_payment_id']
            : null;

        if ($kind === PaymentCheckoutIntent::KIND_MONTHLY
            && ($month === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $month))
        ) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_UNKNOWN);
        }

        if ($kind === PaymentCheckoutIntent::KIND_LESSON && $lessonId === null) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_UNKNOWN);
        }

        if ($kind === PaymentCheckoutIntent::KIND_CUSTOM && $customId === null) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_UNKNOWN);
        }

        if ($kind === PaymentCheckoutIntent::KIND_CLUB && (int) $student->id !== (int) $actor->id) {
            throw new PaymentCheckoutIntentException(self::MESSAGE_STALE);
        }

        return new PaymentCheckoutIntent(
            kind: $kind,
            partnerId: $partnerId,
            actorUserId: (int) $actor->id,
            studentUserId: (int) $student->id,
            month: $kind === PaymentCheckoutIntent::KIND_MONTHLY ? $month : null,
            teamId: $kind === PaymentCheckoutIntent::KIND_MONTHLY ? $teamId : null,
            userLessonPackageId: $kind === PaymentCheckoutIntent::KIND_LESSON ? $lessonId : null,
            customPaymentId: $kind === PaymentCheckoutIntent::KIND_CUSTOM ? $customId : null,
            expiresAt: $expiresAt,
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    private function payload(PaymentCheckoutIntent $intent): array
    {
        return [
            'v' => PaymentCheckoutIntent::VERSION,
            'kind' => $intent->kind,
            'partner_id' => $intent->partnerId,
            'actor_user_id' => $intent->actorUserId,
            'student_user_id' => $intent->studentUserId,
            'month' => $intent->month,
            'team_id' => $intent->teamId,
            'user_lesson_package_id' => $intent->userLessonPackageId,
            'custom_payment_id' => $intent->customPaymentId,
            'exp' => $intent->expiresAt,
        ];
    }
}
