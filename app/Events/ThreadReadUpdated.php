<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThreadReadUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

public int $threadId;
public int $userId;

    public function __construct(int $threadId, int $userId)
    {
        $this->threadId = $threadId;
        $this->userId   = $userId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('thread.' . $this->threadId)];
    }

    public function broadcastAs(): string
    {
        return 'thread.read';
    }

    public function broadcastWith(): array
    {
        return ['userId' => $this->userId];
    }
}
