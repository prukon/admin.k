<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Config;
use App\Services\Tinkoff\TinkoffApiClient;
use App\Services\Tinkoff\TinkoffSignature;

class TinkoffDebugController extends Controller
{
    public function state($paymentId)
    {
        $cfg = Config::get('tinkoff.payment');
        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'PaymentId'   => $paymentId,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);
        $res = TinkoffApiClient::post($cfg['base_url'], '/v2/GetState', $payload);

        return response()->json($res);
    }

    public function tpayStatus()
    {
        $tk = config('tinkoff.payment.terminal_key');
        $url = "https://rest-api-test.tinkoff.ru/v2/TinkoffPay/terminals/{$tk}/status";
        $res = \App\Services\Tinkoff\TinkoffApiClient::get($url);
        return response()->json($res);
    }

    public function verifyToken(\Illuminate\Http\Request $r)
    {
        $data = $r->json()->all();
        $secret = config('tinkoff.payment.password');

        $expected = \App\Services\Tinkoff\TinkoffSignature::makeToken($data, $secret);
        return response()->json([
            'received' => $data['Token'] ?? null,
            'expected' => $expected,
            'match'    => isset($data['Token']) ? hash_equals($data['Token'], $expected) : false,
            'sorted'   => (function($arr) use($secret){
                unset($arr['Token']); $arr['Password']=$secret; ksort($arr,SORT_STRING);
                $out=[];
                foreach($arr as $k=>$v){
                    if (is_array($v)||is_object($v)) continue;
                    $out[$k]= is_bool($v)?($v?'true':'false') : (is_null($v)?null:(string)$v);
                }
                return $out;
            })($data),
        ]);
    }

    public function ingest(\Illuminate\Http\Request $r, \App\Services\Tinkoff\TinkoffPaymentsService $svc)
    {
        $data = $r->json()->all();
        if (!isset($data['PaymentId']) || !isset($data['Status'])) {
            return response()->json(['ok'=>false,'err'=>'PaymentId/Status required'], 422);
        }
        // Обработать без валидации токена — ТОЛЬКО админ-дебаг!
        $svc->handleWebhook($data, skipSignature: true);
    return response()->json(['ok'=>true]);
}



}
