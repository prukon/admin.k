<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\LessonPackages\AutoMarkYesterdayLessonOccurrencesService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class AutoMarkYesterdayLessonOccurrencesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  string|null  $occurrenceDateYmd  Для тестов; в проде null → вчера по Europe/Moscow.
     */
    public function __construct(
        public ?string $occurrenceDateYmd = null,
    ) {}

    public function handle(AutoMarkYesterdayLessonOccurrencesService $service): void
    {
        $date = $this->occurrenceDateYmd !== null
            ? CarbonImmutable::parse($this->occurrenceDateYmd, 'Europe/Moscow')->startOfDay()
            : CarbonImmutable::now('Europe/Moscow')->subDay()->startOfDay();

        $service->processForDate($date);
    }
}
