<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InAppNotificationBellUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<int>  $userIds
     * @param  list<int>  $partnerIds
     */
    public function __construct(
        public array $userIds,
        public bool $isGlobal,
        public array $partnerIds,
        public int $notificationId,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $this->userIds))));

        return array_map(
            static fn (int $userId) => new PrivateChannel('user.'.$userId),
            $ids
        );
    }

    public function broadcastAs(): string
    {
        return 'in-app-notification.bell';
    }

    /**
     * @return array{notification_id: int, is_global: bool, partner_ids: list<int>}
     */
    public function broadcastWith(): array
    {
        return [
            'notification_id' => $this->notificationId,
            'is_global' => $this->isGlobal,
            'partner_ids' => $this->isGlobal
                ? []
                : array_values(array_unique(array_map('intval', $this->partnerIds))),
        ];
    }
}
