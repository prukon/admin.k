<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // NOW!
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

public int $threadId;
public array $payload;

    public function __construct(int $threadId, array $messagePayload)
    {
        $this->threadId = $threadId;
        $this->payload  = ['message' => $messagePayload];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('thread.' . $this->threadId)];
    }

    public function broadcastAs(): string
    {
        return 'message.created'; // <-- под твой JS
    }

    public function broadcastWith(): array
    {
        return $this->payload; // { message: {...} }
    }
}
