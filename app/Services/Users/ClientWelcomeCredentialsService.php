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
    public function send(User $student, string $plainPassword, int $partnerId): array
    {
        $student->loadMissing('role');

        if ($student->role?->name !== 'user') {
            return [
                'sent'  => false,
                'error' => 'Отправка доступна только для учеников.',
            ];
        }

        $email = trim((string) ($student->email ?? ''));
        if ($email === '') {
            return [
                'sent'  => false,
                'error' => 'У ученика не указан email.',
            ];
        }

        $partnerTitle = (string) (Partner::query()->whereKey($partnerId)->value('title') ?? config('app.name'));

        try {
            Mail::to($email)->send(new ClientWelcomeCredentialsMail(
                student: $student,
                plainPassword: $plainPassword,
                partnerTitle: $partnerTitle,
                partnerId: $partnerId,
                loginUrl: url('/login'),
            ));

            return ['sent' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('[ClientWelcomeCredentials] email send failed', [
                'user_id'    => $student->id,
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
     * @return array{sent: bool, error: string|null}
     */
    public function regenerateAndSend(User $student, int $partnerId): array
    {
        $plainPassword = $this->generatePassword();
        $student->password = $plainPassword;
        $student->save();

        return $this->send($student, $plainPassword, $partnerId);
    }
}
