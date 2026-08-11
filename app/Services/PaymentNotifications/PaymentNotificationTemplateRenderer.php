<?php

declare(strict_types=1);

namespace App\Services\PaymentNotifications;

use App\Models\LessonPackage;
use App\Models\UserPrice;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Подстановка переменных в шаблоны писем уведомлений об оплате.
 * Значения переменных экранируются; HTML-разметка шаблона сохраняется.
 */
final class PaymentNotificationTemplateRenderer
{
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
        ];
    }

    /**
     * @return array{subject: string, body_html: string, email_html: string, variables: array<string, string>}
     */
    public function render(string $subjectTemplate, string $bodyHtmlTemplate, UserPrice $userPrice): array
    {
        $vars = $this->variablesFromUserPrice($userPrice);
        $bodyHtml = $this->replace($bodyHtmlTemplate, $vars, escape: true);

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
