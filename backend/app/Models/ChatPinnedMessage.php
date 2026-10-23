<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatPinnedMessage extends Model
{
    protected $fillable = [
        'room_type', 'room_key',
        'message_id', 'pinned_by_type', 'pinned_by_id',
    ];

    public function message()
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}
