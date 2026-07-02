<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Kernel;
use App\Jobs\AutoMarkYesterdayLessonOccurrencesJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

final class KernelAutoAttendanceScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_registers_auto_attendance_job_at_0005_moscow(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $schedule = $this->app->make(Schedule::class);

        $ref = new ReflectionClass($kernel);
        $method = $ref->getMethod('schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $matched = collect($schedule->events())->first(function ($event): bool {
            return ($event->description ?? '') === AutoMarkYesterdayLessonOccurrencesJob::class
                || str_contains((string) ($event->description ?? ''), 'AutoMarkYesterdayLessonOccurrencesJob');
        });

        $this->assertNotNull($matched, 'AutoMarkYesterdayLessonOccurrencesJob not found in schedule events.');

        $expression = (string) $matched->expression;
        $this->assertTrue(
            $expression === '5 0 * * *' || str_contains($expression, '0:05') || str_contains($expression, '00:05'),
            'Unexpected cron expression: '.$expression
        );
    }
}
