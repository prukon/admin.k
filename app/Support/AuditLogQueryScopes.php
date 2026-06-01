<?php

namespace App\Support;

use App\Enums\AuditEvent;
use App\Enums\AuditLevel;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Единые scope-фильтры для DataTables логов (my_logs).
 *
 * Поддерживает канонические event/level и legacy type/action до полного отказа от legacy.
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
     * @return list<int>
     */
    public static function knownLegacyActionCodes(): array
    {
        return array_map(intval(...), array_keys(AuditEvent::legacyActionLabels()));
    }

    /**
     * Исключить входы в систему (auth.login) по event или legacy type/action.
     */
    public static function applyHideAuthorizations(Builder $query): void
    {
        $login = AuditEvent::AuthLogin;

        $query->where(function (Builder $keep) use ($login) {
            $keep->where(function (Builder $byEvent) use ($login) {
                $byEvent->whereNull('my_logs.event')
                    ->orWhere('my_logs.event', '!=', $login->value);
            })->where(function (Builder $byLegacy) use ($login) {
                $byLegacy->whereNotNull('my_logs.event')
                    ->orWhere('my_logs.type', '!=', $login->legacyType())
                    ->orWhere('my_logs.action', '!=', $login->legacyAction());
            });
        });
    }

    /**
     * Исключить интеграционные события (webhook, платежи, подписание и т.п.).
     */
    public static function applyHideIntegrations(Builder $query): void
    {
        $integrationLevel = AuditLevel::Integration->value;
        $integrationEvents = self::integrationEventValues();
        $integrationActions = self::integrationLegacyActionCodes();

        $query->where(function (Builder $keep) use ($integrationLevel, $integrationEvents, $integrationActions) {
            $keep->where(function (Builder $byLevel) use ($integrationLevel) {
                $byLevel->whereNull('my_logs.level')
                    ->orWhere('my_logs.level', '!=', $integrationLevel);
            })->where(function (Builder $byEvent) use ($integrationEvents) {
                $byEvent->whereNull('my_logs.event')
                    ->orWhereNotIn('my_logs.event', $integrationEvents);
            })->where(function (Builder $byLegacy) use ($integrationActions) {
                $byLegacy->whereNotNull('my_logs.event')
                    ->orWhereNotNull('my_logs.level')
                    ->orWhereNotIn('my_logs.action', $integrationActions);
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
        $legacyTypes = AuditEvent::legacyTypesForCategory($category);
        $legacyActions = AuditEvent::legacyActionsForCategory($category);

        $query->where(function (Builder $match) use ($events, $legacyTypes, $legacyActions) {
            if ($events !== []) {
                $match->whereIn('my_logs.event', $events);
            }

            $match->orWhere(function (Builder $legacy) use ($legacyTypes, $legacyActions) {
                $legacy->whereNull('my_logs.event');

                if ($legacyTypes !== []) {
                    $legacy->whereIn('my_logs.type', $legacyTypes);
                }

                if ($legacyActions !== []) {
                    $legacy->whereIn('my_logs.action', $legacyActions);
                }
            });
        });
    }

    /**
     * filter_level: info | security | integration.
     */
    public static function applyFilterLevel(Builder $query, string $level): void
    {
        if (! in_array($level, AuditLevel::values(), true)) {
            throw new InvalidArgumentException('Unknown audit level filter: '.$level);
        }

        $events = self::eventValuesForLevel($level);

        $query->where(function (Builder $match) use ($level, $events) {
            $match->where('my_logs.level', $level)
                ->orWhere(function (Builder $legacy) use ($events) {
                    $legacy->whereNull('my_logs.level')
                        ->whereIn('my_logs.event', $events);
                });
        });
    }

    /**
     * filter_action: legacy-код action, канонический event (domain.action) или «unknown».
     */
    public static function applyFilterAction(Builder $query, string $filterAction): void
    {
        if ($filterAction === 'unknown') {
            self::applyUnknownActionFilter($query);

            return;
        }

        if (str_contains($filterAction, '.')) {
            self::applyEventValueFilter($query, $filterAction);

            return;
        }

        if (ctype_digit($filterAction)) {
            self::applyLegacyActionCodeFilter($query, (int) $filterAction);
        }
    }

    private static function applyUnknownActionFilter(Builder $query): void
    {
        $knownActions = self::knownLegacyActionCodes();
        $knownEvents = self::knownEventValues();

        $query->where(function (Builder $unknownQuery) use ($knownActions, $knownEvents) {
            $unknownQuery->where(function (Builder $legacy) use ($knownActions) {
                $legacy->whereNull('my_logs.event')
                    ->where(function (Builder $byAction) use ($knownActions) {
                        $byAction->whereNull('my_logs.action')
                            ->orWhereNotIn('my_logs.action', $knownActions);
                    });
            })->orWhere(function (Builder $byEvent) use ($knownEvents) {
                $byEvent->whereNotNull('my_logs.event')
                    ->whereNotIn('my_logs.event', $knownEvents);
            });
        });
    }

    private static function applyEventValueFilter(Builder $query, string $eventValue): void
    {
        $event = AuditEvent::tryFromString($eventValue);
        if ($event === null) {
            $query->where('my_logs.event', $eventValue);

            return;
        }

        self::applyResolvedEventFilter($query, $event);
    }

    private static function applyLegacyActionCodeFilter(Builder $query, int $actionCode): void
    {
        $event = AuditEvent::fromLegacy(null, $actionCode);
        if ($event === null) {
            $query->where('my_logs.action', $actionCode);

            return;
        }

        self::applyResolvedEventFilter($query, $event);
    }

    private static function applyResolvedEventFilter(Builder $query, AuditEvent $event): void
    {
        $query->where(function (Builder $matchQuery) use ($event) {
            $matchQuery->where('my_logs.event', $event->value)
                ->orWhere(function (Builder $legacy) use ($event) {
                    $legacy->whereNull('my_logs.event')
                        ->where('my_logs.action', $event->legacyAction());

                    if ($event === AuditEvent::PartnerSettingsUpdated) {
                        $legacy->where('my_logs.type', $event->legacyType());
                    }
                });
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

    /**
     * @return list<int>
     */
    private static function integrationLegacyActionCodes(): array
    {
        $codes = [];

        foreach (AuditEvent::cases() as $case) {
            if ($case->level() === AuditLevel::Integration) {
                $codes[] = $case->legacyAction();
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @return list<string>
     */
    private static function eventValuesForLevel(string $level): array
    {
        $values = [];

        foreach (AuditEvent::cases() as $case) {
            if ($case->level()->value === $level) {
                $values[] = $case->value;
            }
        }

        return $values;
    }
}
