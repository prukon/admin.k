<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InboxBump implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

public int $userId;
public array $payload;

    public function __construct(int $userId, array $payload)
    {
        $this->userId  = $userId;
        $this->payload = $payload;
    }

    public function broadcastOn(): array
    {
        return [ new PrivateChannel('inbox.' . $this->userId) ];
    }

    public function broadcastAs(): string
    {
        return 'inbox.bump';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
