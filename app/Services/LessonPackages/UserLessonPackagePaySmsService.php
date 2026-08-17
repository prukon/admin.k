<?php

declare(strict_types=1);

namespace App\Services\LessonPackages;

use App\Models\Partner;
use App\Models\PartnerWalletTransaction;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Services\Payments\UserLessonPackagePublicPayService;
use App\Services\SmsRuService;
use App\Support\Money;
use App\Support\RuPhone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class UserLessonPackagePaySmsService
{
    public function __construct(
        private readonly UserLessonPackagePublicPayService $publicPay,
        private readonly SmsRuService $smsRu,
    ) {
    }

    public function sendFee(): float
    {
        return (float) (config('billing.sms_send_fee') ?? 70.00);
    }

    public function sendFeeCents(): int
    {
        return Money::toCentsOrFail($this->sendFee());
    }

    public function partnerCanAfford(?Partner $partner): bool
    {
        if ($partner === null) {
            return false;
        }

        return (int) $partner->wallet_balance_cents >= $this->sendFeeCents();
    }

    public function isPayLinkAvailable(UserLessonPackage $assignment, bool $tbankReady): bool
    {
        $feeAmountCents = (int) ($assignment->fee_amount_cents ?? 0);

        return $tbankReady
            && ! $assignment->effective_is_paid
            && $feeAmountCents >= 1000;
    }

    /**
     * @return array{
     *     phone: string,
     *     phone_display: string,
     *     phone_locked: bool,
     *     phone_source: string|null,
     *     message: string,
     *     fee: float,
     *     fee_label: string,
     *     pay_url: string
     * }
     */
    public function preview(UserLessonPackage $assignment, int $partnerId): array
    {
        $this->assertCanSend($assignment, $partnerId);

        $resolved = $this->resolveRecipient($assignment);
        $payUrl = $this->ensurePayUrl($assignment);
        $message = $this->buildMessage($assignment, $payUrl);

        return [
            'phone' => $resolved['digits'] ?? '',
            'phone_display' => $resolved['display'],
            'phone_locked' => $resolved['locked'],
            'phone_source' => $resolved['source'],
            'message' => $message,
            'fee' => $this->sendFee(),
            'fee_label' => Money::formatRub($this->sendFeeCents()).' руб.',
            'pay_url' => $payUrl,
        ];
    }

    /**
     * @return array{phone_saved: bool, fee_cents: int, pay_url: string}
     */
    public function send(
        UserLessonPackage $assignment,
        int $partnerId,
        ?string $postedPhoneDigits,
        int $actorId,
    ): array {
        $this->assertCanSend($assignment, $partnerId);

        $resolved = $this->resolveRecipient($assignment);
        $digits = $resolved['digits'];
        $shouldSavePhone = false;

        if ($resolved['locked']) {
            if ($digits === null) {
                throw ValidationException::withMessages([
                    'phone' => 'Укажите номер телефона.',
                ]);
            }
        } else {
            if ($postedPhoneDigits === null || $postedPhoneDigits === '') {
                throw ValidationException::withMessages([
                    'phone' => 'Укажите номер телефона.',
                ]);
            }
            $this->assertPhoneAvailableForStudent($assignment, $postedPhoneDigits);
            $digits = $postedPhoneDigits;
            $shouldSavePhone = true;
        }

        $partner = Partner::query()->find($partnerId);
        if ($partner === null) {
            abort(404);
        }

        $payUrl = $this->ensurePayUrl($assignment);
        $message = $this->buildMessage($assignment, $payUrl);
        $feeCents = $this->sendFeeCents();
        $walletTxId = $this->chargePartner($partner, $feeCents, $actorId, (int) $assignment->id);

        $smsResult = $this->smsRu->send($digits, $message);
        if ($smsResult !== true) {
            $this->refundPartner($partner, $feeCents, $actorId, $walletTxId, (int) $assignment->id);
            throw ValidationException::withMessages([
                'sms' => SmsRuService::userFacingErrorMessage(
                    is_string($smsResult) ? $smsResult : 'Unknown error'
                ),
            ]);
        }

        if ($shouldSavePhone) {
            $this->saveStudentPhone($assignment, $digits);
        }

        return [
            'phone_saved' => $shouldSavePhone,
            'fee_cents' => $feeCents,
            'pay_url' => $payUrl,
        ];
    }

    private function assertCanSend(UserLessonPackage $assignment, int $partnerId): void
    {
        $this->loadAssignmentForSms($assignment);

        $tbankReady = $this->publicPay->partnerTbankConfigured($partnerId);
        if (! $this->isPayLinkAvailable($assignment, $tbankReady)) {
            throw ValidationException::withMessages([
                'sms' => 'Ссылка на оплату недоступна для этого назначения.',
            ]);
        }

        try {
            $this->publicPay->assertAmountAllowedForSbp($partnerId, (int) $assignment->id);
        } catch (HttpException $e) {
            throw ValidationException::withMessages([
                'sms' => $e->getMessage(),
            ]);
        }
    }

    private function loadAssignmentForSms(UserLessonPackage $assignment): void
    {
        if ($assignment->relationLoaded('user') && $assignment->user !== null) {
            $keys = array_keys($assignment->user->getAttributes());
            if (! in_array('phone', $keys, true) || ! in_array('parent_id', $keys, true)) {
                $assignment->unsetRelation('user');
            }
        }

        $assignment->load([
            'user' => static function ($q): void {
                $q->withTrashed()->select(['id', 'name', 'lastname', 'partner_id', 'deleted_at', 'phone', 'parent_id']);
            },
            'user.parentProfile:id,phone',
            'lessonPackage:id,name',
        ]);
    }

    /**
     * @return array{digits: string|null, display: string, locked: bool, source: string|null}
     */
    private function resolveRecipient(UserLessonPackage $assignment): array
    {
        $this->loadAssignmentForSms($assignment);

        $parentPhone = trim((string) ($assignment->user?->parentProfile?->phone ?? ''));
        $studentPhone = trim((string) ($assignment->user?->phone ?? ''));

        $parentDigits = $this->validSmsDigits($parentPhone);
        if ($parentDigits !== null) {
            return [
                'digits' => $parentDigits,
                'display' => RuPhone::formatForInput($parentDigits),
                'locked' => true,
                'source' => 'parent',
            ];
        }

        $studentDigits = $this->validSmsDigits($studentPhone);
        if ($studentDigits !== null) {
            return [
                'digits' => $studentDigits,
                'display' => RuPhone::formatForInput($studentDigits),
                'locked' => true,
                'source' => 'student',
            ];
        }

        return [
            'digits' => null,
            'display' => '',
            'locked' => false,
            'source' => null,
        ];
    }

    private function validSmsDigits(?string $phone): ?string
    {
        $digits = RuPhone::normalizeDigits($phone);
        if ($digits === null || strlen($digits) !== 11 || ! str_starts_with($digits, '7')) {
            return null;
        }

        return $digits;
    }

    private function ensurePayUrl(UserLessonPackage $assignment): string
    {
        $this->loadAssignmentForSms($assignment);
        $link = $this->publicPay->ensureFreshLink($assignment);

        return $this->publicPay->publicShareUrl($link);
    }

    private function buildMessage(UserLessonPackage $assignment, string $payUrl): string
    {
        $amount = (string) intdiv((int) ($assignment->fee_amount_cents ?? 0), 100);

        return 'Оплатите абонемент '.$amount.' руб: '.$payUrl;
    }

    private function assertPhoneAvailableForStudent(UserLessonPackage $assignment, string $digits): void
    {
        $userId = (int) $assignment->user_id;
        $e164 = '+'.$digits;

        $taken = User::query()
            ->where('id', '!=', $userId)
            ->where(function ($q) use ($e164, $digits): void {
                $q->where('phone', $e164)
                    ->orWhere('phone', $digits);
            })
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'phone' => 'Пользователь с таким телефоном уже существует.',
            ]);
        }
    }

    private function saveStudentPhone(UserLessonPackage $assignment, string $digits): void
    {
        $user = $assignment->user;
        if ($user === null) {
            return;
        }

        $user->phone = $digits;
        $user->save();
    }

    private function chargePartner(Partner $partner, int $feeCents, int $actorId, int $assignmentId): int
    {
        return (int) DB::transaction(function () use ($partner, $feeCents, $actorId, $assignmentId) {
            /** @var Partner $locked */
            $locked = Partner::query()->whereKey($partner->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->wallet_balance_cents < $feeCents) {
                throw ValidationException::withMessages([
                    'wallet' => 'Недостаточно средств. Пополните баланс кабинета.',
                ]);
            }

            $locked->wallet_balance_cents = (int) $locked->wallet_balance_cents - $feeCents;
            $locked->save();
            Cache::forget("partner_balance_{$locked->id}");

            $tx = PartnerWalletTransaction::query()->create([
                'partner_id' => $locked->id,
                'user_id' => $actorId,
                'type' => 'debit',
                'amount_cents' => $feeCents,
                'currency' => 'RUB',
                'provider' => 'manual',
                'status' => 'succeeded',
                'description' => 'Отправка SMS со ссылкой на оплату абонемента',
                'meta' => [
                    'user_lesson_package_id' => $assignmentId,
                    'reason' => 'ulp_pay_sms',
                ],
            ]);

            $partner->wallet_balance_cents = (int) $locked->wallet_balance_cents;

            return (int) $tx->id;
        });
    }

    private function refundPartner(
        Partner $partner,
        int $feeCents,
        int $actorId,
        int $walletTxId,
        int $assignmentId,
    ): void {
        DB::transaction(function () use ($partner, $feeCents, $actorId, $walletTxId, $assignmentId): void {
            /** @var Partner $locked */
            $locked = Partner::query()->whereKey($partner->id)->lockForUpdate()->firstOrFail();
            $locked->wallet_balance_cents = (int) $locked->wallet_balance_cents + $feeCents;
            $locked->save();
            Cache::forget("partner_balance_{$locked->id}");

            PartnerWalletTransaction::query()->whereKey($walletTxId)->update([
                'status' => 'failed',
            ]);

            PartnerWalletTransaction::query()->create([
                'partner_id' => $locked->id,
                'user_id' => $actorId,
                'type' => 'credit',
                'amount_cents' => $feeCents,
                'currency' => 'RUB',
                'provider' => 'refund',
                'status' => 'succeeded',
                'description' => 'Возврат: не удалось отправить SMS',
                'meta' => [
                    'user_lesson_package_id' => $assignmentId,
                    'reason' => 'ulp_pay_sms_refund',
                    'original_wallet_transaction_id' => $walletTxId,
                ],
            ]);

            $partner->wallet_balance_cents = (int) $locked->wallet_balance_cents;
        });
    }
}
