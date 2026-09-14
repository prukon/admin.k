<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use App\Support\OpsMonitor;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Mockery;
use PDOException;

/**
 * Хелперы GET /password/reset и POST /password/email.
 */
abstract class PasswordResetTestCase extends SessionAuthTestCase
{
    protected function passwordRequestUrl(): string
    {
        return route('password.request');
    }

    protected function passwordEmailUrl(): string
    {
        return route('password.email');
    }

    protected function uniqueConstraintViolation(): UniqueConstraintViolationException
    {
        $previous = new PDOException(
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry'
        );
        $previous->errorInfo = ['23000', 1062, 'Duplicate entry'];

        return new UniqueConstraintViolationException(
            'mysql',
            'insert into `password_reset_tokens` (`email`, `token`, `created_at`) values (?, ?, ?)',
            ['reset@example.test', 'hash', '2026-09-13 23:18:50'],
            $previous
        );
    }

    protected function mockBrokerThrowsUniqueConstraint(): void
    {
        $broker = Mockery::mock(PasswordBroker::class);
        $broker->shouldReceive('sendResetLink')
            ->once()
            ->andThrow($this->uniqueConstraintViolation());

        Password::shouldReceive('broker')->andReturn($broker);
    }

    protected function sentStatusMessage(): string
    {
        return 'Мы отправили вам ссылку для сброса пароля на ваш email.';
    }

    /**
     * UX-баг: disabled в обработчике submit до ухода POST отменяет native submit.
     */
    protected function assertSubmitGuardDoesNotCancelNativePost(string $source): void
    {
        $timeoutPos = strpos($source, 'setTimeout');
        $disabledPos = strpos($source, 'button.disabled = true');
        $this->assertNotFalse($timeoutPos, 'нужен setTimeout вокруг disabled');
        $this->assertNotFalse($disabledPos, 'кнопка гасится после submit');
        $this->assertGreaterThan(
            $timeoutPos,
            $disabledPos,
            'button.disabled = true должен быть внутри setTimeout, иначе браузер не шлёт POST'
        );
        $this->assertMatchesRegularExpression(
            '/setTimeout\s*\(\s*function\s*\(\s*\)\s*\{[^}]*button\.disabled\s*=\s*true/s',
            $source
        );

        $submittingCheckPos = strpos($source, "getAttribute('data-submitting')");
        $preventPos = strpos($source, 'event.preventDefault()');
        $this->assertNotFalse($submittingCheckPos);
        $this->assertNotFalse($preventPos);
        $this->assertGreaterThan(
            $submittingCheckPos,
            $preventPos,
            'preventDefault только если форма уже data-submitting, не на первом клике'
        );
        $this->assertSame(1, substr_count($source, "addEventListener('submit'"));
        $this->assertSame(1, substr_count($source, 'event.preventDefault()'));
        $this->assertStringNotContainsString('$.ajax', $source);
        $this->assertStringNotContainsString('fetch(', $source);
    }

    protected function assertFirstPaintSubmitIsEnabled(string $html): void
    {
        $this->assertTrue(
            (bool) preg_match('/<button[^>]*id="forgot-password-submit"[^>]*>/', $html, $button),
            'кнопка #forgot-password-submit в HTML'
        );
        $this->assertStringNotContainsString('disabled', $button[0]);

        $this->assertTrue(
            (bool) preg_match('/<form[^>]*id="forgot-password-form"[^>]*>/', $html, $form),
            'форма #forgot-password-form в HTML'
        );
        $this->assertStringNotContainsString('data-submitting', $form[0]);

        $this->assertTrue(
            (bool) preg_match('/<input[^>]*id="email"[^>]*>/', $html, $email),
            'поле #email в HTML'
        );
        $this->assertStringNotContainsString('disabled', $email[0]);
    }

    /**
     * 1062 на password_reset_tokens ловится в контроллере — пульт «500» не растёт.
     */
    protected function assertUniqueConstraintDoesNotBumpOpsMonitor(TestResponse $response, int $countBefore): void
    {
        $this->assertNotServerError($response, '1062 password_reset_tokens');
        $after = OpsMonitor::snapshot();
        $this->assertSame(
            $countBefore,
            (int) $after['errors']['count'],
            'UniqueConstraintViolationException на POST /password/email не должен писаться в пульт'
        );
        $this->assertNotSame('UniqueConstraintViolationException', $after['errors']['last_class']);
    }

    protected function opsErrorCount(): int
    {
        return (int) OpsMonitor::snapshot()['errors']['count'];
    }

    protected function tooLongEmail(): string
    {
        return str_repeat('a', 250).'@example.test';
    }

    protected function forgotPasswordBladeSource(): string
    {
        $path = resource_path('views/auth/passwords/email.blade.php');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    protected function forgotPasswordInlineScriptFromHtml(string $html): string
    {
        $start = strpos($html, "getElementById('forgot-password-form')");
        $this->assertNotFalse($start, 'inline JS #forgot-password-form должен попасть в HTML страницы');

        return substr($html, $start, 900);
    }
}
