<?php

declare(strict_types=1);

namespace App\Services\SettingPrices;

use App\Support\SettingPricesMonth;

/**
 * Превью / итог пролонгации месяца: счётчики и список пропусков.
 */
final class MonthlyPricesProlongReport
{
    public const REASON_EMPTY_SOURCE = 'empty_source';

    public const REASON_ALREADY_SET = 'already_set';

    public const REASON_ALREADY_PAID = 'already_paid';

    public const REASON_LAID_OUT = 'laid_out';

    public const REASON_AUTO_PROLONG = 'auto_prolong';

    public const REASON_POSTPAY_DENIED = 'postpay_denied';

    public const REASON_TEMPLATE_MISSING = 'template_missing';

    public const REASON_ERROR = 'error';

    public const REASON_LABELS = [
        self::REASON_EMPTY_SOURCE => 'В исходном месяце не установлены абонементы',
        self::REASON_ALREADY_SET => 'В следующем месяце уже задан другой абонемент или сумма',
        self::REASON_ALREADY_PAID => 'Следующий месяц уже оплачен',
        self::REASON_LAID_OUT => 'Назначение уже разложено в расписание',
        self::REASON_AUTO_PROLONG => 'У ученика включена автопролонгация абонемента — новые назначения недоступны. Сначала отключите автопролонгацию.',
        self::REASON_POSTPAY_DENIED => 'Недостаточно прав для выбора абонемента типа «Постоплата».',
        self::REASON_TEMPLATE_MISSING => 'Шаблон абонемента не найден или недоступен',
        self::REASON_ERROR => 'Не удалось сохранить',
    ];

    private const ITEMS_LIMIT = 80;

    /** @var array<string, array{students: int, teams: int}> */
    private array $skipReasons = [];

    /** @var list<array<string, mixed>> */
    private array $items = [];

    private int $studentsCreate = 0;

    private int $studentsUnchanged = 0;

    private int $studentsSkip = 0;

    private int $studentsError = 0;

    private int $teamsSet = 0;

    private int $teamsUnchanged = 0;

    private int $teamsSkip = 0;

    public function __construct(
        public readonly string $sourceMonth,
        public readonly string $targetMonth,
        public readonly string $sourceLabel,
        public readonly string $targetLabel,
    ) {
    }

    public function addStudentCreate(int $userId, string $userName, int $teamId, string $teamTitle, string $packageName, int $priceCents): void
    {
        $this->studentsCreate++;
        $this->pushItem([
            'kind' => 'student',
            'action' => 'create',
            'reason' => null,
            'reason_label' => null,
            'user_id' => $userId,
            'user_name' => $userName,
            'team_id' => $teamId,
            'team_title' => $teamTitle,
            'package_name' => $packageName,
            'price_cents' => $priceCents,
        ], false);
    }

    public function addStudentUnchanged(): void
    {
        $this->studentsUnchanged++;
    }

    public function addStudentSkip(
        string $reason,
        int $userId,
        string $userName,
        int $teamId,
        string $teamTitle,
        bool $listItem = true,
        ?string $detail = null,
    ): void {
        $this->studentsSkip++;
        $this->bumpReason($reason, 'student');
        if (! $listItem) {
            return;
        }

        $this->pushItem([
            'kind' => 'student',
            'action' => 'skip',
            'reason' => $reason,
            'reason_label' => $this->labelFor($reason, $detail),
            'user_id' => $userId,
            'user_name' => $userName,
            'team_id' => $teamId,
            'team_title' => $teamTitle,
            'package_name' => null,
            'price_cents' => null,
        ], true);
    }

    public function addStudentError(string $message, int $userId, string $userName, int $teamId, string $teamTitle): void
    {
        $this->studentsError++;
        $this->bumpReason(self::REASON_ERROR, 'student');
        $this->pushItem([
            'kind' => 'student',
            'action' => 'error',
            'reason' => self::REASON_ERROR,
            'reason_label' => $message !== '' ? $message : self::REASON_LABELS[self::REASON_ERROR],
            'user_id' => $userId,
            'user_name' => $userName,
            'team_id' => $teamId,
            'team_title' => $teamTitle,
            'package_name' => null,
            'price_cents' => null,
        ], true);
    }

    public function addTeamSet(int $teamId, string $teamTitle, string $packageName, int $priceCents): void
    {
        $this->teamsSet++;
        $this->pushItem([
            'kind' => 'team',
            'action' => 'create',
            'reason' => null,
            'reason_label' => null,
            'user_id' => null,
            'user_name' => null,
            'team_id' => $teamId,
            'team_title' => $teamTitle,
            'package_name' => $packageName,
            'price_cents' => $priceCents,
        ], false);
    }

