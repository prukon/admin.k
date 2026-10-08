<?php

declare(strict_types=1);

namespace App\Services\SettingPrices;

use App\Mail\PaymentNotificationMail;
use App\Models\UserPrice;
use App\Services\PaymentNotifications\PaymentNotificationRecipient;
use App\Services\PaymentNotifications\PaymentNotificationTemplateRenderer;
use App\Services\Payments\UserPriceMonthlyFeePaymentResolver;
use App\Services\Payments\UserPricePublicPayService;
use App\Services\Postpay\PostpayUsersPriceSync;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Ручная отправка счёта по строке users_prices.
 * Текст — системный шаблон уведомлений, ссылка — та же публичная СБП /pm/{code}.
 */
final class SettingPricesInvoiceEmailService
{
    public function __construct(
        private readonly PaymentNotificationTemplateRenderer $renderer,
        private readonly PaymentNotificationRecipient $recipient,
        private readonly UserPricePublicPayService $publicPay,
        private readonly UserPriceMonthlyFeePaymentResolver $feeResolver,
        private readonly PostpayUsersPriceSync $postpaySync,
    ) {
    }

    public function findCharge(int $partnerId, int $userId, int $teamId, string $newMonth): ?UserPrice
    {
        return UserPrice::query()
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->whereDate('new_month', $newMonth)
            ->whereHas('user', static function ($query) use ($partnerId): void {
                $query->where('partner_id', $partnerId);
            })
            ->whereHas('team', static function ($query) use ($partnerId): void {
                $query->where('partner_id', $partnerId)->whereNull('deleted_at');
            })
            ->with(['user.parentProfile', 'team', 'lessonPackage'])
            ->first();
    }

    /**
     * @return array{
     *     ok: true,
     *     can_send: bool,
     *     errors: array<string, list<string>>,
     *     facts: array<string, string>,
     *     subject: string,
     *     body_html: string,
     *     email_html: string
     * }
     */
    public function preview(UserPrice $row): array
    {
        $row = $this->prepareRow($row);
        $errors = $this->errorsFor($row);
        $rendered = $this->renderDefault($row);

        if ($errors === [] && trim((string) ($rendered['variables']['pay_url'] ?? '')) === '') {
            $errors['pay_url'] = ['Ссылку на оплату сейчас собрать нельзя.'];
        }

        return [
            'ok' => true,
            'can_send' => $errors === [],
            'errors' => $errors,
            'facts' => $this->facts($row, $rendered['variables']),
            'subject' => $rendered['subject'],
            'body_html' => $rendered['body_html'],
            'email_html' => $rendered['email_html'],
        ];
    }

    /**
     * @return array{ok: true, message: string}
     */
    public function send(UserPrice $row): array
    {
        $row = $this->prepareRow($row);
        $errors = $this->errorsFor($row);
        $rendered = $this->renderDefault($row);

        if ($errors === [] && trim((string) ($rendered['variables']['pay_url'] ?? '')) === '') {
            $errors['pay_url'] = ['Ссылку на оплату сейчас собрать нельзя.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $email = (string) $this->recipient->emailFor($row->user);
        Mail::to($email)->send(new PaymentNotificationMail(
            emailSubject: $rendered['subject'],
            bodyHtml: $rendered['body_html'],
            partnerId: (int) $row->user->partner_id,
            dispatchId: null,
        ));

        return [
            'ok' => true,
            'message' => 'Счёт отправлен на '.$email.'.',
        ];
    }

    private function prepareRow(UserPrice $row): UserPrice
    {
        $row->loadMissing(['user.parentProfile', 'team', 'lessonPackage']);
        if ($row->lessonPackage && $row->lessonPackage->isPostpay()) {
            $this->postpaySync->syncRow($row);
            $row->refresh();
            $row->load(['user.parentProfile', 'team', 'lessonPackage']);
        }

        return $row;
    }

    /**
     * @return array<string, list<string>>
     */
    private function errorsFor(UserPrice $row): array
    {
        $errors = [];

        if ((bool) $row->is_paid && ! $row->effective_is_paid) {
            $errors['amount'] = ['Этот период уже оплачен через платёжную систему.'];
        } elseif ($row->effective_is_paid) {
            $errors['amount'] = ['Этот период уже оплачен.'];
        } elseif ((int) $row->price_cents <= 0) {
            $errors['amount'] = ['Сумма к оплате должна быть больше нуля.'];
        }

        if (! $row->lesson_package_id) {
            $errors['package'] = ['У начисления не выбран абонемент.'];
        }

        if ($this->recipient->emailFor($row->user) === null) {
            $errors['email'] = ['Нет корректного email родителя или ученика.'];
        }

        if (! isset($errors['amount']) && ! isset($errors['package'])) {
            $payReason = $this->payLinkReason($row);
            if ($payReason !== null) {
                $errors['pay_url'] = [$payReason];
            }
        }

        return $errors;
    }

    private function payLinkReason(UserPrice $row): ?string
    {
        $partnerId = (int) ($row->user?->partner_id ?? 0);
        if ($partnerId <= 0) {
            return 'Начисление не найдено.';
        }

        try {
            $resolved = $this->feeResolver->resolvePublicPayForPartner($partnerId, $row);
        } catch (HttpException $e) {
            $message = trim($e->getMessage());

            return $message !== '' ? $message : 'Ссылку на оплату сейчас собрать нельзя.';
        }

        if (! $this->publicPay->partnerTbankConfigured($partnerId, (int) $row->team_id)) {
            return 'Оплата через СБП недоступна: у школы не подключён T‑Bank.';
        }

        if (! $this->publicPay->isAmountAllowedForSbp((int) $resolved['amount_cents'])) {
            return 'Сумма вне диапазона оплаты через СБП: от 10 до 1 000 000 ₽.';
        }

        return null;
    }

    /**
     * @return array{subject: string, body_html: string, email_html: string, variables: array<string, string>}
     */
    private function renderDefault(UserPrice $row): array
    {
        return $this->renderer->render(
            PaymentNotificationTemplateRenderer::defaultSubjectTemplate(),
            PaymentNotificationTemplateRenderer::defaultBodyHtmlTemplate(),
            $row
        );
    }

    /**
     * @param  array<string, string>  $variables
     * @return array<string, string>
     */
    private function facts(UserPrice $row, array $variables): array
    {
        $parentName = trim((string) ($row->user?->parentProfile?->full_name ?? ''));
        $email = $this->recipient->emailFor($row->user);
        $package = $row->lesson_package_id
            ? (string) ($variables['package'] ?? '')
            : 'не выбран';

        return [
            'package' => $package !== '' ? $package : 'не выбран',
            'amount' => trim((string) ($variables['amount'] ?? '')).' ₽',
            'month' => (string) ($variables['month_year'] ?? ''),
            'team' => (string) ($variables['team'] ?? ''),
            'student_name' => (string) ($variables['student_name'] ?? ''),
            'parent_name' => $parentName !== '' ? $parentName : 'нет, в письме обращение на ученика',
            'email' => $email ?? '—',
            'pay_url' => trim((string) ($variables['pay_url'] ?? '')) !== ''
                ? 'кнопка «Оплатить через СБП»'
                : 'недоступна',
        ];
    }
};
