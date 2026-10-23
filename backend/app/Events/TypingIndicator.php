<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingIndicator implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $userType;
    public $userName;
    public $chatRoomId;
    public $isTyping;

    public function __construct($userId, $userType, $userName, $chatRoomId, $isTyping)
    {
        $this->userId = $userId;
        $this->userType = $userType;
        $this->userName = $userName;
        $this->chatRoomId = $chatRoomId;
        $this->isTyping = $isTyping;
    }

    public function broadcastOn(): array
    {
        $roomId = (string) $this->chatRoomId;

        if (str_starts_with($roomId, 'group:')) {
            return [new PrivateChannel('chat.group.' . substr($roomId, 6))];
        }

        if (str_starts_with($roomId, 'user:')) {
            return [new PrivateChannel('chat.user.' . substr($roomId, 5))];
        }

        if (str_starts_with($roomId, 'sales:')) {
            return [new PrivateChannel('chat.sales.' . substr($roomId, 6))];
        }

        // Fallback: do not broadcast on an unknown typing target
        return [];
    }

    public function broadcastAs() {
        return 'typing';
    }

    public function broadcastWith() {
        return [
            'user_id' => $this->userId,
            'user_type' => $this->userType,
            'user_name' => $this->userName,
            'is_typing' => $this->isTyping,
            'chat_room_id' => $this->chatRoomId,
        ];
    }

}
