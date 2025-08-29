<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MyGroupController extends Controller
{
    /**
     * Страница "Моя группа"
     */
    public function index()
    {
        // Отдаём Blade, данные подгрузим AJAX'ом
        return view('user.myGroup');
    }

    /**
     * AJAX-данные для визуализации
     */
    public function data()
    {
        $me = Auth::user();

        if (!$me || !$me->team_id) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не состоит в группе.'
            ]);
        }

        // Текущий пользователь
        $current = [
            'id'     => $me->id,
            'name'   => $me->name,
            'avatar' => $this->avatarUrl($me->image_crop, $me->image),
        ];

        // Одногруппники (рандомный порядок)
        $peers = User::query()
            ->where('team_id', $me->team_id)
            ->where('id', '!=', $me->id)
            ->where('is_enabled', 1)
            ->inRandomOrder()
            ->get(['id', 'name', 'image', 'image_crop']);

        $list = $peers->map(function ($u) {
            return [
                'id'     => $u->id,
                'name'   => $u->name,
                'avatar' => $this->avatarUrl($u->image_crop, $u->image),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'current' => $current,
            'peers'   => $list,
        ]);
    }

    /**
     * Сборка ссылки на аватар
     */
    private function avatarUrl(?string $imageCrop, ?string $imageFallback = null): string
    {
        // приоритет image_crop
        $raw = $imageCrop ?: $imageFallback;

        // если пусто → дефолтная
        if (!$raw) {
            return asset('img/default-avatar.png');
        }

        // если уже ссылка (например https://...) → отдаём как есть
        if (preg_match('~^https?://~i', $raw)) {
            return $raw;
        }

        // нормализуем имя файла
        $filename = basename($raw);
        $path = 'avatars/' . ltrim($filename, '/');

        // если файл не найден в storage → дефолтная
        if (!Storage::disk('public')->exists($path)) {
            return asset('img/default-avatar.png');
        }

        // правильный путь через симлинк storage
        return Storage::url($path);
    }
}
