<?php

namespace Tests\Feature\Console;

use App\Console\Kernel;
use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class KernelPayoutScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_uses_interval_from_setting_when_row_exists(): void
    {
        Setting::setTinkoffPayoutScheduledIntervalMinutes(15);

        $kernel = $this->app->make(Kernel::class);
        $schedule = $this->app->make(Schedule::class);

        $ref = new ReflectionClass($kernel);
        $method = $ref->getMethod('schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $events = $schedule->events();
        $cronStrings = array_map(fn ($e) => $e->expression, $events);

        $this->assertContains('*/15 * * * *', $cronStrings);
    }

    public function test_schedule_uses_config_default_when_no_setting_row(): void
    {
        Setting::query()->where('name', 'tinkoff_payout_scheduled_interval_minutes')->delete();
        config(['tinkoff.payouts.scheduled_interval_minutes' => 10]);

        $kernel = $this->app->make(Kernel::class);
        $schedule = $this->app->make(Schedule::class);

        $ref = new ReflectionClass($kernel);
        $method = $ref->getMethod('schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $events = $schedule->events();
        $cronStrings = array_map(fn ($e) => $e->expression, $events);

        $this->assertContains('*/10 * * * *', $cronStrings);
    }
}
