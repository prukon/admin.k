<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\View;
use App\Models\SocialItem;

class SetSocialItems
{
    public function handle($request, Closure $next)
    {
        // 1) Берём current_partner из сессии или из авторизации
        $partnerId = session('current_partner')
            ?? optional(auth()->user())->partner_id;

        if ($partnerId) {
            // 2) Инициализируем (если ещё нет) стандартный набор соцсетей
            $defaultNames = [
                'vk.com',
                'YouTube.com',
                'facebook.com',
                'Instagram.com',
                'Telegram.org',
                'TikTok.com',
                'WhatsApp.com',
                'Vimeo.com',
            ];

            foreach ($defaultNames as $name) {
                SocialItem::firstOrCreate(
                    ['partner_id' => $partnerId, 'name' => $name],
                    ['link'       => ''] // по умолчанию пустая ссылка
                );
            }

            // 3) Подгружаем уже инициализированные записи
            $socialItems = SocialItem::where('partner_id', $partnerId)
                ->orderBy('name')
                ->get();
        } else {
            // Если партнёр не определён — пустая коллекция
            $socialItems = collect();
        }

        // 4) Делаем доступным во всех blade
        View::share('socialItems', $socialItems);

        return $next($request);
    }
}
