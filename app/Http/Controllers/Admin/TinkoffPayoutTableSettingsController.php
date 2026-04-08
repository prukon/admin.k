<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserTableSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TinkoffPayoutTableSettingsController extends Controller
{
    private const TABLE_KEY = 'tinkoff_payouts_index';

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

        if (array_key_exists('bank_fee', $columns)) {
            $v = $columns['bank_fee'];
            $columns['bank_accept_fee'] = $v;
            $columns['bank_payout_fee'] = $v;
            unset($columns['bank_fee']);
        }

        return response()->json($columns);
    }

    public function saveColumnsSettings(Request $request)
    {
        $userId = Auth::id();

        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $rawColumns = $data['columns'];
        $normalized = [];

        foreach ($rawColumns as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                $bool = false;
            }
            $normalized[$key] = $bool;
        }

        unset($normalized['bank_fee']);

        UserTableSetting::updateOrCreate(
            [
                'user_id' => $userId,
                'table_key' => self::TABLE_KEY,
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json(['success' => true]);
    }
}

