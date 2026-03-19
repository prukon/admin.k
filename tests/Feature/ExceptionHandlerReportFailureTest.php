<?php

namespace Tests\Feature;

use App\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Проверка, что при падении внутри report() (например, отсутствует SentReports.php)
 * приложение не падает и исходное исключение пишется в лог.
 * Тест без реальной оплаты.
 */
class ExceptionHandlerReportFailureTest extends TestCase
{
    use RefreshDatabase;

    private string $failureLogPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->failureLogPath = storage_path('logs/exception_report_failures.log');
        if (file_exists($this->failureLogPath)) {
            @unlink($this->failureLogPath);
        }
    }

    public function test_report_does_not_rethrow_when_report_chain_throws(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        if (!$handler instanceof Handler) {
            $this->markTestSkipped('App uses custom handler');
        }

        $originalMessage = 'Original job error (e.g. CloudKassir failed)';
        $simulatedReportError = 'Simulated SentReports.php missing';

        $this->addThrowingReportable($handler, $simulatedReportError);
        try {
            $handler->report(new RuntimeException($originalMessage));
            $this->addToAssertionCount(1);
        } finally {
            $this->removeLastReportable($handler);
        }

        $this->assertFileExists($this->failureLogPath);
        $content = file_get_contents($this->failureLogPath);
        $this->assertStringContainsString($simulatedReportError, $content, 'Log must contain report failure reason');
        $this->assertStringContainsString($originalMessage, $content, 'Log must contain original exception');
    }

    private function addThrowingReportable(Handler $handler, string $message): void
    {
        $handler->reportable(function (Throwable $e) use ($message): void {
            throw new RuntimeException($message);
        });
    }

    private function removeLastReportable(Handler $handler): void
    {
        $ref = new \ReflectionProperty($handler, 'reportCallbacks');
        $ref->setAccessible(true);
        $callbacks = $ref->getValue($handler);
        if (\is_array($callbacks) && $callbacks !== []) {
            array_pop($callbacks);
            $ref->setValue($handler, $callbacks);
        }
        $ref->setAccessible(false);
    }
}
