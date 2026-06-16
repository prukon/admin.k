<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserTableSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleStaffColumnsSettingsController extends Controller
{
    public function getColumnsSettings(Request $request)
    {
        $tableKey = $this->tableKey($request);

        $settings = UserTableSetting::where('user_id', Auth::id())
            ->where('table_key', $tableKey)
            ->first();

        $columns = $settings?->columns;

        if (!is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    public function saveColumnsSettings(Request $request)
    {
        $tableKey = $this->tableKey($request);

        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $raw = $data['columns'];
        $normalized = [];

        foreach ($raw as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                $bool = false;
            }
            $normalized[$key] = $bool;
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id'   => Auth::id(),
                'table_key' => $tableKey,
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json(['success' => true]);
    }

    private function tableKey(Request $request): string
    {
        $tableKey = trim((string) $request->input('table_key', ''));

        if ($tableKey === '' || !preg_match('/^role_staff_[a-z0-9_]+$/', $tableKey)) {
            abort(422, 'Некорректный ключ таблицы.');
        }

        return $tableKey;
    }
}
