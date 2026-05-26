<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractTemplateColumnsSettingsSaveRequest;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;

class ContractTemplateColumnsSettingsController extends Controller
{
    private const TABLE_KEY = 'contract_templates_index';

    public function getColumnsSettings()
    {
        $settings = UserTableSetting::where('user_id', Auth::id())
            ->where('table_key', self::TABLE_KEY)
            ->first();

        $columns = $settings?->columns;

        if (! is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    public function saveColumnsSettings(ContractTemplateColumnsSettingsSaveRequest $request)
    {
        $validated = $request->validated();

        UserTableSetting::updateOrCreate(
            [
                'user_id'   => Auth::id(),
                'table_key' => self::TABLE_KEY,
            ],
            [
                'columns' => $validated['columns'],
            ]
        );

        return response()->json(['success' => true]);
    }
}
