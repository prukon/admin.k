<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueueSmokeTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 0;

    /**
     * @param  string  $scenario  success|log|fail_once|fail_always
     */
    public function __construct(
        public string $runId,
        public string $scenario = 'success',
    ) {
    }

    public function handle(): void
    {
        $attempt = (int) ($this->attempts() ?: 1);
        $now = now();

        $existing = DB::table('queue_smoke_test_runs')->where('run_id', $this->runId)->first();
        if (!$existing) {
            DB::table('queue_smoke_test_runs')->insert([
                'run_id' => $this->runId,
                'scenario' => $this->scenario,
                'status' => 'processing',
                'attempts' => $attempt,
                'last_error' => null,
                'started_at' => $now,
                'finished_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $update = [
                'scenario' => $this->scenario,
                'status' => 'processing',
                'attempts' => $attempt,
                'last_error' => null,
                'finished_at' => null,
                'updated_at' => $now,
            ];

            if (empty($existing->started_at)) {
                $update['started_at'] = $now;
            }

            DB::table('queue_smoke_test_runs')
                ->where('run_id', $this->runId)
                ->update($update);
        }

        try {
            if ($this->scenario === 'log') {
                Log::info('Queue smoke test job executed', [
                    'run_id' => $this->runId,
                    'attempt' => $attempt,
                ]);
            }

            if ($this->scenario === 'fail_once' && $attempt === 1) {
                throw new \RuntimeException('Queue smoke test: intentional fail_once');
            }
            if ($this->scenario === 'fail_always') {
                throw new \RuntimeException('Queue smoke test: intentional fail_always');
            }

            DB::table('queue_smoke_test_runs')
                ->where('run_id', $this->runId)
                ->update([
                    'status' => 'succeeded',
                    'attempts' => $attempt,
                    'finished_at' => $now,
                    'updated_at' => $now,
                ]);
        } catch (\Throwable $e) {
            DB::table('queue_smoke_test_runs')
                ->where('run_id', $this->runId)
                ->update([
                    'status' => 'retrying',
                    'attempts' => $attempt,
                    'last_error' => $e->getMessage(),
                    'updated_at' => $now,
                ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $now = now();

        DB::table('queue_smoke_test_runs')
            ->where('run_id', $this->runId)
            ->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
                'finished_at' => $now,
                'updated_at' => $now,
            ]);
    }
}

