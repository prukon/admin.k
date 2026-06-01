<?php

namespace App\Services\Audit;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Единая точка записи в my_logs с каноническими event и level.
 */
final class AuditLogger
{
    public function record(AuditEvent $event, AuditContext $context): MyLog
    {
        $payload = [
            'event' => $event->value,
            'level' => $event->level()->value,
            'description' => $context->description,
        ];

        if ($context->createdAt !== null) {
            $payload['created_at'] = $context->createdAt;
        }

        $authorId = $context->authorId;
        if ($authorId === null && Auth::check()) {
            $authorId = Auth::id();
        }
        if ($authorId !== null) {
            $payload['author_id'] = $authorId;
        }

        $partnerId = $context->partnerId ?? $this->resolvePartnerId();
        if ($partnerId !== null) {
            $payload['partner_id'] = $partnerId;
        }

        if ($context->user !== null) {
            $payload['user_id'] = $context->user->getKey();
        } elseif ($context->userId !== null) {
            $payload['user_id'] = $context->userId;
        }

        $this->applyTargetFields($payload, $context);

        return MyLog::query()->create($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyTargetFields(array &$payload, AuditContext $context): void
    {
        if ($context->targetType !== null) {
            $payload['target_type'] = $context->targetType;
        }
        if ($context->targetId !== null) {
            $payload['target_id'] = $context->targetId;
        }
        if ($context->targetLabel !== null) {
            $payload['target_label'] = $context->targetLabel;
        }

        if ($context->target === null) {
            return;
        }

        $target = $context->target;

        $payload['target_type'] = $target->getMorphClass();
        $payload['target_id'] = $target->getKey();

        if ($context->targetLabel !== null && $context->targetLabel !== '') {
            $payload['target_label'] = $context->targetLabel;

            return;
        }

        $payload['target_label'] = $this->resolveTargetLabel($target);
    }

    private function resolveTargetLabel(Model $target): string
    {
        foreach (['full_name', 'title', 'name', 'label'] as $attribute) {
            $value = $target->getAttribute($attribute);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return class_basename($target).'#'.$target->getKey();
    }

    private function resolvePartnerId(): ?int
    {
        if (! app()->bound('current_partner')) {
            return null;
        }

        $partner = app('current_partner');

        if ($partner === null || ! isset($partner->id)) {
            return null;
        }

        return (int) $partner->id;
    }
}
