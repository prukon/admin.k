<?php

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;
use Jenssegers\Agent\Agent;

class LogUserLogin
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user || ! $user->id) {
            return;
        }

        $ipAddress = Request::ip();
        $agent = new Agent();
        $platform = $agent->platform();
        $browser = $agent->browser();
        $device = $agent->device();
        $platformVersion = $agent->version($platform);
        $browserVersion = $agent->version($browser);
        $isMobile = $agent->isMobile() ? 'Да' : 'Нет';
        $isTablet = $agent->isTablet() ? 'Да' : 'Нет';
        $isDesktop = $agent->isDesktop() ? 'Да' : 'Нет';

        $description = "Логин: {$user->name}, IP: {$ipAddress}
        Платформа: {$platform} {$platformVersion}, Браузер: {$browser} {$browserVersion}, 
        Устройство: {$device},ПК: {$isDesktop}, Моб. устройство: {$isMobile}, Планшет: {$isTablet}";

        $this->auditLogger->record(
            AuditEvent::AuthLogin,
            AuditContext::make($description)
                ->withAuthorId((int) $user->id)
        );
    }
}
