<?php

declare(strict_types=1);

namespace App\Services\SettingPrices;

use RuntimeException;

/**
 * Бизнес-запрет sync начисления ↔ ULP (например смена шаблона при уже разложенном назначении).
 */
final class UsersPriceLessonPackageSyncException extends RuntimeException
{
    public function __construct(
        private readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->field;
    }
}
