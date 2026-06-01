<?php

namespace App\Services\Audit;

use App\Enums\AuditEvent;
use App\Models\Contract;
use App\Models\MyLog;
use App\Models\Partner;

/**
 * Унифицированная запись аудита по договорам.
 */
final class ContractAudit
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function record(
        AuditEvent $event,
        string $description,
        ?int $userId = null,
        ?int $authorId = null,
        ?int $partnerId = null,
        ?Contract $contract = null,
        ?Partner $partner = null,
    ): MyLog {
        $context = AuditContext::make($description)->withCreatedAt(now());

        if ($contract !== null) {
            $context = $context->withTarget($contract, 'Договор № ' . $contract->id);
        } elseif ($partner !== null) {
            $context = $context->withTargetReference(Contract::class, (int) $partner->id, (string) $partner->title);
        }

        if ($userId !== null) {
            $context = $context->withUserId($userId);
        }
        if ($authorId !== null) {
            $context = $context->withAuthorId($authorId);
        }
        if ($partnerId !== null) {
            $context = $context->withPartnerId($partnerId);
        }

        return $this->auditLogger->record($event, $context);
    }
}
