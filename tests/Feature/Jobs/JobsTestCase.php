<?php

namespace Tests\Feature\Jobs;

use Database\Seeders\PermissionGroupsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class JobsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // В тестах не должны зависеть от writable storage/ (file cache).
        config(['cache.default' => 'array']);

        // В некоторых окружениях storage/ может быть недоступен для записи тестовым процессом.
        // Переключаем логирование на errorlog и переопределяем file-based каналы.
        config(['logging.default' => 'errorlog']);
        config([
            'logging.channels.tinkoff' => [
                'driver' => 'errorlog',
                'level' => 'debug',
            ],
        ]);

        // Partner::creating/created expects base roles & permissions exist.
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionGroupsSeeder::class);
        $this->seed(PermissionSeeder::class);
    }
}

