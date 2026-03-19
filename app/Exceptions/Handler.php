<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     * Если при отчёте падает код (например, отсутствует SentReports.php у Ignition),
     * перехватываем и пишем в файл напрямую (без Log facade), чтобы приложение не падало.
     */
    public function report(Throwable $e): void
    {
        try {
            parent::report($e);
        } catch (Throwable $reportException) {
            $this->writeReportFailureSafely($reportException, $e);
        }
    }

    /**
     * Render an exception into an HTTP response.
     * Если при рендере падает код (например, Ignition), возвращаем простой 500 без загрузки пакетов.
     */
    public function render($request, Throwable $e): Response
    {
        try {
            return parent::render($request, $e);
        } catch (Throwable $renderException) {
            return $this->fallbackExceptionResponse($request, $e);
        }
    }

    /**
     * Пишем сбой отчёта и исходное исключение в файл без использования Log/автозагрузки.
     * Не используем Log:: — цепочка логирования может снова загрузить Ignition.
     */
    private function writeReportFailureSafely(Throwable $reportException, Throwable $original): void
    {
        $path = storage_path('logs/exception_report_failures.log');
        $line = sprintf(
            "[%s] report_error=%s | report_file=%s | report_line=%d | original=%s | original_file=%s | original_line=%d\n",
            date('Y-m-d H:i:s'),
            str_replace(["\n", "\r"], ' ', $reportException->getMessage()),
            $reportException->getFile(),
            $reportException->getLine(),
            str_replace(["\n", "\r"], ' ', $original->getMessage()),
            $original->getFile(),
            $original->getLine()
        );
        try {
            @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        } catch (Throwable $ignored) {
            @error_log('Exception report failed: ' . $reportException->getMessage() . ' | Original: ' . $original->getMessage());
        }
    }

    /**
     * Простой ответ 500 без рендера через Ignition/Blade.
     */
    private function fallbackExceptionResponse(Request $request, Throwable $e): Response
    {
        $message = config('app.debug') ? $e->getMessage() : 'Server Error';
        return new Response($message, 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
