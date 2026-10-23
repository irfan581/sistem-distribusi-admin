<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $message;

    public function __construct(ChatMessage $chat)
    {
        // Lewat PengirimChat supaya pesan sopir tidak tampil memakai nama
        // sales yang kebetulan ber-id sama.
        $senderName = \App\Support\PengirimChat::nama($chat->sender_type, $chat->sender_id);

        // Resolve reply_to if present — load fresh to avoid unloaded relation crash
        $replyTo = null;
        if ($chat->reply_to_id) {
            $chat->loadMissing('replyTo');
        }
        if ($chat->reply_to_id && $chat->replyTo) {
            $r = $chat->replyTo;
            $replySender = \App\Support\PengirimChat::nama($r->sender_type, $r->sender_id);
            $replyTo = [
                'sender_name'  => $replySender,
                'message'      => $r->message,
                'message_type' => $r->message_type,
                'file_name'    => $r->file_name,
                'is_deleted'   => $r->is_deleted,
            ];
        }

        $this->message = [
            'id'             => $chat->id,
            'sender_id'      => $chat->sender_id,
            'sender_type'    => $chat->sender_type,
            'sender_name'    => $senderName,
            // Disamakan dengan bentuk REST (ChatController::formatMessage)
            // supaya aplikasi tidak perlu dua cara membaca pesan yang sama.
            'sender_role'    => \App\Support\PengirimChat::peran($chat->sender_type),
            'sender_initial' => strtoupper(substr($senderName, 0, 1)),
            'message'        => $chat->message,
            'message_type'   => $chat->message_type,
            // Dulu kolom file_url yang dibaca di sini, padahal jalur sopir
            // dan sales tidak pernah mengisinya — seluruh foto laporan
            // bongkar datang tanpa gambar, dan baru muncul setelah chat
            // dibuka ulang.
            'file_url'       => \App\Support\BerkasChat::url($chat),
            'thumb_url'      => \App\Support\BerkasChat::thumb($chat),
            'file_name'      => $chat->file_name,
            'type'           => $chat->type,
            'group_id'       => $chat->group_id,
            'receiver_id'    => $chat->receiver_id,
            'receiver_type'  => $chat->receiver_type,
            'reply_to_id'    => $chat->reply_to_id,
            'reply_to'       => $replyTo,
            'is_deleted'     => false,
            'is_edited'      => false,
            'is_me'          => false,
            'created_at'     => $chat->created_at->setTimezone('Asia/Jakarta')->toISOString(),
            'time'           => $chat->created_at->setTimezone('Asia/Jakarta')->format('H:i'),
        ];
    }

    public function broadcastOn(): array
    {
        $chat = $this->message;

        if ($chat['type'] === 'group') {
            return [
                new PrivateChannel("chat.group.{$chat['group_id']}"),
            ];
        }

        // DM: broadcast ke receiver channel dan sender channel.
        // Channel name berdasarkan model type:
        //   App\Models\User  → chat.user.{id}   (Admin — Vue dashboard)
        //   App\Models\Sales → chat.sales.{id}  (Sales — Flutter app)
        $receiverChannel = $chat['receiver_type'] === 'App\\Models\\Sales'
            ? "chat.sales.{$chat['receiver_id']}"
            : "chat.user.{$chat['receiver_id']}";

        $senderChannel = $chat['sender_type'] === 'App\\Models\\Sales'
            ? "chat.sales.{$chat['sender_id']}"
            : "chat.user.{$chat['sender_id']}";

        return [
            new PrivateChannel($receiverChannel),
            new PrivateChannel($senderChannel),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ChatMessageSent';
    }
}
