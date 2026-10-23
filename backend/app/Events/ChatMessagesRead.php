<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessagesRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $readerId,
        public readonly string $readerType,
        public readonly int    $senderId,
        public readonly string $senderType,
        public readonly string $chatRoomId,
    ) {}

    public function broadcastOn(): array
    {
        // Notify the original sender that their messages have been read.
        // Sender could be Admin (User model) or Sales.
        if ($this->senderType === 'App\\Models\\User') {
            return [new PrivateChannel("chat.user.{$this->senderId}")];
        }
        if ($this->senderType === 'App\\Models\\Sales') {
            return [new PrivateChannel("chat.sales.{$this->senderId}")];
        }

        return [];
    }

    public function broadcastAs(): string
    {
        return 'ChatMessagesRead';
    }

    public function broadcastWith(): array
    {
        return [
            'reader_id'   => $this->readerId,
            'reader_type' => $this->readerType,
            'chat_room_id' => $this->chatRoomId,
        ];
    }
}
