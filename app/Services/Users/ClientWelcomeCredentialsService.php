<?php

namespace App\Services\Users;

use App\Mail\ClientSiblingAddedMail;
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
     * Уведомление о втором (и далее) ребёнке: без нового логина/пароля.
     *
     * @return array{sent: bool, error: string|null}
     */
    public function sendSiblingAdded(
        User $student,
        User $familyLoginUser,
        string $notifyEmail,
        int $partnerId,
    ): array {
        $notifyEmail = trim($notifyEmail);
        $loginEmail = trim((string) ($familyLoginUser->email ?? ''));

        if ($notifyEmail === '' || $loginEmail === '') {
            return [
                'sent'  => false,
                'error' => 'Не указан email для уведомления о семейном кабинете.',
            ];
        }

        $partnerTitle = (string) (Partner::query()->whereKey($partnerId)->value('title') ?? config('app.name'));

        try {
            Mail::to($notifyEmail)->send(new ClientSiblingAddedMail(
                student: $student,
                familyLoginUser: $familyLoginUser,
                partnerTitle: $partnerTitle,
                partnerId: $partnerId,
                loginUrl: url('/login'),
            ));

            return ['sent' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('[ClientWelcomeCredentials] sibling-added email send failed', [
                'user_id'             => $student->id,
                'family_login_user_id'=> $familyLoginUser->id,
                'partner_id'          => $partnerId,
                'email'               => $notifyEmail,
                'error'               => $e->getMessage(),
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

    /**
     * Текст ответа после create второго ребёнка в семье.
     */
    public function createSiblingResponseMessage(string $createdPrefix, ?string $email, bool $sent): string
    {
        $recipientEmail = trim((string) ($email ?? ''));

        if ($sent) {
            return $recipientEmail !== ''
                ? "{$createdPrefix}. Уведомление о доступе через семейный кабинет отправлено на {$recipientEmail}."
                : "{$createdPrefix}. Уведомление о доступе через семейный кабинет отправлено.";
        }

        return $recipientEmail !== ''
            ? "{$createdPrefix}, но не удалось отправить уведомление на {$recipientEmail}."
            : "{$createdPrefix}, но не удалось отправить уведомление о семейном кабинете.";
    }
}
