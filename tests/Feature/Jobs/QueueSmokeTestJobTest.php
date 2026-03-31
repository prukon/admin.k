<?php

namespace Tests\Feature\Jobs;

use App\Jobs\QueueSmokeTestJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QueueSmokeTestJobTest extends JobsTestCase
{
    private function workQueueUntilEmpty(int $maxIterations = 10): void
    {
        for ($i = 0; $i < $maxIterations; $i++) {
            $count = (int) DB::table('jobs')->count();
            if ($count <= 0) {
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

    public function test_smoke_job_succeeds_via_database_queue(): void
    {
        $runId = (string) Str::uuid();

        dispatch(new QueueSmokeTestJob($runId, 'success'));
        $this->workQueueUntilEmpty();

        $this->assertDatabaseHas('queue_smoke_test_runs', [
            'run_id' => $runId,
            'scenario' => 'success',
            'status' => 'succeeded',
        ]);
    }

    public function test_smoke_job_writes_log_in_log_scenario(): void
    {
        Log::spy();
        $runId = (string) Str::uuid();

        dispatch(new QueueSmokeTestJob($runId, 'log'));
        $this->workQueueUntilEmpty();

        $this->assertDatabaseHas('queue_smoke_test_runs', [
            'run_id' => $runId,
            'scenario' => 'log',
            'status' => 'succeeded',
        ]);

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context = []) use ($runId) {
                return $message === 'Queue smoke test job executed'
                    && ($context['run_id'] ?? null) === $runId
                    && (int) ($context['attempt'] ?? 0) >= 1;
            })
            ->atLeast()
            ->once();
    }

    public function test_smoke_job_fail_once_retries_and_then_succeeds(): void
    {
        $runId = (string) Str::uuid();

        dispatch(new QueueSmokeTestJob($runId, 'fail_once'));

        // 1st attempt => retrying
        Artisan::call('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--sleep' => 0,
            '--tries' => 1,
            '--quiet' => true,
        ]);

        $this->assertDatabaseHas('queue_smoke_test_runs', [
            'run_id' => $runId,
            'scenario' => 'fail_once',
            'status' => 'retrying',
            'attempts' => 1,
        ]);

        // 2nd attempt => succeed
        $this->workQueueUntilEmpty();

        $this->assertDatabaseHas('queue_smoke_test_runs', [
            'run_id' => $runId,
            'scenario' => 'fail_once',
            'status' => 'succeeded',
        ]);
    }
}

