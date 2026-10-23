<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageReacted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $channels;
    public int   $messageId;
    public array $reactions;

    public function __construct(array $channels, int $messageId, array $reactions)
    {
        $this->channels  = $channels;
        $this->messageId = $messageId;
        $this->reactions = $reactions;
    }

    public function broadcastOn(): array
    {
        return array_map(fn($ch) => new PrivateChannel($ch), $this->channels);
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'reactions'  => $this->reactions,
        ];
    }
}
