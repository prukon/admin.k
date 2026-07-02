<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\AutoMarkYesterdayLessonOccurrencesJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Jobs\JobsTestCase;

final class AutoMarkYesterdayLessonOccurrencesJobQueueTest extends JobsTestCase
{
    private function workQueueUntilEmpty(int $maxIterations = 20): void
    {
        for ($i = 0; $i < $maxIterations; $i++) {
            if ((int) DB::table('jobs')->count() <= 0) {
                return;
            }

            Artisan::call('queue:work', [
                'connection' => 'database',
                '--once' => true,
                '--sleep' => 0,
                '--tries' => 1,
                '--quiet' => true,
            ]);
        }

        $this->fail('Queue did not drain within max iterations.');
    }

    public function test_job_can_be_dispatched_and_processed_from_queue(): void
    {
        AutoMarkYesterdayLessonOccurrencesJob::dispatch('2026-07-02');

        $this->assertGreaterThan(0, (int) DB::table('jobs')->count());

        $this->workQueueUntilEmpty();

        $this->assertSame(0, (int) DB::table('jobs')->count());
        $this->assertSame(0, (int) DB::table('failed_jobs')->count());
    }
}
