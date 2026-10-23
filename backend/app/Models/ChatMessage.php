<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = [
        'sender_type', 'sender_id',
        'receiver_type', 'receiver_id',
        'group_id', 'type', 'message_type',
        'message', 'file_url', 'file_name',
        'file_path', 'thumb_path', 'poll_id', 'reply_to_id',
        'is_deleted', 'is_edited', 'edited_at',
        'forwarded_from_id', 'mentions',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'is_edited'  => 'boolean',
        'mentions'   => 'array',
    ];

    public function reads()
    {
        return $this->hasMany(ChatMessageRead::class, 'message_id');
    }

    public function isReadBy(string $readerType, int $readerId): bool
    {
        return $this->reads()->where('reader_type', $readerType)->where('reader_id', $readerId)->exists();
    }

    public function senderUser()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function senderSales()
    {
        return $this->belongsTo(Sales::class, 'sender_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(ChatMessage::class, 'reply_to_id');
    }

    public function forwardedFrom()
    {
        return $this->belongsTo(ChatMessage::class, 'forwarded_from_id');
    }
}
