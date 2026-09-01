<?php

declare(strict_types=1);

namespace App\Services\InAppNotifications;

use App\Models\Contract;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * In-app уведомление админам школы: договор ученика перешёл в signed.
 */
final class ContractSignedNotifier
{
    public function __construct(
        private readonly InAppNotificationDispatcher $dispatcher,
        private readonly InAppNotificationAudience $audience,
    ) {}

    public function notify(Contract $contract): void
    {
        try {
            $this->dispatch($contract);
        } catch (Throwable $e) {
            Log::error('[ContractSignedNotifier] failed', [
                'contract_id' => (int) $contract->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatch(Contract $contract): void
    {
        $config = (array) config('in_app_notifications.events.contract_signed', []);
        $roleNames = array_values(array_filter(array_map('strval', (array) ($config['role_names'] ?? []))));
        $roleIds = $this->audience->systemRoleIdsByNames($roleNames);
        $partnerId = (int) $contract->school_id;

        if ($roleIds === [] || $partnerId <= 0) {
            return;
        }

        $contract->loadMissing('user.parentProfile');
        $student = $contract->user;

        $this->dispatcher->dispatchEvent([
            'title' => (string) ($config['title'] ?? 'Договор подписан'),
            'body' => $this->buildBody($student, $contract),
            'category' => (string) ($config['category'] ?? InAppNotification::CATEGORY_NORMAL),
            'partner_ids' => [$partnerId],
            'role_ids' => $roleIds,
            'ttl_preset' => (string) ($config['ttl_preset'] ?? InAppNotification::TTL_7D),
        ], $student);
    }

    private function buildBody(?User $student, Contract $contract): string
    {
        $childName = trim((string) ($student?->full_name ?: ''));
        if ($childName === '') {
            $userId = (int) ($student?->id ?: $contract->user_id);
            $childName = 'Ученик #'.$userId;
        }

        $parentName = trim((string) ($student?->parent_full_name ?: ''));
        if ($parentName !== '') {
            return sprintf(
                '<p>Родитель: %s<br>Ребёнок: %s.<br>Договор №%d подписан.</p>',
                e($parentName),
                e($childName),
                (int) $contract->id
            );
        }

        return sprintf(
            '<p>%s. Договор №%d подписан.</p>',
            e($childName),
            (int) $contract->id
        );
    }
}
