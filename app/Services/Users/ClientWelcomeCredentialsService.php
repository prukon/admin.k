<?php

namespace App\Services\Users;

use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClientWelcomeCredentialsService
{
    public function generatePassword(): string
    {
        return Str::password(12, letters: true, numbers: true, symbols: false);
    }

    /**
     * @return array{sent: bool, error: string|null}
     */
    public function send(User $user, string $plainPassword, int $partnerId): array
    {
        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return [
                'sent'  => false,
                'error' => 'У пользователя не указан email.',
            ];
        }

        $partnerTitle = (string) (Partner::query()->whereKey($partnerId)->value('title') ?? config('app.name'));

        try {
            Mail::to($email)->send(new ClientWelcomeCredentialsMail(
                student: $user,
                plainPassword: $plainPassword,
                partnerTitle: $partnerTitle,
                partnerId: $partnerId,
                loginUrl: url('/login'),
            ));

            return ['sent' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('[ClientWelcomeCredentials] email send failed', [
                'user_id'    => $user->id,
                'partner_id' => $partnerId,
                'email'      => $email,
                'error'      => $e->getMessage(),
            ]);

            report($e);

            return [
                'sent'  => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Повторная отправка — только для учеников (роль user).
     *
     * @return array{sent: bool, error: string|null}
     */
    public function regenerateAndSend(User $student, int $partnerId): array
    {
        $student->loadMissing('role');

        if ($student->role?->name !== 'user') {
            return [
                'sent'  => false,
                'error' => 'Отправка доступна только для учеников.',
            ];
        }

        $plainPassword = $this->generatePassword();
        $student->password = $plainPassword;
        $student->save();

        return $this->send($student, $plainPassword, $partnerId);
    }

    /**
     * Текст ответа после create + попытки отправки welcome-письма.
     */
    public function createResponseMessage(string $createdPrefix, ?string $email, bool $sent): string
    {
        $recipientEmail = trim((string) ($email ?? ''));

        if ($sent) {
            return $recipientEmail !== ''
                ? "{$createdPrefix}. Письмо с данными для входа отправлено на {$recipientEmail}."
                : "{$createdPrefix}. Письмо с данными для входа отправлено.";
        }

        return $recipientEmail !== ''
            ? "{$createdPrefix}, но не удалось отправить письмо на {$recipientEmail}."
            : "{$createdPrefix}, но не удалось отправить письмо с данными для входа.";
    }
}
