<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThreadReadUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $threadId,
        public int $userId,
        public int $unreadTotal,
        public array $inboxUserIds = [],
    ) {
        if ($this->inboxUserIds === []) {
            $this->inboxUserIds = [$this->userId];
        }
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('thread.'.$this->threadId)];
        foreach (array_unique($this->inboxUserIds) as $inboxUserId) {
            $channels[] = new PrivateChannel('inbox.'.(int) $inboxUserId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'thread.read';
    }

    /**
     * @return array{thread_id: int, user_id: int, unread_total: int}
     */
    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->threadId,
            'user_id' => $this->userId,
            'unread_total' => $this->unreadTotal,
        ];
    }
}
