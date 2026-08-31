<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Mailable;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \Illuminate\Auth\Events\Login::class => [
            \App\Listeners\LogUserLogin::class,
        ],
        // Лог исходящих писем (см. отчёт /admin/reports/emails).
        \Illuminate\Mail\Events\MessageSending::class => [
            [\App\Listeners\LogOutgoingEmail::class, 'sending'],
        ],
        \Illuminate\Mail\Events\MessageSent::class => [
            [\App\Listeners\LogOutgoingEmail::class, 'sent'],
        ],
    ];
 

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Laravel 10 не кладёт класс Mailable в MessageSending::$data.
        // Без этого outgoing_email_logs.mailable_class всегда null — пульт Welcome врёт.
        Mailable::buildViewDataUsing(static function (Mailable $mailable): array {
            return [
                '__laravel_mailable' => $mailable::class,
            ];
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
