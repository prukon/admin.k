<?php

namespace App\Services\Contracts;

use App\Models\User;
use App\Services\Users\StudentParentSyncService;

/**
 * Сохраняет данные из заполненного договора в профиль родителя (parents).
 */
class ContractParentProfileSyncService
{
    public function __construct(
        private readonly StudentParentSyncService $studentParentSync,
    ) {
    }

    /**
     * @param array<string, mixed> $filledData
     */
    public function syncFromFilledData(User $student, int $partnerId, array $filledData): void
    {
        $payload = $this->mapFilledDataToParentPayload($filledData);
        if ($payload === []) {
            return;
        }

        $this->studentParentSync->updateFromAccount($student, $partnerId, $payload);
    }

    /**
     * @param array<string, mixed> $filledData
     * @return array<string, mixed>
     */
    public function mapFilledDataToParentPayload(array $filledData): array
    {
        $payload = [];

        foreach (StudentParentSyncService::parentProfilePayloadKeys() as $requestKey => $filledKey) {
            if (!array_key_exists($filledKey, $filledData)) {
                continue;
            }

            $value = trim((string) $filledData[$filledKey]);
            if ($value === '') {
                continue;
            }

            $payload[$requestKey] = $value;
        }

        return $payload;
    }
}
