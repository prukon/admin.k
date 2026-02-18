<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Config;
use App\Models\TinkoffPayment;
use App\Services\Tinkoff\TinkoffPayoutsService;
use Illuminate\Support\Facades\Log;

class TinkoffDealController extends Controller
{
    public function close($deal, TinkoffPayoutsService $svc)
    {
        $dealId = (string) $deal;
        $payment = TinkoffPayment::where('deal_id', $dealId)->first();
        if (!$payment) {
            return back()->withErrors(['tinkoff' => 'Не найден платеж с таким DealId']);
        }

        // Закрываем сделку ключами партнёра (partner-specific e2c keys)
        $res = $svc->closeSpDeal($dealId, (int) $payment->partner_id);

        // Важно: закрытие сделки НЕ означает, что оплата CONFIRMED.
        // Не трогаем status; просто сохраняем факт/ответ в payload для диагностики.
        $payments = TinkoffPayment::where('deal_id', $dealId)->get();
        foreach ($payments as $p) {
            $pl = $p->payload ?? [];
            $pl['deal_close'] = [
                'closed_at' => now()->toISOString(),
                'response'  => $res,
            ];
            $p->payload = $pl;
            $p->save();
        }

        if (empty($res['Success'])) {
            Log::channel('tinkoff')->warning('[deal][close] failed', ['deal_id' => $dealId, 'res' => $res]);
            return back()->withErrors(['tinkoff' => 'Банк вернул ошибку при закрытии сделки']);
        }

        return back()->with('status', 'Сделка закрыта (в банк отправлено)');
    }
}
