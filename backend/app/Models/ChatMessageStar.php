<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessageStar extends Model
{
    public $timestamps = false;

    protected $fillable = ['message_id', 'user_type', 'user_id', 'starred_at'];

    protected $casts = ['starred_at' => 'datetime'];

    public function message()
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}
