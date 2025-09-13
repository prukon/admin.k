<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PodpislonWebhookController extends Controller
{

    public function handle(Request $request)
    {
        $rid = bin2hex(random_bytes(8));
        $log = Log::channel('podpislon');

        // --- телеметрия запроса
        $log->debug('Webhook received', [
            'rid'          => $rid,
            'method'       => $request->method(),
            'uri'          => $request->getRequestUri(),
            'ip'           => $request->ip(),
            'ips'          => $request->ips(),
            'user_agent'   => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'length'       => $request->header('Content-Length'),
            'headers'      => $this->safeHeaders($request->headers->all()),
            'query'        => $request->query(),
        ]);

        // --- тело: raw + parsed (с фолбэком)
        $raw    = $request->getContent();
        $parsed = $request->all();

        if (empty($parsed) && is_string($raw) && strlen($raw) > 0) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                $parsed = $j;
            } else {
                $qs = [];
                parse_str($raw, $qs);
                if (!empty($qs)) $parsed = $qs;
            }
        }

        // нормализуем ключи в UPPER_SNAKE
        if (!empty($parsed)) {
            $norm = [];
            foreach ($parsed as $k => $v) $norm[strtoupper((string)$k)] = $v;
            $parsed = $norm;
        }

        $log->debug('Webhook payloads', ['rid'=>$rid, 'raw'=>$raw, 'parsed'=>$parsed]);

        // --- подпись (если секрет задан)
        $provided = (string)($parsed['SIGNATURE'] ?? '');
        $secret   = (string)config('services.podpislon.webhook_secret');
        $sigOk    = null;
        $sigAlgo  = null;

        if ($secret !== '') {
            $candidates = [
                'sha256_raw'  => hash_hmac('sha256', (string)$raw, $secret),
                'sha1_raw'    => hash_hmac('sha1',   (string)$raw, $secret),
                'md5_raw'     => hash_hmac('md5',    (string)$raw, $secret),
                'sha256_form' => hash_hmac('sha256', http_build_query($parsed), $secret),
                'sha1_form'   => hash_hmac('sha1',   http_build_query($parsed), $secret),
                'md5_form'    => hash_hmac('md5',    http_build_query($parsed), $secret),
            ];
            foreach ($candidates as $algo => $cand) {
                if ($provided !== '' && hash_equals(strtolower($provided), strtolower($cand))) {
                    $sigOk = true; $sigAlgo = $algo; break;
                }
            }
            $sigOk ??= false;
            if ($sigOk === false) $log->warning('Webhook signature mismatch', ['rid'=>$rid]);
        }

        $log->info('Webhook parsed fields', [
            'rid'               => $rid,
            'EVENT'             => $parsed['EVENT']      ?? null,
            'FILE_ID'           => $parsed['FILE_ID']    ?? ($parsed['ID'] ?? null),
            'COMPANY_ID'        => $parsed['COMPANY_ID'] ?? null,
            'CONTACT'           => $parsed['CONTACT']    ?? null,
            'SIGNATURE_present' => $provided !== '',
            'signature_ok'      => $sigOk,
            'signature_algo'    => $sigAlgo,
        ]);

        try {
            $eventRaw = strtoupper(trim((string)($parsed['EVENT'] ?? '')));
            $fileId   = isset($parsed['FILE_ID']) ? (int)$parsed['FILE_ID']
                : (isset($parsed['ID'])     ? (int)$parsed['ID']     : null);

            // матчим контракт
            $contract = $fileId ? Contract::where('provider_doc_id', $fileId)->first() : null;

            if (!$contract) {
                $log->warning('Orphan webhook: contract not found for FILE_ID', [
                    'rid'=>$rid, 'FILE_ID'=>$fileId, 'payload'=>$parsed,
                ]);
                return response()->json([
                    'ok'=>true, 'rid'=>$rid, 'orphan'=>true,
                    'message'=>'Contract not found for FILE_ID; event logged only',
                ]);
            }

            $log->debug('Contract matched', ['rid'=>$rid, 'contract_id'=>$contract->id]);

            // нормализуем тип события для БД: один раз и понятным именем
            $etype = match (true) {
            $eventRaw === 'DOCUMENT_SIGNED' || str_contains($eventRaw, 'SIGN') => 'webhook_document_signed',
            $eventRaw === 'DOCUMENT_OPENED' || str_contains($eventRaw, 'OPEN') => 'webhook_document_opened',
            default => 'webhook_'.strtolower($eventRaw ?: 'unknown'),
        };

        // --- антидубликат: подавим точные повторы в течение 5 секунд
        $isDup = false;
        if (in_array($etype, ['webhook_document_signed','webhook_document_opened'], true)) {
            $isDup = ContractEvent::where('contract_id', $contract->id)
                ->where('type', $etype)
                ->where('created_at', '>=', now()->subSeconds(5))
                ->exists();
            if ($isDup) {
                $log->warning('Duplicate webhook suppressed', ['rid'=>$rid, 'etype'=>$etype]);
            }
        }

        if (!$isDup) {
            ContractEvent::create([
                'contract_id'  => $contract->id,
                'type'         => $etype,
                'payload_json' => json_encode(['rid'=>$rid, 'raw'=>$parsed], JSON_UNESCAPED_UNICODE),
            ]);
        }

        // --- реакция по статусу (идемпотентно)
        if ($etype === 'webhook_document_opened') {
            if ($contract->status !== \App\Models\Contract::STATUS_OPENED
                && $contract->status !== \App\Models\Contract::STATUS_SIGNED) {
                $contract->status = \App\Models\Contract::STATUS_OPENED;
                $contract->save();
                $log->info('Contract marked OPENED', ['rid'=>$rid, 'contract_id'=>$contract->id]);
            }
        }

        if ($etype === 'webhook_document_signed') {
            $changed = false;

            if ($contract->status !== \App\Models\Contract::STATUS_SIGNED) {
                $contract->status    = \App\Models\Contract::STATUS_SIGNED;
                $contract->signed_at = now();
                $contract->save();
                $changed = true;
                $log->info('Contract marked SIGNED', ['rid'=>$rid, 'contract_id'=>$contract->id]);
            } else {
                $log->info('Contract already SIGNED, skipping status update', ['rid'=>$rid, 'contract_id'=>$contract->id]);
            }

            // докачаем PDF, если ещё не сохранён
            if (!$contract->signed_pdf_path) {
                try {
                    /** @var \App\Services\Signatures\Providers\PodpislonProvider $provider */
                    $provider = app(\App\Services\Signatures\Providers\PodpislonProvider::class);
                    $file     = $provider->downloadSigned($contract);

                    $path = 'documents/' . date('Y/m') . '/' . $file['filename'];
                    Storage::put($path, $file['content']);

                    $contract->signed_pdf_path = $path;
                    $contract->save();

                    ContractEvent::create([
                        'contract_id'  => $contract->id,
                        'type'         => 'signed_pdf_saved',
                        'payload_json' => json_encode(['path'=>$path], JSON_UNESCAPED_UNICODE),
                    ]);

                    $log->info('Signed PDF saved', ['rid'=>$rid, 'path'=>$path]);
                } catch (\Throwable $e) {
                    $log->error('Auto download signed failed', [
                        'rid'=>$rid, 'error'=>$e->getMessage(), 'trace'=>$e->getTraceAsString(),
                    ]);
                }
            } else {
                if ($changed) {
                    $log->info('Signed PDF already attached (no re-download)', [
                        'rid'=>$rid, 'path'=>$contract->signed_pdf_path,
                    ]);
                }
            }
        }

        return response()->json(['ok'=>true, 'rid'=>$rid]);
    } catch (\Throwable $e) {
            $log->error('Webhook processing failed', [
                'rid'=>$rid, 'error'=>$e->getMessage(), 'trace'=>$e->getTraceAsString(),
            ]);
            return response()->json(['ok'=>false, 'error'=>$e->getMessage(), 'rid'=>$rid]);
        }
    }

    private function safeHeaders(array $headers): array
    {
        $redact = ['authorization', 'x-api-key', 'cookie', 'set-cookie'];
        foreach ($headers as $k => $v) {
            if (in_array(strtolower($k), $redact, true)) {
                $headers[$k] = ['***redacted***'];
            }
        }
        return $headers;
    }
}
