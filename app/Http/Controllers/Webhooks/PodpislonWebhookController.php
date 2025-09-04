<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractEvent;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Http\Request;

class PodpislonWebhookController extends Controller
{
    public function handle(Request $request, SignatureProvider $provider)
    {
        // Простейшая проверка секрета (подмените на реальную валидацию подписи/хедера провайдера)
        $secret = config('services.podpislon.webhook_secret');
        $token  = $request->header('X-Webhook-Token');

        if (!$secret || !$token || !hash_equals($secret, $token)) {
            return response()->json(['message'=>'Forbidden'], 403);
        }

        $payload = $request->json()->all();
        // ожидаем, что в payload есть provider_doc_id и event/status
        $providerDocId = $payload['provider_doc_id'] ?? $payload['document_id'] ?? null;
        $event         = $payload['event'] ?? $payload['status'] ?? null;

        if (!$providerDocId) {
            return response()->json(['message'=>'No document id'], 422);
        }

        $contract = Contract::where('provider_doc_id', $providerDocId)->first();
        if (!$contract) {
            return response()->json(['message'=>'Contract not found'], 404);
        }

        // логируем сырой вебхук
        ContractEvent::create([
            'contract_id' => $contract->id,
            'type'        => 'webhook_raw',
            'payload_json'=> json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        // Мапим статус
        $status = match($event) {
        'opened'  => 'opened',
            'signed'  => 'signed',
            'expired' => 'expired',
            'revoked' => 'revoked',
            'sent'    => 'sent',
            'failed'  => 'failed',
            default   => null
        };

        if ($status && $status !== $contract->status) {
            $contract->status = $status;
            $contract->save();

            ContractEvent::create([
                'contract_id' => $contract->id,
                'type'        => $status,
                'payload_json'=> null,
            ]);

            if ($status === 'signed' && !$contract->signed_pdf_path) {
                // подтянем подписанный
                try {
                    $file = $provider->downloadSigned($contract);
                    $path = 'documents/'.date('Y/m').'/'.$file['filename'];
                    \Storage::put($path, $file['content']);
                    $contract->signed_pdf_path = $path;
                    $contract->signed_at = now();
                    $contract->save();

                    ContractEvent::create([
                        'contract_id' => $contract->id,
                        'type'        => 'signed_pdf_saved',
                        'payload_json'=> json_encode(['path'=>$path], JSON_UNESCAPED_UNICODE),
                    ]);
                } catch (\Throwable $e) {
                    ContractEvent::create([
                        'contract_id' => $contract->id,
                        'type'        => 'failed',
                        'payload_json'=> json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }
        }

        return response()->json(['ok'=>true]);
    }
}
