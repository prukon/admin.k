<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleStaffColumnsSettingsSaveRequest;
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

    public function saveColumnsSettings(RoleStaffColumnsSettingsSaveRequest $request)
    {
        $tableKey = $this->tableKey($request);
        $data = $request->validated();
        $payload = [];

        if (array_key_exists('columns', $data) && is_array($data['columns'])) {
            $payload['columns'] = $data['columns'];
        }

        if ($tableKey === 'role_staff_admin'
            && array_key_exists('page_length', $data)
            && $data['page_length'] !== null) {
            $payload['page_length'] = (int) $data['page_length'];
        }

        if ($payload === []) {
            return response()->json(['success' => true]);
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id'   => Auth::id(),
                'table_key' => $tableKey,
            ],
            $payload
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
