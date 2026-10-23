<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatPollVote extends Model
{
    protected $fillable = ['poll_id', 'voter_type', 'voter_id', 'pilihan_index'];
}
