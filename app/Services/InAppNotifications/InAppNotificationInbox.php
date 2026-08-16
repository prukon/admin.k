<?php

declare(strict_types=1);

namespace App\Services\InAppNotifications;

use App\Models\InAppNotification;
use App\Models\InAppNotificationRead;
use App\Models\User;
use App\Services\PartnerContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class InAppNotificationInbox
{
    public function __construct(
        private readonly PartnerContext $partnerContext,
    ) {}

    public function unreadCount(User $user): int
    {
        return $this->visibleQuery($user)
            ->whereDoesntHave('reads', function (Builder $q) use ($user): void {
                $q->where('user_id', $user->id);
            })
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function latestItems(User $user, int $limit): array
    {
        $rows = $this->visibleQuery($user)
            ->withExists(['reads as is_read' => function (Builder $q) use ($user): void {
                $q->where('user_id', $user->id);
            }])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn (InAppNotification $row) => $this->serialize($row, $user))->all();
    }

    public function paginate(User $user, int $perPage): LengthAwarePaginator
    {
        $paginator = $this->visibleQuery($user)
            ->withExists(['reads as is_read' => function (Builder $q) use ($user): void {
                $q->where('user_id', $user->id);
            }])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginator->getCollection()->transform(
            fn (InAppNotification $row) => $this->serialize($row, $user)
        );

        return $paginator;
    }

    public function findVisible(User $user, int $notificationId): ?InAppNotification
    {
        return $this->visibleQuery($user)
            ->whereKey($notificationId)
            ->first();
    }

    public function markRead(User $user, InAppNotification $notification): void
    {
        InAppNotificationRead::query()->insertOrIgnore([
            [
                'in_app_notification_id' => $notification->id,
                'user_id' => $user->id,
                'read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function markAllRead(User $user): int
    {
        $ids = $this->visibleQuery($user)
            ->whereDoesntHave('reads', function (Builder $q) use ($user): void {
                $q->where('user_id', $user->id);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return 0;
        }

        $now = now();
        $rows = [];
        foreach ($ids as $id) {
            $rows[] = [
                'in_app_notification_id' => $id,
                'user_id' => $user->id,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            InAppNotificationRead::query()->insertOrIgnore($chunk);
        }

        return count($ids);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(InAppNotification $notification, User $user): array
    {
        $isRead = (bool) ($notification->getAttribute('is_read') ?? $notification->reads()
            ->where('user_id', $user->id)
            ->exists());

        $bodyHtml = InAppNotificationBodyHtml::toDisplayHtml((string) $notification->body);

        return [
            'id' => (int) $notification->id,
            'title' => (string) $notification->title,
            'body' => $bodyHtml,
            'body_preview' => InAppNotificationBodyHtml::preview(
                $bodyHtml,
                (int) config('in_app_notifications.dropdown_preview_limit', 60)
            ),
            'category' => (string) $notification->category,
            'category_label' => $notification->categoryLabel(),
            'is_read' => $isRead,
            'created_at' => optional($notification->created_at)?->toIso8601String(),
            'created_at_human' => $notification->created_at
                ? $notification->created_at->timezone('Europe/Moscow')->locale('ru')->translatedFormat('j F Y')
                : '',
            'page_url' => route('inAppNotifications.index', ['n' => $notification->id]),
        ];
    }

    private function visibleQuery(User $user): Builder
    {
        $query = InAppNotification::query()
            ->dispatched()
            ->notExpired();

        if ($this->partnerContext->isSuperAdmin($user)) {
            $partnerId = $this->partnerContext->partnerId();

            return $query->where(function (Builder $q) use ($partnerId): void {
                $q->where('is_global', true);
                if ($partnerId) {
                    $q->orWhereHas('partners', function (Builder $partners) use ($partnerId): void {
                        $partners->where('partners.id', $partnerId);
                    });
                }
            });
        }

        return $query->whereHas('recipients', function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id);
        });
    }
}
