<?php

declare(strict_types=1);

namespace App\Services\PaymentNotifications;

use App\Models\LessonPackage;
use App\Models\UserPrice;
use App\Services\Payments\UserPricePublicPayService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Подстановка переменных в шаблоны писем уведомлений об оплате.
 * Значения переменных экранируются; HTML-разметка шаблона сохраняется.
 */
final class PaymentNotificationTemplateRenderer
{
    public function __construct(
        private readonly UserPricePublicPayService $publicPay,
    ) {
    }

    /**
     * @return list<array{key: string, label: string, example: string}>
     */
    public static function availableVariables(): array
    {
        $monthDate = CarbonImmutable::now(PaymentNotificationTriggerResolver::TIMEZONE)->locale('ru');
        $monthName = Str::lower($monthDate->translatedFormat('F'));
        $monthYear = $monthName.' '.$monthDate->format('Y');

        return [
            ['key' => 'student_name', 'label' => 'ФИО ученика', 'example' => 'Иванов Иван'],
            ['key' => 'student_firstname', 'label' => 'Имя ученика', 'example' => 'Иван'],
            ['key' => 'student_lastname', 'label' => 'Фамилия ученика', 'example' => 'Иванов'],
            ['key' => 'month', 'label' => 'Месяц (название)', 'example' => $monthName],
            ['key' => 'month_year', 'label' => 'Месяц и год', 'example' => $monthYear],
            ['key' => 'amount', 'label' => 'Сумма к оплате, ₽', 'example' => '5 000'],
            ['key' => 'team', 'label' => 'Группа', 'example' => 'Младшая группа'],
            ['key' => 'package', 'label' => 'Название абонемента', 'example' => 'Фиксированный'],
            ['key' => 'package_type', 'label' => 'Тип абонемента', 'example' => 'Фиксированный'],
            ['key' => 'pay_url', 'label' => 'Ссылка на оплату через СБП', 'example' => self::demoPayUrl()],
        ];
    }

    public static function demoPayUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/pm/examplePay';
    }

    /**
     * @return array{subject: string, body_html: string, email_html: string, variables: array<string, string>}
     */
    public function render(string $subjectTemplate, string $bodyHtmlTemplate, UserPrice $userPrice): array
    {
        $vars = $this->variablesFromUserPrice($userPrice);
        $bodyHtml = $this->replace($bodyHtmlTemplate, $vars, escape: true);
        $bodyHtml = $this->appendSbpPayBlockIfNeeded($bodyHtmlTemplate, $bodyHtml, $vars['pay_url']);

        return [
            'subject' => $this->replace($subjectTemplate, $vars, escape: false),
            'body_html' => $bodyHtml,
            'email_html' => $this->wrapInEmailLayout($bodyHtml),
            'variables' => $vars,
        ];
    }

    /**
     * Демо-данные для превью без выбранного начисления.
     * Месяц = текущий календарный месяц Europe/Moscow (не захардкоженный пример).
     *
     * @return array{subject: string, body_html: string, email_html: string, variables: array<string, string>}
     */
    public function renderDemo(string $subjectTemplate, string $bodyHtmlTemplate): array
    {
        $vars = [];
        foreach (self::availableVariables() as $row) {
            $vars[$row['key']] = $row['example'];
        }

        $bodyHtml = $this->replace($bodyHtmlTemplate, $vars, escape: true);
        $bodyHtml = $this->appendSbpPayBlockIfNeeded($bodyHtmlTemplate, $bodyHtml, $vars['pay_url']);

        return [
            'subject' => $this->replace($subjectTemplate, $vars, escape: false),
            'body_html' => $bodyHtml,
            'email_html' => $this->wrapInEmailLayout($bodyHtml),
            'variables' => $vars,
        ];
    }

    /**
     * Полное HTML-письмо (лого + текст + футер), как уходит клиенту.
     */
    public function wrapInEmailLayout(string $bodyHtml): string
    {
        return view('emails.payment-notification', [
            'bodyHtml' => $bodyHtml,
        ])->render();
    }

    /**
     * @return array<string, string>
     */
    public function variablesFromUserPrice(UserPrice $userPrice): array
    {
        $user = $userPrice->user;
        $firstname = trim((string) ($user?->name ?? ''));
        $lastname = trim((string) ($user?->lastname ?? ''));
        $fullName = trim($lastname.' '.$firstname);

        $monthDate = CarbonImmutable::parse((string) $userPrice->new_month, PaymentNotificationTriggerResolver::TIMEZONE)
            ->locale('ru');
        $monthName = Str::lower($monthDate->translatedFormat('F'));
        $monthYear = $monthName.' '.$monthDate->format('Y');

        $amountRubles = Money::formatRub((int) $userPrice->price_cents);

        $scheduleType = (string) ($userPrice->lessonPackage?->schedule_type ?? '');

        return [
            'student_name' => $fullName !== '' ? $fullName : 'Ученик',
            'student_firstname' => $firstname !== '' ? $firstname : 'Имя',
            'student_lastname' => $lastname !== '' ? $lastname : 'Фамилия',
            'month' => $monthName,
            'month_year' => $monthYear,
            'amount' => $amountRubles,
            'team' => (string) ($userPrice->team?->title ?? 'Группа'),
            'package' => (string) ($userPrice->lessonPackage?->name ?? 'Абонемент'),
            'package_type' => $this->scheduleTypeLabel($scheduleType),
            'pay_url' => $this->publicPay->shareUrlForNotification($userPrice),
        ];
    }

    public function scheduleTypeLabel(string $scheduleType): string
    {
        return match ($scheduleType) {
            LessonPackage::SCHEDULE_TYPE_FIXED => 'Фиксированный',
            LessonPackage::SCHEDULE_TYPE_FLEXIBLE => 'Гибкий',
            LessonPackage::SCHEDULE_TYPE_POSTPAY => 'Постоплата',
            LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE => 'Без расписания',
            default => $scheduleType !== '' ? $scheduleType : '—',
        };
    }

    public static function templateContainsPayUrlPlaceholder(string $template): bool
    {
        return preg_match('/\{\{\s*pay_url\s*\}\}/u', $template) === 1;
    }

    private function appendSbpPayBlockIfNeeded(string $template, string $bodyHtml, string $payUrl): string
    {
        if ($payUrl === '' || self::templateContainsPayUrlPlaceholder($template)) {
            return $bodyHtml;
        }

        $safeUrl = e($payUrl);

        return $bodyHtml
            .'<p style="margin:24px 0 8px;">'
            .'<a href="'.$safeUrl.'" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;">Оплатить через СБП</a>'
            .'</p>'
            .'<p style="margin:0;font-size:12px;color:#555;">Если кнопка не открывается, скопируйте ссылку:<br>'
            .'<a href="'.$safeUrl.'" style="color:#2563eb;word-break:break-all;">'.$safeUrl.'</a></p>';
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function replace(string $template, array $vars, bool $escape): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/u',
            static function (array $m) use ($vars, $escape): string {
                $key = $m[1];
                if (! array_key_exists($key, $vars)) {
                    return $m[0];
                }
                $value = (string) $vars[$key];

                return $escape ? e($value) : $value;
            },
            $template
        );
    }
}
