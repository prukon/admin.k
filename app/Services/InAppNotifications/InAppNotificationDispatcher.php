<?php

declare(strict_types=1);

namespace App\Services\InAppNotifications;

use App\Enums\AuditEvent;
use App\Jobs\FanOutInAppNotificationJob;
use App\Models\InAppNotification;
use App\Models\Partner;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class InAppNotificationDispatcher
{
    public function __construct(
        private readonly InAppNotificationAudience $audience,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     title: string,
     *     body: string,
     *     category: string,
     *     all_partners: bool,
     *     partner_ids: list<int>,
     *     role_ids: list<int>,
     *     ttl_preset: string,
     *     custom_expires_at: ?string
     * }  $payload
     */
    public function dispatchManual(array $payload, User $author): InAppNotification
    {
        $allPartners = (bool) $payload['all_partners'];
        $partnerIds = $allPartners
            ? Partner::query()->pluck('id')->map(fn ($id) => (int) $id)->all()
            : array_values(array_unique(array_map('intval', $payload['partner_ids'])));

        if ($partnerIds === []) {
            throw new InvalidArgumentException('Не выбраны школы для рассылки.');
        }

        $roleIds = $this->audience->allowedRoleIds(
            $payload['role_ids'],
            $allPartners ? [] : $partnerIds,
            $allPartners
        );

        if ($roleIds === []) {
            throw new InvalidArgumentException('Не выбраны допустимые роли для рассылки.');
        }

        $notification = InAppNotification::query()->create([
            'category' => $payload['category'],
            'source' => InAppNotification::SOURCE_MANUAL,
            'title' => $payload['title'],
            'body' => InAppNotificationBodyHtml::sanitize($payload['body']),
            'action_url' => null,
            'is_global' => $allPartners,
            'audience_role_ids' => $roleIds,
            'ttl_preset' => $payload['ttl_preset'],
            'expires_at' => $this->resolveExpiresAt(
                $payload['ttl_preset'],
                $payload['custom_expires_at'] ?? null
            ),
            'status' => InAppNotification::STATUS_PENDING,
            'recipients_count' => 0,
            'created_by' => $author->id,
        ]);

        if (! $allPartners) {
            $notification->partners()->sync($partnerIds);
        }

        $this->auditLogger->record(
            AuditEvent::InAppNotificationCreated,
            AuditContext::make('Создание in-app уведомления')
                ->withTarget($notification, $notification->title)
                ->withAuthorId((int) $author->id)
                ->withPartnerId($allPartners ? null : ($partnerIds[0] ?? null))
        );

        FanOutInAppNotificationJob::dispatch($notification->id);

        return $notification;
    }

    /**
     * Автособытие: одна школа, без аудита ручной рассылки.
     *
     * @param  array{
     *     title: string,
     *     body: string,
     *     category?: string,
     *     partner_ids: list<int>,
     *     role_ids: list<int>,
     *     ttl_preset?: string,
     *     custom_expires_at?: ?string
     * }  $payload
     */
    public function dispatchEvent(array $payload, ?User $author = null): InAppNotification
    {
        $partnerIds = array_values(array_unique(array_filter(array_map('intval', $payload['partner_ids'] ?? []))));
        if ($partnerIds === []) {
            throw new InvalidArgumentException('Не выбраны школы для рассылки.');
        }

        $roleIds = array_values(array_unique(array_filter(array_map('intval', $payload['role_ids'] ?? []))));
        if ($roleIds === []) {
            throw new InvalidArgumentException('Не выбраны роли для рассылки.');
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $body = (string) ($payload['body'] ?? '');
        if ($title === '' || InAppNotificationBodyHtml::isBlank($body)) {
            throw new InvalidArgumentException('Пустые заголовок или текст уведомления.');
        }

        $category = (string) ($payload['category'] ?? InAppNotification::CATEGORY_NORMAL);
        if (! in_array($category, InAppNotification::CATEGORIES, true)) {
            throw new InvalidArgumentException('Некорректный тип уведомления.');
        }

        $ttlPreset = (string) ($payload['ttl_preset'] ?? InAppNotification::TTL_30D);

        $notification = InAppNotification::query()->create([
            'category' => $category,
            'source' => InAppNotification::SOURCE_EVENT,
            'title' => mb_substr($title, 0, 160),
            'body' => InAppNotificationBodyHtml::sanitize($body),
            'action_url' => null,
            'is_global' => false,
            'audience_role_ids' => $roleIds,
            'ttl_preset' => $ttlPreset,
            'expires_at' => $this->resolveExpiresAt(
                $ttlPreset,
                $payload['custom_expires_at'] ?? null
            ),
            'status' => InAppNotification::STATUS_PENDING,
            'recipients_count' => 0,
            'created_by' => $author?->id,
        ]);

        $notification->partners()->sync($partnerIds);

        FanOutInAppNotificationJob::dispatch($notification->id);

        return $notification;
    }

    public function resolveExpiresAt(string $preset, ?string $customDate): ?Carbon
    {
        return match ($preset) {
            InAppNotification::TTL_1D => now()->addDay(),
            InAppNotification::TTL_7D => now()->addDays(7),
            InAppNotification::TTL_30D => now()->addDays(30),
            InAppNotification::TTL_CUSTOM => $this->endOfCustomDate($customDate),
            InAppNotification::TTL_UNTIL_READ => null,
            default => throw new InvalidArgumentException('Некорректный срок жизни уведомления.'),
        };
    }

    private function endOfCustomDate(?string $customDate): Carbon
    {
        if ($customDate === null || trim($customDate) === '') {
            throw new InvalidArgumentException('Укажите дату окончания показа.');
        }

        return Carbon::parse($customDate, 'Europe/Moscow')->endOfDay();
    }
}
