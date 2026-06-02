<?php

namespace App\Support;

use App\Enums\AuditEvent;
use App\Enums\AuditLevel;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Единые scope-фильтры для DataTables логов (my_logs) по колонкам event и level.
 */
final class AuditLogQueryScopes
{
    /**
     * @return list<string>
     */
    public static function knownEventValues(): array
    {
        return array_map(static fn (AuditEvent $event) => $event->value, AuditEvent::cases());
    }

    /**
     * Исключить входы в систему (auth.login).
     */
    public static function applyHideAuthorizations(Builder $query): void
    {
        $query->where(function (Builder $keep) {
            $keep->whereNull('my_logs.event')
                ->orWhere('my_logs.event', '!=', AuditEvent::AuthLogin->value);
        });
    }

    /**
     * Исключить интеграционные события (webhook, платежи, подписание и т.п.).
     */
    public static function applyHideIntegrations(Builder $query): void
    {
        $integrationLevel = AuditLevel::Integration->value;
        $integrationEvents = self::integrationEventValues();

        $query->where(function (Builder $keep) use ($integrationLevel, $integrationEvents) {
            $keep->where(function (Builder $byLevel) use ($integrationLevel) {
                $byLevel->whereNull('my_logs.level')
                    ->orWhere('my_logs.level', '!=', $integrationLevel);
            })->where(function (Builder $byEvent) use ($integrationEvents) {
                $byEvent->whereNull('my_logs.event')
                    ->orWhereNotIn('my_logs.event', $integrationEvents);
            });
        });
    }

    /**
     * Ограничение модалок «История изменений» по доменной категории (pricing, user, team…).
     */
    public static function applyCategoryScope(Builder $query, string $category): void
    {
        if (! in_array($category, AuditEvent::knownCategories(), true)) {
            throw new InvalidArgumentException('Unknown audit category: '.$category);
        }

        $events = AuditEvent::eventValuesForCategory($category);

        if ($events === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn('my_logs.event', $events);
    }

    /**
     * filter_level: info | security | integration.
     */
    public static function applyFilterLevel(Builder $query, string $level): void
    {
        if (! in_array($level, AuditLevel::values(), true)) {
            throw new InvalidArgumentException('Unknown audit level filter: '.$level);
        }

        $query->where('my_logs.level', $level);
    }

    /**
     * filter_action: канонический event (domain.action) или «unknown».
     */
    public static function applyFilterAction(Builder $query, string $filterAction): void
    {
        if ($filterAction === 'unknown') {
            self::applyUnknownEventFilter($query);

            return;
        }

        if (! str_contains($filterAction, '.')) {
            return;
        }

        $query->where('my_logs.event', $filterAction);
    }

    private static function applyUnknownEventFilter(Builder $query): void
    {
        $knownEvents = self::knownEventValues();

        $query->where(function (Builder $unknownQuery) use ($knownEvents) {
            $unknownQuery->whereNull('my_logs.event')
                ->orWhere('my_logs.event', '')
                ->orWhereNotIn('my_logs.event', $knownEvents);
        });
    }

    /**
     * @return list<string>
     */
    private static function integrationEventValues(): array
    {
        $values = [];

        foreach (AuditEvent::cases() as $case) {
            if ($case->level() === AuditLevel::Integration) {
                $values[] = $case->value;
            }
        }

        return $values;
    }
}
