<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractSignRequest;
use App\Enums\AuditEvent;
use App\Services\Audit\ContractAudit;
use App\Services\Signatures\Providers\PodpislonProvider;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ContractPodpislonSendService
{
    public function __construct(
        private readonly SignatureProvider $provider,
        private readonly ContractAudit $contractAudit,
    ) {
    }

    /**
     * @param array{signer_lastname: string, signer_firstname: string, signer_middlename?: string|null, signer_phone: string, ttl_hours?: int} $validated
     * @return array{success: bool, message: string, status?: string, code?: string, links?: array}
     */
    public function send(Contract $contract, array $validated, ?int $authorId = null): array
    {
        $authorId = $authorId ?? Auth::id();

        $cooldown = ContractSmsCooldown::tryAcquire($contract->id);
        if (!$cooldown['allowed']) {
            return ContractSmsCooldown::blockedResponse($cooldown['remaining']);
        }

        $signerFio = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
            $validated['signer_lastname'] ?? '',
            $validated['signer_firstname'] ?? '',
            $validated['signer_middlename'] ?? '',
        ]))));

        $phone = (string) $validated['signer_phone'];

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

        $this->contractAudit->record(
            AuditEvent::ContractSignRequestCreated,
            "Запрос на подпись создан\n" .
            "ФИО: {$signerFio}\n" .
            "Телефон: {$phone}\n" .
            "TTL (часы): " . ($validated['ttl_hours'] ?? 72),
            userId: (int) $contract->user_id,
            contract: $contract,
        );

        if ($contract->provider === 'podpislon' && $contract->provider_doc_id) {
            return $this->resendExisting($contract, $sr, $authorId);
        }

        try {
            $res = $this->provider->send($contract, $sr);
            $doc = $this->pollForSent($contract);

            if ($doc) {
                $sr->status = 'sent';
                $sr->save();

                $oldContractStatus = $contract->status;
                $contract->status = Contract::STATUS_SENT;
                $contract->save();

                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'author_id'    => $authorId,
                    'type'         => 'sent',
                    'payload_json' => json_encode(['channel' => 'client_cabinet'], JSON_UNESCAPED_UNICODE),
                ]);

                $this->contractAudit->record(
                    AuditEvent::ContractSignSentSuccess,
                    implode("\n", [
                        'Статус запроса: "created" → "sent"',
                        'Статус договора: "' . $oldContractStatus . '" → "' . $contract->status . '"',
                    ]),
                    userId: (int) $contract->user_id,
                    authorId: $authorId,
                    contract: $contract,
                );

                return [
                    'success' => true,
                    'message' => 'SMS отправлена',
                    'status'  => 'sent',
                ];
            }

            $sr->status = 'failed';
            $sr->save();
            $oldContractStatus = $contract->status;
            $contract->status = Contract::STATUS_FAILED;
            $contract->save();

            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => $authorId,
                'type'         => 'failed',
                'payload_json' => json_encode(['res' => $res, 'links' => $this->signingLinks($contract)], JSON_UNESCAPED_UNICODE),
            ]);

            $this->contractAudit->record(
                AuditEvent::ContractSignSentFailed,
                implode("\n", [
                    'Статус запроса: "created" → "failed"',
                    'Статус договора: "' . $oldContractStatus . '" → "' . $contract->status . '"',
                ]),
                userId: (int) $contract->user_id,
                authorId: $authorId,
                contract: $contract,
            );

            ContractSmsCooldown::release($contract->id);

            return [
                'success' => false,
                'message' => 'Провайдер не подтвердил отправку SMS.',
                'code'    => 'send_not_sent',
                'links'   => $this->signingLinks($contract),
            ];
        } catch (\Throwable $e) {
            $sr->status = 'failed';
            $sr->save();
            $oldContractStatus = $contract->status;
            $contract->status = Contract::STATUS_FAILED;
            $contract->save();

            ContractEvent::create([
                'contract_id'  => $contract->id,
                'author_id'    => $authorId,
                'type'         => 'failed',
                'payload_json' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);

            $this->contractAudit->record(
                AuditEvent::ContractSignSentFailed,
                implode("\n", [
                    'Статус запроса: "created" → "failed"',
                    'Статус договора: "' . $oldContractStatus . '" → "' . $contract->status . '"',
                    'Ошибка: ' . $e->getMessage(),
                ]),
                userId: (int) $contract->user_id,
                authorId: $authorId,
                contract: $contract,
            );

            Log::error('[contracts.send] fail', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
            ]);

            ContractSmsCooldown::release($contract->id);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code'    => 'send_failed',
            ];
        }
    }

    public function assertCanClientSign(Contract $contract): void
    {
        if (!$contract->isTemplateMode()) {
            throw new \InvalidArgumentException('Подпись из кабинета доступна только для договоров по шаблону.');
        }

        if ($contract->status !== Contract::STATUS_DRAFT || !$contract->source_pdf_path) {
            throw new \InvalidArgumentException('Сначала сформируйте договор.');
        }

        if ($contract->status === Contract::STATUS_SIGNED) {
            throw new \InvalidArgumentException('Договор уже подписан.');
        }
    }

    /**
     * @return array{success: bool, message: string, status?: string, code?: string}
     */
    private function resendExisting(Contract $contract, ContractSignRequest $sr, ?int $authorId): array
    {
        try {
            /** @var PodpislonProvider $pod */
            $pod = app(PodpislonProvider::class);
            $res = $pod->resendForContract($contract, null);
            $doc = $this->pollForSent($contract);

            if ($doc) {
                $sr->status = 'sent';
                $sr->save();
                if (!in_array($contract->status, [Contract::STATUS_SIGNED, Contract::STATUS_OPENED], true)) {
                    $contract->status = Contract::STATUS_SENT;
                    $contract->save();
                }

                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'author_id'    => $authorId,
                    'type'         => 'resend',
                    'payload_json' => json_encode(['res' => $res], JSON_UNESCAPED_UNICODE),
                ]);

                return ['success' => true, 'message' => 'SMS отправлена', 'status' => 'sent'];
            }

            $sr->status = 'failed';
            $sr->save();

            ContractSmsCooldown::release($contract->id);

            return [
                'success' => false,
                'message' => 'Провайдер не подтвердил отправку SMS.',
                'code'    => 'resend_not_sent',
            ];
        } catch (\Throwable $e) {
            $sr->status = 'failed';
            $sr->save();

            ContractSmsCooldown::release($contract->id);

            return ['success' => false, 'message' => $e->getMessage(), 'code' => 'resend_failed'];
        }
    }

    private function pollForSent(Contract $contract): ?array
    {
        for ($i = 0; $i < 3; $i++) {
            $doc = $this->fetchProviderDoc($contract);
            if ($this->isSentByProvider($doc)) {
                return $doc;
            }
            usleep(300_000);
        }

        return null;
    }

    private function fetchProviderDoc(Contract $contract): ?array
    {
        /** @var PodpislonProvider $pod */
        $pod = app(PodpislonProvider::class);

        if (!$contract->provider_doc_id) {
            return null;
        }

        $list = $pod->list([(int) $contract->provider_doc_id], [], 1, true);

        return $list['items'][0] ?? null;
    }

    private function isSentByProvider(?array $doc): bool
    {
        if (!$doc) {
            return false;
        }

        $code = $doc['status'] ?? null;
        $code = is_numeric($code) ? (int) $code : null;
        $text = mb_strtolower((string) ($doc['status_text'] ?? ''));

        return $code === 15
            || str_contains($text, 'отправлен')
            || str_contains($text, 'sent');
    }

    private function signingLinks(Contract $contract): array
    {
        try {
            /** @var PodpislonProvider $pod */
            $pod = app(PodpislonProvider::class);

            return $pod->getSigningLinks($contract);
        } catch (\Throwable) {
            return [];
        }
    }
}
