<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Нельзя изменить слот: есть привязки учеников (UserTeamScheduleSlot).
 *
 * @phpstan-type ConflictRow array{
 *     user_team_schedule_slot_id: int,
 *     user_id: int,
 *     user_label: string,
 *     occurrence_date: string,
 *     user_lesson_package_id: int|null
 * }
 */
final class TeamScheduleSlotConflictException extends RuntimeException
{
    /**
     * @param list<ConflictRow> $conflicts
     */
    public function __construct(
        string $message,
        public readonly array $conflicts,
    ) {
        parent::__construct($message);
    }
}
