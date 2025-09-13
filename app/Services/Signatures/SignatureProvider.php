<?php

namespace App\Services\Signatures;

use App\Models\Contract;
use App\Models\ContractSignRequest;

interface SignatureProvider
{
    /**
     * Создать задачу на подпись и отправить ссылку подписанту (SMS/OTP).
     * Должен записать provider_doc_id в $contract при первом вызове.
     */
    public function send(Contract $contract, ContractSignRequest $request): array;

    /** Отозвать подпись (если поддерживается). */
    public function revoke(Contract $contract): void;

    /** Получить текущий статус документа у провайдера. */
    public function getStatus(Contract $contract): array;

    /**
     * Скачать подписанный PDF.
     * Возвращает ['filename' => string, 'content' => binary-string]
     */
    public function downloadSigned(Contract $contract): array;
}
