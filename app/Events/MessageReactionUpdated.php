<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReactionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $reactions
     */
    public function __construct(
        public int $threadId,
        public int $messageId,
        public array $reactions,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('thread.'.$this->threadId)];
    }

    public function broadcastAs(): string
    {
        return 'message.reaction';
    }

    /**
     * @return array{message_id: int, reactions: list<array<string, mixed>>}
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'reactions' => $this->reactions,
        ];
    }
}
