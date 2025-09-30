<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Config;
use App\Models\TinkoffPayment;
use App\Services\Tinkoff\TinkoffApiClient;
use App\Services\Tinkoff\TinkoffSignature;

class TinkoffDealController extends Controller
{
    public function close($deal)
    {
        $cfg = Config::get('tinkoff.e2c');
        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'DealId'      => $deal,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);
        TinkoffApiClient::post($cfg['base_url'], '/e2c/v2/CloseSpDeal', $payload);

        TinkoffPayment::where('deal_id', $deal)->update(['status' => 'CONFIRMED']); // оставим так; в UI пометим "сделка закрыта"
        return back()->with('status', 'Сделка закрыта');
    }
}
