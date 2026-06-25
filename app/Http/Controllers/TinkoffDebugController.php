<?php

namespace App\Http\Controllers;

use App\Services\Tinkoff\TbankTerminalConfig;
use App\Services\Tinkoff\TinkoffApiClient;
use App\Services\Tinkoff\TinkoffSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TinkoffDebugController extends Controller
{
    public function state($paymentId): JsonResponse
    {
        $cfg = TbankTerminalConfig::tryPaymentConfig();
        if ($cfg === null) {
            return response()->json(['error' => 'T‑Bank terminal is not configured'], 503);
        }

        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'PaymentId'   => $paymentId,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);
        $res = TinkoffApiClient::post($cfg['base_url'], '/v2/GetState', $payload);

        return response()->json($res);
    }

    public function tpayStatus(): JsonResponse
    {
        $cfg = TbankTerminalConfig::tryPaymentConfig();
        if ($cfg === null) {
            return response()->json(['error' => 'T‑Bank terminal is not configured'], 503);
        }

        $tk = (string) $cfg['terminal_key'];
        $base = rtrim((string) $cfg['base_url'], '/');
        $url = "{$base}/v2/TinkoffPay/terminals/{$tk}/status";
        $res = TinkoffApiClient::get($url);

        return response()->json($res);
    }

    public function verifyToken(Request $r): JsonResponse
    {
        $cfg = TbankTerminalConfig::tryPaymentConfig();
        if ($cfg === null) {
            return response()->json(['error' => 'T‑Bank terminal is not configured'], 503);
        }

        $data = $r->json()->all();
        $secret = $cfg['password'];

        $expected = TinkoffSignature::makeToken($data, $secret);

        return response()->json([
            'received' => $data['Token'] ?? null,
            'expected' => $expected,
            'match'    => isset($data['Token']) ? hash_equals($data['Token'], $expected) : false,
            'sorted'   => (function ($arr) use ($secret) {
                unset($arr['Token']);
                $arr['Password'] = $secret;
                ksort($arr, SORT_STRING);
                $out = [];
                foreach ($arr as $k => $v) {
                    if (is_array($v) || is_object($v)) {
                        continue;
                    }
                    $out[$k] = is_bool($v) ? ($v ? 'true' : 'false') : (is_null($v) ? null : (string) $v);
                }

                return $out;
            })($data),
        ]);
    }

    public function ingest(Request $r, \App\Services\Tinkoff\TinkoffPaymentsService $svc): JsonResponse
    {
        $data = $r->json()->all();
        if (! isset($data['PaymentId']) || ! isset($data['Status'])) {
            return response()->json(['ok' => false, 'err' => 'PaymentId/Status required'], 422);
        }

        // Обработать без валидации токена — ТОЛЬКО админ-дебаг!
        $svc->handleWebhook($data, skipSignature: true);

        return response()->json(['ok' => true]);
    }
}
