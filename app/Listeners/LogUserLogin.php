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
        \Log::info('User ID:', ['id' => $user->id]); // Добавьте эту строку

        if (!$user || !$user->id) {
            \Log::error('Пользователь не найден при входе в систему.');
            return;
        }


        $ipAddress = Request::ip();

        $agent = new Agent();
        $deviceType = $agent->device();

        $platform = $agent->platform();
        $browser = $agent->browser();
        $device = $agent->device();


        $description = "Логин: {$user->name}, IP: {$ipAddress}\n Платформа: {$platform}, Браузер: {$browser}, Устройство: {$device}";

        $logData = [
            'type' => 4,
            'action' => 40,
            'author_id' => $user->id,
            'description' => $description,
        ];

        \Log::info('Данные для сохранения в лог:', $logData);

        Log::create($logData);
    }
}
