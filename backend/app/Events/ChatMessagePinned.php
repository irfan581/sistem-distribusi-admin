<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessagePinned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $channels;
    public ?array $pinned; // null = unpin

    /**
     * @param array        $channels  list of channel names to broadcast on
     * @param array|null   $pinned    formatted pin data, null when unpinning
     */
    public function __construct(array $channels, ?array $pinned)
    {
        $this->channels = $channels;
        $this->pinned   = $pinned;
    }

    public function broadcastOn(): array
    {
        return array_map(fn($ch) => new PrivateChannel($ch), $this->channels);
    }

    public function broadcastWith(): array
    {
        return ['pinned' => $this->pinned];
    }
}
