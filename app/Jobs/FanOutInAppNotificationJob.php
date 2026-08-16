<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\InAppNotificationBellUpdated;
use App\Models\InAppNotification;
use App\Models\InAppNotificationRecipient;
use App\Models\Partner;
use App\Services\InAppNotifications\InAppNotificationAudience;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class FanOutInAppNotificationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $notificationId,
    ) {}

    public function uniqueId(): string
    {
        return 'in-app-notification-fanout-'.$this->notificationId;
    }

    public function handle(InAppNotificationAudience $audience): void
    {
        $claimed = InAppNotification::query()
            ->whereKey($this->notificationId)
            ->where('status', InAppNotification::STATUS_PENDING)
            ->update(['status' => InAppNotification::STATUS_DISPATCHING]);

        if ($claimed === 0) {
            $existing = InAppNotification::query()->find($this->notificationId);
            if ($existing === null || $existing->status === InAppNotification::STATUS_DISPATCHED) {
                return;
            }

            if ($existing->status !== InAppNotification::STATUS_DISPATCHING) {
                return;
            }
        }

        $notification = InAppNotification::query()->find($this->notificationId);
        if ($notification === null) {
            return;
        }

        try {
            $partnerIds = $notification->is_global
                ? Partner::query()->pluck('id')->map(fn ($id) => (int) $id)->all()
                : $notification->partners()->pluck('partners.id')->map(fn ($id) => (int) $id)->all();

            $roleIds = array_map('intval', $notification->audience_role_ids ?? []);
            $userIds = $audience->resolveRecipientUserIds($partnerIds, $roleIds);

            $now = now();
            foreach (array_chunk($userIds, 500) as $chunk) {
                $rows = [];
                foreach ($chunk as $userId) {
                    $rows[] = [
                        'in_app_notification_id' => $notification->id,
                        'user_id' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                InAppNotificationRecipient::query()->insertOrIgnore($rows);
            }

            $recipientsCount = $notification->recipients()->count();

            $notification->update([
                'status' => InAppNotification::STATUS_DISPATCHED,
                'recipients_count' => $recipientsCount,
                'dispatched_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ]);

            $broadcastUserIds = array_values(array_unique(array_merge(
                $userIds,
                $audience->superadminUserIds()
            )));

            $broadcastPartnerIds = $notification->is_global ? [] : $partnerIds;

            foreach (array_chunk($broadcastUserIds, 50) as $chunk) {
                if ($chunk === []) {
                    continue;
                }
                event(new InAppNotificationBellUpdated(
                    $chunk,
                    (bool) $notification->is_global,
                    $broadcastPartnerIds,
                    (int) $notification->id,
                ));
            }
        } catch (Throwable $e) {
            Log::error('[FanOutInAppNotificationJob] failed', [
                'notification_id' => $this->notificationId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        $notification = InAppNotification::query()->find($this->notificationId);
        if ($notification === null) {
            return;
        }

        if ($notification->status === InAppNotification::STATUS_DISPATCHED) {
            return;
        }

        $notification->update([
            'status' => InAppNotification::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => mb_substr($e?->getMessage() ?? 'unknown', 0, 2000),
        ]);
    }
}
