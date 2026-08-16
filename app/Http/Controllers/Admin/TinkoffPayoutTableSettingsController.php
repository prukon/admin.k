<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ColumnsSettingsWithPageLengthSaveRequest;
use App\Models\UserTableSetting;
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

    public function saveColumnsSettings(ColumnsSettingsWithPageLengthSaveRequest $request)
    {
        $userId = Auth::id();
        $payload = $request->persistPayload();

        if (array_key_exists('columns', $payload) && is_array($payload['columns'])) {
            $normalized = $payload['columns'];
            unset($normalized['bank_fee']);
            $payload['columns'] = $normalized;
        }

        if ($payload === []) {
            return response()->json(['success' => true]);
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id' => $userId,
                'table_key' => self::TABLE_KEY,
            ],
            $payload
        );

        return response()->json(['success' => true]);
    }
}

