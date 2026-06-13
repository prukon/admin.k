<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractResendRequest;
use App\Http\Requests\Contracts\ContractSendEmailRequest;
use App\Http\Requests\Contracts\ContractSendRequest;
use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractSignRequest;
use App\Enums\AuditEvent;
use App\Services\Audit\ContractAudit;
use App\Models\Partner;
use App\Services\Contracts\ContractBillingService;
use App\Services\Contracts\ContractPodpislonSendService;
use App\Services\Contracts\ContractSmsCooldown;
use App\Services\Signatures\Providers\PodpislonProvider;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ContractSigningController extends Controller
{
    public function __construct(
        private readonly ContractAudit $contractAudit,
    ) {}

    public function send(Contract $contract, ContractSendRequest $request, ContractPodpislonSendService $sendService)
    {
        $result = $sendService->send($contract, $request->validated(), Auth::id());

        return response()->json($result, !empty($result['success']) ? 200 : 422);
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
        $cooldown = ContractSmsCooldown::tryAcquire($contract->id);
        if (!$cooldown['allowed']) {
            $sr->delete();

            return response()->json(
                ContractSmsCooldown::blockedResponse($cooldown['remaining']),
                422
            );
        }

        if ($contract->provider !== 'podpislon' || !$contract->provider_doc_id) {
            $sr->status = 'failed';
            $sr->save();
            ContractSmsCooldown::release($contract->id);

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
                    $this->contractAudit->record(
                        AuditEvent::ContractSignResentSuccess,
                        implode("\n", $changes),
                        userId: (int) $contract->user_id,
                        contract: $contract,
                    );
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

            $this->contractAudit->record(
                AuditEvent::ContractSignResentFailed,
                implode("\n", [
                    'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"',
                    'Договор: Договор #' . $contract->id,
                ]),
                userId: (int) $contract->user_id,
                contract: $contract,
            );

            ContractSmsCooldown::release($contract->id);

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

            $this->contractAudit->record(
                AuditEvent::ContractSignResentFailed,
                implode("\n", [
                    'Статус запроса: "' . $oldSrStatus . '" → "' . $sr->status . '"',
                    'Ошибка: ' . $e->getMessage(),
                    'Договор: Договор #' . $contract->id,
                ]),
                userId: (int) $contract->user_id,
                contract: $contract,
            );

            ContractSmsCooldown::release($contract->id);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка повторной отправки: ' . $e->getMessage(),
                'code'    => 'resend_exception',
                'links'   => $links,
            ], 422);
        }
    }

    public function revoke(Contract $contract, SignatureProvider $provider, ContractBillingService $billing)
    {
        if ($contract->canRevokeWithRefund()) {
            return $this->revokeAwaitingClientFillWithRefund($contract, $billing);
        }

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

            $this->contractAudit->record(
                AuditEvent::ContractRevoked,
                "Договор отозван.\nВозврат 70 ₽: Нет",
                userId: (int) $contract->user_id,
                authorId: Auth::id(),
                contract: $contract,
            );

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

    private function revokeAwaitingClientFillWithRefund(Contract $contract, ContractBillingService $billing)
    {
        try {
            DB::transaction(function () use ($contract, $billing) {
                $partner = Partner::query()->whereKey($contract->school_id)->lockForUpdate()->first();
                abort_unless($partner, 422, 'Партнёр не найден.');

                $billing->refundCreationFee($partner, $contract);

                $contract->status = Contract::STATUS_REVOKED;
                $contract->save();

                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'author_id'    => Auth::id(),
                    'type'         => 'revoked',
                    'payload_json' => json_encode([
                        'refunded' => true,
                        'reason'   => 'awaiting_client_fill',
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            });

            $this->contractAudit->record(
                AuditEvent::ContractRevoked,
                "Договор отозван.\nВозврат 70 ₽: Да",
                userId: (int) $contract->user_id,
                authorId: Auth::id(),
                contract: $contract,
            );

            return response()->json([
                'message' => 'Договор отозван. Средства возвращены на баланс партнёра.',
                'status'  => 'revoked',
            ]);
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
        $this->authorize('contracts.sync');

        try {
            if (!$contract->provider_doc_id) {
                return response()->json([
                    'message' => 'Договор не связан с Подпислоном (нет provider_doc_id). Сначала отправьте договор на подпись.',
                ], 422);
            }

            $data = $provider->getStatus($contract);
            $mapped = PodpislonProvider::mapDocumentStatusToContract(
                $data['status'] ?? null,
                isset($data['status_text']) ? (string) $data['status_text'] : null
            );

            if (!$mapped) {
                return response()->json([
                    'status' => $contract->status,
                    'raw'    => $data,
                    'synced' => false,
                    'message' => 'Провайдер вернул неизвестный статус.',
                ]);
            }

            $contract->refresh();
            $oldStatus = $contract->status;
            $authorId = Auth::id() ?? $contract->user_id;
            $rid = 'MANUAL-SYNC-' . bin2hex(random_bytes(8));
            $payloadBase = ['rid' => $rid, 'source' => 'manual_status_sync', 'raw' => $data];

            $needsSignedPdf = $mapped === Contract::STATUS_SIGNED && !$contract->signed_pdf_path;
            if ($mapped === $oldStatus && !$needsSignedPdf) {
                return response()->json([
                    'status' => $contract->status,
                    'raw'    => $data,
                    'synced' => false,
                ]);
            }

            // Уже signed в БД, но нет файла — имитируем цепочку вебхука и докачиваем PDF
            if ($mapped === Contract::STATUS_SIGNED && $oldStatus === Contract::STATUS_SIGNED && $needsSignedPdf) {
                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'author_id'    => $authorId,
                    'type'         => 'webhook_document_signed',
                    'payload_json' => json_encode($payloadBase, JSON_UNESCAPED_UNICODE),
                ]);
                $this->downloadAndAttachSigned($contract, $provider);
                $contract->refresh();

                return response()->json([
                    'status' => $contract->status,
                    'raw'    => $data,
                    'synced' => true,
                ]);
            }

            // OPENED — как PodpislonWebhookController (не понижаем подписанный)
            if ($mapped === Contract::STATUS_OPENED && $oldStatus !== Contract::STATUS_SIGNED && $oldStatus !== Contract::STATUS_OPENED) {
                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'author_id'    => $authorId,
                    'type'         => 'webhook_document_opened',
                    'payload_json' => json_encode($payloadBase, JSON_UNESCAPED_UNICODE),
                ]);

                DB::transaction(function () use ($contract, $payloadBase, $authorId) {
                    $c = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
                    if ($c->status === Contract::STATUS_SIGNED || $c->status === Contract::STATUS_OPENED) {
                        return;
                    }
                    $prev = $c->status;
                    $c->status = Contract::STATUS_OPENED;
                    $c->save();

                    ContractEvent::create([
                        'contract_id'  => $c->id,
                        'author_id'    => $authorId,
                        'type'         => 'status_sync',
                        'payload_json' => json_encode($payloadBase, JSON_UNESCAPED_UNICODE),
                    ]);
                    $this->manualSyncMyLogContractOpened($c, $prev);
                });

                $contract->refresh();

                return response()->json([
                    'status' => $contract->status,
                    'raw'    => $data,
                    'synced' => true,
                ]);
            }

            // SIGNED — как вебхук: webhook_document_signed → статус + status_sync + MyLog → PDF
            if ($mapped === Contract::STATUS_SIGNED && $oldStatus !== Contract::STATUS_SIGNED) {
                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'author_id'    => $authorId,
                    'type'         => 'webhook_document_signed',
                    'payload_json' => json_encode($payloadBase, JSON_UNESCAPED_UNICODE),
                ]);

                DB::transaction(function () use ($contract, $payloadBase, $authorId) {
                    $c = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
                    if ($c->status === Contract::STATUS_SIGNED) {
                        return;
                    }
                    $prev = $c->status;
                    $c->status = Contract::STATUS_SIGNED;
                    $c->signed_at = now();
                    $c->save();

                    ContractEvent::create([
                        'contract_id'  => $c->id,
                        'author_id'    => $authorId,
                        'type'         => 'status_sync',
                        'payload_json' => json_encode($payloadBase, JSON_UNESCAPED_UNICODE),
                    ]);
                    $this->manualSyncMyLogContractSigned($c, $prev);
                });

                $contract->refresh();
                if (!$contract->signed_pdf_path) {
                    $this->downloadAndAttachSigned($contract, $provider);
                }
                $contract->refresh();

                return response()->json([
                    'status' => $contract->status,
                    'raw'    => $data,
                    'synced' => true,
                ]);
            }

            // Прочие смены статуса
            if ($mapped !== $oldStatus) {
                DB::transaction(function () use ($contract, $mapped, $data, $authorId) {
                    $c = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
                    if ($c->status === $mapped) {
                        return;
                    }
                    $c->status = $mapped;
                    $c->save();

                    ContractEvent::create([
                        'contract_id'  => $c->id,
                        'author_id'    => $authorId,
                        'type'         => 'status_sync',
                        'payload_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    ]);
                });
                $contract->refresh();
            }

            return response()->json([
                'status' => $contract->status,
                'raw'    => $data,
                'synced' => $mapped !== $oldStatus,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    protected function manualSyncMyLogContractOpened(Contract $contract, string $oldStatus): void
    {
        try {
            $oldLabel = Contract::$STATUS_RU[$oldStatus] ?? ucfirst($oldStatus);
            $newLabel = Contract::$STATUS_RU[Contract::STATUS_OPENED] ?? 'Opened';

            $this->contractAudit->record(
                AuditEvent::ContractSmsOpened,
                'Статус договора: "' . $oldLabel . '" → "' . $newLabel . '"',
                userId: (int) $contract->user_id,
                authorId: (int) $contract->user_id,
                partnerId: (int) $contract->school_id,
                contract: $contract,
            );
        } catch (\Throwable $e) {
            Log::error('manualSyncMyLogContractOpened failed', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    protected function manualSyncMyLogContractSigned(Contract $contract, string $oldStatus): void
    {
        try {
            $oldLabel = Contract::$STATUS_RU[$oldStatus] ?? ucfirst($oldStatus);
            $newLabel = Contract::$STATUS_RU[Contract::STATUS_SIGNED] ?? 'Signed';

            $this->contractAudit->record(
                AuditEvent::ContractSigned,
                'Статус договора: "' . $oldLabel . '" → "' . $newLabel . '"',
                userId: (int) $contract->user_id,
                authorId: (int) $contract->user_id,
                partnerId: (int) $contract->school_id,
                contract: $contract,
            );
        } catch (\Throwable $e) {
            Log::error('manualSyncMyLogContractSigned failed', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
            ]);
        }
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
            'author_id'    => Auth::id() ?? $contract->user_id,
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

            $this->contractAudit->record(
                AuditEvent::ContractEmailSent,
                'Email: ' . $to . "\n"
                . 'Вложение: ' . ($sendSigned ? 'подписанный PDF' : 'исходный PDF'),
                userId: (int) $contract->user_id,
                authorId: Auth::id(),
                contract: $contract,
            );

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

