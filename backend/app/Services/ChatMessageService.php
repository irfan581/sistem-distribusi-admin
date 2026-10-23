<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Events\ChatMessageSent;
use Illuminate\Support\Facades\Auth;

class ChatMessageService
{
    public function sendMessage(array $data): ChatMessage
    {
        $user = Auth::user();
        $filePath = null;
        $msgType = 'text';
        $msgText = $data['message'] ?? '';

        if (!empty($data['file_path'])) {
            $mime = $data['file_mime'] ?? '';
            if (str_starts_with($mime, 'image/')) {
                $msgType = 'image';
            } else {
                $msgType = 'file';
            }
            $filePath = $data['file_path'];
        }

        $messageData = [
            'sender_type'  => get_class($user),
            'sender_id'    => $user->id,
            'type'         => $data['chat_type'],
            'message_type' => $msgType,
            'message'      => $msgText,
            'file_path'    => $filePath,
        ];

        if ($data['chat_type'] === 'group') {
            $messageData['group_id'] = $data['chat_id'];
        } else {
            $messageData['receiver_type'] = $data['receiver_type'];
            $messageData['receiver_id'] = $data['chat_id'];
        }

        $chat = ChatMessage::create($messageData);
        broadcast(new ChatMessageSent($chat));

        return $chat;
    }

    public function deleteMessage(int $messageId, bool $permanent = false): void
    {
        $message = ChatMessage::find($messageId);
        if (!$message) return;

        if ($permanent) {
            $message->delete();
        } else {
            $message->update(['is_deleted' => true]);
        }
    }

    public function editMessage(int $messageId, string $newMessage): void
    {
        ChatMessage::find($messageId)?->update([
            'message' => $newMessage,
            'is_edited' => true,
        ]);
    }

    public function loadMessages(int $chatId, string $chatType, string $search = ''): array
    {
        $query = ChatMessage::where('is_deleted', false);

        if ($chatType === 'group') {
            $query->where('type', 'group')->where('group_id', $chatId);
        } else {
            $query->where('type', 'dm')
                ->where(function($q) use ($chatId) {
                    $q->where([
                        'sender_id' => Auth::id(),
                        'receiver_id' => $chatId,
                    ])->orWhere([
                        'sender_id' => $chatId,
                        'receiver_id' => Auth::id(),
                    ]);
                });
        }

        if ($search) {
            $query->where('message_type', 'text')
                  ->where('message', 'like', "%{$search}%");
        }

        $messages = $query->latest()->get();
        $user = Auth::user();

        return $messages->map(function ($msg) use ($user) {
            return [
                'id' => $msg->id,
                'message' => $msg->message,
                'message_type' => $msg->message_type,
                'sender_name' => $msg->sender_name,
                'time' => $msg->created_at->format('H:i'),
                'date' => $msg->created_at->toDateString(),
                'is_me' => $msg->sender_id === $user->id && get_class($msg->sender) === get_class($user),
                'is_deleted' => $msg->is_deleted,
                'is_edited' => $msg->is_edited,
                'file_url' => \App\Support\BerkasChat::url($msg),
                'thumb_url' => \App\Support\BerkasChat::thumb($msg),
                'file_name' => basename($msg->file_path ?? ''),
                'poll' => $msg->poll ? $this->formatPoll($msg->poll) : null,
            ];
        })->values()->all();
    }

    private function formatPoll($poll): array
    {
        return [
            'id' => $poll->id,
            'pertanyaan' => $poll->pertanyaan,
            'pilihan' => json_decode($poll->pilihan, true),
            'hasil' => collect(json_decode($poll->hasil, true))->map(function($item) {
                return [
                    'index' => $item['index'],
                    'percentage' => $item['percentage'],
                ];
            })->toArray(),
            'total_votes' => $poll->total_votes,
            'is_closed' => $poll->is_closed,
            'sudah_vote' => in_array(Auth::id(), json_decode($poll->users_voted, true) ?? []),
            'votes_saya' => json_decode($poll->votes_saya ?? '[]', true),
        ];
    }
}
