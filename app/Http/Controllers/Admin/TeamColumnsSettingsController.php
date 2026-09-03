<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ColumnsSettingsWithPageLengthSaveRequest;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;

class TeamColumnsSettingsController extends Controller
{
    private const TABLE_KEY = 'teams_index';

    /**
     * Вернуть настройки колонок для таблицы "teams_index"
     */
    public function getColumnsSettings()
    {
        $userId = Auth::id();

        $settings = UserTableSetting::where('user_id', $userId)
            ->where('table_key', self::TABLE_KEY)
            ->first();

        $columns = $settings?->columns;

        if (!is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    /**
     * Сохранить настройки колонок и/или «Показать N» для таблицы "teams_index"
     */
    public function saveColumnsSettings(ColumnsSettingsWithPageLengthSaveRequest $request)
    {
        $userId = Auth::id();
        $payload = $request->persistPayload();

        if ($payload === []) {
            return response()->json(['success' => true]);
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id'   => $userId,
                'table_key' => self::TABLE_KEY,
            ],
            $payload
        );

        return response()->json(['success' => true]);
    }
}
