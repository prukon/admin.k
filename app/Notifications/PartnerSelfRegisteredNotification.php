<?php

namespace App\Notifications;

use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PartnerSelfRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Partner $partner,
        public string $adminName,
        public string $loginEmail,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['mail' => 'platform_outbound_mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cabinetUrl = url('/cabinet');
        $partnerId = (int) $this->partner->id;

        return (new MailMessage)
            ->subject('Регистрация школы на kidscrm.online')
            ->withSymfonyMessage(function ($message) use ($partnerId): void {
                if ($partnerId > 0) {
                    $message->getHeaders()->addTextHeader('X-Partner-Id', (string) $partnerId);
                }
            })
            ->greeting('Здравствуйте!')
            ->line('Регистрация прошла успешно. Ниже ключевые данные:')
            ->lines([
                'Название школы: ' . $this->partner->title,
                'Email для входа: ' . $this->loginEmail,
                'Имя администратора: ' . $this->adminName,
                'ID партнёра в системе: ' . $this->partner->id,
            ])
            ->action('Открыть личный кабинет', $cabinetUrl)
            ->line('Вы можете войти, используя указанный email и заданный при регистрации пароль.');
    }
}
