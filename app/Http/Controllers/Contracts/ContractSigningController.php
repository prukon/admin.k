<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractResendRequest;
use App\Http\Requests\Contracts\ContractSendEmailRequest;
use App\Http\Requests\Contracts\ContractSendRequest;
use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractSignRequest;
use App\Models\MyLog;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ContractSigningController extends Controller
{
    public function send(Contract $contract, ContractSendRequest $request, SignatureProvider $provider)
    {
        $validated = $request->validated();

        $signerFio = trim(
            preg_replace('/\s+/', ' ',
                ($validated['signer_lastname'] ?? '') . ' ' .
                ($validated['signer_firstname'] ?? '') . ' ' .
                ($validated['signer_middlename'] ?? '')
            )
        );

        $phone = (string)$validated['signer_phone']; // только цифры 7XXXXXXXXXX

        $sr = new ContractSignRequest([
            'signer_name'       => $signerFio,
            'signer_lastname'   => $validated['signer_lastname'] ?? null,
            'signer_firstname'  => $validated['signer_firstname'] ?? null,
            'signer_middlename' => $validated['signer_middlename'] ?? null,
            'signer_phone'      => $phone,
            'ttl_hours'         => $validated['ttl_hours'] ?? 72,
            'status'            => 'created',
        ]);
        $contract->signRequests()->save($sr);

        MyLog::create([
            'type'         => 500,
            'action'       => 510,
            'user_id'      => $contract->user_id,
            'target_type'  => Contract::class,
            'target_id'    => $contract->id,
            'target_label' => "Договор № {$contract->id}",
            'description'  =>
                "Запрос на подпись создан!!!\n" .
                "ФИО: {$signerFio}\n" .
                "Телефон: {$phone}\n" .
                "TTL (часы): " . ($validated['ttl_hours'] ?? 72) . "\n" .
                "Договор: Договор #{$contract->id}",
            'created_at'   => now(),
        ]);

        // ===== РЕСЕНД через /send (когда документ уже существует у провайдера)
        if ($contract->provider === 'podpislon' && $contract->provider_doc_id) {
            return $this->resendInternal($contract, $sr, null);
        }

        // ===== ПЕРВАЯ ОТПРАВКА
        try {
            $res = $provider->send($contract, $sr);

            $doc = $this->pollForSent($contract);

            if ($doc) {
                $changes = [];

                $oldSrStatus = $sr->status;
                $sr->status = 'sent';
                $sr->save();
                if ($oldSrStatus !== $sr->status) {
                    $changes[] = 'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"';
                }

                $oldContractStatus = $contract->status;
                $contract->status = Contract::STATUS_SENT;
                $contract->save();
                if ($oldContractStatus !== $contract->status) {
                    $changes[] = 'Статус договора: "' . $oldContractStatus . '" → "' . $contract->status . '"';
                }

                if ($changes) {
                    MyLog::create([
                        'type'         => 500,
                        'action'       => 513,
                        'user_id'      => $contract->user_id,
                        'target_type'  => Contract::class,
                        'target_id'    => $contract->id,
                        'target_label' => "Договор № {$contract->id}",
                        'description'  => implode("\n", $changes),
                        'created_at'   => now(),
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'SMS отправлена',
                    'status'  => 'sent',
                ], 200);
            }

            // не подтвердили "отправлено"
            $oldSrStatus = $sr->status;
            $sr->status = 'failed';
            $sr->save();

            $oldContractStatus = $contract->status;
            $contract->status = Contract::STATUS_FAILED;
            $contract->save();

            $links = $this->signingLinks($contract);
            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => Auth::id(),
                'type'         => 'failed',
                'payload_json' => json_encode(['res' => $res, 'links' => $links], JSON_UNESCAPED_UNICODE),
            ]);

            MyLog::create([
                'type'         => 500,
                'action'       => 514,
                'user_id'      => $contract->user_id,
                'target_type'  => Contract::class,
                'target_id'    => $contract->id,
                'target_label' => "Договор № {$contract->id}",
                'description'  =>
                    "Статус запроса: \"{$oldSrStatus}\" → \"{$sr->status}\"\n" .
                    "Статус договора: \"{$oldContractStatus}\" → \"{$contract->status}\"",
                'created_at'   => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Провайдер не подтвердил отправку SMS.',
                'code'    => 'send_not_sent',
                'links'   => $links,
            ], 422);
        } catch (\Throwable $e) {
            $oldSrStatus = $sr->status;
            $sr->status = 'failed';
            $sr->save();

            $oldContractStatus = $contract->status;
            $contract->status = Contract::STATUS_FAILED;
            $contract->save();

            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => Auth::id(),
                'type'         => 'failed',
                'payload_json' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);

            MyLog::create([
                'type'         => 500,
                'action'       => 514,
                'user_id'      => $contract->user_id,
                'target_type'  => Contract::class,
                'target_id'    => $contract->id,
                'target_label' => "Договор № {$contract->id}",
                'description'  =>
                    "Статус запроса: \"{$oldSrStatus}\" → \"{$sr->status}\"\n" .
                    "Статус договора: \"{$oldContractStatus}\" → \"{$contract->status}\"\n" .
                    "Ошибка: {$e->getMessage()}",
                'created_at'   => now(),
            ]);

            Log::error('[contracts.send] fail', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code'    => 'send_failed',
            ], 422);
        }
    }

    /**
     * POST /client-contracts/{contract}/resend
     * Легаси/ручной ресенд без повторного ввода ФИО/телефона.
     */
    public function resend(Contract $contract, ContractResendRequest $request)
    {
        $validated = $request->validated();
        $sid = $validated['sid'] ?? null;

        /** @var ContractSignRequest|null $last */
        $last = $contract->signRequests()->orderByDesc('id')->first();
        if (!$last) {
            return response()->json([
                'success' => false,
                'message' => 'Нет данных подписанта для повторной отправки. Откройте модал и отправьте договор заново.',
                'code'    => 'no_sign_request',
            ], 422);
        }

        // создаём новую запись, чтобы история была консистентной (как в send())
        $sr = new ContractSignRequest([
            'signer_name'       => $last->signer_name,
            'signer_lastname'   => $last->signer_lastname,
            'signer_firstname'  => $last->signer_firstname,
            'signer_middlename' => $last->signer_middlename,
            'signer_phone'      => $last->signer_phone,
            'ttl_hours'         => $last->ttl_hours ?? 72,
            'status'            => 'created',
        ]);
        $contract->signRequests()->save($sr);

        return $this->resendInternal($contract, $sr, $sid);
    }

    private function resendInternal(Contract $contract, ContractSignRequest $sr, ?string $sid)
    {
        if ($contract->provider !== 'podpislon' || !$contract->provider_doc_id) {
            $sr->status = 'failed';
            $sr->save();

            return response()->json([
                'success' => false,
                'message' => 'Повторная отправка недоступна: у договора нет данных провайдера.',
                'code'    => 'resend_not_supported',
            ], 422);
        }

        try {
            /** @var \App\Services\Signatures\Providers\PodpislonProvider $pod */
            $pod = app(\App\Services\Signatures\Providers\PodpislonProvider::class);
            $res = $pod->resendForContract($contract, $sid);

            $doc = $this->pollForSent($contract);

            if ($doc) {
                $changes = [];

                $oldSrStatus = $sr->status;
                $sr->status = 'sent';
                $sr->save();
                if ($oldSrStatus !== $sr->status) {
                    $changes[] = 'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"';
                }

                if (!in_array($contract->status, [
                    Contract::STATUS_SIGNED,
                    Contract::STATUS_OPENED,
                ], true)) {
                    $oldContractStatus = $contract->status;
                    $contract->status = Contract::STATUS_SENT;
                    $contract->save();
                    if ($oldContractStatus !== $contract->status) {
                        $changes[] = 'Статус договора: "' . $oldContractStatus . '" → "' . $contract->status . '"';
                    }
                }

                if ($changes) {
                    MyLog::create([
                        'type'         => 500,
                        'action'       => 511,
                        'user_id'      => $contract->user_id,
                        'target_type'  => Contract::class,
                        'target_id'    => $contract->id,
                        'target_label' => "Договор № {$contract->id}",
                        'description'  => implode("\n", $changes),
                        'created_at'   => now(),
                    ]);
                }

                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'author_id'    => Auth::id(),
                    'type'         => 'resend',
                    'payload_json' => json_encode(['res' => $res, 'doc' => $doc], JSON_UNESCAPED_UNICODE),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'SMS отправлена',
                    'status'  => 'sent',
                ], 200);
            }

            // не подтвердили отправку
            $oldSrStatus = $sr->status;
            $sr->status = 'failed';
            $sr->save();

            $links = $this->signingLinks($contract);
            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => Auth::id(),
                'type'         => 'resend_failed',
                'payload_json' => json_encode(['res' => $res, 'links' => $links], JSON_UNESCAPED_UNICODE),
            ]);

            MyLog::create([
                'type'         => 500,
                'action'       => 512,
                'user_id'      => $contract->user_id,
                'target_type'  => Contract::class,
                'target_id'    => $contract->id,
                'target_label' => "Договор № {$contract->id}",
                'description'  => implode("\n", [
                    'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"',
                    'Договор: Договор #' . $contract->id,
                ]),
                'created_at'   => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Провайдер не подтвердил отправку SMS.',
                'code'    => 'resend_not_sent',
                'links'   => $links,
            ], 422);
        } catch (\Throwable $e) {
            $oldSrStatus = $sr->status;
            $sr->status = 'failed';
            $sr->save();

            $links = $this->signingLinks($contract);
            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => Auth::id(),
                'type'         => 'resend_failed',
                'payload_json' => json_encode(['error' => $e->getMessage(), 'links' => $links], JSON_UNESCAPED_UNICODE),
            ]);

            MyLog::create([
                'type'         => 500,
                'action'       => 512,
                'user_id'      => $contract->user_id,
                'target_type'  => Contract::class,
                'target_id'    => $contract->id,
                'target_label' => "Договор № {$contract->id}",
                'description'  => implode("\n", [
                    'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"',
                    'Ошибка: ' . $e->getMessage(),
                    'Договор: Договор #' . $contract->id,
                ]),
                'created_at'   => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка повторной отправки: ' . $e->getMessage(),
                'code'    => 'resend_exception',
                'links'   => $links,
            ], 422);
        }
    }

    public function revoke(Contract $contract, SignatureProvider $provider)
    {
        try {
            $provider->revoke($contract);

            $contract->status = Contract::STATUS_REVOKED;
            $contract->save();

            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => Auth::id(),
                'type'         => 'revoked',
                'payload_json' => null,
            ]);

            return response()->json(['message' => 'Подписание отозвано', 'status' => 'revoked']);
        } catch (\Throwable $e) {
            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => Auth::id(),
                'type'         => 'failed',
                'payload_json' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function status(Contract $contract, SignatureProvider $provider)
    {
        try {
            $data = $provider->getStatus($contract);
            $status = $this->mapProviderStatus($data['status'] ?? null);

            if ($status && $status !== $contract->status) {
                $contract->status = $status;
                $contract->save();

                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'author_id'    => Auth::id(),
                    'type'         => 'status_sync',
                    'payload_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                ]);

                if ($status === Contract::STATUS_SIGNED && !$contract->signed_pdf_path) {
                    $this->downloadAndAttachSigned($contract, $provider);
                }
            }

            return response()->json(['status' => $contract->status, 'raw' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    protected function mapProviderStatus(?string $s): ?string
    {
        return match ($s) {
            'sent'    => Contract::STATUS_SENT,
            'opened'  => Contract::STATUS_OPENED,
            'signed'  => Contract::STATUS_SIGNED,
            'expired' => Contract::STATUS_EXPIRED,
            'revoked' => Contract::STATUS_REVOKED,
            'failed'  => Contract::STATUS_FAILED,
            default   => null,
        };
    }

    protected function downloadAndAttachSigned(Contract $contract, SignatureProvider $provider): void
    {
        $file = $provider->downloadSigned($contract);
        $path = 'documents/' . date('Y/m') . '/' . $file['filename'];
        Storage::put($path, $file['content']);

        $contract->signed_pdf_path = $path;
        $contract->signed_at = now();
        $contract->save();

        ContractEvent::create([
            'contract_id'  => $contract->id,
            'author_id'    => Auth::id(),
            'type'         => 'signed_pdf_saved',
            'payload_json' => json_encode(['path' => $path], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function sendEmail(Contract $contract, ContractSendEmailRequest $request)
    {
        $validated = $request->validated();
        $to = (string)$validated['email'];
        $sendSigned = (bool)($validated['signed'] ?? false);

        try {
            if ($sendSigned) {
                if (!$contract->signed_pdf_path || !is_file(Storage::path($contract->signed_pdf_path))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Подписанный файл не найден.',
                    ], 422);
                }
                $attachPath = Storage::path($contract->signed_pdf_path);
                $attachName = 'contract-' . $contract->id . '-signed.pdf';
                $subject = 'Подписанный договор #' . $contract->id;
                $body = 'Подписанный договор во вложении.';
            } else {
                if (!$contract->source_pdf_path || !is_file(Storage::path($contract->source_pdf_path))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Исходный файл не найден.',
                    ], 422);
                }
                $attachPath = Storage::path($contract->source_pdf_path);
                $attachName = 'contract-' . $contract->id . '.pdf';
                $subject = 'Договор #' . $contract->id;
                $body = 'Договор во вложении.';
            }

            Mail::raw($body, function ($message) use ($to, $subject, $attachPath, $attachName) {
                $message->to($to)->subject($subject);
                $message->attach($attachPath, [
                    'as'   => $attachName,
                    'mime' => 'application/pdf',
                ]);
            });

            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => Auth::id(),
                'type'         => $sendSigned ? 'email_signed_sent' : 'email_sent',
                'payload_json' => json_encode(['to' => $to], JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json([
                'success' => true,
                'message' => $sendSigned
                    ? 'Подписанный договор отправлен на e-mail'
                    : 'Отправлено на e-mail',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function isSentByProvider(?array $doc): bool
    {
        if (!$doc) return false;

        $code = $doc['status'] ?? null;
        $code = is_numeric($code) ? (int)$code : null;
        $text = mb_strtolower((string)($doc['status_text'] ?? ''));

        return $code === 15
            || str_contains($text, 'отправлен')
            || str_contains($text, 'sent');
    }

    private function fetchProviderDoc(Contract $contract): ?array
    {
        /** @var \App\Services\Signatures\Providers\PodpislonProvider $pod */
        $pod = app(\App\Services\Signatures\Providers\PodpislonProvider::class);

        if (!$contract->provider_doc_id) return null;

        $list = $pod->list([(int)$contract->provider_doc_id], [], 1, true);
        return $list['items'][0] ?? null;
    }

    private function pollForSent(Contract $contract): ?array
    {
        for ($i = 0; $i < 3; $i++) {
            $doc = $this->fetchProviderDoc($contract);
            if ($this->isSentByProvider($doc)) return $doc;
            usleep(300_000); // 300 мс
        }
        return null;
    }

    private function signingLinks(Contract $contract): array
    {
        try {
            /** @var \App\Services\Signatures\Providers\PodpislonProvider $pod */
            $pod = app(\App\Services\Signatures\Providers\PodpislonProvider::class);
            return $pod->getSigningLinks($contract);
        } catch (\Throwable $e) {
            return [];
        }
    }
}

