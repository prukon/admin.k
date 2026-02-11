<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserTableSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserTableSettingsController extends Controller
{
    /**
     * Вернуть настройки колонок для текущего пользователя
     * для таблицы "users_index".
     *
     * GET /admin/users/table-settings
     */
    public function getColumnsSettings()
    {
        $userId = Auth::id();

        $settings = UserTableSetting::where('user_id', $userId)
            ->where('table_key', 'users_index')
            ->first();

        // 👉 ВАЖНО: возвращаем ЧИСТЫЙ массив columns или пустой объект
        $columns = $settings?->columns;

        if (!is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    /**
     * Сохранить настройки колонок для текущего пользователя.
     * Ожидает в запросе: columns: { avatar: true, name: false, ... }
     *
     * POST /admin/users/table-settings
     */
    public function saveColumnsSettings(Request $request)
    {
        $userId = Auth::id();

        // валидируем только, что это массив
        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $rawColumns = $data['columns'];

        // аккуратно нормализуем к boolean
        $normalized = [];

        foreach ($rawColumns as $key => $value) {
            // в запрос может прилететь 1/0, "1"/"0", true/false, "true"/"false"
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            // если вдруг ничего не распознали — считаем false
            if ($bool === null) {
                $bool = false;
            }

            $normalized[$key] = $bool;
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id'   => $userId,
                'table_key' => 'users_index',
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json([
            'success' => true,
        ]);
    }
}