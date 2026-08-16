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
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('thread.'.$this->threadId),
            new PrivateChannel('inbox.'.$this->userId),
        ];
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