    public function addTeamUnchanged(): void
    {
        $this->teamsUnchanged++;
    }

    public function addTeamSkip(string $reason, int $teamId, string $teamTitle, bool $listItem = true): void
    {
        $this->teamsSkip++;
        $this->bumpReason($reason, 'team');
        if (! $listItem) {
            return;
        }

        $this->pushItem([
            'kind' => 'team',
            'action' => 'skip',
            'reason' => $reason,
            'reason_label' => $this->labelFor($reason),
            'user_id' => null,
            'user_name' => null,
            'team_id' => $teamId,
            'team_title' => $teamTitle,
            'package_name' => null,
            'price_cents' => null,
        ], true);
    }

    public function canApply(): bool
    {
        return $this->studentsCreate > 0 || $this->teamsSet > 0;
    }

    public function summaryMessage(bool $afterWrite = false): string
    {
        if (! $this->canApply() && $this->studentsError === 0) {
            return 'Нечего пролонгировать: в выбранном месяце нет абонементов, которые можно перенести.';
        }

        $parts = [];
        if ($this->studentsCreate > 0) {
            $parts[] = 'учеников '.$this->studentsCreate;
        }
        if ($this->teamsSet > 0) {
            $parts[] = 'групп '.$this->teamsSet;
        }

        $head = $parts !== []
            ? ($afterWrite ? 'Пролонгировано: ' : 'Будет пролонгировано: ').implode(', ', $parts).'.'
            : ($afterWrite ? 'Записей не создано.' : 'Нечего записывать.');

        $skipParts = [];
        if ($this->studentsSkip > 0) {
            $skipParts[] = 'учеников '.$this->studentsSkip;
        }
        if ($this->teamsSkip > 0) {
            $skipParts[] = 'групп '.$this->teamsSkip;
        }
        if ($skipParts !== []) {
            $head .= ' Пропущено: '.implode(', ', $skipParts).'.';
        }
        if ($this->studentsError > 0) {
            $head .= ' Ошибок: '.$this->studentsError.'.';
        }

        return $head;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $afterWrite = false): array
    {
        $reasons = [];
        foreach ($this->skipReasons as $code => $counts) {
            $reasons[] = [
                'reason' => $code,
                'students' => $counts['students'],
                'teams' => $counts['teams'],
                'label' => $this->labelForReason($code),
            ];
        }

        return [
            'success' => true,
            'source_month' => $this->sourceMonth,
            'target_month' => $this->targetMonth,
            'source_month_label' => $this->sourceLabel,
            'target_month_label' => $this->targetLabel,
            'can_apply' => $this->canApply(),
            'message' => $this->summaryMessage($afterWrite),
            'counts' => [
                'students_create' => $this->studentsCreate,
                'students_unchanged' => $this->studentsUnchanged,
                'students_skip' => $this->studentsSkip,
                'students_error' => $this->studentsError,
                'teams_set' => $this->teamsSet,
                'teams_unchanged' => $this->teamsUnchanged,
                'teams_skip' => $this->teamsSkip,
            ],
            'skip_reasons' => $reasons,
            'items' => $this->items,
        ];
    }

    /**
     * @param  'student'|'team'  $kind
     */
    private function bumpReason(string $reason, string $kind): void
    {
        if (! isset($this->skipReasons[$reason])) {
            $this->skipReasons[$reason] = ['students' => 0, 'teams' => 0];
        }

        if ($kind === 'team') {
            $this->skipReasons[$reason]['teams']++;

            return;
        }

        $this->skipReasons[$reason]['students']++;
    }

    private function labelFor(string $reason, ?string $detail = null): string
    {
        $base = $this->labelForReason($reason);
        if ($detail !== null && $detail !== '') {
            return $detail;
        }

        return $base;
    }

    private function labelForReason(string $reason): string
    {
        if ($reason === self::REASON_EMPTY_SOURCE) {
            $month = SettingPricesMonth::toPrepositionalMonth($this->sourceMonth);
            if ($month !== '') {
                return 'В '.$month.' не установлены абонементы';
            }
        }

        return self::REASON_LABELS[$reason] ?? $reason;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function pushItem(array $item, bool $priority): void
    {
        if (count($this->items) >= self::ITEMS_LIMIT) {
            return;
        }

        if ($priority) {
            $this->items[] = $item;

            return;
        }

        // Create-строки в превью не раздувают список: достаточно счётчиков.
    }
}
