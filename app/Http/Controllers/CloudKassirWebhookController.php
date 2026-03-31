<?php

namespace App\Http\Controllers;

use App\Models\FiscalReceipt;
use App\Services\CloudKassir\CloudKassirWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CloudKassirWebhookController extends Controller
{
    public function receipt(
        Request $request,
        CloudKassirWebhookVerifier $verifier
    ): JsonResponse {
        $rawBody = (string) $request->getContent();
        $payload = $request->all();

        $xContentHmac = $request->header('X-Content-HMAC');
        $contentHmac = $request->header('Content-HMAC');

        Log::channel('cloudkassir')->info('[webhook incoming]', [
            'headers' => $request->headers->all(),
            'body' => $payload,
        ]);

        try {
            $isValid = $verifier->isValid($rawBody, $xContentHmac, $contentHmac);

            if (!$isValid) {
                Log::channel('cloudkassir')->warning('[webhook invalid hmac]', [
                    'headers' => $request->headers->all(),
                    'raw' => $rawBody,
                ]);

                // Чтобы не провоцировать бесконечные ретраи провайдера,
                // на первом этапе подтверждаем приём, но не обрабатываем.
                return response()->json(['code' => 0]);
            }

            $externalId = (string) ($payload['Id'] ?? '');
            if ($externalId === '') {
                Log::channel('cloudkassir')->warning('[webhook missing id]', [
                    'payload' => $payload,
                ]);

                return response()->json(['code' => 0]);
            }

            /** @var FiscalReceipt|null $receipt */
            $receipt = FiscalReceipt::query()
                ->where('external_id', $externalId)
                ->first();

            if (!$receipt) {
                Log::channel('cloudkassir')->warning('[webhook receipt not found]', [
                    'external_id' => $externalId,
                    'payload' => $payload,
                ]);

                return response()->json(['code' => 0]);
            }

            $receipt->webhook_payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $receipt->document_number = isset($payload['DocumentNumber']) ? (string) $payload['DocumentNumber'] : $receipt->document_number;
            $receipt->session_number = isset($payload['SessionNumber']) ? (string) $payload['SessionNumber'] : $receipt->session_number;
            $receipt->number = isset($payload['Number']) ? (string) $payload['Number'] : $receipt->number;
            $receipt->fiscal_sign = isset($payload['FiscalSign']) ? (string) $payload['FiscalSign'] : $receipt->fiscal_sign;
            $receipt->device_number = isset($payload['DeviceNumber']) ? (string) $payload['DeviceNumber'] : $receipt->device_number;
            $receipt->reg_number = isset($payload['RegNumber']) ? (string) $payload['RegNumber'] : $receipt->reg_number;
            $receipt->fiscal_number = isset($payload['FiscalNumber']) ? (string) $payload['FiscalNumber'] : $receipt->fiscal_number;
            $receipt->ofd = isset($payload['Ofd']) ? (string) $payload['Ofd'] : $receipt->ofd;
            $receipt->receipt_url = isset($payload['Url']) ? (string) $payload['Url'] : $receipt->receipt_url;
            $receipt->qr_code_url = isset($payload['QrCodeUrl']) ? (string) $payload['QrCodeUrl'] : $receipt->qr_code_url;

            if (!empty($payload['DateTime'])) {
                $receipt->receipt_datetime = $payload['DateTime'];
            }

            $receipt->status = FiscalReceipt::STATUS_PROCESSED;
            $receipt->processed_at = now();
            $receipt->error_code = null;
            $receipt->error_message = null;
            $receipt->failed_at = null;

            $receipt->save();

            return response()->json(['code' => 0]);
        } catch (\Throwable $e) {
            Log::channel('cloudkassir')->error('[webhook failed] ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['code' => 0]);
        }
    }
}