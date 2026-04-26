<?php

/*
 * Bootstrap для PHPUnit.
 *
 * Цель: тесты должны быть невосприимчивы к закэшированному конфигу со стенда
 * (например, после `php artisan config:cache` в deploy.sh с APP_ENV=staging).
 * Если bootstrap/cache/config.php присутствует, Laravel НЕ перечитывает .env / .env.testing,
 * и тесты подключаются к staging-БД и срываются на SAFETY GUARD в CrmTestCase.
 *
 * Решение: при запуске PHPUnit удаляем закэшированный конфиг до подключения autoload.
 * Сам staging-сайт (web/CLI вне PHPUnit) этот файл не подключает.
 */

$configCache = __DIR__.'/../bootstrap/cache/config.php';
if (is_file($configCache)) {
    @unlink($configCache);
}

require __DIR__.'/../vendor/autoload.php';
