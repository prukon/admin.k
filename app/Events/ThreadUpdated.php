<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThreadUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

public int $threadId;
public array $payload;
    /** @var int[] */
public array $recipients;

    public function __construct(int $threadId, array $payload)
    {
        $this->threadId  = $threadId;
        $this->payload   = $payload;
        $this->recipients = $payload['recipients'] ?? [];
        unset($this->payload['recipients']);
    }

    public function broadcastOn(): array
    {
        // рассылаем всем участникам в их персональные каналы
        return array_map(fn($uid) => new PrivateChannel('user.' . (int)$uid), $this->recipients);
    }

    public function broadcastAs(): string
    {
        return 'thread.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'threadId' => $this->threadId,
            'payload'  => $this->payload,
        ];
    }
}
