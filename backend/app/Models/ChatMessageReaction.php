<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessageReaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'message_id', 'emoji', 'user_type', 'user_id', 'reacted_at',
    ];

    protected $casts = [
        'reacted_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}
