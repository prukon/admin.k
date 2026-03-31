<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\View;
use App\Models\PartnerSocialLink;

class SetSocialItems
{
    public function handle($request, Closure $next)
    {
        // 1) Берём current_partner из сессии или из авторизации
        $partnerId = session('current_partner')
            ?? optional(auth()->user())->partner_id;

        if ($partnerId) {
            // 2) Источник: справочник + ссылки партнёра
            // Показываем только включённые и только с заполненным URL (по требованию "скрываем").
            $socialItems = PartnerSocialLink::query()
                ->where('partner_id', $partnerId)
                ->where('is_enabled', 1)
                ->whereNotNull('url')
                ->where('url', '!=', '')
                ->whereHas('socialNetwork', fn($q) => $q->where('is_enabled', 1))
                ->with(['socialNetwork:id,code,title,icon,sort,is_enabled'])
                ->orderBy('sort')
                ->get()
                ->map(function (PartnerSocialLink $link) {
                    return (object)[
                        'link'  => $link->url,
                        'title' => $link->socialNetwork?->title,
                        'code'  => $link->socialNetwork?->code,
                        'icon'  => $link->socialNetwork?->icon,
                    ];
                });
        } else {
            // Если партнёр не определён — пустая коллекция
            $socialItems = collect();
        }

        // 4) Делаем доступным во всех blade
        View::share('socialItems', $socialItems);

        return $next($request);
    }
}
