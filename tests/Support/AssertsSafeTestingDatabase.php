<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

trait AssertsSafeTestingDatabase
{
    private const ALLOWED_TEST_DATABASE = 'prukon_test.kidcrm.testing';

    protected function assertSafeTestingEnvironment(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException(
                "SAFETY GUARD: Тесты можно запускать только в окружении 'testing'. " .
                "Сейчас: '" . app()->environment() . "'. " .
                "Запускай: php artisan test --env=testing"
            );
        }

        $dbName = DB::connection()->getDatabaseName();

        if (! is_string($dbName) || $dbName === '') {
            throw new RuntimeException(
                'SAFETY GUARD: Не удалось определить имя базы данных для текущего подключения.'
            );
        }

        if ($dbName !== self::ALLOWED_TEST_DATABASE) {
            throw new RuntimeException(
                "SAFETY GUARD: Подключение указывает на НЕразрешённую БД: '{$dbName}'. " .
                "Разрешена ТОЛЬКО: '" . self::ALLOWED_TEST_DATABASE . "'. " .
                'Проверь, что ты запускаешь тесты так: php artisan test --env=testing ' .
                'и что в .env.testing указана правильная DB_DATABASE.'
            );
        }
    }
}
