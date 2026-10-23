<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatGroupCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $groupId,
        public readonly string $groupNama,
        public readonly array  $memberUserIds,
    ) {}

    public function broadcastOn(): array
    {
        return array_map(
            fn($userId) => new Channel('chat.user.' . $userId),
            $this->memberUserIds
        );
    }

    public function broadcastAs(): string
    {
        return 'ChatGroupCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'group_id'   => $this->groupId,
            'group_nama' => $this->groupNama,
        ];
    }
}
