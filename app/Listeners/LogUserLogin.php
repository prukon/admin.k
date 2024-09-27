<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;
use App\Models\Log;
use Jenssegers\Agent\Agent;

class LogUserLogin
{
    /**
     * Обработка события.
     *
     * @param  \Illuminate\Auth\Events\Login  $event
     * @return void
     */
    public function handle(Login $event)
    {
        $user = $event->user;
        $ipAddress = Request::ip();
        $agent = new Agent();
        $deviceType = $agent->device();
        $platform = $agent->platform();
        $browser = $agent->browser();
        $device = $agent->device();

        $userAgent = $agent->getUserAgent();
        $platformVersion = $agent->version($platform);
        $browserVersion = $agent->version($browser);
        $languages = implode(', ', $agent->languages());
        $isMobile = $agent->isMobile() ? 'Да' : 'Нет';
        $isTablet = $agent->isTablet() ? 'Да' : 'Нет';
        $isDesktop = $agent->isDesktop() ? 'Да' : 'Нет';
        $isRobot = $agent->isRobot() ? 'Да' : 'Нет';




        $description = "Логин: {$user->name}, IP: {$ipAddress}
        Платформа: {$platform} {$platformVersion}, Браузер: {$browser} {$browserVersion}, 
        Устройство: {$device}, Моб. устройство: {$isMobile}, Планшет: {$isTablet}, ПК: {$isDesktop}
        Языки: {$languages}, User-Agent: {$userAgent}";



        $logData = [
            'type' => 4,
            'action' => 40,
            'author_id' => $user->id,
            'description' => $description,
        ];

    }
}
