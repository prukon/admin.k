<?php

declare(strict_types=1);

namespace App\Support\Schedule;

/**
 * Фильтр групп журнала /schedule: пусто = все; none; один или несколько id (OR).
 */
final class ScheduleJournalTeamFilter
{
    /**
     * @param  list<int>  $teamIds
     */
    public function __construct(
        public readonly bool $includeNone,
        public readonly array $teamIds,
    ) {
    }

    /**
     * @param  list<int|string|null>  $tokens
     */
    public static function fromTokens(array $tokens): self
    {
        $includeNone = false;
        $ids = [];

        foreach ($tokens as $token) {
            if ($token === null) {
                continue;
            }

            $value = trim((string) $token);
            if ($value === '' || $value === 'all') {
                continue;
            }

            if ($value === 'none') {
                $includeNone = true;

                continue;
            }

            if (ctype_digit($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        }

        $ids = array_values(array_unique($ids));

        return new self($includeNone, $ids);
    }

    public static function fromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_array($value)) {
            return self::fromTokens($value);
        }

        if ($value === null) {
            return new self(false, []);
        }

        return self::fromTokens([(string) $value]);
    }

    /**
     * @param  array{team_ids?: mixed, team?: mixed}  $data
     */
    public static function fromIndexValidated(array $data): self
    {
        if (array_key_exists('team_ids', $data) && is_array($data['team_ids'])) {
            return self::fromTokens($data['team_ids']);
        }

        return self::fromMixed($data['team'] ?? 'all');
    }

    public function isAll(): bool
    {
        return ! $this->includeNone && $this->teamIds === [];
    }

    public function isNoneOnly(): bool
    {
        return $this->includeNone && $this->teamIds === [];
    }

    public function isSingleTeam(): bool
    {
        return ! $this->includeNone && count($this->teamIds) === 1;
    }

    public function singleTeamId(): ?int
    {
        return $this->isSingleTeam() ? $this->teamIds[0] : null;
    }

    /**
     * Колонка оплаты: «все группы» (частичная / ховер), не одна выбранная.
     */
    public function usesAllGroupsPresentation(): bool
    {
        return $this->isAll()
            || count($this->teamIds) > 1
            || ($this->includeNone && $this->teamIds !== []);
    }

    public function legacyScalar(): string
    {
        if ($this->isAll()) {
            return 'all';
        }

        if ($this->isNoneOnly()) {
            return 'none';
        }

        if ($this->isSingleTeam()) {
            return (string) $this->teamIds[0];
        }

        return 'all';
    }

    /**
     * @return list<string>
     */
    public function tokens(): array
    {
        $tokens = [];
        if ($this->includeNone) {
            $tokens[] = 'none';
        }

        foreach ($this->teamIds as $id) {
            $tokens[] = (string) $id;
        }

        return $tokens;
    }

    /**
     * Контекст модалки: одна выбранная группа, иначе пересечение с группами ученика.
     *
     * @param  list<int|string>  $studentTeamIds
     */
    public function contextTeamId(array $studentTeamIds): ?int
    {
        $studentIds = array_values(array_unique(array_filter(
            array_map('intval', $studentTeamIds),
            static fn (int $id) => $id > 0
        )));

        if ($this->isSingleTeam()) {
            return $this->teamIds[0];
        }

        if ($this->teamIds !== []) {
            foreach ($studentIds as $id) {
                if (in_array($id, $this->teamIds, true)) {
                    return $id;
                }
            }
        }

        return $studentIds[0] ?? null;
    }
}
