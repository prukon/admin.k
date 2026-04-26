<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * При `php artisan test` сначала грузится Artisan + .env (часто staging), затем
     * тот же экземпляр приложения с env=staging. Флаг KIDSCRM_PHPUNIT задаётся только в phpunit.xml;
     * при нём выравниваем $app['env'] для настоящих прогонов PHPUnit.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $fromPhpUnit = getenv('KIDSCRM_PHPUNIT') === '1' || (($_ENV['KIDSCRM_PHPUNIT'] ?? '') === '1');
        if ($fromPhpUnit && ! $this->app->environment('testing')) {
            $this->app->instance('env', 'testing');
        }
    }
}
