<?php

namespace App\Exceptions;

use App\Support\OpsMonitor;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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
     * Маршруты публичных заявок, для которых CSRF 419 нужно писать в laravel.log.
     *
     * @var list<string>
     */
    private const SCHOOL_LEAD_SUBMIT_ROUTE_NAMES = [
        'lead.submit',
        'widget.school-lead.submit',
    ];

    /**
     * Report or log an exception.
     * Если при отчёте падает код (например, отсутствует SentReports.php у Ignition),
     * перехватываем и пишем в файл напрямую (без Log facade), чтобы приложение не падало.
     */
    public function report(Throwable $e): void
    {
        try {
            $this->logSchoolLeadCsrfMismatchIfNeeded($e);
            $this->recordOpsMonitorException($e);
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

        $this->renderable(function (TeamScheduleSlotConflictException $e, Request $request): ?Response {
            if (! $request->expectsJson() && ! $request->ajax()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'conflicts' => $e->conflicts,
            ], 422);
        });

        $this->renderable(function (HttpException $e, Request $request): ?Response {
            // TokenMismatchException до renderable уже map'ится в HttpException 419.
            if ($e->getStatusCode() !== 419 || ! $this->isSchoolLeadSubmitRequest($request)) {
                return null;
            }

            if (! $request->expectsJson() && ! $request->ajax()) {
                return null;
            }

            return response()->json([
                'message' => 'Сессия устарела. Обновите страницу и отправьте заявку ещё раз.',
            ], 419);
        });
    }

    /**
     * Reportable 500 в cache пульта. 4xx / CSRF / сбой cache не должны ломать report().
     */
    private function recordOpsMonitorException(Throwable $e): void
    {
        try {
            if ($e instanceof TokenMismatchException) {
                return;
            }
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return;
            }
            if (! $this->shouldReport($e)) {
                return;
            }
            OpsMonitor::recordException($e);
        } catch (Throwable) {
        }
    }

    /**
     * TokenMismatchException в internalDontReport — без явной записи в лог 419 на заявках не видно.
     */
    private function logSchoolLeadCsrfMismatchIfNeeded(Throwable $e): void
    {
        if (! $e instanceof TokenMismatchException) {
            return;
        }

        $request = request();
        if (! $request instanceof Request || ! $this->isSchoolLeadSubmitRequest($request)) {
            return;
        }

        Log::warning('CSRF token mismatch on school lead submit', [
            'route' => $request->route()?->getName(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->headers->get('referer'),
            'has_session_cookie' => $request->cookies->has(config('session.cookie')),
            'has_token_input' => $request->has('_token'),
        ]);
    }

    private function isSchoolLeadSubmitRequest(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName)
            && in_array($routeName, self::SCHOOL_LEAD_SUBMIT_ROUTE_NAMES, true);
    }
}
