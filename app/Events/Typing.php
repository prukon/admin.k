<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Typing implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

public int $threadId;
public int $userId;
public bool $isTyping;

    public function __construct(int $threadId, int $userId, bool $isTyping)
    {
        $this->threadId = $threadId;
        $this->userId   = $userId;
        $this->isTyping = $isTyping;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('thread.' . $this->threadId)];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    public function broadcastWith(): array
    {
        return ['userId' => $this->userId, 'isTyping' => $this->isTyping];
    }
}
