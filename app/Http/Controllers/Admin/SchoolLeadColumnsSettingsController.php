<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolLeadColumnsSettingsSaveRequest;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;

class SchoolLeadColumnsSettingsController extends Controller
{
    public function getColumnsSettings()
    {
        $userId = Auth::id();

        $settings = UserTableSetting::where('user_id', $userId)
            ->where('table_key', 'school_leads_index')
            ->first();

        $columns = $settings?->columns;

        if (!is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    public function saveColumnsSettings(SchoolLeadColumnsSettingsSaveRequest $request)
    {
        $userId = Auth::id();
        $data = $request->validated();
        $payload = [];

        if (array_key_exists('columns', $data) && is_array($data['columns'])) {
            $payload['columns'] = $data['columns'];
        }

        if (array_key_exists('page_length', $data) && $data['page_length'] !== null) {
            $payload['page_length'] = (int) $data['page_length'];
        }

        if ($payload === []) {
            return response()->json([
                'success' => true,
            ]);
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id'   => $userId,
                'table_key' => 'school_leads_index',
            ],
            $payload
        );

        return response()->json([
            'success' => true,
        ]);
    }
}
