<?php

namespace App\Jobs;

use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\Sales;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendChatNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private ChatMessage $message) {}

    public function handle(): void
    {
        $msg         = $this->message;
        $senderClass = $msg->sender_type;
        $senderId    = $msg->sender_id;

        $senderName = $senderClass === User::class
            ? (User::find($senderId)?->name ?? 'Admin')
            : (Sales::find($senderId)?->nama ?? 'Sales');

        $body = match ($msg->message_type) {
            'image' => '📷 Foto',
            'file'  => '📎 File',
            'audio' => '🎙️ Pesan suara',
            'poll'  => '📊 Polling',
            default => $msg->message,
        };

        $data = [
            'type'       => $msg->type,
            'message_id' => (string) $msg->id,
        ];

        if ($msg->type === 'group') {
            $this->notifyGroup($msg, $senderClass, $senderId, $senderName, $body, $data);
        } else {
            $this->notifyDm($msg, $senderClass, $senderId, $senderName, $body, $data);
        }
    }

    private function notifyDm(ChatMessage $msg, string $senderClass, int $senderId, string $senderName, string $body, array $data): void
    {
        $data['chat_type']    = 'dm';
        $data['sender_id']    = (string) $senderId;
        $data['sender_type']  = $senderClass;

        $token = $this->getToken($msg->receiver_type, $msg->receiver_id);
        if ($token) {
            FcmService::sendNotification($token, $senderName, $body, $data);
        }
    }

    private function notifyGroup(ChatMessage $msg, string $senderClass, int $senderId, string $senderName, string $body, array $data): void
    {
        $data['chat_type'] = 'group';
        $data['group_id']  = (string) $msg->group_id;

        $members = ChatGroupMember::where('group_id', $msg->group_id)
            ->where(fn($q) => $q->where('member_type', '!=', $senderClass)->orWhere('member_id', '!=', $senderId))
            ->get();

        foreach ($members as $member) {
            $token = $this->getToken($member->member_type, $member->member_id);
            if ($token) {
                FcmService::sendNotification($token, $senderName, $body, $data);
            }
        }
    }

    private function getToken(string $modelClass, int $modelId): ?string
    {
        $model = match ($modelClass) {
            User::class  => User::find($modelId),
            Sales::class => Sales::find($modelId),
            default      => null,
        };

        return $model?->fcm_token ?: null;
    }
}
