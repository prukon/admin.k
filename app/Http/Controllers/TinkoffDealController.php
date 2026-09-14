<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tinkoff\TinkoffDealCloseRequest;
use App\Models\TinkoffPayment;
use App\Services\Tinkoff\TinkoffPayoutsService;
use Illuminate\Support\Facades\Log;
use Throwable;

class TinkoffDealController extends Controller
{
    public function close(TinkoffDealCloseRequest $request, TinkoffPayoutsService $svc)
    {
        $dealId = $request->dealId();
        $payment = TinkoffPayment::where('deal_id', $dealId)->first();
        if (! $payment) {
            return back()->withErrors(['tinkoff' => 'Не найден платеж с таким DealId']);
        }

        try {
            $res = $svc->closeSpDeal($dealId, (int) $payment->partner_id);
        } catch (Throwable $e) {
            Log::channel('tinkoff')->warning('[deal][close] exception', [
                'deal_id' => $dealId,
                'error' => $e->getMessage(),
            ]);
            $res = [
                'Success' => false,
                'Message' => 'Не удалось связаться с банком',
                'Details' => $e->getMessage(),
            ];
        }

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

            return back()->withErrors(['tinkoff' => $this->closeDealUserMessage($res)]);
        }

        return back()->with('status', 'Сделка закрыта (в банк отправлено)');
    }

    /**
     * Текст для карточки платежа: Message / Details банка, иначе HTTP-код.
     */
    private function closeDealUserMessage(array $res): string
    {
        $message = trim((string) ($res['Message'] ?? ''));
        $details = trim((string) ($res['Details'] ?? ''));
        $http = $res['http_status'] ?? null;
        $httpLabel = is_numeric($http) ? 'HTTP '.(int) $http : '';

        $parts = [];
        if ($message !== '') {
            $parts[] = $message;
        }
        if ($details !== '' && $details !== $message) {
            $parts[] = $details;
        }

        if ($parts === []) {
            return $httpLabel !== ''
                ? 'Банк вернул ошибку при закрытии сделки ('.$httpLabel.')'
                : 'Банк вернул ошибку при закрытии сделки';
        }

        $text = implode('. ', $parts);
        if ($httpLabel !== '' && (int) $http >= 400) {
            $text .= ' ('.$httpLabel.')';
        }

        return $text;
    }
}
