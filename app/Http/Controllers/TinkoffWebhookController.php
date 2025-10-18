<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Tinkoff\TinkoffPaymentsService;

class TinkoffWebhookController extends Controller
{
    public function payments2(Request $request, TinkoffPaymentsService $svc)
    {
        \Log::channel('tinkoff')->debug('[WEBHOOK RAW]', $request->all());


        try {
            $svc->handleWebhook($request->all());
            Log::channel('tinkoff')->info('[WEBHOOK OK]', $request->all());
            return response('OK', 200);
        } catch (\Throwable $e) {
            Log::channel('tinkoff')->error('[WEBHOOK ERR] '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            // все равно 200, чтобы банк не ретраил бесконечно
            return response('OK', 200);
        }
    }

    public function payments(Request $request, TinkoffPaymentsService $svc)
    {
        \Log::channel('tinkoff')->debug('[WEBHOOK RAW]', [
            'headers' => $request->headers->all(),
            'ip'      => $request->ip(),
            'method'  => $request->getMethod(),
            'body'    => $request->getContent(),     // сырое тело
            'parsed'  => $request->all(),            // распарсенные поля
        ]);

        try {
            $svc->handleWebhook($request->all());
            \Log::channel('tinkoff')->info('[WEBHOOK OK]');
            return response('OK', 200);
        } catch (\Throwable $e) {
            \Log::channel('tinkoff')->error('[WEBHOOK ERR] '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            return response('OK', 200);
        }
    }
}
